<?php
/**
 * Version-specific WP-CLI smoke check for portable saved-form support.
 *
 * This file runs only against the disposable WordPress installation created by
 * the quality workflow. It must not be included in the release archive.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

use RAN\EmailOctopusJetpackForms\IntegrationResolver;
use RAN\EmailOctopusJetpackForms\Settings;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$saved_form_content = '<!-- wp:jetpack/contact-form -->'
	. '<!-- wp:jetpack/field-email {"label":"Email","required":true} /-->'
	. '<!-- wp:jetpack/field-checkbox {"label":"Join our newsletter"} /-->'
	. '<!-- /wp:jetpack/contact-form -->';
$saved_form_ids = array();

foreach ( array( 'First selected smoke form', 'Second selected smoke form', 'Unselected smoke form' ) as $title ) {
	$saved_form_id = wp_insert_post(
		array(
			'post_type'    => 'jetpack_form',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => $saved_form_content,
		),
		true
	);

	if ( is_wp_error( $saved_form_id ) ) {
		WP_CLI::error( $saved_form_id->get_error_message() );
	}

	$saved_form_ids[] = (int) $saved_form_id;
}

update_option(
	Settings::OPTION_NAME,
	array(
		'target_form_id' => $saved_form_ids[0],
	)
);
Settings::upgrade();

$migrated = get_option( Settings::OPTION_NAME, array() );

if ( array( $saved_form_ids[0] ) !== ( $migrated['target_form_ids'] ?? null ) || array_key_exists( 'target_form_id', $migrated ) ) {
	WP_CLI::error( 'The scalar saved-form target did not migrate to the canonical collection.' );
}

update_option(
	Settings::OPTION_NAME,
	array(
		'target_form_ids' => array( $saved_form_ids[1], $saved_form_ids[0], $saved_form_ids[1] ),
	)
);

$target_form_ids = Settings::get_target_form_ids();
$profile         = IntegrationResolver::get_default_profile();

if ( array( $saved_form_ids[0], $saved_form_ids[1] ) !== $target_form_ids ) {
	WP_CLI::error( 'Selected saved-form references were not normalized as an ordered collection.' );
}

if ( $target_form_ids !== $profile->get_form_ids() ) {
	WP_CLI::error( 'The default integration profile did not expose every selected form.' );
}

foreach ( $target_form_ids as $target_form_id ) {
	if ( ! IntegrationResolver::is_routing_eligible_form_id( $target_form_id ) ) {
		WP_CLI::error( 'Selected saved-form routing is unavailable for form ' . $target_form_id . ': ' . IntegrationResolver::get_target_form_reason( $target_form_id ) );
	}
}

if ( IntegrationResolver::is_routing_eligible_form_id( $saved_form_ids[2] ) ) {
	WP_CLI::error( 'The unselected saved form was incorrectly admitted to the integration profile.' );
}

WP_CLI::success(
	sprintf(
		'Saved forms %1$d and %2$d resolved independently; unselected form %3$d remained outside the profile.',
		$saved_form_ids[0],
		$saved_form_ids[1],
		$saved_form_ids[2]
	)
);
