<?php
/**
 * Plugin Name: RAN Octopus Forms
 * Plugin URI: https://github.com/RocketsAreNostalgic/ran-octopus-forms
 * Description: Site-owned contact form integrations for WordPress sites.
 * x-release-please-start-version
 * Version: 1.0.0
 * x-release-please-end
 * Author: bnjmnrsh
 * Author URI: https://github.com/RocketsAreNostalgic/
 * Text Domain: ran-octopus-forms
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Requires Plugins: jetpack
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 * @package RAN_Octopus_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// x-release-please-start-version
define( 'RAN_OCTOPUS_FORMS_VERSION', '1.0.0' );
// x-release-please-end
define( 'RAN_OCTOPUS_FORMS_PLUGIN_FILE', __FILE__ );
define( 'RAN_OCTOPUS_FORMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once RAN_OCTOPUS_FORMS_PLUGIN_DIR . 'includes/Settings.php';
require_once RAN_OCTOPUS_FORMS_PLUGIN_DIR . 'includes/EmailOctopusApi.php';
require_once RAN_OCTOPUS_FORMS_PLUGIN_DIR . 'includes/EmailOctopusFieldMapper.php';
require_once RAN_OCTOPUS_FORMS_PLUGIN_DIR . 'includes/EmailOctopusSubscriber.php';
require_once RAN_OCTOPUS_FORMS_PLUGIN_DIR . 'includes/SubmissionMessages.php';
require_once RAN_OCTOPUS_FORMS_PLUGIN_DIR . 'includes/Turnstile.php';
require_once RAN_OCTOPUS_FORMS_PLUGIN_DIR . 'includes/Patterns.php';
require_once RAN_OCTOPUS_FORMS_PLUGIN_DIR . 'includes/JetpackForms.php';
require_once RAN_OCTOPUS_FORMS_PLUGIN_DIR . 'includes/HealthCheck.php';
require_once RAN_OCTOPUS_FORMS_PLUGIN_DIR . 'includes/Admin.php';
require_once RAN_OCTOPUS_FORMS_PLUGIN_DIR . 'includes/Plugin.php';

register_activation_hook( __FILE__, array( '\\RAN\\OctopusForms\\Plugin', 'activate' ) );

\RAN\OctopusForms\Plugin::register();
