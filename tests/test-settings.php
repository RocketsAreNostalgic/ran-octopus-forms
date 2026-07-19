<?php
/**
 * Integration coverage for saved-form configuration.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

use RAN\EmailOctopusJetpackForms\Settings;

/**
 * Ensure settings store a canonical saved Jetpack form collection.
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
	}

	/**
	 * New installations have no target or site-specific route defaults.
	 *
	 * @return void
	 */
	public function test_new_install_has_no_target_or_success_url_default() {
		$this->assertSame( array(), Settings::get_target_form_ids() );
		$this->assertSame( '', Settings::get_success_url() );
		$this->assertArrayNotHasKey( 'contact_page_id', Settings::get_all() );
	}

	/**
	 * The Option 1 scalar migrates once and is removed from storage.
	 *
	 * @return void
	 */
	public function test_scalar_target_migrates_once_to_collection_and_is_removed() {
		update_option(
			Settings::OPTION_NAME,
			array(
				'target_form_id'         => 6243,
				'emailoctopus_list_id'   => 'newsletter-list',
				'emailoctopus_form_id'   => '',
				'emailoctopus_field_map' => array(),
				'newsletter_source'      => '',
				'success_page_id'        => 0,
			)
		);

		$this->assertSame( array( 6243 ), Settings::get_target_form_ids() );

		$stored = get_option( Settings::OPTION_NAME );
		$this->assertSame( array( 6243 ), $stored['target_form_ids'] );
		$this->assertArrayNotHasKey( 'target_form_id', $stored );

		$stored['target_form_ids'] = array( 7002, 7001 );
		$stored['target_form_id']  = 9999;
		update_option( Settings::OPTION_NAME, $stored );

		$this->assertSame( array( 7001, 7002 ), Settings::get_target_form_ids() );
		$this->assertArrayNotHasKey( 'target_form_id', get_option( Settings::OPTION_NAME ) );
	}

	/**
	 * Collections normalize to unique ascending positive integer IDs.
	 *
	 * @return void
	 */
	public function test_target_collection_normalizes_deduplicates_and_sorts() {
		$this->assertSame(
			array( 3, 8, 12 ),
			Settings::normalize_target_form_ids( array( '12', 3, 8, '003', 12, 0, -2, '1.5', 'no', true, null ) )
		);
	}

	/**
	 * Rebrand migration copies EmailOctopus settings but no page ownership.
	 *
	 * @return void
	 */
	public function test_rebrand_migration_excludes_page_and_turnstile_settings() {
		$legacy_settings = array(
			'contact_page_id'      => 42,
			'emailoctopus_list_id' => 'newsletter-list',
			'turnstile_enabled'    => 1,
			'turnstile_site_key'   => 'legacy-site-key',
			'turnstile_secret_key' => 'legacy-secret-key',
		);

		update_option( Settings::PREVIOUS_OPTION_NAME, $legacy_settings );
		$migrated = Settings::get_all();

		$this->assertSame( 'newsletter-list', $migrated['emailoctopus_list_id'] );
		$this->assertArrayNotHasKey( 'contact_page_id', $migrated );
		$this->assertArrayNotHasKey( 'turnstile_enabled', $migrated );
		$this->assertArrayNotHasKey( 'turnstile_site_key', $migrated );
		$this->assertArrayNotHasKey( 'turnstile_secret_key', $migrated );
		$this->assertSame( $legacy_settings, get_option( Settings::PREVIOUS_OPTION_NAME ) );
	}

	/**
	 * Obsolete page keys in an existing option are ignored.
	 *
	 * @return void
	 */
	public function test_existing_contact_page_key_is_not_exposed() {
		update_option(
			Settings::OPTION_NAME,
			array(
				'contact_page_id'      => 42,
				'emailoctopus_list_id' => 'newsletter-list',
			)
		);
		Settings::upgrade();

		$settings = Settings::get_all();

		$this->assertArrayNotHasKey( 'contact_page_id', $settings );
		$this->assertArrayNotHasKey( 'contact_page_id', get_option( Settings::OPTION_NAME ) );
		$this->assertSame( 'newsletter-list', $settings['emailoctopus_list_id'] );
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
	 * A saved form is mandatory and an absent target remains visibly invalid.
	 *
	 * @return void
	 */
	public function test_sanitize_requires_a_saved_form_target() {
		$settings = Settings::sanitize( Settings::get_defaults() );
		$errors   = get_settings_errors( Settings::OPTION_NAME );

		$this->assertSame( array(), $settings['target_form_ids'] );
		$this->assertSame( 'ran_emailoctopus_target_required', $errors[0]['code'] );
		$this->assertSame( 'error', $errors[0]['type'] );
	}

	/**
	 * The hidden zero emitted by the checkbox list permits intentional clear-all.
	 *
	 * @return void
	 */
	public function test_sanitize_can_clear_all_selected_forms() {
		$form_id = $this->create_saved_form();
		update_option(
			Settings::OPTION_NAME,
			array_merge( Settings::get_defaults(), array( 'target_form_ids' => array( $form_id ) ) )
		);

		$settings = Settings::sanitize( array( 'target_form_ids' => array( '0' ) ) );
		$errors   = get_settings_errors( Settings::OPTION_NAME );

		$this->assertSame( array(), $settings['target_form_ids'] );
		$this->assertSame( 'ran_emailoctopus_target_required', $errors[0]['code'] );
	}

	/**
	 * Invalid saved IDs remain stored for diagnostics but fail validation.
	 *
	 * @return void
	 */
	public function test_sanitize_retains_an_invalid_target_for_diagnostics() {
		$input                    = Settings::get_defaults();
		$input['target_form_ids'] = array( 999999 );
		$settings                 = Settings::sanitize( $input );
		$errors                   = get_settings_errors( Settings::OPTION_NAME );

		$this->assertSame( array( 999999 ), $settings['target_form_ids'] );
		$this->assertSame( 'ran_emailoctopus_target_invalid', $errors[0]['code'] );
		$this->assertSame( 'error', $errors[0]['type'] );
	}

	/**
	 * A configured destination warns about an unresolved newsletter source.
	 *
	 * @dataProvider unresolved_newsletter_source_provider
	 *
	 * @param string $newsletter_source Submitted newsletter source key.
	 * @return void
	 */
	public function test_sanitize_warns_and_retains_an_unresolved_newsletter_source( $newsletter_source ) {
		$form_id  = $this->create_saved_form();
		$settings = Settings::sanitize( $this->get_configured_source_settings_input( $form_id, $newsletter_source ) );
		$errors   = get_settings_errors( Settings::OPTION_NAME );

		$this->assertSame( $newsletter_source, $settings['newsletter_source'] );
		$this->assertCount( 1, $errors );
		$this->assertSame( 'ran_octopus_forms_invalid_newsletter_source', $errors[0]['code'] );
		$this->assertSame( 'warning', $errors[0]['type'] );
	}

	/**
	 * Valid saved-form source mappings need no warning.
	 *
	 * @return void
	 */
	public function test_sanitize_accepts_valid_saved_form_sources() {
		$form_id  = $this->create_saved_form();
		$settings = Settings::sanitize( $this->get_configured_source_settings_input( $form_id, 'join_our_newsletter' ) );

		$this->assertSame( 'email', $settings['emailoctopus_email_source'] );
		$this->assertSame( 'join_our_newsletter', $settings['newsletter_source'] );
		$this->assertSame( array(), get_settings_errors( Settings::OPTION_NAME ) );
	}

	/**
	 * @return array<string,array{string}>
	 */
	public function unresolved_newsletter_source_provider() {
		return array(
			'blank source' => array( '' ),
			'stale source' => array( 'former_newsletter_opt_in' ),
		);
	}

	/**
	 * Create a valid saved Jetpack form fixture.
	 *
	 * @return int
	 */
	private function create_saved_form() {
		return self::factory()->post->create(
			array(
				'post_type'    => 'jetpack_form',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:jetpack/contact-form --><div class="wp-block-jetpack-contact-form">'
					. '<!-- wp:jetpack/field-email --><div><!-- wp:jetpack/label {"label":"Email"} /--></div><!-- /wp:jetpack/field-email -->'
					. '<!-- wp:jetpack/field-checkbox --><div><!-- wp:jetpack/label {"label":"Join our newsletter"} /--></div><!-- /wp:jetpack/field-checkbox -->'
					. '</div><!-- /wp:jetpack/contact-form -->',
			)
		);
	}

	/**
	 * Build destination settings with a caller-controlled newsletter source.
	 *
	 * @param int    $form_id           Saved Jetpack form ID.
	 * @param string $newsletter_source Submitted newsletter source key.
	 * @return array<string,mixed>
	 */
	private function get_configured_source_settings_input( $form_id, $newsletter_source ) {
		return array_merge(
			Settings::get_defaults(),
			array(
				'target_form_ids'           => array( $form_id ),
				'emailoctopus_destination'  => 'list:newsletter-list',
				'emailoctopus_email_source' => 'email',
				'newsletter_source'         => $newsletter_source,
			)
		);
	}
}
