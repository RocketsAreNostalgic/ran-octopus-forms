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
		$_POST = array();
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
}
