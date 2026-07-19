<?php
/**
 * Profile administration workflow coverage.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

use RAN\EmailOctopusJetpackForms\Admin;
use RAN\EmailOctopusJetpackForms\Settings;

/**
 * Prove server-rendered profile edits remain isolated and conflict safe.
 */
class RAN_EmailOctopus_Jetpack_Forms_Profile_Admin_Workflows_Test extends WP_UnitTestCase {
	/** @var int */
	private $admin_id;

	/** @var array<int,string> */
	private $redirects = array();

	/** Reset settings, request and user state. */
	public function set_up() {
		parent::set_up();
		delete_option( Settings::OPTION_NAME );
		delete_option( Settings::LOCK_OPTION_NAME );
		delete_option( 'emailoctopus_api_key' );
		Settings::set_uuid_factory();
		$_GET            = array();
		$_POST           = array();
		$_REQUEST        = array();
		$this->redirects = array();
		$this->admin_id  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );
		add_filter( 'wp_redirect', array( $this, 'capture_redirect' ) );
	}

	/** Reset globals and filters. */
	public function tear_down() {
		remove_filter( 'wp_redirect', array( $this, 'capture_redirect' ) );
		Settings::set_uuid_factory();
		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
		parent::tear_down();
	}

	/** Capture redirects without changing their destination. */
	public function capture_redirect( $location ) {
		$this->redirects[] = $location;
		return false;
	}

	/** A create POST establishes only the submitted stage-one values. */
	public function test_create_handler_builds_one_incomplete_profile() {
		$form_id = $this->create_saved_form();
		$this->use_uuid_sequence( array( '10000000-0000-4000-8000-000000000001' ) );
		$_POST    = array(
			'_wpnonce' => wp_create_nonce( 'ran_emailoctopus_jetpack_forms_create_profile' ),
			'profile'  => array(
				'label'             => 'Newsletter',
				'form_ids'          => array( $form_id ),
				'destination_value' => 'list:list-one',
			),
		);
		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Test request mirrors the nonce-protected POST.

		Admin::create_profile();

		$profile = Settings::get_profile( '10000000-0000-4000-8000-000000000001' );
		$this->assertSame( 'Newsletter', $profile['label'] );
		$this->assertSame( array( $form_id ), $profile['form_ids'] );
		$this->assertSame(
			array(
				'type' => 'list',
				'id'   => 'list-one',
			),
			$profile['destination']
		);
		$this->assertSame( '', $profile['email_source'] );
		$this->assertStringContainsString( 'notice=created', end( $this->redirects ) );
	}

	/** Stage one preserves every behaviour value already saved in the profile. */
	public function test_identity_handler_preserves_behaviour_section() {
		$form_id    = $this->create_saved_form();
		$success_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		$profile_id = $this->create_profile( 'Original', array( $form_id ) );
		$revision   = Settings::update_profile_stage_two(
			$profile_id,
			1,
			array(
				'email_source'    => 'email',
				'consent_source'  => 'consent',
				'field_map'       => array(
					'FirstName' => array(
						'source'    => 'name',
						'transform' => 'first_word',
					),
				),
				'success_page_id' => $success_id,
				'messages'        => $this->messages( 'kept' ),
			)
		);
		$_POST      = array(
			'_wpnonce'         => wp_create_nonce( 'ran_emailoctopus_jetpack_forms_save_identity_' . $profile_id ),
			'profile_id'       => $profile_id,
			'profile_revision' => $revision,
			'profile'          => array(
				'label'             => 'Renamed',
				'form_ids'          => array( $form_id ),
				'destination_value' => 'form:form-two',
			),
		);
		$_REQUEST   = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Test request mirrors the nonce-protected POST.

		Admin::save_profile_identity();
		$profile = Settings::get_profile( $profile_id );

		$this->assertSame( 'Renamed', $profile['label'] );
		$this->assertSame( 'email', $profile['email_source'] );
		$this->assertSame(
			array(
				'FirstName' => array(
					'source'    => 'name',
					'transform' => 'first_word',
				),
			),
			$profile['field_map']
		);
		$this->assertSame( $success_id, $profile['success_page_id'] );
		$this->assertSame( $this->messages( 'kept' ), $profile['messages'] );
	}

	/** A stale same-profile tab writes nothing and preserves submitted editor values. */
	public function test_stale_identity_handler_rejects_entire_save_and_retains_input() {
		$form_id    = $this->create_saved_form();
		$profile_id = $this->create_profile( 'Current', array( $form_id ) );
		Settings::update_profile_stage_one(
			$profile_id,
			1,
			array(
				'label'       => 'Newer',
				'form_ids'    => array( $form_id ),
				'destination' => array(
					'type' => 'list',
					'id'   => 'newer',
				),
			)
		);
		$_POST    = array(
			'_wpnonce'         => wp_create_nonce( 'ran_emailoctopus_jetpack_forms_save_identity_' . $profile_id ),
			'profile_id'       => $profile_id,
			'profile_revision' => 1,
			'profile'          => array(
				'label'             => 'Stale typed value',
				'form_ids'          => array( $form_id ),
				'destination_value' => 'list:stale',
			),
		);
		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Test request mirrors the nonce-protected POST.

		Admin::save_profile_identity();
		$this->assertSame( 'Newer', Settings::get_profile( $profile_id )['label'] );
		$redirect = end( $this->redirects );
		$this->assertStringContainsString( 'notice=error', $redirect );
		parse_str( (string) wp_parse_url( $redirect, PHP_URL_QUERY ), $query );
		$_GET = $query;
		ob_start();
		Admin::render_page();
		$markup = ob_get_clean();
		$this->assertStringContainsString( 'value="Stale typed value"', $markup );
	}

	/** Duplicate assignments are rejected without mutating either profile. */
	public function test_duplicate_form_assignment_is_rejected_without_mutation() {
		$form_id  = $this->create_saved_form();
		$first    = $this->create_profile( 'First', array( $form_id ) );
		$second   = $this->create_profile( 'Second', array() );
		$before   = Settings::get_store();
		$_POST    = array(
			'_wpnonce'         => wp_create_nonce( 'ran_emailoctopus_jetpack_forms_save_identity_' . $second ),
			'profile_id'       => $second,
			'profile_revision' => 1,
			'profile'          => array(
				'label'             => 'Second',
				'form_ids'          => array( $form_id ),
				'destination_value' => '',
			),
		);
		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Test request mirrors the nonce-protected POST.

		Admin::save_profile_identity();
		$this->assertSame( $before, Settings::get_store() );
		$this->assertSame( array( $form_id ), Settings::get_profile( $first )['form_ids'] );
	}

	/** The index is local-only and never requests EmailOctopus resources. */
	public function test_index_performs_no_remote_calls_and_uses_admin_post_forms() {
		$this->create_profile( 'Index profile', array( $this->create_saved_form() ) );
		$request_count = 0;
		$http_mock     = static function () use ( &$request_count ) {
			++$request_count;
			return new WP_Error( 'unexpected', 'The index must not call HTTP.' );
		};
		add_filter( 'pre_http_request', $http_mock );
		$_GET = array( 'page' => Admin::PAGE_SLUG );
		ob_start();
		Admin::render_page();
		$markup = ob_get_clean();
		remove_filter( 'pre_http_request', $http_mock );

		$this->assertSame( 0, $request_count );
		$this->assertStringContainsString( 'Index profile', $markup );
		$this->assertStringContainsString( 'admin-post.php', $markup );
		$this->assertStringNotContainsString( 'action="options.php"', $markup );
	}

	/** Assigned and unavailable form rows remain explicit and removable. */
	public function test_identity_editor_disables_foreign_forms_and_shows_unavailable_selection() {
		$owned_form = $this->create_saved_form( 'Owned form' );
		$this->create_profile( 'Owner', array( $owned_form ) );
		$editing = $this->create_profile( 'Editing', array( 999999 ) );
		$_GET    = array(
			'page'       => Admin::PAGE_SLUG,
			'view'       => 'edit',
			'profile_id' => $editing,
		);
		ob_start();
		Admin::render_page();
		$markup = ob_get_clean();

		$this->assertMatchesRegularExpression( '/value="' . $owned_form . '"[^>]*disabled/', $markup );
		$this->assertStringContainsString( 'assigned to Owner', $markup );
		$this->assertStringContainsString( 'Unavailable selected form #999999', $markup );
	}

	/** Step-two source choices use only the persisted common field intersection. */
	public function test_behaviour_editor_uses_persisted_common_fields() {
		$first  = $this->create_saved_form( 'First', true );
		$second = $this->create_saved_form( 'Second', false );
		$id     = $this->create_profile( 'Shared', array( $first, $second ) );
		Settings::update_profile_stage_two(
			$id,
			1,
			array(
				'field_map' => array(
					'FirstName' => array(
						'source'    => 'name',
						'transform' => 'as_is',
					),
				),
				'messages'  => $this->messages( 'shared' ),
			)
		);
		$_GET = array(
			'page'       => Admin::PAGE_SLUG,
			'view'       => 'edit',
			'profile_id' => $id,
		);
		ob_start();
		Admin::render_page();
		$markup = ob_get_clean();

		$this->assertStringContainsString( 'value="email"', $markup );
		$this->assertStringContainsString( 'value="consent"', $markup );
		$this->assertStringContainsString( 'value="name"', $markup );
		$this->assertStringNotContainsString( 'value="extra"', $markup );
	}

	/** Provider failure keeps stored destination and mapping controls visible. */
	public function test_remote_failure_preserves_stored_destination_and_mapping_markup() {
		$form_id = $this->create_saved_form();
		$id      = $this->create_profile( 'Remote failure', array( $form_id ), 'form', 'stored-form' );
		Settings::update_profile_stage_two(
			$id,
			1,
			array(
				'email_source'    => 'email',
				'consent_source'  => 'consent',
				'field_map'       => array(
					'FirstName' => array(
						'source'    => 'name',
						'transform' => 'first_word',
					),
				),
				'success_page_id' => 0,
				'messages'        => $this->messages( 'message' ),
			)
		);
		$_GET = array(
			'page'       => Admin::PAGE_SLUG,
			'view'       => 'edit',
			'profile_id' => $id,
		);
		ob_start();
		Admin::render_page();
		$markup = ob_get_clean();

		$this->assertMatchesRegularExpression( '/<option[^>]*value="form:stored-form"[^>]*selected|<option[^>]*selected[^>]*value="form:stored-form"/', $markup );
		$this->assertStringContainsString( 'profile[field_map][FirstName][source]', $markup );
		$this->assertMatchesRegularExpression( '/<option[^>]*value="name"[^>]*selected|<option[^>]*selected[^>]*value="name"/', $markup );
	}

	/** Delete requires the exact current profile revision. */
	public function test_stale_delete_writes_nothing() {
		$id = $this->create_profile( 'Delete me', array() );
		Settings::update_profile_stage_one(
			$id,
			1,
			array(
				'label'       => 'Updated',
				'form_ids'    => array(),
				'destination' => array(
					'type' => '',
					'id'   => '',
				),
			)
		);
		$_POST    = array(
			'_wpnonce'         => wp_create_nonce( 'ran_emailoctopus_jetpack_forms_delete_profile_' . $id ),
			'profile_id'       => $id,
			'profile_revision' => 1,
		);
		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Test request mirrors the nonce-protected POST.
		Admin::delete_profile();
		$this->assertNotNull( Settings::get_profile( $id ) );
		$this->assertStringContainsString( 'notice=error', end( $this->redirects ) );
	}

	/** Non-administrators cannot render the integration editor. */
	public function test_page_requires_manage_options() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->expectException( WPDieException::class );
		Admin::render_page();
	}

	/** Create one profile with a deterministic UUID. */
	private function create_profile( $label, $forms, $type = 'list', $destination_id = 'list-id' ) {
		$uuid = sprintf( '%08d-0000-4000-8000-%012d', count( Settings::get_profiles() ) + 1, count( Settings::get_profiles() ) + 1 );
		$this->use_uuid_sequence( array( $uuid ) );
		$result = Settings::create_profile(
			array(
				'label'       => $label,
				'form_ids'    => $forms,
				'destination' => array(
					'type' => $type,
					'id'   => $destination_id,
				),
			)
		);
		Settings::set_uuid_factory();
		return $result;
	}

	/** Create a deterministic UUID sequence. */
	private function use_uuid_sequence( $uuids ) {
		Settings::set_uuid_factory(
			static function () use ( &$uuids ) {
				return array_shift( $uuids );
			}
		);
	}

	/** Create one saved Jetpack form. */
	private function create_saved_form( $title = 'Saved form', $extra = false ) {
		$extra_block = $extra ? '<!-- wp:jetpack/field-text --><div><!-- wp:jetpack/label {"label":"Extra"} /--></div><!-- /wp:jetpack/field-text -->' : '';
		$fields      = '<!-- wp:jetpack/field-email --><div><!-- wp:jetpack/label {"label":"Email"} /--></div><!-- /wp:jetpack/field-email -->'
			. '<!-- wp:jetpack/field-checkbox --><div><!-- wp:jetpack/label {"label":"Consent"} /--></div><!-- /wp:jetpack/field-checkbox -->'
			. '<!-- wp:jetpack/field-name --><div><!-- wp:jetpack/label {"label":"Name"} /--></div><!-- /wp:jetpack/field-name -->';
		return self::factory()->post->create(
			array(
				'post_type'    => 'jetpack_form',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => '<!-- wp:jetpack/contact-form --><div class="wp-block-jetpack-contact-form">' . $fields . $extra_block . '</div><!-- /wp:jetpack/contact-form -->',
			)
		);
	}

	/** Build all outcome messages. */
	private function messages( $prefix ) {
		return array(
			'pending'    => $prefix . ' pending',
			'subscribed' => $prefix . ' subscribed',
			'existing'   => $prefix . ' existing',
			'failed'     => $prefix . ' failed',
		);
	}
}
