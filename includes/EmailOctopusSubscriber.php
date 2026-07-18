<?php
/**
 * EmailOctopus subscription client.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

namespace RAN\EmailOctopusJetpackForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Subscribes opted-in contact form submitters to EmailOctopus.
 */
final class EmailOctopusSubscriber {
	/**
	 * Subscribe an email address to the newsletter list.
	 *
	 * @param string $email_address Email address.
	 * @param array  $all_values    Submitted Jetpack values.
	 * @return array{outcome:string}|\WP_Error
	 */
	public function subscribe( $email_address, $all_values = array() ) {
		$email_address = sanitize_email( $email_address );

		if ( ! is_email( $email_address ) ) {
			return new \WP_Error( 'ran_octopus_forms_invalid_email', __( 'A valid email address is required.', 'ran-emailoctopus-jetpack-forms' ) );
		}

		$api_key = $this->get_api_key();
		$list_id = $this->get_list_id();

		if ( '' === $api_key || '' === $list_id ) {
			return new \WP_Error( 'ran_octopus_forms_emailoctopus_missing_credentials', __( 'EmailOctopus credentials are unavailable.', 'ran-emailoctopus-jetpack-forms' ) );
		}

		$body = array(
			'api_key'       => $api_key,
			'email_address' => $email_address,
		);

		$fields = EmailOctopusFieldMapper::build_fields_payload( is_array( $all_values ) ? $all_values : array() );

		if ( ! empty( $fields ) ) {
			$body['fields'] = $fields;
		}

		$response = wp_remote_post(
			sprintf( 'https://emailoctopus.com/api/1.6/lists/%s/contacts', rawurlencode( $list_id ) ),
			array(
				'headers' => array(
					'Content-Type' => 'application/json',
					'Accept'       => 'application/json',
				),
				'timeout' => 10,
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $status_code && is_array( $body ) ) {
			$status = strtoupper( sanitize_text_field( (string) ( $body['status'] ?? '' ) ) );

			if ( 'PENDING' === $status ) {
				return array( 'outcome' => 'pending' );
			}

			if ( 'SUBSCRIBED' === $status ) {
				return array( 'outcome' => 'subscribed' );
			}

			return new \WP_Error( 'ran_octopus_forms_emailoctopus_unknown_contact_status', __( 'EmailOctopus returned an unknown contact status.', 'ran-emailoctopus-jetpack-forms' ) );
		}

		if ( is_array( $body ) && 'MEMBER_EXISTS_WITH_EMAIL_ADDRESS' === ( $body['code'] ?? '' ) ) {
			return array( 'outcome' => 'existing' );
		}

		return new \WP_Error(
			'ran_octopus_forms_emailoctopus_bad_response',
			is_array( $body ) && ! empty( $body['message'] ) ? sanitize_text_field( (string) $body['message'] ) : __( 'EmailOctopus returned an unexpected response.', 'ran-emailoctopus-jetpack-forms' ),
			array(
				'status' => $status_code,
				'code'   => is_array( $body ) ? ( $body['code'] ?? '' ) : '',
			)
		);
	}

	/**
	 * Get the EmailOctopus API key from the installed EmailOctopus plugin.
	 *
	 * @return string
	 */
	private function get_api_key() {
		return EmailOctopusApi::get_api_key();
	}

	/**
	 * Resolve the EmailOctopus list ID.
	 *
	 * @return string
	 */
	private function get_list_id() {
		$list_id = Settings::get_emailoctopus_list_id();

		if ( '' !== $list_id ) {
			return $list_id;
		}

		$form_id = Settings::get_emailoctopus_form_id();

		if ( '' === $form_id ) {
			return '';
		}

		$form = EmailOctopusApi::get_form( $form_id );

		if ( is_wp_error( $form ) || ! is_array( $form ) ) {
			return '';
		}

		return sanitize_text_field( (string) ( $form['list_id'] ?? '' ) );
	}
}
