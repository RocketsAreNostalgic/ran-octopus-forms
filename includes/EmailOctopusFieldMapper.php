<?php
/**
 * EmailOctopus field mapping helpers.
 *
 * @package RAN_Octopus_Forms
 */

namespace RAN\OctopusForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps submitted Jetpack form fields to EmailOctopus list fields.
 */
final class EmailOctopusFieldMapper {
	/**
	 * Get available transform options.
	 *
	 * @return array<string,string>
	 */
	public static function get_transform_options() {
		return array(
			'as_is'           => __( 'No transform', 'ran-octopus-forms' ),
			'first_word'      => __( 'Use first word only', 'ran-octopus-forms' ),
			'remaining_words' => __( 'Use everything after first word', 'ran-octopus-forms' ),
			'lowercase'       => __( 'Send lowercase', 'ran-octopus-forms' ),
		);
	}

	/**
	 * Get Jetpack fields available on the configured contact form.
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function get_source_fields() {
		$fields = array();
		$content = self::get_contact_form_content();

		if ( '' === $content ) {
			return $fields;
		}

		foreach ( parse_blocks( $content ) as $block ) {
			self::collect_source_fields( $block, $fields );
		}

		return array_values( $fields );
	}

	/**
	 * Get custom EmailOctopus fields from a list response.
	 *
	 * @param array<string,mixed> $list EmailOctopus list response.
	 * @return array<int,array<string,string>>
	 */
	public static function get_custom_fields( $list ) {
		$fields = isset( $list['fields'] ) && is_array( $list['fields'] ) ? $list['fields'] : array();
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
	 * Uses an explicitly configured source first, then falls back to the
	 * standard Jetpack email key and finally any submitted email-like field.
	 *
	 * @param array<string,mixed> $all_values Submitted Jetpack values.
	 * @return string
	 */
	public static function get_email_address( $all_values ) {
		$email_source = Settings::get_emailoctopus_email_source();

		if ( '' !== $email_source ) {
			$email = sanitize_email( self::get_submitted_value( $all_values, $email_source ) );

			return is_email( $email ) ? $email : '';
		}

		if ( isset( $all_values['email'] ) && is_email( $all_values['email'] ) ) {
			return (string) $all_values['email'];
		}

		foreach ( $all_values as $key => $value ) {
			if ( false === stripos( (string) $key, 'email' ) || ! is_string( $value ) ) {
				continue;
			}

			if ( is_email( $value ) ) {
				return $value;
			}
		}

		return '';
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
	 * Get configured contact form content.
	 *
	 * @return string
	 */
	private static function get_contact_form_content() {
		$page_content = get_post_field( 'post_content', Settings::get_contact_page_id() );
		$form_block   = self::find_contact_form_block( parse_blocks( is_string( $page_content ) ? $page_content : '' ) );

		if ( ! is_array( $form_block ) ) {
			return '';
		}

		$ref = absint( $form_block['attrs']['ref'] ?? 0 );

		if ( 0 < $ref ) {
			return (string) get_post_field( 'post_content', $ref );
		}

		return serialize_block( $form_block );
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
	 * Collect Jetpack field blocks.
	 *
	 * @param array<string,mixed>            $block  Block data.
	 * @param array<string,array<string,string>> $fields Field accumulator.
	 * @return void
	 */
	private static function collect_source_fields( $block, &$fields ) {
		$block_name = (string) ( $block['blockName'] ?? '' );

		if ( 0 === strpos( $block_name, 'jetpack/field-' ) ) {
			$label = self::get_field_label( $block );
			$key   = self::normalize_source_key( $label );

			if ( '' !== $key && ! isset( $fields[ $key ] ) ) {
				$fields[ $key ] = array(
					'key'   => $key,
					'label' => $label,
					'type'  => str_replace( 'jetpack/field-', '', $block_name ),
				);
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
		$nested_label = self::find_nested_label( $block );

		if ( '' !== $nested_label ) {
			return $nested_label;
		}

		return ucwords( str_replace( '-', ' ', str_replace( 'jetpack/field-', '', (string) ( $block['blockName'] ?? '' ) ) ) );
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
			if ( self::normalize_source_key( (string) $key ) !== $source ) {
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
