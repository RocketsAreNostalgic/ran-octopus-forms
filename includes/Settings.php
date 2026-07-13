<?php
/**
 * RAN Octopus Forms settings.
 *
 * @package RAN_Octopus_Forms
 */

namespace RAN\OctopusForms;

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
	const OPTION_NAME = 'ran_octopus_forms_settings';

	/**
	 * Previous option name, retained only for the one-time settings migration.
	 */
	const LEGACY_OPTION_NAME = 'ran_forms_settings';

	/**
	 * Default EmailOctopus hosted form connected to the newsletter list.
	 */
	const EMAILOCTOPUS_FORM_ID = '';

	/**
	 * Default Jetpack source key for newsletter opt-ins.
	 */
	const NEWSLETTER_SOURCE = 'join_our_newsletter';

	/**
	 * CSS class that identifies the single form owned by this integration.
	 */
	const TARGET_FORM_CLASS = 'ran-octopus-forms-contact-form';

	/**
	 * Option that records the completed portability upgrade.
	 */
	const VERSION_OPTION = 'ran_octopus_forms_version';

	/**
	 * Cloudflare's always-pass visible test site key.
	 */
	const TURNSTILE_TEST_SITE_KEY = '1x00000000000000000000AA';

	/**
	 * Cloudflare's always-pass test secret key.
	 */
	const TURNSTILE_TEST_SECRET_KEY = '1x0000000000000000000000000000000AA';

	/**
	 * Cloudflare's always-fail visible test site key.
	 */
	const TURNSTILE_FAIL_TEST_SITE_KEY = '2x00000000000000000000AB';

	/**
	 * Cloudflare's always-fail test secret key.
	 */
	const TURNSTILE_FAIL_TEST_SECRET_KEY = '2x0000000000000000000000000000000AA';

	/**
	 * Get default settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_defaults() {
		return array(
			'contact_page_id'           => 0,
			'success_page_id'           => 0,
			'emailoctopus_form_id'      => self::EMAILOCTOPUS_FORM_ID,
			'emailoctopus_list_id'      => '',
			'emailoctopus_email_source' => '',
			'emailoctopus_field_map'    => array(),
			'newsletter_source'         => self::NEWSLETTER_SOURCE,
			'turnstile_enabled'         => 0,
			'turnstile_site_key'        => '',
			'turnstile_secret_key'      => '',
		);
	}

	/**
	 * Copy the existing RAN Forms settings into the renamed option once.
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

		$legacy_settings = get_option( self::LEGACY_OPTION_NAME, false );

		if ( is_array( $legacy_settings ) ) {
			add_option( self::OPTION_NAME, $legacy_settings, '', false );
		}
	}

	/**
	 * Upgrade an existing RAN Forms installation without retaining PNS defaults.
	 *
	 * New installations remain deliberately unconfigured. Existing settings get
	 * explicit page IDs only when the former conventional pages still exist.
	 *
	 * @return void
	 */
	public static function upgrade() {
		self::migrate_legacy_settings();

		if ( ! version_compare( (string) get_option( self::VERSION_OPTION, '0.0.0' ), RAN_OCTOPUS_FORMS_VERSION, '<' ) ) {
			return;
		}

		$stored = get_option( self::OPTION_NAME, false );

		if ( ! is_array( $stored ) ) {
			update_option( self::VERSION_OPTION, RAN_OCTOPUS_FORMS_VERSION, false );
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

		self::mark_legacy_target_form( absint( $stored['contact_page_id'] ?? 0 ) );
		update_option( self::VERSION_OPTION, RAN_OCTOPUS_FORMS_VERSION, false );
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_all() {
		self::migrate_legacy_settings();

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
		$input    = is_array( $input ) ? $input : array();
		$current  = self::get_all();
		$settings = self::get_defaults();

		$settings['contact_page_id'] = absint( $input['contact_page_id'] ?? 0 );
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

		$settings['emailoctopus_email_source'] = EmailOctopusFieldMapper::normalize_source_key( (string) ( $input['emailoctopus_email_source'] ?? '' ) );
		$settings['emailoctopus_field_map']    = self::sanitize_emailoctopus_field_map( $input['emailoctopus_field_map'] ?? array() );
		$settings['newsletter_source']         = EmailOctopusFieldMapper::normalize_source_key( (string) ( $input['newsletter_source'] ?? self::NEWSLETTER_SOURCE ) );
		$settings['turnstile_enabled']         = empty( $input['turnstile_enabled'] ) ? 0 : 1;
		$settings['turnstile_site_key']        = sanitize_text_field( $input['turnstile_site_key'] ?? '' );

		$secret_key = sanitize_text_field( $input['turnstile_secret_key'] ?? '' );
		$settings['turnstile_secret_key'] = '' === $secret_key ? (string) ( $current['turnstile_secret_key'] ?? '' ) : $secret_key;

		if ( ! empty( $input['turnstile_setup_local_dev'] ) ) {
			$settings['turnstile_enabled']    = 1;
			$settings['turnstile_site_key']   = self::TURNSTILE_TEST_SITE_KEY;
			$settings['turnstile_secret_key'] = self::TURNSTILE_TEST_SECRET_KEY;
		}

		if ( '' === $settings['newsletter_source'] ) {
			$settings['newsletter_source'] = self::NEWSLETTER_SOURCE;
		}

		return $settings;
	}

	/**
	 * Sanitize EmailOctopus field mapping settings.
	 *
	 * @param mixed $input Raw field map input.
	 * @return array<string,array<string,string>>
	 */
	private static function sanitize_emailoctopus_field_map( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$transforms = array_keys( EmailOctopusFieldMapper::get_transform_options() );
		$field_map  = array();

		foreach ( $input as $tag => $mapping ) {
			if ( ! is_array( $mapping ) ) {
				continue;
			}

			$tag       = sanitize_text_field( (string) $tag );
			$source    = EmailOctopusFieldMapper::normalize_source_key( (string) ( $mapping['source'] ?? '' ) );
			$transform = sanitize_key( (string) ( $mapping['transform'] ?? 'as_is' ) );

			if ( '' === $tag || '' === $source ) {
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
		return self::count_contact_form_blocks( self::get_contact_form_blocks() );
	}

	/**
	 * Get the configured page's parsed blocks.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function get_contact_form_blocks() {
		$content = get_post_field( 'post_content', self::get_contact_page_id() );

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
	 * Mark the unique existing contact-form block during the one-time upgrade.
	 *
	 * @param int $page_id Contact page ID.
	 * @return void
	 */
	private static function mark_legacy_target_form( $page_id ) {
		if ( 0 >= $page_id ) {
			return;
		}

		$content = get_post_field( 'post_content', $page_id );
		$blocks  = parse_blocks( is_string( $content ) ? $content : '' );

		if ( 1 !== self::count_contact_form_blocks( $blocks ) || self::count_target_contact_form_blocks( $blocks ) ) {
			return;
		}

		self::add_target_class_to_first_contact_form( $blocks );

		wp_update_post(
			array(
				'ID'           => $page_id,
				'post_content' => serialize_blocks( $blocks ),
			)
		);
	}

	/**
	 * Add the target class to the first contact-form block in a parsed tree.
	 *
	 * @param array<int,array<string,mixed>> $blocks Parsed blocks.
	 * @return bool Whether a block was updated.
	 */
	private static function add_target_class_to_first_contact_form( &$blocks ) {
		foreach ( $blocks as &$block ) {
			if ( 'jetpack/contact-form' === ( $block['blockName'] ?? '' ) ) {
				$block['attrs']['className'] = trim( (string) ( $block['attrs']['className'] ?? '' ) . ' ' . self::TARGET_FORM_CLASS );
				return true;
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) && self::add_target_class_to_first_contact_form( $block['innerBlocks'] ) ) {
				return true;
			}
		}

		return false;
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
		return (string) apply_filters( 'ran_octopus_forms_emailoctopus_form_id', $form_id );
	}

	/**
	 * Get an explicit EmailOctopus list ID override.
	 *
	 * @return string
	 */
	public static function get_emailoctopus_list_id() {
		if ( defined( 'RAN_OCTOPUS_FORMS_EMAILOCTOPUS_LIST_ID' ) ) {
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
		return (string) apply_filters( 'ran_octopus_forms_emailoctopus_list_id', $list_id );
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
		return (string) apply_filters( 'ran_octopus_forms_contact_success_url', $url ? $url : '' );
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
		return (string) apply_filters( 'ran_octopus_forms_newsletter_source', $source );
	}

	/**
	 * Get configured Jetpack source key for EmailOctopus email_address.
	 *
	 * Empty means auto-detect.
	 *
	 * @return string
	 */
	public static function get_emailoctopus_email_source() {
		$source = (string) self::get( 'emailoctopus_email_source' );

		/**
		 * Filters the configured EmailOctopus email source key.
		 *
		 * @param string $source Normalized Jetpack source key. Empty means auto-detect.
		 */
		return (string) apply_filters( 'ran_octopus_forms_emailoctopus_email_source', $source );
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
		return (array) apply_filters( 'ran_octopus_forms_emailoctopus_field_map', $field_map );
	}

	/**
	 * Whether Turnstile is enabled.
	 *
	 * @return bool
	 */
	public static function is_turnstile_enabled() {
		return (bool) self::get( 'turnstile_enabled' );
	}

	/**
	 * Get the current WordPress environment type.
	 *
	 * @return string
	 */
	public static function get_environment_type() {
		$environment = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';

		/**
		 * Filters the environment type used by RAN Octopus Forms safety checks.
		 *
		 * @param string $environment Environment type.
		 */
		return (string) apply_filters( 'ran_octopus_forms_environment_type', $environment );
	}

	/**
	 * Whether WordPress is running as production.
	 *
	 * @return bool
	 */
	public static function is_production_environment() {
		return 'production' === self::get_environment_type();
	}

	/**
	 * Get Turnstile site key.
	 *
	 * @return string
	 */
	public static function get_turnstile_site_key() {
		if ( defined( 'RAN_OCTOPUS_FORMS_TURNSTILE_SITE_KEY' ) ) {
			$key = constant( 'RAN_OCTOPUS_FORMS_TURNSTILE_SITE_KEY' );
		} elseif ( defined( 'RAN_FORMS_TURNSTILE_SITE_KEY' ) ) {
			$key = constant( 'RAN_FORMS_TURNSTILE_SITE_KEY' );
		} else {
			$key = (string) self::get( 'turnstile_site_key' );
		}

		return is_string( $key ) ? $key : '';
	}

	/**
	 * Get Turnstile secret key.
	 *
	 * @return string
	 */
	public static function get_turnstile_secret_key() {
		if ( defined( 'RAN_OCTOPUS_FORMS_TURNSTILE_SECRET_KEY' ) ) {
			$key = constant( 'RAN_OCTOPUS_FORMS_TURNSTILE_SECRET_KEY' );
		} elseif ( defined( 'RAN_FORMS_TURNSTILE_SECRET_KEY' ) ) {
			$key = constant( 'RAN_FORMS_TURNSTILE_SECRET_KEY' );
		} else {
			$key = (string) self::get( 'turnstile_secret_key' );
		}

		return is_string( $key ) ? $key : '';
	}

	/**
	 * Whether Turnstile has both required keys.
	 *
	 * @return bool
	 */
	public static function has_turnstile_keys() {
		return '' !== self::get_turnstile_site_key() && '' !== self::get_turnstile_secret_key();
	}

	/**
	 * Whether configured Turnstile keys are Cloudflare test keys.
	 *
	 * @return bool
	 */
	public static function is_turnstile_test_key_pair() {
		return self::TURNSTILE_TEST_SITE_KEY === self::get_turnstile_site_key() && self::TURNSTILE_TEST_SECRET_KEY === self::get_turnstile_secret_key();
	}

	/**
	 * Whether test keys are blocked in the current environment.
	 *
	 * @return bool
	 */
	public static function blocks_turnstile_test_keys() {
		return self::is_production_environment() && self::is_turnstile_test_key_pair();
	}

	/**
	 * Whether Turnstile can be rendered and validated in this environment.
	 *
	 * @return bool
	 */
	public static function can_use_turnstile() {
		return self::is_turnstile_enabled() && self::has_turnstile_keys() && ! self::blocks_turnstile_test_keys();
	}
}
