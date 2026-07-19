<?php
/**
 * Integration coverage for explicit EmailOctopus source mapping.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

use RAN\EmailOctopusJetpackForms\EmailOctopusFieldMapper;
use RAN\EmailOctopusJetpackForms\Settings;

/**
 * Ensure subscriptions use only an explicit email source.
 */
class RAN_EmailOctopus_Jetpack_Forms_Email_Source_Mapping_Test extends WP_UnitTestCase {
	/**
	 * Reset persisted integration state.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		delete_option( Settings::OPTION_NAME );
	}

	/**
	 * Fresh settings require administrators to choose both source fields.
	 *
	 * @return void
	 */
	public function test_defaults_leave_email_and_newsletter_sources_empty() {
		$defaults = Settings::get_defaults();

		$this->assertSame( '', $defaults['emailoctopus_email_source'] );
		$this->assertSame( '', $defaults['newsletter_source'] );
	}

	/**
	 * An unconfigured email source must not guess from submitted values.
	 *
	 * @return void
	 */
	public function test_unconfigured_email_source_does_not_fall_back_to_a_submitted_email() {
		$this->assertSame(
			'',
			EmailOctopusFieldMapper::get_email_address(
				array(
					'2_Email address' => 'reader@example.com',
				)
			)
		);
	}

	/**
	 * An explicit label-normalized source resolves Jetpack's numbered key.
	 *
	 * @return void
	 */
	public function test_configured_email_source_resolves_a_numbered_jetpack_key() {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::get_defaults(),
				array(
					'emailoctopus_email_source' => 'email_address',
				)
			)
		);

		$this->assertSame(
			'reader@example.com',
			EmailOctopusFieldMapper::get_email_address(
				array(
					'2_Email address' => 'reader@example.com',
				)
			)
		);
	}

	/**
	 * An existing optional custom source may submit a blank value without payload.
	 *
	 * @return void
	 */
	public function test_blank_optional_custom_value_is_omitted_from_payload() {
		update_option(
			Settings::OPTION_NAME,
			array_merge(
				Settings::get_defaults(),
				array(
					'emailoctopus_field_map' => array(
						'FirstName' => array(
							'source'    => 'first_name',
							'transform' => 'as_is',
						),
						'Country'   => array(
							'source'    => 'country',
							'transform' => 'as_is',
						),
					),
				)
			)
		);

		$this->assertSame(
			array( 'Country' => 'Scotland' ),
			EmailOctopusFieldMapper::build_fields_payload(
				array(
					'3_First name' => '',
					'4_Country'    => 'Scotland',
				)
			)
		);
	}
}
