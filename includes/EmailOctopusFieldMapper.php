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
	 * Get Jetpack fields available on the configured saved form.
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function get_source_fields() {
		return self::get_source_fields_for_saved_form( Settings::get_target_form_id() );
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
	 * @return array<int,array<string,string>>
	 */
	public static function get_newsletter_source_fields() {
		return array_values(
			array_filter(
				self::get_source_fields(),
				array( __CLASS__, 'is_supported_newsletter_source_field' )
			)
		);
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
	 * @return array<int,array<string,string>>
	 */
	public static function get_email_source_fields() {
		return array_values(
			array_filter(
				self::get_source_fields(),
				array( __CLASS__, 'is_supported_email_source_field' )
			)
		);
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
	 * @return array<string,string>
	 */
	public static function build_fields_payload( $all_values ) {
		$field_map = Settings::get_emailoctopus_field_map();
		$payload   = array();

		foreach ( $field_map as $tag => $mapping ) {
			$source = (string) ( $mapping['source'] ?? '' );

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
	 * @param array<string,mixed> $all_values Submitted Jetpack values.
	 * @return string
	 */
	public static function get_email_address( $all_values ) {
		$email_source = Settings::get_emailoctopus_email_source();

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
		$value = self::get_submitted_value_raw( $all_values, $source );

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
