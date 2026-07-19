<?php
/**
 * RAN EmailOctopus for Jetpack Forms settings.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

namespace RAN\EmailOctopusJetpackForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralizes site-specific form integration settings.
 */
final class Settings {
	/**
	 * Option name.
	 */
	const OPTION_NAME = 'ran_emailoctopus_jetpack_forms_settings';

	/**
	 * Previous bundled-plugin option, retained as a read-only migration source.
	 */
	const PREVIOUS_OPTION_NAME = 'ran_octopus_forms_settings';

	/**
	 * Previous option name, retained only for the one-time settings migration.
	 */
	const LEGACY_OPTION_NAME = 'ran_forms_settings';

	/**
	 * Default EmailOctopus hosted form connected to the newsletter list.
	 */
	const EMAILOCTOPUS_FORM_ID = '';

	/**
	 * Option that records the completed portability upgrade.
	 */
	const VERSION_OPTION = 'ran_emailoctopus_jetpack_forms_version';

	/**
	 * Get default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_defaults() {
		return array(
			'target_form_ids'                 => array(),
			'success_page_id'                 => 0,
			'emailoctopus_form_id'            => self::EMAILOCTOPUS_FORM_ID,
			'emailoctopus_list_id'            => '',
			'emailoctopus_email_source'       => '',
			'emailoctopus_field_map'          => array(),
			'emailoctopus_pending_message'    => __( 'There’s one more step: please confirm your subscription using the email we’ve just sent you.', 'ran-emailoctopus-jetpack-forms' ),
			'emailoctopus_subscribed_message' => __( 'You’re now subscribed to our newsletter.', 'ran-emailoctopus-jetpack-forms' ),
			'emailoctopus_existing_message'   => __( 'This email address has already been registered. If you have not yet confirmed your subscription, use the confirmation email you received earlier.', 'ran-emailoctopus-jetpack-forms' ),
			'emailoctopus_failure_message'    => __( 'Your message has been sent, but we could not add you to the newsletter. Please try again later.', 'ran-emailoctopus-jetpack-forms' ),
			'newsletter_source'               => '',
		);
	}

	/**
	 * Copy EmailOctopus settings from an earlier plugin option once.
	 *
	 * Keeping the original option intact gives a safe rollback path while the
	 * renamed plugin uses its own settings key from this point forward.
	 *
	 * @return void
	 */
	public static function migrate_legacy_settings() {
		if ( false !== get_option( self::OPTION_NAME, false ) ) {
			return;
		}

		foreach ( array( self::PREVIOUS_OPTION_NAME, self::LEGACY_OPTION_NAME ) as $legacy_option_name ) {
			$legacy_settings = get_option( $legacy_option_name, false );

			if ( ! is_array( $legacy_settings ) ) {
				continue;
			}

			$migration_keys                   = self::get_defaults();
			$migration_keys['target_form_id'] = 0;
			$emailoctopus_settings            = array_intersect_key( $legacy_settings, $migration_keys );
			add_option( self::OPTION_NAME, $emailoctopus_settings, '', false );
			return;
		}
	}

	/**
	 * Migrate the one-form scalar setting to the canonical form-ID collection.
	 *
	 * The presence of target_form_ids is the schema marker. Once it exists, the
	 * retired scalar is never read again and is removed if still present.
	 *
	 * @return void
	 */
	private static function migrate_target_form_ids() {
		$stored = get_option( self::OPTION_NAME, false );

		if ( ! is_array( $stored ) ) {
			return;
		}

		$has_collection = array_key_exists( 'target_form_ids', $stored );
		$raw_form_ids   = $has_collection ? $stored['target_form_ids'] : array( $stored['target_form_id'] ?? 0 );
		$form_ids       = self::normalize_target_form_ids( $raw_form_ids );
		$changed        = ! $has_collection || $form_ids !== $raw_form_ids || array_key_exists( 'target_form_id', $stored );

		if ( ! $changed ) {
			return;
		}

		$stored['target_form_ids'] = $form_ids;
		unset( $stored['target_form_id'] );
		update_option( self::OPTION_NAME, $stored, false );
	}

	/**
	 * Upgrade an existing RAN Forms installation without retaining site-specific defaults.
	 *
	 * New installations remain deliberately unconfigured. Only the global outcome
	 * destination retains its historical conventional-page discovery.
	 *
	 * @return void
	 */
	public static function upgrade() {
		self::migrate_legacy_settings();
		self::migrate_target_form_ids();
		self::remove_obsolete_contact_page_setting();

		if ( ! version_compare( (string) get_option( self::VERSION_OPTION, '0.0.0' ), RAN_EMAILOCTOPUS_JETPACK_FORMS_VERSION, '<' ) ) {
			return;
		}

		$stored = get_option( self::OPTION_NAME, false );

		if ( ! is_array( $stored ) ) {
			update_option( self::VERSION_OPTION, RAN_EMAILOCTOPUS_JETPACK_FORMS_VERSION, false );
			return;
		}

		$changed = false;

		if ( empty( $stored['success_page_id'] ) ) {
			$success_page = get_page_by_path( 'contact-success', OBJECT, 'page' );

			if ( $success_page instanceof \WP_Post ) {
				$stored['success_page_id'] = (int) $success_page->ID;
				$changed                   = true;
			}
		}

		if ( $changed ) {
			update_option( self::OPTION_NAME, $stored, false );
		}

		update_option( self::VERSION_OPTION, RAN_EMAILOCTOPUS_JETPACK_FORMS_VERSION, false );
	}

	/**
	 * Remove the retired page-scoped target from this plugin's option.
	 *
	 * The previous source options remain untouched for rollback/history. Only the
	 * active option loses the obsolete key, and no content or route is scanned.
	 *
	 * @return void
	 */
	private static function remove_obsolete_contact_page_setting() {
		$stored = get_option( self::OPTION_NAME, false );

		if ( ! is_array( $stored ) || ! array_key_exists( 'contact_page_id', $stored ) ) {
			return;
		}

		unset( $stored['contact_page_id'] );
		update_option( self::OPTION_NAME, $stored, false );
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_all() {
		self::migrate_legacy_settings();
		self::migrate_target_form_ids();

		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$defaults = self::get_defaults();
		$settings = array_intersect_key( $settings, $defaults );

		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Get a hash representing settings that affect health check results.
	 *
	 * @return string
	 */
	public static function get_health_hash() {
		return md5( wp_json_encode( self::get_all() ) );
	}

	/**
	 * Get a setting value.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$settings = self::get_all();

		return $settings[ $key ] ?? null;
	}

	/**
	 * Sanitize Settings API input.
	 *
	 * @param array<string,mixed> $input Input values.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input ) {
		$input           = is_array( $input ) ? $input : array();
		$current         = self::get_all();
		$settings        = self::get_defaults();
		$target_form_ids = self::normalize_target_form_ids( array_key_exists( 'target_form_ids', $input ) ? $input['target_form_ids'] : $current['target_form_ids'] );

		$settings['target_form_ids'] = $target_form_ids;
		$settings['success_page_id'] = absint( $input['success_page_id'] ?? 0 );
		if ( array_key_exists( 'emailoctopus_destination', $input ) ) {
			$destination = sanitize_text_field( $input['emailoctopus_destination'] );

			if ( 0 === strpos( $destination, 'form:' ) ) {
				$settings['emailoctopus_form_id'] = substr( $destination, 5 );
			} elseif ( 0 === strpos( $destination, 'list:' ) ) {
				$settings['emailoctopus_list_id'] = substr( $destination, 5 );
			}
		} else {
			$settings['emailoctopus_form_id'] = sanitize_text_field( $input['emailoctopus_form_id'] ?? '' );
			$settings['emailoctopus_list_id'] = sanitize_text_field( $input['emailoctopus_list_id'] ?? '' );
		}

		$settings['emailoctopus_email_source']       = EmailOctopusFieldMapper::normalize_source_key( (string) ( $input['emailoctopus_email_source'] ?? $current['emailoctopus_email_source'] ?? '' ) );
		$settings['emailoctopus_field_map']          = self::sanitize_emailoctopus_field_map( $input['emailoctopus_field_map'] ?? array(), $current['emailoctopus_field_map'] ?? array() );
		$settings['emailoctopus_pending_message']    = sanitize_textarea_field( $input['emailoctopus_pending_message'] ?? $settings['emailoctopus_pending_message'] );
		$settings['emailoctopus_subscribed_message'] = sanitize_textarea_field( $input['emailoctopus_subscribed_message'] ?? $settings['emailoctopus_subscribed_message'] );
		$settings['emailoctopus_existing_message']   = sanitize_textarea_field( $input['emailoctopus_existing_message'] ?? $settings['emailoctopus_existing_message'] );
		$settings['emailoctopus_failure_message']    = sanitize_textarea_field( $input['emailoctopus_failure_message'] ?? $settings['emailoctopus_failure_message'] );
		$settings['newsletter_source']               = EmailOctopusFieldMapper::normalize_source_key( (string) ( $input['newsletter_source'] ?? $current['newsletter_source'] ?? '' ) );

		self::warn_about_invalid_targets( $target_form_ids );
		self::warn_about_invalid_source_mappings( $settings, $target_form_ids );

		return $settings;
	}

	/**
	 * Add actionable Settings API warnings for unresolved subscription sources.
	 *
	 * The settings remain saved so administrators can correct one mapping while
	 * changing unrelated configuration. Subscription attempts remain paused until
	 * both sources are valid.
	 *
	 * @param array<string,mixed> $settings       Sanitized settings.
	 * @param array<int,int>      $target_form_ids Saved Jetpack form IDs.
	 * @return void
	 */
	private static function warn_about_invalid_source_mappings( $settings, $target_form_ids ) {
		if ( '' === (string) ( $settings['emailoctopus_form_id'] ?? '' ) && '' === (string) ( $settings['emailoctopus_list_id'] ?? '' ) ) {
			return;
		}

		$compatibility      = EmailOctopusFieldMapper::get_subscription_compatibility( $target_form_ids, $settings );
		$invalid_email      = array();
		$invalid_newsletter = array();

		foreach ( $compatibility as $form_id => $result ) {
			if ( in_array( 'email_source_missing', $result['reasons'], true ) || in_array( 'email_source_invalid', $result['reasons'], true ) ) {
				$invalid_email[] = $form_id;
			}

			if ( in_array( 'newsletter_source_missing', $result['reasons'], true ) || in_array( 'newsletter_source_invalid', $result['reasons'], true ) ) {
				$invalid_newsletter[] = $form_id;
			}
		}

		if ( ! empty( $invalid_email ) ) {
			add_settings_error(
				self::OPTION_NAME,
				'ran_octopus_forms_invalid_email_source',
				sprintf(
					/* translators: %s: comma-separated saved Jetpack form IDs. */
					__( 'EmailOctopus email source needs attention on saved form(s) %s: select a current email field before subscriptions can run for those forms.', 'ran-emailoctopus-jetpack-forms' ),
					implode( ', ', $invalid_email )
				),
				'warning'
			);
		}

		if ( ! empty( $invalid_newsletter ) ) {
			add_settings_error(
				self::OPTION_NAME,
				'ran_octopus_forms_invalid_newsletter_source',
				sprintf(
					/* translators: %s: comma-separated saved Jetpack form IDs. */
					__( 'Newsletter opt-in source needs attention on saved form(s) %s: select a current checkbox or consent field before subscriptions can run for those forms.', 'ran-emailoctopus-jetpack-forms' ),
					implode( ', ', $invalid_newsletter )
				),
				'warning'
			);
		}
	}

	/**
	 * Report why the mandatory saved Jetpack form cannot be used.
	 *
	 * Invalid selections remain stored so the settings screen and health check can
	 * explain the broken target while all EmailOctopus side effects stay disabled.
	 *
	 * @param array<int,int> $target_form_ids Saved Jetpack form IDs.
	 * @return void
	 */
	private static function warn_about_invalid_targets( $target_form_ids ) {
		if ( empty( $target_form_ids ) ) {
			add_settings_error(
				self::OPTION_NAME,
				'ran_emailoctopus_target_required',
				__( 'Select a published saved Jetpack form before EmailOctopus subscriptions can run.', 'ran-emailoctopus-jetpack-forms' ),
				'error'
			);
			return;
		}

		foreach ( $target_form_ids as $target_form_id ) {
			$form = get_post( $target_form_id );

			if ( $form instanceof \WP_Post && 'jetpack_form' === $form->post_type && 'publish' === $form->post_status && self::has_valid_saved_form_structure( $target_form_id ) ) {
				continue;
			}

			add_settings_error(
				self::OPTION_NAME,
				'ran_emailoctopus_target_invalid',
				sprintf(
					/* translators: %d: saved Jetpack form ID. */
					__( 'Selected target #%d must be a published, structurally valid saved Jetpack form. It remains selected for diagnostics but is isolated from integration handling.', 'ran-emailoctopus-jetpack-forms' ),
					$target_form_id
				),
				'error'
			);
		}
	}

	/**
	 * Sanitize EmailOctopus field mapping settings.
	 *
	 * @param mixed $input   Raw field map input.
	 * @param mixed $current Existing field map.
	 * @return array<string,array<string,string>>
	 */
	private static function sanitize_emailoctopus_field_map( $input, $current ) {
		if ( ! is_array( $input ) ) {
			return is_array( $current ) ? $current : array();
		}

		$transforms = array_keys( EmailOctopusFieldMapper::get_transform_options() );
		$field_map  = is_array( $current ) ? $current : array();

		foreach ( $input as $tag => $mapping ) {
			if ( ! is_array( $mapping ) ) {
				continue;
			}

			$tag       = sanitize_text_field( (string) $tag );
			$source    = EmailOctopusFieldMapper::normalize_source_key( (string) ( $mapping['source'] ?? $mapping['preserve_source'] ?? '' ) );
			$transform = sanitize_key( (string) ( $mapping['transform'] ?? 'as_is' ) );

			if ( '' === $tag ) {
				continue;
			}

			if ( '' === $source ) {
				unset( $field_map[ $tag ] );
				continue;
			}

			if ( ! in_array( $transform, $transforms, true ) ) {
				$transform = 'as_is';
			}

			$field_map[ $tag ] = array(
				'source'    => $source,
				'transform' => $transform,
			);
		}

		return $field_map;
	}

	/**
	 * Normalize selected saved Jetpack form IDs.
	 *
	 * @param mixed $form_ids Raw selected form IDs.
	 * @return array<int,int>
	 */
	public static function normalize_target_form_ids( $form_ids ) {
		$form_ids   = is_array( $form_ids ) ? $form_ids : array();
		$normalized = array();

		foreach ( $form_ids as $form_id ) {
			if ( is_bool( $form_id ) || ! is_scalar( $form_id ) || ! preg_match( '/^\d+$/', (string) $form_id ) ) {
				continue;
			}

			$form_id = (int) $form_id;

			if ( 0 < $form_id ) {
				$normalized[ $form_id ] = $form_id;
			}
		}

		ksort( $normalized, SORT_NUMERIC );

		return array_values( $normalized );
	}

	/**
	 * Get selected saved Jetpack form IDs, including unavailable selections.
	 *
	 * @return array<int,int>
	 */
	public static function get_target_form_ids() {
		return self::normalize_target_form_ids( self::get( 'target_form_ids' ) );
	}

	/**
	 * Whether a post is a published saved Jetpack form.
	 *
	 * @param int $form_id Candidate saved form ID.
	 * @return bool
	 */
	public static function is_valid_published_saved_form( $form_id ) {
		$form = get_post( absint( $form_id ) );

		return $form instanceof \WP_Post && 'jetpack_form' === $form->post_type && 'publish' === $form->post_status;
	}

	/**
	 * Whether a saved form contains one complete Jetpack contact-form block.
	 *
	 * @param int $form_id Saved Jetpack form ID.
	 * @return bool
	 */
	public static function has_valid_saved_form_structure( $form_id ) {
		$form = get_post( absint( $form_id ) );

		if ( ! $form instanceof \WP_Post || 'jetpack_form' !== $form->post_type ) {
			return false;
		}

		return 1 === self::count_contact_form_blocks( parse_blocks( (string) $form->post_content ) );
	}

	/**
	 * Count contact form blocks recursively.
	 *
	 * @param array<int,array<string,mixed>> $blocks Blocks.
	 * @return int
	 */
	private static function count_contact_form_blocks( $blocks ) {
		$count = 0;

		foreach ( $blocks as $block ) {
			if ( 'jetpack/contact-form' === ( $block['blockName'] ?? '' ) ) {
				++$count;
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$count += self::count_contact_form_blocks( $block['innerBlocks'] );
			}
		}

		return $count;
	}

	/**
	 * Get the EmailOctopus form ID used to resolve the newsletter list.
	 *
	 * @return string
	 */
	public static function get_emailoctopus_form_id() {
		$form_id = (string) self::get( 'emailoctopus_form_id' );

		if ( '' === $form_id ) {
			$form_id = self::EMAILOCTOPUS_FORM_ID;
		}

		/**
		 * Filters the EmailOctopus hosted form ID connected to the newsletter list.
		 *
		 * @param string $form_id EmailOctopus form ID.
		 */
		$form_id = apply_filters_deprecated(
			'ran_octopus_forms_emailoctopus_form_id',
			array( $form_id ),
			'1.1.0',
			'ran_emailoctopus_jetpack_forms_emailoctopus_form_id'
		);

		return (string) apply_filters( 'ran_emailoctopus_jetpack_forms_emailoctopus_form_id', $form_id );
	}

	/**
	 * Get an explicit EmailOctopus list ID override.
	 *
	 * @return string
	 */
	public static function get_emailoctopus_list_id() {
		if ( defined( 'RAN_EMAILOCTOPUS_JETPACK_FORMS_EMAILOCTOPUS_LIST_ID' ) ) {
			$list_id = constant( 'RAN_EMAILOCTOPUS_JETPACK_FORMS_EMAILOCTOPUS_LIST_ID' );
		} elseif ( defined( 'RAN_OCTOPUS_FORMS_EMAILOCTOPUS_LIST_ID' ) ) {
			$list_id = constant( 'RAN_OCTOPUS_FORMS_EMAILOCTOPUS_LIST_ID' );
		} elseif ( defined( 'RAN_FORMS_EMAILOCTOPUS_LIST_ID' ) ) {
			$list_id = constant( 'RAN_FORMS_EMAILOCTOPUS_LIST_ID' );
		} else {
			$list_id = (string) self::get( 'emailoctopus_list_id' );
		}

		/**
		 * Filters the EmailOctopus list ID. Return an empty string to resolve it
		 * from the configured EmailOctopus form.
		 *
		 * @param string $list_id EmailOctopus list ID.
		 */
		$list_id = apply_filters_deprecated(
			'ran_octopus_forms_emailoctopus_list_id',
			array( $list_id ),
			'1.1.0',
			'ran_emailoctopus_jetpack_forms_emailoctopus_list_id'
		);

		return (string) apply_filters( 'ran_emailoctopus_jetpack_forms_emailoctopus_list_id', $list_id );
	}

	/**
	 * Get the success redirect URL.
	 *
	 * @return string
	 */
	public static function get_success_url() {
		$page_id = absint( self::get( 'success_page_id' ) );
		$url     = 0 < $page_id ? get_permalink( $page_id ) : false;

		/**
		 * Filters the successful contact form redirect URL.
		 *
		 * @param string $url Success URL.
		 */
		$url = apply_filters_deprecated(
			'ran_octopus_forms_contact_success_url',
			array( $url ? $url : '' ),
			'1.1.0',
			'ran_emailoctopus_jetpack_forms_contact_success_url'
		);

		return (string) apply_filters( 'ran_emailoctopus_jetpack_forms_contact_success_url', $url );
	}

	/**
	 * Get configured Jetpack source key for the newsletter opt-in.
	 *
	 * @return string
	 */
	public static function get_newsletter_source() {
		$source = (string) self::get( 'newsletter_source' );

		/**
		 * Filters the configured newsletter opt-in source key.
		 *
		 * @param string $source Normalized Jetpack source key.
		 */
		$source = apply_filters_deprecated(
			'ran_octopus_forms_newsletter_source',
			array( $source ),
			'1.1.0',
			'ran_emailoctopus_jetpack_forms_newsletter_source'
		);

		return (string) apply_filters( 'ran_emailoctopus_jetpack_forms_newsletter_source', $source );
	}

	/**
	 * Get configured Jetpack source key for EmailOctopus email_address.
	 *
	 * Empty means no email source has been configured.
	 *
	 * @return string
	 */
	public static function get_emailoctopus_email_source() {
		$source = (string) self::get( 'emailoctopus_email_source' );

		/**
		 * Filters the configured EmailOctopus email source key.
		 *
	 * @param string $source Normalized Jetpack source key. Empty means unconfigured.
		 */
		$source = apply_filters_deprecated(
			'ran_octopus_forms_emailoctopus_email_source',
			array( $source ),
			'1.1.0',
			'ran_emailoctopus_jetpack_forms_emailoctopus_email_source'
		);

		return (string) apply_filters( 'ran_emailoctopus_jetpack_forms_emailoctopus_email_source', $source );
	}

	/**
	 * Get the configured visitor-facing newsletter outcome message.
	 *
	 * @param string $outcome EmailOctopus subscription outcome.
	 * @return string
	 */
	public static function get_emailoctopus_outcome_message( $outcome ) {
		$message_keys = array(
			'pending'    => 'emailoctopus_pending_message',
			'subscribed' => 'emailoctopus_subscribed_message',
			'existing'   => 'emailoctopus_existing_message',
			'failed'     => 'emailoctopus_failure_message',
		);
		$key          = $message_keys[ sanitize_key( $outcome ) ] ?? '';

		return '' !== $key ? sanitize_textarea_field( (string) self::get( $key ) ) : '';
	}

	/**
	 * Get configured EmailOctopus field map.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function get_emailoctopus_field_map() {
		$field_map = self::get( 'emailoctopus_field_map' );

		if ( ! is_array( $field_map ) ) {
			$field_map = array();
		}

		/**
		 * Filters the configured EmailOctopus field map.
		 *
		 * @param array<string,array<string,string>> $field_map Field map.
		 */
		$field_map = apply_filters_deprecated(
			'ran_octopus_forms_emailoctopus_field_map',
			array( $field_map ),
			'1.1.0',
			'ran_emailoctopus_jetpack_forms_emailoctopus_field_map'
		);

		return (array) apply_filters( 'ran_emailoctopus_jetpack_forms_emailoctopus_field_map', $field_map );
	}
}
