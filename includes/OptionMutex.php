<?php
/**
 * Database-backed option mutex.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

namespace RAN\EmailOctopusJetpackForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serializes short option mutations without relying on WordPress option upserts.
 */
final class OptionMutex {
	/**
	 * Lock lifetime in seconds.
	 */
	const TTL = 30;

	/**
	 * Lock option name.
	 *
	 * @var string
	 */
	private $option_name;

	/**
	 * Clock returning a Unix timestamp.
	 *
	 * @var callable
	 */
	private $clock;

	/**
	 * Token generator.
	 *
	 * @var callable
	 */
	private $token_generator;

	/**
	 * WordPress database connection.
	 *
	 * @var \wpdb
	 */
	private $database;

	/**
	 * Build a mutex.
	 *
	 * The injected seams make expiry and ownership behavior deterministic in tests.
	 *
	 * @param string        $option_name     Unique lock option name.
	 * @param callable|null $clock           Optional clock.
	 * @param callable|null $token_generator Optional token generator.
	 * @param \wpdb|null    $database        Optional database connection.
	 */
	public function __construct( $option_name, $clock = null, $token_generator = null, $database = null ) {
		global $wpdb;

		$this->option_name     = sanitize_key( $option_name );
		$this->clock           = is_callable( $clock ) ? $clock : 'time';
		$this->token_generator = is_callable( $token_generator ) ? $token_generator : 'wp_generate_uuid4';
		$this->database        = is_object( $database ) ? $database : $wpdb;
	}

	/**
	 * Acquire the lock with one bounded retry after a lost race.
	 *
	 * @return string|\WP_Error Ownership token or an actionable error.
	 */
	public function acquire() {
		$token   = (string) call_user_func( $this->token_generator );
		$payload = $this->build_payload( $token );

		if ( '' === $token || false === $payload ) {
			return new \WP_Error( 'ran_emailoctopus_jetpack_forms_lock_token_invalid', __( 'The settings write lock could not generate a valid ownership token.', 'ran-emailoctopus-jetpack-forms' ) );
		}

		for ( $attempt = 0; 2 > $attempt; ++$attempt ) {
			$inserted = $this->database->query(
				$this->database->prepare(
					"INSERT IGNORE INTO {$this->database->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Core options table name is trusted.
					$this->option_name,
					$payload,
					'no'
				)
			);

			if ( 1 === $inserted ) {
				$this->invalidate_option_cache();

				if ( $this->is_owner( $token ) ) {
					return $token;
				}

				continue;
			}

			$observed = $this->read_raw_payload();

			if ( null === $observed ) {
				continue;
			}

			$decoded = $this->decode_payload( $observed );

			if ( ! is_array( $decoded ) ) {
				return new \WP_Error( 'ran_emailoctopus_jetpack_forms_lock_malformed', __( 'The settings write lock is malformed and was not changed.', 'ran-emailoctopus-jetpack-forms' ) );
			}

			if ( $decoded['expires_at'] > $this->now() ) {
				return new \WP_Error( 'ran_emailoctopus_jetpack_forms_lock_busy', __( 'Another settings change is currently being saved. Please retry in a moment.', 'ran-emailoctopus-jetpack-forms' ) );
			}

			$reclaimed = $this->database->query(
				$this->database->prepare(
					"UPDATE {$this->database->options} SET option_value = %s, autoload = %s WHERE option_name = %s AND option_value = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Core options table name is trusted.
					$payload,
					'no',
					$this->option_name,
					$observed
				)
			);

			if ( 1 === $reclaimed ) {
				$this->invalidate_option_cache();

				if ( $this->is_owner( $token ) ) {
					return $token;
				}
			}
		}

		return new \WP_Error( 'ran_emailoctopus_jetpack_forms_lock_busy', __( 'Another settings change won the write lock. Please retry.', 'ran-emailoctopus-jetpack-forms' ) );
	}

	/**
	 * Confirm this token still owns an unexpired lock.
	 *
	 * @param string $token Ownership token.
	 * @return bool
	 */
	public function is_owner( $token ) {
		$decoded = $this->decode_payload( $this->read_raw_payload() );

		return is_array( $decoded )
			&& $decoded['expires_at'] > $this->now()
			&& hash_equals( $decoded['token'], (string) $token );
	}

	/**
	 * Release only the exact payload currently owned by this token.
	 *
	 * @param string $token Ownership token.
	 * @return bool Whether this caller removed its lock.
	 */
	public function release( $token ) {
		$observed = $this->read_raw_payload();
		$decoded  = $this->decode_payload( $observed );

		if ( null === $observed || ! is_array( $decoded ) || ! hash_equals( $decoded['token'], (string) $token ) ) {
			return false;
		}

		$deleted = $this->database->query(
			$this->database->prepare(
				"DELETE FROM {$this->database->options} WHERE option_name = %s AND option_value = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Core options table name is trusted.
				$this->option_name,
				$observed
			)
		);

		if ( 1 === $deleted ) {
			$this->invalidate_option_cache();
			return true;
		}

		return false;
	}

	/**
	 * Create a stable serialized lock payload.
	 *
	 * @param string $token Ownership token.
	 * @return string|false
	 */
	private function build_payload( $token ) {
		return wp_json_encode(
			array(
				'token'      => $token,
				'expires_at' => $this->now() + self::TTL,
			)
		);
	}

	/**
	 * Decode a lock payload.
	 *
	 * @param string|null $payload Raw payload.
	 * @return array{token:string,expires_at:int}|null
	 */
	private function decode_payload( $payload ) {
		if ( ! is_string( $payload ) || '' === $payload ) {
			return null;
		}

		$decoded = json_decode( $payload, true );

		if ( ! is_array( $decoded ) || ! isset( $decoded['token'], $decoded['expires_at'] ) || ! is_string( $decoded['token'] ) || '' === $decoded['token'] || ! is_numeric( $decoded['expires_at'] ) ) {
			return null;
		}

		return array(
			'token'      => $decoded['token'],
			'expires_at' => (int) $decoded['expires_at'],
		);
	}

	/**
	 * Read the current row without the WordPress option cache.
	 *
	 * @return string|null
	 */
	private function read_raw_payload() {
		$value = $this->database->get_var(
			$this->database->prepare(
				"SELECT option_value FROM {$this->database->options} WHERE option_name = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Core options table name is trusted.
				$this->option_name
			)
		);

		return null === $value ? null : (string) $value;
	}

	/**
	 * Get the injected current time.
	 *
	 * @return int
	 */
	private function now() {
		return (int) call_user_func( $this->clock );
	}

	/**
	 * Keep direct database changes out of the options/notoptions caches.
	 *
	 * @return void
	 */
	private function invalidate_option_cache() {
		wp_cache_delete( $this->option_name, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
	}
}
