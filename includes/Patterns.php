<?php
/**
 * Block pattern registration.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

namespace RAN\EmailOctopusJetpackForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers form starter patterns.
 */
final class Patterns {
	/**
	 * Pattern slug.
	 */
	const CONTACT_FORM_PATTERN = 'ran-emailoctopus-jetpack-forms/contact-form';

	/**
	 * Pattern category owned by this plugin.
	 */
	const PATTERN_CATEGORY = 'ran-emailoctopus-jetpack-forms';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		if ( did_action( 'init' ) && ! doing_action( 'init' ) ) {
			self::register_patterns();
			return;
		}

		add_action( 'init', array( __CLASS__, 'register_patterns' ), 20 );
	}

	/**
	 * Register form pattern.
	 *
	 * @return void
	 */
	public static function register_patterns() {
		register_block_pattern_category(
			self::PATTERN_CATEGORY,
			array(
				'label' => __( 'RAN EmailOctopus Forms', 'ran-emailoctopus-jetpack-forms' ),
			)
		);

		if ( \WP_Block_Patterns_Registry::get_instance()->is_registered( self::CONTACT_FORM_PATTERN ) ) {
			unregister_block_pattern( self::CONTACT_FORM_PATTERN );
		}

		register_block_pattern(
			self::CONTACT_FORM_PATTERN,
			array(
				'title'         => __( 'Contact Newsletter Form', 'ran-emailoctopus-jetpack-forms' ),
				'description'   => __( 'Jetpack contact form with name, email, message, and newsletter opt-in checkbox.', 'ran-emailoctopus-jetpack-forms' ),
				'categories'    => array( self::PATTERN_CATEGORY ),
				'keywords'      => array( 'contact', 'form', 'newsletter', 'emailoctopus' ),
				'content'       => self::get_contact_form_content(),
				'viewportWidth' => 720,
			)
		);
	}

	/**
	 * Get starter Jetpack contact form pattern content.
	 *
	 * @return string
	 */
	public static function get_contact_form_content() {
		$name_label        = wp_json_encode( __( 'Name', 'ran-emailoctopus-jetpack-forms' ) );
		$email_label       = wp_json_encode( __( 'Email', 'ran-emailoctopus-jetpack-forms' ) );
		$message_label     = wp_json_encode( __( 'Message', 'ran-emailoctopus-jetpack-forms' ) );
		$newsletter_label  = wp_json_encode( __( 'Join our newsletter!', 'ran-emailoctopus-jetpack-forms' ) );
		$label_placeholder = wp_json_encode( __( 'Add label...', 'ran-emailoctopus-jetpack-forms' ) );
		$submit_label      = esc_html__( 'Contact us', 'ran-emailoctopus-jetpack-forms' );

		return trim(
			sprintf(
				'<!-- wp:jetpack/contact-form {"jetpackCRM":false,"variationName":"default","salesforceData":{"organizationId":"","sendToSalesforce":false},"mailpoet":{"listId":null,"listName":null,"enabledForForm":false},"layout":{"type":"flex","flexWrap":"nowrap","orientation":"vertical","justifyContent":"left","verticalAlignment":"top"}} -->
	<div class="wp-block-jetpack-contact-form"><!-- wp:jetpack/field-name {"required":true,"fieldVariant":"name"} -->
	<div><!-- wp:jetpack/label {"label":%1$s} /-->

<!-- wp:jetpack/input /--></div>
<!-- /wp:jetpack/field-name -->

<!-- wp:jetpack/field-email {"required":true} -->
<div><!-- wp:jetpack/label {"label":%2$s} /-->

<!-- wp:jetpack/input /--></div>
<!-- /wp:jetpack/field-email -->

<!-- wp:jetpack/field-textarea -->
<div><!-- wp:jetpack/label {"label":%3$s} /-->

<!-- wp:jetpack/input {"type":"textarea"} /--></div>
<!-- /wp:jetpack/field-textarea -->

<!-- wp:jetpack/field-checkbox {"className":"is-style-list"} -->
<div><!-- wp:jetpack/option {"placeholder":%4$s,"label":%5$s,"isStandalone":true} /--></div>
<!-- /wp:jetpack/field-checkbox -->

<!-- wp:button {"tagName":"button","type":"submit"} -->
<div class="wp-block-button"><button type="submit" class="wp-block-button__link wp-element-button">%6$s</button></div>
<!-- /wp:button --></div>
				<!-- /wp:jetpack/contact-form -->',
				$name_label,
				$email_label,
				$message_label,
				$label_placeholder,
				$newsletter_label,
				$submit_label
			)
		);
	}
}
