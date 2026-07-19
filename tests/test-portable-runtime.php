<?php
/**
 * Portable saved-form rendering and submission coverage.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

use Automattic\Jetpack\Forms\ContactForm\Feedback;
use RAN\EmailOctopusJetpackForms\IntegrationResolver;
use RAN\EmailOctopusJetpackForms\JetpackForms;
use RAN\EmailOctopusJetpackForms\Settings;
use RAN\EmailOctopusJetpackForms\SubmissionMessages;

	/**
	 * Prove selected saved Jetpack forms are portable and signed end-to-end.
	 */
class RAN_EmailOctopus_Jetpack_Forms_Portable_Runtime_Test extends WP_UnitTestCase {
		/**
		 * Reset integration and request state.
		 *
		 * @return void
		 */
	public function set_up() {
		parent::set_up();
		delete_option( Settings::OPTION_NAME );
		Feedback::$form_ids = array();
		$_GET               = array();
		$_POST              = array();
	}

		/**
		 * The selected saved form receives identical portable context on any route.
		 *
		 * @return void
		 */
	public function test_selected_saved_form_is_marked_on_page_and_post_routes() {
		$form_id = $this->configure_portable_form();
		$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$post_id = self::factory()->post->create( array( 'post_type' => 'post' ) );

		foreach ( array( $page_id, $post_id ) as $route_id ) {
			$this->go_to( get_permalink( $route_id ) );
			$html = $this->render_form_html( $form_id );

			$this->assertStringContainsString( 'name="' . JetpackForms::PROFILE_FIELD . '" value="default"', $html );
			$this->assertStringContainsString( 'name="' . JetpackForms::FORM_REF_FIELD . '" value="' . $form_id . '"', $html );
			$this->assertStringContainsString( 'name="' . JetpackForms::TARGET_FIELD . '"', $html );
		}
	}

		/**
		 * Adjacent forms remain AJAX-enabled and render context is reset.
		 *
		 * @return void
		 */
	public function test_unrelated_form_is_untouched_and_render_context_resets() {
		$form_id    = $this->configure_portable_form();
		$other_form = self::factory()->post->create(
			array(
				'post_type'   => 'jetpack_form',
				'post_status' => 'publish',
			)
		);

		$block = $this->get_reference_block( $form_id );
		JetpackForms::before_render_block( null, $block );
		$this->assertFalse( JetpackForms::disable_ajax_for_contact_form( true ) );
		JetpackForms::after_render_block( '', $block );

		$this->assertTrue( JetpackForms::disable_ajax_for_contact_form( true ) );
		$this->assertSame( '<form></form>', $this->render_form_html( $other_form ) );
	}

	/**
	 * Multiple selected saved forms are marked independently on one route.
	 *
	 * @return void
	 */
	public function test_multiple_selected_forms_are_marked_while_unselected_form_is_untouched() {
		$first_form_id  = $this->create_portable_form();
		$second_form_id = $this->create_portable_form();
		$other_form_id  = $this->create_portable_form();

		$this->configure_portable_forms( array( $first_form_id, $second_form_id ) );

		foreach ( array( $first_form_id, $second_form_id ) as $form_id ) {
			$html = $this->render_form_html( $form_id );

			$this->assertStringContainsString( 'name="' . JetpackForms::PROFILE_FIELD . '" value="default"', $html );
			$this->assertStringContainsString( 'name="' . JetpackForms::FORM_REF_FIELD . '" value="' . $form_id . '"', $html );
			$this->assertStringContainsString( 'name="' . JetpackForms::TARGET_FIELD . '"', $html );
		}

		$this->assertSame( '<form></form>', $this->render_form_html( $other_form_id ) );
		$this->assertTrue( JetpackForms::disable_ajax_for_contact_form( true ) );
	}

	/**
	 * Jetpack's synced-form pre-render short circuit cannot leak context forward.
	 *
	 * @return void
	 */
	public function test_pre_rendered_selected_form_does_not_mark_an_adjacent_unselected_form() {
		$selected_form_id = $this->create_portable_form();
		$other_form_id    = $this->create_portable_form();

		$this->configure_portable_forms( array( $selected_form_id ) );
		JetpackForms::before_render_block(
			null,
			array(
				'blockName' => 'jetpack/contact-form',
				'attrs'     => array( 'ref' => $selected_form_id ),
			)
		);

		$selected_html = JetpackForms::mark_target_form_submission( '<form></form>' );

		// Jetpack returns from pre_render_block, so render_block never fires for
		// the selected outer reference before the next sibling starts rendering.
		JetpackForms::before_render_block(
			null,
			array(
				'blockName' => 'jetpack/contact-form',
				'attrs'     => array( 'ref' => $other_form_id ),
			)
		);
		$other_html = JetpackForms::mark_target_form_submission( '<form></form>' );

		$this->assertStringContainsString( 'value="' . $selected_form_id . '"', $selected_html );
		$this->assertSame( '<form></form>', $other_html );
	}

	/**
	 * An unconfigured integration never changes Jetpack form behavior.
	 *
	 * @return void
	 */
	public function test_missing_saved_form_target_adds_no_marker_or_ajax_change() {
		$form_id = self::factory()->post->create(
			array(
				'post_type'    => 'jetpack_form',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:jetpack/contact-form --><div class="wp-block-jetpack-contact-form"></div><!-- /wp:jetpack/contact-form -->',
			)
		);
		update_option( Settings::OPTION_NAME, Settings::get_defaults() );

		$this->assertSame( '<form></form>', $this->render_form_html( $form_id ) );
		$this->assertTrue( JetpackForms::disable_ajax_for_contact_form( true ) );
		$this->assertFalse( JetpackForms::is_target_submission() );
	}

		/**
		 * Signed context must agree with Jetpack's authoritative feedback form ID.
		 *
		 * @return void
		 */
	public function test_feedback_form_identity_mismatch_fails_closed() {
		$form_id       = $this->configure_portable_form();
		$other_form_id = self::factory()->post->create(
			array(
				'post_type'   => 'jetpack_form',
				'post_status' => 'publish',
			)
		);
		$feedback_id   = self::factory()->post->create();

		$_POST                    = $this->get_context_from_html( $this->render_form_html( $form_id ) );
		$_POST['contact-form-id'] = 'route-123';
		$this->assertTrue( JetpackForms::is_target_submission() );
		Feedback::$form_ids[ $feedback_id ] = $form_id;
		$this->assertTrue( JetpackForms::is_target_submission( $feedback_id ) );

		Feedback::$form_ids[ $feedback_id ] = $other_form_id;
		$this->assertFalse( JetpackForms::is_target_submission( $feedback_id ) );
		$this->assertSame(
			'https://example.org/original/',
			JetpackForms::redirect_contact_form( 'https://example.org/original/', 123, $feedback_id )
		);
	}

	/**
	 * Signed context cannot be swapped between two selected forms.
	 *
	 * @return void
	 */
	public function test_feedback_identity_must_match_exact_selected_form() {
		$first_form_id  = $this->create_portable_form();
		$second_form_id = $this->create_portable_form();
		$feedback_id    = self::factory()->post->create();

		$this->configure_portable_forms( array( $first_form_id, $second_form_id ) );
		$_POST                              = $this->get_context_from_html( $this->render_form_html( $first_form_id ) );
		Feedback::$form_ids[ $feedback_id ] = $second_form_id;

		$this->assertTrue( JetpackForms::is_target_submission() );
		$this->assertFalse( JetpackForms::is_target_submission( $feedback_id ) );
		$this->assertSame(
			'https://example.org/original/',
			JetpackForms::redirect_contact_form( 'https://example.org/original/', 123, $feedback_id )
		);
	}

		/**
		 * Missing or changed signed context fields are rejected.
		 *
		 * @dataProvider tampered_context_provider
		 *
		 * @param string $field Submitted field to change.
		 * @param mixed  $value Replacement value, or null to remove it.
		 * @return void
		 */
	public function test_tampered_portable_context_fails_closed( $field, $value ) {
		$form_id = $this->configure_portable_form();
		$_POST   = $this->get_context_from_html( $this->render_form_html( $form_id ) );

		if ( null === $value ) {
			unset( $_POST[ $field ] );
		} else {
			$_POST[ $field ] = $value;
		}

		$this->assertFalse( JetpackForms::is_target_submission() );
	}

		/**
		 * @return array<string,array{string,mixed}>
		 */
	public function tampered_context_provider() {
		return array(
			'missing profile' => array( JetpackForms::PROFILE_FIELD, null ),
			'changed profile' => array( JetpackForms::PROFILE_FIELD, 'other' ),
			'missing ref'     => array( JetpackForms::FORM_REF_FIELD, null ),
			'changed ref'     => array( JetpackForms::FORM_REF_FIELD, 987654 ),
			'missing nonce'   => array( JetpackForms::TARGET_FIELD, null ),
			'changed nonce'   => array( JetpackForms::TARGET_FIELD, 'invalid' ),
		);
	}

		/**
		 * A valid marker becomes stale when the configured target changes.
		 *
		 * @return void
		 */
	public function test_marker_for_previous_target_is_rejected_after_settings_change() {
		$form_id = $this->create_portable_form();
		$new_id  = $this->create_portable_form();

		$this->configure_portable_forms( array( $form_id, $new_id ) );
		$_POST = $this->get_context_from_html( $this->render_form_html( $form_id ) );

		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::get_all(),
				array( 'target_form_ids' => array( $new_id ) )
			)
		);

		$this->assertFalse( JetpackForms::is_target_submission() );

		$_POST = $this->get_context_from_html( $this->render_form_html( $new_id ) );
		$this->assertTrue( JetpackForms::is_target_submission() );
	}

	/**
	 * A mapping-incompatible selected form retains routing but cannot subscribe.
	 *
	 * @return void
	 */
	public function test_mapping_incompatible_selected_form_retains_routing_without_subscription() {
		$compatible_form_id   = $this->create_portable_form();
		$incompatible_form_id = $this->create_portable_form( 'Different email', 'Different consent' );
		$feedback_id          = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$success_page_id      = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		$this->configure_portable_forms(
			array( $compatible_form_id, $incompatible_form_id ),
			array(
				'success_page_id'           => $success_page_id,
				'emailoctopus_list_id'      => 'newsletter-list',
				'emailoctopus_email_source' => 'email',
				'newsletter_source'         => 'join_our_newsletter',
				'emailoctopus_field_map'    => array(),
			)
		);
		update_option( 'emailoctopus_api_key', 'test-api-key' );

		$_POST                              = $this->get_context_from_html( $this->render_form_html( $incompatible_form_id ) );
		Feedback::$form_ids[ $feedback_id ] = $incompatible_form_id;
		$request_count                      = 0;
		$http_mock                          = static function ( $preempt ) use ( &$request_count ) {
			++$request_count;
			return $preempt;
		};

		add_filter( 'pre_http_request', $http_mock );
		$this->run_subscription_callback( $feedback_id );
		remove_filter( 'pre_http_request', $http_mock );

		$this->assertTrue( JetpackForms::is_target_submission( $feedback_id ) );
		$this->assertSame( 0, $request_count );
		$this->assertSame( '', get_post_meta( $feedback_id, '_ran_emailoctopus_subscription_status', true ) );
		$this->assertSame(
			get_permalink( $success_page_id ),
			JetpackForms::redirect_contact_form( 'https://example.org/original/', 123, $feedback_id )
		);
	}

	/**
	 * A selected but invalid saved form disables all side-effect routing.
	 *
	 * @dataProvider invalid_target_provider
	 *
	 * @param string $invalidity How to invalidate the selected saved form.
	 * @return void
	 */
	public function test_nonzero_invalid_target_disables_side_effect_routing( $invalidity ) {
		$form_id = $this->configure_portable_form();

		if ( 'draft' === $invalidity ) {
			wp_update_post(
				array(
					'ID'          => $form_id,
					'post_status' => 'draft',
				)
			);
		} else {
			wp_update_post(
				array(
					'ID'           => $form_id,
					'post_content' => '<!-- wp:paragraph --><p>No form here.</p><!-- /wp:paragraph -->',
				)
			);
		}

		$_POST = array(
			JetpackForms::TARGET_FIELD => 'unsigned-without-profile-or-ref',
		);

		$this->assertFalse( IntegrationResolver::is_portable_available() );
		$this->assertSame( '<form></form>', $this->render_form_html( $form_id ) );
		$this->assertFalse( JetpackForms::is_target_submission() );
		$this->assertTrue( JetpackForms::disable_ajax_for_contact_form( true ) );
	}

	/**
	 * @return array<string,array{string}>
	 */
	public function invalid_target_provider() {
		return array(
			'draft target'                 => array( 'draft' ),
			'invalid saved-form structure' => array( 'structure' ),
		);
	}

	/**
	 * Jetpack-rejected portable feedback never reaches EmailOctopus.
	 *
	 * @dataProvider rejected_feedback_status_provider
	 *
	 * @param string $status Rejected feedback status.
	 * @return void
	 */
	public function test_rejected_portable_feedback_has_no_emailoctopus_side_effect( $status ) {
		$form_id     = $this->configure_portable_form();
		$feedback_id = self::factory()->post->create( array( 'post_status' => $status ) );
		$settings    = Settings::get_all();

		$settings['emailoctopus_list_id']      = 'newsletter-list';
		$settings['emailoctopus_email_source'] = 'email';
		$settings['newsletter_source']         = 'join_our_newsletter';
		update_option( Settings::OPTION_NAME, $settings );
		update_option( 'emailoctopus_api_key', 'test-api-key' );

		$_POST                              = $this->get_context_from_html( $this->render_form_html( $form_id ) );
		Feedback::$form_ids[ $feedback_id ] = $form_id;
		$request_count                      = 0;
		$http_mock                          = static function ( $preempt ) use ( &$request_count ) {
			++$request_count;
			return $preempt;
		};

		add_filter( 'pre_http_request', $http_mock );
		$this->run_subscription_callback( $feedback_id );
		remove_filter( 'pre_http_request', $http_mock );

		$this->assertSame( 0, $request_count );
		$this->assertSame( '', get_post_meta( $feedback_id, '_ran_emailoctopus_subscription_status', true ) );
	}

	/**
	 * Valid page and post routes share one subscription and redirect profile.
	 *
	 * @dataProvider portable_route_type_provider
	 *
	 * @param string $post_type Route post type.
	 * @return void
	 */
	public function test_valid_portable_routes_subscribe_and_redirect( $post_type ) {
		$form_id         = $this->configure_portable_form();
		$route_id        = self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_status' => 'publish',
			)
		);
		$success_page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		$feedback_id     = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$settings        = Settings::get_all();

		$settings['success_page_id']           = $success_page_id;
		$settings['emailoctopus_list_id']      = 'newsletter-list';
		$settings['emailoctopus_email_source'] = 'email';
		$settings['newsletter_source']         = 'join_our_newsletter';
		update_option( Settings::OPTION_NAME, $settings );
		update_option( 'emailoctopus_api_key', 'test-api-key' );

		$this->go_to( get_permalink( $route_id ) );
		$_POST                              = $this->get_context_from_html( $this->render_form_html( $form_id ) );
		$_POST['contact-form-id']           = (string) $route_id;
		Feedback::$form_ids[ $feedback_id ] = $form_id;
		$request_count                      = 0;
		$http_mock                          = static function () use ( &$request_count ) {
			++$request_count;

			return array(
				'headers'  => array(),
				'body'     => wp_json_encode( array( 'status' => 'PENDING' ) ),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
			);
		};

		add_filter( 'pre_http_request', $http_mock, 10, 3 );
		$this->run_subscription_callback( $feedback_id );
		remove_filter( 'pre_http_request', $http_mock, 10 );

		$redirect = JetpackForms::redirect_contact_form( 'https://example.org/original/', $route_id, $feedback_id );

		$this->assertSame( 1, $request_count );
		$this->assertSame( 'pending', get_post_meta( $feedback_id, '_ran_emailoctopus_subscription_status', true ) );
		$this->assertStringStartsWith( get_permalink( $success_page_id ), $redirect );
		$this->assertStringContainsString( SubmissionMessages::RESULT_QUERY_ARG . '=', $redirect );
	}

	/**
	 * @return array<string,array{string}>
	 */
	public function portable_route_type_provider() {
		return array(
			'page route' => array( 'page' ),
			'post route' => array( 'post' ),
		);
	}

	/**
	 * @return array<string,array{string}>
	 */
	public function rejected_feedback_status_provider() {
		return array(
			'Akismet spam'             => array( 'spam' ),
			'disallowed content trash' => array( 'trash' ),
		);
	}

		/**
		 * Outcome tokens carry the profile while old string tokens still render.
		 *
		 * @return void
		 */
	public function test_result_token_carries_profile_and_accepts_legacy_string_value() {
		$this->configure_portable_form();
		$success_page_id             = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$feedback_id                 = self::factory()->post->create();
		$settings                    = Settings::get_all();
		$settings['success_page_id'] = $success_page_id;
		update_option( Settings::OPTION_NAME, $settings );
		update_post_meta( $feedback_id, '_ran_emailoctopus_subscription_status', 'pending' );

		$redirect = SubmissionMessages::add_result_to_redirect( get_permalink( $success_page_id ), $feedback_id, 'default' );
		parse_str( (string) wp_parse_url( $redirect, PHP_URL_QUERY ), $query_args );
		$token  = $query_args[ SubmissionMessages::RESULT_QUERY_ARG ];
		$result = get_transient( SubmissionMessages::RESULT_TRANSIENT_PREFIX . $token );

		$this->assertSame( 'default', $result['profile_id'] );
		$this->assertSame( 'pending', $result['outcome'] );

		$legacy_token = str_repeat( 'A', 32 );
		set_transient( SubmissionMessages::RESULT_TRANSIENT_PREFIX . $legacy_token, 'pending', MINUTE_IN_SECONDS );
		$this->go_to( get_permalink( $success_page_id ) );
		$_GET[ SubmissionMessages::RESULT_QUERY_ARG ] = $legacy_token;

		$this->assertStringContainsString( 'There’s one more step', SubmissionMessages::render_shortcode() );
	}

		/**
		 * Configure one published saved form.
		 *
		 * @return int
		 */
	private function configure_portable_form() {
		$form_id = $this->create_portable_form();
		$this->configure_portable_forms( array( $form_id ) );

		return $form_id;
	}

	/**
	 * Persist selected saved forms for the shared default profile.
	 *
	 * @param array<int,int>      $form_ids Selected form IDs.
	 * @param array<string,mixed> $overrides Additional settings.
	 * @return void
	 */
	private function configure_portable_forms( $form_ids, $overrides = array() ) {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::get_defaults(),
				$overrides,
				array(
					'target_form_ids' => $form_ids,
				)
			)
		);

		$this->assertTrue( IntegrationResolver::is_portable_available() );
	}

	/**
	 * Create a structurally valid saved form with configurable shared fields.
	 *
	 * @param string $email_label      Email field label.
	 * @param string $newsletter_label Newsletter field label.
	 * @return int
	 */
	private function create_portable_form( $email_label = 'Email', $newsletter_label = 'Join our newsletter' ) {
		$fields = '<!-- wp:jetpack/field-email --><div><!-- wp:jetpack/label {"label":"' . esc_attr( $email_label ) . '"} /--></div><!-- /wp:jetpack/field-email -->'
			. '<!-- wp:jetpack/field-checkbox --><div><!-- wp:jetpack/label {"label":"' . esc_attr( $newsletter_label ) . '"} /--></div><!-- /wp:jetpack/field-checkbox -->';

		return self::factory()->post->create(
			array(
				'post_type'    => 'jetpack_form',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:jetpack/contact-form --><div class="wp-block-jetpack-contact-form">' . $fields . '</div><!-- /wp:jetpack/contact-form -->',
			)
		);
	}

		/**
		 * Render minimal form HTML inside the supplied reference context.
		 *
		 * @param int $form_id Saved form ID.
		 * @return string
		 */
	private function render_form_html( $form_id ) {
		$block = $this->get_reference_block( $form_id );
		JetpackForms::before_render_block( null, $block );
		$html = JetpackForms::mark_target_form_submission( '<form></form>' );
		JetpackForms::after_render_block( '', $block );

		return $html;
	}

		/**
		 * @param int $form_id Saved form ID.
		 * @return array<string,mixed>
		 */
	private function get_reference_block( $form_id ) {
		return array(
			'blockName' => 'jetpack/contact-form',
			'attrs'     => array( 'ref' => $form_id ),
		);
	}

		/**
		 * Extract hidden marker values from rendered HTML.
		 *
		 * @param string $html Rendered form HTML.
		 * @return array<string,string>
		 */
	private function get_context_from_html( $html ) {
		preg_match_all( '/name="([^"]+)" value="([^"]*)"/', $html, $matches, PREG_SET_ORDER );
		$context = array();

		foreach ( $matches as $match ) {
			$context[ $match[1] ] = html_entity_decode( $match[2], ENT_QUOTES );
		}

		return $context;
	}

	/**
	 * Invoke the Jetpack post-submission integration with an opted-in visitor.
	 *
	 * @param int $feedback_id Feedback post ID.
	 * @return void
	 */
	private function run_subscription_callback( $feedback_id ) {
		JetpackForms::subscribe_newsletter_opt_in(
			$feedback_id,
			array(),
			'',
			'',
			array(),
			array(
				'1_Email'                => 'reader@example.com',
				'2_Join our newsletter!' => 'Yes',
			),
			array()
		);
	}
}
