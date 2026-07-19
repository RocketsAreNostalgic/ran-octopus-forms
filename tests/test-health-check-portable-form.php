<?php
/**
 * Health-check coverage for portable saved-form targets.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

use RAN\EmailOctopusJetpackForms\HealthCheck;
use RAN\EmailOctopusJetpackForms\Settings;

/**
 * Ensure saved-form failures and mappings produce actionable diagnostics.
 */
class RAN_EmailOctopus_Jetpack_Forms_Health_Check_Portable_Form_Test extends WP_UnitTestCase {
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
	 * Invalid saved-form targets report the precise repair path.
	 *
	 * @dataProvider invalid_target_provider
	 *
	 * @param string $scenario         Target failure scenario.
	 * @param string $expected_message Expected diagnostic fragment.
	 * @return void
	 */
	public function test_invalid_saved_form_target_is_actionable( $scenario, $expected_message ) {
		if ( 'missing' === $scenario ) {
			$form_id = 999999;
		} elseif ( 'wrong_type' === $scenario ) {
			$form_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		} elseif ( 'draft' === $scenario ) {
			$form_id = $this->create_saved_form( 'draft', true );
		} else {
			$form_id = $this->create_saved_form( 'publish', false );
		}

		update_option(
			Settings::OPTION_NAME,
			array_merge( Settings::get_defaults(), array( 'target_form_ids' => array( $form_id ) ) )
		);

		$rows = $this->invoke_health_method( 'check_integration_target' );
		$row  = $this->find_row( $rows, 'Saved Jetpack form #' . $form_id );

		$this->assertSame( 'error', $row['status'] );
		$this->assertStringContainsString( $expected_message, $row['message'] );
		$this->assertStringContainsString( 'EmailOctopus routing is disabled', $row['message'] );
	}

	/**
	 * @return array<string,array{string,string}>
	 */
	public function invalid_target_provider() {
		return array(
			'deleted or missing' => array( 'missing', 'deleted or is unavailable' ),
			'wrong post type'    => array( 'wrong_type', 'is not a jetpack_form post' ),
			'draft form'         => array( 'draft', 'has status "draft"' ),
			'invalid structure'  => array( 'structure', 'does not contain exactly one Jetpack contact form' ),
		);
	}

	/**
	 * Mapping checks read the selected saved form without a page target.
	 *
	 * @return void
	 */
	public function test_mapping_health_uses_selected_saved_form_fields() {
		$form_id = $this->create_saved_form( 'publish', true );

		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::get_defaults(),
				array(
					'target_form_ids'           => array( $form_id ),
					'emailoctopus_email_source' => 'portable_email',
					'newsletter_source'         => 'portable_opt_in',
				)
			)
		);

		$email_check      = $this->invoke_health_method( 'check_email_source_mapping' );
		$newsletter_check = $this->invoke_health_method( 'check_newsletter_source_mapping' );
		$target_checks    = $this->invoke_health_method( 'check_integration_target' );

		$this->assertSame( 'pass', $email_check['status'] );
		$this->assertStringContainsString( 'Portable email', $email_check['message'] );
		$this->assertSame( 'pass', $newsletter_check['status'] );
		$this->assertStringContainsString( 'Portable opt-in', $newsletter_check['message'] );
		$routing_check = $this->find_row( $target_checks, 'Saved-form routing' );

		$this->assertSame( 'pass', $routing_check['status'] );
		$this->assertStringContainsString( '1 of 1 selected saved form(s) can be routed', $routing_check['message'] );
	}

	/**
	 * Missing selection is an error and mapping checks are skipped.
	 *
	 * @return void
	 */
	public function test_missing_saved_form_disables_routing() {
		update_option( Settings::OPTION_NAME, Settings::get_defaults() );

		$target_rows = $this->invoke_health_method( 'check_integration_target' );
		$form_rows   = $this->invoke_health_method( 'check_contact_form' );

		$this->assertSame( 'error', $target_rows[0]['status'] );
		$this->assertStringContainsString( 'No saved forms are selected', $target_rows[0]['message'] );
		$this->assertStringContainsString( 'routing remains disabled', $target_rows[0]['message'] );
		$this->assertSame( 'skipped', $form_rows[0]['status'] );
		$this->assertStringContainsString( 'EmailOctopus routing is disabled', $form_rows[0]['message'] );
	}

	/**
	 * An invalid selected form is isolated while a valid peer remains active.
	 *
	 * @return void
	 */
	public function test_invalid_form_is_degraded_without_disabling_valid_peer() {
		$valid_form_id = $this->create_saved_form( 'publish', true );
		$draft_form_id = $this->create_saved_form( 'draft', true );

		update_option(
			Settings::OPTION_NAME,
			array_merge( Settings::get_defaults(), array( 'target_form_ids' => array( $valid_form_id, $draft_form_id ) ) )
		);

		$rows        = $this->invoke_health_method( 'check_integration_target' );
		$routing_row = $this->find_row( $rows, 'Saved-form routing' );
		$invalid_row = $this->find_row( $rows, 'Saved Jetpack form #' . $draft_form_id );
		$valid_row   = $this->find_row( $rows, 'Saved Jetpack form #' . $valid_form_id );

		$this->assertSame( 'warning', $routing_row['status'] );
		$this->assertStringContainsString( '1 of 2', $routing_row['message'] );
		$this->assertSame( 'warning', $invalid_row['status'] );
		$this->assertStringContainsString( 'valid selected peers remain active', $invalid_row['message'] );
		$this->assertSame( 'pass', $valid_row['status'] );
	}

	/**
	 * Mapping incompatibility is degraded when one selected peer remains eligible.
	 *
	 * @return void
	 */
	public function test_mapping_incompatibility_reports_warning_with_eligible_peer() {
		$valid_form_id        = $this->create_saved_form( 'publish', true );
		$incompatible_form_id = self::factory()->post->create(
			array(
				'post_type'    => 'jetpack_form',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:jetpack/contact-form --><div>'
					. '<!-- wp:jetpack/field-email --><div><!-- wp:jetpack/label {"label":"Work email"} /--></div><!-- /wp:jetpack/field-email -->'
					. '<!-- wp:jetpack/field-consent --><div><!-- wp:jetpack/label {"label":"Work opt-in"} /--></div><!-- /wp:jetpack/field-consent -->'
					. '</div><!-- /wp:jetpack/contact-form -->',
			)
		);
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::get_defaults(),
				array(
					'target_form_ids'           => array( $valid_form_id, $incompatible_form_id ),
					'emailoctopus_email_source' => 'portable_email',
					'newsletter_source'         => 'portable_opt_in',
				)
			)
		);

		$rows = $this->invoke_health_method( 'check_contact_form' );

		$this->assertSame( 'warning', $this->find_row( $rows, 'Shared saved-form fields' )['status'] );
		$this->assertSame( 'warning', $this->find_row( $rows, 'Shared email fields' )['status'] );
		$this->assertSame( 'warning', $this->find_row( $rows, 'Email source mapping' )['status'] );
		$this->assertSame( 'pass', $this->find_row( $rows, 'Subscription mapping for form #' . $valid_form_id )['status'] );
		$this->assertSame( 'warning', $this->find_row( $rows, 'Subscription mapping for form #' . $incompatible_form_id )['status'] );

		foreach ( $rows as $row ) {
			$this->assertNotSame( 'error', $row['status'] );
		}
	}

	/**
	 * Invoke one private diagnostic without external provider calls.
	 *
	 * @param string $method_name HealthCheck method name.
	 * @return mixed
	 */
	private function invoke_health_method( $method_name ) {
		$method = new ReflectionMethod( HealthCheck::class, $method_name );

		if ( PHP_VERSION_ID < 80500 ) {
			$method->setAccessible( true );
		}

		return $method->invoke( null );
	}

	/**
	 * Find a diagnostic row by label.
	 *
	 * @param array<int,array<string,string>> $rows  Health rows.
	 * @param string                          $label Expected label.
	 * @return array<string,string>
	 */
	private function find_row( $rows, $label ) {
		foreach ( $rows as $row ) {
			if ( $label === $row['label'] ) {
				return $row;
			}
		}

		$this->fail( 'Health row not found: ' . $label );
	}

	/**
	 * @param string $status          Saved-form status.
	 * @param bool   $valid_structure Whether to include one contact-form wrapper.
	 * @return int
	 */
	private function create_saved_form( $status, $valid_structure ) {
		$fields  = '<!-- wp:jetpack/field-email --><div><!-- wp:jetpack/label {"label":"Portable email"} /--></div><!-- /wp:jetpack/field-email -->'
			. '<!-- wp:jetpack/field-checkbox --><div><!-- wp:jetpack/option {"label":"Portable opt-in"} /--></div><!-- /wp:jetpack/field-checkbox -->';
		$content = $valid_structure
			? '<!-- wp:jetpack/contact-form --><div>' . $fields . '</div><!-- /wp:jetpack/contact-form -->'
			: $fields;

		return self::factory()->post->create(
			array(
				'post_type'    => 'jetpack_form',
				'post_status'  => $status,
				'post_content' => $content,
			)
		);
	}
}
