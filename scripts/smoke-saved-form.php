<?php
/**
 * WP-CLI smoke check for independent saved-form integration profiles.
 *
 * This file runs only against the disposable WordPress installation created by
 * the quality workflow. It must not be included in the release archive.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

use RAN\EmailOctopusJetpackForms\EmailOctopusApi;
use RAN\EmailOctopusJetpackForms\IntegrationResolver;
use RAN\EmailOctopusJetpackForms\JetpackForms;
use RAN\EmailOctopusJetpackForms\Settings;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$profile_a_id = '11111111-1111-4111-8111-111111111111';
$profile_b_id = '22222222-2222-4222-8222-222222222222';
$created_ids  = array();

$saved_form_content = '<!-- wp:jetpack/contact-form --><div>'
	. '<!-- wp:jetpack/field-email --><div><!-- wp:jetpack/label {"label":"Email"} /--></div><!-- /wp:jetpack/field-email -->'
	. '<!-- wp:jetpack/field-consent --><div><!-- wp:jetpack/label {"label":"Join newsletter"} /--></div><!-- /wp:jetpack/field-consent -->'
	. '</div><!-- /wp:jetpack/contact-form -->';

foreach ( array( 'Alpha profile smoke form', 'Bravo profile smoke form', 'Unassigned smoke form' ) as $fixture_title ) {
	$saved_form_id = wp_insert_post(
		array(
			'post_type'    => 'jetpack_form',
			'post_status'  => 'publish',
			'post_title'   => $fixture_title,
			'post_content' => $saved_form_content,
		),
		true
	);

	if ( is_wp_error( $saved_form_id ) ) {
		WP_CLI::error( $saved_form_id->get_error_message() );
	}

	$created_ids[] = (int) $saved_form_id;
}

foreach ( array( 'Alpha success page', 'Bravo success page' ) as $fixture_title ) {
	$page_id = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => $fixture_title,
		),
		true
	);

	if ( is_wp_error( $page_id ) ) {
		WP_CLI::error( $page_id->get_error_message() );
	}

	$created_ids[] = (int) $page_id;
}

$form_a_id     = $created_ids[0];
$form_b_id     = $created_ids[1];
$unassigned_id = $created_ids[2];
$success_a_id  = $created_ids[3];
$success_b_id  = $created_ids[4];

$profile = static function ( $label, $form_id, $list_id, $success_page_id, $pending_message ) {
	return array_replace(
		Settings::get_profile_defaults(),
		array(
			'revision'        => 1,
			'label'           => $label,
			'form_ids'        => array( $form_id ),
			'destination'     => array(
				'type' => 'list',
				'id'   => $list_id,
			),
			'email_source'    => 'email',
			'consent_source'  => 'join_newsletter',
			'field_map'       => array(),
			'success_page_id' => $success_page_id,
			'messages'        => array(
				'pending'    => $pending_message,
				'subscribed' => $label . ' subscribed',
				'existing'   => $label . ' existing',
				'failed'     => $label . ' failed',
			),
		)
	);
};

update_option(
	Settings::OPTION_NAME,
	array(
		'schema_version' => Settings::SCHEMA_VERSION,
		'revision'       => 1,
		'profiles'       => array(
			$profile_a_id => $profile( 'Alpha', $form_a_id, 'alpha-list', $success_a_id, 'Alpha pending' ),
			$profile_b_id => $profile( 'Bravo', $form_b_id, 'bravo-list', $success_b_id, 'Bravo pending' ),
		),
	),
	false
);

$stored = Settings::get_store();

if ( is_wp_error( $stored ) || 1 !== $stored['schema_version'] || array( $profile_a_id, $profile_b_id ) !== array_keys( $stored['profiles'] ) ) {
	WP_CLI::error( 'The schema-v1 two-profile store was not read canonically.' );
}

if ( array_key_exists( 'target_form_id', $stored ) || array_key_exists( 'target_form_ids', $stored ) || array_key_exists( 'default', $stored['profiles'] ) ) {
	WP_CLI::error( 'Legacy flat/default-profile state leaked into the schema-v1 smoke store.' );
}

$emailoctopus_requests = 0;
$http_guard            = static function ( $preempt, $args, $url ) use ( &$emailoctopus_requests ) {
	unset( $args );

	if ( 0 === strpos( (string) $url, EmailOctopusApi::API_BASE ) ) {
		++$emailoctopus_requests;
		return new WP_Error( 'ran_emailoctopus_jetpack_forms_smoke_blocked_remote', 'Smoke checks must not contact EmailOctopus.' );
	}

	return $preempt;
};
add_filter( 'pre_http_request', $http_guard, 10, 3 );

$profile_a = IntegrationResolver::get_profile( $profile_a_id );
$profile_b = IntegrationResolver::get_profile( $profile_b_id );

if ( null === $profile_a || null === $profile_b ) {
	WP_CLI::error( 'Both explicit integration profiles must resolve.' );
}

if ( array( $form_a_id ) !== $profile_a->get_form_ids() || array( $form_b_id ) !== $profile_b->get_form_ids() ) {
	WP_CLI::error( 'Saved-form ownership bled between integration profiles.' );
}

$owner_a = IntegrationResolver::get_profile_for_form_id( $form_a_id );
$owner_b = IntegrationResolver::get_profile_for_form_id( $form_b_id );

if ( null === $owner_a || null === $owner_b || $profile_a_id !== $owner_a->get_id() || $profile_b_id !== $owner_b->get_id() ) {
	WP_CLI::error( 'A saved form did not resolve to its sole owning profile.' );
}

if ( null !== IntegrationResolver::get_profile_for_form_id( $unassigned_id ) ) {
	WP_CLI::error( 'The unassigned saved form was incorrectly admitted to a profile.' );
}

if ( ! IntegrationResolver::is_routing_eligible_form_id( $form_a_id, $profile_a_id ) || ! IntegrationResolver::is_routing_eligible_form_id( $form_b_id, $profile_b_id ) ) {
	WP_CLI::error( 'One or both assigned saved forms are not independently routable.' );
}

if ( 'alpha-list' !== $profile_a->get_configuration()['emailoctopus_list_id'] || 'bravo-list' !== $profile_b->get_configuration()['emailoctopus_list_id'] ) {
	WP_CLI::error( 'EmailOctopus destinations bled between profiles.' );
}

if ( $success_a_id !== $profile_a->get_success_page_id() || $success_b_id !== $profile_b->get_success_page_id() ) {
	WP_CLI::error( 'Success destinations bled between profiles.' );
}

if ( 'Alpha pending' !== $profile_a->get_outcome_message( 'pending' ) || 'Bravo pending' !== $profile_b->get_outcome_message( 'pending' ) ) {
	WP_CLI::error( 'Outcome messages bled between profiles.' );
}

$render_context = static function ( $form_id ) {
	$block = array(
		'blockName' => 'jetpack/contact-form',
		'attrs'     => array( 'ref' => $form_id ),
	);

	JetpackForms::before_render_block( null, $block );
	$markup = JetpackForms::mark_target_form_submission( '<form></form>' );
	JetpackForms::after_render_block( '', $block );

	return $markup;
};

$markup_a = $render_context( $form_a_id );
$markup_b = $render_context( $form_b_id );
$markup_u = $render_context( $unassigned_id );

if ( false === strpos( $markup_a, 'value="' . $profile_a_id . '"' ) || false === strpos( $markup_a, 'value="' . $form_a_id . '"' ) ) {
	WP_CLI::error( 'Alpha rendered context does not contain its exact profile and form reference.' );
}

if ( false === strpos( $markup_b, 'value="' . $profile_b_id . '"' ) || false === strpos( $markup_b, 'value="' . $form_b_id . '"' ) ) {
	WP_CLI::error( 'Bravo rendered context does not contain its exact profile and form reference.' );
}

if ( '<form></form>' !== $markup_u ) {
	WP_CLI::error( 'The unassigned saved form received integration context.' );
}

remove_filter( 'pre_http_request', $http_guard, 10 );

if ( 0 !== $emailoctopus_requests ) {
	WP_CLI::error( 'Profile isolation smoke attempted to contact EmailOctopus.' );
}

WP_CLI::success(
	sprintf(
		'Profiles %1$s and %2$s resolved forms %3$d and %4$d independently; unassigned form %5$d remained native; no EmailOctopus request ran.',
		$profile_a_id,
		$profile_b_id,
		$form_a_id,
		$form_b_id,
		$unassigned_id
	)
);
