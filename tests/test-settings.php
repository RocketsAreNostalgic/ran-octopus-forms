<?php
/**
 * Integration coverage for configuration and target-form ownership.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

use RAN\EmailOctopusJetpackForms\JetpackForms;
use RAN\EmailOctopusJetpackForms\Patterns;
use RAN\EmailOctopusJetpackForms\Settings;

/**
 * Ensure a fresh installation and upgraded installation stay isolated.
 */
class RAN_EmailOctopus_Jetpack_Forms_Settings_Test extends WP_UnitTestCase {
	/**
	 * Reset persisted integration state.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		delete_option( Settings::OPTION_NAME );
		delete_option( Settings::PREVIOUS_OPTION_NAME );
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
	 * The rebranded connector copies only its own fields from the bundled option.
	 *
	 * @return void
	 */
	public function test_rebrand_migration_excludes_turnstile_settings_and_keeps_source() {
		$legacy_settings = array(
			'contact_page_id'      => 42,
			'emailoctopus_list_id' => 'newsletter-list',
			'turnstile_enabled'    => 1,
			'turnstile_site_key'   => 'legacy-site-key',
			'turnstile_secret_key' => 'legacy-secret-key',
		);

		update_option( Settings::PREVIOUS_OPTION_NAME, $legacy_settings );
		$migrated = Settings::get_all();

		$this->assertSame( 42, $migrated['contact_page_id'] );
		$this->assertSame( 'newsletter-list', $migrated['emailoctopus_list_id'] );
		$this->assertArrayNotHasKey( 'turnstile_enabled', $migrated );
		$this->assertArrayNotHasKey( 'turnstile_site_key', $migrated );
		$this->assertArrayNotHasKey( 'turnstile_secret_key', $migrated );
		$this->assertSame( $legacy_settings, get_option( Settings::PREVIOUS_OPTION_NAME ) );
	}

	/**
	 * New filters run after the previous hook alias.
	 *
	 * @return void
	 */
	public function test_emailoctopus_list_filters_keep_the_legacy_alias() {
		update_option( Settings::OPTION_NAME, array( 'emailoctopus_list_id' => 'saved-list' ) );
		$legacy_filter = static function ( $list_id ) {
			return $list_id . '-legacy';
		};
		$new_filter    = static function ( $list_id ) {
			return $list_id . '-new';
		};

		add_filter( 'ran_octopus_forms_emailoctopus_list_id', $legacy_filter );
		add_filter( 'ran_emailoctopus_jetpack_forms_emailoctopus_list_id', $new_filter );
		$this->setExpectedDeprecated( 'ran_octopus_forms_emailoctopus_list_id' );

		$this->assertSame( 'saved-list-legacy-new', Settings::get_emailoctopus_list_id() );

		remove_filter( 'ran_octopus_forms_emailoctopus_list_id', $legacy_filter );
		remove_filter( 'ran_emailoctopus_jetpack_forms_emailoctopus_list_id', $new_filter );
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
	 * An inline legacy form is never rewritten during the saved-form migration.
	 *
	 * @return void
	 */
	public function test_upgrade_leaves_a_single_inline_contact_form_unchanged() {
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

		$original_content = (string) get_post_field( 'post_content', $page_id );
		Settings::upgrade();

		$this->assertSame( 0, Settings::get_target_form_id() );
		$this->assertFalse( Settings::has_target_contact_form() );
		$this->assertSame( $original_content, (string) get_post_field( 'post_content', $page_id ) );
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
