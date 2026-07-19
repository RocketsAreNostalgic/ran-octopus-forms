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
$saved_form_id      = wp_insert_post(
	array(
		'post_type'    => 'jetpack_form',
		'post_status'  => 'publish',
		'post_title'   => 'Portable smoke form',
		'post_content' => $saved_form_content,
	),
	true
);

if ( is_wp_error( $saved_form_id ) ) {
	WP_CLI::error( $saved_form_id->get_error_message() );
}

update_option(
	Settings::OPTION_NAME,
	array(
		'target_form_id' => $saved_form_id,
	)
);
Settings::upgrade();

$target_form_id = Settings::get_target_form_id();
$is_portable    = IntegrationResolver::is_portable();

if ( (int) $saved_form_id !== $target_form_id ) {
	WP_CLI::error( 'The selected saved-form reference did not resolve as target_form_id.' );
}

if ( ! $is_portable ) {
	WP_CLI::error( 'Saved-form routing is unavailable: ' . IntegrationResolver::get_portability_reason() );
}

WP_CLI::success(
	sprintf(
		'Saved form %d resolved; saved-form routing verified.',
		$target_form_id
	)
);
