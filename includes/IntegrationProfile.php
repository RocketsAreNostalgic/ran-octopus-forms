<?php
/**
 * Immutable runtime view of one integration profile.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

namespace RAN\EmailOctopusJetpackForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carries one explicitly resolved profile through routing and subscription.
 */
final class IntegrationProfile {
	/**
	 * Immutable UUID.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Stored profile revision.
	 *
	 * @var int
	 */
	private $revision;

	/**
	 * Editor-facing label.
	 *
	 * @var string
	 */
	private $label;

	/**
	 * Saved Jetpack form IDs.
	 *
	 * @var array<int,int>
	 */
	private $form_ids;

	/**
	 * Stored canonical profile.
	 *
	 * @var array<string,mixed>
	 */
	private $stored;

	/**
	 * Filtered runtime configuration.
	 *
	 * @var array<string,mixed>
	 */
	private $configuration;

	/**
	 * Build a runtime profile.
	 *
	 * @param string              $id            Immutable profile UUID.
	 * @param array<string,mixed> $stored        Stored canonical profile.
	 * @param array<string,mixed> $configuration Effective filtered configuration.
	 */
	public function __construct( $id, $stored, $configuration ) {
		$this->id            = strtolower( (string) $id );
		$this->revision      = absint( $stored['revision'] ?? 0 );
		$this->label         = sanitize_text_field( (string) ( $stored['label'] ?? '' ) );
		$this->form_ids      = Settings::normalize_form_ids( $stored['form_ids'] ?? array() );
		$this->stored        = $stored;
		$this->configuration = $configuration;
	}

	/**
	 * Get immutable profile UUID.
	 *
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get stored profile revision.
	 *
	 * @return int
	 */
	public function get_revision() {
		return $this->revision;
	}

	/**
	 * Get profile label.
	 *
	 * @return string
	 */
	public function get_label() {
		return $this->label;
	}

	/**
	 * Get selected saved Jetpack form IDs.
	 *
	 * @return array<int,int>
	 */
	public function get_form_ids() {
		return $this->form_ids;
	}

	/**
	 * Get the canonical stored profile.
	 *
	 * @return array<string,mixed>
	 */
	public function get_stored_profile() {
		return $this->stored;
	}

	/**
	 * Get filtered runtime configuration.
	 *
	 * @return array<string,mixed>
	 */
	public function get_configuration() {
		return $this->configuration;
	}

	/**
	 * Get profile-specific success page ID.
	 *
	 * @return int
	 */
	public function get_success_page_id() {
		return absint( $this->stored['success_page_id'] ?? 0 );
	}

	/**
	 * Get one stored outcome message.
	 *
	 * @param string $outcome Supported outcome.
	 * @return string
	 */
	public function get_outcome_message( $outcome ) {
		$outcome  = sanitize_key( $outcome );
		$messages = is_array( $this->stored['messages'] ?? null ) ? $this->stored['messages'] : array();

		return sanitize_textarea_field( (string) ( $messages[ $outcome ] ?? '' ) );
	}
}
