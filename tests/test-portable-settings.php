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
 * Prove one profile can safely target multiple saved Jetpack forms.
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
		$first_form_id  = $this->create_saved_form();
		$second_form_id = $this->create_saved_form();
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::get_defaults(),
				array(
					'target_form_ids'      => array( $second_form_id, $first_form_id, $second_form_id ),
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
		$this->assertSame( array( $first_form_id, $second_form_id ), $profile->get_form_ids() );
		$this->assertArrayNotHasKey( 'contact_page_id', $profile->get_configuration() );
		$this->assertSame( 'newsletter-list-filtered', $profile->get_configuration()['emailoctopus_list_id'] );
		$this->assertNull( IntegrationResolver::get_profile( 'unknown' ) );
	}

	/**
	 * Shared selectors use the compatible intersection of valid selected forms.
	 *
	 * @return void
	 */
	public function test_field_candidates_are_intersected_across_selected_forms() {
		$first_form_id  = $this->create_saved_form_with_fields(
			array(
				array( 'email', 'Email' ),
				array( 'checkbox', 'Join our newsletter' ),
				array( 'name', 'Name' ),
				array( 'text', 'City' ),
			)
		);
		$second_form_id = $this->create_saved_form_with_fields(
			array(
				array( 'email', 'Email' ),
				array( 'consent', 'Join our newsletter' ),
				array( 'name', 'Name' ),
				array( 'email', 'City' ),
			)
		);
		update_option(
			Settings::OPTION_NAME,
			array_merge( Settings::get_defaults(), array( 'target_form_ids' => array( $first_form_id, $second_form_id ) ) )
		);

		$this->assertSame( array( 'email', 'name' ), wp_list_pluck( EmailOctopusFieldMapper::get_source_fields(), 'key' ) );
		$this->assertSame( array( 'email' ), wp_list_pluck( EmailOctopusFieldMapper::get_email_source_fields(), 'key' ) );
		$this->assertSame( array( 'join_our_newsletter' ), wp_list_pluck( EmailOctopusFieldMapper::get_newsletter_source_fields(), 'key' ) );
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
	 * An invalid selection is isolated while valid peers remain routable.
	 *
	 * @return void
	 */
	public function test_invalid_saved_form_is_isolated_from_valid_peer() {
		$valid_form_id = $this->create_saved_form();
		$draft_form_id = self::factory()->post->create(
			array(
				'post_type'    => 'jetpack_form',
				'post_status'  => 'draft',
				'post_content' => $this->get_form_content( array( array( 'email', 'Draft Email' ) ) ),
			)
		);
		update_option(
			Settings::OPTION_NAME,
			array_merge( Settings::get_defaults(), array( 'target_form_ids' => array( $valid_form_id, $draft_form_id ) ) )
		);

		$this->assertTrue( IntegrationResolver::is_portable_available() );
		$this->assertTrue( IntegrationResolver::is_routing_eligible_form_id( $valid_form_id ) );
		$this->assertFalse( IntegrationResolver::is_routing_eligible_form_id( $draft_form_id ) );
		$this->assertSame( 'target_not_published', IntegrationResolver::get_target_form_reason( $draft_form_id ) );
		$this->assertSame( array( 'email' ), wp_list_pluck( EmailOctopusFieldMapper::get_source_fields(), 'key' ) );
	}

	/**
	 * A missing configured source degrades only the affected selected form.
	 *
	 * @return void
	 */
	public function test_subscription_compatibility_is_reported_per_form() {
		$complete_form_id = $this->create_saved_form_with_fields(
			array(
				array( 'email', 'Email' ),
				array( 'checkbox', 'Join our newsletter' ),
				array( 'text', 'First name' ),
			)
		);
		$missing_form_id  = $this->create_saved_form_with_fields(
			array(
				array( 'email', 'Email' ),
				array( 'consent', 'Join our newsletter' ),
			)
		);
		$form_ids         = array( $complete_form_id, $missing_form_id );
		$configuration    = array_merge(
			Settings::get_defaults(),
			array(
				'target_form_ids'           => $form_ids,
				'emailoctopus_email_source' => 'email',
				'newsletter_source'         => 'join_our_newsletter',
				'emailoctopus_field_map'    => array(
					'FirstName' => array(
						'source'    => 'first_name',
						'transform' => 'as_is',
					),
				),
			)
		);

		$compatibility = EmailOctopusFieldMapper::get_subscription_compatibility( $form_ids, $configuration );

		$this->assertTrue( $compatibility[ $complete_form_id ]['eligible'] );
		$this->assertFalse( $compatibility[ $missing_form_id ]['eligible'] );
		$this->assertContains( 'custom_source_missing', $compatibility[ $missing_form_id ]['reasons'] );
		$this->assertSame( 'first_name', $compatibility[ $missing_form_id ]['source_failures'][0]['source'] );
	}

	/**
	 * Email has an exact type contract and a bad peer does not disable a good one.
	 *
	 * @return void
	 */
	public function test_email_source_type_mismatch_is_isolated_per_form() {
		$email_form_id = $this->create_saved_form_with_fields(
			array(
				array( 'email', 'Email' ),
				array( 'checkbox', 'Join our newsletter' ),
			)
		);
		$text_form_id  = $this->create_saved_form_with_fields(
			array(
				array( 'text', 'Email' ),
				array( 'checkbox', 'Join our newsletter' ),
			)
		);
		$form_ids      = array( $email_form_id, $text_form_id );
		$configuration = array_merge(
			Settings::get_defaults(),
			array(
				'emailoctopus_email_source' => 'email',
				'newsletter_source'         => 'join_our_newsletter',
			)
		);

		$compatibility = EmailOctopusFieldMapper::get_subscription_compatibility( $form_ids, $configuration );

		$this->assertTrue( $compatibility[ $email_form_id ]['eligible'] );
		$this->assertFalse( $compatibility[ $text_form_id ]['eligible'] );
		$this->assertContains( 'email_source_invalid', $compatibility[ $text_form_id ]['reasons'] );
		$this->assertSame( 'wrong_type', $compatibility[ $text_form_id ]['source_failures'][0]['reason'] );
		$this->assertSame( array(), EmailOctopusFieldMapper::get_email_source_fields_for_saved_forms( $form_ids ) );
	}

	/**
	 * Custom mappings require one normalized source key and exact type everywhere.
	 *
	 * @return void
	 */
	public function test_custom_source_type_mismatch_is_reported_for_affected_form() {
		$text_form_id     = $this->create_saved_form_with_fields(
			array(
				array( 'email', 'Email' ),
				array( 'checkbox', 'Join our newsletter' ),
				array( 'text', 'First name' ),
			)
		);
		$textarea_form_id = $this->create_saved_form_with_fields(
			array(
				array( 'email', 'Email' ),
				array( 'checkbox', 'Join our newsletter' ),
				array( 'textarea', 'First name' ),
			)
		);
		$form_ids         = array( $text_form_id, $textarea_form_id );
		$configuration    = array_merge(
			Settings::get_defaults(),
			array(
				'emailoctopus_email_source' => 'email',
				'newsletter_source'         => 'join_our_newsletter',
				'emailoctopus_field_map'    => array(
					'FirstName' => array(
						'source'    => 'first_name',
						'transform' => 'as_is',
					),
				),
			)
		);

		$compatibility = EmailOctopusFieldMapper::get_subscription_compatibility( $form_ids, $configuration );

		$this->assertTrue( $compatibility[ $text_form_id ]['eligible'] );
		$this->assertFalse( $compatibility[ $textarea_form_id ]['eligible'] );
		$this->assertContains( 'custom_source_type_mismatch', $compatibility[ $textarea_form_id ]['reasons'] );
		$this->assertSame( 'text', $compatibility[ $textarea_form_id ]['source_failures'][0]['expected_type'] );
		$this->assertSame( 'textarea', $compatibility[ $textarea_form_id ]['source_failures'][0]['actual_type'] );
	}

	/**
	 * Filtered runtime mappings participate in the same fail-closed form gate.
	 *
	 * @return void
	 */
	public function test_filtered_field_map_is_used_for_runtime_compatibility() {
		$form_id = $this->create_saved_form_with_fields(
			array(
				array( 'email', 'Email' ),
				array( 'checkbox', 'Join our newsletter' ),
			)
		);
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::get_defaults(),
				array(
					'target_form_ids'           => array( $form_id ),
					'emailoctopus_email_source' => 'email',
					'newsletter_source'         => 'join_our_newsletter',
				)
			)
		);
		$filter = static function () {
			return array(
				'FirstName' => array(
					'source'    => 'missing_filtered_source',
					'transform' => 'as_is',
				),
			);
		};

		add_filter( 'ran_emailoctopus_jetpack_forms_emailoctopus_field_map', $filter );
		$eligible = IntegrationResolver::is_subscription_eligible_form_id( $form_id );
		remove_filter( 'ran_emailoctopus_jetpack_forms_emailoctopus_field_map', $filter );

		$this->assertFalse( $eligible );
	}

	/**
	 * Create a published saved form fixture.
	 *
	 * @param string $email_label Email field label.
	 * @return int
	 */
	private function create_saved_form( $email_label = 'Email' ) {
		return $this->create_saved_form_with_fields( array( array( 'email', $email_label ) ) );
	}

	/**
	 * Create a published saved form fixture with caller-controlled fields.
	 *
	 * @param array<int,array{string,string}> $fields Field type and label pairs.
	 * @return int
	 */
	private function create_saved_form_with_fields( $fields ) {
		return self::factory()->post->create(
			array(
				'post_type'    => 'jetpack_form',
				'post_status'  => 'publish',
				'post_content' => $this->get_form_content( $fields ),
			)
		);
	}

	/**
	 * Serialize one Jetpack contact form with nested field labels.
	 *
	 * @param array<int,array{string,string}> $fields Field type and label pairs.
	 * @return string
	 */
	private function get_form_content( $fields ) {
		$content = '<!-- wp:jetpack/contact-form --><div class="wp-block-jetpack-contact-form">';

		foreach ( $fields as $field ) {
			$type     = sanitize_key( $field[0] );
			$label    = esc_attr( $field[1] );
			$content .= '<!-- wp:jetpack/field-' . $type . ' --><div><!-- wp:jetpack/label {"label":"' . $label . '"} /--></div><!-- /wp:jetpack/field-' . $type . ' -->';
		}

		return $content . '</div><!-- /wp:jetpack/contact-form -->';
	}
}
