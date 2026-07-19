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
		$form_ids = IntegrationResolver::get_target_form_ids();

		if ( ! IntegrationResolver::supports_portable_forms() ) {
			return array(
				self::row( 'error', __( 'Saved-form routing', 'ran-emailoctopus-jetpack-forms' ), __( 'EmailOctopus routing is disabled because this Jetpack version cannot authoritatively identify a saved form after submission. Update Jetpack to a version that supports saved-form identity.', 'ran-emailoctopus-jetpack-forms' ) ),
			);
		}

		if ( empty( $form_ids ) ) {
			return array(
				self::row( 'error', __( 'Selected saved forms', 'ran-emailoctopus-jetpack-forms' ), __( 'No saved forms are selected. Select at least one published saved Jetpack form; EmailOctopus routing remains disabled until a target is valid.', 'ran-emailoctopus-jetpack-forms' ) ),
			);
		}

		$routable_ids = self::get_routable_form_ids( $form_ids );
		$has_peer     = ! empty( $routable_ids );
		$checks       = array(
			self::row(
				'pass',
				__( 'Selected saved forms', 'ran-emailoctopus-jetpack-forms' ),
				sprintf(
					/* translators: %d: number of selected saved Jetpack forms. */
					_n( '%d saved form is selected.', '%d saved forms are selected.', count( $form_ids ), 'ran-emailoctopus-jetpack-forms' ),
					count( $form_ids )
				)
			),
		);

		$checks[] = self::row(
			count( $routable_ids ) === count( $form_ids ) ? 'pass' : ( $has_peer ? 'warning' : 'error' ),
			__( 'Saved-form routing', 'ran-emailoctopus-jetpack-forms' ),
			sprintf(
				/* translators: 1: number of routable forms, 2: number of selected forms. */
				__( '%1$d of %2$d selected saved form(s) can be routed. Invalid forms are isolated and do not stop valid peers.', 'ran-emailoctopus-jetpack-forms' ),
				count( $routable_ids ),
				count( $form_ids )
			)
		);

		foreach ( $form_ids as $form_id ) {
			$reason = IntegrationResolver::get_target_form_reason( $form_id );

			if ( '' === $reason ) {
				$form    = get_post( $form_id );
				$title   = $form instanceof \WP_Post && '' !== trim( (string) $form->post_title ) ? $form->post_title : __( 'Untitled saved form', 'ran-emailoctopus-jetpack-forms' );
				$message = sprintf(
					/* translators: 1: saved Jetpack form title, 2: post ID. */
					__( 'Published saved form "%1$s" (#%2$d) is eligible for route-independent handling.', 'ran-emailoctopus-jetpack-forms' ),
					$title,
					$form_id
				);
				$checks[] = self::row( 'pass', sprintf( /* translators: %d: saved Jetpack form ID. */ __( 'Saved Jetpack form #%d', 'ran-emailoctopus-jetpack-forms' ), $form_id ), $message );
				continue;
			}

			$checks[] = self::row(
				$has_peer ? 'warning' : 'error',
				sprintf( /* translators: %d: saved Jetpack form ID. */ __( 'Saved Jetpack form #%d', 'ran-emailoctopus-jetpack-forms' ), $form_id ),
				self::get_invalid_target_message( $reason, $form_id, $has_peer )
			);
		}

		return $checks;
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
		$form_ids     = IntegrationResolver::get_target_form_ids();
		$routable_ids = self::get_routable_form_ids( $form_ids );

		if ( empty( $routable_ids ) ) {
			return array(
				self::row( 'skipped', __( 'Saved form field mapping', 'ran-emailoctopus-jetpack-forms' ), __( 'Field mapping cannot be checked until at least one selected saved form is routable. Jetpack notifications remain independent; EmailOctopus routing is disabled.', 'ran-emailoctopus-jetpack-forms' ) ),
			);
		}

		$source_fields       = EmailOctopusFieldMapper::get_source_fields_for_saved_forms( $routable_ids );
		$email_source_fields = EmailOctopusFieldMapper::get_email_source_fields_for_saved_forms( $routable_ids );
		$compatibility       = EmailOctopusFieldMapper::get_subscription_compatibility( $routable_ids );
		$eligible_count      = count(
			array_filter(
				$compatibility,
				static function ( $result ) {
					return ! empty( $result['eligible'] );
				}
			)
		);
		$incompatible_status = 0 < $eligible_count ? 'warning' : 'error';
		$checks              = array(
			self::row( ! empty( $source_fields ) ? 'pass' : $incompatible_status, __( 'Shared saved-form fields', 'ran-emailoctopus-jetpack-forms' ), ! empty( $source_fields ) ? __( 'Field discovery found unambiguous fields with matching types across every routable selected form.', 'ran-emailoctopus-jetpack-forms' ) : __( 'No unambiguous Jetpack fields with matching types are shared by every routable selected form.', 'ran-emailoctopus-jetpack-forms' ) ),
			self::row( ! empty( $email_source_fields ) ? 'pass' : $incompatible_status, __( 'Shared email fields', 'ran-emailoctopus-jetpack-forms' ), ! empty( $email_source_fields ) ? __( 'Every routable selected form shares at least one compatible email field.', 'ran-emailoctopus-jetpack-forms' ) : __( 'The routable selected forms do not share an unambiguous email field with the same normalized key and type.', 'ran-emailoctopus-jetpack-forms' ) ),
			self::check_email_source_mapping(),
			self::check_newsletter_source_mapping(),
		);

		foreach ( $compatibility as $form_id => $result ) {
			if ( ! empty( $result['eligible'] ) ) {
				$checks[] = self::row( 'pass', sprintf( /* translators: %d: saved Jetpack form ID. */ __( 'Subscription mapping for form #%d', 'ran-emailoctopus-jetpack-forms' ), $form_id ), __( 'Every configured source is present, unambiguous, and type-compatible on this form.', 'ran-emailoctopus-jetpack-forms' ) );
				continue;
			}

			$checks[] = self::row(
				0 < $eligible_count ? 'warning' : 'error',
				sprintf( /* translators: %d: saved Jetpack form ID. */ __( 'Subscription mapping for form #%d', 'ran-emailoctopus-jetpack-forms' ), $form_id ),
				self::get_source_failure_message( $result )
			);
		}

		$checks[] = self::status( has_filter( 'grunion_contact_form_redirect_url', array( JetpackForms::class, 'redirect_contact_form' ) ) ? 'pass' : 'error', __( 'Redirect hook', 'ran-emailoctopus-jetpack-forms' ), __( 'Saved-form redirect hook is registered.', 'ran-emailoctopus-jetpack-forms' ), __( 'Saved-form redirect hook is missing.', 'ran-emailoctopus-jetpack-forms' ) );

		return $checks;
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
	 * Keep only selected forms that can receive route-independent handling.
	 *
	 * @param array<int,int> $form_ids Selected saved form IDs.
	 * @return array<int,int>
	 */
	private static function get_routable_form_ids( $form_ids ) {
		return array_values( array_filter( $form_ids, array( IntegrationResolver::class, 'is_routing_eligible_form_id' ) ) );
	}

	/**
	 * Explain how to repair an invalid selected saved-form target.
	 *
	 * @param string $reason   Resolver reason code.
	 * @param int    $form_id  Selected form ID.
	 * @param bool   $isolated Whether a valid selected peer remains active.
	 * @return string
	 */
	private static function get_invalid_target_message( $reason, $form_id, $isolated ) {
		$effect = $isolated
			? __( 'This form is isolated; valid selected peers remain active.', 'ran-emailoctopus-jetpack-forms' )
			: __( 'EmailOctopus routing is disabled because no valid selected peer remains.', 'ran-emailoctopus-jetpack-forms' );

		switch ( $reason ) {
			case 'target_missing':
				/* translators: %d: missing saved Jetpack form ID. */
				return sprintf( __( 'Saved form #%d was deleted or is unavailable. Clear it or restore the saved form. ', 'ran-emailoctopus-jetpack-forms' ), $form_id ) . $effect;
			case 'target_wrong_type':
				/* translators: %d: selected WordPress post ID. */
				return sprintf( __( 'Target #%d is not a jetpack_form post. Clear it or select a saved Jetpack form. ', 'ran-emailoctopus-jetpack-forms' ), $form_id ) . $effect;
			case 'target_not_published':
				$post   = get_post( $form_id );
				$status = $post instanceof \WP_Post ? $post->post_status : __( 'unknown', 'ran-emailoctopus-jetpack-forms' );

				/* translators: 1: selected saved Jetpack form ID, 2: WordPress post status. */
				return sprintf( __( 'Saved form #%1$d has status "%2$s". Publish it or clear the selection. ', 'ran-emailoctopus-jetpack-forms' ), $form_id, $status ) . $effect;
			case 'target_invalid_structure':
				/* translators: %d: selected saved Jetpack form ID. */
				return sprintf( __( 'Saved form #%d does not contain exactly one Jetpack contact form. Repair its structure or clear the selection. ', 'ran-emailoctopus-jetpack-forms' ), $form_id ) . $effect;
			default:
				return __( 'The selected saved Jetpack form is unavailable for EmailOctopus routing. ', 'ran-emailoctopus-jetpack-forms' ) . $effect;
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

		return self::check_configured_source_across_forms( 'email', $email_source, __( 'Email source mapping', 'ran-emailoctopus-jetpack-forms' ) );
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

		return self::check_configured_source_across_forms( 'newsletter', $newsletter_source, __( 'Newsletter opt-in source', 'ran-emailoctopus-jetpack-forms' ) );
	}

	/**
	 * Check one configured source against every routable selected form.
	 *
	 * @param string $kind   Mapping kind.
	 * @param string $source Configured normalized source key.
	 * @param string $label  Health row label.
	 * @return array<string,string>
	 */
	private static function check_configured_source_across_forms( $kind, $source, $label ) {
		$form_ids      = self::get_routable_form_ids( IntegrationResolver::get_target_form_ids() );
		$compatibility = EmailOctopusFieldMapper::get_subscription_compatibility( $form_ids );
		$failed_ids    = array();

		foreach ( $compatibility as $form_id => $result ) {
			foreach ( (array) ( $result['source_failures'] ?? array() ) as $failure ) {
				if ( is_array( $failure ) && ( $failure['kind'] ?? '' ) === $kind && ( $failure['source'] ?? '' ) === $source ) {
					$failed_ids[] = absint( $form_id );
					break;
				}
			}
		}

		if ( empty( $failed_ids ) ) {
			$fields = 'email' === $kind ? EmailOctopusFieldMapper::get_email_source_fields_for_saved_forms( $form_ids ) : EmailOctopusFieldMapper::get_newsletter_source_fields_for_saved_forms( $form_ids );
			$name   = $source;

			foreach ( $fields as $field ) {
				if ( ( $field['key'] ?? '' ) === $source ) {
					$name = (string) ( $field['label'] ?? $source );
					break;
				}
			}

			return self::row( 'pass', $label, sprintf( /* translators: %s: selected Jetpack contact-form field label. */ __( 'Configured source "%s" is compatible across every routable selected form.', 'ran-emailoctopus-jetpack-forms' ), $name ) );
		}

		return self::row(
			count( $failed_ids ) < count( $form_ids ) ? 'warning' : 'error',
			$label,
			sprintf(
				/* translators: 1: configured source key, 2: comma-separated saved form IDs. */
				__( 'Configured source "%1$s" is missing, ambiguous, or type-incompatible on saved form(s) %2$s. Those forms cannot send EmailOctopus subscriptions; compatible peers remain active.', 'ran-emailoctopus-jetpack-forms' ),
				$source,
				implode( ', ', $failed_ids )
			)
		);
	}

	/**
	 * Explain every configured-source failure for one saved form.
	 *
	 * @param array<string,mixed> $result Mapper compatibility result.
	 * @return string
	 */
	private static function get_source_failure_message( $result ) {
		$messages = array();

		foreach ( (array) ( $result['source_failures'] ?? array() ) as $failure ) {
			if ( ! is_array( $failure ) ) {
				continue;
			}

			$kind     = (string) ( $failure['kind'] ?? '' );
			$tag      = (string) ( $failure['tag'] ?? '' );
			$source   = (string) ( $failure['source'] ?? '' );
			$reason   = (string) ( $failure['reason'] ?? '' );
			$expected = (string) ( $failure['expected_type'] ?? '' );
			$actual   = (string) ( $failure['actual_type'] ?? '' );
			$name     = 'custom' === $kind && '' !== $tag ? sprintf( '%s (%s)', $source, $tag ) : $source;

			switch ( $reason ) {
				case 'missing':
					$messages[] = sprintf( /* translators: %s: configured Jetpack source key. */ __( 'source "%s" is missing', 'ran-emailoctopus-jetpack-forms' ), $name );
					break;
				case 'ambiguous':
					$messages[] = sprintf( /* translators: %s: configured Jetpack source key. */ __( 'source "%s" is ambiguous', 'ran-emailoctopus-jetpack-forms' ), $name );
					break;
				case 'wrong_type':
				case 'type_mismatch':
					$messages[] = sprintf(
						/* translators: 1: configured Jetpack source key, 2: expected field type, 3: actual field type. */
						__( 'source "%1$s" expects %2$s but is %3$s', 'ran-emailoctopus-jetpack-forms' ),
						$name,
						'' !== $expected ? $expected : __( 'a compatible type', 'ran-emailoctopus-jetpack-forms' ),
						'' !== $actual ? $actual : __( 'another type', 'ran-emailoctopus-jetpack-forms' )
					);
					break;
				default:
					$messages[] = sprintf( /* translators: %s: configured Jetpack source key. */ __( 'source "%s" is incompatible', 'ran-emailoctopus-jetpack-forms' ), $name );
					break;
			}
		}

		if ( empty( $messages ) ) {
			$messages[] = __( 'the configured subscription mapping is incomplete', 'ran-emailoctopus-jetpack-forms' );
		}

		return sprintf(
			/* translators: %s: semicolon-separated mapping failure descriptions. */
			__( 'EmailOctopus is skipped for this form because %s. Other compatible selected forms remain active.', 'ran-emailoctopus-jetpack-forms' ),
			implode( '; ', $messages )
		);
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
