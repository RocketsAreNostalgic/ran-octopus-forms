<?php
/**
 * EmailOctopus API helpers.
 *
 * @package RAN_Octopus_Forms
 */

namespace RAN\OctopusForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only helpers for EmailOctopus account data.
 */
final class EmailOctopusApi {
	/**
	 * EmailOctopus API base URL.
	 */
	const API_BASE = 'https://emailoctopus.com/api/1.6';

	/**
	 * Get the EmailOctopus API key stored by the official plugin.
	 *
	 * @return string
	 */
	public static function get_api_key() {
		$api_key = get_option( 'emailoctopus_api_key', '' );

		return is_string( $api_key ) ? $api_key : '';
	}

	/**
	 * Get available EmailOctopus forms.
	 *
	 * @return array<int,array<string,string>>|\WP_Error
	 */
	public static function get_forms() {
		$api_key = self::get_api_key();

		if ( '' === $api_key ) {
			return new \WP_Error( 'ran_octopus_forms_emailoctopus_missing_api_key', __( 'EmailOctopus API key is missing.', 'ran-octopus-forms' ) );
		}

		$forms = self::api_get( '/forms', array( 'api_key' => $api_key ) );

		if ( is_wp_error( $forms ) ) {
			return $forms;
		}

		if ( ! is_array( $forms ) ) {
			return new \WP_Error( 'ran_octopus_forms_emailoctopus_bad_forms', __( 'EmailOctopus returned an unexpected forms response.', 'ran-octopus-forms' ) );
		}

		$normalized = array();

		foreach ( $forms as $form ) {
			if ( ! is_array( $form ) ) {
				continue;
			}

			$list_id   = sanitize_text_field( (string) ( $form['list_id'] ?? '' ) );
			$list_name = sanitize_text_field( (string) ( $form['list_name'] ?? '' ) );

			if ( '' === $list_name && '' !== $list_id ) {
				$list = self::get_list( $list_id );

				if ( is_array( $list ) ) {
					$list_name = sanitize_text_field( (string) ( $list['name'] ?? '' ) );
				}
			}

			$normalized[] = array(
				'id'        => sanitize_text_field( (string) ( $form['id'] ?? '' ) ),
				'name'      => sanitize_text_field( (string) ( $form['name'] ?? '' ) ),
				'type'      => sanitize_text_field( (string) ( $form['type'] ?? '' ) ),
				'list_id'   => $list_id,
				'list_name' => $list_name,
			);
		}

		return array_values(
			array_filter(
				$normalized,
				static function ( $form ) {
					return is_array( $form ) && '' !== ( $form['id'] ?? '' );
				}
			)
		);
	}

	/**
	 * Get one EmailOctopus form.
	 *
	 * @param string $form_id EmailOctopus form ID.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function get_form( $form_id ) {
		$form_id = sanitize_text_field( $form_id );

		if ( '' === $form_id ) {
			return new \WP_Error( 'ran_octopus_forms_emailoctopus_missing_form_id', __( 'EmailOctopus form ID is missing.', 'ran-octopus-forms' ) );
		}

		return self::api_get(
			sprintf( '/forms/%s', rawurlencode( $form_id ) ),
			array( 'api_key' => self::get_api_key() )
		);
	}

	/**
	 * Get one EmailOctopus list.
	 *
	 * @param string $list_id EmailOctopus list ID.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function get_list( $list_id ) {
		$list_id = sanitize_text_field( $list_id );

		if ( '' === $list_id ) {
			return new \WP_Error( 'ran_octopus_forms_emailoctopus_missing_list_id', __( 'EmailOctopus list ID is missing.', 'ran-octopus-forms' ) );
		}

		return self::api_get(
			sprintf( '/lists/%s', rawurlencode( $list_id ) ),
			array( 'api_key' => self::get_api_key() )
		);
	}

	/**
	 * Run an EmailOctopus API GET request.
	 *
	 * @param string              $path  API path.
	 * @param array<string,mixed> $query Query args.
	 * @return array<mixed>|\WP_Error
	 */
	private static function api_get( $path, $query ) {
		if ( empty( $query['api_key'] ) ) {
			return new \WP_Error( 'ran_octopus_forms_emailoctopus_missing_api_key', __( 'EmailOctopus API key is missing.', 'ran-octopus-forms' ) );
		}

		$url       = add_query_arg( $query, self::API_BASE . $path );
		$cache_key = 'ran_octopus_forms_emailoctopus_' . md5( $url );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return self::normalize_api_response( $cached );
		}

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Accept' => 'application/json',
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error( 'ran_octopus_forms_emailoctopus_bad_status', __( 'EmailOctopus API returned an unexpected status.', 'ran-octopus-forms' ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) ) {
			return new \WP_Error( 'ran_octopus_forms_emailoctopus_bad_json', __( 'EmailOctopus API returned invalid JSON.', 'ran-octopus-forms' ) );
		}

		if ( isset( $body['error'] ) ) {
			return new \WP_Error( 'ran_octopus_forms_emailoctopus_api_error', sanitize_text_field( (string) $body['error'] ) );
		}

		$data = self::normalize_api_response( $body );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		set_transient( $cache_key, $data, MINUTE_IN_SECONDS );

		return $data;
	}

	/**
	 * Normalize EmailOctopus API responses.
	 *
	 * Collection responses are wrapped in a `data` key, while single-resource
	 * responses are returned as the resource object.
	 *
	 * @param array<mixed> $body Decoded response body.
	 * @return array<mixed>|\WP_Error
	 */
	private static function normalize_api_response( $body ) {
		$data = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : $body;

		if ( isset( $data['error'] ) ) {
			return new \WP_Error( 'ran_octopus_forms_emailoctopus_api_error', sanitize_text_field( (string) $data['error'] ) );
		}

		return $data;
	}
}
