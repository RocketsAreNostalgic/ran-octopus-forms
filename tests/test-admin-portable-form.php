<?php
/**
 * Admin coverage for portable saved-form selection.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

use RAN\EmailOctopusJetpackForms\Admin;
use RAN\EmailOctopusJetpackForms\Settings;

/**
 * Ensure the saved form is primary while legacy page configuration is retained.
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
	 * Portable capability exposes the saved form ahead of fallback page fields.
	 *
	 * @return void
	 */
	public function test_saved_form_selector_is_primary_and_mapping_uses_its_fields() {
		$form_id = $this->create_saved_form( 'Portable address', 'Portable opt-in' );
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => $this->get_reference_content( $form_id ),
			)
		);

		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::get_defaults(),
				array(
					'contact_page_id'           => $page_id,
					'target_form_id'            => $form_id,
					'emailoctopus_email_source' => 'portable_address',
					'newsletter_source'         => 'portable_opt_in',
				)
			)
		);

		$markup = $this->render_settings_page();

		$this->assertStringContainsString( 'Portable saved-form mode is active', $markup );
		$this->assertStringContainsString( 'name="ran_emailoctopus_jetpack_forms_settings[target_form_id]"', $markup );
		$this->assertMatchesRegularExpression( '#<option value="' . $form_id . '"[^>]*selected[^>]*>Portable form \(\#' . $form_id . '\)</option>#', $markup );
		$this->assertStringContainsString( 'Legacy contact page fallback', $markup );
		$this->assertLessThan(
			strpos( $markup, 'ran-emailoctopus-jetpack-forms-contact-page' ),
			strpos( $markup, 'ran-emailoctopus-jetpack-forms-target-form' )
		);
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
			array_merge( Settings::get_defaults(), array( 'target_form_id' => $form_id ) )
		);

		$markup = $this->render_settings_page();

		$this->assertStringContainsString( 'EmailOctopus routing is paused', $markup );
		$this->assertStringContainsString( 'not published', $markup );
		$this->assertStringContainsString( 'Unavailable saved form (#' . $form_id . ')', $markup );
		$this->assertStringContainsString( 'EmailOctopus side effects are paused', $markup );
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
	 * @return int
	 */
	private function create_saved_form( $email_label, $newsletter_label, $status = 'publish' ) {
		return self::factory()->post->create(
			array(
				'post_type'    => 'jetpack_form',
				'post_status'  => $status,
				'post_title'   => 'Portable form',
				'post_content' => '<!-- wp:jetpack/contact-form --><div class="wp-block-jetpack-contact-form">'
					. '<!-- wp:jetpack/field-email --><div><!-- wp:jetpack/label {"label":"' . esc_attr( $email_label ) . '"} /--></div><!-- /wp:jetpack/field-email -->'
					. '<!-- wp:jetpack/field-checkbox --><div><!-- wp:jetpack/option {"label":"' . esc_attr( $newsletter_label ) . '"} /--></div><!-- /wp:jetpack/field-checkbox -->'
					. '</div><!-- /wp:jetpack/contact-form -->',
			)
		);
	}

	/**
	 * @param int $form_id Saved form ID.
	 * @return string
	 */
	private function get_reference_content( $form_id ) {
		return '<!-- wp:jetpack/contact-form {"ref":' . absint( $form_id ) . ',"className":"' . Settings::TARGET_FORM_CLASS . '"} /-->';
	}
}
