<?php
/**
 * Saved-form migration, profile, and field-discovery coverage.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

use RAN\EmailOctopusJetpackForms\EmailOctopusFieldMapper;
use RAN\EmailOctopusJetpackForms\IntegrationResolver;
use RAN\EmailOctopusJetpackForms\Settings;

/**
 * Prove Option 1 settings remain portable and backward compatible.
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
		delete_option( Settings::PREVIOUS_OPTION_NAME );
		delete_option( Settings::LEGACY_OPTION_NAME );
		delete_option( Settings::VERSION_OPTION );
	}

	/**
	 * One marked saved-form reference migrates once without changing content.
	 *
	 * @return void
	 */
	public function test_migration_resolves_one_marked_saved_form_without_rewriting_content() {
		$form_id      = $this->create_saved_form();
		$page_id      = $this->create_reference_page( $form_id );
		$page_content = (string) get_post_field( 'post_content', $page_id );

		update_option( Settings::OPTION_NAME, array( 'contact_page_id' => $page_id ) );
		Settings::migrate_saved_form_target();

		$this->assertSame( $form_id, Settings::get_target_form_id() );
		$this->assertSame( $page_id, Settings::get_contact_page_id() );
		$this->assertSame( $page_content, (string) get_post_field( 'post_content', $page_id ) );

		$replacement_id = $this->create_saved_form();
		wp_update_post(
			array(
				'ID'           => $page_id,
				'post_content' => $this->get_reference_content( $replacement_id ),
			)
		);
		Settings::migrate_saved_form_target();

		$this->assertSame( $form_id, Settings::get_target_form_id() );
	}

	/**
	 * Inline, ambiguous, missing, and wrong-type references fail closed.
	 *
	 * @dataProvider unresolved_reference_provider
	 *
	 * @param string $scenario Migration scenario.
	 * @return void
	 */
	public function test_migration_stores_zero_when_reference_is_not_unambiguous_and_saved( $scenario ) {
		$form_id = $this->create_saved_form();

		if ( 'inline' === $scenario ) {
			$content = '<!-- wp:jetpack/contact-form {"className":"' . Settings::TARGET_FORM_CLASS . '"} --><div></div><!-- /wp:jetpack/contact-form -->';
		} elseif ( 'ambiguous' === $scenario ) {
			$content = $this->get_reference_content( $form_id ) . $this->get_reference_content( $this->create_saved_form() );
		} elseif ( 'missing' === $scenario ) {
			$content = $this->get_reference_content( 999999 );
		} else {
			$page_ref = self::factory()->post->create( array( 'post_type' => 'page' ) );
			$content  = $this->get_reference_content( $page_ref );
		}

		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => $content,
			)
		);

		update_option( Settings::OPTION_NAME, array( 'contact_page_id' => $page_id ) );
		Settings::migrate_saved_form_target();

		$this->assertSame( 0, Settings::get_target_form_id() );
		$this->assertSame( $content, (string) get_post_field( 'post_content', $page_id ) );
	}

	/**
	 * @return array<string,array{string}>
	 */
	public function unresolved_reference_provider() {
		return array(
			'inline form'       => array( 'inline' ),
			'ambiguous forms'   => array( 'ambiguous' ),
			'missing reference' => array( 'missing' ),
			'wrong post type'   => array( 'wrong_type' ),
		);
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
		$this->assertSame( 'newsletter-list-filtered', $profile->get_configuration()['emailoctopus_list_id'] );
		$this->assertNull( IntegrationResolver::get_profile( 'unknown' ) );
	}

	/**
	 * Portable field discovery uses the saved form rather than a route's form.
	 *
	 * @return void
	 */
	public function test_field_candidates_come_from_selected_saved_form() {
		$form_id = $this->create_saved_form( 'Portable Email' );
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => $this->get_form_content( 'Legacy Email' ),
			)
		);

		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::get_defaults(),
				array(
					'contact_page_id' => $page_id,
					'target_form_id'  => $form_id,
				)
			)
		);

		$this->assertSame( 'portable_email', EmailOctopusFieldMapper::get_source_fields()[0]['key'] );
		$this->assertTrue( IntegrationResolver::is_portable() );
	}

	/**
	 * Invalid targets report a reason and do not expose stale route fields.
	 *
	 * @return void
	 */
	public function test_invalid_saved_form_disables_portable_fields() {
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
		$this->assertFalse( IntegrationResolver::is_portable() );
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
				'post_content' => $this->get_form_content( $email_label ),
			)
		);
	}

	/**
	 * Create a route containing one marked saved-form reference.
	 *
	 * @param int $form_id Saved form ID.
	 * @return int
	 */
	private function create_reference_page( $form_id ) {
		return self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => $this->get_reference_content( $form_id ),
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

	/**
	 * @param string $email_label Email field label.
	 * @return string
	 */
	private function get_form_content( $email_label ) {
		return '<!-- wp:jetpack/contact-form --><div class="wp-block-jetpack-contact-form">'
			. '<!-- wp:jetpack/field-email --><div><!-- wp:jetpack/label {"label":"' . esc_attr( $email_label ) . '"} /--></div><!-- /wp:jetpack/field-email -->'
			. '</div><!-- /wp:jetpack/contact-form -->';
	}
}
