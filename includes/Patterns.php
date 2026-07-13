<?php
/**
 * Block pattern registration.
 *
 * @package RAN_Octopus_Forms
 */

namespace RAN\OctopusForms;

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
	const CONTACT_FORM_PATTERN = 'ran-octopus-forms/contact-form';

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
		if ( \WP_Block_Patterns_Registry::get_instance()->is_registered( self::CONTACT_FORM_PATTERN ) ) {
			unregister_block_pattern( self::CONTACT_FORM_PATTERN );
		}

		register_block_pattern(
			self::CONTACT_FORM_PATTERN,
			array(
				'title'         => __( 'Contact Newsletter Form', 'ran-octopus-forms' ),
				'description'   => __( 'Jetpack contact form with name, email, message, and newsletter opt-in checkbox.', 'ran-octopus-forms' ),
				'categories'    => array( 'pns-layout' ),
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
		return trim(
			'<!-- wp:jetpack/contact-form {"jetpackCRM":false,"variationName":"default","salesforceData":{"organizationId":"","sendToSalesforce":false},"mailpoet":{"listId":null,"listName":null,"enabledForForm":false},"layout":{"type":"flex","flexWrap":"nowrap","orientation":"vertical","justifyContent":"left","verticalAlignment":"top"}} -->
<div class="wp-block-jetpack-contact-form"><!-- wp:jetpack/field-name {"required":true,"fieldVariant":"name"} -->
<div><!-- wp:jetpack/label {"label":"Name"} /-->

<!-- wp:jetpack/input /--></div>
<!-- /wp:jetpack/field-name -->

<!-- wp:jetpack/field-email {"required":true} -->
<div><!-- wp:jetpack/label {"label":"Email"} /-->

<!-- wp:jetpack/input /--></div>
<!-- /wp:jetpack/field-email -->

<!-- wp:jetpack/field-textarea -->
<div><!-- wp:jetpack/label {"label":"Message"} /-->

<!-- wp:jetpack/input {"type":"textarea"} /--></div>
<!-- /wp:jetpack/field-textarea -->

<!-- wp:jetpack/field-checkbox {"className":"is-style-list"} -->
<div><!-- wp:jetpack/option {"placeholder":"Add label...","label":"Join our newsletter!","isStandalone":true} /--></div>
<!-- /wp:jetpack/field-checkbox -->

<!-- wp:button {"tagName":"button","type":"submit"} -->
<div class="wp-block-button"><button type="submit" class="wp-block-button__link wp-element-button">Contact us</button></div>
<!-- /wp:button --></div>
<!-- /wp:jetpack/contact-form -->'
		);
	}
}
