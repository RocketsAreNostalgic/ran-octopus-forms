<?php
/**
 * Profile-explicit diagnostics and health-cache coverage.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

use RAN\EmailOctopusJetpackForms\HealthCheck;
use RAN\EmailOctopusJetpackForms\Settings;

/**
 * Prove diagnostics remain isolated, explicit, and safe for index rendering.
 */
class RAN_EmailOctopus_Jetpack_Forms_Profile_Health_Check_Test extends WP_UnitTestCase {
	/**
	 * First immutable profile UUID.
	 */
	const PROFILE_A = '11111111-1111-4111-8111-111111111111';

	/**
	 * Second immutable profile UUID.
	 */
	const PROFILE_B = '22222222-2222-4222-8222-222222222222';

	/**
	 * Reset canonical and provider state.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		delete_option( Settings::OPTION_NAME );
		delete_option( 'emailoctopus_api_key' );
	}

	/**
	 * Index summaries are local and report complete per-profile coverage.
	 *
	 * @return void
	 */
	public function test_profile_summary_is_read_only_and_performs_no_remote_calls() {
		$form_id = $this->create_saved_form( 'Alpha form' );
		$page_id = $this->create_success_page();
		$this->store_profiles( array( self::PROFILE_A => $this->profile( 'Alpha', array( $form_id ), $page_id, 'list-alpha' ) ) );
		update_option( 'emailoctopus_api_key', 'test-key' );

		$requests = 0;
		$filter   = static function ( $preempt ) use ( &$requests ) {
			++$requests;
			return $preempt;
		};
		add_filter( 'pre_http_request', $filter );
		$result = HealthCheck::get_profile_summary( self::PROFILE_A );
		remove_filter( 'pre_http_request', $filter );

		$this->assertIsArray( $result );
		$this->assertSame( 0, $requests );
		$this->assertSame( 'healthy', $result['overall'] );
		$this->assertSame( 1, $result['selected_count'] );
		$this->assertSame( 1, $result['routing_count'] );
		$this->assertSame( 1, $result['subscription_count'] );
		$this->assertSame( 0, $result['checked_at'] );
		$this->assertSame( 'pass', $this->find_row( $result['global_checks'], 'Jetpack feedback identity' )['status'] );
		$this->assertSame( 'pass', $this->find_row( $result['profile_checks'], 'EmailOctopus destination' )['status'] );
	}

	/**
	 * An unavailable stored ID remains named while a valid peer stays active.
	 *
	 * @return void
	 */
	public function test_unavailable_form_is_reported_without_disabling_valid_peer() {
		$form_id = $this->create_saved_form( 'Working form' );
		$page_id = $this->create_success_page();
		$this->store_profiles( array( self::PROFILE_A => $this->profile( 'Alpha', array( $form_id, 999999 ), $page_id, 'list-alpha' ) ) );
		update_option( 'emailoctopus_api_key', 'test-key' );

		$result = HealthCheck::get_profile_summary( self::PROFILE_A );

		$this->assertSame( 'degraded', $result['overall'] );
		$this->assertSame( 2, $result['selected_count'] );
		$this->assertSame( 1, $result['routing_count'] );
		$this->assertSame( 1, $result['subscription_count'] );
		$this->assertSame( 'error', $this->find_row( $result['profile_checks'], 'Saved Jetpack form #999999' )['status'] );
		$this->assertStringContainsString( 'stored ID is preserved', $this->find_row( $result['profile_checks'], 'Saved Jetpack form #999999' )['message'] );
	}

	/**
	 * A broken profile does not alter a healthy peer's result.
	 *
	 * @return void
	 */
	public function test_broken_profile_does_not_affect_healthy_peer() {
		$form_a   = $this->create_saved_form( 'Alpha form' );
		$page_id  = $this->create_success_page();
		$broken_b = self::factory()->post->create(
			array(
				'post_type'    => 'jetpack_form',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:paragraph --><p>Malformed</p><!-- /wp:paragraph -->',
			)
		);
		$this->store_profiles(
			array(
				self::PROFILE_A => $this->profile( 'Alpha', array( $form_a ), $page_id, 'list-alpha' ),
				self::PROFILE_B => $this->profile( 'Bravo', array( $broken_b ), $page_id, 'list-bravo' ),
			)
		);
		update_option( 'emailoctopus_api_key', 'test-key' );

		$alpha = HealthCheck::get_profile_summary( self::PROFILE_A );
		$bravo = HealthCheck::get_profile_summary( self::PROFILE_B );

		$this->assertSame( 'healthy', $alpha['overall'] );
		$this->assertSame( 1, $alpha['subscription_count'] );
		$this->assertSame( 'broken', $bravo['overall'] );
		$this->assertSame( 0, $bravo['routing_count'] );
		$this->assertSame( 0, $bravo['subscription_count'] );
	}

	/**
	 * Missing shared sources identify exactly which forms are affected.
	 *
	 * @return void
	 */
	public function test_missing_configured_source_lists_affected_forms() {
		$form_a  = $this->create_saved_form( 'Alpha form' );
		$form_b  = $this->create_saved_form( 'Bravo form', 'other_email', 'other_consent' );
		$page_id = $this->create_success_page();
		$this->store_profiles( array( self::PROFILE_A => $this->profile( 'Alpha', array( $form_a, $form_b ), $page_id, 'list-alpha' ) ) );
		update_option( 'emailoctopus_api_key', 'test-key' );

		$result  = HealthCheck::get_profile_summary( self::PROFILE_A );
		$email   = $this->find_row( $result['profile_checks'], 'Email source' );
		$mapping = $this->find_row( $result['profile_checks'], 'Subscription mapping for form #' . $form_b );

		$this->assertSame( 'degraded', $result['overall'] );
		$this->assertSame( 'warning', $email['status'] );
		$this->assertStringContainsString( (string) $form_b, $email['message'] );
		$this->assertStringNotContainsString( (string) $form_a, $email['message'] );
		$this->assertStringContainsString( 'source "email" is missing', $mapping['message'] );
	}

	/**
	 * New profiles remain incomplete until required behaviour is configured.
	 *
	 * @return void
	 */
	public function test_incomplete_profile_has_distinct_state() {
		$profile = array_replace(
			Settings::get_profile_defaults(),
			array(
				'revision' => 1,
				'label'    => 'Draft profile',
			)
		);
		$this->store_profiles( array( self::PROFILE_A => $profile ) );

		$result = HealthCheck::get_profile_summary( self::PROFILE_A );

		$this->assertSame( 'incomplete', $result['overall'] );
		$this->assertSame( 0, $result['selected_count'] );
		$this->assertSame( 'skipped', $this->find_row( $result['profile_checks'], 'Success page' )['status'] );
		$this->assertSame( 'skipped', $this->find_row( $result['profile_checks'], 'EmailOctopus destination' )['status'] );
	}

	/**
	 * On-demand checks contact only the explicit profile destination.
	 *
	 * @return void
	 */
	public function test_on_demand_check_is_profile_explicit_and_cache_keyed() {
		$form_a  = $this->create_saved_form( 'Alpha form' );
		$form_b  = $this->create_saved_form( 'Bravo form' );
		$page_id = $this->create_success_page();
		$this->store_profiles(
			array(
				self::PROFILE_A => $this->profile( 'Alpha', array( $form_a ), $page_id, 'list-alpha-health' ),
				self::PROFILE_B => $this->profile( 'Bravo', array( $form_b ), $page_id, 'list-bravo-health' ),
			)
		);
		update_option( 'emailoctopus_api_key', 'test-key' );

		$urls   = array();
		$filter = static function ( $preempt, $args, $url ) use ( &$urls ) {
			$urls[] = $url;
			return array(
				'headers'  => array(),
				'body'     => wp_json_encode(
					array(
						'data' => array(
							'id'     => 'list-alpha-health',
							'fields' => array(),
						),
					)
				),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};
		add_filter( 'pre_http_request', $filter, 10, 3 );
		$result = HealthCheck::run( self::PROFILE_A );
		remove_filter( 'pre_http_request', $filter, 10 );

		$this->assertSame( 'healthy', $result['overall'] );
		$this->assertCount( 1, $urls );
		$this->assertStringContainsString( 'list-alpha-health', $urls[0] );
		$this->assertStringNotContainsString( 'list-bravo-health', $urls[0] );
		$this->assertTrue( HealthCheck::is_current_result( $result, self::PROFILE_A ) );

		$store = get_option( Settings::OPTION_NAME );
		++$store['revision'];
		++$store['profiles'][ self::PROFILE_B ]['revision'];
		update_option( Settings::OPTION_NAME, $store, false );

		$this->assertFalse( HealthCheck::is_current_result( $result, self::PROFILE_A ) );
	}

	/**
	 * A reachable route degrades, rather than disappearing, on provider failure.
	 *
	 * @return void
	 */
	public function test_provider_failure_degrades_routable_profile() {
		$form_id = $this->create_saved_form( 'Alpha form' );
		$page_id = $this->create_success_page();
		$this->store_profiles( array( self::PROFILE_A => $this->profile( 'Alpha', array( $form_id ), $page_id, 'list-provider-error' ) ) );
		update_option( 'emailoctopus_api_key', 'test-key' );

		$filter = static function () {
			return new WP_Error( 'provider_unreachable', 'Provider unavailable.' );
		};
		add_filter( 'pre_http_request', $filter );
		$result = HealthCheck::run( self::PROFILE_A );
		remove_filter( 'pre_http_request', $filter );

		$this->assertSame( 'degraded', $result['overall'] );
		$this->assertSame( 1, $result['routing_count'] );
		$this->assertSame( 0, $result['subscription_count'] );
		$this->assertSame( 'error', $this->find_row( $result['global_checks'], 'EmailOctopus provider reachability' )['status'] );
	}

	/**
	 * Store canonical profiles without exercising the save repository.
	 *
	 * @param array<string,array<string,mixed>> $profiles Profiles by UUID.
	 * @return void
	 */
	private function store_profiles( $profiles ) {
		update_option(
			Settings::OPTION_NAME,
			array(
				'schema_version' => Settings::SCHEMA_VERSION,
				'revision'       => 1,
				'profiles'       => $profiles,
			),
			false
		);
	}

	/**
	 * Build one canonical profile fixture.
	 *
	 * @param string         $label    Label.
	 * @param array<int,int> $form_ids Saved form IDs.
	 * @param int            $page_id  Success page ID.
	 * @param string         $list_id  EmailOctopus list ID.
	 * @return array<string,mixed>
	 */
	private function profile( $label, $form_ids, $page_id, $list_id ) {
		return array_replace(
			Settings::get_profile_defaults(),
			array(
				'revision'        => 1,
				'label'           => $label,
				'form_ids'        => Settings::normalize_form_ids( $form_ids ),
				'destination'     => array(
					'type' => 'list',
					'id'   => $list_id,
				),
				'email_source'    => 'email',
				'consent_source'  => 'consent',
				'success_page_id' => $page_id,
			)
		);
	}

	/**
	 * Create a saved Jetpack form.
	 *
	 * @param string $title          Form title.
	 * @param string $email_label    Email field label.
	 * @param string $consent_label  Consent field label.
	 * @return int
	 */
	private function create_saved_form( $title, $email_label = 'Email', $consent_label = 'Consent' ) {
		$content = '<!-- wp:jetpack/contact-form --><div>'
			. '<!-- wp:jetpack/field-email --><div><!-- wp:jetpack/label {"label":"' . esc_attr( $email_label ) . '"} /--></div><!-- /wp:jetpack/field-email -->'
			. '<!-- wp:jetpack/field-consent --><div><!-- wp:jetpack/label {"label":"' . esc_attr( $consent_label ) . '"} /--></div><!-- /wp:jetpack/field-consent -->'
			. '</div><!-- /wp:jetpack/contact-form -->';

		return self::factory()->post->create(
			array(
				'post_type'    => 'jetpack_form',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => $content,
			)
		);
	}

	/**
	 * Create a published success page.
	 *
	 * @return int
	 */
	private function create_success_page() {
		return self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * Find one row by exact label.
	 *
	 * @param array<int,array<string,string>> $rows Rows.
	 * @param string                          $label Label.
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
}
