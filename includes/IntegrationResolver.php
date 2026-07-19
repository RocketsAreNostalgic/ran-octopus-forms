<?php
/**
 * Internal EmailOctopus integration profile resolver.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

namespace RAN\EmailOctopusJetpackForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves the route-independent default profile and portable capability.
 */
final class IntegrationResolver {
	/**
	 * Option 1's one internal profile identifier.
	 */
	const DEFAULT_PROFILE_ID = 'default';

	/**
	 * Jetpack class that exposes authoritative feedback form identity.
	 */
	const FEEDBACK_CLASS = '\\Automattic\\Jetpack\\Forms\\ContactForm\\Feedback';

	/**
	 * Resolve the default integration profile.
	 *
	 * @return IntegrationProfile
	 */
	public static function get_default_profile() {
		$target_form_id = Settings::get_target_form_id();
		$form_ids       = 0 < $target_form_id ? array( $target_form_id ) : array();

		return new IntegrationProfile(
			self::DEFAULT_PROFILE_ID,
			$form_ids,
			array(
				'contact_page_id'                 => Settings::get_contact_page_id(),
				'success_url'                     => Settings::get_success_url(),
				'emailoctopus_form_id'            => Settings::get_emailoctopus_form_id(),
				'emailoctopus_list_id'            => Settings::get_emailoctopus_list_id(),
				'emailoctopus_email_source'       => Settings::get_emailoctopus_email_source(),
				'emailoctopus_field_map'          => Settings::get_emailoctopus_field_map(),
				'newsletter_source'               => Settings::get_newsletter_source(),
				'emailoctopus_pending_message'    => Settings::get_emailoctopus_outcome_message( 'pending' ),
				'emailoctopus_subscribed_message' => Settings::get_emailoctopus_outcome_message( 'subscribed' ),
				'emailoctopus_existing_message'   => Settings::get_emailoctopus_outcome_message( 'existing' ),
				'emailoctopus_failure_message'    => Settings::get_emailoctopus_outcome_message( 'failed' ),
			)
		);
	}

	/**
	 * Resolve a known profile ID.
	 *
	 * @param string $profile_id Profile ID.
	 * @return IntegrationProfile|null
	 */
	public static function get_profile( $profile_id ) {
		if ( self::DEFAULT_PROFILE_ID !== sanitize_key( $profile_id ) ) {
			return null;
		}

		return self::get_default_profile();
	}

	/**
	 * Whether Jetpack exposes authoritative saved-form feedback identity.
	 *
	 * @return bool
	 */
	public static function supports_portable_forms() {
		return class_exists( self::FEEDBACK_CLASS ) && method_exists( self::FEEDBACK_CLASS, 'get_form_id' );
	}

	/**
	 * Alias describing the portable capability gate.
	 *
	 * @return bool
	 */
	public static function has_portable_capability() {
		return self::supports_portable_forms();
	}

	/**
	 * Whether the default profile can safely run in portable mode.
	 *
	 * @return bool
	 */
	public static function is_portable_available() {
		return '' === self::get_portability_reason();
	}

	/**
	 * Alias for callers that describe the active routing mode.
	 *
	 * @return bool
	 */
	public static function is_portable() {
		return self::is_portable_available();
	}

	/**
	 * Get a machine-readable reason portable mode is unavailable.
	 *
	 * @return string Empty when portable mode is available.
	 */
	public static function get_portability_reason() {
		if ( ! self::supports_portable_forms() ) {
			return 'feedback_identity_unavailable';
		}

		$form_id = Settings::get_target_form_id();

		if ( 0 >= $form_id ) {
			return 'target_not_selected';
		}

		$form = get_post( $form_id );

		if ( ! $form instanceof \WP_Post ) {
			return 'target_missing';
		}

		if ( 'jetpack_form' !== $form->post_type ) {
			return 'target_wrong_type';
		}

		if ( 'publish' !== $form->post_status ) {
			return 'target_not_published';
		}

		if ( ! Settings::has_valid_saved_form_structure( $form_id ) ) {
			return 'target_invalid_structure';
		}

		return '';
	}

	/**
	 * Get the selected target form ID, valid or otherwise.
	 *
	 * @return int
	 */
	public static function get_target_form_id() {
		return self::get_default_profile()->get_target_form_id();
	}

	/**
	 * Whether a saved form belongs to the active portable profile.
	 *
	 * @param int $form_id Saved Jetpack form ID.
	 * @return bool
	 */
	public static function is_target_form_id( $form_id ) {
		return self::is_portable_available() && in_array( absint( $form_id ), self::get_default_profile()->get_form_ids(), true );
	}
}
