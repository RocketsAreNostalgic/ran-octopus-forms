<?php
/**
 * WordPress integration-test bootstrap.
 *
 * @package RAN_Octopus_Forms
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "WordPress test library is not installed. Set WP_TESTS_DIR before running PHPUnit.\n" );
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/ran-octopus-forms.php';
	}
);

require $_tests_dir . '/includes/bootstrap.php';
