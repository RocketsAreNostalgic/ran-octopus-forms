<?php
/**
 * Integration coverage for configuration and target-form ownership.
 *
 * @package RAN_Octopus_Forms
 */

use RAN\OctopusForms\JetpackForms;
use RAN\OctopusForms\Patterns;
use RAN\OctopusForms\Settings;
use RAN\OctopusForms\Turnstile;

/**
 * Ensure a fresh installation and upgraded installation stay isolated.
 */
class RAN_Octopus_Forms_Settings_Test extends WP_UnitTestCase {
	/**
	 * Reset persisted integration state.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		delete_option( Settings::OPTION_NAME );
		delete_option( Settings::LEGACY_OPTION_NAME );
		delete_option( Settings::VERSION_OPTION );
		$GLOBALS['wp_settings_errors'] = array();
		$_POST                         = array();
	}

	/**
	 * New installations must not use site-specific page paths.
	 *
	 * @return void
	 */
	public function test_new_install_has_no_page_or_success_url_default() {
		$this->assertSame( 0, Settings::get_contact_page_id() );
		$this->assertSame( '', Settings::get_success_url() );
	}

	/**
	 * A configured EmailOctopus destination warns about unresolved newsletter sources.
	 *
	 * @dataProvider unresolved_newsletter_source_provider
	 *
	 * @param string $newsletter_source Submitted newsletter source key.
	 * @return void
	 */
	public function test_sanitize_warns_and_retains_an_unresolved_newsletter_source( $newsletter_source ) {
		$contact_page_id = $this->create_contact_page_with_default_fields();
		$settings        = Settings::sanitize( $this->get_configured_source_settings_input( $contact_page_id, $newsletter_source ) );
		$errors          = get_settings_errors( Settings::OPTION_NAME );

		$this->assertSame( $newsletter_source, $settings['newsletter_source'] );
		$this->assertCount( 1, $errors );
		$this->assertSame( 'ran_octopus_forms_invalid_newsletter_source', $errors[0]['code'] );
		$this->assertSame( 'warning', $errors[0]['type'] );
	}

	/**
	 * A configured destination with valid email and newsletter fields needs no source warning.
	 *
	 * @return void
	 */
	public function test_sanitize_accepts_a_valid_email_and_newsletter_source_pair() {
		$contact_page_id = $this->create_contact_page_with_default_fields();
		$settings        = Settings::sanitize( $this->get_configured_source_settings_input( $contact_page_id, 'join_our_newsletter' ) );

		$this->assertSame( 'email', $settings['emailoctopus_email_source'] );
		$this->assertSame( 'join_our_newsletter', $settings['newsletter_source'] );
		$this->assertSame( array(), get_settings_errors( Settings::OPTION_NAME ) );
	}

	/**
	 * Newsletter sources that do not identify the current checkbox field.
	 *
	 * @return array<string,array{string}>
	 */
	public function unresolved_newsletter_source_provider() {
		return array(
			'blank source' => array( '' ),
			'stale source' => array( 'former_newsletter_opt_in' ),
		);
	}

	/**
	 * The starter pattern always identifies its one intended Jetpack form.
	 *
	 * @return void
	 */
	public function test_pattern_marks_its_jetpack_form() {
		$blocks = parse_blocks( Patterns::get_contact_form_content() );

		$this->assertCount( 1, $blocks );
		$this->assertTrue( Settings::is_target_contact_form_block( $blocks[0] ) );
	}

	/**
	 * A legacy page with exactly one form is upgraded to explicit ownership.
	 *
	 * @return void
	 */
	public function test_upgrade_marks_a_single_existing_contact_form() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => 'contact-us',
				'post_content' => '<!-- wp:jetpack/contact-form {} --><div class="wp-block-jetpack-contact-form"></div><!-- /wp:jetpack/contact-form -->',
			)
		);

		update_option(
			Settings::OPTION_NAME,
			array(
				'contact_page_id' => $page_id,
			)
		);

		Settings::upgrade();

		$this->assertTrue( Settings::has_single_contact_form() );
		$this->assertStringContainsString( Settings::TARGET_FORM_CLASS, (string) get_post_field( 'post_content', $page_id ) );
	}

	/**
	 * A legacy page with multiple forms must be left for an administrator.
	 *
	 * @return void
	 */
	public function test_upgrade_does_not_mark_an_ambiguous_contact_page() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:jetpack/contact-form {} --><div></div><!-- /wp:jetpack/contact-form --><!-- wp:jetpack/contact-form {} --><div></div><!-- /wp:jetpack/contact-form -->',
			)
		);

		update_option( Settings::OPTION_NAME, array( 'contact_page_id' => $page_id ) );
		Settings::upgrade();

		$this->assertFalse( Settings::has_target_contact_form() );
		$this->assertStringNotContainsString( Settings::TARGET_FORM_CLASS, (string) get_post_field( 'post_content', $page_id ) );
	}

	/**
	 * Settings cannot be pointed at a page without a Jetpack contact form.
	 *
	 * @return void
	 */
	public function test_sanitize_rejects_contact_page_without_a_jetpack_form() {
		$current_page_id          = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => Patterns::get_contact_form_content(),
			)
		);
		$invalid_page_id          = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		$current                  = array_merge(
			Settings::get_defaults(),
			array(
				'contact_page_id' => $current_page_id,
			)
		);
		$input                    = $current;
		$input['contact_page_id'] = $invalid_page_id;

		update_option( Settings::OPTION_NAME, $current );

		$this->assertSame( $current, Settings::sanitize( $input ) );
		$this->assertSame( 'ran_octopus_forms_invalid_contact_page', get_settings_errors( Settings::OPTION_NAME )[0]['code'] );
	}

	/**
	 * Settings cannot be pointed at a page with multiple Jetpack contact forms.
	 *
	 * @return void
	 */
	public function test_sanitize_rejects_contact_page_with_multiple_jetpack_forms() {
		$current_page_id          = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => Patterns::get_contact_form_content(),
			)
		);
		$invalid_page_id          = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:jetpack/contact-form {} --><div></div><!-- /wp:jetpack/contact-form --><!-- wp:jetpack/contact-form {} --><div></div><!-- /wp:jetpack/contact-form -->',
			)
		);
		$current                  = array_merge(
			Settings::get_defaults(),
			array(
				'contact_page_id' => $current_page_id,
			)
		);
		$input                    = $current;
		$input['contact_page_id'] = $invalid_page_id;

		update_option( Settings::OPTION_NAME, $current );

		$this->assertSame( $current, Settings::sanitize( $input ) );
		$this->assertSame( 'ran_octopus_forms_invalid_contact_page', get_settings_errors( Settings::OPTION_NAME )[0]['code'] );
	}

	/**
	 * A marker nonce prevents neighbouring Jetpack forms from receiving RAN hooks.
	 *
	 * @return void
	 */
	public function test_target_submission_requires_the_ran_marker_nonce() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => Patterns::get_contact_form_content(),
			)
		);

		update_option( Settings::OPTION_NAME, array( 'contact_page_id' => $page_id ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$_POST = array( 'contact-form-id' => (string) $page_id );
		$this->assertFalse( JetpackForms::is_target_submission() );

		$_POST['ran_octopus_forms_target'] = wp_create_nonce( 'ran_octopus_forms_target_' . $page_id );
		$this->assertTrue( JetpackForms::is_target_submission() );
		$this->assertFalse( JetpackForms::disable_ajax_for_contact_form( true ) );
	}

	/**
	 * Optional Turnstile must be added to only the marked form's rendered HTML.
	 *
	 * @return void
	 */
	public function test_turnstile_widget_is_scoped_to_the_marked_form() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => Patterns::get_contact_form_content(),
			)
		);

		update_option(
			Settings::OPTION_NAME,
			array(
				'contact_page_id'      => $page_id,
				'turnstile_enabled'    => 1,
				'turnstile_site_key'   => 'site-key',
				'turnstile_secret_key' => 'secret-key',
			)
		);

		$this->go_to( get_permalink( $page_id ) );

		$marked_html = '<form class="' . Settings::TARGET_FORM_CLASS . '"><div class="wp-block-button"></div></form>';
		$other_html  = '<form><div class="wp-block-button"></div></form>';

		$this->assertStringContainsString( 'cf-turnstile', Turnstile::append_widget( $marked_html ) );
		$this->assertSame( $other_html, Turnstile::append_widget( $other_html ) );
	}

	/**
	 * Create a contact page with the plugin's valid email and newsletter fields.
	 *
	 * @return int
	 */
	private function create_contact_page_with_default_fields() {
		return self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => Patterns::get_contact_form_content(),
			)
		);
	}

	/**
	 * Build valid destination settings with a caller-controlled newsletter source.
	 *
	 * @param int    $contact_page_id   Contact page ID.
	 * @param string $newsletter_source Submitted newsletter source key.
	 * @return array<string,mixed>
	 */
	private function get_configured_source_settings_input( $contact_page_id, $newsletter_source ) {
		return array_merge(
			Settings::get_defaults(),
			array(
				'contact_page_id'           => $contact_page_id,
				'emailoctopus_destination'  => 'list:newsletter-list',
				'emailoctopus_email_source' => 'email',
				'newsletter_source'         => $newsletter_source,
			)
		);
	}
}
