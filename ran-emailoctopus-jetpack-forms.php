<?php
/**
 * Plugin Name: RAN EmailOctopus for Jetpack Forms
 * Plugin URI: https://github.com/RocketsAreNostalgic/ran-emailoctopus-jetpack-forms
 * Description: EmailOctopus subscriptions for selected Jetpack forms.
 * x-release-please-start-version
 * Version: 1.1.0
 * x-release-please-end
 * Author: bnjmnrsh
 * Author URI: https://github.com/RocketsAreNostalgic/
 * Text Domain: ran-emailoctopus-jetpack-forms
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 8.0
 * Requires Plugins: jetpack
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// x-release-please-start-version
define( 'RAN_EMAILOCTOPUS_JETPACK_FORMS_VERSION', '1.1.0' );
// x-release-please-end
define( 'RAN_EMAILOCTOPUS_JETPACK_FORMS_PLUGIN_FILE', __FILE__ );
define( 'RAN_EMAILOCTOPUS_JETPACK_FORMS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once RAN_EMAILOCTOPUS_JETPACK_FORMS_PLUGIN_DIR . 'includes/Settings.php';
require_once RAN_EMAILOCTOPUS_JETPACK_FORMS_PLUGIN_DIR . 'includes/IntegrationProfile.php';
require_once RAN_EMAILOCTOPUS_JETPACK_FORMS_PLUGIN_DIR . 'includes/IntegrationResolver.php';
require_once RAN_EMAILOCTOPUS_JETPACK_FORMS_PLUGIN_DIR . 'includes/EmailOctopusApi.php';
require_once RAN_EMAILOCTOPUS_JETPACK_FORMS_PLUGIN_DIR . 'includes/EmailOctopusFieldMapper.php';
require_once RAN_EMAILOCTOPUS_JETPACK_FORMS_PLUGIN_DIR . 'includes/EmailOctopusSubscriber.php';
require_once RAN_EMAILOCTOPUS_JETPACK_FORMS_PLUGIN_DIR . 'includes/SubmissionMessages.php';
require_once RAN_EMAILOCTOPUS_JETPACK_FORMS_PLUGIN_DIR . 'includes/Patterns.php';
require_once RAN_EMAILOCTOPUS_JETPACK_FORMS_PLUGIN_DIR . 'includes/JetpackForms.php';
require_once RAN_EMAILOCTOPUS_JETPACK_FORMS_PLUGIN_DIR . 'includes/HealthCheck.php';
require_once RAN_EMAILOCTOPUS_JETPACK_FORMS_PLUGIN_DIR . 'includes/Admin.php';
require_once RAN_EMAILOCTOPUS_JETPACK_FORMS_PLUGIN_DIR . 'includes/Plugin.php';

register_activation_hook( __FILE__, array( '\\RAN\\EmailOctopusJetpackForms\\Plugin', 'activate' ) );

\RAN\EmailOctopusJetpackForms\Plugin::register();
