<?php
/**
 * Integration coverage for EmailOctopus outcome messages.
 *
 * @package RAN_Octopus_Forms
 */

use RAN\OctopusForms\EmailOctopusSubscriber;
use RAN\OctopusForms\JetpackForms;
use RAN\OctopusForms\Patterns;
use RAN\OctopusForms\Settings;
use RAN\OctopusForms\SubmissionMessages;

/**
 * Ensure provider outcomes reach the configured success page safely.
 */
class RAN_Octopus_Forms_Submission_Messages_Test extends WP_UnitTestCase {
	/**
	 * Reset persisted state.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		delete_option( Settings::OPTION_NAME );
		delete_option( 'emailoctopus_api_key' );
		$_GET  = array();
		$_POST = array();
	}

	/**
	 * The EmailOctopus contact response determines the visitor-facing outcome.
	 *
	 * @return void
	 */
	public function test_subscriber_returns_pending_outcome_from_emailoctopus() {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::get_defaults(),
				array(
					'emailoctopus_list_id' => 'newsletter-list',
				)
			)
		);
		update_option( 'emailoctopus_api_key', 'test-api-key' );

		$http_mock = static function ( $preempt, $args, $url ) {
			unset( $args );

			if ( false === strpos( $url, '/lists/newsletter-list/contacts' ) ) {
				return $preempt;
			}

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
		$result = ( new EmailOctopusSubscriber() )->subscribe( 'reader@example.com' );
		remove_filter( 'pre_http_request', $http_mock, 10 );

		$this->assertSame( array( 'outcome' => 'pending' ), $result );
	}

	/**
	 * The success page shows a pending message once, without exposing feedback data.
	 *
	 * @return void
	 */
	public function test_pending_result_is_rendered_once_on_the_success_page() {
		$contact_page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => Patterns::get_contact_form_content(),
			)
		);
		$success_page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:shortcode -->[' . SubmissionMessages::SHORTCODE . ']<!-- /wp:shortcode -->',
			)
		);
		$feedback_id     = self::factory()->post->create();

		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::get_defaults(),
				array(
					'contact_page_id' => $contact_page_id,
					'success_page_id' => $success_page_id,
				)
			)
		);
		update_post_meta( $feedback_id, '_ran_emailoctopus_subscription_status', 'pending' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST = array(
			'contact-form-id'          => (string) $contact_page_id,
			'ran_octopus_forms_target' => wp_create_nonce( 'ran_octopus_forms_target_' . $contact_page_id ),
		);

		$redirect = JetpackForms::redirect_contact_form( 'https://example.org/contact-us/', $contact_page_id, $feedback_id );
		$token    = wp_parse_url( $redirect, PHP_URL_QUERY );

		parse_str( is_string( $token ) ? $token : '', $query_args );
		$this->assertArrayHasKey( SubmissionMessages::RESULT_QUERY_ARG, $query_args );

		$this->go_to( get_permalink( $success_page_id ) );
		$_GET[ SubmissionMessages::RESULT_QUERY_ARG ] = $query_args[ SubmissionMessages::RESULT_QUERY_ARG ];

		$success_page_content = (string) get_post_field( 'post_content', $success_page_id );

		$this->assertStringContainsString( 'There’s one more step', do_blocks( $success_page_content ) );
		$this->assertSame( '', do_blocks( $success_page_content ) );
	}
}
