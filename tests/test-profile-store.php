<?php
/**
 * Canonical profile store coverage.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

use RAN\EmailOctopusJetpackForms\IntegrationResolver;
use RAN\EmailOctopusJetpackForms\Settings;

/**
 * Prove clean-cutover schema, isolation, and optimistic concurrency behavior.
 */
class RAN_EmailOctopus_Jetpack_Forms_Profile_Store_Test extends WP_UnitTestCase {
	/**
	 * Reset profile state.
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
	 * Reset injected factories.
	 *
	 * @return void
	 */
	public function tear_down() {
		Settings::set_mutex_factory();
		Settings::set_uuid_factory();
		delete_option( Settings::LOCK_OPTION_NAME );
		parent::tear_down();
	}

	/**
	 * An absent option initializes to the canonical empty root.
	 *
	 * @return void
	 */
	public function test_absent_store_initializes_without_a_profile_mutation() {
		$this->assertSame(
			array(
				'schema_version' => 1,
				'revision'       => 0,
				'profiles'       => array(),
			),
			Settings::get_store()
		);
		$this->assertSame( 'no', $this->get_option_autoload( Settings::OPTION_NAME ) );
	}

	/**
	 * Stale absent-option cache state cannot let initialization erase profiles.
	 *
	 * @return void
	 */
	public function test_initialization_never_overwrites_an_existing_store_row() {
		$this->use_uuid_sequence( array( '7022a039-63b8-4c85-8c77-6b92909cb12e' ) );
		$profile_id = Settings::create_profile( $this->stage_one( 'Existing', array( 41 ), 'form', 'remote-form' ) );
		$before     = get_option( Settings::OPTION_NAME );

		wp_cache_set( Settings::OPTION_NAME, false, 'options' );
		wp_cache_set( 'notoptions', array( Settings::OPTION_NAME => true ), 'options' );

		$this->assertFalse( Settings::initialize_store() );
		$this->assertSame( $before, get_option( Settings::OPTION_NAME ) );
		$this->assertSame( 'Existing', Settings::get_profile( $profile_id )['label'] );
	}

	/**
	 * Accurately cached stores avoid direct insert and cache invalidation work.
	 *
	 * @return void
	 */
	public function test_initialization_has_a_cached_existing_store_fast_path() {
		Settings::initialize_store();
		$this->assertSame( Settings::get_empty_store(), get_option( Settings::OPTION_NAME ) );
		$queries = array();
		$filter  = static function ( $query ) use ( &$queries ) {
			$queries[] = $query;
			return $query;
		};
		add_filter( 'query', $filter );

		$this->assertFalse( Settings::initialize_store() );

		remove_filter( 'query', $filter );
		$this->assertSame( array(), $queries );
		$this->assertSame( Settings::get_empty_store(), get_option( Settings::OPTION_NAME ) );
	}

	/**
	 * Flat settings are never read as a profile or silently rewritten.
	 *
	 * @return void
	 */
	public function test_malformed_flat_root_returns_error_without_mutation() {
		$flat = array(
			'target_form_ids'      => array( 6243 ),
			'success_page_id'      => 99,
			'emailoctopus_list_id' => 'old-list',
		);
		update_option( Settings::OPTION_NAME, $flat, false );

		$result = Settings::get_store();

		$this->assertWPError( $result );
		$this->assertSame( 'ran_emailoctopus_jetpack_forms_store_invalid', $result->get_error_code() );
		$this->assertSame( $flat, get_option( Settings::OPTION_NAME ) );
		$this->assertSame( array(), Settings::get_profiles() );
	}

	/**
	 * An intentional create cleanly replaces a malformed old root.
	 *
	 * @return void
	 */
	public function test_create_replaces_malformed_root_without_migrating_values() {
		update_option(
			Settings::OPTION_NAME,
			array(
				'target_form_ids'      => array( 6243 ),
				'emailoctopus_list_id' => 'must-not-migrate',
			),
			false
		);
		$this->use_uuid_sequence( array( '3c00b5af-44af-45bc-92cc-e759c650fd63' ) );

		$profile_id = Settings::create_profile( $this->stage_one( 'Fresh profile', array( 9, '3', 9, 0 ), 'list', 'fresh-list' ) );
		$store      = Settings::get_store();

		$this->assertSame( '3c00b5af-44af-45bc-92cc-e759c650fd63', $profile_id );
		$this->assertSame( 1, $store['revision'] );
		$this->assertSame( array( $profile_id ), array_keys( $store['profiles'] ) );
		$this->assertSame( array( 3, 9 ), $store['profiles'][ $profile_id ]['form_ids'] );
		$this->assertSame(
			array(
				'type' => 'list',
				'id'   => 'fresh-list',
			),
			$store['profiles'][ $profile_id ]['destination']
		);
		$this->assertStringNotContainsString( 'must-not-migrate', wp_json_encode( $store ) );
	}

	/**
	 * Interleaved profile updates preserve unrelated profiles and sections.
	 *
	 * @return void
	 */
	public function test_profile_and_section_updates_do_not_clobber_each_other() {
		$this->use_uuid_sequence(
			array(
				'5d1b9ec9-3e29-4e5e-b7f9-e7afce8cf4da',
				'9be00ca3-b553-4d1b-914f-b95f0f5253ea',
			)
		);
		$first_id  = Settings::create_profile( $this->stage_one( 'First', array( 101 ), 'form', 'first-form' ) );
		$second_id = Settings::create_profile( $this->stage_one( 'Second', array( 202 ), 'list', 'second-list' ) );

		$this->assertSame( 2, Settings::update_profile_stage_two( $first_id, 1, $this->stage_two( 501, 'first_email' ) ) );
		$first_after_stage_two = Settings::get_profile( $first_id );
		$second_before         = Settings::get_profile( $second_id );

		$this->assertSame( 2, Settings::update_profile_stage_one( $second_id, 1, $this->stage_one( 'Second renamed', array( 202, 303 ), 'list', 'second-list-new' ) ) );
		$this->assertSame( $first_after_stage_two, Settings::get_profile( $first_id ) );

		$second_after = Settings::get_profile( $second_id );
		$this->assertSame( $second_before['email_source'], $second_after['email_source'] );
		$this->assertSame( $second_before['messages'], $second_after['messages'] );
		$this->assertSame( 'Second renamed', $second_after['label'] );
		$this->assertSame( 4, Settings::get_store()['revision'] );
	}

	/**
	 * A stale tab cannot overwrite a newer profile revision.
	 *
	 * @return void
	 */
	public function test_stale_or_malformed_revision_rejects_without_mutation() {
		$this->use_uuid_sequence( array( '876d097b-1715-4d1e-a10b-326da26e57b8' ) );
		$profile_id = Settings::create_profile( $this->stage_one( 'Original', array( 91 ), 'form', 'form-a' ) );
		$this->assertSame( 2, Settings::update_profile_stage_one( $profile_id, 1, $this->stage_one( 'Newer', array( 91 ), 'form', 'form-a' ) ) );
		$before = Settings::get_store();

		$stale = Settings::update_profile_stage_one( $profile_id, 1, $this->stage_one( 'Stale', array( 92 ), 'list', 'wrong' ) );
		$this->assertWPError( $stale );
		$this->assertSame( 'ran_emailoctopus_jetpack_forms_profile_conflict', $stale->get_error_code() );
		$this->assertSame( $before, Settings::get_store() );

		$invalid = Settings::delete_profile( $profile_id, -2 );
		$this->assertWPError( $invalid );
		$this->assertSame( 'ran_emailoctopus_jetpack_forms_profile_revision_invalid', $invalid->get_error_code() );
		$this->assertSame( $before, Settings::get_store() );
	}

	/**
	 * Exclusive form ownership is revalidated inside the locked mutation.
	 *
	 * @return void
	 */
	public function test_duplicate_form_assignment_is_rejected_without_mutation() {
		$this->use_uuid_sequence(
			array(
				'b919f7ae-1097-4e30-86be-b5ade7efbb19',
				'23e11de5-1566-4ff2-81eb-2113aa3a57e2',
			)
		);
		$first_id = Settings::create_profile( $this->stage_one( 'Owner', array( 6243 ), 'form', 'form-a' ) );
		$before   = Settings::get_store();
		$result   = Settings::create_profile( $this->stage_one( 'Conflict', array( 6243 ), 'list', 'list-b' ) );

		$this->assertIsString( $first_id );
		$this->assertWPError( $result );
		$this->assertSame( 'ran_emailoctopus_jetpack_forms_form_already_assigned', $result->get_error_code() );
		$this->assertSame( $before, Settings::get_store() );
	}

	/**
	 * Corrupt duplicate ownership isolates only the conflicted form.
	 *
	 * @return void
	 */
	public function test_corrupt_duplicate_ownership_fails_closed_per_form() {
		$this->use_uuid_sequence(
			array(
				'90a06b7b-070b-47ae-a741-2e4227856b70',
				'0c70d1fe-79d2-46f2-8712-90529cf48d7a',
			)
		);
		$first_id  = Settings::create_profile( $this->stage_one( 'First', array( 100 ), 'form', 'form-a' ) );
		$second_id = Settings::create_profile( $this->stage_one( 'Second', array( 200 ), 'list', 'list-b' ) );
		$store     = Settings::get_store();

		$store['profiles'][ $second_id ]['form_ids'] = array( 100, 200 );
		update_option( Settings::OPTION_NAME, $store, false );

		$this->assertNull( IntegrationResolver::get_profile_for_form_id( 100 ) );
		$this->assertSame( array( $first_id, $second_id ), IntegrationResolver::get_profile_ids_for_form_id( 100 ) );
		$this->assertSame( 'target_ownership_conflict', IntegrationResolver::get_target_form_reason( 100 ) );
		$this->assertSame( $second_id, IntegrationResolver::get_profile_for_form_id( 200 )->get_id() );
	}

	/**
	 * UUID collisions retry rather than overwriting an existing profile.
	 *
	 * @return void
	 */
	public function test_uuid_collision_retries_against_latest_locked_store() {
		$existing = 'b0d9bd1c-5153-453e-9a6c-e6dc64e9c3ef';
		$new      = '00ba90cc-05d9-49cf-880b-8d38cf270337';
		$this->use_uuid_sequence( array( $existing ) );
		$this->assertSame( $existing, Settings::create_profile( $this->stage_one( 'Existing', array( 1 ), 'form', 'a' ) ) );
		$this->use_uuid_sequence( array( $existing, $existing, $new ) );

		$this->assertSame( $new, Settings::create_profile( $this->stage_one( 'New', array( 2 ), 'list', 'b' ) ) );
		$this->assertSame( array( $existing, $new ), array_keys( Settings::get_profiles() ) );
		$this->assertSame( 'Existing', Settings::get_profile( $existing )['label'] );
	}

	/**
	 * Deleting the final profile is supported and revisioned.
	 *
	 * @return void
	 */
	public function test_final_profile_can_be_deleted() {
		$this->use_uuid_sequence( array( '70c68d41-00cb-4171-bfe2-eec0e7156862' ) );
		$profile_id = Settings::create_profile( $this->stage_one( 'Disposable', array(), '', '' ) );

		$this->assertTrue( Settings::delete_profile( $profile_id, 1 ) );
		$this->assertSame( array(), Settings::get_profiles() );
		$this->assertSame( 2, Settings::get_store()['revision'] );
	}

	/**
	 * Build stage-one input.
	 *
	 * @param string         $label    Profile label.
	 * @param array<int,mixed> $form_ids Form IDs.
	 * @param string         $type     Destination type.
	 * @param string         $id       Destination ID.
	 * @return array<string,mixed>
	 */
	private function stage_one( $label, $form_ids, $type, $id ) {
		return array(
			'label'       => $label,
			'form_ids'    => $form_ids,
			'destination' => array(
				'type' => $type,
				'id'   => $id,
			),
		);
	}

	/**
	 * Build stage-two input.
	 *
	 * @param int    $success_page_id Success page ID.
	 * @param string $email_source    Email source.
	 * @return array<string,mixed>
	 */
	private function stage_two( $success_page_id, $email_source ) {
		return array(
			'email_source'    => $email_source,
			'consent_source'  => 'join newsletter',
			'field_map'       => array(
				'FirstName' => array(
					'source'    => 'Full name',
					'transform' => 'first_word',
				),
			),
			'success_page_id' => $success_page_id,
			'messages'        => array(
				'pending'    => 'Pending',
				'subscribed' => 'Subscribed',
				'existing'   => 'Existing',
				'failed'     => 'Failed',
			),
		);
	}

	/**
	 * Install a deterministic UUID sequence.
	 *
	 * @param array<int,string> $uuids UUID sequence.
	 * @return void
	 */
	private function use_uuid_sequence( $uuids ) {
		Settings::set_uuid_factory(
			static function () use ( &$uuids ) {
				return (string) array_shift( $uuids );
			}
		);
	}

	/**
	 * Read the raw autoload column.
	 *
	 * @param string $option_name Option name.
	 * @return string
	 */
	private function get_option_autoload( $option_name ) {
		global $wpdb;

		return (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Core options table name is trusted.
				$option_name
			)
		);
	}
}
