<?php
/**
 * Integration profile resolution and routing gates.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

namespace RAN\EmailOctopusJetpackForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves immutable profile and saved-form ownership explicitly.
 */
final class IntegrationResolver {
	/**
	 * Jetpack class exposing authoritative feedback form identity.
	 */
	const FEEDBACK_CLASS = '\\Automattic\\Jetpack\\Forms\\ContactForm\\Feedback';

	/**
	 * Resolve one profile by immutable UUID.
	 *
	 * @param string $profile_id Immutable profile UUID.
	 * @return IntegrationProfile|null
	 */
	public static function get_profile( $profile_id ) {
		$profile_id = strtolower( (string) $profile_id );
		$stored     = Settings::get_profile( $profile_id );

		if ( null === $stored ) {
			return null;
		}

		return new IntegrationProfile( $profile_id, $stored, self::get_effective_configuration( $profile_id, $stored ) );
	}

	/**
	 * Get all structurally valid profiles.
	 *
	 * @return array<string,IntegrationProfile>
	 */
	public static function get_profiles() {
		$resolved = array();

		foreach ( Settings::get_profiles() as $profile_id => $stored ) {
			$profile = self::get_profile( $profile_id );

			if ( null !== $profile ) {
				$resolved[ $profile_id ] = $profile;
			}
		}

		return $resolved;
	}

	/**
	 * Resolve the sole owner of one form.
	 *
	 * Duplicate corrupt ownership fails closed for this form only.
	 *
	 * @param int $form_id Saved Jetpack form ID.
	 * @return IntegrationProfile|null
	 */
	public static function get_profile_for_form_id( $form_id ) {
		$owners = self::get_profile_ids_for_form_id( $form_id );

		return 1 === count( $owners ) ? self::get_profile( $owners[0] ) : null;
	}

	/**
	 * Get every stored owner for diagnostics and conflict detection.
	 *
	 * @param int $form_id Saved Jetpack form ID.
	 * @return array<int,string>
	 */
	public static function get_profile_ids_for_form_id( $form_id ) {
		$form_id = absint( $form_id );
		$owners  = array();

		if ( 0 === $form_id ) {
			return $owners;
		}

		foreach ( Settings::get_profiles() as $profile_id => $profile ) {
			if ( in_array( $form_id, Settings::normalize_form_ids( $profile['form_ids'] ?? array() ), true ) ) {
				$owners[] = $profile_id;
			}
		}

		return $owners;
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
	 * Alias for the authoritative identity capability.
	 *
	 * @return bool
	 */
	public static function has_portable_capability() {
		return self::supports_portable_forms();
	}

	/**
	 * Whether at least one profile form is currently routable.
	 *
	 * @return bool
	 */
	public static function is_portable_available() {
		return '' === self::get_portability_reason();
	}

	/**
	 * Alias describing active profile routing.
	 *
	 * @return bool
	 */
	public static function is_portable() {
		return self::is_portable_available();
	}

	/**
	 * Explain why no configured profile form is routable.
	 *
	 * @return string Empty when at least one form is routable.
	 */
	public static function get_portability_reason() {
		if ( ! self::supports_portable_forms() ) {
			return 'feedback_identity_unavailable';
		}

		$profiles = self::get_profiles();

		if ( empty( $profiles ) ) {
			return 'profile_not_configured';
		}

		$first_reason = 'target_not_selected';

		foreach ( $profiles as $profile ) {
			if ( empty( $profile->get_form_ids() ) ) {
				continue;
			}

			foreach ( $profile->get_form_ids() as $form_id ) {
				$reason = self::get_target_form_reason( $form_id, $profile->get_id() );

				if ( '' === $reason ) {
					return '';
				}

				$first_reason = $reason;
			}
		}

		return $first_reason;
	}

	/**
	 * Get one form's profile-aware routing reason.
	 *
	 * @param int    $form_id    Saved Jetpack form ID.
	 * @param string $profile_id Optional expected owner UUID.
	 * @return string Empty when routing is eligible.
	 */
	public static function get_target_form_reason( $form_id, $profile_id = '' ) {
		$form_id = absint( $form_id );
		$owners  = self::get_profile_ids_for_form_id( $form_id );

		if ( 1 < count( $owners ) ) {
			return 'target_ownership_conflict';
		}

		if ( empty( $owners ) ) {
			return 'target_not_selected';
		}

		$owner_id = $owners[0];

		if ( '' !== $profile_id && strtolower( (string) $profile_id ) !== $owner_id ) {
			return 'target_profile_mismatch';
		}

		$profile = self::get_profile( $owner_id );

		if ( null === $profile ) {
			return 'profile_not_configured';
		}

		if ( ! self::supports_portable_forms() ) {
			return 'feedback_identity_unavailable';
		}

		$success_page = get_post( $profile->get_success_page_id() );

		if ( ! $success_page instanceof \WP_Post || 'page' !== $success_page->post_type || 'publish' !== $success_page->post_status ) {
			return 'success_destination_unavailable';
		}

		if ( '' === (string) ( $profile->get_configuration()['success_url'] ?? '' ) ) {
			return 'success_destination_unavailable';
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
	 * Whether one profile-owned form can receive signed routing.
	 *
	 * @param int    $form_id    Saved form ID.
	 * @param string $profile_id Optional expected profile UUID.
	 * @return bool
	 */
	public static function is_routing_eligible_form_id( $form_id, $profile_id = '' ) {
		return '' === self::get_target_form_reason( $form_id, $profile_id );
	}

	/**
	 * Whether one routed form has all profile mapping sources.
	 *
	 * @param int    $form_id    Saved form ID.
	 * @param string $profile_id Optional expected profile UUID.
	 * @return bool
	 */
	public static function is_subscription_eligible_form_id( $form_id, $profile_id = '' ) {
		$profile = '' === $profile_id ? self::get_profile_for_form_id( $form_id ) : self::get_profile( $profile_id );

		if ( null === $profile || ! self::is_routing_eligible_form_id( $form_id, $profile->get_id() ) ) {
			return false;
		}

		$configuration = $profile->get_configuration();

		if ( '' === trim( (string) ( $configuration['emailoctopus_form_id'] ?? '' ) ) && '' === trim( (string) ( $configuration['emailoctopus_list_id'] ?? '' ) ) ) {
			return false;
		}

		$compatibility = EmailOctopusFieldMapper::get_subscription_compatibility( $profile->get_form_ids(), $configuration );

		return ! empty( $compatibility[ absint( $form_id ) ]['eligible'] );
	}

	/**
	 * Build the six-filter runtime configuration for one immutable profile.
	 *
	 * @param string              $profile_id Immutable profile UUID.
	 * @param array<string,mixed> $stored     Canonical profile.
	 * @return array<string,mixed>
	 */
	private static function get_effective_configuration( $profile_id, $stored ) {
		$destination = is_array( $stored['destination'] ?? null ) ? $stored['destination'] : array();
		$type        = (string) ( $destination['type'] ?? '' );
		$id          = (string) ( $destination['id'] ?? '' );
		$form_id     = 'form' === $type ? $id : '';
		$list_id     = 'list' === $type ? $id : '';
		$page_id     = absint( $stored['success_page_id'] ?? 0 );
		$success_url = 0 < $page_id ? get_permalink( $page_id ) : false;
		$messages    = is_array( $stored['messages'] ?? null ) ? $stored['messages'] : array();

		$form_id = (string) apply_filters( 'ran_emailoctopus_jetpack_forms_emailoctopus_form_id', $form_id, $profile_id );
		$list_id = (string) apply_filters( 'ran_emailoctopus_jetpack_forms_emailoctopus_list_id', $list_id, $profile_id );

		return array(
			'destination'                     => array(
				'type' => $type,
				'id'   => $id,
			),
			'success_page_id'                 => $page_id,
			'success_url'                     => (string) apply_filters( 'ran_emailoctopus_jetpack_forms_contact_success_url', $success_url ? $success_url : '', $profile_id ),
			'emailoctopus_form_id'            => $form_id,
			'emailoctopus_list_id'            => $list_id,
			'emailoctopus_email_source'       => (string) apply_filters( 'ran_emailoctopus_jetpack_forms_emailoctopus_email_source', (string) ( $stored['email_source'] ?? '' ), $profile_id ),
			'emailoctopus_field_map'          => (array) apply_filters( 'ran_emailoctopus_jetpack_forms_emailoctopus_field_map', (array) ( $stored['field_map'] ?? array() ), $profile_id ),
			'newsletter_source'               => (string) apply_filters( 'ran_emailoctopus_jetpack_forms_newsletter_source', (string) ( $stored['consent_source'] ?? '' ), $profile_id ),
			'emailoctopus_pending_message'    => sanitize_textarea_field( (string) ( $messages['pending'] ?? '' ) ),
			'emailoctopus_subscribed_message' => sanitize_textarea_field( (string) ( $messages['subscribed'] ?? '' ) ),
			'emailoctopus_existing_message'   => sanitize_textarea_field( (string) ( $messages['existing'] ?? '' ) ),
			'emailoctopus_failure_message'    => sanitize_textarea_field( (string) ( $messages['failed'] ?? '' ) ),
		);
	}
}
