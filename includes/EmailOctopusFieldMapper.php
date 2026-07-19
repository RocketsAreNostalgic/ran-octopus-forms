<?php
/**
 * EmailOctopus field mapping helpers.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

namespace RAN\EmailOctopusJetpackForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps submitted Jetpack form fields to EmailOctopus list fields.
 */
final class EmailOctopusFieldMapper {
	/**
	 * Jetpack field types that can represent a newsletter opt-in.
	 *
	 * @var array<int,string>
	 */
	const NEWSLETTER_SOURCE_FIELD_TYPES = array( 'checkbox', 'consent' );

	/**
	 * Jetpack field types that can supply EmailOctopus email_address.
	 *
	 * @var array<int,string>
	 */
	const EMAIL_SOURCE_FIELD_TYPES = array( 'email' );

	/**
	 * Get available transform options.
	 *
	 * @return array<string,string>
	 */
	public static function get_transform_options() {
		return array(
			'as_is'           => __( 'No transform', 'ran-emailoctopus-jetpack-forms' ),
			'first_word'      => __( 'Use first word only', 'ran-emailoctopus-jetpack-forms' ),
			'remaining_words' => __( 'Use everything after first word', 'ran-emailoctopus-jetpack-forms' ),
			'lowercase'       => __( 'Send lowercase', 'ran-emailoctopus-jetpack-forms' ),
		);
	}

	/**
	 * Get Jetpack fields shared by every structurally valid selected form.
	 *
	 * @param array<int,int> $form_ids Saved form IDs.
	 * @return array<int,array<string,string>>
	 */
	public static function get_source_fields( $form_ids ) {
		return self::get_source_fields_for_saved_forms( $form_ids );
	}

	/**
	 * Build the detected field matrix for selected saved forms.
	 *
	 * Unavailable or structurally invalid selections remain represented with an
	 * empty field list so diagnostics can retain their identity.
	 *
	 * @param array<int,int> $form_ids Saved form IDs.
	 * @return array<int,array<int,array<string,string>>>
	 */
	public static function get_source_field_matrix( $form_ids ) {
		$form_ids = Settings::normalize_form_ids( $form_ids );
		$matrix   = array();

		foreach ( $form_ids as $form_id ) {
			$matrix[ $form_id ] = self::get_source_fields_for_saved_form( $form_id );
		}

		return $matrix;
	}

	/**
	 * Get unambiguous fields with the same key and exact type on every valid form.
	 *
	 * @param array<int,int> $form_ids Saved form IDs.
	 * @return array<int,array<string,string>>
	 */
	public static function get_source_fields_for_saved_forms( $form_ids ) {
		return self::get_compatible_source_fields( $form_ids, array(), false );
	}

	/**
	 * Get Jetpack fields from a saved Jetpack form post.
	 *
	 * @param int $form_id Saved Jetpack form ID.
	 * @return array<int,array<string,string>>
	 */
	public static function get_source_fields_for_saved_form( $form_id ) {
		$form = get_post( absint( $form_id ) );

		if ( ! $form instanceof \WP_Post || 'jetpack_form' !== $form->post_type || 'publish' !== $form->post_status || ! Settings::has_valid_saved_form_structure( $form_id ) ) {
			return array();
		}

		return self::get_source_fields_from_content( (string) $form->post_content );
	}

	/**
	 * Get fields that can be used as a newsletter opt-in.
	 *
	 * An implicit Jetpack consent field means submitting the form subscribes the
	 * visitor. It is valid only when an administrator deliberately selects it for
	 * a newsletter signup form. Radio and select fields need an affirmative
	 * option to be configured separately, so they are not supported.
	 *
	 * @param array<int,int> $form_ids Saved form IDs.
	 * @return array<int,array<string,string>>
	 */
	public static function get_newsletter_source_fields( $form_ids ) {
		return self::get_newsletter_source_fields_for_saved_forms( $form_ids );
	}

	/**
	 * Get newsletter fields shared across forms as one consent-type family.
	 *
	 * @param array<int,int> $form_ids Saved form IDs.
	 * @return array<int,array<string,string>>
	 */
	public static function get_newsletter_source_fields_for_saved_forms( $form_ids ) {
		return self::get_compatible_source_fields( $form_ids, self::NEWSLETTER_SOURCE_FIELD_TYPES, true );
	}

	/**
	 * Get newsletter opt-in fields from a saved Jetpack form.
	 *
	 * @param int $form_id Saved Jetpack form ID.
	 * @return array<int,array<string,string>>
	 */
	public static function get_newsletter_source_fields_for_saved_form( $form_id ) {
		return array_values(
			array_filter(
				self::get_source_fields_for_saved_form( $form_id ),
				array( __CLASS__, 'is_supported_newsletter_source_field' )
			)
		);
	}

	/**
	 * Get fields that can be used as EmailOctopus email_address.
	 *
	 * @param array<int,int> $form_ids Saved form IDs.
	 * @return array<int,array<string,string>>
	 */
	public static function get_email_source_fields( $form_ids ) {
		return self::get_email_source_fields_for_saved_forms( $form_ids );
	}

	/**
	 * Get email fields shared across all structurally valid selected forms.
	 *
	 * @param array<int,int> $form_ids Saved form IDs.
	 * @return array<int,array<string,string>>
	 */
	public static function get_email_source_fields_for_saved_forms( $form_ids ) {
		return self::get_compatible_source_fields( $form_ids, self::EMAIL_SOURCE_FIELD_TYPES, false );
	}

	/**
	 * Get email source fields from a saved Jetpack form.
	 *
	 * @param int $form_id Saved Jetpack form ID.
	 * @return array<int,array<string,string>>
	 */
	public static function get_email_source_fields_for_saved_form( $form_id ) {
		return array_values(
			array_filter(
				self::get_source_fields_for_saved_form( $form_id ),
				array( __CLASS__, 'is_supported_email_source_field' )
			)
		);
	}

	/**
	 * Whether a detected field is supported as a newsletter opt-in source.
	 *
	 * @param array<string,string> $field Detected Jetpack field.
	 * @return bool
	 */
	public static function is_supported_newsletter_source_field( $field ) {
		return empty( $field['ambiguous'] ) && in_array( (string) ( $field['type'] ?? '' ), self::NEWSLETTER_SOURCE_FIELD_TYPES, true );
	}

	/**
	 * Whether a detected field is supported as the EmailOctopus email source.
	 *
	 * @param array<string,string> $field Detected Jetpack field.
	 * @return bool
	 */
	public static function is_supported_email_source_field( $field ) {
		return empty( $field['ambiguous'] ) && in_array( (string) ( $field['type'] ?? '' ), self::EMAIL_SOURCE_FIELD_TYPES, true );
	}

	/**
	 * Report subscription compatibility independently for each selected form.
	 *
	 * @param array<int,int>           $form_ids      Saved form IDs.
	 * @param array<string,mixed>      $configuration Explicit profile configuration.
	 * @return array<int,array{eligible:bool,routing_reason:string,reasons:array<int,string>,source_failures:array<int,array{kind:string,tag:string,source:string,reason:string,expected_type:string,actual_type:string}>}>
	 */
	public static function get_subscription_compatibility( $form_ids, $configuration ) {
		$form_ids      = Settings::normalize_form_ids( $form_ids );
		$configuration = is_array( $configuration ) ? $configuration : array();
		$matrix        = self::get_source_field_matrix( $form_ids );
		$email_source  = self::normalize_source_key( (string) ( $configuration['emailoctopus_email_source'] ?? '' ) );
		$newsletter    = self::normalize_source_key( (string) ( $configuration['newsletter_source'] ?? '' ) );
		$field_map     = is_array( $configuration['emailoctopus_field_map'] ?? null ) ? $configuration['emailoctopus_field_map'] : array();
		$custom_types  = self::get_expected_custom_source_types( $form_ids, $matrix, $field_map );
		$results       = array();

		foreach ( $form_ids as $form_id ) {
			$routing_reason = IntegrationResolver::get_target_form_reason( $form_id );
			$failures       = array();
			$reasons        = array();

			if ( '' !== $routing_reason ) {
				$reasons[] = 'routing_ineligible';
			} else {
				$email_failure = self::get_source_failure( $matrix[ $form_id ], 'email', '', $email_source, self::EMAIL_SOURCE_FIELD_TYPES, '' );

				if ( null !== $email_failure ) {
					$failures[] = $email_failure;
					$reasons[]  = 'missing' === $email_failure['reason'] ? 'email_source_missing' : 'email_source_invalid';
				}

				$newsletter_failure = self::get_source_failure( $matrix[ $form_id ], 'newsletter', '', $newsletter, self::NEWSLETTER_SOURCE_FIELD_TYPES, '' );

				if ( null !== $newsletter_failure ) {
					$failures[] = $newsletter_failure;
					$reasons[]  = 'missing' === $newsletter_failure['reason'] ? 'newsletter_source_missing' : 'newsletter_source_invalid';
				}

				foreach ( $field_map as $tag => $mapping ) {
					if ( ! is_array( $mapping ) ) {
						continue;
					}

					$source = self::normalize_source_key( (string) ( $mapping['source'] ?? '' ) );

					if ( '' === $source ) {
						continue;
					}

					$expected_type = (string) ( $custom_types[ $source ] ?? '' );
					$failure       = self::get_source_failure( $matrix[ $form_id ], 'custom', sanitize_text_field( (string) $tag ), $source, array(), $expected_type );

					if ( null === $failure ) {
						continue;
					}

					$failures[] = $failure;
					$reasons[]  = 'missing' === $failure['reason'] ? 'custom_source_missing' : ( 'type_mismatch' === $failure['reason'] ? 'custom_source_type_mismatch' : 'custom_source_invalid' );
				}
			}

			$results[ $form_id ] = array(
				'eligible'        => '' === $routing_reason && empty( $failures ),
				'routing_reason'  => $routing_reason,
				'reasons'         => array_values( array_unique( $reasons ) ),
				'source_failures' => $failures,
			);
		}

		return $results;
	}

	/**
	 * Whether one selected form has every configured subscription source.
	 *
	 * @param int                 $form_id       Saved Jetpack form ID.
	 * @param array<string,mixed> $configuration Explicit profile configuration.
	 * @return bool
	 */
	public static function is_subscription_compatible_form_id( $form_id, $configuration ) {
		$form_id = absint( $form_id );
		$results = self::get_subscription_compatibility( array( $form_id ), $configuration );

		return ! empty( $results[ $form_id ]['eligible'] );
	}

	/**
	 * Get custom EmailOctopus fields from a list response.
	 *
	 * @param array<string,mixed> $emailoctopus_list EmailOctopus list response.
	 * @return array<int,array<string,string>>
	 */
	public static function get_custom_fields( $emailoctopus_list ) {
		$fields = isset( $emailoctopus_list['fields'] ) && is_array( $emailoctopus_list['fields'] ) ? $emailoctopus_list['fields'] : array();
		$custom = array();

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$tag = sanitize_text_field( (string) ( $field['tag'] ?? '' ) );

			if ( '' === $tag || 'emailaddress' === strtolower( $tag ) ) {
				continue;
			}

			$custom[] = array(
				'tag'   => $tag,
				'type'  => sanitize_text_field( (string) ( $field['type'] ?? '' ) ),
				'label' => sanitize_text_field( (string) ( $field['label'] ?? '' ) ),
			);
		}

		return $custom;
	}

	/**
	 * Build EmailOctopus `fields` payload from submitted Jetpack values.
	 *
	 * @param array<string,mixed> $all_values Submitted Jetpack values.
	 * @param array<string,mixed> $field_map  Explicit profile field mapping.
	 * @return array<string,string>
	 */
	public static function build_fields_payload( $all_values, $field_map ) {
		$field_map = is_array( $field_map ) ? $field_map : array();
		$payload   = array();

		foreach ( $field_map as $tag => $mapping ) {
			$source = self::normalize_source_key( (string) ( $mapping['source'] ?? '' ) );

			if ( '' === $source ) {
				continue;
			}

			$value = self::get_submitted_value( $all_values, $source );

			if ( '' === $value ) {
				continue;
			}

			$value = self::transform_value( $value, (string) ( $mapping['transform'] ?? 'as_is' ) );

			if ( '' === $value ) {
				continue;
			}

			$payload[ $tag ] = $value;
		}

		return $payload;
	}

	/**
	 * Find the submitted email address.
	 *
	 * Uses only the explicitly configured source.
	 *
	 * @param array<string,mixed> $all_values   Submitted Jetpack values.
	 * @param string              $email_source Explicit normalized source key.
	 * @return string
	 */
	public static function get_email_address( $all_values, $email_source ) {
		$email_source = self::normalize_source_key( $email_source );

		if ( '' === $email_source ) {
			return '';
		}

		$email = sanitize_email( self::get_submitted_value( $all_values, $email_source ) );

		return is_email( $email ) ? $email : '';
	}

	/**
	 * Whether a submitted source field is truthy.
	 *
	 * @param array<string,mixed> $all_values Submitted Jetpack values.
	 * @param string              $source     Normalized source key.
	 * @return bool
	 */
	public static function has_truthy_submitted_value( $all_values, $source ) {
		$source = self::normalize_source_key( $source );
		$value  = self::get_submitted_value_raw( $all_values, $source );

		if ( null === $value ) {
			return false;
		}

		if ( is_array( $value ) ) {
			$value = reset( $value );
		}

		if ( is_bool( $value ) ) {
			return $value;
		}

		$value = strtolower( trim( (string) $value ) );

		return in_array( $value, array( '1', 'on', 'true', 'yes', 'y' ), true );
	}

	/**
	 * Normalize a source key for comparing block labels to submitted keys.
	 *
	 * @param string $value Raw source label/key.
	 * @return string
	 */
	public static function normalize_source_key( $value ) {
		$value = strtolower( trim( $value ) );
		$value = preg_replace( '/[^a-z0-9]+/', '_', $value );
		$value = is_string( $value ) ? trim( $value, '_' ) : '';

		return $value;
	}

	/**
	 * Intersect compatible fields across structurally valid selected forms.
	 *
	 * @param array<int,int>    $form_ids     Saved form IDs.
	 * @param array<int,string> $allowed_types Optional supported field types.
	 * @param bool              $type_family  Whether allowed types are interchangeable.
	 * @return array<int,array<string,string>>
	 */
	private static function get_compatible_source_fields( $form_ids, $allowed_types, $type_family ) {
		$form_ids = Settings::normalize_form_ids( $form_ids );
		$form_ids = array_values(
			array_filter(
				$form_ids,
				static function ( $form_id ) {
					return Settings::is_valid_published_saved_form( $form_id ) && Settings::has_valid_saved_form_structure( $form_id );
				}
			)
		);

		if ( empty( $form_ids ) ) {
			return array();
		}

		$matrix     = self::get_source_field_matrix( $form_ids );
		$candidates = self::index_unambiguous_fields( $matrix[ $form_ids[0] ] );

		foreach ( $candidates as $key => $field ) {
			if ( ! empty( $allowed_types ) && ! in_array( (string) ( $field['type'] ?? '' ), $allowed_types, true ) ) {
				unset( $candidates[ $key ] );
			}
		}

		foreach ( array_slice( $form_ids, 1 ) as $form_id ) {
			$fields = self::index_unambiguous_fields( $matrix[ $form_id ] );

			foreach ( $candidates as $key => $candidate ) {
				if ( ! isset( $fields[ $key ] ) ) {
					unset( $candidates[ $key ] );
					continue;
				}

				$type = (string) ( $fields[ $key ]['type'] ?? '' );

				if ( ! empty( $allowed_types ) && ! in_array( $type, $allowed_types, true ) ) {
					unset( $candidates[ $key ] );
					continue;
				}

				if ( ! $type_family && (string) ( $candidate['type'] ?? '' ) !== $type ) {
					unset( $candidates[ $key ] );
				}
			}
		}

		return array_values( $candidates );
	}

	/**
	 * Index unambiguous fields by normalized source key.
	 *
	 * @param array<int,array<string,string>> $fields Detected fields.
	 * @return array<string,array<string,string>>
	 */
	private static function index_unambiguous_fields( $fields ) {
		$indexed = array();

		foreach ( $fields as $field ) {
			$key = (string) ( $field['key'] ?? '' );

			if ( '' !== $key && empty( $field['ambiguous'] ) ) {
				$indexed[ $key ] = $field;
			}
		}

		return $indexed;
	}

	/**
	 * Determine each configured custom source's canonical type.
	 *
	 * The earliest routing-eligible selected form that contains an unambiguous
	 * source establishes its type. This preserves the migrated primary form while
	 * isolating later selections that reuse the key with another Jetpack type.
	 *
	 * @param array<int,int>                              $form_ids Saved form IDs.
	 * @param array<int,array<int,array<string,string>>> $matrix   Detected fields by form.
	 * @param array<string,mixed>                         $field_map Configured mappings.
	 * @return array<string,string>
	 */
	private static function get_expected_custom_source_types( $form_ids, $matrix, $field_map ) {
		$expected = array();

		foreach ( $field_map as $mapping ) {
			if ( ! is_array( $mapping ) ) {
				continue;
			}

			$source = self::normalize_source_key( (string) ( $mapping['source'] ?? '' ) );

			if ( '' === $source || isset( $expected[ $source ] ) ) {
				continue;
			}

			foreach ( $form_ids as $form_id ) {
				if ( '' !== IntegrationResolver::get_target_form_reason( $form_id ) ) {
					continue;
				}

				$fields = self::index_unambiguous_fields( $matrix[ $form_id ] ?? array() );

				if ( isset( $fields[ $source ] ) ) {
					$expected[ $source ] = (string) ( $fields[ $source ]['type'] ?? '' );
					break;
				}
			}
		}

		return $expected;
	}

	/**
	 * Describe why one configured source is incompatible with a form.
	 *
	 * @param array<int,array<string,string>> $fields        Detected form fields.
	 * @param string                          $kind          Source role.
	 * @param string                          $tag           EmailOctopus custom tag.
	 * @param string                          $source        Normalized source key.
	 * @param array<int,string>               $allowed_types Supported type family.
	 * @param string                          $expected_type Canonical custom type.
	 * @return array{kind:string,tag:string,source:string,reason:string,expected_type:string,actual_type:string}|null
	 */
	private static function get_source_failure( $fields, $kind, $tag, $source, $allowed_types, $expected_type ) {
		$indexed        = array();
		$expected_label = '' !== $expected_type ? $expected_type : implode( '|', $allowed_types );

		foreach ( $fields as $field ) {
			$key = (string) ( $field['key'] ?? '' );

			if ( '' !== $key ) {
				$indexed[ $key ] = $field;
			}
		}

		if ( '' === $source || ! isset( $indexed[ $source ] ) ) {
			$reason      = 'missing';
			$actual_type = '';
		} else {
			$field       = $indexed[ $source ];
			$actual_type = (string) ( $field['type'] ?? '' );

			if ( ! empty( $field['ambiguous'] ) ) {
				$reason = 'ambiguous';
			} elseif ( ! empty( $allowed_types ) && ! in_array( $actual_type, $allowed_types, true ) ) {
				$reason = 'wrong_type';
			} elseif ( '' !== $expected_type && $actual_type !== $expected_type ) {
				$reason = 'type_mismatch';
			} else {
				return null;
			}
		}

		return array(
			'kind'          => $kind,
			'tag'           => $tag,
			'source'        => $source,
			'reason'        => $reason,
			'expected_type' => $expected_label,
			'actual_type'   => $actual_type,
		);
	}

	/**
	 * Collect source fields from serialized block content.
	 *
	 * @param string $content Serialized block content.
	 * @return array<int,array<string,string>>
	 */
	private static function get_source_fields_from_content( $content ) {
		$fields = array();

		if ( '' === $content ) {
			return $fields;
		}

		foreach ( parse_blocks( $content ) as $block ) {
			self::collect_source_fields( $block, $fields );
		}

		return array_values( $fields );
	}

	/**
	 * Collect Jetpack field blocks.
	 *
	 * @param array<string,mixed>            $block  Block data.
	 * @param array<string,array<string,string>> $fields Field accumulator.
	 * @return void
	 */
	private static function collect_source_fields( $block, &$fields ) {
		$block_name = (string) ( $block['blockName'] ?? '' );

		if ( 0 === strpos( $block_name, 'jetpack/field-' ) ) {
			$label        = self::get_field_label( $block );
			$type         = str_replace( 'jetpack/field-', '', $block_name );
			$consent_type = sanitize_key( (string) ( $block['attrs']['consentType'] ?? $block['attrs']['consenttype'] ?? '' ) );
			$key          = self::normalize_source_key( $label );

			if ( '' !== $key ) {
				if ( isset( $fields[ $key ] ) ) {
					$fields[ $key ]['ambiguous'] = '1';
				} else {
					$fields[ $key ] = array(
						'key'          => $key,
						'label'        => $label,
						'type'         => $type,
						'consent_type' => $consent_type,
						'ambiguous'    => '0',
					);
				}
			}
		}

		if ( empty( $block['innerBlocks'] ) || ! is_array( $block['innerBlocks'] ) ) {
			return;
		}

		foreach ( $block['innerBlocks'] as $inner_block ) {
			self::collect_source_fields( $inner_block, $fields );
		}
	}

	/**
	 * Get a Jetpack field label.
	 *
	 * @param array<string,mixed> $block Field block.
	 * @return string
	 */
	private static function get_field_label( $block ) {
		return self::find_nested_label( $block );
	}

	/**
	 * Find a nested Jetpack label or option label.
	 *
	 * @param array<string,mixed> $block Block data.
	 * @return string
	 */
	private static function find_nested_label( $block ) {
		if ( in_array( (string) ( $block['blockName'] ?? '' ), array( 'jetpack/label', 'jetpack/option' ), true ) ) {
			$label = sanitize_text_field( (string) ( $block['attrs']['label'] ?? '' ) );

			if ( '' !== $label ) {
				return $label;
			}
		}

		if ( empty( $block['innerBlocks'] ) || ! is_array( $block['innerBlocks'] ) ) {
			return '';
		}

		foreach ( $block['innerBlocks'] as $inner_block ) {
			$label = self::find_nested_label( $inner_block );

			if ( '' !== $label ) {
				return $label;
			}
		}

		return '';
	}

	/**
	 * Get submitted value for a normalized source key.
	 *
	 * @param array<string,mixed> $all_values Submitted Jetpack values.
	 * @param string              $source     Normalized source key.
	 * @return string
	 */
	private static function get_submitted_value( $all_values, $source ) {
		$value = self::get_submitted_value_raw( $all_values, $source );

		if ( null === $value ) {
			return '';
		}

		if ( is_array( $value ) ) {
			$value = reset( $value );
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Get raw submitted value for a normalized source key.
	 *
	 * @param array<string,mixed> $all_values Submitted Jetpack values.
	 * @param string              $source     Normalized source key.
	 * @return mixed|null
	 */
	private static function get_submitted_value_raw( $all_values, $source ) {
		foreach ( $all_values as $key => $value ) {
			$key = preg_replace( '/^\d+_/', '', (string) $key );

			if ( self::normalize_source_key( is_string( $key ) ? $key : '' ) !== $source ) {
				continue;
			}

			return $value;
		}

		return null;
	}

	/**
	 * Apply a configured transform.
	 *
	 * @param string $value     Field value.
	 * @param string $transform Transform key.
	 * @return string
	 */
	private static function transform_value( $value, $transform ) {
		$value = trim( $value );

		if ( 'first_word' === $transform ) {
			$parts = preg_split( '/\s+/', $value );
			return is_array( $parts ) ? (string) reset( $parts ) : $value;
		}

		if ( 'remaining_words' === $transform ) {
			$parts = preg_split( '/\s+/', $value );

			if ( ! is_array( $parts ) || 2 > count( $parts ) ) {
				return '';
			}

			array_shift( $parts );
			return implode( ' ', $parts );
		}

		if ( 'lowercase' === $transform ) {
			return strtolower( $value );
		}

		return $value;
	}
}
