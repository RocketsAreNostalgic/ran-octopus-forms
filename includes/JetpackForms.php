<?php
/**
 * Jetpack Forms integration.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

namespace RAN\EmailOctopusJetpackForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridges Jetpack Forms submissions to site-owned integrations.
 */
final class JetpackForms {
	/**
	 * Nonce field signing the portable form context.
	 */
	const TARGET_FIELD = 'ran_octopus_forms_target';

	/**
	 * Portable integration profile field.
	 */
	const PROFILE_FIELD = 'ran_emailoctopus_jetpack_forms_profile';

	/**
	 * Portable saved-form reference field.
	 */
	const FORM_REF_FIELD = 'ran_emailoctopus_jetpack_forms_form_ref';

	/**
	 * Active portable render context.
	 *
	 * @var array{profile_id:string,form_ref:int,depth:int}|null
	 */
	private static $portable_render_context = null;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'pre_render_block', array( __CLASS__, 'before_render_block' ), 10, 2 );
		add_filter( 'render_block', array( __CLASS__, 'after_render_block' ), 10, 2 );
		add_filter( 'jetpack_forms_enable_ajax_submission', array( __CLASS__, 'disable_ajax_for_contact_form' ) );
		add_filter( 'jetpack_contact_form_html', array( __CLASS__, 'mark_target_form_submission' ) );
		add_filter( 'grunion_contact_form_redirect_url', array( __CLASS__, 'redirect_contact_form' ), 10, 3 );
		add_action( 'grunion_after_message_sent', array( __CLASS__, 'subscribe_newsletter_opt_in' ), 10, 7 );
		SubmissionMessages::register();
	}

	/**
	 * Enter the target form's server-side block-render context.
	 *
	 * @param string|null         $pre_render   Existing pre-rendered content.
	 * @param array<string,mixed> $parsed_block Parsed block data.
	 * @return string|null
	 */
	public static function before_render_block( $pre_render, $parsed_block ) {
		if ( is_admin() ) {
			return $pre_render;
		}

		if ( ! IntegrationResolver::is_portable_available() ) {
			return $pre_render;
		}

		$form_ref = self::get_block_form_ref( $parsed_block );

		if ( 0 < $form_ref && IntegrationResolver::is_target_form_id( $form_ref ) ) {
			$profile = IntegrationResolver::get_default_profile();

			if ( null !== self::$portable_render_context && $form_ref === self::$portable_render_context['form_ref'] ) {
				++self::$portable_render_context['depth'];
			} else {
				self::$portable_render_context = array(
					'profile_id' => $profile->get_id(),
					'form_ref'   => $form_ref,
					'depth'      => 1,
				);
			}
		}

		return $pre_render;
	}

	/**
	 * Leave the target form's server-side block-render context.
	 *
	 * @param string              $block_content Rendered block content.
	 * @param array<string,mixed> $parsed_block   Parsed block data.
	 * @return string
	 */
	public static function after_render_block( $block_content, $parsed_block ) {
		$form_ref = self::get_block_form_ref( $parsed_block );

		if ( null !== self::$portable_render_context && $form_ref === self::$portable_render_context['form_ref'] ) {
			--self::$portable_render_context['depth'];

			if ( 0 >= self::$portable_render_context['depth'] ) {
				self::$portable_render_context = null;
			}
		}

		return $block_content;
	}

	/**
	 * Disable Jetpack AJAX only for the resolved integration target.
	 *
	 * Jetpack returns JSON for AJAX submissions before its redirect hook runs,
	 * so the success-page redirect requires a normal form post.
	 *
	 * @param bool $enabled Whether AJAX form submission is enabled.
	 * @return bool
	 */
	public static function disable_ajax_for_contact_form( $enabled ) {
		if ( null !== self::$portable_render_context || self::is_target_submission() ) {
			return false;
		}

		return $enabled;
	}

	/**
	 * Add signed integration context to the resolved Jetpack form.
	 *
	 * @param string $form_html Rendered Jetpack form HTML.
	 * @return string
	 */
	public static function mark_target_form_submission( $form_html ) {
		if ( ! self::is_target_form_html( $form_html ) ) {
			return $form_html;
		}

		$profile_id = self::$portable_render_context['profile_id'];
		$form_ref   = self::$portable_render_context['form_ref'];
		$marker     = sprintf(
			'<input type="hidden" name="%1$s" value="%2$s" /><input type="hidden" name="%3$s" value="%4$d" /><input type="hidden" name="%5$s" value="%6$s" />',
			esc_attr( self::PROFILE_FIELD ),
			esc_attr( $profile_id ),
			esc_attr( self::FORM_REF_FIELD ),
			$form_ref,
			esc_attr( self::TARGET_FIELD ),
			esc_attr( wp_create_nonce( self::get_portable_nonce_action( $profile_id, $form_ref ) ) )
		);

		return str_replace( '</form>', $marker . '</form>', $form_html );
	}

	/**
	 * Whether rendered form markup belongs to the configured integration.
	 *
	 * @param string $form_html Rendered Jetpack form HTML.
	 * @return bool
	 */
	public static function is_target_form_html( $form_html ) {
		unset( $form_html );

		return IntegrationResolver::is_portable_available() && null !== self::$portable_render_context;
	}

	/**
	 * Whether the request is a signed submission from the configured form.
	 *
	 * Supplying a feedback post ID additionally verifies Jetpack's authoritative
	 * saved-form identity before any external side effect or redirect.
	 *
	 * @param int $feedback_id Optional Jetpack feedback post ID.
	 * @return bool
	 */
	public static function is_target_submission( $feedback_id = 0 ) {
		$context = self::get_submitted_context();

		if ( null === $context ) {
			return false;
		}

		if ( 0 >= absint( $feedback_id ) ) {
			return true;
		}

		return self::feedback_matches_context( absint( $feedback_id ), $context );
	}

	/**
	 * Redirect a successfully handled target form to the success page.
	 *
	 * @param string $redirect Existing redirect URL.
	 * @param int    $id       Jetpack contact form route ID.
	 * @param int    $post_id  Feedback post ID.
	 * @return string
	 */
	public static function redirect_contact_form( $redirect, $id, $post_id ) {
		unset( $id );

		$context = self::get_submitted_context();

		if ( null === $context || ! self::feedback_matches_context( absint( $post_id ), $context ) ) {
			return $redirect;
		}

		$success_url = Settings::get_success_url();

		if ( '' === $success_url ) {
			return $redirect;
		}

		return SubmissionMessages::add_result_to_redirect( $success_url, $post_id, $context['profile_id'] );
	}

	/**
	 * Subscribe opted-in Jetpack form submissions to EmailOctopus.
	 *
	 * @param int          $post_id      Feedback post ID.
	 * @param string|array $to           Email recipients.
	 * @param string       $subject      Email subject.
	 * @param string       $message      Email message.
	 * @param string|array $headers      Email headers.
	 * @param array        $all_values   Contact form fields.
	 * @param array        $extra_values Extra contact form fields.
	 * @return void
	 */
	public static function subscribe_newsletter_opt_in( $post_id, $to, $subject, $message, $headers, $all_values, $extra_values ) {
		unset( $to, $subject, $message, $headers, $extra_values );

		$context = self::get_submitted_context();

		if ( null === $context || ! self::feedback_matches_context( absint( $post_id ), $context ) ) {
			return;
		}

		if ( in_array( get_post_status( $post_id ), array( 'spam', 'trash' ), true ) ) {
			return;
		}

		if ( ! self::has_newsletter_opt_in( $all_values ) ) {
			return;
		}

		$email_address = EmailOctopusFieldMapper::get_email_address( $all_values );

		if ( '' === $email_address ) {
			update_post_meta( $post_id, '_ran_emailoctopus_subscription_status', 'failed' );
			return;
		}

		$result = ( new EmailOctopusSubscriber( $context['profile_id'] ) )->subscribe( $email_address, $all_values );

		if ( is_wp_error( $result ) ) {
			update_post_meta( $post_id, '_ran_emailoctopus_subscription_status', 'failed' );
			update_post_meta(
				$post_id,
				'_ran_emailoctopus_subscription_error',
				array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				)
			);
			return;
		}

		update_post_meta( $post_id, '_ran_emailoctopus_subscription_status', sanitize_key( $result['outcome'] ?? 'failed' ) );
	}

	/**
	 * Parse and verify the submitted integration context.
	 *
	 * @return array{profile_id:string,form_ref:int}|null
	 */
	private static function get_submitted_context() {
		if ( ! isset( $_POST[ self::TARGET_FIELD ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verification occurs below.
			return null;
		}

		if ( ! isset( $_POST[ self::PROFILE_FIELD ], $_POST[ self::FORM_REF_FIELD ] ) || ! IntegrationResolver::is_portable_available() ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- signed context is verified below.
			return null;
		}

		$nonce      = sanitize_text_field( wp_unslash( $_POST[ self::TARGET_FIELD ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- this is the nonce being verified.
		$profile_id = sanitize_key( wp_unslash( $_POST[ self::PROFILE_FIELD ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- signed context is verified below.
		$form_ref   = absint( wp_unslash( $_POST[ self::FORM_REF_FIELD ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- signed context is verified below.
		$profile    = IntegrationResolver::get_profile( $profile_id );

		if ( null === $profile || ! in_array( $form_ref, $profile->get_target_form_ids(), true ) || ! IntegrationResolver::is_target_form_id( $form_ref ) ) {
			return null;
		}

		if ( ! wp_verify_nonce( $nonce, self::get_portable_nonce_action( $profile_id, $form_ref ) ) ) {
			return null;
		}

		return array(
			'profile_id' => $profile_id,
			'form_ref'   => $form_ref,
		);
	}

	/**
	 * Verify Jetpack's authoritative saved-form identity against signed context.
	 *
	 * @param int                                         $feedback_id Feedback post ID.
	 * @param array{profile_id:string,form_ref:int} $context Signed context.
	 * @return bool
	 */
	private static function feedback_matches_context( $feedback_id, $context ) {
		if ( 0 >= $feedback_id ) {
			return false;
		}

		$feedback_form_id = self::get_feedback_form_id( $feedback_id );

		if ( null === $feedback_form_id ) {
			return false;
		}

		return $context['form_ref'] === $feedback_form_id;
	}

	/**
	 * Read the saved-form ID recorded by Jetpack for a feedback post.
	 *
	 * @param int $feedback_id Feedback post ID.
	 * @return int|null
	 */
	private static function get_feedback_form_id( $feedback_id ) {
		$class_name = '\\Automattic\\Jetpack\\Forms\\ContactForm\\Feedback';

		if ( ! class_exists( $class_name ) || ! method_exists( $class_name, 'get' ) ) {
			return null;
		}

		try {
			$feedback = $class_name::get( $feedback_id );

			if ( ! is_object( $feedback ) || ! method_exists( $feedback, 'get_form_id' ) ) {
				return null;
			}

			return absint( $feedback->get_form_id() );
		} catch ( \Throwable $exception ) {
			unset( $exception );
			return null;
		}
	}

	/**
	 * Resolve a saved-form reference from parsed block attributes.
	 *
	 * @param array<string,mixed> $parsed_block Parsed block data.
	 * @return int
	 */
	private static function get_block_form_ref( $parsed_block ) {
		if ( 'jetpack/contact-form' !== ( $parsed_block['blockName'] ?? '' ) ) {
			return 0;
		}

		return absint( $parsed_block['attrs']['ref'] ?? 0 );
	}

	/**
	 * Get the nonce action binding a portable profile and saved form.
	 *
	 * @param string $profile_id Integration profile ID.
	 * @param int    $form_ref   Saved Jetpack form ID.
	 * @return string
	 */
	private static function get_portable_nonce_action( $profile_id, $form_ref ) {
		return 'ran_octopus_forms_target_' . sanitize_key( $profile_id ) . '_' . absint( $form_ref );
	}

	/**
	 * Whether submitted fields include the configured newsletter opt-in.
	 *
	 * @param array $all_values Contact form fields.
	 * @return bool
	 */
	private static function has_newsletter_opt_in( $all_values ) {
		return EmailOctopusFieldMapper::has_truthy_submitted_value( $all_values, Settings::get_newsletter_source() );
	}
}
