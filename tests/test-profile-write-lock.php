<?php
/**
 * Coverage for profile storage's conflict-safe write lock.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

use RAN\EmailOctopusJetpackForms\OptionMutex;
use RAN\EmailOctopusJetpackForms\Settings;

/**
 * Verify that direct option-row locking cannot lose ownership.
 */
class RAN_EmailOctopus_Jetpack_Forms_Profile_Write_Lock_Test extends WP_UnitTestCase {
	/**
	 * Clear the canonical store and its direct mutex between tests.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		delete_option( Settings::OPTION_NAME );
		delete_option( Settings::LOCK_OPTION_NAME );
		Settings::set_mutex_factory();
		Settings::set_uuid_factory();
	}
	/**
	 * Build an in-memory wpdb double for the mutex's direct option-table queries.
	 *
	 * @return \wpdb
	 */
	private function database() {
		return new class() extends \wpdb {
			/** @var string */
			public $options = 'wp_options';

			/** @var array<string,array{option_value:string,autoload:string}> */
			public $rows = array();

			/** @var array<int,array{0:string,1:array<int,mixed>}> */
			public $queries = array();

			/** @var bool */
			public $fail_compare_and_swap = false;

			/** @var string */
			public $insert_payload_override = '';

			/** @var string */
			public $compare_and_swap_payload_override = '';

			/** @return void */
			public function __construct() {}

			/**
			 * @param string $query Query template.
			 * @param mixed  ...$args Query arguments.
			 * @return array{0:string,1:array<int,mixed>}
			 */
			public function prepare( $query, ...$args ) {
				return array( $query, $args );
			}

			/**
			 * @param array{0:string,1:array<int,mixed>} $query Prepared query.
			 * @return int
			 */
			public function query( $query ) {
				$this->queries[] = $query;
				$sql             = $query[0];
				$args            = $query[1];

				if ( 0 === strpos( $sql, 'INSERT IGNORE' ) ) {
					if ( isset( $this->rows[ $args[0] ] ) ) {
						return 0;
					}

					$this->rows[ $args[0] ] = array(
						'option_value' => '' === $this->insert_payload_override ? $args[1] : $this->insert_payload_override,
						'autoload'     => $args[2],
					);
					return 1;
				}

				if ( 0 === strpos( $sql, 'UPDATE' ) ) {
					if ( $this->fail_compare_and_swap || ! isset( $this->rows[ $args[2] ] ) || $args[3] !== $this->rows[ $args[2] ]['option_value'] ) {
						return 0;
					}

					$this->rows[ $args[2] ] = array(
						'option_value' => '' === $this->compare_and_swap_payload_override ? $args[0] : $this->compare_and_swap_payload_override,
						'autoload'     => $args[1],
					);
					return 1;
				}

				if ( 0 === strpos( $sql, 'DELETE' ) ) {
					if ( ! isset( $this->rows[ $args[0] ] ) || $args[1] !== $this->rows[ $args[0] ]['option_value'] ) {
						return 0;
					}

					unset( $this->rows[ $args[0] ] );
					return 1;
				}

				return 0;
			}

			/**
			 * @param array{0:string,1:array<int,mixed>}|null $query Prepared query.
			 * @param int                                      $x Column offset.
			 * @param int                                      $y Row offset.
			 * @return string|null
			 */
			public function get_var( $query = null, $x = 0, $y = 0 ) {
				$this->queries[] = $query;

				return is_array( $query ) ? ( $this->rows[ $query[1][0] ]['option_value'] ?? null ) : null;
			}
		};
	}
	/**
	 * Build a valid raw lock payload.
	 *
	 * @param string $token Token.
	 * @param int    $expires_at Unix expiry timestamp.
	 * @return string
	 */
	private function payload( $token, $expires_at ) {
		return wp_json_encode(
			array(
				'token'      => $token,
				'expires_at' => $expires_at,
			)
		);
	}

	/**
	 * Build a deterministic mutex and its backing double.
	 *
	 * @param \wpdb  $database Database double.
	 * @param int                                                  $now Current time.
	 * @param string                                               $token Generated token.
	 * @return OptionMutex
	 */
	private function mutex( $database, $now, $token ) {
		return new OptionMutex(
			'ran_emailoctopus_jetpack_forms_test_lock',
			static function () use ( $now ) {
				return $now;
			},
			static function () use ( $token ) {
				return $token;
			},
			$database
		);
	}

	/**
	 * An active owner is not replaced and remains observable verbatim.
	 *
	 * @return void
	 */
	public function test_active_lock_is_busy_and_leaves_the_existing_token_unchanged() {
		$database = $this->database();
		$name     = 'ran_emailoctopus_jetpack_forms_test_lock';
		$payload  = $this->payload( 'current-owner', 1031 );

		$database->rows[ $name ] = array(
			'option_value' => $payload,
			'autoload'     => 'no',
		);

		$result = $this->mutex( $database, 1001, 'new-owner' )->acquire();

		$this->assertWPError( $result );
		$this->assertSame( 'ran_emailoctopus_jetpack_forms_lock_busy', $result->get_error_code() );
		$this->assertSame( $payload, $database->rows[ $name ]['option_value'] );
		$this->assertSame( 'no', $database->rows[ $name ]['autoload'] );
	}

	/**
	 * An exact expired payload is reclaimed using a compare-and-swap update.
	 *
	 * @return void
	 */
	public function test_expired_lock_is_reclaimed_with_a_new_non_autoloaded_payload() {
		$database = $this->database();
		$name     = 'ran_emailoctopus_jetpack_forms_test_lock';

		$database->rows[ $name ] = array(
			'option_value' => $this->payload( 'old-owner', 1000 ),
			'autoload'     => 'yes',
		);

		$mutex = $this->mutex( $database, 1000, 'new-owner' );

		$this->assertSame( 'new-owner', $mutex->acquire() );
		$this->assertTrue( $mutex->is_owner( 'new-owner' ) );
		$this->assertSame( 'no', $database->rows[ $name ]['autoload'] );
		$this->assertSame( 'new-owner', json_decode( $database->rows[ $name ]['option_value'], true )['token'] );
		$this->assertStringStartsWith( 'UPDATE', $database->queries[2][0] );
	}

	/**
	 * A successful insert is not ownership until the stored token is rechecked.
	 *
	 * @return void
	 */
	public function test_successful_insert_that_loses_ownership_returns_busy() {
		$database                          = $this->database();
		$database->insert_payload_override = $this->payload( 'other-owner', 1031 );

		$result = $this->mutex( $database, 1001, 'new-owner' )->acquire();

		$this->assertWPError( $result );
		$this->assertSame( 'ran_emailoctopus_jetpack_forms_lock_busy', $result->get_error_code() );
	}

	/**
	 * A successful reclaim is not ownership until its stored token is rechecked.
	 *
	 * @return void
	 */
	public function test_successful_compare_and_swap_that_loses_ownership_returns_busy() {
		$database = $this->database();
		$name     = 'ran_emailoctopus_jetpack_forms_test_lock';

		$database->rows[ $name ] = array(
			'option_value' => $this->payload( 'old-owner', 1000 ),
			'autoload'     => 'no',
		);

		$database->compare_and_swap_payload_override = $this->payload( 'other-owner', 1031 );

		$result = $this->mutex( $database, 1001, 'new-owner' )->acquire();

		$this->assertWPError( $result );
		$this->assertSame( 'ran_emailoctopus_jetpack_forms_lock_busy', $result->get_error_code() );
	}

	/**
	 * Direct queries invalidate every cache entry that could hide a lock row.
	 *
	 * @return void
	 */
	public function test_acquire_invalidates_option_and_notoptions_caches() {
		$database = $this->database();
		$name     = 'ran_emailoctopus_jetpack_forms_test_lock';
		wp_cache_set( $name, 'stale', 'options' );
		wp_cache_set( 'notoptions', array( $name => true ), 'options' );
		wp_cache_set( 'alloptions', array( $name => 'stale' ), 'options' );

		$this->assertSame( 'new-owner', $this->mutex( $database, 1001, 'new-owner' )->acquire() );
		$this->assertFalse( wp_cache_get( $name, 'options' ) );
		$this->assertFalse( wp_cache_get( 'notoptions', 'options' ) );
		$this->assertFalse( wp_cache_get( 'alloptions', 'options' ) );
	}

	/**
	 * Malformed rows fail safely instead of being treated as stale ownership.
	 *
	 * @return void
	 */
	public function test_malformed_lock_payload_returns_busy_without_overwriting_it() {
		$database = $this->database();
		$name     = 'ran_emailoctopus_jetpack_forms_test_lock';

		$database->rows[ $name ] = array(
			'option_value' => '{not-json',
			'autoload'     => 'no',
		);

		$result = $this->mutex( $database, 1000, 'new-owner' )->acquire();

		$this->assertWPError( $result );
		$this->assertSame( 'ran_emailoctopus_jetpack_forms_lock_malformed', $result->get_error_code() );
		$this->assertSame( '{not-json', $database->rows[ $name ]['option_value'] );
	}

	/**
	 * A lost compare-and-swap race must not claim ownership.
	 *
	 * @return void
	 */
	public function test_failed_compare_and_swap_returns_busy_without_overwriting_the_existing_row() {
		$database = $this->database();
		$name     = 'ran_emailoctopus_jetpack_forms_test_lock';
		$payload  = $this->payload( 'old-owner', 1000 );

		$database->rows[ $name ] = array(
			'option_value' => $payload,
			'autoload'     => 'no',
		);

		$database->fail_compare_and_swap = true;

		$result = $this->mutex( $database, 1000, 'new-owner' )->acquire();

		$this->assertWPError( $result );
		$this->assertSame( 'ran_emailoctopus_jetpack_forms_lock_busy', $result->get_error_code() );
		$this->assertSame( $payload, $database->rows[ $name ]['option_value'] );
	}

	/**
	 * A stale owner cannot remove a row reclaimed by a later writer.
	 *
	 * @return void
	 */
	public function test_stale_owner_release_cannot_delete_a_reclaimed_lock() {
		$database = $this->database();
		$name     = 'ran_emailoctopus_jetpack_forms_test_lock';

		$database->rows[ $name ] = array(
			'option_value' => $this->payload( 'old-owner', 1000 ),
			'autoload'     => 'no',
		);

		$new_mutex = $this->mutex( $database, 1001, 'new-owner' );
		$this->assertSame( 'new-owner', $new_mutex->acquire() );
		$this->assertFalse( $new_mutex->release( 'old-owner' ) );
		$this->assertTrue( $new_mutex->is_owner( 'new-owner' ) );
		$this->assertArrayHasKey( $name, $database->rows );
	}

	/**
	 * Ownership is rechecked against both expiry and the stored token.
	 *
	 * @return void
	 */
	public function test_ownership_recheck_fails_after_expiry_or_token_replacement() {
		$database = $this->database();
		$now      = 1000;
		$mutex    = new OptionMutex(
			'ran_emailoctopus_jetpack_forms_test_lock',
			static function () use ( &$now ) {
				return $now;
			},
			static function () {
				return 'owner';
			},
			$database
		);

		$this->assertSame( 'owner', $mutex->acquire() );
		$this->assertTrue( $mutex->is_owner( 'owner' ) );
		$now += OptionMutex::TTL;
		$this->assertFalse( $mutex->is_owner( 'owner' ) );

		$database->rows['ran_emailoctopus_jetpack_forms_test_lock']['option_value'] = $this->payload( 'other-owner', $now + OptionMutex::TTL );
		$this->assertFalse( $mutex->is_owner( 'owner' ) );
		$this->assertFalse( $mutex->release( 'owner' ) );
	}

	/**
	 * Build valid stage-one input.
	 *
	 * @param string         $label Profile label.
	 * @param array<int,int> $form_ids Saved forms.
	 * @return array<string,mixed>
	 */
	private function stage_one( $label, $form_ids ) {
		return array(
			'label'       => $label,
			'form_ids'    => $form_ids,
			'destination' => array(
				'type' => 'list',
				'id'   => 'list-' . sanitize_title( $label ),
			),
		);
	}

	/**
	 * Build valid stage-two input.
	 *
	 * @param string $suffix Unique source suffix.
	 * @return array<string,mixed>
	 */
	private function stage_two( $suffix ) {
		return array(
			'email_source'    => 'email_' . $suffix,
			'consent_source'  => 'consent_' . $suffix,
			'field_map'       => array(
				'FirstName' => array(
					'source'    => 'name_' . $suffix,
					'transform' => 'first_word',
				),
			),
			'success_page_id' => 100,
			'messages'        => array(
				'pending'    => 'Pending ' . $suffix,
				'subscribed' => 'Subscribed ' . $suffix,
				'existing'   => 'Existing ' . $suffix,
				'failed'     => 'Failed ' . $suffix,
			),
		);
	}

	/**
	 * Updating profile A preserves profile B exactly.
	 *
	 * @return void
	 */
	public function test_profile_a_write_preserves_profile_b_byte_for_byte() {
		$profile_a = Settings::create_profile( $this->stage_one( 'Profile A', array( 101 ) ) );
		$profile_b = Settings::create_profile( $this->stage_one( 'Profile B', array( 202 ) ) );
		$before_b  = Settings::get_profile( $profile_b );

		$this->assertIsString( $profile_a );
		$this->assertIsString( $profile_b );
		$this->assertSame( 2, Settings::update_profile_stage_one( $profile_a, 1, $this->stage_one( 'Profile A revised', array( 303 ) ) ) );
		$this->assertSame( $before_b, Settings::get_profile( $profile_b ) );
	}

	/**
	 * A stale submission for the same profile is rejected without mutation.
	 *
	 * @return void
	 */
	public function test_stale_same_profile_revision_is_rejected_without_overwrite() {
		$profile_id = Settings::create_profile( $this->stage_one( 'Profile A', array( 101 ) ) );

		$this->assertSame( 2, Settings::update_profile_stage_one( $profile_id, 1, $this->stage_one( 'Current edit', array( 101 ) ) ) );
		$before = Settings::get_profile( $profile_id );
		$result = Settings::update_profile_stage_two( $profile_id, 1, $this->stage_two( 'stale' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'ran_emailoctopus_jetpack_forms_profile_conflict', $result->get_error_code() );
		$this->assertSame( $before, Settings::get_profile( $profile_id ) );
	}

	/**
	 * Both section-specific saves leave the opposite section untouched.
	 *
	 * @return void
	 */
	public function test_stage_specific_saves_preserve_the_other_profile_section() {
		$profile_id = Settings::create_profile( $this->stage_one( 'Profile A', array( 101 ) ) );
		$this->assertSame( 2, Settings::update_profile_stage_two( $profile_id, 1, $this->stage_two( 'first' ) ) );
		$after_stage_two = Settings::get_profile( $profile_id );

		$this->assertSame( 3, Settings::update_profile_stage_one( $profile_id, 2, $this->stage_one( 'Profile A revised', array( 303 ) ) ) );
		$after_stage_one = Settings::get_profile( $profile_id );
		$this->assertSame( $after_stage_two['email_source'], $after_stage_one['email_source'] );
		$this->assertSame( $after_stage_two['consent_source'], $after_stage_one['consent_source'] );
		$this->assertSame( $after_stage_two['field_map'], $after_stage_one['field_map'] );
		$this->assertSame( $after_stage_two['messages'], $after_stage_one['messages'] );

		$this->assertSame( 4, Settings::update_profile_stage_two( $profile_id, 3, $this->stage_two( 'second' ) ) );
		$after_second_stage_two = Settings::get_profile( $profile_id );
		$this->assertSame( $after_stage_one['label'], $after_second_stage_two['label'] );
		$this->assertSame( $after_stage_one['form_ids'], $after_second_stage_two['form_ids'] );
		$this->assertSame( $after_stage_one['destination'], $after_second_stage_two['destination'] );
	}

	/**
	 * A form cannot be silently assigned to two profiles.
	 *
	 * @return void
	 */
	public function test_duplicate_form_ownership_is_rejected_without_mutation() {
		$profile_a = Settings::create_profile( $this->stage_one( 'Profile A', array( 101 ) ) );
		$before    = Settings::get_store();
		$result    = Settings::create_profile( $this->stage_one( 'Profile B', array( 101 ) ) );

		$this->assertIsString( $profile_a );
		$this->assertWPError( $result );
		$this->assertSame( 'ran_emailoctopus_jetpack_forms_form_already_assigned', $result->get_error_code() );
		$this->assertSame( $before, Settings::get_store() );
	}

	/**
	 * A UUID collision cannot replace an existing profile after the store lock.
	 *
	 * @return void
	 */
	public function test_repeated_uuid_collision_is_rejected_without_mutation() {
		$uuid = '51d63902-2a2e-4fd6-ab90-483cf505a730';
		Settings::set_uuid_factory(
			static function () use ( $uuid ) {
				return $uuid;
			}
		);
		$this->assertSame( $uuid, Settings::create_profile( $this->stage_one( 'Profile A', array( 101 ) ) ) );
		$before = Settings::get_store();
		$result = Settings::create_profile( $this->stage_one( 'Profile B', array( 202 ) ) );
		Settings::set_uuid_factory();

		$this->assertWPError( $result );
		$this->assertSame( 'ran_emailoctopus_jetpack_forms_profile_id_invalid', $result->get_error_code() );
		$this->assertSame( $before, Settings::get_store() );
	}

	/**
	 * A malformed legacy root remains untouched by reads and rejected updates.
	 *
	 * @return void
	 */
	public function test_malformed_root_is_not_overwritten_by_read_or_update() {
		$malformed = array(
			'target_form_id'  => 101,
			'success_page_id' => 200,
		);
		update_option( Settings::OPTION_NAME, $malformed, false );

		$this->assertWPError( Settings::get_store() );
		$this->assertSame( array(), Settings::get_profiles() );
		$result = Settings::update_profile_stage_one( '51d63902-2a2e-4fd6-ab90-483cf505a730', 1, $this->stage_one( 'Profile A', array( 101 ) ) );

		$this->assertWPError( $result );
		$this->assertSame( $malformed, get_option( Settings::OPTION_NAME ) );
	}

	/**
	 * The absent root initializes to the canonical empty store.
	 *
	 * @return void
	 */
	public function test_absent_root_initializes_the_canonical_empty_store() {
		$this->assertSame( Settings::get_empty_store(), Settings::get_store() );
		$this->assertSame( Settings::get_empty_store(), get_option( Settings::OPTION_NAME ) );
	}

	/**
	 * A failed option write is surfaced and leaves the store unchanged.
	 *
	 * @return void
	 */
	public function test_failed_update_option_returns_an_error_without_mutation() {
		$profile_id = Settings::create_profile( $this->stage_one( 'Profile A', array( 101 ) ) );
		$before     = Settings::get_store();
		$filter     = static function ( $value, $old_value ) {
			return $old_value;
		};
		add_filter( 'pre_update_option_' . Settings::OPTION_NAME, $filter, 10, 2 );

		$result = Settings::update_profile_stage_one( $profile_id, 1, $this->stage_one( 'Profile A changed', array( 202 ) ) );

		remove_filter( 'pre_update_option_' . Settings::OPTION_NAME, $filter, 10 );
		$this->assertWPError( $result );
		$this->assertSame( 'ran_emailoctopus_jetpack_forms_store_save_failed', $result->get_error_code() );
		$this->assertSame( $before, Settings::get_store() );
	}
}
