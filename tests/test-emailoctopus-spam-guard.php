<?php
/**
 * Integration coverage for rejected-feedback subscription guards.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

use RAN\EmailOctopusJetpackForms\JetpackForms;
use RAN\EmailOctopusJetpackForms\Patterns;
use RAN\EmailOctopusJetpackForms\Settings;

/**
 * Ensure Jetpack-rejected feedback cannot trigger EmailOctopus side effects.
 */
class RAN_EmailOctopus_Jetpack_Forms_EmailOctopus_Spam_Guard_Test extends WP_UnitTestCase {
	/**
	 * Reset persisted integration and request state.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		delete_option( Settings::OPTION_NAME );
		delete_option( 'emailoctopus_api_key' );
		$_POST = array();
	}

	/**
	 * Spam and blocklisted feedback must not reach EmailOctopus.
	 *
	 * @dataProvider rejected_feedback_status_provider
	 *
	 * @param string $feedback_status Rejected Jetpack feedback status.
	 * @return void
	 */
	public function test_rejected_feedback_does_not_subscribe( $feedback_status ) {
		$feedback_id   = $this->configure_target_submission( $feedback_status );
		$request_count = 0;
		$http_mock     = static function ( $preempt ) use ( &$request_count ) {
			++$request_count;
			return $preempt;
		};

		add_filter( 'pre_http_request', $http_mock );
		$this->run_subscription_callback( $feedback_id );
		remove_filter( 'pre_http_request', $http_mock );

		$this->assertSame( 0, $request_count );
		$this->assertSame( '', get_post_meta( $feedback_id, '_ran_emailoctopus_subscription_status', true ) );
	}

	/**
	 * Accepted feedback continues through the existing subscription path.
	 *
	 * @return void
	 */
	public function test_published_feedback_still_subscribes() {
		$feedback_id   = $this->configure_target_submission( 'publish' );
		$request_count = 0;
		$http_mock     = static function ( $preempt, $args, $url ) use ( &$request_count ) {
			unset( $preempt, $args, $url );
			++$request_count;

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
		$this->run_subscription_callback( $feedback_id );
		remove_filter( 'pre_http_request', $http_mock, 10 );

		$this->assertSame( 1, $request_count );
		$this->assertSame( 'pending', get_post_meta( $feedback_id, '_ran_emailoctopus_subscription_status', true ) );
	}

	/**
	 * Rejected statuses assigned by Jetpack.
	 *
	 * @return array<string,array{string}>
	 */
	public function rejected_feedback_status_provider() {
		return array(
			'Akismet spam'             => array( 'spam' ),
			'disallowed content trash' => array( 'trash' ),
		);
	}

	/**
	 * Configure a valid marked submission and create its feedback post.
	 *
	 * @param string $feedback_status Feedback post status.
	 * @return int
	 */
	private function configure_target_submission( $feedback_status ) {
		$contact_page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => Patterns::get_contact_form_content(),
			)
		);
		$feedback_id     = self::factory()->post->create(
			array(
				'post_status' => $feedback_status,
			)
		);

		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::get_defaults(),
				array(
					'contact_page_id'           => $contact_page_id,
					'emailoctopus_list_id'      => 'newsletter-list',
					'emailoctopus_email_source' => 'email',
					'newsletter_source'         => 'join_our_newsletter',
				)
			)
		);
		update_option( 'emailoctopus_api_key', 'test-api-key' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$_POST = array(
			'contact-form-id'          => (string) $contact_page_id,
			'ran_octopus_forms_target' => wp_create_nonce( 'ran_octopus_forms_target_' . $contact_page_id ),
		);

		return $feedback_id;
	}

	/**
	 * Invoke the Jetpack post-submission integration with an opted-in visitor.
	 *
	 * @param int $feedback_id Feedback post ID.
	 * @return void
	 */
	private function run_subscription_callback( $feedback_id ) {
		JetpackForms::subscribe_newsletter_opt_in(
			$feedback_id,
			array(),
			'',
			'',
			array(),
			array(
				'1_Email'                => 'reader@example.com',
				'2_Join our newsletter!' => 'Yes',
			),
			array()
		);
	}
}
