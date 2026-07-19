<?php
/**
 * Profile-aware public configuration contract coverage.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

use RAN\EmailOctopusJetpackForms\IntegrationResolver;
use RAN\EmailOctopusJetpackForms\Settings;

/**
 * Lock the canonical filter surface and immutable profile context.
 */
class RAN_EmailOctopus_Jetpack_Forms_Profile_Public_Contract_Test extends WP_UnitTestCase {
	/**
	 * Immutable test profile UUID.
	 */
	const PROFILE_ID = '88888888-8888-4888-8888-888888888888';

	/**
	 * Canonical public configuration filters.
	 *
	 * @var array<int,string>
	 */
	private $filters = array(
		'ran_emailoctopus_jetpack_forms_emailoctopus_form_id',
		'ran_emailoctopus_jetpack_forms_emailoctopus_list_id',
		'ran_emailoctopus_jetpack_forms_contact_success_url',
		'ran_emailoctopus_jetpack_forms_emailoctopus_email_source',
		'ran_emailoctopus_jetpack_forms_emailoctopus_field_map',
		'ran_emailoctopus_jetpack_forms_newsletter_source',
	);

	/**
	 * Reset the canonical store.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		delete_option( Settings::OPTION_NAME );
	}

	/**
	 * Each filter receives effective value plus immutable profile UUID.
	 *
	 * @return void
	 */
	public function test_six_configuration_filters_receive_profile_id() {
		$success_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);
		$this->store_profile( $success_id );
		$seen      = array();
		$callbacks = array();

		foreach ( $this->filters as $filter_name ) {
			$callbacks[ $filter_name ] = static function ( $value, $profile_id ) use ( &$seen, $filter_name ) {
				$seen[ $filter_name ] = array( $value, $profile_id );
				return $value;
			};
			add_filter( $filter_name, $callbacks[ $filter_name ], 10, 2 );
		}

		$profile = IntegrationResolver::get_profile( self::PROFILE_ID );

		foreach ( $callbacks as $filter_name => $callback ) {
			remove_filter( $filter_name, $callback, 10 );
		}

		$this->assertNotNull( $profile );
		$this->assertSame( $this->filters, array_keys( $seen ) );
		foreach ( $seen as $arguments ) {
			$this->assertCount( 2, $arguments );
			$this->assertSame( self::PROFILE_ID, $arguments[1] );
		}
	}

	/**
	 * Source declares exactly the six canonical filters and no legacy aliases.
	 *
	 * @return void
	 */
	public function test_source_contains_exactly_six_canonical_filters_without_legacy_aliases() {
		$includes_directory = dirname( __DIR__ ) . '/includes';
		$source             = '';

		foreach ( glob( $includes_directory . '/*.php' ) as $file ) {
			$contents = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local test fixture inspection, not a URL.
			$source  .= is_string( $contents ) ? $contents : '';
		}

		preg_match_all( "/apply_filters\\(\\s*'([^']+)'/", $source, $matches );
		$this->assertSame( $this->filters, $matches[1] );
		$this->assertStringNotContainsString( 'ran_octopus_forms', $source );
		$this->assertStringNotContainsString( 'ran_forms_settings', $source );
		$this->assertFalse( method_exists( Settings::class, 'normalize_target_form_ids' ) );
		$this->assertFalse( method_exists( Settings::class, 'get_target_form_ids' ) );
		$this->assertFalse( method_exists( IntegrationResolver::class, 'get_target_form_ids' ) );
	}

	/**
	 * Store one canonical profile with non-empty values for all filter inputs.
	 *
	 * @param int $success_id Published success page ID.
	 * @return void
	 */
	private function store_profile( $success_id ) {
		$profile                    = Settings::get_profile_defaults();
		$profile['revision']        = 1;
		$profile['label']           = 'Filter profile';
		$profile['destination']     = array(
			'type' => 'form',
			'id'   => 'provider-form',
		);
		$profile['email_source']    = 'email';
		$profile['consent_source']  = 'consent';
		$profile['field_map']       = array(
			'FirstName' => array(
				'source'    => 'name',
				'transform' => 'first_word',
			),
		);
		$profile['success_page_id'] = $success_id;

		update_option(
			Settings::OPTION_NAME,
			array(
				'schema_version' => Settings::SCHEMA_VERSION,
				'revision'       => 1,
				'profiles'       => array( self::PROFILE_ID => $profile ),
			),
			false
		);
	}
}
