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
	 * CSS class that identifies the single form owned by this integration.
	 */
	const TARGET_FORM_CLASS = 'ran-octopus-forms-contact-form';

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
			'contact_page_id'                 => 0,
			'target_form_id'                  => 0,
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

			$emailoctopus_settings = array_intersect_key( $legacy_settings, self::get_defaults() );
			add_option( self::OPTION_NAME, $emailoctopus_settings, '', false );
			return;
		}
	}

	/**
	 * Upgrade an existing RAN Forms installation without retaining site-specific defaults.
	 *
	 * New installations remain deliberately unconfigured. Existing settings get
	 * explicit page IDs only when the former conventional pages still exist.
	 *
	 * @return void
	 */
	public static function upgrade() {
		self::migrate_legacy_settings();
		self::migrate_saved_form_target();

		if ( ! version_compare( (string) get_option( self::VERSION_OPTION, '0.0.0' ), RAN_EMAILOCTOPUS_JETPACK_FORMS_VERSION, '<' ) ) {
			return;
		}

		$stored = get_option( self::OPTION_NAME, false );

		if ( ! is_array( $stored ) ) {
			update_option( self::VERSION_OPTION, RAN_EMAILOCTOPUS_JETPACK_FORMS_VERSION, false );
			return;
		}

		$changed = false;

		if ( empty( $stored['contact_page_id'] ) ) {
			$contact_page = get_page_by_path( 'contact-us', OBJECT, 'page' );

			if ( $contact_page instanceof \WP_Post ) {
				$stored['contact_page_id'] = (int) $contact_page->ID;
				$changed                   = true;
			}
		}

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
	 * Resolve a saved Jetpack form reference from existing page-scoped settings.
	 *
	 * The raw-key check makes this migration idempotent without coupling it to a
	 * plugin release number. Content is deliberately read-only: inline, missing,
	 * deleted, wrong-type, and ambiguous forms remain in legacy mode.
	 *
	 * @return void
	 */
	public static function migrate_saved_form_target() {
		$stored = get_option( self::OPTION_NAME, false );

		if ( ! is_array( $stored ) || array_key_exists( 'target_form_id', $stored ) ) {
			return;
		}

		$stored['target_form_id'] = self::resolve_legacy_saved_form_target( absint( $stored['contact_page_id'] ?? 0 ) );
		update_option( self::OPTION_NAME, $stored, false );
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_all() {
		self::migrate_legacy_settings();
		self::migrate_saved_form_target();

		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, self::get_defaults() );
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
		$contact_page_id = absint( $input['contact_page_id'] ?? $current['contact_page_id'] ?? 0 );
		$target_form_id  = absint( $input['target_form_id'] ?? $current['target_form_id'] ?? 0 );

		if ( 0 < $contact_page_id ) {
			$contact_form_count = self::get_contact_form_count_for_page( $contact_page_id );

			if ( 1 !== $contact_form_count ) {
				add_settings_error(
					self::OPTION_NAME,
					'ran_octopus_forms_invalid_contact_page',
					sprintf(
						/* translators: %d: number of Jetpack contact forms found. */
						__( 'Cannot save settings: the selected contact page contains %d Jetpack contact forms. RAN EmailOctopus for Jetpack Forms requires exactly one.', 'ran-emailoctopus-jetpack-forms' ),
						$contact_form_count
					),
					'error'
				);

				return $current;
			}
		}

		$settings['contact_page_id'] = $contact_page_id;
		$settings['target_form_id']  = $target_form_id;
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

		self::warn_about_invalid_source_mappings( $settings, $contact_page_id, $target_form_id );

		return $settings;
	}

	/**
	 * Add actionable Settings API warnings for unresolved subscription sources.
	 *
	 * The settings remain saved so administrators can correct one mapping while
	 * changing unrelated configuration. Subscription attempts remain paused until
	 * both sources are valid.
	 *
	 * @param array<string,mixed> $settings        Sanitized settings.
	 * @param int                 $contact_page_id Contact page ID.
	 * @param int                 $target_form_id  Saved Jetpack form ID.
	 * @return void
	 */
	private static function warn_about_invalid_source_mappings( $settings, $contact_page_id, $target_form_id ) {
		if ( '' === (string) ( $settings['emailoctopus_form_id'] ?? '' ) && '' === (string) ( $settings['emailoctopus_list_id'] ?? '' ) ) {
			return;
		}

		if ( IntegrationResolver::supports_portable_forms() && 0 < $target_form_id ) {
			$email_fields      = EmailOctopusFieldMapper::get_email_source_fields_for_saved_form( $target_form_id );
			$newsletter_fields = EmailOctopusFieldMapper::get_newsletter_source_fields_for_saved_form( $target_form_id );
		} else {
			$email_fields      = EmailOctopusFieldMapper::get_email_source_fields_for_contact_page( $contact_page_id );
			$newsletter_fields = EmailOctopusFieldMapper::get_newsletter_source_fields_for_contact_page( $contact_page_id );
		}

		if ( ! self::has_valid_source_field( $email_fields, (string) ( $settings['emailoctopus_email_source'] ?? '' ) ) ) {
			add_settings_error(
				self::OPTION_NAME,
				'ran_octopus_forms_invalid_email_source',
				__( 'EmailOctopus email source needs attention: select a current email field before subscriptions can run.', 'ran-emailoctopus-jetpack-forms' ),
				'warning'
			);
		}

		if ( ! self::has_valid_source_field( $newsletter_fields, (string) ( $settings['newsletter_source'] ?? '' ) ) ) {
			add_settings_error(
				self::OPTION_NAME,
				'ran_octopus_forms_invalid_newsletter_source',
				__( 'Newsletter opt-in source needs attention: select a current checkbox or consent field before subscriptions can run.', 'ran-emailoctopus-jetpack-forms' ),
				'warning'
			);
		}
	}

	/**
	 * Whether a saved source belongs to the current source candidates.
	 *
	 * @param array<int,array<string,string>> $source_fields Candidate fields.
	 * @param string                           $source        Saved source key.
	 * @return bool
	 */
	private static function has_valid_source_field( $source_fields, $source ) {
		if ( '' === $source ) {
			return false;
		}

		foreach ( $source_fields as $source_field ) {
			if ( ( $source_field['key'] ?? '' ) === $source ) {
				return true;
			}
		}

		return false;
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
	 * Get the contact page ID.
	 *
	 * @return int
	 */
	public static function get_contact_page_id() {
		$page_id = absint( self::get( 'contact_page_id' ) );

		if ( 0 < $page_id && 'page' === get_post_type( $page_id ) ) {
			return $page_id;
		}

		return 0;
	}

	/**
	 * Get the selected saved Jetpack form ID, including an invalid stored target.
	 *
	 * Keeping the raw sanitized ID lets diagnostics explain deleted, draft, and
	 * wrong-type selections instead of silently reverting configuration.
	 *
	 * @return int
	 */
	public static function get_target_form_id() {
		return absint( self::get( 'target_form_id' ) );
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
	 * Whether a Jetpack form ID belongs to the contact page.
	 *
	 * Jetpack derives IDs from the page ID and appends "-n" for subsequent
	 * forms on the same page.
	 *
	 * @param string|int|null $form_id Jetpack contact form ID.
	 * @return bool
	 */
	public static function is_contact_form_id( $form_id ) {
		$page_id = self::get_contact_page_id();

		if ( 0 >= $page_id || null === $form_id ) {
			return false;
		}

		$form_id = (string) $form_id;
		$base_id = (string) $page_id;

		return $form_id === $base_id || 0 === strpos( $form_id, $base_id . '-' );
	}

	/**
	 * Whether the configured contact page contains exactly one Jetpack form.
	 *
	 * @return bool
	 */
	public static function has_single_contact_form() {
		return 1 === self::get_contact_form_count() && self::has_target_contact_form();
	}

	/**
	 * Whether the configured page has exactly one RAN-marked Jetpack form.
	 *
	 * @return bool
	 */
	public static function has_target_contact_form() {
		return 1 === self::count_target_contact_form_blocks( self::get_contact_form_blocks() );
	}

	/**
	 * Count Jetpack contact forms on the configured contact page.
	 *
	 * @return int
	 */
	public static function get_contact_form_count() {
		return self::get_contact_form_count_for_page( self::get_contact_page_id() );
	}

	/**
	 * Count Jetpack contact forms on a specific page.
	 *
	 * @param int $page_id Page ID.
	 * @return int
	 */
	public static function get_contact_form_count_for_page( $page_id ) {
		return self::count_contact_form_blocks( self::get_contact_form_blocks( $page_id ) );
	}

	/**
	 * Get the configured page's parsed blocks.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function get_contact_form_blocks( $page_id = null ) {
		if ( null === $page_id ) {
			$page_id = self::get_contact_page_id();
		}

		$content = get_post_field( 'post_content', absint( $page_id ) );

		return parse_blocks( is_string( $content ) ? $content : '' );
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
	 * Count marked contact-form blocks recursively.
	 *
	 * @param array<int,array<string,mixed>> $blocks Blocks.
	 * @return int
	 */
	private static function count_target_contact_form_blocks( $blocks ) {
		$count = 0;

		foreach ( $blocks as $block ) {
			if ( self::is_target_contact_form_block( $block ) ) {
				++$count;
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$count += self::count_target_contact_form_blocks( $block['innerBlocks'] );
			}
		}

		return $count;
	}

	/**
	 * Whether parsed block metadata identifies the RAN-owned form.
	 *
	 * @param array<string,mixed> $block Parsed block.
	 * @return bool
	 */
	public static function is_target_contact_form_block( $block ) {
		if ( 'jetpack/contact-form' !== ( $block['blockName'] ?? '' ) ) {
			return false;
		}

		$class_name = (string) ( $block['attrs']['className'] ?? '' );

		return 1 === preg_match( '/(?:^|\\s)' . preg_quote( self::TARGET_FORM_CLASS, '/' ) . '(?:\\s|$)/', $class_name );
	}

	/**
	 * Resolve one marked saved-form reference from a legacy contact page.
	 *
	 * @param int $page_id Legacy contact page ID.
	 * @return int
	 */
	private static function resolve_legacy_saved_form_target( $page_id ) {
		if ( 0 >= $page_id || 'page' !== get_post_type( $page_id ) ) {
			return 0;
		}

		$references = array();
		self::collect_marked_saved_form_references( self::get_contact_form_blocks( $page_id ), $references );

		if ( 1 !== count( $references ) ) {
			return 0;
		}

		$form_id = absint( reset( $references ) );

		return 'jetpack_form' === get_post_type( $form_id ) ? $form_id : 0;
	}

	/**
	 * Collect marked saved-form references recursively, retaining duplicates.
	 *
	 * Retaining occurrences means two marked embeds remain ambiguous even when
	 * they happen to reference the same saved form.
	 *
	 * @param array<int,array<string,mixed>> $blocks     Parsed blocks.
	 * @param array<int,int>                 $references Reference accumulator.
	 * @return void
	 */
	private static function collect_marked_saved_form_references( $blocks, &$references ) {
		foreach ( $blocks as $block ) {
			if ( self::is_target_contact_form_block( $block ) ) {
				$reference = absint( $block['attrs']['ref'] ?? 0 );

				if ( 0 < $reference ) {
					$references[] = $reference;
				}
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				self::collect_marked_saved_form_references( $block['innerBlocks'], $references );
			}
		}
	}

	/**
	 * Get submitted Jetpack form ID.
	 *
	 * @return string
	 */
	public static function get_submitted_form_id() {
		if ( ! isset( $_POST['contact-form-id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only routing check.
			return '';
		}

		return sanitize_text_field( wp_unslash( $_POST['contact-form-id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only routing check.
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
