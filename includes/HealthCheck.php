<?php
/**
 * RAN EmailOctopus for Jetpack Forms health checks.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

namespace RAN\EmailOctopusJetpackForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs safe diagnostics for the RAN EmailOctopus for Jetpack Forms integration.
 */
final class HealthCheck {
	/**
	 * Run all health checks.
	 *
	 * @return array<string,mixed>
	 */
	public static function run() {
		$checks = array_merge(
			self::check_plugins(),
			self::check_pages(),
			self::check_pattern(),
			self::check_contact_form(),
			self::check_emailoctopus(),
			self::check_frontend_render()
		);

		$overall = 'pass';

		foreach ( $checks as $check ) {
			if ( 'error' === $check['status'] ) {
				$overall = 'error';
				break;
			}

			if ( 'warning' === $check['status'] ) {
				$overall = 'warning';
			}
		}

		return array(
			'overall' => $overall,
			'checks'  => $checks,
		);
	}

	/**
	 * Check required plugins.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function check_plugins() {
		return array(
			self::status( class_exists( '\Automattic\Jetpack\Forms\ContactForm\Contact_Form' ) ? 'pass' : 'error', __( 'Jetpack Forms', 'ran-emailoctopus-jetpack-forms' ), __( 'Jetpack Forms class is available.', 'ran-emailoctopus-jetpack-forms' ), __( 'Jetpack Forms is not available.', 'ran-emailoctopus-jetpack-forms' ) ),
			self::status( '' !== EmailOctopusApi::get_api_key() ? 'pass' : 'error', __( 'EmailOctopus API key', 'ran-emailoctopus-jetpack-forms' ), __( 'EmailOctopus API key is available.', 'ran-emailoctopus-jetpack-forms' ), __( 'EmailOctopus API key is missing.', 'ran-emailoctopus-jetpack-forms' ) ),
			self::status( class_exists( __CLASS__ ) ? 'pass' : 'error', __( 'RAN EmailOctopus for Jetpack Forms', 'ran-emailoctopus-jetpack-forms' ), __( 'RAN EmailOctopus for Jetpack Forms is loaded.', 'ran-emailoctopus-jetpack-forms' ), __( 'RAN EmailOctopus for Jetpack Forms is not loaded.', 'ran-emailoctopus-jetpack-forms' ) ),
		);
	}

	/**
	 * Check configured pages.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function check_pages() {
		$contact_id = Settings::get_contact_page_id();
		$success_id = absint( Settings::get( 'success_page_id' ) );

		return array(
			self::post_status_check( __( 'Contact page', 'ran-emailoctopus-jetpack-forms' ), $contact_id ),
			self::post_status_check( __( 'Success page', 'ran-emailoctopus-jetpack-forms' ), $success_id ),
		);
	}

	/**
	 * Check block pattern registration/content.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function check_pattern() {
		$registered = \WP_Block_Patterns_Registry::get_instance()->is_registered( Patterns::CONTACT_FORM_PATTERN );
		$content    = Patterns::get_contact_form_content();

		$has_email_field      = false !== strpos( $content, 'jetpack/field-email' );
		$has_newsletter_field = false !== strpos( $content, 'jetpack/field-checkbox' ) || false !== strpos( $content, 'jetpack/field-consent' );
		$has_fields           = $has_email_field && $has_newsletter_field;

		return array(
			self::status( $registered ? 'pass' : 'error', __( 'Contact form pattern', 'ran-emailoctopus-jetpack-forms' ), __( 'Pattern is registered.', 'ran-emailoctopus-jetpack-forms' ), __( 'Pattern is not registered.', 'ran-emailoctopus-jetpack-forms' ) ),
			self::status( $has_fields ? 'pass' : 'error', __( 'Pattern fields', 'ran-emailoctopus-jetpack-forms' ), __( 'Pattern includes email and newsletter fields.', 'ran-emailoctopus-jetpack-forms' ), __( 'Pattern is missing an email field or checkbox/consent field.', 'ran-emailoctopus-jetpack-forms' ) ),
		);
	}

	/**
	 * Check configured contact page form.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function check_contact_form() {
		$content = self::get_contact_form_content();
		$count   = Settings::get_contact_form_count();
		/* translators: %d: number of Jetpack contact forms found. */
		$count_error = sprintf( __( 'Contact page contains %d Jetpack contact forms. RAN EmailOctopus for Jetpack Forms requires exactly one form so submissions cannot be attributed to the wrong integration.', 'ran-emailoctopus-jetpack-forms' ), $count );

		return array(
			self::status( 1 === $count ? 'pass' : 'error', __( 'Contact page form count', 'ran-emailoctopus-jetpack-forms' ), __( 'Contact page contains one Jetpack contact form.', 'ran-emailoctopus-jetpack-forms' ), $count_error ),
			self::status( Settings::has_target_contact_form() ? 'pass' : 'error', __( 'RAN form marker', 'ran-emailoctopus-jetpack-forms' ), __( 'The Jetpack form is marked for RAN EmailOctopus for Jetpack Forms.', 'ran-emailoctopus-jetpack-forms' ), __( 'The intended form is not marked for RAN EmailOctopus for Jetpack Forms. Reinsert the Contact Newsletter Form pattern or add its RAN class to the one intended Jetpack form.', 'ran-emailoctopus-jetpack-forms' ) ),
			self::status( false !== strpos( $content, 'jetpack/field-email' ) ? 'pass' : 'error', __( 'Email field', 'ran-emailoctopus-jetpack-forms' ), __( 'Contact form includes an email field.', 'ran-emailoctopus-jetpack-forms' ), __( 'Contact form is missing an email field.', 'ran-emailoctopus-jetpack-forms' ) ),
			self::check_email_source_mapping(),
			self::check_newsletter_source_mapping(),
			self::status( has_filter( 'grunion_contact_form_redirect_url', array( JetpackForms::class, 'redirect_contact_form' ) ) ? 'pass' : 'error', __( 'Redirect hook', 'ran-emailoctopus-jetpack-forms' ), __( 'Contact form redirect hook is registered.', 'ran-emailoctopus-jetpack-forms' ), __( 'Contact form redirect hook is missing.', 'ran-emailoctopus-jetpack-forms' ) ),
		);
	}

	/**
	 * Check EmailOctopus configuration with read-only calls.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function check_emailoctopus() {
		$checks  = array();
		$api_key = EmailOctopusApi::get_api_key();
		$form_id = Settings::get_emailoctopus_form_id();
		$list_id = Settings::get_emailoctopus_list_id();

		$checks[] = self::status( is_string( $api_key ) && '' !== $api_key ? 'pass' : 'error', __( 'EmailOctopus API key', 'ran-emailoctopus-jetpack-forms' ), __( 'API key is present.', 'ran-emailoctopus-jetpack-forms' ), __( 'API key is missing.', 'ran-emailoctopus-jetpack-forms' ) );

		if ( ! is_string( $api_key ) || '' === $api_key ) {
			$checks[] = self::row( 'error', __( 'EmailOctopus form/list', 'ran-emailoctopus-jetpack-forms' ), __( 'Cannot check form or list without an API key.', 'ran-emailoctopus-jetpack-forms' ) );
			return $checks;
		}

		if ( '' !== $list_id ) {
			$checks[] = self::row( 'skipped', __( 'EmailOctopus form', 'ran-emailoctopus-jetpack-forms' ), __( 'Direct list override is selected.', 'ran-emailoctopus-jetpack-forms' ) );
		} elseif ( '' !== $form_id ) {
			$form = EmailOctopusApi::get_form( $form_id );

			if ( is_wp_error( $form ) ) {
				$checks[] = self::row( 'error', __( 'EmailOctopus form', 'ran-emailoctopus-jetpack-forms' ), $form->get_error_message() );
				return $checks;
			}

			$checks[] = self::row( 'pass', __( 'EmailOctopus form', 'ran-emailoctopus-jetpack-forms' ), __( 'Selected form resolves through the API.', 'ran-emailoctopus-jetpack-forms' ) );

			if ( is_array( $form ) ) {
				$list_id = (string) ( $form['list_id'] ?? '' );
			}
		} else {
			$checks[] = self::row( 'error', __( 'EmailOctopus form/list', 'ran-emailoctopus-jetpack-forms' ), __( 'Select an EmailOctopus form or direct list override.', 'ran-emailoctopus-jetpack-forms' ) );
		}

		if ( '' === $list_id ) {
			$checks[] = self::row( 'error', __( 'EmailOctopus list', 'ran-emailoctopus-jetpack-forms' ), __( 'Could not resolve a list ID.', 'ran-emailoctopus-jetpack-forms' ) );
			return $checks;
		}

		$list = EmailOctopusApi::get_list( $list_id );

		$checks[] = self::row( is_wp_error( $list ) ? 'error' : 'pass', __( 'EmailOctopus list', 'ran-emailoctopus-jetpack-forms' ), is_wp_error( $list ) ? $list->get_error_message() : __( 'Configured list resolves through the API.', 'ran-emailoctopus-jetpack-forms' ) );

		if ( ! is_wp_error( $list ) && is_array( $list ) ) {
			$checks[] = self::check_emailoctopus_list_fields( $list );
		}

		return $checks;
	}

	/**
	 * Check rendered frontend safely.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function check_frontend_render() {
		$url = get_permalink( Settings::get_contact_page_id() );

		if ( ! $url ) {
			return array( self::row( 'error', __( 'Frontend render', 'ran-emailoctopus-jetpack-forms' ), __( 'Contact page URL is unavailable.', 'ran-emailoctopus-jetpack-forms' ) ) );
		}

		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return array( self::row( 'error', __( 'Frontend render', 'ran-emailoctopus-jetpack-forms' ), $response->get_error_message() ) );
		}

		$body = wp_remote_retrieve_body( $response );

		return array(
			self::status( 200 === wp_remote_retrieve_response_code( $response ) ? 'pass' : 'error', __( 'Contact page response', 'ran-emailoctopus-jetpack-forms' ), __( 'Contact page returns HTTP 200.', 'ran-emailoctopus-jetpack-forms' ), __( 'Contact page did not return HTTP 200.', 'ran-emailoctopus-jetpack-forms' ) ),
			self::status( false !== strpos( $body, 'jetpack-contact-form' ) ? 'pass' : 'error', __( 'Rendered Jetpack form', 'ran-emailoctopus-jetpack-forms' ), __( 'Rendered page includes the Jetpack form.', 'ran-emailoctopus-jetpack-forms' ), __( 'Rendered page is missing the Jetpack form.', 'ran-emailoctopus-jetpack-forms' ) ),
			self::status( false === strpos( $body, 'emailoctopus' ) ? 'pass' : 'error', __( 'EmailOctopus embed removed', 'ran-emailoctopus-jetpack-forms' ), __( 'Rendered contact page does not include an EmailOctopus embed.', 'ran-emailoctopus-jetpack-forms' ), __( 'Rendered contact page still includes an EmailOctopus embed.', 'ran-emailoctopus-jetpack-forms' ) ),
		);
	}

	/**
	 * Build a status row from condition.
	 *
	 * @param string $status Status when condition has already been decided.
	 * @param string $label Label.
	 * @param string $pass_message Pass message.
	 * @param string $fail_message Fail message.
	 * @return array<string,string>
	 */
	private static function status( $status, $label, $pass_message, $fail_message ) {
		return self::row( 'pass' === $status ? 'pass' : 'error', $label, 'pass' === $status ? $pass_message : $fail_message );
	}

	/**
	 * Build a status row.
	 *
	 * @param string $status Status.
	 * @param string $label Label.
	 * @param string $message Message.
	 * @return array<string,string>
	 */
	private static function row( $status, $label, $message ) {
		return array(
			'status'  => $status,
			'label'   => $label,
			'message' => $message,
		);
	}

	/**
	 * Check a post exists and is published.
	 *
	 * @param string $label Label.
	 * @param int    $post_id Post ID.
	 * @return array<string,string>
	 */
	private static function post_status_check( $label, $post_id ) {
		$post = 0 < $post_id ? get_post( $post_id ) : null;

		if ( ! $post instanceof \WP_Post ) {
			return self::row( 'error', $label, __( 'Page does not exist.', 'ran-emailoctopus-jetpack-forms' ) );
		}

		/* translators: %s: WordPress post status. */
		return self::row( 'publish' === $post->post_status ? 'pass' : 'error', $label, sprintf( __( 'Page status: %s.', 'ran-emailoctopus-jetpack-forms' ), $post->post_status ) );
	}

	/**
	 * Get configured contact form content.
	 *
	 * @return string
	 */
	private static function get_contact_form_content() {
		$page_content = get_post_field( 'post_content', Settings::get_contact_page_id() );
		$block        = self::find_contact_form_block( parse_blocks( is_string( $page_content ) ? $page_content : '' ) );

		if ( ! is_array( $block ) ) {
			return '';
		}

		$ref = absint( $block['attrs']['ref'] ?? 0 );

		if ( 0 < $ref ) {
			return (string) get_post_field( 'post_content', $ref );
		}

		return serialize_block( $block );
	}

	/**
	 * Find the first Jetpack contact form block.
	 *
	 * @param array<int,array<string,mixed>> $blocks Blocks.
	 * @return array<string,mixed>|null
	 */
	private static function find_contact_form_block( $blocks ) {
		foreach ( $blocks as $block ) {
			if ( 'jetpack/contact-form' === ( $block['blockName'] ?? '' ) ) {
				return $block;
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$inner = self::find_contact_form_block( $block['innerBlocks'] );

				if ( is_array( $inner ) ) {
					return $inner;
				}
			}
		}

		return null;
	}

	/**
	 * Check the configured EmailOctopus email source.
	 *
	 * @return array<string,string>
	 */
	private static function check_email_source_mapping() {
		$email_source = Settings::get_emailoctopus_email_source();

		if ( '' === $email_source ) {
			return self::row( 'error', __( 'Email source mapping', 'ran-emailoctopus-jetpack-forms' ), __( 'No email source is configured. Select a current email field in RAN EmailOctopus for Jetpack Forms settings.', 'ran-emailoctopus-jetpack-forms' ) );
		}

		foreach ( EmailOctopusFieldMapper::get_source_fields() as $source_field ) {
			if ( ( $source_field['key'] ?? '' ) !== $email_source ) {
				continue;
			}

			$looks_like_email = EmailOctopusFieldMapper::is_supported_email_source_field( $source_field );

			if ( ! $looks_like_email ) {
				/* translators: %s: selected Jetpack contact-form field label. */
				return self::row( 'error', __( 'Email source mapping', 'ran-emailoctopus-jetpack-forms' ), sprintf( __( 'Configured email source "%s" is not an email field.', 'ran-emailoctopus-jetpack-forms' ), $source_field['label'] ?? $email_source ) );
			}

			/* translators: %s: selected Jetpack contact-form field label. */
			return self::row( 'pass', __( 'Email source mapping', 'ran-emailoctopus-jetpack-forms' ), sprintf( __( 'Email source maps to "%s".', 'ran-emailoctopus-jetpack-forms' ), $source_field['label'] ?? $email_source ) );
		}

		return self::row( 'error', __( 'Email source mapping', 'ran-emailoctopus-jetpack-forms' ), __( 'Configured email source was not detected on the contact form.', 'ran-emailoctopus-jetpack-forms' ) );
	}

	/**
	 * Check the configured newsletter opt-in source.
	 *
	 * @return array<string,string>
	 */
	private static function check_newsletter_source_mapping() {
		$newsletter_source = Settings::get_newsletter_source();

		if ( '' === $newsletter_source ) {
			return self::row( 'error', __( 'Newsletter opt-in source', 'ran-emailoctopus-jetpack-forms' ), __( 'No newsletter opt-in source is configured. Select a current checkbox or consent field in RAN EmailOctopus for Jetpack Forms settings.', 'ran-emailoctopus-jetpack-forms' ) );
		}

		foreach ( EmailOctopusFieldMapper::get_source_fields() as $source_field ) {
			if ( ( $source_field['key'] ?? '' ) !== $newsletter_source ) {
				continue;
			}

			if ( ! EmailOctopusFieldMapper::is_supported_newsletter_source_field( $source_field ) ) {
				/* translators: %s: selected Jetpack contact-form field label. */
				return self::row( 'error', __( 'Newsletter opt-in source', 'ran-emailoctopus-jetpack-forms' ), sprintf( __( 'Configured newsletter source "%s" is not a checkbox or consent field.', 'ran-emailoctopus-jetpack-forms' ), $source_field['label'] ?? $newsletter_source ) );
			}

			/* translators: %s: selected Jetpack contact-form field label. */
			return self::row( 'pass', __( 'Newsletter opt-in source', 'ran-emailoctopus-jetpack-forms' ), sprintf( __( 'Newsletter opt-in maps to "%s".', 'ran-emailoctopus-jetpack-forms' ), $source_field['label'] ?? $newsletter_source ) );
		}

		return self::row( 'error', __( 'Newsletter opt-in source', 'ran-emailoctopus-jetpack-forms' ), __( 'Configured newsletter source was not detected on the contact form.', 'ran-emailoctopus-jetpack-forms' ) );
	}

	/**
	 * Check whether the selected EmailOctopus list expects unmapped fields.
	 *
	 * @param array<string,mixed> $emailoctopus_list EmailOctopus list response.
	 * @return array<string,string>
	 */
	private static function check_emailoctopus_list_fields( $emailoctopus_list ) {
		$custom_fields = EmailOctopusFieldMapper::get_custom_fields( $emailoctopus_list );
		$field_map     = Settings::get_emailoctopus_field_map();
		$source_fields = EmailOctopusFieldMapper::get_source_fields();
		$source_keys   = wp_list_pluck( $source_fields, 'key' );
		$mapped_count  = 0;
		$unmapped      = array();
		$invalid       = array();

		foreach ( $custom_fields as $field ) {
			$tag   = (string) ( $field['tag'] ?? '' );
			$label = '' !== ( $field['label'] ?? '' ) ? sprintf( '%s (%s)', $field['label'], $tag ) : $tag;

			if ( empty( $field_map[ $tag ]['source'] ) ) {
				$unmapped[] = $label;
				continue;
			}

			if ( ! in_array( $field_map[ $tag ]['source'], $source_keys, true ) ) {
				$invalid[] = $label;
				continue;
			}

			++$mapped_count;
		}

		if ( empty( $custom_fields ) ) {
			return self::row( 'pass', __( 'EmailOctopus field mapping', 'ran-emailoctopus-jetpack-forms' ), __( 'Selected list does not expose custom fields beyond the email address field that RAN EmailOctopus for Jetpack Forms submits.', 'ran-emailoctopus-jetpack-forms' ) );
		}

		if ( empty( $unmapped ) && empty( $invalid ) ) {
			/* translators: %d: number of mapped EmailOctopus custom fields. */
			return self::row( 'pass', __( 'EmailOctopus field mapping', 'ran-emailoctopus-jetpack-forms' ), sprintf( __( 'All %d EmailOctopus custom field(s) are mapped to detected Jetpack fields.', 'ran-emailoctopus-jetpack-forms' ), $mapped_count ) );
		}

		if ( ! empty( $invalid ) ) {
			return self::row(
				'warning',
				__( 'EmailOctopus field mapping', 'ran-emailoctopus-jetpack-forms' ),
				sprintf(
					/* translators: 1: number of mapped fields, 2: number of invalid fields, 3: comma-separated example field names. */
					__( '%1$d custom field(s) are mapped, but %2$d mapping(s) point to Jetpack fields that were not detected on the configured contact form: %3$s.', 'ran-emailoctopus-jetpack-forms' ),
					$mapped_count,
					count( $invalid ),
					implode( ', ', array_slice( $invalid, 0, 5 ) )
				)
			);
		}

		return self::row(
			'warning',
			__( 'EmailOctopus field mapping', 'ran-emailoctopus-jetpack-forms' ),
			sprintf(
				/* translators: 1: number of mapped fields, 2: number of custom fields, 3: comma-separated example field names. */
				__( '%1$d of %2$d EmailOctopus custom field(s) are mapped. Only mapped fields and email_address are submitted; unmapped fields include: %3$s.', 'ran-emailoctopus-jetpack-forms' ),
				$mapped_count,
				count( $custom_fields ),
				implode( ', ', array_slice( $unmapped, 0, 5 ) )
			)
		);
	}
}
