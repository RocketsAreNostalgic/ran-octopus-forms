<?php
/**
 * Profile-explicit outcome-token security coverage.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

use RAN\EmailOctopusJetpackForms\IntegrationResolver;
use RAN\EmailOctopusJetpackForms\Settings;
use RAN\EmailOctopusJetpackForms\SubmissionMessages;

/**
 * Prove one-time outcomes bind to an existing profile and its success page.
 */
class RAN_EmailOctopus_Jetpack_Forms_Profile_Outcome_Security_Test extends WP_UnitTestCase {
	/**
	 * First immutable profile UUID.
	 */
	const PROFILE_A = '11111111-1111-4111-8111-111111111111';

	/**
	 * Second immutable profile UUID.
	 */
	const PROFILE_B = '22222222-2222-4222-8222-222222222222';

	/**
	 * Reset persisted and request state.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		delete_option( Settings::OPTION_NAME );
		$_GET  = array();
		$_POST = array();
	}

	/**
	 * Only the canonical public shortcode is registered.
	 *
	 * @return void
	 */
	public function test_only_canonical_shortcode_is_registered() {
		$this->assertTrue( shortcode_exists( SubmissionMessages::SHORTCODE ) );
		$this->assertFalse( shortcode_exists( 'ran_octopus_forms_subscription_message' ) );
	}

	/**
	 * A token on the wrong page displays nothing and remains usable once.
	 *
	 * @return void
	 */
	public function test_wrong_page_does_not_consume_token_for_correct_page() {
		$page_a      = $this->create_success_page();
		$page_b      = $this->create_success_page();
		$feedback_id = self::factory()->post->create();

		$this->store_profiles(
			array(
				self::PROFILE_A => $this->profile( 'Alpha', $page_a ),
				self::PROFILE_B => $this->profile( 'Bravo', $page_b ),
			)
		);
		update_post_meta( $feedback_id, '_ran_emailoctopus_subscription_status', 'pending' );
		$token = $this->token_from_redirect( SubmissionMessages::add_result_to_redirect( get_permalink( $page_a ), $feedback_id, IntegrationResolver::get_profile( self::PROFILE_A ) ) );

		$this->go_to( get_permalink( $page_b ) );
		$_GET[ SubmissionMessages::RESULT_QUERY_ARG ] = $token;
		$this->assertSame( '', SubmissionMessages::render_shortcode() );
		$this->assertNotFalse( get_transient( SubmissionMessages::RESULT_TRANSIENT_PREFIX . $token ) );

		$this->go_to( get_permalink( $page_a ) );
		$_GET[ SubmissionMessages::RESULT_QUERY_ARG ] = $token;
		$this->assertStringContainsString( 'Alpha pending', SubmissionMessages::render_shortcode() );
		$this->assertFalse( get_transient( SubmissionMessages::RESULT_TRANSIENT_PREFIX . $token ) );
		$this->assertSame( '', SubmissionMessages::render_shortcode() );
	}

	/**
	 * Shared success pages still render the message carried by the exact profile.
	 *
	 * @return void
	 */
	public function test_shared_page_tokens_render_distinct_profile_messages() {
		$page_id    = $this->create_success_page();
		$feedback_a = self::factory()->post->create();
		$feedback_b = self::factory()->post->create();

		$this->store_profiles(
			array(
				self::PROFILE_A => $this->profile( 'Alpha', $page_id ),
				self::PROFILE_B => $this->profile( 'Bravo', $page_id ),
			)
		);
		update_post_meta( $feedback_a, '_ran_emailoctopus_subscription_status', 'subscribed' );
		update_post_meta( $feedback_b, '_ran_emailoctopus_subscription_status', 'subscribed' );
		$token_a = $this->token_from_redirect( SubmissionMessages::add_result_to_redirect( get_permalink( $page_id ), $feedback_a, IntegrationResolver::get_profile( self::PROFILE_A ) ) );
		$token_b = $this->token_from_redirect( SubmissionMessages::add_result_to_redirect( get_permalink( $page_id ), $feedback_b, IntegrationResolver::get_profile( self::PROFILE_B ) ) );

		$this->go_to( get_permalink( $page_id ) );
		$_GET[ SubmissionMessages::RESULT_QUERY_ARG ] = $token_a;
		$this->assertStringContainsString( 'Alpha subscribed', SubmissionMessages::render_shortcode() );

		$_GET[ SubmissionMessages::RESULT_QUERY_ARG ] = $token_b;
		$this->assertStringContainsString( 'Bravo subscribed', SubmissionMessages::render_shortcode() );
	}

	/**
	 * Deleting a profile makes its outstanding token inert.
	 *
	 * @return void
	 */
	public function test_deleted_profile_makes_outstanding_token_inert() {
		$page_id     = $this->create_success_page();
		$feedback_id = self::factory()->post->create();

		$this->store_profiles( array( self::PROFILE_A => $this->profile( 'Alpha', $page_id ) ) );
		update_post_meta( $feedback_id, '_ran_emailoctopus_subscription_status', 'existing' );
		$token = $this->token_from_redirect( SubmissionMessages::add_result_to_redirect( get_permalink( $page_id ), $feedback_id, IntegrationResolver::get_profile( self::PROFILE_A ) ) );
		$this->store_profiles( array() );

		$this->go_to( get_permalink( $page_id ) );
		$_GET[ SubmissionMessages::RESULT_QUERY_ARG ] = $token;

		$this->assertSame( '', SubmissionMessages::render_shortcode() );
	}

	/**
	 * An empty configured message does not consume an otherwise valid token.
	 *
	 * @return void
	 */
	public function test_empty_profile_message_preserves_token() {
		$page_id     = $this->create_success_page();
		$feedback_id = self::factory()->post->create();
		$profile     = $this->profile( 'Alpha', $page_id );

		$profile['messages']['pending'] = '';
		$this->store_profiles( array( self::PROFILE_A => $profile ) );
		update_post_meta( $feedback_id, '_ran_emailoctopus_subscription_status', 'pending' );
		$token = $this->token_from_redirect( SubmissionMessages::add_result_to_redirect( get_permalink( $page_id ), $feedback_id, IntegrationResolver::get_profile( self::PROFILE_A ) ) );

		$this->go_to( get_permalink( $page_id ) );
		$_GET[ SubmissionMessages::RESULT_QUERY_ARG ] = $token;

		$this->assertSame( '', SubmissionMessages::render_shortcode() );
		$this->assertNotFalse( get_transient( SubmissionMessages::RESULT_TRANSIENT_PREFIX . $token ) );
	}

	/**
	 * Invalid and absent result tokens do not emit visitor content.
	 *
	 * @return void
	 */
	public function test_missing_tampered_and_unknown_tokens_render_nothing() {
		$page_id = $this->create_success_page();

		$this->store_profiles( array( self::PROFILE_A => $this->profile( 'Alpha', $page_id ) ) );
		$this->go_to( get_permalink( $page_id ) );
		$this->assertSame( '', SubmissionMessages::render_shortcode() );

		$_GET[ SubmissionMessages::RESULT_QUERY_ARG ] = 'invalid';
		$this->assertSame( '', SubmissionMessages::render_shortcode() );

		$_GET[ SubmissionMessages::RESULT_QUERY_ARG ] = str_repeat( 'A', 32 );
		$this->assertSame( '', SubmissionMessages::render_shortcode() );
	}

	/**
	 * Cache bypass never consumes a valid outcome token.
	 *
	 * @return void
	 */
	public function test_cache_bypass_does_not_consume_token() {
		$page_a = $this->create_success_page();

		$this->store_profiles( array( self::PROFILE_A => $this->profile( 'Alpha', $page_a ) ) );
		$token = str_repeat( 'B', 32 );
		set_transient(
			SubmissionMessages::RESULT_TRANSIENT_PREFIX . $token,
			array(
				'profile_id' => self::PROFILE_A,
				'outcome'    => 'failed',
			),
			MINUTE_IN_SECONDS
		);
		$this->go_to( get_permalink( $page_a ) );
		$_GET[ SubmissionMessages::RESULT_QUERY_ARG ] = $token;

		SubmissionMessages::disable_cache_for_result();

		$this->assertTrue( defined( 'DONOTCACHEPAGE' ) );
		$this->assertNotFalse( get_transient( SubmissionMessages::RESULT_TRANSIENT_PREFIX . $token ) );
	}

	/**
	 * Persist canonical profiles.
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
	 * Build one outcome-capable profile fixture.
	 *
	 * @param string $label   Profile label.
	 * @param int    $page_id Success page ID.
	 * @return array<string,mixed>
	 */
	private function profile( $label, $page_id ) {
		return array(
			'revision'        => 1,
			'label'           => $label,
			'form_ids'        => array(),
			'destination'     => array(
				'type' => '',
				'id'   => '',
			),
			'email_source'    => '',
			'consent_source'  => '',
			'field_map'       => array(),
			'success_page_id' => $page_id,
			'messages'        => array(
				'pending'    => $label . ' pending',
				'subscribed' => $label . ' subscribed',
				'existing'   => $label . ' existing',
				'failed'     => $label . ' failed',
			),
		);
	}

	/**
	 * Create a published success page.
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
	 * Extract a result token from a redirect URL.
	 *
	 * @param string $redirect Redirect URL.
	 * @return string
	 */
	private function token_from_redirect( $redirect ) {
		parse_str( (string) wp_parse_url( $redirect, PHP_URL_QUERY ), $query_args );

		$this->assertArrayHasKey( SubmissionMessages::RESULT_QUERY_ARG, $query_args );

		return (string) $query_args[ SubmissionMessages::RESULT_QUERY_ARG ];
	}
}
