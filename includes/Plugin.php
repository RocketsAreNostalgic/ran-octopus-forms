<?php
/**
 * RAN EmailOctopus for Jetpack Forms plugin coordinator.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

namespace RAN\EmailOctopusJetpackForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers site-owned form integrations.
 */
final class Plugin {
	/**
	 * Initialize a canonical empty profile store on first activation.
	 *
	 * @return void
	 */
	public static function activate() {
		Settings::initialize_store();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		Patterns::register();
		JetpackForms::register();
		Admin::register();
	}
}
