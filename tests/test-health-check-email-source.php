<?php
/**
 * Integration coverage for Health Check email-source validation.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

use RAN\EmailOctopusJetpackForms\HealthCheck;
use RAN\EmailOctopusJetpackForms\Settings;

/**
 * Ensure Health Check validates email source fields by Jetpack block type.
 */
class RAN_EmailOctopus_Jetpack_Forms_Health_Check_Email_Source_Test extends WP_UnitTestCase {
	/**
	 * Reset persisted integration state.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		delete_option( Settings::OPTION_NAME );
	}

	/**
	 * A text field called Email is not a valid EmailOctopus email source.
	 *
	 * @return void
	 */
	public function test_health_check_rejects_a_text_field_named_email() {
		$this->configure_contact_page_and_email_source(
			'<!-- wp:jetpack/field-text -->
<div><!-- wp:jetpack/label {"label":"Email"} /--></div>
<!-- /wp:jetpack/field-text -->',
			'email'
		);

		$check = $this->get_email_source_mapping_check();

		$this->assertSame( 'error', $check['status'] );
		$this->assertStringContainsString( 'is not an email field', $check['message'] );
	}

	/**
	 * An email block remains valid even when its label does not mention email.
	 *
	 * @return void
	 */
	public function test_health_check_accepts_an_email_field_with_an_arbitrary_label() {
		$this->configure_contact_page_and_email_source(
			'<!-- wp:jetpack/field-email -->
<div><!-- wp:jetpack/label {"label":"Preferred address"} /--></div>
<!-- /wp:jetpack/field-email -->',
			'preferred_address'
		);

		$check = $this->get_email_source_mapping_check();

		$this->assertSame( 'pass', $check['status'] );
		$this->assertStringContainsString( 'Preferred address', $check['message'] );
	}

	/**
	 * Configure the selected email source against one contact form.
	 *
	 * @param string $field_block Serialized Jetpack field block.
	 * @param string $source_key  Selected normalized source key.
	 * @return void
	 */
	private function configure_contact_page_and_email_source( $field_block, $source_key ) {
		$contact_page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:jetpack/contact-form -->
<div>' . $field_block . '</div>
<!-- /wp:jetpack/contact-form -->',
			)
		);

		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::get_defaults(),
				array(
					'contact_page_id'           => $contact_page_id,
					'emailoctopus_email_source' => $source_key,
				)
			)
		);
	}

	/**
	 * Call the narrow Health Check row without unrelated external diagnostics.
	 *
	 * @return array<string,string>
	 */
	private function get_email_source_mapping_check() {
		$method = new ReflectionMethod( HealthCheck::class, 'check_email_source_mapping' );
		$method->setAccessible( true );
		$check = $method->invoke( null );

		$this->assertIsArray( $check );

		return $check;
	}
}
