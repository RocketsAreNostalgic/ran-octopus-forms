<?php
/**
 * Profile-explicit diagnostics for RAN EmailOctopus for Jetpack Forms.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

namespace RAN\EmailOctopusJetpackForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds read-only profile summaries and explicit remote health results.
 */
final class HealthCheck {
	/**
	 * Build a local, read-only summary for one profile.
	 *
	 * This method never calls EmailOctopus. It is safe to use while rendering the
	 * integrations index; the administrator must explicitly run a health check to
	 * refresh provider status.
	 *
	 * @param string $profile_id Immutable profile UUID.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function get_profile_summary( $profile_id ) {
		$store = Settings::get_store();

		if ( is_wp_error( $store ) ) {
			return $store;
		}

		$profile = IntegrationResolver::get_profile( $profile_id );

		if ( null === $profile ) {
			return new \WP_Error( 'ran_emailoctopus_jetpack_forms_health_profile_missing', __( 'The integration profile no longer exists.', 'ran-emailoctopus-jetpack-forms' ) );
		}

		$global_checks = self::get_global_prerequisite_checks();
		$diagnostics   = self::get_profile_diagnostics( $profile );
		$overall       = self::get_overall_state( $diagnostics, $global_checks );

		return array(
			'overall'            => $overall,
			'profile_id'         => $profile->get_id(),
			'profile_revision'   => $profile->get_revision(),
			'store_revision'     => (int) $store['revision'],
			'settings_hash'      => Settings::get_health_hash(),
			'selected_count'     => $diagnostics['selected_count'],
			'routing_count'      => $diagnostics['routing_count'],
			'subscription_count' => $diagnostics['subscription_count'],
			'global_checks'      => $global_checks,
			'profile_checks'     => $diagnostics['checks'],
			'checks'             => array_merge( $global_checks, $diagnostics['checks'] ),
			'checked_at'         => 0,
		);
	}

	/**
	 * Run an explicit on-demand health check for one profile.
	 *
	 * @param string $profile_id Immutable profile UUID.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function run( $profile_id ) {
		$result = self::get_profile_summary( $profile_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$profile = IntegrationResolver::get_profile( $profile_id );

		if ( null === $profile ) {
			return new \WP_Error( 'ran_emailoctopus_jetpack_forms_health_profile_missing', __( 'The integration profile no longer exists.', 'ran-emailoctopus-jetpack-forms' ) );
		}

		$provider = self::check_provider( $profile );

		$result['global_checks'] = array_merge( $result['global_checks'], $provider['checks'] );
		$result['checks']        = array_merge( $result['global_checks'], $result['profile_checks'] );
		$result['checked_at']    = time();

		if ( ! $provider['available'] && 0 < $result['routing_count'] && 'incomplete' !== $result['overall'] ) {
			$result['overall']            = 'degraded';
			$result['subscription_count'] = 0;
		} elseif ( ! $provider['available'] && 0 === $result['routing_count'] && 'incomplete' !== $result['overall'] ) {
			$result['overall']            = 'broken';
			$result['subscription_count'] = 0;
		}

		return $result;
	}

	/**
	 * Whether a cached health result still represents the current profile store.
	 *
	 * @param mixed  $result     Candidate cached result.
	 * @param string $profile_id Expected profile UUID.
	 * @return bool
	 */
	public static function is_current_result( $result, $profile_id ) {
		if ( ! is_array( $result ) ) {
			return false;
		}

		$summary = self::get_profile_summary( $profile_id );

		if ( is_wp_error( $summary ) ) {
			return false;
		}

		return 0 < absint( $result['checked_at'] ?? 0 )
			&& ( $result['profile_id'] ?? '' ) === $summary['profile_id']
			&& ( $result['profile_revision'] ?? null ) === $summary['profile_revision']
			&& ( $result['store_revision'] ?? null ) === $summary['store_revision']
			&& hash_equals( $summary['settings_hash'], (string) ( $result['settings_hash'] ?? '' ) );
	}

	/**
	 * Build global prerequisite rows without contacting EmailOctopus.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function get_global_prerequisite_checks() {
		$portable = IntegrationResolver::supports_portable_forms();
		$api_key  = EmailOctopusApi::get_api_key();

		return array(
			self::row(
				$portable ? 'pass' : 'error',
				__( 'Jetpack feedback identity', 'ran-emailoctopus-jetpack-forms' ),
				$portable
					? __( 'Jetpack can authoritatively identify the saved form behind a feedback submission.', 'ran-emailoctopus-jetpack-forms' )
					: __( 'Jetpack cannot authoritatively identify saved-form feedback. Profile routing is unavailable until Jetpack is updated.', 'ran-emailoctopus-jetpack-forms' )
			),
			self::row(
				'' !== $api_key ? 'pass' : 'error',
				__( 'EmailOctopus API key', 'ran-emailoctopus-jetpack-forms' ),
				'' !== $api_key
					? __( 'The EmailOctopus API key is available.', 'ran-emailoctopus-jetpack-forms' )
					: __( 'The EmailOctopus API key is missing. Jetpack routing can continue, but EmailOctopus subscriptions are disabled.', 'ran-emailoctopus-jetpack-forms' )
			),
		);
	}

	/**
	 * Build all local diagnostics for one explicit profile.
	 *
	 * @param IntegrationProfile $profile Runtime profile.
	 * @return array{selected_count:int,routing_count:int,subscription_count:int,incomplete:bool,checks:array<int,array<string,string>>}
	 */
	private static function get_profile_diagnostics( $profile ) {
		$form_ids       = $profile->get_form_ids();
		$configuration  = $profile->get_configuration();
		$selected_count = count( $form_ids );
		$routable_ids   = array();
		$checks         = array();
		$incomplete     = false;

		$checks[] = self::row(
			0 < $selected_count ? 'pass' : 'skipped',
			__( 'Selected saved forms', 'ran-emailoctopus-jetpack-forms' ),
			0 < $selected_count
				? sprintf(
					/* translators: %d: number of selected forms. */
					_n( '%d saved form is assigned to this profile.', '%d saved forms are assigned to this profile.', $selected_count, 'ran-emailoctopus-jetpack-forms' ),
					$selected_count
				)
				: __( 'No saved forms are assigned. The profile is incomplete.', 'ran-emailoctopus-jetpack-forms' )
		);

		if ( 0 === $selected_count ) {
			$incomplete = true;
		}

		$success_check = self::check_success_page( $profile );
		$checks[]      = $success_check;

		if ( 'skipped' === $success_check['status'] ) {
			$incomplete = true;
		}

		$destination_check = self::check_destination( $configuration );
		$checks[]          = $destination_check;

		if ( 'skipped' === $destination_check['status'] ) {
			$incomplete = true;
		}

		foreach ( $form_ids as $form_id ) {
			$reason = IntegrationResolver::get_target_form_reason( $form_id, $profile->get_id() );

			if ( '' === $reason ) {
				$routable_ids[] = $form_id;
			}

			$checks[] = self::get_target_check( $form_id, $reason );
		}

		$compatibility  = EmailOctopusFieldMapper::get_subscription_compatibility( $form_ids, $configuration );
		$compatible_ids = array();

		foreach ( $compatibility as $form_id => $result ) {
			if ( ! empty( $result['eligible'] ) ) {
				$compatible_ids[] = absint( $form_id );
			}
		}

		$checks = array_merge(
			$checks,
			self::get_field_compatibility_checks( $routable_ids ),
			self::get_source_checks( $profile, $compatibility )
		);

		$email_source   = trim( (string) ( $configuration['emailoctopus_email_source'] ?? '' ) );
		$consent_source = trim( (string) ( $configuration['newsletter_source'] ?? '' ) );

		if ( '' === $email_source || '' === $consent_source ) {
			$incomplete = true;
		}

		$destination_ready = 'pass' === $destination_check['status'];
		$api_ready         = '' !== EmailOctopusApi::get_api_key();
		$subscription_ids  = $destination_ready && $api_ready ? array_values( array_intersect( $routable_ids, $compatible_ids ) ) : array();

		$checks[] = self::count_row(
			__( 'Routing coverage', 'ran-emailoctopus-jetpack-forms' ),
			count( $routable_ids ),
			$selected_count,
			__( 'selected form(s) can receive profile routing', 'ran-emailoctopus-jetpack-forms' )
		);
		$checks[] = self::count_row(
			__( 'Subscription coverage', 'ran-emailoctopus-jetpack-forms' ),
			count( $subscription_ids ),
			$selected_count,
			__( 'selected form(s) can send an EmailOctopus subscription', 'ran-emailoctopus-jetpack-forms' )
		);

		return array(
			'selected_count'     => $selected_count,
			'routing_count'      => count( $routable_ids ),
			'subscription_count' => count( $subscription_ids ),
			'incomplete'         => $incomplete,
			'checks'             => $checks,
		);
	}

	/**
	 * Check one profile's configured success page.
	 *
	 * @param IntegrationProfile $profile Profile.
	 * @return array<string,string>
	 */
	private static function check_success_page( $profile ) {
		$page_id = $profile->get_success_page_id();

		if ( 0 === $page_id ) {
			return self::row( 'skipped', __( 'Success page', 'ran-emailoctopus-jetpack-forms' ), __( 'No success page is selected. Save profile behaviour before routing can begin.', 'ran-emailoctopus-jetpack-forms' ) );
		}

		$page = get_post( $page_id );

		if ( ! $page instanceof \WP_Post ) {
			return self::row( 'error', __( 'Success page', 'ran-emailoctopus-jetpack-forms' ), sprintf( /* translators: %d: missing page ID. */ __( 'Success page #%d is unavailable.', 'ran-emailoctopus-jetpack-forms' ), $page_id ) );
		}

		if ( 'page' !== $page->post_type || 'publish' !== $page->post_status ) {
			return self::row( 'error', __( 'Success page', 'ran-emailoctopus-jetpack-forms' ), sprintf( /* translators: 1: page ID, 2: post status. */ __( 'Success destination #%1$d is not a published page (status: %2$s).', 'ran-emailoctopus-jetpack-forms' ), $page_id, $page->post_status ) );
		}

		return self::row( 'pass', __( 'Success page', 'ran-emailoctopus-jetpack-forms' ), sprintf( /* translators: %d: published page ID. */ __( 'Published success page #%d is available.', 'ran-emailoctopus-jetpack-forms' ), $page_id ) );
	}

	/**
	 * Check the explicit profile destination without contacting EmailOctopus.
	 *
	 * @param array<string,mixed> $configuration Effective profile configuration.
	 * @return array<string,string>
	 */
	private static function check_destination( $configuration ) {
		$form_id = trim( (string) ( $configuration['emailoctopus_form_id'] ?? '' ) );
		$list_id = trim( (string) ( $configuration['emailoctopus_list_id'] ?? '' ) );

		if ( '' === $form_id && '' === $list_id ) {
			return self::row( 'skipped', __( 'EmailOctopus destination', 'ran-emailoctopus-jetpack-forms' ), __( 'No EmailOctopus form or list is selected. Routing can continue, but subscriptions are not configured.', 'ran-emailoctopus-jetpack-forms' ) );
		}

		if ( '' !== $form_id && '' !== $list_id ) {
			return self::row( 'error', __( 'EmailOctopus destination', 'ran-emailoctopus-jetpack-forms' ), __( 'Both a form and a list resolve after filters. Configure exactly one destination.', 'ran-emailoctopus-jetpack-forms' ) );
		}

		$type = '' !== $list_id ? __( 'list', 'ran-emailoctopus-jetpack-forms' ) : __( 'form', 'ran-emailoctopus-jetpack-forms' );
		$id   = '' !== $list_id ? $list_id : $form_id;

		return self::row( 'pass', __( 'EmailOctopus destination', 'ran-emailoctopus-jetpack-forms' ), sprintf( /* translators: 1: destination type, 2: provider resource ID. */ __( 'EmailOctopus %1$s "%2$s" is configured for this profile.', 'ran-emailoctopus-jetpack-forms' ), $type, $id ) );
	}

	/**
	 * Build one selected-form target row while preserving unavailable IDs.
	 *
	 * @param int    $form_id Saved form ID.
	 * @param string $reason  Resolver reason code.
	 * @return array<string,string>
	 */
	private static function get_target_check( $form_id, $reason ) {
		$label = sprintf( /* translators: %d: saved Jetpack form ID. */ __( 'Saved Jetpack form #%d', 'ran-emailoctopus-jetpack-forms' ), $form_id );

		if ( '' === $reason ) {
			$form  = get_post( $form_id );
			$title = $form instanceof \WP_Post && '' !== trim( (string) $form->post_title ) ? $form->post_title : __( 'Untitled saved form', 'ran-emailoctopus-jetpack-forms' );

			return self::row( 'pass', $label, sprintf( /* translators: %s: saved form title. */ __( 'Published saved form "%s" is structurally valid and routable.', 'ran-emailoctopus-jetpack-forms' ), $title ) );
		}

		$messages = array(
			'target_ownership_conflict'       => __( 'This form is assigned to more than one profile. Routing fails closed for this form only.', 'ran-emailoctopus-jetpack-forms' ),
			'target_profile_mismatch'         => __( 'This form is owned by another profile. Routing fails closed for this form.', 'ran-emailoctopus-jetpack-forms' ),
			'feedback_identity_unavailable'   => __( 'Jetpack cannot provide authoritative feedback identity for this saved form.', 'ran-emailoctopus-jetpack-forms' ),
			'success_destination_unavailable' => __( 'This profile does not have a valid published success page, so the form cannot be routed.', 'ran-emailoctopus-jetpack-forms' ),
			'target_missing'                  => __( 'The selected saved form was deleted or is unavailable. Its stored ID is preserved until an administrator removes it.', 'ran-emailoctopus-jetpack-forms' ),
			'target_wrong_type'               => __( 'The selected post is not a saved Jetpack form. Its stored ID is preserved for repair or removal.', 'ran-emailoctopus-jetpack-forms' ),
			'target_not_published'            => __( 'The selected saved Jetpack form is not published. Publish it or remove it from this profile.', 'ran-emailoctopus-jetpack-forms' ),
			'target_invalid_structure'        => __( 'The selected saved form must contain exactly one Jetpack contact-form block.', 'ran-emailoctopus-jetpack-forms' ),
		);

		return self::row( 'error', $label, $messages[ $reason ] ?? __( 'The selected saved form is unavailable for profile routing.', 'ran-emailoctopus-jetpack-forms' ) );
	}

	/**
	 * Report shared field choices across routable forms.
	 *
	 * @param array<int,int> $form_ids Routable form IDs.
	 * @return array<int,array<string,string>>
	 */
	private static function get_field_compatibility_checks( $form_ids ) {
		if ( empty( $form_ids ) ) {
			return array(
				self::row( 'skipped', __( 'Shared field compatibility', 'ran-emailoctopus-jetpack-forms' ), __( 'Shared fields cannot be evaluated until at least one selected form is routable.', 'ran-emailoctopus-jetpack-forms' ) ),
			);
		}

		$fields     = EmailOctopusFieldMapper::get_source_fields( $form_ids );
		$email      = EmailOctopusFieldMapper::get_email_source_fields( $form_ids );
		$newsletter = EmailOctopusFieldMapper::get_newsletter_source_fields( $form_ids );

		return array(
			self::row( ! empty( $fields ) ? 'pass' : 'warning', __( 'Shared field compatibility', 'ran-emailoctopus-jetpack-forms' ), ! empty( $fields ) ? sprintf( /* translators: %d: compatible shared fields. */ __( '%d unambiguous field(s) share the same normalized key and type across every routable form.', 'ran-emailoctopus-jetpack-forms' ), count( $fields ) ) : __( 'The routable forms do not share an unambiguous field with the same key and type.', 'ran-emailoctopus-jetpack-forms' ) ),
			self::row( ! empty( $email ) ? 'pass' : 'error', __( 'Shared email source choices', 'ran-emailoctopus-jetpack-forms' ), ! empty( $email ) ? __( 'At least one compatible email field is shared across every routable form.', 'ran-emailoctopus-jetpack-forms' ) : __( 'No compatible email field is shared across every routable form.', 'ran-emailoctopus-jetpack-forms' ) ),
			self::row( ! empty( $newsletter ) ? 'pass' : 'error', __( 'Shared consent source choices', 'ran-emailoctopus-jetpack-forms' ), ! empty( $newsletter ) ? __( 'At least one compatible checkbox or consent field is shared across every routable form.', 'ran-emailoctopus-jetpack-forms' ) : __( 'No compatible checkbox or consent field is shared across every routable form.', 'ran-emailoctopus-jetpack-forms' ) ),
		);
	}

	/**
	 * Report every configured source and the forms it prevents from subscribing.
	 *
	 * @param IntegrationProfile   $profile       Profile.
	 * @param array<int,mixed>     $compatibility Per-form mapper results.
	 * @return array<int,array<string,string>>
	 */
	private static function get_source_checks( $profile, $compatibility ) {
		$configuration = $profile->get_configuration();
		$form_ids      = $profile->get_form_ids();
		$sources       = array(
			'email'      => array(
				'label'  => __( 'Email source', 'ran-emailoctopus-jetpack-forms' ),
				'source' => (string) ( $configuration['emailoctopus_email_source'] ?? '' ),
			),
			'newsletter' => array(
				'label'  => __( 'Consent source', 'ran-emailoctopus-jetpack-forms' ),
				'source' => (string) ( $configuration['newsletter_source'] ?? '' ),
			),
		);

		foreach ( (array) ( $configuration['emailoctopus_field_map'] ?? array() ) as $tag => $mapping ) {
			if ( ! is_array( $mapping ) || '' === (string) ( $mapping['source'] ?? '' ) ) {
				continue;
			}

			$sources[ 'custom:' . $tag ] = array(
				'label'  => sprintf( /* translators: %s: EmailOctopus field tag. */ __( 'Custom source for %s', 'ran-emailoctopus-jetpack-forms' ), $tag ),
				'source' => (string) $mapping['source'],
			);
		}

		$checks = array();

		foreach ( $sources as $kind => $details ) {
			$source     = trim( $details['source'] );
			$failed_ids = array();

			if ( '' === $source ) {
				$failed_ids = $form_ids;
			} else {
				foreach ( $compatibility as $form_id => $result ) {
					foreach ( (array) ( $result['source_failures'] ?? array() ) as $failure ) {
						if ( ! is_array( $failure ) ) {
							continue;
						}

						$matches = 0 === strpos( $kind, 'custom:' )
							? 'custom' === ( $failure['kind'] ?? '' ) && ( $failure['tag'] ?? '' ) === substr( $kind, 7 )
							: ( $failure['kind'] ?? '' ) === $kind;

						if ( $matches && ( $failure['source'] ?? '' ) === $source ) {
							$failed_ids[] = absint( $form_id );
							break;
						}
					}
				}
			}

			$failed_ids = Settings::normalize_form_ids( $failed_ids );

			if ( empty( $failed_ids ) ) {
				$checks[] = self::row( 'pass', $details['label'], sprintf( /* translators: %s: configured source key. */ __( 'Configured source "%s" is compatible with every routable form in this profile.', 'ran-emailoctopus-jetpack-forms' ), $source ) );
				continue;
			}

			$message = '' === $source
				? sprintf( /* translators: %s: comma-separated form IDs. */ __( 'No source is configured. Affected saved form(s): %s.', 'ran-emailoctopus-jetpack-forms' ), implode( ', ', $failed_ids ) )
				: sprintf( /* translators: 1: source key, 2: comma-separated form IDs. */ __( 'Configured source "%1$s" is missing, ambiguous, or type-incompatible on saved form(s): %2$s.', 'ran-emailoctopus-jetpack-forms' ), $source, implode( ', ', $failed_ids ) );

			$checks[] = self::row( count( $failed_ids ) < count( $form_ids ) ? 'warning' : 'error', $details['label'], $message );
		}

		foreach ( $compatibility as $form_id => $result ) {
			if ( ! empty( $result['eligible'] ) ) {
				$checks[] = self::row( 'pass', sprintf( /* translators: %d: saved form ID. */ __( 'Subscription mapping for form #%d', 'ran-emailoctopus-jetpack-forms' ), $form_id ), __( 'Every configured source is present, unambiguous, and type-compatible.', 'ran-emailoctopus-jetpack-forms' ) );
				continue;
			}

			$checks[] = self::row( 'error', sprintf( /* translators: %d: saved form ID. */ __( 'Subscription mapping for form #%d', 'ran-emailoctopus-jetpack-forms' ), $form_id ), self::get_source_failure_message( $result ) );
		}

		return $checks;
	}

	/**
	 * Check EmailOctopus provider reachability for one explicit destination.
	 *
	 * @param IntegrationProfile $profile Profile.
	 * @return array{available:bool,checks:array<int,array<string,string>>}
	 */
	private static function check_provider( $profile ) {
		$configuration = $profile->get_configuration();
		$form_id       = trim( (string) ( $configuration['emailoctopus_form_id'] ?? '' ) );
		$list_id       = trim( (string) ( $configuration['emailoctopus_list_id'] ?? '' ) );

		if ( '' === EmailOctopusApi::get_api_key() ) {
			return array(
				'available' => false,
				'checks'    => array( self::row( 'skipped', __( 'EmailOctopus provider reachability', 'ran-emailoctopus-jetpack-forms' ), __( 'Provider reachability was not tested because the API key is missing.', 'ran-emailoctopus-jetpack-forms' ) ) ),
			);
		}

		if ( '' === $form_id && '' === $list_id ) {
			return array(
				'available' => false,
				'checks'    => array( self::row( 'skipped', __( 'EmailOctopus provider reachability', 'ran-emailoctopus-jetpack-forms' ), __( 'Provider reachability was not tested because this profile has no destination.', 'ran-emailoctopus-jetpack-forms' ) ) ),
			);
		}

		if ( '' !== $form_id && '' !== $list_id ) {
			return array(
				'available' => false,
				'checks'    => array( self::row( 'error', __( 'EmailOctopus provider reachability', 'ran-emailoctopus-jetpack-forms' ), __( 'Provider reachability was not tested because the effective destination is ambiguous.', 'ran-emailoctopus-jetpack-forms' ) ) ),
			);
		}

		$checks = array();

		if ( '' !== $form_id ) {
			$form = EmailOctopusApi::get_form( $form_id );

			if ( is_wp_error( $form ) ) {
				return array(
					'available' => false,
					'checks'    => array( self::row( 'error', __( 'EmailOctopus provider reachability', 'ran-emailoctopus-jetpack-forms' ), $form->get_error_message() ) ),
				);
			}

			$list_id  = is_array( $form ) ? sanitize_text_field( (string) ( $form['list_id'] ?? '' ) ) : '';
			$checks[] = self::row( 'pass', __( 'EmailOctopus form', 'ran-emailoctopus-jetpack-forms' ), __( 'The configured EmailOctopus form resolves through the provider API.', 'ran-emailoctopus-jetpack-forms' ) );

			if ( '' === $list_id ) {
				$checks[] = self::row( 'error', __( 'EmailOctopus provider reachability', 'ran-emailoctopus-jetpack-forms' ), __( 'The configured EmailOctopus form did not resolve to a list.', 'ran-emailoctopus-jetpack-forms' ) );

				return array(
					'available' => false,
					'checks'    => $checks,
				);
			}
		}

		$list = EmailOctopusApi::get_list( $list_id );

		if ( is_wp_error( $list ) ) {
			$checks[] = self::row( 'error', __( 'EmailOctopus provider reachability', 'ran-emailoctopus-jetpack-forms' ), $list->get_error_message() );

			return array(
				'available' => false,
				'checks'    => $checks,
			);
		}

		$checks[] = self::row( 'pass', __( 'EmailOctopus provider reachability', 'ran-emailoctopus-jetpack-forms' ), __( 'The configured EmailOctopus list resolves through the provider API.', 'ran-emailoctopus-jetpack-forms' ) );

		return array(
			'available' => true,
			'checks'    => $checks,
		);
	}

	/**
	 * Explain configured-source failures for one saved form.
	 *
	 * @param array<string,mixed> $result Mapper compatibility result.
	 * @return string
	 */
	public static function get_source_failure_message( $result ) {
		$messages = array();

		foreach ( (array) ( $result['source_failures'] ?? array() ) as $failure ) {
			if ( ! is_array( $failure ) ) {
				continue;
			}

			$source = (string) ( $failure['source'] ?? '' );
			$tag    = (string) ( $failure['tag'] ?? '' );
			$name   = '' !== $tag ? sprintf( '%s (%s)', $source, $tag ) : $source;
			$reason = (string) ( $failure['reason'] ?? '' );

			if ( 'missing' === $reason ) {
				$messages[] = sprintf( /* translators: %s: source key. */ __( 'source "%s" is missing', 'ran-emailoctopus-jetpack-forms' ), $name );
			} elseif ( 'ambiguous' === $reason ) {
				$messages[] = sprintf( /* translators: %s: source key. */ __( 'source "%s" is ambiguous', 'ran-emailoctopus-jetpack-forms' ), $name );
			} else {
				$messages[] = sprintf( /* translators: %s: source key. */ __( 'source "%s" has an incompatible field type', 'ran-emailoctopus-jetpack-forms' ), $name );
			}
		}

		if ( empty( $messages ) ) {
			$routing_reason = (string) ( $result['routing_reason'] ?? '' );
			return '' !== $routing_reason
				? sprintf( /* translators: %s: diagnostic reason code. */ __( 'EmailOctopus is skipped because routing is unavailable (%s).', 'ran-emailoctopus-jetpack-forms' ), $routing_reason )
				: __( 'EmailOctopus is skipped because the configured mapping is incomplete.', 'ran-emailoctopus-jetpack-forms' );
		}

		return sprintf( /* translators: %s: semicolon-separated failure descriptions. */ __( 'EmailOctopus is skipped because %s.', 'ran-emailoctopus-jetpack-forms' ), implode( '; ', $messages ) );
	}

	/**
	 * Determine one of the four index states from local diagnostics.
	 *
	 * @param array<string,mixed>             $diagnostics  Profile diagnostics.
	 * @param array<int,array<string,string>> $global_checks Global prerequisite rows.
	 * @return string
	 */
	private static function get_overall_state( $diagnostics, $global_checks ) {
		if ( ! empty( $diagnostics['incomplete'] ) ) {
			return 'incomplete';
		}

		$selected     = (int) $diagnostics['selected_count'];
		$routing      = (int) $diagnostics['routing_count'];
		$subscription = (int) $diagnostics['subscription_count'];

		if ( 0 === $routing ) {
			return 'broken';
		}

		$global_error = false;

		foreach ( $global_checks as $check ) {
			if ( 'error' === $check['status'] ) {
				$global_error = true;
				break;
			}
		}

		if ( $global_error || $routing < $selected || $subscription < $selected ) {
			return 'degraded';
		}

		return 'healthy';
	}

	/**
	 * Build a coverage row.
	 *
	 * @param string $label Label.
	 * @param int    $active Active count.
	 * @param int    $total Selected count.
	 * @param string $description Count description.
	 * @return array<string,string>
	 */
	private static function count_row( $label, $active, $total, $description ) {
		$status = 0 === $total ? 'skipped' : ( $active === $total ? 'pass' : ( 0 < $active ? 'warning' : 'error' ) );

		return self::row( $status, $label, sprintf( /* translators: 1: active count, 2: selected count, 3: coverage description. */ __( '%1$d of %2$d %3$s.', 'ran-emailoctopus-jetpack-forms' ), $active, $total, $description ) );
	}

	/**
	 * Build one health row.
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
}
