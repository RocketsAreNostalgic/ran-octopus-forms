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
	 * Migrate settings when the renamed plugin is activated.
	 *
	 * @return void
	 */
	public static function activate() {
		Settings::upgrade();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		self::activate();

		Patterns::register();
		JetpackForms::register();
		Admin::register();
	}
}
