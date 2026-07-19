<?php
/**
 * Profile-explicit rendering and submission coverage.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

use Automattic\Jetpack\Forms\ContactForm\Feedback;
use RAN\EmailOctopusJetpackForms\JetpackForms;
use RAN\EmailOctopusJetpackForms\Settings;
use RAN\EmailOctopusJetpackForms\SubmissionMessages;

/**
 * Prove independent profiles cannot bleed or forge runtime context.
 */
class RAN_EmailOctopus_Jetpack_Forms_Profile_Runtime_Isolation_Test extends WP_UnitTestCase {
	/**
	 * First immutable profile UUID.
	 */
	const PROFILE_A = '11111111-1111-4111-8111-111111111111';

	/**
	 * Second immutable profile UUID.
	 */
	const PROFILE_B = '22222222-2222-4222-8222-222222222222';

	/**
	 * Reset integration and request state.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		delete_option( Settings::OPTION_NAME );
		delete_option( 'emailoctopus_api_key' );
		Feedback::$form_ids = array();
		$_GET               = array();
		$_POST              = array();
	}

	/**
	 * Distinct profiles render their own signed identities on the same route.
	 *
	 * @return void
	 */
	public function test_two_profiles_on_one_route_receive_distinct_signed_contexts() {
		$form_a   = $this->create_saved_form();
		$form_b   = $this->create_saved_form();
		$success  = $this->create_success_page();
		$route_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$this->store_profiles(
			array(
				self::PROFILE_A => $this->profile( 'Alpha', array( $form_a ), $success, 'list-alpha' ),
				self::PROFILE_B => $this->profile( 'Bravo', array( $form_b ), $success, 'list-bravo' ),
			)
		);
		$this->go_to( get_permalink( $route_id ) );

		$context_a = $this->context_from_html( $this->render_form_html( $form_a ) );
		$context_b = $this->context_from_html( $this->render_form_html( $form_b ) );

		$this->assertSame( self::PROFILE_A, $context_a[ JetpackForms::PROFILE_FIELD ] );
		$this->assertSame( (string) $form_a, $context_a[ JetpackForms::FORM_REF_FIELD ] );
		$this->assertSame( self::PROFILE_B, $context_b[ JetpackForms::PROFILE_FIELD ] );
		$this->assertSame( (string) $form_b, $context_b[ JetpackForms::FORM_REF_FIELD ] );
		$this->assertNotSame( $context_a[ JetpackForms::TARGET_FIELD ], $context_b[ JetpackForms::TARGET_FIELD ] );
	}

	/**
	 * One profile's saved form remains portable from different singular routes.
	 *
	 * @return void
	 */
	public function test_profile_form_is_signed_identically_on_page_and_post_routes() {
		$form_id = $this->create_saved_form();
		$success = $this->create_success_page();

		$this->store_profiles( array( self::PROFILE_A => $this->profile( 'Alpha', array( $form_id ), $success, 'list-alpha' ) ) );

		foreach ( array( 'page', 'post' ) as $post_type ) {
			$route_id = self::factory()->post->create(
				array(
					'post_status' => 'publish',
					'post_type'   => $post_type,
				)
			);
			$this->go_to( get_permalink( $route_id ) );
			$context = $this->context_from_html( $this->render_form_html( $form_id ) );

			$this->assertSame( self::PROFILE_A, $context[ JetpackForms::PROFILE_FIELD ] );
			$this->assertSame( (string) $form_id, $context[ JetpackForms::FORM_REF_FIELD ] );
		}
	}

	/**
	 * Several forms in one profile keep the shared profile and exact form reference.
	 *
	 * @return void
	 */
	public function test_multiple_forms_in_one_profile_share_identity_but_not_reference() {
		$form_a  = $this->create_saved_form();
		$form_b  = $this->create_saved_form();
		$success = $this->create_success_page();

		$this->store_profiles( array( self::PROFILE_A => $this->profile( 'Alpha', array( $form_a, $form_b ), $success, 'list-alpha' ) ) );
		$context_a = $this->context_from_html( $this->render_form_html( $form_a ) );
		$context_b = $this->context_from_html( $this->render_form_html( $form_b ) );

		$this->assertSame( self::PROFILE_A, $context_a[ JetpackForms::PROFILE_FIELD ] );
		$this->assertSame( self::PROFILE_A, $context_b[ JetpackForms::PROFILE_FIELD ] );
		$this->assertSame( (string) $form_a, $context_a[ JetpackForms::FORM_REF_FIELD ] );
		$this->assertSame( (string) $form_b, $context_b[ JetpackForms::FORM_REF_FIELD ] );
		$this->assertNotSame( $context_a[ JetpackForms::TARGET_FIELD ], $context_b[ JetpackForms::TARGET_FIELD ] );
	}

	/**
	 * Unassigned forms retain native Jetpack markup and AJAX behavior.
	 *
	 * @return void
	 */
	public function test_unassigned_form_is_untouched_and_render_context_resets() {
		$form_id    = $this->create_saved_form();
		$unassigned = $this->create_saved_form();
		$success    = $this->create_success_page();

		$this->store_profiles( array( self::PROFILE_A => $this->profile( 'Alpha', array( $form_id ), $success, 'list-alpha' ) ) );

		$this->assertNotSame( '<form></form>', $this->render_form_html( $form_id ) );
		$this->assertSame( '<form></form>', $this->render_form_html( $unassigned ) );
		$this->assertTrue( JetpackForms::disable_ajax_for_contact_form( true ) );
	}

	/**
	 * Corrupt duplicate ownership fails closed only for the shared form.
	 *
	 * @return void
	 */
	public function test_duplicate_ownership_isolates_only_conflicted_form() {
		$shared_form = $this->create_saved_form();
		$form_a      = $this->create_saved_form();
		$form_b      = $this->create_saved_form();
		$success     = $this->create_success_page();

		$this->store_profiles(
			array(
				self::PROFILE_A => $this->profile( 'Alpha', array( $shared_form, $form_a ), $success, 'list-alpha' ),
				self::PROFILE_B => $this->profile( 'Bravo', array( $shared_form, $form_b ), $success, 'list-bravo' ),
			)
		);

		$this->assertSame( '<form></form>', $this->render_form_html( $shared_form ) );
		$this->assertSame( self::PROFILE_A, $this->context_from_html( $this->render_form_html( $form_a ) )[ JetpackForms::PROFILE_FIELD ] );
		$this->assertSame( self::PROFILE_B, $this->context_from_html( $this->render_form_html( $form_b ) )[ JetpackForms::PROFILE_FIELD ] );
	}

	/**
	 * Missing, changed, and cross-profile marker components fail closed.
	 *
	 * @dataProvider tampered_context_provider
	 *
	 * @param string $mutation Marker mutation.
	 * @return void
	 */
	public function test_tampered_or_swapped_profile_context_fails_closed( $mutation ) {
		$form_a      = $this->create_saved_form();
		$form_b      = $this->create_saved_form();
		$success     = $this->create_success_page();
		$feedback_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$this->store_profiles(
			array(
				self::PROFILE_A => $this->profile( 'Alpha', array( $form_a ), $success, 'list-alpha' ),
				self::PROFILE_B => $this->profile( 'Bravo', array( $form_b ), $success, 'list-bravo' ),
			)
		);
		$context_a = $this->context_from_html( $this->render_form_html( $form_a ) );
		$context_b = $this->context_from_html( $this->render_form_html( $form_b ) );

		switch ( $mutation ) {
			case 'missing_profile':
				unset( $context_a[ JetpackForms::PROFILE_FIELD ] );
				break;
			case 'missing_form':
				unset( $context_a[ JetpackForms::FORM_REF_FIELD ] );
				break;
			case 'missing_nonce':
				unset( $context_a[ JetpackForms::TARGET_FIELD ] );
				break;
			case 'changed_profile':
				$context_a[ JetpackForms::PROFILE_FIELD ] = self::PROFILE_B;
				break;
			case 'changed_form':
				$context_a[ JetpackForms::FORM_REF_FIELD ] = (string) $form_b;
				break;
			case 'changed_nonce':
				$context_a[ JetpackForms::TARGET_FIELD ] = 'invalid';
				break;
			case 'whole_marker_swap':
				$context_a = $context_b;
				break;
		}

		$_POST                              = $context_a;
		Feedback::$form_ids[ $feedback_id ] = $form_a;

		$this->assertFalse( JetpackForms::is_target_submission( $feedback_id ) );
		$this->assertSame( 'https://example.org/native/', JetpackForms::redirect_contact_form( 'https://example.org/native/', 0, $feedback_id ) );
	}

	/**
	 * Marker mutations rejected by the runtime.
	 *
	 * @return array<string,array{string}>
	 */
	public function tampered_context_provider() {
		return array(
			'missing profile'   => array( 'missing_profile' ),
			'missing form'      => array( 'missing_form' ),
			'missing nonce'     => array( 'missing_nonce' ),
			'changed profile'   => array( 'changed_profile' ),
			'changed form'      => array( 'changed_form' ),
			'changed nonce'     => array( 'changed_nonce' ),
			'whole marker swap' => array( 'whole_marker_swap' ),
		);
	}

	/**
	 * Jetpack's authoritative form ID must exactly match the signed reference.
	 *
	 * @return void
	 */
	public function test_authoritative_feedback_identity_mismatch_fails_closed() {
		$form_a      = $this->create_saved_form();
		$form_b      = $this->create_saved_form();
		$success     = $this->create_success_page();
		$feedback_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );

		$this->store_profiles( array( self::PROFILE_A => $this->profile( 'Alpha', array( $form_a, $form_b ), $success, 'list-alpha' ) ) );
		$_POST                              = $this->context_from_html( $this->render_form_html( $form_a ) );
		Feedback::$form_ids[ $feedback_id ] = $form_b;

		$this->assertTrue( JetpackForms::is_target_submission() );
		$this->assertFalse( JetpackForms::is_target_submission( $feedback_id ) );
		$this->assertSame( 'https://example.org/native/', JetpackForms::redirect_contact_form( 'https://example.org/native/', 0, $feedback_id ) );
	}

	/**
	 * Markers become stale when their profile, ownership, or target disappears.
	 *
	 * @dataProvider stale_context_provider
	 *
	 * @param string $mutation Stored state mutation.
	 * @return void
	 */
	public function test_deleted_removed_or_invalidated_profile_context_fails_closed( $mutation ) {
		$form_id = $this->create_saved_form();
		$success = $this->create_success_page();
		$profile = $this->profile( 'Alpha', array( $form_id ), $success, 'list-alpha' );

		$this->store_profiles( array( self::PROFILE_A => $profile ) );
		$_POST = $this->context_from_html( $this->render_form_html( $form_id ) );

		if ( 'deleted_profile' === $mutation ) {
			$this->store_profiles( array() );
		} elseif ( 'removed_form' === $mutation ) {
			$profile['form_ids'] = array();
			$this->store_profiles( array( self::PROFILE_A => $profile ) );
		} elseif ( 'reassigned_form' === $mutation ) {
			$profile['form_ids'] = array();
			$this->store_profiles(
				array(
					self::PROFILE_A => $profile,
					self::PROFILE_B => $this->profile( 'Bravo', array( $form_id ), $success, 'list-bravo' ),
				)
			);
		} elseif ( 'draft_form' === $mutation ) {
			wp_update_post(
				array(
					'ID'          => $form_id,
					'post_status' => 'draft',
				)
			);
		} else {
			wp_update_post(
				array(
					'ID'           => $form_id,
					'post_content' => '<!-- wp:paragraph --><p>Broken</p><!-- /wp:paragraph -->',
				)
			);
		}

		$this->assertFalse( JetpackForms::is_target_submission() );
	}

	/**
	 * Stored state invalidations rejected by the runtime.
	 *
	 * @return array<string,array{string}>
	 */
	public function stale_context_provider() {
		return array(
			'deleted profile' => array( 'deleted_profile' ),
			'removed form'    => array( 'removed_form' ),
			'reassigned form' => array( 'reassigned_form' ),
			'draft form'      => array( 'draft_form' ),
			'malformed form'  => array( 'malformed_form' ),
		);
	}

	/**
	 * Destinations, field mappings, consent, and redirects stay profile-local.
	 *
	 * @return void
	 */
	public function test_distinct_profile_subscription_configuration_does_not_bleed() {
		$form_a    = $this->create_saved_form( 'Email Alpha', 'Consent Alpha', 'Name Alpha' );
		$form_b    = $this->create_saved_form( 'Email Bravo', 'Consent Bravo', 'Name Bravo' );
		$success_a = $this->create_success_page();
		$success_b = $this->create_success_page();
		$profile_a = $this->profile( 'Alpha', array( $form_a ), $success_a, 'list-alpha', 'email_alpha', 'consent_alpha', 'name_alpha', 'AlphaName' );
		$profile_b = $this->profile( 'Bravo', array( $form_b ), $success_b, 'list-bravo', 'email_bravo', 'consent_bravo', 'name_bravo', 'BravoName' );

		$this->store_profiles(
			array(
				self::PROFILE_A => $profile_a,
				self::PROFILE_B => $profile_b,
			)
		);
		update_option( 'emailoctopus_api_key', 'test-api-key' );
		$requests  = array();
		$http_mock = static function ( $preempt, $args, $url ) use ( &$requests ) {
			$requests[] = array(
				'url'  => $url,
				'body' => json_decode( (string) ( $args['body'] ?? '' ), true ),
			);

			return array(
				'headers'  => array(),
				'body'     => wp_json_encode( array( 'status' => 'PENDING' ) ),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
			);
		};
		add_filter( 'pre_http_request', $http_mock, 10, 3 );

		$feedback_a = $this->submit(
			self::PROFILE_A,
			$form_a,
			array(
				'1_Email Alpha'   => 'alpha@example.com',
				'2_Consent Alpha' => 'Yes',
				'3_Name Alpha'    => 'Alice Alpha',
			)
		);
		$feedback_b = $this->submit(
			self::PROFILE_B,
			$form_b,
			array(
				'1_Email Bravo'   => 'bravo@example.com',
				'2_Consent Bravo' => 'Yes',
				'3_Name Bravo'    => 'Bob Bravo',
			)
		);

		remove_filter( 'pre_http_request', $http_mock, 10 );

		$this->assertCount( 2, $requests );
		$this->assertStringContainsString( '/lists/list-alpha/contacts', $requests[0]['url'] );
		$this->assertSame( 'alpha@example.com', $requests[0]['body']['email_address'] );
		$this->assertSame( array( 'AlphaName' => 'Alice Alpha' ), $requests[0]['body']['fields'] );
		$this->assertStringContainsString( '/lists/list-bravo/contacts', $requests[1]['url'] );
		$this->assertSame( 'bravo@example.com', $requests[1]['body']['email_address'] );
		$this->assertSame( array( 'BravoName' => 'Bob Bravo' ), $requests[1]['body']['fields'] );

		$_POST = $this->context_from_html( $this->render_form_html( $form_a ) );
		$this->assertStringStartsWith( get_permalink( $success_a ), JetpackForms::redirect_contact_form( 'https://example.org/native/', 0, $feedback_a ) );

		$_POST = $this->context_from_html( $this->render_form_html( $form_b ) );
		$this->assertStringStartsWith( get_permalink( $success_b ), JetpackForms::redirect_contact_form( 'https://example.org/native/', 0, $feedback_b ) );
	}

	/**
	 * A missing required source prevents only that form's EmailOctopus request.
	 *
	 * @return void
	 */
	public function test_missing_configured_source_blocks_only_affected_form() {
		$broken_form = $this->create_saved_form( 'Email', 'Consent', 'Other name' );
		$valid_form  = $this->create_saved_form( 'Email', 'Consent', 'Name' );
		$success     = $this->create_success_page();
		$profile_a   = $this->profile( 'Broken', array( $broken_form ), $success, 'list-broken', 'email', 'consent', 'name', 'Name' );
		$profile_b   = $this->profile( 'Valid', array( $valid_form ), $success, 'list-valid', 'email', 'consent', 'name', 'Name' );

		$this->store_profiles(
			array(
				self::PROFILE_A => $profile_a,
				self::PROFILE_B => $profile_b,
			)
		);
		update_option( 'emailoctopus_api_key', 'test-api-key' );
		$request_count = 0;
		$http_mock     = static function () use ( &$request_count ) {
			++$request_count;

			return array(
				'headers'  => array(),
				'body'     => wp_json_encode( array( 'status' => 'SUBSCRIBED' ) ),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
			);
		};
		add_filter( 'pre_http_request', $http_mock, 10, 3 );

		$broken_feedback = $this->submit(
			self::PROFILE_A,
			$broken_form,
			array(
				'1_Email'      => 'broken@example.com',
				'2_Consent'    => 'Yes',
				'3_Other name' => 'No Mapping',
			)
		);
		$valid_feedback  = $this->submit(
			self::PROFILE_B,
			$valid_form,
			array(
				'1_Email'   => 'valid@example.com',
				'2_Consent' => 'Yes',
				'3_Name'    => 'Valid Mapping',
			)
		);

		remove_filter( 'pre_http_request', $http_mock, 10 );

		$this->assertSame( 1, $request_count );
		$this->assertSame( '', get_post_meta( $broken_feedback, '_ran_emailoctopus_subscription_status', true ) );
		$this->assertSame( 'subscribed', get_post_meta( $valid_feedback, '_ran_emailoctopus_subscription_status', true ) );
	}

	/**
	 * A missing source isolates one form without pausing a compatible profile peer.
	 *
	 * @return void
	 */
	public function test_missing_source_blocks_only_affected_form_inside_one_profile() {
		$broken_form = $this->create_saved_form( 'Email', 'Consent', 'Other name' );
		$valid_form  = $this->create_saved_form( 'Email', 'Consent', 'Name' );
		$success     = $this->create_success_page();
		$profile     = $this->profile( 'Shared', array( $broken_form, $valid_form ), $success, 'list-shared', 'email', 'consent', 'name', 'Name' );

		$this->store_profiles( array( self::PROFILE_A => $profile ) );
		update_option( 'emailoctopus_api_key', 'test-api-key' );
		$request_count = 0;
		$http_mock     = static function () use ( &$request_count ) {
			++$request_count;

			return array(
				'headers'  => array(),
				'body'     => wp_json_encode( array( 'status' => 'SUBSCRIBED' ) ),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
			);
		};
		add_filter( 'pre_http_request', $http_mock, 10, 3 );

		$broken_feedback = $this->submit(
			self::PROFILE_A,
			$broken_form,
			array(
				'1_Email'      => 'broken@example.com',
				'2_Consent'    => 'Yes',
				'3_Other name' => 'No Mapping',
			)
		);
		$valid_feedback  = $this->submit(
			self::PROFILE_A,
			$valid_form,
			array(
				'1_Email'   => 'valid@example.com',
				'2_Consent' => 'Yes',
				'3_Name'    => 'Valid Mapping',
			)
		);

		remove_filter( 'pre_http_request', $http_mock, 10 );

		$this->assertSame( 1, $request_count );
		$this->assertSame( '', get_post_meta( $broken_feedback, '_ran_emailoctopus_subscription_status', true ) );
		$this->assertSame( 'subscribed', get_post_meta( $valid_feedback, '_ran_emailoctopus_subscription_status', true ) );
	}

	/**
	 * Blank optional mapped values are omitted without blocking subscription.
	 *
	 * @return void
	 */
	public function test_blank_optional_mapped_value_is_omitted_from_payload() {
		$form_id = $this->create_saved_form();
		$success = $this->create_success_page();
		$profile = $this->profile( 'Alpha', array( $form_id ), $success, 'list-alpha' );

		$this->store_profiles( array( self::PROFILE_A => $profile ) );
		update_option( 'emailoctopus_api_key', 'test-api-key' );
		$request_body = array();
		$http_mock    = static function ( $preempt, $args ) use ( &$request_body ) {
			unset( $preempt );
			$request_body = json_decode( (string) ( $args['body'] ?? '' ), true );

			return array(
				'headers'  => array(),
				'body'     => wp_json_encode( array( 'status' => 'PENDING' ) ),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
			);
		};
		add_filter( 'pre_http_request', $http_mock, 10, 2 );
		$this->submit(
			self::PROFILE_A,
			$form_id,
			array(
				'1_Email'   => 'reader@example.com',
				'2_Consent' => 'Yes',
				'3_Name'    => '',
			)
		);
		remove_filter( 'pre_http_request', $http_mock, 10 );

		$this->assertArrayNotHasKey( 'fields', $request_body );
	}

	/**
	 * An incomplete destination skips EmailOctopus without recording an outcome.
	 *
	 * @return void
	 */
	public function test_missing_effective_destination_has_no_emailoctopus_side_effect() {
		$form_id                = $this->create_saved_form();
		$success                = $this->create_success_page();
		$profile                = $this->profile( 'Incomplete', array( $form_id ), $success, 'unused-list' );
		$profile['destination'] = array(
			'type' => '',
			'id'   => '',
		);

		$this->store_profiles( array( self::PROFILE_A => $profile ) );
		update_option( 'emailoctopus_api_key', 'test-api-key' );
		$request_count = 0;
		$http_mock     = static function ( $preempt ) use ( &$request_count ) {
			++$request_count;
			return $preempt;
		};
		add_filter( 'pre_http_request', $http_mock );

		$feedback_id = $this->submit(
			self::PROFILE_A,
			$form_id,
			array(
				'1_Email'   => 'reader@example.com',
				'2_Consent' => 'Yes',
				'3_Name'    => 'Reader',
			)
		);

		remove_filter( 'pre_http_request', $http_mock );
		$this->assertSame( 0, $request_count );
		$this->assertSame( '', get_post_meta( $feedback_id, '_ran_emailoctopus_subscription_status', true ) );
		$this->assertSame( '', get_post_meta( $feedback_id, '_ran_emailoctopus_subscription_error', true ) );
	}

	/**
	 * An invalid success target leaves Jetpack rendering and AJAX untouched.
	 *
	 * @dataProvider invalid_success_target_provider
	 *
	 * @param string $invalidity Invalid success target kind.
	 * @return void
	 */
	public function test_invalid_success_target_preserves_native_jetpack_handling( $invalidity ) {
		$form_id = $this->create_saved_form();

		if ( 'draft' === $invalidity ) {
			$success_id = self::factory()->post->create(
				array(
					'post_type'   => 'page',
					'post_status' => 'draft',
				)
			);
		} else {
			$success_id = self::factory()->post->create(
				array(
					'post_type'   => 'post',
					'post_status' => 'publish',
				)
			);
		}

		$this->store_profiles( array( self::PROFILE_A => $this->profile( 'Invalid success', array( $form_id ), $success_id, 'list-alpha' ) ) );

		$this->assertSame( '<form></form>', $this->render_form_html( $form_id ) );
		$this->assertTrue( JetpackForms::disable_ajax_for_contact_form( true ) );
	}

	/**
	 * Invalid success target fixtures.
	 *
	 * @return array<string,array{string}>
	 */
	public function invalid_success_target_provider() {
		return array(
			'draft page'         => array( 'draft' ),
			'published non-page' => array( 'wrong_type' ),
		);
	}

	/**
	 * Spam and trash feedback never reach EmailOctopus.
	 *
	 * @dataProvider rejected_status_provider
	 *
	 * @param string $status Rejected feedback status.
	 * @return void
	 */
	public function test_spam_and_trash_feedback_never_reach_emailoctopus( $status ) {
		$form_id = $this->create_saved_form();
		$success = $this->create_success_page();

		$this->store_profiles( array( self::PROFILE_A => $this->profile( 'Alpha', array( $form_id ), $success, 'list-alpha' ) ) );
		update_option( 'emailoctopus_api_key', 'test-api-key' );
		$request_count = 0;
		$http_mock     = static function ( $preempt ) use ( &$request_count ) {
			++$request_count;
			return $preempt;
		};
		add_filter( 'pre_http_request', $http_mock );

		$this->submit(
			self::PROFILE_A,
			$form_id,
			array(
				'1_Email'   => 'reader@example.com',
				'2_Consent' => 'Yes',
			),
			$status
		);

		remove_filter( 'pre_http_request', $http_mock );
		$this->assertSame( 0, $request_count );
	}

	/**
	 * Rejected Jetpack feedback statuses.
	 *
	 * @return array<string,array{string}>
	 */
	public function rejected_status_provider() {
		return array(
			'Akismet spam' => array( 'spam' ),
			'trash'        => array( 'trash' ),
		);
	}

	/**
	 * Persist a canonical profile store.
	 *
	 * @param array<string,array<string,mixed>> $profiles Profiles keyed by UUID.
	 * @return void
	 */
	private function store_profiles( $profiles ) {
		update_option(
			Settings::OPTION_NAME,
			array(
				'schema_version' => 1,
				'revision'       => 1,
				'profiles'       => $profiles,
			)
		);
	}

	/**
	 * Build one canonical profile fixture.
	 *
	 * @param string         $label          Profile label.
	 * @param array<int,int> $form_ids       Saved forms.
	 * @param int            $success_page   Success page.
	 * @param string         $list_id        Destination list.
	 * @param string         $email_source   Email source.
	 * @param string         $consent_source Consent source.
	 * @param string         $custom_source  Custom source.
	 * @param string         $custom_tag     Destination field tag.
	 * @return array<string,mixed>
	 */
	private function profile( $label, $form_ids, $success_page, $list_id, $email_source = 'email', $consent_source = 'consent', $custom_source = 'name', $custom_tag = 'Name' ) {
		return array(
			'revision'        => 1,
			'label'           => $label,
			'form_ids'        => $form_ids,
			'destination'     => array(
				'type' => 'list',
				'id'   => $list_id,
			),
			'email_source'    => $email_source,
			'consent_source'  => $consent_source,
			'field_map'       => array(
				$custom_tag => array(
					'source'    => $custom_source,
					'transform' => 'as_is',
				),
			),
			'success_page_id' => $success_page,
			'messages'        => array(
				'pending'    => $label . ' pending',
				'subscribed' => $label . ' subscribed',
				'existing'   => $label . ' existing',
				'failed'     => $label . ' failed',
			),
		);
	}

	/**
	 * Create a structurally valid saved Jetpack form.
	 *
	 * @param string $email_label   Email label.
	 * @param string $consent_label Consent label.
	 * @param string $custom_label  Custom field label.
	 * @return int
	 */
	private function create_saved_form( $email_label = 'Email', $consent_label = 'Consent', $custom_label = 'Name' ) {
		$fields = '<!-- wp:jetpack/field-email --><div><!-- wp:jetpack/label {"label":"' . esc_attr( $email_label ) . '"} /--></div><!-- /wp:jetpack/field-email -->'
			. '<!-- wp:jetpack/field-checkbox --><div><!-- wp:jetpack/label {"label":"' . esc_attr( $consent_label ) . '"} /--></div><!-- /wp:jetpack/field-checkbox -->'
			. '<!-- wp:jetpack/field-name --><div><!-- wp:jetpack/label {"label":"' . esc_attr( $custom_label ) . '"} /--></div><!-- /wp:jetpack/field-name -->';

		return self::factory()->post->create(
			array(
				'post_type'    => 'jetpack_form',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:jetpack/contact-form --><div class="wp-block-jetpack-contact-form">' . $fields . '</div><!-- /wp:jetpack/contact-form -->',
			)
		);
	}

	/**
	 * Create one published success page.
	 *
	 * @return int
	 */
	private function create_success_page() {
		return self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * Render minimal form HTML inside one saved-form reference context.
	 *
	 * @param int $form_id Saved form ID.
	 * @return string
	 */
	private function render_form_html( $form_id ) {
		$block = array(
			'blockName' => 'jetpack/contact-form',
			'attrs'     => array( 'ref' => $form_id ),
		);

		JetpackForms::before_render_block( null, $block );
		$html = JetpackForms::mark_target_form_submission( '<form></form>' );
		JetpackForms::after_render_block( '', $block );

		return $html;
	}

	/**
	 * Extract hidden fields from rendered form HTML.
	 *
	 * @param string $html Form HTML.
	 * @return array<string,string>
	 */
	private function context_from_html( $html ) {
		preg_match_all( '/name="([^"]+)" value="([^"]*)"/', $html, $matches, PREG_SET_ORDER );
		$context = array();

		foreach ( $matches as $match ) {
			$context[ $match[1] ] = html_entity_decode( $match[2], ENT_QUOTES );
		}

		return $context;
	}

	/**
	 * Submit one signed form through the post-send hook.
	 *
	 * @param string              $profile_id Profile UUID.
	 * @param int                 $form_id    Saved form ID.
	 * @param array<string,mixed> $values     Submitted values.
	 * @param string              $status     Feedback status.
	 * @return int
	 */
	private function submit( $profile_id, $form_id, $values, $status = 'publish' ) {
		$feedback_id = self::factory()->post->create( array( 'post_status' => $status ) );
		$context     = $this->context_from_html( $this->render_form_html( $form_id ) );

		$this->assertSame( $profile_id, $context[ JetpackForms::PROFILE_FIELD ] );
		$_POST                              = $context;
		Feedback::$form_ids[ $feedback_id ] = $form_id;
		JetpackForms::subscribe_newsletter_opt_in( $feedback_id, array(), '', '', array(), $values, array() );

		return $feedback_id;
	}
}
