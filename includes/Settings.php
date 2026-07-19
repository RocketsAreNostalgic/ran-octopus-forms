<?php
/**
 * Conflict-safe integration profile storage.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

namespace RAN\EmailOctopusJetpackForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owns the canonical profile option and its section-specific mutations.
 */
final class Settings {
	/**
	 * Canonical profile store option.
	 */
	const OPTION_NAME = 'ran_emailoctopus_jetpack_forms_settings';

	/**
	 * Non-autoloaded write lock option.
	 */
	const LOCK_OPTION_NAME = 'ran_emailoctopus_jetpack_forms_settings_lock';

	/**
	 * Canonical storage schema.
	 */
	const SCHEMA_VERSION = 1;

	/**
	 * Supported field transforms.
	 *
	 * @var array<int,string>
	 */
	const TRANSFORMS = array( 'as_is', 'first_word', 'remaining_words', 'lowercase' );

	/**
	 * Injectable mutex factory used by focused concurrency tests.
	 *
	 * @var callable|null
	 */
	private static $mutex_factory;

	/**
	 * Injectable UUID generator used to prove collision handling.
	 *
	 * @var callable|null
	 */
	private static $uuid_factory;

	/**
	 * Initialize the empty store only when the option is genuinely absent.
	 *
	 * Existing flat or malformed values are intentionally left untouched and are
	 * reported as invalid until an administrator deliberately creates a profile.
	 *
	 * @return bool Whether a new option was added.
	 */
	public static function initialize_store() {
		global $wpdb;

		if ( false !== get_option( self::OPTION_NAME, false ) ) {
			return false;
		}

		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Core options table name is trusted.
				self::OPTION_NAME,
				maybe_serialize( self::get_empty_store() ),
				'no'
			)
		);

		wp_cache_delete( self::OPTION_NAME, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( 'alloptions', 'options' );

		if ( 1 === $inserted ) {
			return true;
		}

		return false;
	}

	/**
	 * Get a canonical empty store.
	 *
	 * Revision zero means no profile mutation has yet succeeded.
	 *
	 * @return array{schema_version:int,revision:int,profiles:array<string,array<string,mixed>>}
	 */
	public static function get_empty_store() {
		return array(
			'schema_version' => self::SCHEMA_VERSION,
			'revision'       => 0,
			'profiles'       => array(),
		);
	}

	/**
	 * Get defaults for one not-yet-persisted profile.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_profile_defaults() {
		return array(
			'revision'        => 0,
			'label'           => '',
			'form_ids'        => array(),
			'destination'     => array(
				'type' => '',
				'id'   => '',
			),
			'email_source'    => '',
			'consent_source'  => '',
			'field_map'       => array(),
			'success_page_id' => 0,
			'messages'        => self::get_default_messages(),
		);
	}

	/**
	 * Read the canonical store.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function get_store() {
		self::initialize_store();

		$store = get_option( self::OPTION_NAME, false );

		if ( ! self::is_canonical_store( $store ) ) {
			return self::invalid_store_error();
		}

		return $store;
	}

	/**
	 * Get stored profiles, or an empty collection for an invalid root.
	 *
	 * Callers that need to distinguish an empty store from a malformed store must
	 * use get_store() and inspect the WP_Error result.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_profiles() {
		$store = self::get_store();

		return is_wp_error( $store ) ? array() : $store['profiles'];
	}

	/**
	 * Get one structurally valid profile.
	 *
	 * @param string $profile_id Immutable UUID v4.
	 * @return array<string,mixed>|null
	 */
	public static function get_profile( $profile_id ) {
		$profile_id = strtolower( (string) $profile_id );

		if ( ! self::is_valid_profile_id( $profile_id ) ) {
			return null;
		}

		$profiles = self::get_profiles();
		$profile  = $profiles[ $profile_id ] ?? null;

		return self::is_canonical_profile( $profile ) ? $profile : null;
	}

	/**
	 * Get a hash representing the complete stored configuration.
	 *
	 * @return string
	 */
	public static function get_health_hash() {
		$store = self::get_store();

		return md5( wp_json_encode( is_wp_error( $store ) ? array( 'invalid' => true ) : $store ) );
	}

	/**
	 * Create a profile from stage-one fields.
	 *
	 * An explicit create is the only mutation allowed to replace a malformed old
	 * root. This is a clean cutover, not a migration or dual read.
	 *
	 * @param array<string,mixed> $input Stage-one values.
	 * @return string|\WP_Error New immutable UUID.
	 */
	public static function create_profile( $input ) {
		$stage_one = self::sanitize_stage_one( $input );

		if ( is_wp_error( $stage_one ) ) {
			return $stage_one;
		}

		return self::mutate_store(
			static function ( $store ) use ( $stage_one ) {
				$profile_id = self::generate_unique_profile_id( $store['profiles'] );

				if ( is_wp_error( $profile_id ) ) {
					return $profile_id;
				}

				$ownership_error = self::get_form_ownership_error( $store['profiles'], $profile_id, $stage_one['form_ids'] );

				if ( is_wp_error( $ownership_error ) ) {
					return $ownership_error;
				}

				$profile                          = array_replace( self::get_profile_defaults(), $stage_one );
				$profile['revision']              = 1;
				$store['profiles'][ $profile_id ] = $profile;
				++$store['revision'];

				return array(
					'store'  => $store,
					'result' => $profile_id,
				);
			},
			true
		);
	}

	/**
	 * Update profile identity, assignment, and destination only.
	 *
	 * @param string              $profile_id       Immutable profile UUID.
	 * @param int                 $expected_revision Revision submitted by the editor.
	 * @param array<string,mixed> $input            Stage-one values.
	 * @return int|\WP_Error New profile revision.
	 */
	public static function update_profile_stage_one( $profile_id, $expected_revision, $input ) {
		$stage_one = self::sanitize_stage_one( $input );

		if ( is_wp_error( $stage_one ) ) {
			return $stage_one;
		}

		return self::update_profile_section( $profile_id, $expected_revision, $stage_one );
	}

	/**
	 * Update mapping, destination behavior, and outcome messages only.
	 *
	 * @param string              $profile_id        Immutable profile UUID.
	 * @param int                 $expected_revision Revision submitted by the editor.
	 * @param array<string,mixed> $input             Stage-two values.
	 * @return int|\WP_Error New profile revision.
	 */
	public static function update_profile_stage_two( $profile_id, $expected_revision, $input ) {
		$stage_two = self::sanitize_stage_two( $input );

		return self::update_profile_section( $profile_id, $expected_revision, $stage_two );
	}

	/**
	 * Delete one profile after verifying its revision.
	 *
	 * @param string $profile_id        Immutable profile UUID.
	 * @param int    $expected_revision Revision submitted by the editor.
	 * @return bool|\WP_Error
	 */
	public static function delete_profile( $profile_id, $expected_revision ) {
		$profile_id        = strtolower( (string) $profile_id );
		$expected_revision = self::normalize_expected_revision( $expected_revision );

		if ( ! self::is_valid_profile_id( $profile_id ) ) {
			return self::profile_not_found_error();
		}

		if ( is_wp_error( $expected_revision ) ) {
			return $expected_revision;
		}

		return self::mutate_store(
			static function ( $store ) use ( $profile_id, $expected_revision ) {
				$profile = $store['profiles'][ $profile_id ] ?? null;

				if ( ! self::is_canonical_profile( $profile ) ) {
					return self::profile_not_found_error();
				}

				if ( $expected_revision !== (int) $profile['revision'] ) {
					return self::stale_profile_error();
				}

				unset( $store['profiles'][ $profile_id ] );
				++$store['revision'];

				return array(
					'store'  => $store,
					'result' => true,
				);
			}
		);
	}

	/**
	 * Inject a mutex factory for deterministic storage tests.
	 *
	 * @internal
	 *
	 * @param callable|null $factory Factory returning OptionMutex.
	 * @return void
	 */
	public static function set_mutex_factory( $factory = null ) {
		self::$mutex_factory = is_callable( $factory ) ? $factory : null;
	}

	/**
	 * Inject a UUID factory for deterministic collision tests.
	 *
	 * @internal
	 *
	 * @param callable|null $factory Factory returning a UUID string.
	 * @return void
	 */
	public static function set_uuid_factory( $factory = null ) {
		self::$uuid_factory = is_callable( $factory ) ? $factory : null;
	}

	/**
	 * Normalize positive saved-form IDs.
	 *
	 * @param mixed $form_ids Raw values.
	 * @return array<int,int>
	 */
	public static function normalize_form_ids( $form_ids ) {
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
	 * Temporary method name retained while the runtime wave becomes profile-explicit.
	 *
	 * This is not a flat-schema read: callers must supply a profile UUID or receive
	 * no forms. The alias can be removed once mapper callers use normalize_form_ids().
	 *
	 * @param mixed $form_ids Raw values.
	 * @return array<int,int>
	 */
	public static function normalize_target_form_ids( $form_ids ) {
		return self::normalize_form_ids( $form_ids );
	}

	/**
	 * Get form IDs for one explicit profile.
	 *
	 * @param string $profile_id Immutable profile UUID.
	 * @return array<int,int>
	 */
	public static function get_target_form_ids( $profile_id = '' ) {
		$profile = self::get_profile( $profile_id );

		return null === $profile ? array() : self::normalize_form_ids( $profile['form_ids'] );
	}

	/**
	 * Validate a UUID v4 profile identifier.
	 *
	 * @param string $profile_id Candidate identifier.
	 * @return bool
	 */
	public static function is_valid_profile_id( $profile_id ) {
		return 1 === preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', strtolower( (string) $profile_id ) );
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
	 * @param int $form_id Saved form ID.
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
	 * Update exactly one profile section.
	 *
	 * @param string              $profile_id        Profile UUID.
	 * @param int                 $expected_revision Submitted profile revision.
	 * @param array<string,mixed> $section           Sanitized section values.
	 * @return int|\WP_Error
	 */
	private static function update_profile_section( $profile_id, $expected_revision, $section ) {
		$profile_id        = strtolower( (string) $profile_id );
		$expected_revision = self::normalize_expected_revision( $expected_revision );

		if ( ! self::is_valid_profile_id( $profile_id ) ) {
			return self::profile_not_found_error();
		}

		if ( is_wp_error( $expected_revision ) ) {
			return $expected_revision;
		}

		return self::mutate_store(
			static function ( $store ) use ( $profile_id, $expected_revision, $section ) {
				$profile = $store['profiles'][ $profile_id ] ?? null;

				if ( ! self::is_canonical_profile( $profile ) ) {
					return self::profile_not_found_error();
				}

				if ( $expected_revision !== (int) $profile['revision'] ) {
					return self::stale_profile_error();
				}

				if ( array_key_exists( 'form_ids', $section ) ) {
					$ownership_error = self::get_form_ownership_error( $store['profiles'], $profile_id, $section['form_ids'] );

					if ( is_wp_error( $ownership_error ) ) {
						return $ownership_error;
					}
				}

				$profile = array_replace( $profile, $section );
				++$profile['revision'];
				$store['profiles'][ $profile_id ] = $profile;
				++$store['revision'];

				return array(
					'store'  => $store,
					'result' => $profile['revision'],
				);
			}
		);
	}

	/**
	 * Serialize one store mutation behind the database mutex.
	 *
	 * @param callable $callback        Mutation returning store and public result.
	 * @param bool     $allow_malformed Whether explicit creation may clean-cut over.
	 * @return mixed|\WP_Error
	 */
	private static function mutate_store( $callback, $allow_malformed = false ) {
		$mutex = self::get_mutex();
		$token = $mutex->acquire();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		try {
			wp_cache_delete( self::OPTION_NAME, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
			wp_cache_delete( 'alloptions', 'options' );
			$store = get_option( self::OPTION_NAME, false );

			if ( ! self::is_canonical_store( $store ) ) {
				if ( ! $allow_malformed ) {
					return self::invalid_store_error();
				}

				$store = self::get_empty_store();
			}

			$mutation = call_user_func( $callback, $store );

			if ( is_wp_error( $mutation ) ) {
				return $mutation;
			}

			if ( ! is_array( $mutation ) || ! isset( $mutation['store'] ) || ! array_key_exists( 'result', $mutation ) || ! self::is_canonical_store( $mutation['store'] ) ) {
				return new \WP_Error( 'ran_emailoctopus_jetpack_forms_mutation_invalid', __( 'The profile settings change produced an invalid store and was not saved.', 'ran-emailoctopus-jetpack-forms' ) );
			}

			// This check must remain immediately adjacent to update_option().
			if ( ! $mutex->is_owner( $token ) ) {
				return new \WP_Error( 'ran_emailoctopus_jetpack_forms_lock_lost', __( 'The settings write lock expired before the change could be saved. Please retry.', 'ran-emailoctopus-jetpack-forms' ) );
			}
			if ( ! update_option( self::OPTION_NAME, $mutation['store'], false ) ) {
				return new \WP_Error( 'ran_emailoctopus_jetpack_forms_store_save_failed', __( 'The integration profile could not be saved. No changes were applied.', 'ran-emailoctopus-jetpack-forms' ) );
			}

			return $mutation['result'];
		} finally {
			$mutex->release( $token );
		}
	}

	/**
	 * Get the production or injected mutex.
	 *
	 * @return OptionMutex
	 */
	private static function get_mutex() {
		if ( is_callable( self::$mutex_factory ) ) {
			$mutex = call_user_func( self::$mutex_factory, self::LOCK_OPTION_NAME );

			if ( $mutex instanceof OptionMutex ) {
				return $mutex;
			}
		}

		return new OptionMutex( self::LOCK_OPTION_NAME );
	}

	/**
	 * Generate a valid UUID not present in the latest locked store.
	 *
	 * @param array<string,array<string,mixed>> $profiles Latest profiles.
	 * @return string|\WP_Error
	 */
	private static function generate_unique_profile_id( $profiles ) {
		$factory = is_callable( self::$uuid_factory ) ? self::$uuid_factory : 'wp_generate_uuid4';

		for ( $attempt = 0; 3 > $attempt; ++$attempt ) {
			$profile_id = strtolower( (string) call_user_func( $factory ) );

			if ( self::is_valid_profile_id( $profile_id ) && ! array_key_exists( $profile_id, $profiles ) ) {
				return $profile_id;
			}
		}

		return new \WP_Error( 'ran_emailoctopus_jetpack_forms_profile_id_invalid', __( 'A unique profile identifier could not be generated.', 'ran-emailoctopus-jetpack-forms' ) );
	}

	/**
	 * Parse an exact positive profile revision.
	 *
	 * @param mixed $revision Submitted revision.
	 * @return int|\WP_Error
	 */
	private static function normalize_expected_revision( $revision ) {
		if ( is_int( $revision ) && 0 < $revision ) {
			return $revision;
		}

		if ( is_string( $revision ) && 1 === preg_match( '/^[1-9]\d*$/', $revision ) ) {
			return (int) $revision;
		}

		return new \WP_Error( 'ran_emailoctopus_jetpack_forms_profile_revision_invalid', __( 'The submitted profile revision is invalid.', 'ran-emailoctopus-jetpack-forms' ) );
	}

	/**
	 * Validate stage-one values.
	 *
	 * @param mixed $input Raw values.
	 * @return array<string,mixed>|\WP_Error
	 */
	private static function sanitize_stage_one( $input ) {
		$input = is_array( $input ) ? $input : array();
		$label = sanitize_text_field( (string) ( $input['label'] ?? '' ) );

		if ( '' === $label ) {
			return new \WP_Error( 'ran_emailoctopus_jetpack_forms_profile_label_required', __( 'Enter a profile name before saving.', 'ran-emailoctopus-jetpack-forms' ) );
		}

		return array(
			'label'       => $label,
			'form_ids'    => self::normalize_form_ids( $input['form_ids'] ?? array() ),
			'destination' => self::sanitize_destination( $input['destination'] ?? array() ),
		);
	}

	/**
	 * Sanitize stage-two values.
	 *
	 * @param mixed $input Raw values.
	 * @return array<string,mixed>
	 */
	private static function sanitize_stage_two( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$messages = is_array( $input['messages'] ?? null ) ? $input['messages'] : array();

		return array(
			'email_source'    => self::normalize_source_key( $input['email_source'] ?? '' ),
			'consent_source'  => self::normalize_source_key( $input['consent_source'] ?? '' ),
			'field_map'       => self::sanitize_field_map( $input['field_map'] ?? array() ),
			'success_page_id' => absint( $input['success_page_id'] ?? 0 ),
			'messages'        => array(
				'pending'    => sanitize_textarea_field( (string) ( $messages['pending'] ?? '' ) ),
				'subscribed' => sanitize_textarea_field( (string) ( $messages['subscribed'] ?? '' ) ),
				'existing'   => sanitize_textarea_field( (string) ( $messages['existing'] ?? '' ) ),
				'failed'     => sanitize_textarea_field( (string) ( $messages['failed'] ?? '' ) ),
			),
		);
	}

	/**
	 * Sanitize destination tagged union.
	 *
	 * @param mixed $destination Raw destination.
	 * @return array{type:string,id:string}
	 */
	private static function sanitize_destination( $destination ) {
		$destination = is_array( $destination ) ? $destination : array();
		$type        = sanitize_key( (string) ( $destination['type'] ?? '' ) );
		$id          = sanitize_text_field( (string) ( $destination['id'] ?? '' ) );

		if ( ! in_array( $type, array( 'form', 'list' ), true ) || '' === $id ) {
			return array(
				'type' => '',
				'id'   => '',
			);
		}

		return array(
			'type' => $type,
			'id'   => $id,
		);
	}

	/**
	 * Sanitize custom EmailOctopus field mappings.
	 *
	 * @param mixed $input Raw mappings.
	 * @return array<string,array<string,string>>
	 */
	private static function sanitize_field_map( $input ) {
		$input     = is_array( $input ) ? $input : array();
		$field_map = array();

		foreach ( $input as $tag => $mapping ) {
			if ( ! is_array( $mapping ) ) {
				continue;
			}

			$tag       = sanitize_text_field( (string) $tag );
			$source    = self::normalize_source_key( $mapping['source'] ?? '' );
			$transform = sanitize_key( (string) ( $mapping['transform'] ?? 'as_is' ) );

			if ( '' === $tag || '' === $source ) {
				continue;
			}

			if ( ! in_array( $transform, self::TRANSFORMS, true ) ) {
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
	 * Normalize a Jetpack field source key.
	 *
	 * @param mixed $value Raw label or key.
	 * @return string
	 */
	private static function normalize_source_key( $value ) {
		$value = strtolower( trim( (string) $value ) );
		$value = preg_replace( '/[^a-z0-9]+/', '_', $value );

		return is_string( $value ) ? trim( $value, '_' ) : '';
	}

	/**
	 * Reject assigning any requested form already owned by another profile.
	 *
	 * @param array<string,array<string,mixed>> $profiles   Latest profiles.
	 * @param string                            $profile_id Profile being written.
	 * @param array<int,int>                    $form_ids   Requested form IDs.
	 * @return true|\WP_Error
	 */
	private static function get_form_ownership_error( $profiles, $profile_id, $form_ids ) {
		foreach ( self::normalize_form_ids( $form_ids ) as $form_id ) {
			foreach ( $profiles as $other_profile_id => $other_profile ) {
				if ( $profile_id === $other_profile_id || ! is_array( $other_profile ) ) {
					continue;
				}

				if ( in_array( $form_id, self::normalize_form_ids( $other_profile['form_ids'] ?? array() ), true ) ) {
					return new \WP_Error(
						'ran_emailoctopus_jetpack_forms_form_already_assigned',
						sprintf(
							/* translators: 1: saved Jetpack form ID, 2: profile label. */
							__( 'Saved form #%1$d is already assigned to “%2$s”. Remove it there before assigning it to this profile.', 'ran-emailoctopus-jetpack-forms' ),
							$form_id,
							sanitize_text_field( (string) ( $other_profile['label'] ?? $other_profile_id ) )
						)
					);
				}
			}
		}

		return true;
	}

	/**
	 * Verify the canonical root shape without normalizing stored profiles.
	 *
	 * @param mixed $store Candidate store.
	 * @return bool
	 */
	private static function is_canonical_store( $store ) {
		if ( ! is_array( $store ) || array( 'schema_version', 'revision', 'profiles' ) !== array_keys( $store ) || self::SCHEMA_VERSION !== ( $store['schema_version'] ?? null ) || ! isset( $store['revision'], $store['profiles'] ) || ! is_int( $store['revision'] ) || 0 > $store['revision'] || ! is_array( $store['profiles'] ) ) {
			return false;
		}

		foreach ( $store['profiles'] as $profile_id => $profile ) {
			if ( strtolower( (string) $profile_id ) !== $profile_id || ! self::is_valid_profile_id( $profile_id ) || ! self::is_canonical_profile( $profile ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validate a stored profile shape while allowing stale positive references.
	 *
	 * @param mixed $profile Candidate profile.
	 * @return bool
	 */
	private static function is_canonical_profile( $profile ) {
		if ( ! is_array( $profile ) ) {
			return false;
		}

		$required = array_keys( self::get_profile_defaults() );

		if ( array_keys( $profile ) !== $required ) {
			return false;
		}

		$destination = self::sanitize_destination( $profile['destination'] );
		$field_map   = self::sanitize_field_map( $profile['field_map'] );
		$messages    = $profile['messages'];

		if ( ! is_array( $messages ) || array( 'pending', 'subscribed', 'existing', 'failed' ) !== array_keys( $messages ) ) {
			return false;
		}

		foreach ( $messages as $message ) {
			if ( ! is_string( $message ) || sanitize_textarea_field( $message ) !== $message ) {
				return false;
			}
		}

		return is_int( $profile['revision'] )
			&& 0 < $profile['revision']
			&& is_string( $profile['label'] )
			&& '' !== $profile['label']
			&& sanitize_text_field( $profile['label'] ) === $profile['label']
			&& is_array( $profile['form_ids'] )
			&& self::normalize_form_ids( $profile['form_ids'] ) === $profile['form_ids']
			&& is_array( $profile['destination'] )
			&& $destination === $profile['destination']
			&& is_string( $profile['email_source'] )
			&& self::normalize_source_key( $profile['email_source'] ) === $profile['email_source']
			&& is_string( $profile['consent_source'] )
			&& self::normalize_source_key( $profile['consent_source'] ) === $profile['consent_source']
			&& is_array( $profile['field_map'] )
			&& $field_map === $profile['field_map']
			&& is_int( $profile['success_page_id'] )
			&& 0 <= $profile['success_page_id'];
	}

	/**
	 * Count contact form blocks recursively.
	 *
	 * @param array<int,array<string,mixed>> $blocks Parsed blocks.
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
	 * Get default visitor messages.
	 *
	 * @return array<string,string>
	 */
	private static function get_default_messages() {
		return array(
			'pending'    => __( 'There’s one more step: please confirm your subscription using the email we’ve just sent you.', 'ran-emailoctopus-jetpack-forms' ),
			'subscribed' => __( 'You’re now subscribed to our newsletter.', 'ran-emailoctopus-jetpack-forms' ),
			'existing'   => __( 'This email address has already been registered. If you have not yet confirmed your subscription, use the confirmation email you received earlier.', 'ran-emailoctopus-jetpack-forms' ),
			'failed'     => __( 'Your message has been sent, but we could not add you to the newsletter. Please try again later.', 'ran-emailoctopus-jetpack-forms' ),
		);
	}

	/**
	 * Invalid-root error.
	 *
	 * @return \WP_Error
	 */
	private static function invalid_store_error() {
		return new \WP_Error( 'ran_emailoctopus_jetpack_forms_store_invalid', __( 'The stored integration settings do not use the current profile schema. Create a profile to replace them.', 'ran-emailoctopus-jetpack-forms' ) );
	}

	/**
	 * Missing-profile error.
	 *
	 * @return \WP_Error
	 */
	private static function profile_not_found_error() {
		return new \WP_Error( 'ran_emailoctopus_jetpack_forms_profile_not_found', __( 'That integration profile no longer exists.', 'ran-emailoctopus-jetpack-forms' ) );
	}

	/**
	 * Stale-editor error.
	 *
	 * @return \WP_Error
	 */
	private static function stale_profile_error() {
		return new \WP_Error( 'ran_emailoctopus_jetpack_forms_profile_conflict', __( 'This profile changed after the editor was opened. Reload it and apply your changes again.', 'ran-emailoctopus-jetpack-forms' ) );
	}
}
