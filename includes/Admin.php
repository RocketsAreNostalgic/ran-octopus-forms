<?php
/**
 * Admin settings page.
 *
 * @package RAN_Octopus_Forms
 */

namespace RAN\OctopusForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the RAN Octopus Forms settings page.
 */
final class Admin {
	/**
	 * Settings page slug.
	 */
	const PAGE_SLUG = 'ran-octopus-forms';

	/**
	 * Health check transient prefix.
	 */
	const HEALTH_TRANSIENT_PREFIX = 'ran_octopus_forms_health_';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'admin_post_ran_octopus_forms_run_health_check', array( __CLASS__, 'run_health_check' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( RAN_OCTOPUS_FORMS_PLUGIN_FILE ), array( __CLASS__, 'plugin_action_links' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );
	}

	/**
	 * Add settings page.
	 *
	 * @return void
	 */
	public static function add_page() {
		add_options_page(
			__( 'RAN Octopus Forms', 'ran-octopus-forms' ),
			__( 'RAN Octopus Forms', 'ran-octopus-forms' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register Settings API option.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'ran_octopus_forms',
			Settings::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::get_defaults(),
			)
		);
	}

	/**
	 * Add plugins-list action links.
	 *
	 * @param array<int|string,string> $links Existing action links.
	 * @return array<int|string,string>
	 */
	public static function plugin_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) ),
			esc_html__( 'Settings', 'ran-octopus-forms' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Add plugins-list metadata links.
	 *
	 * @param array<int,string> $links       Existing metadata links.
	 * @param string            $plugin_file Plugin file.
	 * @return array<int,string>
	 */
	public static function plugin_row_meta( $links, $plugin_file ) {
		if ( plugin_basename( RAN_OCTOPUS_FORMS_PLUGIN_FILE ) !== $plugin_file ) {
			return $links;
		}

		$links[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( 'https://github.com/RocketsAreNostalgic/' ),
			esc_html__( 'RAN GitHub', 'ran-octopus-forms' )
		);

		return $links;
	}

	/**
	 * Enqueue settings-page scripts.
	 *
	 * @param string $hook_suffix Admin screen hook suffix.
	 * @return void
	 */
	public static function enqueue_scripts( $hook_suffix ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix || ! Settings::can_use_turnstile() ) {
			return;
		}

		wp_enqueue_script( 'ran-octopus-forms-turnstile-admin', 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- External service script.
		wp_add_inline_script(
			'ran-octopus-forms-turnstile-admin',
			'window.ranOctopusFormsTurnstileReady=function(){var button=document.getElementById("ran-octopus-forms-run-health-check");if(button){button.disabled=false;}};window.ranOctopusFormsTurnstileExpired=function(){var button=document.getElementById("ran-octopus-forms-run-health-check");if(button){button.disabled=true;}};document.addEventListener("DOMContentLoaded",function(){var widget=document.querySelector("#ran-octopus-forms-health-check-form .cf-turnstile");var button=document.getElementById("ran-octopus-forms-run-health-check");if(widget&&button){button.disabled=true;}});',
			'before'
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = Settings::get_all();
		$health   = self::get_health_result();
		$forms    = EmailOctopusApi::get_forms();
		?>
		<div class="wrap">
			<style>
				.ran-octopus-forms-fieldset {
					padding: 0;
				}

				.ran-octopus-forms-settings-form,
				.ran-octopus-forms-settings-section {
					max-width: 960px;
				}

				.ran-octopus-forms-fieldset > legend.hndle,
				.ran-octopus-forms-settings-section > .hndle {
					box-sizing: border-box;
					color: #1d2327;
					display: block;
					font-size: 14px;
					font-weight: 600;
					line-height: 1.4;
					margin: 0;
					padding: 10px 12px;
					width: 100%;
				}

				.ran-octopus-forms-fieldset > legend.hndle span,
				.ran-octopus-forms-settings-section > .hndle span {
					display: block;
				}

				.ran-octopus-forms-field {
					margin: 0 0 20px;
				}

				.ran-octopus-forms-field:last-child {
					margin-bottom: 0;
				}

				.ran-octopus-forms-field > label,
				.ran-octopus-forms-field-label {
					display: block;
					font-weight: 600;
					margin: 0 0 6px;
				}

				.ran-octopus-forms-details {
					background: #fff;
					border: 1px solid #c3c4c7;
				}

				.ran-octopus-forms-details summary {
					cursor: pointer;
					font-weight: 600;
					padding: 12px;
				}

				.ran-octopus-forms-details .inside {
					border-top: 1px solid #c3c4c7;
				}

				.ran-octopus-forms-health-actions {
					align-items: center;
					display: flex;
					flex-wrap: wrap;
					gap: 12px;
				}

				.ran-octopus-forms-health-actions .button {
					min-height: 65px;
				}

				.ran-octopus-forms-status-pass {
					color: #008a20;
				}

				.ran-octopus-forms-status-error {
					color: #b32d2e;
				}

				.ran-octopus-forms-status-warning {
					color: #996800;
				}

				.ran-octopus-forms-status-skipped {
					color: #646970;
				}
			</style>
			<h1><?php esc_html_e( 'RAN Octopus Forms', 'ran-octopus-forms' ); ?></h1>
			<p><?php esc_html_e( 'Configure the site-owned contact form integration, newsletter opt-in, success redirect, and optional Cloudflare Turnstile protection.', 'ran-octopus-forms' ); ?></p>
			<p>
				<a href="https://developers.cloudflare.com/turnstile/get-started/server-side-validation/"><?php esc_html_e( 'Turnstile validation', 'ran-octopus-forms' ); ?></a>
				<?php echo esc_html_x( '|', 'settings help link separator', 'ran-octopus-forms' ); ?>
				<a href="https://developers.cloudflare.com/turnstile/troubleshooting/testing/"><?php esc_html_e( 'Turnstile testing keys', 'ran-octopus-forms' ); ?></a>
				<?php echo esc_html_x( '|', 'settings help link separator', 'ran-octopus-forms' ); ?>
				<a href="https://emailoctopus.com/api-documentation"><?php esc_html_e( 'EmailOctopus API documentation', 'ran-octopus-forms' ); ?></a>
			</p>

			<form class="ran-octopus-forms-settings-form" method="post" action="options.php">
				<?php settings_fields( 'ran_octopus_forms' ); ?>
				<fieldset class="postbox ran-octopus-forms-fieldset">
					<legend class="hndle"><span><?php esc_html_e( 'Contact flow', 'ran-octopus-forms' ); ?></span></legend>
					<div class="inside">
						<div class="ran-octopus-forms-field">
							<label for="ran-octopus-forms-contact-page"><?php esc_html_e( 'Contact page', 'ran-octopus-forms' ); ?></label>
							<?php self::page_dropdown( 'contact_page_id', 'ran-octopus-forms-contact-page', absint( $settings['contact_page_id'] ) ); ?>
						</div>
						<div class="ran-octopus-forms-field">
							<label for="ran-octopus-forms-success-page"><?php esc_html_e( 'Success page', 'ran-octopus-forms' ); ?></label>
							<?php self::page_dropdown( 'success_page_id', 'ran-octopus-forms-success-page', absint( $settings['success_page_id'] ) ); ?>
						</div>
					</div>
				</fieldset>

				<fieldset class="postbox ran-octopus-forms-fieldset">
					<legend class="hndle"><span><?php esc_html_e( 'EmailOctopus', 'ran-octopus-forms' ); ?></span></legend>
					<div class="inside">
						<?php if ( '' === EmailOctopusApi::get_api_key() ) : ?>
							<p class="notice notice-info inline"><span><?php esc_html_e( 'EmailOctopus is optional and is currently disabled. Add an EmailOctopus API key and select a destination to enable opt-in subscriptions.', 'ran-octopus-forms' ); ?></span></p>
						<?php endif; ?>
						<div class="ran-octopus-forms-field">
							<label for="ran-octopus-forms-emailoctopus-destination"><?php esc_html_e( 'EmailOctopus destination', 'ran-octopus-forms' ); ?></label>
							<?php self::emailoctopus_destination_dropdown( $forms, (string) $settings['emailoctopus_form_id'], (string) $settings['emailoctopus_list_id'] ); ?>
						</div>
						<div class="ran-octopus-forms-field">
							<label for="ran-octopus-forms-emailoctopus-email-source"><?php esc_html_e( 'Email address source', 'ran-octopus-forms' ); ?></label>
							<?php self::emailoctopus_email_source_dropdown( (string) $settings['emailoctopus_email_source'] ); ?>
						</div>
						<div class="ran-octopus-forms-field">
							<label for="ran-octopus-forms-newsletter-source"><?php esc_html_e( 'Newsletter opt-in source', 'ran-octopus-forms' ); ?></label>
							<?php self::newsletter_source_dropdown( (string) $settings['newsletter_source'] ); ?>
						</div>
						<div class="ran-octopus-forms-field">
							<span class="ran-octopus-forms-field-label"><?php esc_html_e( 'Custom field mapping', 'ran-octopus-forms' ); ?></span>
							<?php self::emailoctopus_field_mapping_table( $forms, $settings ); ?>
						</div>
					</div>
				</fieldset>

				<fieldset class="postbox ran-octopus-forms-fieldset">
					<legend class="hndle"><span><?php esc_html_e( 'Turnstile', 'ran-octopus-forms' ); ?></span></legend>
					<div class="inside">
						<?php if ( ! Settings::can_use_turnstile() ) : ?>
							<p class="notice notice-info inline"><span><?php esc_html_e( 'Cloudflare Turnstile is optional and is currently disabled. Enable it and provide valid keys to add verification to the marked RAN form.', 'ran-octopus-forms' ); ?></span></p>
						<?php endif; ?>
						<div class="ran-octopus-forms-field">
							<span class="ran-octopus-forms-field-label"><?php esc_html_e( 'Protection', 'ran-octopus-forms' ); ?></span>
							<label>
								<input name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[turnstile_enabled]" type="checkbox" value="1" <?php checked( ! empty( $settings['turnstile_enabled'] ) ); ?> />
								<?php esc_html_e( 'Enable Cloudflare Turnstile on the contact form', 'ran-octopus-forms' ); ?>
							</label>
						</div>
						<div class="ran-octopus-forms-field">
							<label for="ran-octopus-forms-turnstile-site-key"><?php esc_html_e( 'Turnstile site key', 'ran-octopus-forms' ); ?></label>
							<input class="regular-text" id="ran-octopus-forms-turnstile-site-key" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[turnstile_site_key]" type="text" value="<?php echo esc_attr( (string) $settings['turnstile_site_key'] ); ?>" <?php disabled( defined( 'RAN_OCTOPUS_FORMS_TURNSTILE_SITE_KEY' ) ); ?> />
						</div>
						<div class="ran-octopus-forms-field">
							<label for="ran-octopus-forms-turnstile-secret-key"><?php esc_html_e( 'Turnstile secret key', 'ran-octopus-forms' ); ?></label>
							<input class="regular-text" id="ran-octopus-forms-turnstile-secret-key" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[turnstile_secret_key]" type="password" value="" placeholder="<?php echo esc_attr( empty( $settings['turnstile_secret_key'] ) ? '' : __( 'Stored key hidden', 'ran-octopus-forms' ) ); ?>" <?php disabled( defined( 'RAN_OCTOPUS_FORMS_TURNSTILE_SECRET_KEY' ) ); ?> />
							<p class="description"><?php esc_html_e( 'Leave blank to keep the existing stored secret.', 'ran-octopus-forms' ); ?></p>
						</div>
						<div class="ran-octopus-forms-field">
							<span class="ran-octopus-forms-field-label"><?php esc_html_e( 'Local testing', 'ran-octopus-forms' ); ?></span>
							<?php self::render_turnstile_localhost_details(); ?>
						</div>
					</div>
				</fieldset>
				<?php submit_button( __( 'Save settings', 'ran-octopus-forms' ) ); ?>
			</form>

			<div class="postbox ran-octopus-forms-settings-section">
				<h2 class="hndle"><span><?php esc_html_e( 'Health check', 'ran-octopus-forms' ); ?></span></h2>
				<div class="inside">
					<p><?php esc_html_e( 'Runs safe diagnostics without sending mail, creating feedback posts, or adding EmailOctopus contacts.', 'ran-octopus-forms' ); ?></p>
					<form id="ran-octopus-forms-health-check-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="ran_octopus_forms_run_health_check" />
						<?php wp_nonce_field( 'ran_octopus_forms_run_health_check' ); ?>
						<?php if ( Settings::can_use_turnstile() ) : ?>
							<div class="ran-octopus-forms-health-actions">
								<input id="ran-octopus-forms-run-health-check" class="button button-secondary button-hero" type="submit" value="<?php echo esc_attr__( 'Run health check', 'ran-octopus-forms' ); ?>" />
								<div class="cf-turnstile" data-sitekey="<?php echo esc_attr( Settings::get_turnstile_site_key() ); ?>" data-callback="ranOctopusFormsTurnstileReady" data-expired-callback="ranOctopusFormsTurnstileExpired" data-timeout-callback="ranOctopusFormsTurnstileExpired"></div>
							</div>
						<?php elseif ( Settings::blocks_turnstile_test_keys() ) : ?>
							<p class="notice notice-error"><?php esc_html_e( 'Turnstile test keys are configured while WordPress reports a production environment. The widget will not load until production keys are configured or the environment type is corrected.', 'ran-octopus-forms' ); ?></p>
							<input id="ran-octopus-forms-run-health-check" class="button button-secondary" type="submit" value="<?php echo esc_attr__( 'Run health check', 'ran-octopus-forms' ); ?>" />
						<?php else : ?>
							<input id="ran-octopus-forms-run-health-check" class="button button-secondary" type="submit" value="<?php echo esc_attr__( 'Run health check', 'ran-octopus-forms' ); ?>" />
						<?php endif; ?>
					</form>
					<?php self::render_health_result( $health ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Run health check action.
	 *
	 * @return void
	 */
	public static function run_health_check() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to run this health check.', 'ran-octopus-forms' ) );
		}

		check_admin_referer( 'ran_octopus_forms_run_health_check' );

		$turnstile_token = isset( $_POST['cf-turnstile-response'] ) ? sanitize_text_field( wp_unslash( $_POST['cf-turnstile-response'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Sanitized here after nonce/capability check.

		$health                  = HealthCheck::run( $turnstile_token );
		$health['settings_hash'] = Settings::get_health_hash();

		set_transient( self::HEALTH_TRANSIENT_PREFIX . get_current_user_id(), $health, 10 * MINUTE_IN_SECONDS );

		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&ran_octopus_forms_health=1' ) );
		exit;
	}

	/**
	 * Render a page dropdown.
	 *
	 * @param string $key Setting key.
	 * @param string $id  Field ID.
	 * @param int    $selected Selected page ID.
	 * @return void
	 */
	private static function page_dropdown( $key, $id, $selected ) {
		wp_dropdown_pages(
			array(
				'name'              => Settings::OPTION_NAME . '[' . $key . ']',
				'id'                => $id,
				'selected'          => $selected,
					'show_option_none'  => __( 'Not configured', 'ran-octopus-forms' ),
				'option_none_value' => 0,
			)
		);
	}

	/**
	 * Render EmailOctopus destination picker.
	 *
	 * @param array<int,array<string,string>>|\WP_Error $forms    Available forms.
	 * @param string                                    $form_id  Selected form ID.
	 * @param string                                    $list_id  Selected list ID.
	 * @return void
	 */
	private static function emailoctopus_destination_dropdown( $forms, $form_id, $list_id ) {
		$selected = self::get_emailoctopus_destination_value( $form_id, $list_id );

		if ( is_wp_error( $forms ) ) {
			?>
			<input type="hidden" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[emailoctopus_destination]" value="<?php echo esc_attr( $selected ); ?>" />
			<select class="regular-text" id="ran-octopus-forms-emailoctopus-destination" disabled>
				<option><?php esc_html_e( 'Destinations unavailable', 'ran-octopus-forms' ); ?></option>
			</select>
			<p class="description"><?php echo esc_html( $forms->get_error_message() ); ?></p>
			<?php if ( '' !== $form_id ) : ?>
				<p class="description"><?php echo esc_html( sprintf( __( 'Saved form ID: %s', 'ran-octopus-forms' ), $form_id ) ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $list_id ) : ?>
				<p class="description"><?php echo esc_html( sprintf( __( 'Saved list ID: %s', 'ran-octopus-forms' ), $list_id ) ); ?></p>
			<?php endif; ?>
			<?php
			return;
		}

		$lists          = self::get_emailoctopus_lists_from_forms( $forms );
		$selected_found = false;
		?>
		<select class="regular-text" id="ran-octopus-forms-emailoctopus-destination" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[emailoctopus_destination]">
			<option value=""><?php esc_html_e( 'Select an EmailOctopus destination', 'ran-octopus-forms' ); ?></option>
			<?php if ( ! empty( $forms ) ) : ?>
				<optgroup label="<?php echo esc_attr__( 'Forms', 'ran-octopus-forms' ); ?>">
					<?php foreach ( $forms as $form ) : ?>
						<?php
						$current_form_id = (string) ( $form['id'] ?? '' );

						if ( '' === $current_form_id ) {
							continue;
						}

						$value = 'form:' . $current_form_id;

						if ( $value === $selected ) {
							$selected_found = true;
						}
						?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected, $value ); ?>>
							<?php echo esc_html( self::get_emailoctopus_form_label( $form ) ); ?>
						</option>
					<?php endforeach; ?>
				</optgroup>
			<?php endif; ?>
			<?php if ( ! empty( $lists ) ) : ?>
				<optgroup label="<?php echo esc_attr__( 'Direct list overrides', 'ran-octopus-forms' ); ?>">
					<?php foreach ( $lists as $list ) : ?>
						<?php
						$current_list_id = (string) ( $list['id'] ?? '' );

						if ( '' === $current_list_id ) {
							continue;
						}

						$value = 'list:' . $current_list_id;

						if ( $value === $selected ) {
							$selected_found = true;
						}
						?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected, $value ); ?>>
							<?php echo esc_html( self::get_emailoctopus_list_label( $list ) ); ?>
						</option>
					<?php endforeach; ?>
				</optgroup>
			<?php endif; ?>
			<?php if ( '' !== $selected && ! $selected_found ) : ?>
				<option value="<?php echo esc_attr( $selected ); ?>" selected>
					<?php echo esc_html( self::get_emailoctopus_saved_destination_label( $selected ) ); ?>
				</option>
			<?php endif; ?>
		</select>
		<p class="description"><?php esc_html_e( 'Choose the EmailOctopus form connected to the newsletter list. Use a direct list override only when a form is not the right source of truth.', 'ran-octopus-forms' ); ?></p>
		<?php if ( empty( $forms ) ) : ?>
			<p class="description"><?php esc_html_e( 'No EmailOctopus forms were returned for the configured API key.', 'ran-octopus-forms' ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Get the synthetic EmailOctopus destination setting value.
	 *
	 * @param string $form_id Selected form ID.
	 * @param string $list_id Selected list ID.
	 * @return string
	 */
	private static function get_emailoctopus_destination_value( $form_id, $list_id ) {
		if ( '' !== $list_id ) {
			return 'list:' . $list_id;
		}

		if ( '' !== $form_id ) {
			return 'form:' . $form_id;
		}

		return '';
	}

	/**
	 * Get the fallback label for a saved destination not returned by the API.
	 *
	 * @param string $selected Synthetic selected value.
	 * @return string
	 */
	private static function get_emailoctopus_saved_destination_label( $selected ) {
		if ( 0 === strpos( $selected, 'form:' ) ) {
			return sprintf( __( 'Saved form ID not returned by API: %s', 'ran-octopus-forms' ), substr( $selected, 5 ) );
		}

		if ( 0 === strpos( $selected, 'list:' ) ) {
			return sprintf( __( 'Saved list ID not returned by API: %s', 'ran-octopus-forms' ), substr( $selected, 5 ) );
		}

		return sprintf( __( 'Saved destination not returned by API: %s', 'ran-octopus-forms' ), $selected );
	}

	/**
	 * Get human readable EmailOctopus form label.
	 *
	 * @param array<string,string> $form Form data.
	 * @return string
	 */
	private static function get_emailoctopus_form_label( $form ) {
		$name      = '' !== ( $form['name'] ?? '' ) ? $form['name'] : __( 'Untitled form', 'ran-octopus-forms' );
		$type      = '' !== ( $form['type'] ?? '' ) ? $form['type'] : __( 'unknown type', 'ran-octopus-forms' );
		$list_name = (string) ( $form['list_name'] ?? '' );
		$list_id   = (string) ( $form['list_id'] ?? '' );
		$list      = '' !== $list_name ? $list_name : $list_id;

		if ( '' === $list ) {
			return sprintf( '%s (%s)', $name, $type );
		}

		return sprintf( '%s (%s) - %s', $name, $type, $list );
	}

	/**
	 * Get unique EmailOctopus lists represented by the available forms.
	 *
	 * @param array<int,array<string,string>> $forms Available forms.
	 * @return array<int,array<string,string>>
	 */
	private static function get_emailoctopus_lists_from_forms( $forms ) {
		$lists = array();

		foreach ( $forms as $form ) {
			if ( ! is_array( $form ) ) {
				continue;
			}

			$list_id = (string) ( $form['list_id'] ?? '' );

			if ( '' === $list_id ) {
				continue;
			}

			if ( ! isset( $lists[ $list_id ] ) ) {
				$lists[ $list_id ] = array(
					'id'   => $list_id,
					'name' => (string) ( $form['list_name'] ?? '' ),
				);
				continue;
			}

			if ( '' === ( $lists[ $list_id ]['name'] ?? '' ) && '' !== ( $form['list_name'] ?? '' ) ) {
				$lists[ $list_id ]['name'] = (string) $form['list_name'];
			}
		}

		return array_values( $lists );
	}

	/**
	 * Get human readable EmailOctopus list label.
	 *
	 * @param array<string,string> $list List data.
	 * @return string
	 */
	private static function get_emailoctopus_list_label( $list ) {
		$list_id   = (string) ( $list['id'] ?? '' );
		$list_name = (string) ( $list['name'] ?? '' );

		if ( '' === $list_name ) {
			return $list_id;
		}

		return sprintf( '%s (%s)', $list_name, $list_id );
	}

	/**
	 * Render newsletter opt-in source selector.
	 *
	 * @param string $selected Selected source key.
	 * @return void
	 */
	private static function newsletter_source_dropdown( $selected ) {
		$source_fields   = EmailOctopusFieldMapper::get_source_fields();
		$selected_found  = false;

		if ( empty( $source_fields ) ) {
			?>
			<input type="hidden" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[newsletter_source]" value="<?php echo esc_attr( $selected ); ?>" />
			<select class="regular-text" id="ran-octopus-forms-newsletter-source" disabled>
				<option><?php esc_html_e( 'No Jetpack fields detected', 'ran-octopus-forms' ); ?></option>
			</select>
			<p class="description"><?php esc_html_e( 'Add a Jetpack contact form to the configured contact page, then save settings to choose the opt-in checkbox.', 'ran-octopus-forms' ); ?></p>
			<?php
			return;
		}

		?>
		<select class="regular-text" id="ran-octopus-forms-newsletter-source" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[newsletter_source]">
			<?php foreach ( $source_fields as $source_field ) : ?>
				<?php
				if ( $selected === ( $source_field['key'] ?? '' ) ) {
					$selected_found = true;
				}
				?>
				<option value="<?php echo esc_attr( $source_field['key'] ); ?>" <?php selected( $selected, $source_field['key'] ); ?>>
					<?php echo esc_html( sprintf( '%s (%s)', $source_field['label'], $source_field['type'] ) ); ?>
				</option>
			<?php endforeach; ?>
			<?php if ( '' !== $selected && ! $selected_found ) : ?>
				<option value="<?php echo esc_attr( $selected ); ?>" selected>
					<?php echo esc_html( sprintf( __( 'Saved source not detected: %s', 'ran-octopus-forms' ), $selected ) ); ?>
				</option>
			<?php endif; ?>
		</select>
		<p class="description"><?php esc_html_e( 'Choose the checkbox field that means the submitter opted into the newsletter.', 'ran-octopus-forms' ); ?></p>
		<?php
	}

	/**
	 * Render EmailOctopus email address source selector.
	 *
	 * @param string $selected Selected source key.
	 * @return void
	 */
	private static function emailoctopus_email_source_dropdown( $selected ) {
		$source_fields = EmailOctopusFieldMapper::get_source_fields();

		if ( empty( $source_fields ) ) {
			?>
			<input type="hidden" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[emailoctopus_email_source]" value="<?php echo esc_attr( $selected ); ?>" />
			<select class="regular-text" id="ran-octopus-forms-emailoctopus-email-source" disabled>
				<option><?php esc_html_e( 'No Jetpack fields detected', 'ran-octopus-forms' ); ?></option>
			</select>
			<p class="description"><?php esc_html_e( 'Add a Jetpack contact form to the configured contact page, then save settings to choose the email source.', 'ran-octopus-forms' ); ?></p>
			<?php
			return;
		}

		$selected_found = false;
		?>
		<select class="regular-text" id="ran-octopus-forms-emailoctopus-email-source" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[emailoctopus_email_source]">
			<option value=""><?php esc_html_e( 'Auto-detect email field', 'ran-octopus-forms' ); ?></option>
			<?php foreach ( $source_fields as $source_field ) : ?>
				<?php
				if ( $selected === ( $source_field['key'] ?? '' ) ) {
					$selected_found = true;
				}
				?>
				<option value="<?php echo esc_attr( $source_field['key'] ); ?>" <?php selected( $selected, $source_field['key'] ); ?>>
					<?php echo esc_html( sprintf( '%s (%s)', $source_field['label'], $source_field['type'] ) ); ?>
				</option>
			<?php endforeach; ?>
			<?php if ( '' !== $selected && ! $selected_found ) : ?>
				<option value="<?php echo esc_attr( $selected ); ?>" selected>
					<?php echo esc_html( sprintf( __( 'Saved source not detected: %s', 'ran-octopus-forms' ), $selected ) ); ?>
				</option>
			<?php endif; ?>
		</select>
		<p class="description"><?php esc_html_e( 'RAN Octopus Forms sends this value as EmailOctopus email_address. Auto-detect first tries the Jetpack email field, then any submitted field with email in its key.', 'ran-octopus-forms' ); ?></p>
		<?php
	}

	/**
	 * Render Turnstile localhost testing details.
	 *
	 * @return void
	 */
	private static function render_turnstile_localhost_details() {
		?>
		<details class="ran-octopus-forms-details">
			<summary><?php esc_html_e( 'Localhost test keys', 'ran-octopus-forms' ); ?></summary>
			<div class="inside">
				<p class="description"><?php esc_html_e( 'For local development, use Cloudflare test keys instead of production keys. These work on localhost and always pass validation.', 'ran-octopus-forms' ); ?></p>
				<p>
					<button class="button button-secondary" type="submit" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[turnstile_setup_local_dev]" value="1">
						<?php esc_html_e( 'Set up local dev', 'ran-octopus-forms' ); ?>
					</button>
				</p>
				<p>
					<label for="ran-octopus-forms-turnstile-local-site-key"><strong><?php esc_html_e( 'Site key', 'ran-octopus-forms' ); ?></strong></label><br />
					<input id="ran-octopus-forms-turnstile-local-site-key" class="regular-text code" type="text" readonly value="<?php echo esc_attr( Settings::TURNSTILE_TEST_SITE_KEY ); ?>" />
				</p>
				<p>
					<label for="ran-octopus-forms-turnstile-local-secret-key"><strong><?php esc_html_e( 'Secret key', 'ran-octopus-forms' ); ?></strong></label><br />
					<input id="ran-octopus-forms-turnstile-local-secret-key" class="regular-text code" type="text" readonly value="<?php echo esc_attr( Settings::TURNSTILE_TEST_SECRET_KEY ); ?>" />
				</p>
				<p class="description"><?php esc_html_e( 'The setup button saves these always-pass keys and enables Turnstile. Do not use production keys on localhost unless the Cloudflare widget explicitly allows that hostname. RAN Octopus Forms blocks these test keys when WordPress reports a production environment.', 'ran-octopus-forms' ); ?></p>
				<p>
					<label for="ran-octopus-forms-turnstile-fail-site-key"><strong><?php esc_html_e( 'Failure test site key', 'ran-octopus-forms' ); ?></strong></label><br />
					<input id="ran-octopus-forms-turnstile-fail-site-key" class="regular-text code" type="text" readonly value="<?php echo esc_attr( Settings::TURNSTILE_FAIL_TEST_SITE_KEY ); ?>" />
				</p>
				<p>
					<label for="ran-octopus-forms-turnstile-fail-secret-key"><strong><?php esc_html_e( 'Failure test secret key', 'ran-octopus-forms' ); ?></strong></label><br />
					<input id="ran-octopus-forms-turnstile-fail-secret-key" class="regular-text code" type="text" readonly value="<?php echo esc_attr( Settings::TURNSTILE_FAIL_TEST_SECRET_KEY ); ?>" />
				</p>
				<p class="description"><?php esc_html_e( 'Use the failure test pair only when intentionally testing Turnstile error handling, failed health checks, and retry messaging.', 'ran-octopus-forms' ); ?></p>
			</div>
		</details>
		<?php
	}

	/**
	 * Render EmailOctopus field mapping controls.
	 *
	 * @param array<int,array<string,string>>|\WP_Error $forms    Available forms.
	 * @param array<string,mixed>                       $settings Settings.
	 * @return void
	 */
	private static function emailoctopus_field_mapping_table( $forms, $settings ) {
		$list_id = self::get_selected_emailoctopus_list_id( $forms, $settings );

		if ( '' === $list_id ) {
			?>
			<p class="description"><?php esc_html_e( 'Select an EmailOctopus destination, then save settings to map custom list fields.', 'ran-octopus-forms' ); ?></p>
			<?php
			return;
		}

		$list = EmailOctopusApi::get_list( $list_id );

		if ( is_wp_error( $list ) ) {
			?>
			<p class="description"><?php echo esc_html( $list->get_error_message() ); ?></p>
			<?php
			return;
		}

		$custom_fields = EmailOctopusFieldMapper::get_custom_fields( is_array( $list ) ? $list : array() );
		$source_fields = EmailOctopusFieldMapper::get_source_fields();
		$field_map     = is_array( $settings['emailoctopus_field_map'] ?? null ) ? $settings['emailoctopus_field_map'] : array();
		$transforms    = EmailOctopusFieldMapper::get_transform_options();

		if ( empty( $source_fields ) ) {
			?>
			<p class="description"><?php esc_html_e( 'No Jetpack fields were detected on the configured contact page.', 'ran-octopus-forms' ); ?></p>
			<?php
			return;
		}

		?>
		<details class="ran-octopus-forms-details">
			<summary><?php esc_html_e( 'Map custom EmailOctopus fields', 'ran-octopus-forms' ); ?></summary>
			<div class="inside">
			<?php if ( empty( $custom_fields ) ) : ?>
				<p class="description"><?php esc_html_e( 'The selected list does not expose custom fields beyond EmailAddress.', 'ran-octopus-forms' ); ?></p>
			<?php else : ?>
			<p class="description"><?php esc_html_e( 'Only mapped fields are sent to EmailOctopus. Leave fields set to Do not send unless the mapping is intentional.', 'ran-octopus-forms' ); ?></p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'EmailOctopus field', 'ran-octopus-forms' ); ?></th>
						<th><?php esc_html_e( 'Type', 'ran-octopus-forms' ); ?></th>
						<th><?php esc_html_e( 'Jetpack source', 'ran-octopus-forms' ); ?></th>
						<th><?php esc_html_e( 'Transform', 'ran-octopus-forms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $custom_fields as $field ) : ?>
						<?php
						$tag       = (string) ( $field['tag'] ?? '' );
						$mapping   = is_array( $field_map[ $tag ] ?? null ) ? $field_map[ $tag ] : array();
						$source    = (string) ( $mapping['source'] ?? '' );
						$transform = (string) ( $mapping['transform'] ?? 'as_is' );
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( '' !== ( $field['label'] ?? '' ) ? $field['label'] : $tag ); ?></strong><br />
								<code><?php echo esc_html( $tag ); ?></code>
							</td>
							<td><?php echo esc_html( (string) ( $field['type'] ?? '' ) ); ?></td>
							<td>
								<select name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[emailoctopus_field_map][<?php echo esc_attr( $tag ); ?>][source]">
									<option value=""><?php esc_html_e( 'Do not send', 'ran-octopus-forms' ); ?></option>
									<?php foreach ( $source_fields as $source_field ) : ?>
										<option value="<?php echo esc_attr( $source_field['key'] ); ?>" <?php selected( $source, $source_field['key'] ); ?>>
											<?php echo esc_html( sprintf( '%s (%s)', $source_field['label'], $source_field['type'] ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
							<td>
								<select name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[emailoctopus_field_map][<?php echo esc_attr( $tag ); ?>][transform]">
									<?php foreach ( $transforms as $transform_key => $transform_label ) : ?>
										<option value="<?php echo esc_attr( $transform_key ); ?>" <?php selected( $transform, $transform_key ); ?>>
											<?php echo esc_html( $transform_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
			</div>
		</details>
		<?php
	}

	/**
	 * Resolve the selected EmailOctopus list ID from the destination settings.
	 *
	 * @param array<int,array<string,string>>|\WP_Error $forms    Available forms.
	 * @param array<string,mixed>                       $settings Settings.
	 * @return string
	 */
	private static function get_selected_emailoctopus_list_id( $forms, $settings ) {
		$list_id = (string) ( $settings['emailoctopus_list_id'] ?? '' );

		if ( '' !== $list_id ) {
			return $list_id;
		}

		$form_id = (string) ( $settings['emailoctopus_form_id'] ?? '' );

		if ( '' === $form_id || is_wp_error( $forms ) ) {
			return '';
		}

		foreach ( $forms as $form ) {
			if ( $form_id === (string) ( $form['id'] ?? '' ) ) {
				return sanitize_text_field( (string) ( $form['list_id'] ?? '' ) );
			}
		}

		$form = EmailOctopusApi::get_form( $form_id );

		if ( is_wp_error( $form ) || ! is_array( $form ) ) {
			return '';
		}

		return sanitize_text_field( (string) ( $form['list_id'] ?? '' ) );
	}

	/**
	 * Get the current user's latest health result.
	 *
	 * @return array<string,mixed>|false
	 */
	private static function get_health_result() {
		$result = get_transient( self::HEALTH_TRANSIENT_PREFIX . get_current_user_id() );

		if ( ! is_array( $result ) ) {
			return false;
		}

		if ( ( $result['settings_hash'] ?? '' ) !== Settings::get_health_hash() ) {
			return false;
		}

		return $result;
	}

	/**
	 * Render health result table.
	 *
	 * @param array<string,mixed>|false $health Health result.
	 * @return void
	 */
	private static function render_health_result( $health ) {
		if ( ! is_array( $health ) ) {
			return;
		}

		?>
		<h3><?php echo esc_html( sprintf( __( 'Latest result: %s', 'ran-octopus-forms' ), ucfirst( (string) $health['overall'] ) ) ); ?></h3>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Check', 'ran-octopus-forms' ); ?></th>
					<th><?php esc_html_e( 'Status', 'ran-octopus-forms' ); ?></th>
					<th><?php esc_html_e( 'Detail', 'ran-octopus-forms' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $health['checks'] as $check ) : ?>
					<tr>
						<td><?php echo esc_html( $check['label'] ); ?></td>
						<td><strong class="<?php echo esc_attr( 'ran-octopus-forms-status-' . sanitize_html_class( $check['status'] ) ); ?>"><?php echo esc_html( 'error' === $check['status'] ? __( 'FAIL', 'ran-octopus-forms' ) : strtoupper( $check['status'] ) ); ?></strong></td>
						<td><?php echo esc_html( $check['message'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
