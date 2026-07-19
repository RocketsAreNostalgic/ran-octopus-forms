<?php
/**
 * Saved-form profile, resolver, and field-discovery coverage.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

use RAN\EmailOctopusJetpackForms\EmailOctopusFieldMapper;
use RAN\EmailOctopusJetpackForms\IntegrationResolver;
use RAN\EmailOctopusJetpackForms\Settings;

/**
 * Prove the integration is configured only by one saved Jetpack form.
 */
class RAN_EmailOctopus_Jetpack_Forms_Portable_Settings_Test extends WP_UnitTestCase {
	/**
	 * Reset integration state.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		delete_option( Settings::OPTION_NAME );
	}

	/**
	 * The profile exposes its stable ID, form collection, and filtered settings.
	 *
	 * @return void
	 */
	public function test_default_profile_exposes_collection_and_filtered_configuration() {
		$form_id = $this->create_saved_form();
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::get_defaults(),
				array(
					'target_form_id'       => $form_id,
					'emailoctopus_list_id' => 'newsletter-list',
				)
			)
		);

		$filter = static function ( $list_id ) {
			return $list_id . '-filtered';
		};
		add_filter( 'ran_emailoctopus_jetpack_forms_emailoctopus_list_id', $filter );
		$profile = IntegrationResolver::get_default_profile();
		remove_filter( 'ran_emailoctopus_jetpack_forms_emailoctopus_list_id', $filter );

		$this->assertSame( 'default', $profile->get_id() );
		$this->assertSame( array( $form_id ), $profile->get_form_ids() );
		$this->assertSame( $form_id, $profile->get_target_form_id() );
		$this->assertArrayNotHasKey( 'contact_page_id', $profile->get_configuration() );
		$this->assertSame( 'newsletter-list-filtered', $profile->get_configuration()['emailoctopus_list_id'] );
		$this->assertNull( IntegrationResolver::get_profile( 'unknown' ) );
	}

	/**
	 * Field discovery reads only the selected saved form.
	 *
	 * @return void
	 */
	public function test_field_candidates_come_from_selected_saved_form() {
		$form_id = $this->create_saved_form( 'Portable Email' );
		update_option(
			Settings::OPTION_NAME,
			array_merge( Settings::get_defaults(), array( 'target_form_id' => $form_id ) )
		);

		$this->assertSame( 'portable_email', EmailOctopusFieldMapper::get_source_fields()[0]['key'] );
		$this->assertTrue( IntegrationResolver::is_portable_available() );
	}

	/**
	 * No target is disabled rather than routed through a page fallback.
	 *
	 * @return void
	 */
	public function test_missing_target_disables_integration() {
		$this->assertSame( 'target_not_selected', IntegrationResolver::get_portability_reason() );
		$this->assertFalse( IntegrationResolver::is_portable_available() );
		$this->assertSame( array(), EmailOctopusFieldMapper::get_source_fields() );
	}

	/**
	 * Invalid saved-form structure disables the integration and its fields.
	 *
	 * @return void
	 */
	public function test_invalid_saved_form_disables_integration() {
		$form_id = self::factory()->post->create(
			array(
				'post_type'    => 'jetpack_form',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:jetpack/field-email --><div></div><!-- /wp:jetpack/field-email -->',
			)
		);
		update_option(
			Settings::OPTION_NAME,
			array_merge( Settings::get_defaults(), array( 'target_form_id' => $form_id ) )
		);

		$this->assertSame( 'target_invalid_structure', IntegrationResolver::get_portability_reason() );
		$this->assertFalse( IntegrationResolver::is_portable_available() );
		$this->assertSame( array(), EmailOctopusFieldMapper::get_source_fields() );
	}

	/**
	 * Create a published saved form fixture.
	 *
	 * @param string $email_label Email field label.
	 * @return int
	 */
	private function create_saved_form( $email_label = 'Email' ) {
		return self::factory()->post->create(
			array(
				'post_type'    => 'jetpack_form',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:jetpack/contact-form --><div class="wp-block-jetpack-contact-form">'
					. '<!-- wp:jetpack/field-email --><div><!-- wp:jetpack/label {"label":"' . esc_attr( $email_label ) . '"} /--></div><!-- /wp:jetpack/field-email -->'
					. '</div><!-- /wp:jetpack/contact-form -->',
			)
		);
	}
}
