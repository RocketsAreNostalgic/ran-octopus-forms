<?php
/**
 * Internal EmailOctopus integration profile.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

namespace RAN\EmailOctopusJetpackForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Route-independent configuration resolved for one logical integration.
 *
 * Option 2 has only the `default` profile, with a canonical collection of
 * compatible saved-form references sharing one integration configuration.
 */
final class IntegrationProfile {
	/**
	 * Profile ID.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Saved Jetpack form IDs.
	 *
	 * @var array<int,int>
	 */
	private $form_ids;

	/**
	 * Filtered integration configuration.
	 *
	 * @var array<string,mixed>
	 */
	private $configuration;

	/**
	 * Create an integration profile.
	 *
	 * @param string              $id            Profile ID.
	 * @param array<int,int>      $form_ids      Saved Jetpack form IDs.
	 * @param array<string,mixed> $configuration Filtered integration configuration.
	 */
	public function __construct( $id, $form_ids, $configuration ) {
		$this->id            = sanitize_key( $id );
		$this->form_ids      = Settings::normalize_target_form_ids( $form_ids );
		$this->configuration = $configuration;
	}

	/**
	 * Get the profile ID.
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get saved Jetpack form IDs.
	 *
	 * @return array<int,int>
	 */
	public function get_form_ids() {
		return $this->form_ids;
	}

	/**
	 * Get the profile's filtered EmailOctopus and outcome configuration.
	 *
	 * @return array<string,mixed>
	 */
	public function get_configuration() {
		return $this->configuration;
	}
}
