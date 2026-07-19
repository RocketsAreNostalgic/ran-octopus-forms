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
 * Option 1 has only the `default` profile and one form reference. Keeping form
 * IDs as a collection lets later roadmap options add compatible references
 * without changing the runtime contract.
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
		$this->form_ids      = array_values( array_filter( array_map( 'absint', $form_ids ) ) );
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
	 * Alias describing the collection's role for callers.
	 *
	 * @return array<int,int>
	 */
	public function get_target_form_ids() {
		return $this->get_form_ids();
	}

	/**
	 * Get the first selected form ID.
	 *
	 * @return int
	 */
	public function get_target_form_id() {
		return isset( $this->form_ids[0] ) ? $this->form_ids[0] : 0;
	}

	/**
	 * Alias retained for code that describes Option 1's single primary target.
	 *
	 * @return int
	 */
	public function get_primary_target_form_id() {
		return $this->get_target_form_id();
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
