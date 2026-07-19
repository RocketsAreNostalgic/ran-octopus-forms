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
			array_merge( Settings::get_defaults(), array( 'target_form_id' => $form_id ) )
		);

		$rows = $this->invoke_health_method( 'check_integration_target' );

		$this->assertSame( 'error', $rows[0]['status'] );
		$this->assertStringContainsString( $expected_message, $rows[0]['message'] );
		$this->assertStringContainsString( 'EmailOctopus side effects are paused', $rows[0]['message'] );
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
	 * Mapping checks read the portable saved form rather than the fallback page.
	 *
	 * @return void
	 */
	public function test_mapping_health_uses_selected_saved_form_fields() {
		$form_id = $this->create_saved_form( 'publish', true );
		$page_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:jetpack/contact-form --><div><!-- wp:jetpack/field-email --><div><!-- wp:jetpack/label {"label":"Legacy email"} /--></div><!-- /wp:jetpack/field-email --></div><!-- /wp:jetpack/contact-form -->',
			)
		);

		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::get_defaults(),
				array(
					'contact_page_id'           => $page_id,
					'target_form_id'            => $form_id,
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
		$this->assertSame( 'pass', $target_checks[0]['status'] );
		$this->assertStringContainsString( 'Portable saved-form mode', $target_checks[0]['message'] );
	}

	/**
	 * A missing fallback warns but does not invalidate active portable targeting.
	 *
	 * @return void
	 */
	public function test_portable_mode_warns_when_legacy_contact_page_fallback_is_missing() {
		$form_id = $this->create_saved_form( 'publish', true );
		update_option(
			Settings::OPTION_NAME,
			array_merge( Settings::get_defaults(), array( 'target_form_id' => $form_id ) )
		);

		$rows = $this->invoke_health_method( 'check_pages' );

		$this->assertSame( 'warning', $rows[0]['status'] );
		$this->assertSame( 'Legacy contact page fallback', $rows[0]['label'] );
		$this->assertStringContainsString( 'downgrade', $rows[0]['message'] );
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
