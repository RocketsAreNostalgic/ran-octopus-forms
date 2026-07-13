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
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'jetpack_forms_enable_ajax_submission', array( __CLASS__, 'disable_ajax_for_contact_form' ) );
		add_filter( 'grunion_contact_form_redirect_url', array( __CLASS__, 'redirect_contact_form' ), 10, 3 );
		add_action( 'grunion_after_message_sent', array( __CLASS__, 'subscribe_newsletter_opt_in' ), 10, 7 );
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
		if ( is_page( Settings::get_contact_page_id() ) || is_page( Settings::get_contact_page_slug() ) ) {
			return false;
		}

		if ( Settings::is_contact_form_id( Settings::get_submitted_form_id() ) ) {
			return false;
		}

		return $enabled;
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
		unset( $post_id );

		if ( ! Settings::is_contact_form_id( $id ) || ! Settings::has_single_contact_form() ) {
			return $redirect;
		}

		return Settings::get_success_url();
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

		if ( ! Settings::has_single_contact_form() ) {
			update_post_meta( $post_id, '_ran_emailoctopus_subscription_status', 'ambiguous_contact_form' );
			return;
		}

		if ( ! self::has_newsletter_opt_in( $all_values ) ) {
			return;
		}

		$email_address = EmailOctopusFieldMapper::get_email_address( $all_values );

		if ( '' === $email_address ) {
			update_post_meta( $post_id, '_ran_emailoctopus_subscription_status', 'missing_email' );
			return;
		}

		$result = ( new EmailOctopusSubscriber() )->subscribe( $email_address, $all_values );

		if ( is_wp_error( $result ) ) {
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

		update_post_meta( $post_id, '_ran_emailoctopus_subscription_status', 'subscribed' );
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
