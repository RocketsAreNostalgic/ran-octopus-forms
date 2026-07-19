<?php
/**
 * Admin coverage for portable saved-form selection.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

use RAN\EmailOctopusJetpackForms\Admin;
use RAN\EmailOctopusJetpackForms\Settings;

/**
 * Ensure only explicitly selected saved forms are EmailOctopus targets.
 */
class RAN_EmailOctopus_Jetpack_Forms_Admin_Portable_Form_Test extends WP_UnitTestCase {
	/**
	 * Reset settings and provide an administrator.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		delete_option( Settings::OPTION_NAME );
		delete_option( 'emailoctopus_api_key' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * The settings page exposes collection targeting and shared fields.
	 *
	 * @return void
	 */
	public function test_saved_form_selector_is_primary_and_mapping_uses_its_fields() {
		$form_id        = $this->create_saved_form( 'Portable address', 'Portable opt-in' );
		$second_form_id = $this->create_saved_form( 'Portable address', 'Portable opt-in', 'publish', 'Second portable form' );

		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::get_defaults(),
				array(
					'target_form_ids'           => array( $form_id, $second_form_id ),
					'emailoctopus_email_source' => 'portable_address',
					'newsletter_source'         => 'portable_opt_in',
				)
			)
		);

		$markup = $this->render_settings_page();

		$this->assertStringContainsString( 'Saved-form routing is active for 2 of 2 selected form(s)', $markup );
		$this->assertStringContainsString( 'name="ran_emailoctopus_jetpack_forms_settings[target_form_ids][]" value="0"', $markup );
		$this->assertMatchesRegularExpression( '#type="checkbox"[^>]*value="' . $form_id . '"[^>]*checked#', $markup );
		$this->assertMatchesRegularExpression( '#type="checkbox"[^>]*value="' . $second_form_id . '"[^>]*checked#', $markup );
		$this->assertStringNotContainsString( 'contact_page_id', $markup );
		$this->assertStringNotContainsString( 'ran-emailoctopus-jetpack-forms-contact-page', $markup );
		$this->assertStringContainsString( 'value="portable_address"', $markup );
		$this->assertStringContainsString( 'value="portable_opt_in"', $markup );
	}

	/**
	 * An unavailable target remains visible so the administrator can repair it.
	 *
	 * @return void
	 */
	public function test_draft_saved_form_is_preserved_with_actionable_mode_warning() {
		$form_id = $this->create_saved_form( 'Email', 'Opt in', 'draft' );

		update_option(
			Settings::OPTION_NAME,
			array_merge( Settings::get_defaults(), array( 'target_form_ids' => array( $form_id ) ) )
		);

		$markup = $this->render_settings_page();

		$this->assertStringContainsString( 'EmailOctopus routing is disabled', $markup );
		$this->assertStringContainsString( 'not published', $markup );
		$this->assertStringContainsString( 'Unavailable saved form (#' . $form_id . ')', $markup );
		$this->assertStringContainsString( 'selected for diagnostics', $markup );
	}

	/**
	 * @return string
	 */
	private function render_settings_page() {
		ob_start();
		Admin::render_page();

		return (string) ob_get_clean();
	}

	/**
	 * @param string $email_label      Email field label.
	 * @param string $newsletter_label Newsletter field label.
	 * @param string $status           Post status.
	 * @param string $title            Saved form title.
	 * @return int
	 */
	private function create_saved_form( $email_label, $newsletter_label, $status = 'publish', $title = 'Portable form' ) {
		return self::factory()->post->create(
			array(
				'post_type'    => 'jetpack_form',
				'post_status'  => $status,
				'post_title'   => $title,
				'post_content' => '<!-- wp:jetpack/contact-form --><div class="wp-block-jetpack-contact-form">'
					. '<!-- wp:jetpack/field-email --><div><!-- wp:jetpack/label {"label":"' . esc_attr( $email_label ) . '"} /--></div><!-- /wp:jetpack/field-email -->'
					. '<!-- wp:jetpack/field-checkbox --><div><!-- wp:jetpack/option {"label":"' . esc_attr( $newsletter_label ) . '"} /--></div><!-- /wp:jetpack/field-checkbox -->'
					. '</div><!-- /wp:jetpack/contact-form -->',
			)
		);
	}
}
