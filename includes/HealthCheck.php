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
			self::check_integration_target(),
			self::check_success_page(),
			self::check_pattern(),
			self::check_contact_form(),
			self::check_emailoctopus()
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
	 * Check the configured success page.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function check_success_page() {
		$success_id = absint( Settings::get( 'success_page_id' ) );

		return array(
			self::post_status_check( __( 'Success page', 'ran-emailoctopus-jetpack-forms' ), $success_id ),
		);
	}

	/**
	 * Check the capability and selected saved-form target behind the active mode.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function check_integration_target() {
		$reason  = IntegrationResolver::get_portability_reason();
		$form_id = IntegrationResolver::get_target_form_id();

		if ( IntegrationResolver::is_portable_available() ) {
			$form  = get_post( $form_id );
			$title = $form instanceof \WP_Post && '' !== trim( (string) $form->post_title ) ? $form->post_title : __( 'Untitled saved form', 'ran-emailoctopus-jetpack-forms' );

			return array(
				self::row( 'pass', __( 'Saved-form routing', 'ran-emailoctopus-jetpack-forms' ), __( 'Saved-form routing is active. One integration profile follows the selected form across routes.', 'ran-emailoctopus-jetpack-forms' ) ),
				self::row(
					'pass',
					__( 'Saved Jetpack form', 'ran-emailoctopus-jetpack-forms' ),
					sprintf(
						/* translators: 1: saved Jetpack form title, 2: post ID. */
						__( 'Published saved form "%1$s" (#%2$d) is the EmailOctopus integration target.', 'ran-emailoctopus-jetpack-forms' ),
						$title,
						$form_id
					)
				),
			);
		}

		if ( 'feedback_identity_unavailable' === $reason ) {
			return array(
				self::row( 'error', __( 'Saved-form routing', 'ran-emailoctopus-jetpack-forms' ), __( 'EmailOctopus routing is disabled because this Jetpack version cannot authoritatively identify a saved form after submission. Update Jetpack to a version that supports saved-form identity.', 'ran-emailoctopus-jetpack-forms' ) ),
			);
		}

		if ( 'target_not_selected' === $reason ) {
			return array(
				self::row( 'error', __( 'Saved Jetpack form', 'ran-emailoctopus-jetpack-forms' ), __( 'No saved form is selected. Select a published saved Jetpack form; EmailOctopus routing remains disabled until the target is valid.', 'ran-emailoctopus-jetpack-forms' ) ),
			);
		}

		return array(
			self::row( 'error', __( 'Saved Jetpack form', 'ran-emailoctopus-jetpack-forms' ), self::get_invalid_target_message( $reason, $form_id ) ),
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
	 * Check the selected saved Jetpack form.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function check_contact_form() {
		if ( ! IntegrationResolver::is_portable_available() ) {
			return array(
				self::row( 'skipped', __( 'Saved form field mapping', 'ran-emailoctopus-jetpack-forms' ), __( 'Field mapping cannot be checked until saved-form routing is available and the selected target is valid. Jetpack notifications remain independent; EmailOctopus routing is disabled.', 'ran-emailoctopus-jetpack-forms' ) ),
			);
		}

		$source_fields = EmailOctopusFieldMapper::get_source_fields();

		return array(
			self::status( ! empty( $source_fields ) ? 'pass' : 'error', __( 'Saved form fields', 'ran-emailoctopus-jetpack-forms' ), __( 'Field discovery is reading the selected saved Jetpack form.', 'ran-emailoctopus-jetpack-forms' ), __( 'No Jetpack fields were detected in the selected saved form. Edit the saved form and confirm its field blocks are inside the single contact form.', 'ran-emailoctopus-jetpack-forms' ) ),
			self::status( ! empty( EmailOctopusFieldMapper::get_email_source_fields() ) ? 'pass' : 'error', __( 'Email field', 'ran-emailoctopus-jetpack-forms' ), __( 'Selected saved form includes a usable email field.', 'ran-emailoctopus-jetpack-forms' ), __( 'Selected saved form is missing an unambiguous email field.', 'ran-emailoctopus-jetpack-forms' ) ),
			self::check_email_source_mapping(),
			self::check_newsletter_source_mapping(),
			self::status( has_filter( 'grunion_contact_form_redirect_url', array( JetpackForms::class, 'redirect_contact_form' ) ) ? 'pass' : 'error', __( 'Redirect hook', 'ran-emailoctopus-jetpack-forms' ), __( 'Saved-form redirect hook is registered.', 'ran-emailoctopus-jetpack-forms' ), __( 'Saved-form redirect hook is missing.', 'ran-emailoctopus-jetpack-forms' ) ),
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

		/* translators: %s: WordPress page status. */
		return self::row( 'publish' === $post->post_status ? 'pass' : 'error', $label, sprintf( __( 'Page status: %s.', 'ran-emailoctopus-jetpack-forms' ), $post->post_status ) );
	}

	/**
	 * Explain how to repair an invalid selected saved-form target.
	 *
	 * @param string $reason  Resolver reason code.
	 * @param int    $form_id Selected form ID.
	 * @return string
	 */
	private static function get_invalid_target_message( $reason, $form_id ) {
		switch ( $reason ) {
			case 'target_missing':
				/* translators: %d: missing saved Jetpack form ID. */
				return sprintf( __( 'Saved form #%d was deleted or is unavailable. Select a replacement published Jetpack form. EmailOctopus routing is disabled; Jetpack notifications remain independent.', 'ran-emailoctopus-jetpack-forms' ), $form_id );
			case 'target_wrong_type':
				/* translators: %d: selected WordPress post ID. */
				return sprintf( __( 'Target #%d is not a jetpack_form post. Select a published saved Jetpack form. EmailOctopus routing is disabled.', 'ran-emailoctopus-jetpack-forms' ), $form_id );
			case 'target_not_published':
				$post   = get_post( $form_id );
				$status = $post instanceof \WP_Post ? $post->post_status : __( 'unknown', 'ran-emailoctopus-jetpack-forms' );

				/* translators: 1: selected saved Jetpack form ID, 2: WordPress post status. */
				return sprintf( __( 'Saved form #%1$d has status "%2$s". Publish it or select another published form. EmailOctopus routing is disabled.', 'ran-emailoctopus-jetpack-forms' ), $form_id, $status );
			case 'target_invalid_structure':
				/* translators: %d: selected saved Jetpack form ID. */
				return sprintf( __( 'Saved form #%d does not contain exactly one Jetpack contact form. Repair its structure or select another saved form. EmailOctopus routing is disabled.', 'ran-emailoctopus-jetpack-forms' ), $form_id );
			default:
				return __( 'The selected saved Jetpack form is unavailable for EmailOctopus routing. Select a published saved form and rerun the health check.', 'ran-emailoctopus-jetpack-forms' );
		}
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

		return self::row( 'error', __( 'Email source mapping', 'ran-emailoctopus-jetpack-forms' ), __( 'Configured email source was not detected on the active integration form.', 'ran-emailoctopus-jetpack-forms' ) );
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

		return self::row( 'error', __( 'Newsletter opt-in source', 'ran-emailoctopus-jetpack-forms' ), __( 'Configured newsletter source was not detected on the active integration form.', 'ran-emailoctopus-jetpack-forms' ) );
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
					__( '%1$d custom field(s) are mapped, but %2$d mapping(s) point to Jetpack fields that were not detected on the active integration form: %3$s.', 'ran-emailoctopus-jetpack-forms' ),
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
