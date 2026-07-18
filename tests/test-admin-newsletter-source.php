<?php
/**
 * Integration coverage for newsletter opt-in source settings.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

use RAN\EmailOctopusJetpackForms\Admin;
use RAN\EmailOctopusJetpackForms\Settings;

/**
 * Ensure newsletter opt-in sources are limited to affirmative controls.
 */
class RAN_EmailOctopus_Jetpack_Forms_Admin_Newsletter_Source_Test extends WP_UnitTestCase {
	/**
	 * Reset persisted integration state.
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
	 * The newsletter source selector only exposes affirmative opt-in fields.
	 *
	 * @return void
	 */
	public function test_newsletter_source_selector_only_lists_supported_field_types() {
		$contact_page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => $this->get_contact_form_content(),
			)
		);

		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::get_defaults(),
				array(
					'contact_page_id'   => $contact_page_id,
					'newsletter_source' => 'checkbox_opt_in',
				)
			)
		);

		ob_start();
		Admin::render_page();
		$markup = (string) ob_get_clean();

		$this->assertMatchesRegularExpression(
			'#<option value="checkbox_opt_in"[^>]*>\s*Checkbox opt-in \(checkbox\)\s*</option>#',
			$markup
		);
		$newsletter_select = $this->get_newsletter_source_select_markup( $markup );

		$this->assertStringContainsString( 'checkbox_opt_in', $newsletter_select );
		$this->assertStringContainsString( 'consent_opt_in', $newsletter_select );
		$this->assertStringNotContainsString( 'textarea_opt_in', $newsletter_select );
		$this->assertStringNotContainsString( 'radio_opt_in', $newsletter_select );
		$this->assertStringNotContainsString( 'select_opt_in', $newsletter_select );
	}

	/**
	 * A single detected email and newsletter source are visibly selected without first saving them.
	 *
	 * @dataProvider single_newsletter_source_provider
	 *
	 * @param string $newsletter_field Newsletter source field block name.
	 * @param string $newsletter_label Newsletter source field label.
	 * @param string $newsletter_key   Expected normalized newsletter source key.
	 * @param string $option_label     Expected selector option label.
	 * @return void
	 */
	public function test_source_selectors_default_to_their_only_available_fields( $newsletter_field, $newsletter_label, $newsletter_key, $option_label ) {
		$contact_page_id = $this->create_contact_page(
			'<!-- wp:jetpack/field-email -->
<div><!-- wp:jetpack/label {"label":"Email address"} /--></div>
<!-- /wp:jetpack/field-email -->

' . $this->get_option_field_block( $newsletter_field, $newsletter_label )
		);

		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::get_defaults(),
				array(
					'contact_page_id'           => $contact_page_id,
					'emailoctopus_email_source' => '',
					'newsletter_source'         => '',
				)
			)
		);

		$markup = $this->render_settings_page();

		$this->assertMatchesRegularExpression(
			'#<option value="email_address"[^>]*selected[^>]*>\s*Email address \(email\)\s*</option>#',
			$this->get_email_source_select_markup( $markup )
		);
		$this->assertMatchesRegularExpression(
			'#<option value="' . preg_quote( $newsletter_key, '#' ) . '"[^>]*selected[^>]*>\s*' . preg_quote( $option_label, '#' ) . '\s*</option>#',
			$this->get_newsletter_source_select_markup( $markup )
		);
	}

	/**
	 * Ambiguous sources require an explicit saved choice instead of guessing.
	 *
	 * @return void
	 */
	public function test_source_selectors_render_a_hidden_placeholder_when_multiple_fields_are_available() {
		$contact_page_id = $this->create_contact_page(
			'<!-- wp:jetpack/field-email -->
<div><!-- wp:jetpack/label {"label":"Personal email"} /--></div>
<!-- /wp:jetpack/field-email -->

<!-- wp:jetpack/field-email -->
<div><!-- wp:jetpack/label {"label":"Work email"} /--></div>
<!-- /wp:jetpack/field-email -->

<!-- wp:jetpack/field-checkbox -->
<div><!-- wp:jetpack/option {"label":"Newsletter opt-in"} /--></div>
<!-- /wp:jetpack/field-checkbox -->

<!-- wp:jetpack/field-checkbox -->
<div><!-- wp:jetpack/option {"label":"Research updates"} /--></div>
<!-- /wp:jetpack/field-checkbox -->'
		);

		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::get_defaults(),
				array(
					'contact_page_id'           => $contact_page_id,
					'emailoctopus_email_source' => '',
					'newsletter_source'         => '',
				)
			)
		);

		$markup                 = $this->render_settings_page();
		$email_select           = $this->get_email_source_select_markup( $markup );
		$newsletter_select      = $this->get_newsletter_source_select_markup( $markup );
		$email_placeholder      = 'Choose an email source';
		$newsletter_placeholder = 'Choose a newsletter opt-in source';

		$this->assert_hidden_selected_placeholder( $email_placeholder, $email_select );
		$this->assert_hidden_selected_placeholder( $newsletter_placeholder, $newsletter_select );
	}

	/**
	 * A stale saved source preselects the sole current candidate without saving it.
	 *
	 * @dataProvider stale_source_provider
	 *
	 * @param string $select_id        Source selector ID.
	 * @param string $setting_key      Saved settings key.
	 * @param string $source           Stale source key.
	 * @param string $replacement      Expected current candidate key.
	 * @return void
	 */
	public function test_stale_saved_source_preselects_the_sole_current_candidate_with_an_adjacent_warning( $select_id, $setting_key, $source, $replacement ) {
		$contact_page_id = $this->create_contact_page(
			'<!-- wp:jetpack/field-email -->
<div><!-- wp:jetpack/label {"label":"Email address"} /--></div>
<!-- /wp:jetpack/field-email -->

<!-- wp:jetpack/field-checkbox -->
<div><!-- wp:jetpack/option {"label":"Newsletter opt-in"} /--></div>
<!-- /wp:jetpack/field-checkbox -->'
		);

		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::get_defaults(),
				array(
					'contact_page_id' => $contact_page_id,
					$setting_key      => $source,
				)
			)
		);

		$markup        = $this->render_settings_page();
		$source_select = $this->get_source_select_markup( $markup, $select_id );
		$source_field  = $this->get_source_field_markup( $markup, $select_id );

		$this->assertMatchesRegularExpression(
			'#<option value="' . preg_quote( $replacement, '#' ) . '"[^>]*selected[^>]*>#',
			$source_select
		);
		$this->assertStringNotContainsString( 'value="' . $source . '"', $source_select );
		$this->assertStringContainsString( str_replace( '_', ' ', $source ), $source_field );
		$this->assertStringContainsString( 'save settings to confirm this replacement', $source_field );
	}

	/**
	 * Newsletter source field types that should auto-select when they are unique.
	 *
	 * @return array<string,array{string,string,string,string}>
	 */
	public function single_newsletter_source_provider() {
		return array(
			'checkbox source' => array( 'checkbox', 'Newsletter checkbox', 'newsletter_checkbox', 'Newsletter checkbox (checkbox)' ),
			'consent source'  => array( 'consent', 'Newsletter consent', 'newsletter_consent', 'Newsletter consent (consent; submitting this form subscribes the visitor)' ),
		);
	}

	/**
	 * Persisted stale sources for both selector types.
	 *
	 * @return array<string,array{string,string,string,string}>
	 */
	public function stale_source_provider() {
		return array(
			'email source'      => array( 'ran-emailoctopus-jetpack-forms-emailoctopus-email-source', 'emailoctopus_email_source', 'former_email', 'email_address' ),
			'newsletter source' => array( 'ran-emailoctopus-jetpack-forms-newsletter-source', 'newsletter_source', 'former_newsletter_opt_in', 'newsletter_opt_in' ),
		);
	}

	/**
	 * Create a contact page containing the supplied Jetpack field blocks.
	 *
	 * @param string $fields Serialized Jetpack field blocks.
	 * @return int
	 */
	private function create_contact_page( $fields ) {
		return self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => $this->get_contact_form_with_fields( $fields ),
			)
		);
	}

	/**
	 * Render the settings page as an administrator.
	 *
	 * @return string
	 */
	private function render_settings_page() {
		ob_start();
		Admin::render_page();

		return (string) ob_get_clean();
	}

	/**
	 * Extract the email source select from the settings-page markup.
	 *
	 * @param string $markup Rendered settings-page markup.
	 * @return string
	 */
	private function get_email_source_select_markup( $markup ) {
		return $this->get_source_select_markup( $markup, 'ran-emailoctopus-jetpack-forms-emailoctopus-email-source' );
	}

	/**
	 * Extract the newsletter source select from the settings-page markup.
	 *
	 * @param string $markup Rendered settings-page markup.
	 * @return string
	 */
	private function get_newsletter_source_select_markup( $markup ) {
		return $this->get_source_select_markup( $markup, 'ran-emailoctopus-jetpack-forms-newsletter-source' );
	}

	/**
	 * Extract a configured source select from the settings-page markup.
	 *
	 * @param string $markup    Rendered settings-page markup.
	 * @param string $select_id Source selector ID.
	 * @return string
	 */
	private function get_source_select_markup( $markup, $select_id ) {
		$matched = preg_match(
			'#<select[^>]*id="' . preg_quote( $select_id, '#' ) . '"[^>]*>(.*?)</select>#s',
			$markup,
			$matches
		);

		$this->assertSame( 1, $matched );

		return $matches[1];
	}

	/**
	 * Extract the settings field containing a source selector and adjacent guidance.
	 *
	 * @param string $markup    Rendered settings-page markup.
	 * @param string $select_id Source selector ID.
	 * @return string
	 */
	private function get_source_field_markup( $markup, $select_id ) {
		$matched = preg_match(
			'#<div class="ran-emailoctopus-jetpack-forms-field">\s*<label for="' . preg_quote( $select_id, '#' ) . '">.*?</label>(.*?)</div>#s',
			$markup,
			$matches
		);

		$this->assertSame( 1, $matched );

		return $matches[1];
	}

	/**
	 * Assert that a placeholder is selected but cannot be submitted as a source.
	 *
	 * @param string $placeholder Placeholder label.
	 * @param string $markup      Source select markup.
	 * @return void
	 */
	private function assert_hidden_selected_placeholder( $placeholder, $markup ) {
		$this->assertMatchesRegularExpression(
			'#<option(?=[^>]*value="")(?=[^>]*disabled)(?=[^>]*hidden)(?=[^>]*selected)[^>]*>\s*' . preg_quote( $placeholder, '#' ) . '\s*</option>#',
			$markup
		);
	}

	/**
	 * Build a target form with supported and unsupported source field types.
	 *
	 * @return string
	 */
	private function get_contact_form_content() {
		return $this->get_contact_form_with_fields(
			$this->get_option_field_block( 'checkbox', 'Checkbox opt-in' ) .
			$this->get_option_field_block( 'consent', 'Consent opt-in' ) .
			'<!-- wp:jetpack/field-textarea -->
<div><!-- wp:jetpack/label {"label":"Textarea opt-in"} /--></div>
<!-- /wp:jetpack/field-textarea -->

' . $this->get_option_field_block( 'radio', 'Radio opt-in' ) .
			$this->get_option_field_block( 'select', 'Select opt-in' )
		);
	}

	/**
	 * Wrap Jetpack form field blocks in one contact-form block.
	 *
	 * @param string $fields Serialized Jetpack field blocks.
	 * @return string
	 */
	private function get_contact_form_with_fields( $fields ) {
		return '<!-- wp:jetpack/contact-form -->
<div>' . $fields . '</div>
<!-- /wp:jetpack/contact-form -->';
	}

	/**
	 * Build a Jetpack option-based field block.
	 *
	 * @param string $type  Jetpack field type.
	 * @param string $label Jetpack option label.
	 * @return string
	 */
	private function get_option_field_block( $type, $label ) {
		return sprintf(
			'<!-- wp:jetpack/field-%1$s -->
<div><!-- wp:jetpack/option {"label":%2$s} /--></div>
<!-- /wp:jetpack/field-%1$s -->

',
			esc_attr( $type ),
			wp_json_encode( $label )
		);
	}
}
