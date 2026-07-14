<?php
/**
 * Jetpack Forms integration.
 *
 * @package RAN_Octopus_Forms
 */

namespace RAN\OctopusForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bridges Jetpack Forms submissions to site-owned integrations.
 */
final class JetpackForms {
	/**
	 * Whether the current block render is the marked RAN form.
	 *
	 * Jetpack's AJAX filter does not provide a form ID. Restricting the value to
	 * the server-side block-render window keeps other Jetpack forms on the same
	 * page independent.
	 *
	 * @var bool
	 */
	private static $rendering_target_form = false;

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
	 * Enter the marked form's server-side block-render context.
	 *
	 * @param string|null         $pre_render Existing pre-rendered content.
	 * @param array<string,mixed> $parsed_block Parsed block data.
	 * @return string|null
	 */
	public static function before_render_block( $pre_render, $parsed_block ) {
		if ( ! is_admin() && is_page( Settings::get_contact_page_id() ) && Settings::is_target_contact_form_block( $parsed_block ) ) {
			self::$rendering_target_form = true;
		}

		return $pre_render;
	}

	/**
	 * Leave the marked form's server-side block-render context.
	 *
	 * @param string              $block_content Rendered block content.
	 * @param array<string,mixed> $parsed_block Parsed block data.
	 * @return string
	 */
	public static function after_render_block( $block_content, $parsed_block ) {
		if ( Settings::is_target_contact_form_block( $parsed_block ) ) {
			self::$rendering_target_form = false;
		}

		return $block_content;
	}

	/**
	 * Disable Jetpack's AJAX submission on the Contact Us form.
	 *
	 * Jetpack returns JSON for AJAX submissions before its redirect hook runs,
	 * so the success-page redirect requires a normal form post.
	 *
	 * @param bool $enabled Whether AJAX form submission is enabled.
	 * @return bool
	 */
	public static function disable_ajax_for_contact_form( $enabled ) {
		if ( self::$rendering_target_form || self::is_target_submission() ) {
			return false;
		}

		return $enabled;
	}

	/**
	 * Add a marker nonce to the one Jetpack form owned by this plugin.
	 *
	 * The marker allows submission hooks to distinguish the RAN form from other
	 * Jetpack forms that may share the same page or form-ID prefix.
	 *
	 * @param string $form_html Rendered Jetpack form HTML.
	 * @return string
	 */
	public static function mark_target_form_submission( $form_html ) {
		if ( ! self::is_target_form_html( $form_html ) ) {
			return $form_html;
		}

		$marker = sprintf(
			'<input type="hidden" name="ran_octopus_forms_target" value="%s" />',
			esc_attr( wp_create_nonce( self::get_target_nonce_action() ) )
		);

		return str_replace( '</form>', $marker . '</form>', $form_html );
	}

	/**
	 * Whether rendered form markup belongs to this plugin's marked form.
	 *
	 * @param string $form_html Rendered Jetpack form HTML.
	 * @return bool
	 */
	public static function is_target_form_html( $form_html ) {
		return Settings::has_single_contact_form() && ( self::$rendering_target_form || false !== strpos( $form_html, Settings::TARGET_FORM_CLASS ) );
	}

	/**
	 * Whether the request is a submission from the marked RAN form.
	 *
	 * @return bool
	 */
	public static function is_target_submission() {
		if ( ! Settings::has_single_contact_form() || ! Settings::is_contact_form_id( Settings::get_submitted_form_id() ) || ! isset( $_POST['ran_octopus_forms_target'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verification occurs below.
			return false;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['ran_octopus_forms_target'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- this is the nonce being verified.

		return (bool) wp_verify_nonce( $nonce, self::get_target_nonce_action() );
	}

	/**
	 * Get the nonce action used exclusively for the configured form.
	 *
	 * @return string
	 */
	private static function get_target_nonce_action() {
		return 'ran_octopus_forms_target_' . Settings::get_contact_page_id();
	}

	/**
	 * Redirect the public Contact Us form to the success page.
	 *
	 * @param string $redirect Existing redirect URL.
	 * @param int    $id       Jetpack contact form ID.
	 * @param int    $post_id  Feedback post ID.
	 * @return string
	 */
	public static function redirect_contact_form( $redirect, $id, $post_id ) {
		if ( ! Settings::is_contact_form_id( $id ) || ! self::is_target_submission() ) {
			return $redirect;
		}

		$success_url = Settings::get_success_url();

		if ( '' === $success_url ) {
			return $redirect;
		}

		return SubmissionMessages::add_result_to_redirect( $success_url, $post_id );
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

		if ( ! Settings::is_contact_form_id( Settings::get_submitted_form_id() ) ) {
			return;
		}

		if ( ! self::is_target_submission() ) {
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

		$result = ( new EmailOctopusSubscriber() )->subscribe( $email_address, $all_values );

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
	 * Whether the submitted Jetpack fields include a newsletter opt-in.
	 *
	 * @param array $all_values Contact form fields.
	 * @return bool
	 */
	private static function has_newsletter_opt_in( $all_values ) {
		return EmailOctopusFieldMapper::has_truthy_submitted_value( $all_values, Settings::get_newsletter_source() );
	}
}
