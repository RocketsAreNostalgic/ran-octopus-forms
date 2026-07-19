<?php
/**
 * Visitor-facing EmailOctopus subscription outcome messages.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

namespace RAN\EmailOctopusJetpackForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carries one subscription result through Jetpack's success redirect.
 */
final class SubmissionMessages {
	/**
	 * Shortcode used on the configured success page.
	 */
	const SHORTCODE = 'ran_emailoctopus_jetpack_forms_subscription_message';

	/**
	 * Previous shortcode retained for existing success-page content.
	 */
	const LEGACY_SHORTCODE = 'ran_octopus_forms_subscription_message';

	/**
	 * Query argument carrying a one-time result token.
	 */
	const RESULT_QUERY_ARG = 'ran_octopus_forms_result';

	/**
	 * Transient key prefix for one-time result tokens.
	 */
	const RESULT_TRANSIENT_PREFIX = 'ran_octopus_forms_result_';

	/**
	 * Register visitor-facing result hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render_shortcode' ) );
		add_shortcode( self::LEGACY_SHORTCODE, array( __CLASS__, 'render_shortcode' ) );
		add_filter( 'render_block_core/shortcode', array( __CLASS__, 'render_shortcode_block' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'disable_cache_for_result' ) );
	}

	/**
	 * Add a one-time subscription result token to the success redirect.
	 *
	 * @param string $redirect   Redirect URL.
	 * @param int    $post_id    Jetpack feedback post ID.
	 * @param string $profile_id Integration profile ID.
	 * @return string
	 */
	public static function add_result_to_redirect( $redirect, $post_id, $profile_id = 'default' ) {
		$outcome = sanitize_key( (string) get_post_meta( $post_id, '_ran_emailoctopus_subscription_status', true ) );
		$profile = IntegrationResolver::get_profile( sanitize_key( (string) $profile_id ) );

		if ( ! self::is_supported_outcome( $outcome ) || null === $profile ) {
			return $redirect;
		}

		$token = wp_generate_password( 32, false, false );

		set_transient(
			self::RESULT_TRANSIENT_PREFIX . $token,
			array(
				'profile_id' => $profile->get_id(),
				'outcome'    => $outcome,
			),
			10 * MINUTE_IN_SECONDS
		);

		return add_query_arg( self::RESULT_QUERY_ARG, $token, $redirect );
	}

	/**
	 * Render the one-time subscription result.
	 *
	 * @return string
	 */
	public static function render_shortcode() {
		if ( ! is_page( absint( Settings::get( 'success_page_id' ) ) ) ) {
			return '';
		}

		$token = isset( $_GET[ self::RESULT_QUERY_ARG ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::RESULT_QUERY_ARG ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- opaque, short-lived display token only.

		if ( ! preg_match( '/^[A-Za-z0-9]{32}$/', $token ) ) {
			return '';
		}

		$result = get_transient( self::RESULT_TRANSIENT_PREFIX . $token );
		delete_transient( self::RESULT_TRANSIENT_PREFIX . $token );

		if ( is_string( $result ) ) {
			// Existing one-time tokens stored only the outcome.
			$profile_id = 'default';
			$outcome    = $result;
		} elseif ( is_array( $result ) ) {
			$profile_id = sanitize_key( (string) ( $result['profile_id'] ?? '' ) );
			$outcome    = sanitize_key( (string) ( $result['outcome'] ?? '' ) );
		} else {
			return '';
		}

		$profile = IntegrationResolver::get_profile( $profile_id );

		if ( ! self::is_supported_outcome( $outcome ) || null === $profile ) {
			return '';
		}

		$message = self::get_profile_outcome_message( $profile, $outcome );

		if ( '' === $message ) {
			return '';
		}

		return sprintf(
			'<p class="ran-emailoctopus-jetpack-forms-subscription-message ran-octopus-forms-subscription-message" role="status">%s</p>',
			esc_html( $message )
		);
	}

	/**
	 * Render this shortcode inside a Core Shortcode block on WordPress 7.
	 *
	 * @param string              $block_content Rendered block content.
	 * @param array<string,mixed> $block Parsed block data.
	 * @return string
	 */
	public static function render_shortcode_block( $block_content, $block ) {
		if ( 'core/shortcode' !== ( $block['blockName'] ?? '' ) ) {
			return $block_content;
		}

		$shortcode_content = isset( $block['innerHTML'] ) && is_string( $block['innerHTML'] ) ? $block['innerHTML'] : $block_content;

		if ( ! has_shortcode( $shortcode_content, self::SHORTCODE ) && ! has_shortcode( $block_content, self::SHORTCODE ) && ! has_shortcode( $shortcode_content, self::LEGACY_SHORTCODE ) && ! has_shortcode( $block_content, self::LEGACY_SHORTCODE ) ) {
			return $block_content;
		}

		return do_shortcode( trim( $shortcode_content ) );
	}

	/**
	 * Prevent cache plugins from storing a visitor-specific result page.
	 *
	 * @return void
	 */
	public static function disable_cache_for_result() {
		if ( ! is_page( absint( Settings::get( 'success_page_id' ) ) ) || empty( $_GET[ self::RESULT_QUERY_ARG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- cache bypass only.
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		nocache_headers();
	}

	/**
	 * Whether an outcome has a configured visitor message.
	 *
	 * @param string $outcome Subscription outcome.
	 * @return bool
	 */
	private static function is_supported_outcome( $outcome ) {
		return in_array( $outcome, array( 'pending', 'subscribed', 'existing', 'failed' ), true );
	}

	/**
	 * Resolve an outcome message through the carried integration profile.
	 *
	 * @param IntegrationProfile $profile Integration profile.
	 * @param string             $outcome EmailOctopus outcome.
	 * @return string
	 */
	private static function get_profile_outcome_message( $profile, $outcome ) {
		$message_keys = array(
			'pending'    => 'emailoctopus_pending_message',
			'subscribed' => 'emailoctopus_subscribed_message',
			'existing'   => 'emailoctopus_existing_message',
			'failed'     => 'emailoctopus_failure_message',
		);
		$key          = $message_keys[ sanitize_key( $outcome ) ] ?? '';
		$config       = $profile->get_configuration();

		if ( '' !== $key && array_key_exists( $key, $config ) ) {
			return sanitize_textarea_field( (string) $config[ $key ] );
		}

		return Settings::get_emailoctopus_outcome_message( $outcome );
	}
}
