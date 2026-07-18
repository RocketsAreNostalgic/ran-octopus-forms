<?php
/**
 * WordPress integration-test bootstrap.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
$autoload   = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "WordPress test library is not installed. Set WP_TESTS_DIR before running PHPUnit.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Test bootstrap runs before WordPress is loaded.
	exit( 1 );
}

if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/ran-emailoctopus-jetpack-forms.php';
	}
);

require $_tests_dir . '/includes/bootstrap.php';
