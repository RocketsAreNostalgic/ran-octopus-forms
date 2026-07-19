<?php
/**
 * Profile field mapping and provider destination coverage.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

use RAN\EmailOctopusJetpackForms\EmailOctopusFieldMapper;
use RAN\EmailOctopusJetpackForms\EmailOctopusSubscriber;
use RAN\EmailOctopusJetpackForms\IntegrationResolver;
use RAN\EmailOctopusJetpackForms\Settings;

/**
 * Prove mappings and provider resolution remain profile-local.
 */
class RAN_EmailOctopus_Jetpack_Forms_Profile_Field_Mapping_Test extends WP_UnitTestCase {
	/**
	 * Immutable test profile UUID.
	 */
	const PROFILE_ID = '77777777-7777-4777-8777-777777777777';

	/**
	 * Reset provider and profile state.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		delete_option( Settings::OPTION_NAME );
		delete_option( 'emailoctopus_api_key' );
	}

	/**
	 * Every advertised transform produces its documented payload value.
	 *
	 * @return void
	 */
	public function test_every_supported_transform_is_applied() {
		$this->assertSame( Settings::TRANSFORMS, array_keys( EmailOctopusFieldMapper::get_transform_options() ) );

		$payload = EmailOctopusFieldMapper::build_fields_payload(
			array( '1_Full Name' => '  Ada Augusta Lovelace  ' ),
			array(
				'AsIs'      => array(
					'source'    => 'full_name',
					'transform' => 'as_is',
				),
				'First'     => array(
					'source'    => 'full_name',
					'transform' => 'first_word',
				),
				'Remaining' => array(
					'source'    => 'full_name',
					'transform' => 'remaining_words',
				),
				'Lower'     => array(
					'source'    => 'full_name',
					'transform' => 'lowercase',
				),
			)
		);

		$this->assertSame(
			array(
				'AsIs'      => 'Ada Augusta Lovelace',
				'First'     => 'Ada',
				'Remaining' => 'Augusta Lovelace',
				'Lower'     => 'ada augusta lovelace',
			),
			$payload
		);
	}

	/**
	 * A one-word value is intentionally omitted by remaining_words.
	 *
	 * @return void
	 */
	public function test_empty_transform_result_is_omitted() {
		$this->assertSame(
			array(),
			EmailOctopusFieldMapper::build_fields_payload(
				array( 'Name' => 'Ada' ),
				array(
					'LastName' => array(
						'source'    => 'name',
						'transform' => 'remaining_words',
					),
				)
			)
		);
	}

	/**
	 * A form destination resolves to its list before the contact request.
	 *
	 * @return void
	 */
	public function test_form_destination_resolves_to_list_via_provider() {
		$this->store_profile( 'form', 'provider-form' );
		update_option( 'emailoctopus_api_key', 'test-api-key' );
		$requests  = array();
		$http_mock = static function ( $preempt, $args, $url ) use ( &$requests ) {
			unset( $preempt );
			$requests[] = array(
				'method' => isset( $args['body'] ) ? 'POST' : 'GET',
				'url'    => $url,
			);

			if ( false !== strpos( $url, '/forms/provider-form' ) ) {
				return self::http_response( 200, array( 'list_id' => 'resolved-list' ) );
			}

			return self::http_response( 200, array( 'status' => 'SUBSCRIBED' ) );
		};
		add_filter( 'pre_http_request', $http_mock, 10, 3 );

		$result = ( new EmailOctopusSubscriber( IntegrationResolver::get_profile( self::PROFILE_ID ) ) )->subscribe( 'ada@example.com' );

		remove_filter( 'pre_http_request', $http_mock, 10 );
		$this->assertSame( array( 'outcome' => 'subscribed' ), $result );
		$this->assertCount( 2, $requests );
		$this->assertSame( 'GET', $requests[0]['method'] );
		$this->assertStringContainsString( '/forms/provider-form', $requests[0]['url'] );
		$this->assertSame( 'POST', $requests[1]['method'] );
		$this->assertStringContainsString( '/lists/resolved-list/contacts', $requests[1]['url'] );
	}

	/**
	 * A provider lookup failure never falls through to a contact request.
	 *
	 * @return void
	 */
	public function test_form_resolution_error_isolated_before_contact_request() {
		$this->store_profile( 'form', 'broken-form' );
		update_option( 'emailoctopus_api_key', 'test-api-key' );
		$request_count = 0;
		$http_mock     = static function () use ( &$request_count ) {
			++$request_count;
			return new WP_Error( 'provider_unavailable', 'Provider unavailable.' );
		};
		add_filter( 'pre_http_request', $http_mock );

		$result = ( new EmailOctopusSubscriber( IntegrationResolver::get_profile( self::PROFILE_ID ) ) )->subscribe( 'ada@example.com' );

		remove_filter( 'pre_http_request', $http_mock );
		$this->assertWPError( $result );
		$this->assertSame( 'ran_emailoctopus_jetpack_forms_emailoctopus_missing_credentials', $result->get_error_code() );
		$this->assertSame( 1, $request_count );
	}

	/**
	 * Store one canonical profile.
	 *
	 * @param string $destination_type Destination type.
	 * @param string $destination_id   Provider resource ID.
	 * @return void
	 */
	private function store_profile( $destination_type, $destination_id ) {
		$profile                = Settings::get_profile_defaults();
		$profile['revision']    = 1;
		$profile['label']       = 'Provider profile';
		$profile['destination'] = array(
			'type' => $destination_type,
			'id'   => $destination_id,
		);

		update_option(
			Settings::OPTION_NAME,
			array(
				'schema_version' => Settings::SCHEMA_VERSION,
				'revision'       => 1,
				'profiles'       => array( self::PROFILE_ID => $profile ),
			),
			false
		);
	}

	/**
	 * Build a WordPress HTTP response fixture.
	 *
	 * @param int                 $status Status code.
	 * @param array<string,mixed> $body   JSON response body.
	 * @return array<string,mixed>
	 */
	private static function http_response( $status, $body ) {
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( $body ),
			'response' => array(
				'code'    => $status,
				'message' => 200 === $status ? 'OK' : 'Error',
			),
			'cookies'  => array(),
		);
	}
}
