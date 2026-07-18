<?php
/**
 * Admin settings page.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

namespace RAN\EmailOctopusJetpackForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the RAN EmailOctopus for Jetpack Forms settings page.
 */
final class Admin {
	/**
	 * Settings page slug.
	 */
	const PAGE_SLUG = 'ran-emailoctopus-jetpack-forms';

	/**
	 * Health check transient prefix.
	 */
	const HEALTH_TRANSIENT_PREFIX = 'ran_emailoctopus_jetpack_forms_health_';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_ran_emailoctopus_jetpack_forms_run_health_check', array( __CLASS__, 'run_health_check' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( RAN_EMAILOCTOPUS_JETPACK_FORMS_PLUGIN_FILE ), array( __CLASS__, 'plugin_action_links' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );
	}

	/**
	 * Add settings page.
	 *
	 * @return void
	 */
	public static function add_page() {
		add_options_page(
			__( 'RAN EmailOctopus for Jetpack Forms', 'ran-emailoctopus-jetpack-forms' ),
			__( 'RAN EmailOctopus for Jetpack Forms', 'ran-emailoctopus-jetpack-forms' ),
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
			'ran_emailoctopus_jetpack_forms',
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
			esc_html__( 'Settings', 'ran-emailoctopus-jetpack-forms' )
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
		if ( plugin_basename( RAN_EMAILOCTOPUS_JETPACK_FORMS_PLUGIN_FILE ) !== $plugin_file ) {
			return $links;
		}

		$links[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( 'https://github.com/RocketsAreNostalgic/' ),
			esc_html__( 'RAN GitHub', 'ran-emailoctopus-jetpack-forms' )
		);

		return $links;
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
				.ran-emailoctopus-jetpack-forms-fieldset {
					padding: 0;
				}

				.ran-emailoctopus-jetpack-forms-settings-form,
				.ran-emailoctopus-jetpack-forms-settings-section {
					max-width: 960px;
				}

				.ran-emailoctopus-jetpack-forms-fieldset > legend.hndle,
				.ran-emailoctopus-jetpack-forms-settings-section > .hndle {
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

				.ran-emailoctopus-jetpack-forms-fieldset > legend.hndle span,
				.ran-emailoctopus-jetpack-forms-settings-section > .hndle span {
					display: block;
				}

				.ran-emailoctopus-jetpack-forms-field {
					margin: 0 0 20px;
				}

				.ran-emailoctopus-jetpack-forms-field:last-child {
					margin-bottom: 0;
				}

				.ran-emailoctopus-jetpack-forms-field > label,
				.ran-emailoctopus-jetpack-forms-field-label {
					display: block;
					font-weight: 600;
					margin: 0 0 6px;
				}

				.ran-emailoctopus-jetpack-forms-details {
					background: #fff;
					border: 1px solid #c3c4c7;
				}

				.ran-emailoctopus-jetpack-forms-details summary {
					cursor: pointer;
					font-weight: 600;
					padding: 12px;
				}

				.ran-emailoctopus-jetpack-forms-details .inside {
					border-top: 1px solid #c3c4c7;
				}

				.ran-emailoctopus-jetpack-forms-status-pass {
					color: #008a20;
				}

				.ran-emailoctopus-jetpack-forms-status-error {
					color: #b32d2e;
				}

				.ran-emailoctopus-jetpack-forms-status-warning {
					color: #996800;
				}

				.ran-emailoctopus-jetpack-forms-status-skipped {
					color: #646970;
				}
			</style>
			<h1><?php esc_html_e( 'RAN EmailOctopus for Jetpack Forms', 'ran-emailoctopus-jetpack-forms' ); ?></h1>
			<p><?php esc_html_e( 'Configure the site-owned contact form integration, newsletter opt-in, and success redirect.', 'ran-emailoctopus-jetpack-forms' ); ?></p>
			<p>
				<a href="https://emailoctopus.com/api-documentation"><?php esc_html_e( 'EmailOctopus API documentation', 'ran-emailoctopus-jetpack-forms' ); ?></a>
			</p>

			<form class="ran-emailoctopus-jetpack-forms-settings-form" method="post" action="options.php">
				<?php settings_fields( 'ran_emailoctopus_jetpack_forms' ); ?>
				<fieldset class="postbox ran-emailoctopus-jetpack-forms-fieldset">
					<legend class="hndle"><span><?php esc_html_e( 'Contact flow', 'ran-emailoctopus-jetpack-forms' ); ?></span></legend>
					<div class="inside">
						<div class="ran-emailoctopus-jetpack-forms-field">
							<label for="ran-emailoctopus-jetpack-forms-contact-page"><?php esc_html_e( 'Contact page', 'ran-emailoctopus-jetpack-forms' ); ?></label>
							<?php self::page_dropdown( 'contact_page_id', 'ran-emailoctopus-jetpack-forms-contact-page', absint( $settings['contact_page_id'] ) ); ?>
						</div>
						<div class="ran-emailoctopus-jetpack-forms-field">
							<label for="ran-emailoctopus-jetpack-forms-success-page"><?php esc_html_e( 'Success page', 'ran-emailoctopus-jetpack-forms' ); ?></label>
							<?php self::page_dropdown( 'success_page_id', 'ran-emailoctopus-jetpack-forms-success-page', absint( $settings['success_page_id'] ) ); ?>
						</div>
					</div>
				</fieldset>

				<fieldset class="postbox ran-emailoctopus-jetpack-forms-fieldset">
					<legend class="hndle"><span><?php esc_html_e( 'Newsletter outcome messages', 'ran-emailoctopus-jetpack-forms' ); ?></span></legend>
					<div class="inside">
						<p class="description"><?php esc_html_e( 'On the configured success page, add a Shortcode block and paste the shortcode below. The block displays the appropriate message only after a visitor has chosen the newsletter option.', 'ran-emailoctopus-jetpack-forms' ); ?></p>
						<div class="ran-emailoctopus-jetpack-forms-field">
							<label for="ran-emailoctopus-jetpack-forms-subscription-message-shortcode"><?php esc_html_e( 'Subscription message shortcode', 'ran-emailoctopus-jetpack-forms' ); ?></label>
							<input class="large-text code" id="ran-emailoctopus-jetpack-forms-subscription-message-shortcode" type="text" value="<?php echo esc_attr( '[' . SubmissionMessages::SHORTCODE . ']' ); ?>" readonly="readonly" />
							<p class="description"><?php esc_html_e( 'Copy and paste this into a Shortcode block on the selected success page.', 'ran-emailoctopus-jetpack-forms' ); ?></p>
						</div>
						<details class="ran-emailoctopus-jetpack-forms-details">
							<summary><?php esc_html_e( 'Customize subscription messages', 'ran-emailoctopus-jetpack-forms' ); ?></summary>
							<div class="inside">
								<p class="description"><?php esc_html_e( 'These messages are shown to visitors according to the EmailOctopus subscription result.', 'ran-emailoctopus-jetpack-forms' ); ?></p>
								<?php self::subscription_message_field( 'emailoctopus_pending_message', 'ran-emailoctopus-jetpack-forms-pending-message', __( 'Confirmation required', 'ran-emailoctopus-jetpack-forms' ), (string) $settings['emailoctopus_pending_message'] ); ?>
								<?php self::subscription_message_field( 'emailoctopus_subscribed_message', 'ran-emailoctopus-jetpack-forms-subscribed-message', __( 'Subscription complete', 'ran-emailoctopus-jetpack-forms' ), (string) $settings['emailoctopus_subscribed_message'] ); ?>
								<?php self::subscription_message_field( 'emailoctopus_existing_message', 'ran-emailoctopus-jetpack-forms-existing-message', __( 'Existing email address', 'ran-emailoctopus-jetpack-forms' ), (string) $settings['emailoctopus_existing_message'] ); ?>
								<?php self::subscription_message_field( 'emailoctopus_failure_message', 'ran-emailoctopus-jetpack-forms-failure-message', __( 'Subscription problem', 'ran-emailoctopus-jetpack-forms' ), (string) $settings['emailoctopus_failure_message'] ); ?>
							</div>
						</details>
					</div>
				</fieldset>

				<fieldset class="postbox ran-emailoctopus-jetpack-forms-fieldset">
					<legend class="hndle"><span><?php esc_html_e( 'EmailOctopus', 'ran-emailoctopus-jetpack-forms' ); ?></span></legend>
					<div class="inside">
						<?php if ( '' === EmailOctopusApi::get_api_key() ) : ?>
							<p class="notice notice-info inline"><span><?php esc_html_e( 'EmailOctopus is optional and is currently disabled. Add an EmailOctopus API key and select a destination to enable opt-in subscriptions.', 'ran-emailoctopus-jetpack-forms' ); ?></span></p>
						<?php endif; ?>
						<div class="ran-emailoctopus-jetpack-forms-field">
							<label for="ran-emailoctopus-jetpack-forms-emailoctopus-destination"><?php esc_html_e( 'EmailOctopus destination', 'ran-emailoctopus-jetpack-forms' ); ?></label>
							<?php self::emailoctopus_destination_dropdown( $forms, (string) $settings['emailoctopus_form_id'], (string) $settings['emailoctopus_list_id'] ); ?>
						</div>
						<div class="ran-emailoctopus-jetpack-forms-field">
							<label for="ran-emailoctopus-jetpack-forms-emailoctopus-email-source"><?php esc_html_e( 'Email address source', 'ran-emailoctopus-jetpack-forms' ); ?></label>
							<?php self::emailoctopus_email_source_dropdown( (string) $settings['emailoctopus_email_source'] ); ?>
						</div>
						<div class="ran-emailoctopus-jetpack-forms-field">
							<label for="ran-emailoctopus-jetpack-forms-newsletter-source"><?php esc_html_e( 'Newsletter opt-in source', 'ran-emailoctopus-jetpack-forms' ); ?></label>
							<?php self::newsletter_source_dropdown( (string) $settings['newsletter_source'] ); ?>
						</div>
						<div class="ran-emailoctopus-jetpack-forms-field">
							<span class="ran-emailoctopus-jetpack-forms-field-label"><?php esc_html_e( 'Custom field mapping', 'ran-emailoctopus-jetpack-forms' ); ?></span>
							<?php self::emailoctopus_field_mapping_table( $forms, $settings ); ?>
						</div>
					</div>
				</fieldset>

				<?php submit_button( __( 'Save settings', 'ran-emailoctopus-jetpack-forms' ) ); ?>
			</form>

			<div class="postbox ran-emailoctopus-jetpack-forms-settings-section">
				<h2 class="hndle"><span><?php esc_html_e( 'Health check', 'ran-emailoctopus-jetpack-forms' ); ?></span></h2>
				<div class="inside">
					<p><?php esc_html_e( 'Runs safe diagnostics without sending mail, creating feedback posts, or adding EmailOctopus contacts.', 'ran-emailoctopus-jetpack-forms' ); ?></p>
					<form id="ran-emailoctopus-jetpack-forms-health-check-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="ran_emailoctopus_jetpack_forms_run_health_check" />
						<?php wp_nonce_field( 'ran_emailoctopus_jetpack_forms_run_health_check' ); ?>
						<input id="ran-emailoctopus-jetpack-forms-run-health-check" class="button button-secondary" type="submit" value="<?php echo esc_attr__( 'Run health check', 'ran-emailoctopus-jetpack-forms' ); ?>" />
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
			wp_die( esc_html__( 'Sorry, you are not allowed to run this health check.', 'ran-emailoctopus-jetpack-forms' ) );
		}

		check_admin_referer( 'ran_emailoctopus_jetpack_forms_run_health_check' );

		$health                  = HealthCheck::run();
		$health['settings_hash'] = Settings::get_health_hash();

		set_transient( self::HEALTH_TRANSIENT_PREFIX . get_current_user_id(), $health, 10 * MINUTE_IN_SECONDS );

		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG . '&ran_emailoctopus_jetpack_forms_health=1' ) );
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
				'name'              => esc_attr( Settings::OPTION_NAME . '[' . sanitize_key( $key ) . ']' ),
				'id'                => esc_attr( $id ),
				'selected'          => absint( $selected ),
				'show_option_none'  => esc_html__( 'Not configured', 'ran-emailoctopus-jetpack-forms' ),
				'option_none_value' => 0,
			)
		);
	}

	/**
	 * Render one visitor-facing subscription message setting.
	 *
	 * @param string $key   Settings key.
	 * @param string $id    Field ID.
	 * @param string $label Field label.
	 * @param string $value Field value.
	 * @return void
	 */
	private static function subscription_message_field( $key, $id, $label, $value ) {
		?>
		<div class="ran-emailoctopus-jetpack-forms-field">
			<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
			<textarea class="large-text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( Settings::OPTION_NAME . '[' . sanitize_key( $key ) . ']' ); ?>" rows="3"><?php echo esc_textarea( $value ); ?></textarea>
		</div>
		<?php
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
			<select class="regular-text" id="ran-emailoctopus-jetpack-forms-emailoctopus-destination" disabled>
				<option><?php esc_html_e( 'Destinations unavailable', 'ran-emailoctopus-jetpack-forms' ); ?></option>
			</select>
			<p class="description"><?php echo esc_html( $forms->get_error_message() ); ?></p>
			<?php if ( '' !== $form_id ) : ?>
				<?php /* translators: %s: EmailOctopus form ID. */ ?>
				<p class="description"><?php echo esc_html( sprintf( __( 'Saved form ID: %s', 'ran-emailoctopus-jetpack-forms' ), $form_id ) ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $list_id ) : ?>
				<?php /* translators: %s: EmailOctopus list ID. */ ?>
				<p class="description"><?php echo esc_html( sprintf( __( 'Saved list ID: %s', 'ran-emailoctopus-jetpack-forms' ), $list_id ) ); ?></p>
			<?php endif; ?>
			<?php
			return;
		}

		$lists          = self::get_emailoctopus_lists_from_forms( $forms );
		$selected_found = false;
		?>
		<select class="regular-text" id="ran-emailoctopus-jetpack-forms-emailoctopus-destination" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[emailoctopus_destination]">
			<option value=""><?php esc_html_e( 'Select an EmailOctopus destination', 'ran-emailoctopus-jetpack-forms' ); ?></option>
			<?php if ( ! empty( $forms ) ) : ?>
				<optgroup label="<?php echo esc_attr__( 'Forms', 'ran-emailoctopus-jetpack-forms' ); ?>">
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
				<optgroup label="<?php echo esc_attr__( 'Direct list overrides', 'ran-emailoctopus-jetpack-forms' ); ?>">
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
		<p class="description"><?php esc_html_e( 'Choose the EmailOctopus form connected to the newsletter list. Use a direct list override only when a form is not the right source of truth.', 'ran-emailoctopus-jetpack-forms' ); ?></p>
		<?php if ( empty( $forms ) ) : ?>
			<p class="description"><?php esc_html_e( 'No EmailOctopus forms were returned for the configured API key.', 'ran-emailoctopus-jetpack-forms' ); ?></p>
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
			/* translators: %s: EmailOctopus form ID. */
			return sprintf( __( 'Saved form ID not returned by API: %s', 'ran-emailoctopus-jetpack-forms' ), substr( $selected, 5 ) );
		}

		if ( 0 === strpos( $selected, 'list:' ) ) {
			/* translators: %s: EmailOctopus list ID. */
			return sprintf( __( 'Saved list ID not returned by API: %s', 'ran-emailoctopus-jetpack-forms' ), substr( $selected, 5 ) );
		}

		/* translators: %s: saved EmailOctopus destination identifier. */
		return sprintf( __( 'Saved destination not returned by API: %s', 'ran-emailoctopus-jetpack-forms' ), $selected );
	}

	/**
	 * Get human readable EmailOctopus form label.
	 *
	 * @param array<string,string> $form Form data.
	 * @return string
	 */
	private static function get_emailoctopus_form_label( $form ) {
		$name      = '' !== ( $form['name'] ?? '' ) ? $form['name'] : __( 'Untitled form', 'ran-emailoctopus-jetpack-forms' );
		$type      = '' !== ( $form['type'] ?? '' ) ? $form['type'] : __( 'unknown type', 'ran-emailoctopus-jetpack-forms' );
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
	 * @param array<string,string> $emailoctopus_list EmailOctopus list data.
	 * @return string
	 */
	private static function get_emailoctopus_list_label( $emailoctopus_list ) {
		$list_id   = (string) ( $emailoctopus_list['id'] ?? '' );
		$list_name = (string) ( $emailoctopus_list['name'] ?? '' );

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
		$source_fields  = EmailOctopusFieldMapper::get_newsletter_source_fields();
		$is_stale       = '' !== $selected && ! self::has_source_field( $source_fields, $selected );
		$stale_selected = $is_stale ? $selected : '';
		$replacement    = $is_stale && 1 === count( $source_fields ) ? (string) $source_fields[0]['key'] : '';

		if ( '' === $selected && 1 === count( $source_fields ) ) {
			$selected = (string) $source_fields[0]['key'];
		} elseif ( '' !== $replacement ) {
			$selected = $replacement;
		}

		$needs_choice = '' === $selected;

		if ( empty( $source_fields ) ) {
			?>
			<select class="regular-text" id="ran-emailoctopus-jetpack-forms-newsletter-source" disabled>
				<option><?php esc_html_e( 'No Jetpack opt-in fields detected', 'ran-emailoctopus-jetpack-forms' ); ?></option>
			</select>
			<p class="description error"><strong><?php esc_html_e( 'The configured contact form has no checkbox or consent field that can record newsletter consent. Newsletter subscriptions are paused until you add one and save this setting.', 'ran-emailoctopus-jetpack-forms' ); ?></strong></p>
			<?php
			return;
		}

		?>
		<select class="regular-text" id="ran-emailoctopus-jetpack-forms-newsletter-source" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[newsletter_source]">
			<?php if ( $needs_choice ) : ?>
				<option value="" disabled hidden selected><?php esc_html_e( 'Choose a newsletter opt-in source', 'ran-emailoctopus-jetpack-forms' ); ?></option>
			<?php endif; ?>
			<?php foreach ( $source_fields as $source_field ) : ?>
				<option value="<?php echo esc_attr( $source_field['key'] ); ?>" <?php selected( $selected, $source_field['key'] ); ?>>
					<?php echo esc_html( self::get_source_field_option_label( $source_field ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php if ( $is_stale ) : ?>
			<p class="description error"><strong><?php echo esc_html( sprintf( /* translators: %s: saved Jetpack field key. */ __( 'The saved newsletter opt-in field "%s" is no longer a current checkbox or consent field on the configured contact form. The only current supported field is selected above; save settings to confirm this replacement. Newsletter subscriptions remain paused until it is saved.', 'ran-emailoctopus-jetpack-forms' ), self::get_source_key_label( $stale_selected ) ) ); ?></strong></p>
		<?php elseif ( $needs_choice ) : ?>
			<p class="description error"><strong><?php esc_html_e( 'Select a current checkbox or consent field before newsletter subscriptions can run.', 'ran-emailoctopus-jetpack-forms' ); ?></strong></p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Choose the checkbox or consent field that records newsletter consent. An implicit Jetpack consent field means submitting the form subscribes the visitor.', 'ran-emailoctopus-jetpack-forms' ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render EmailOctopus email address source selector.
	 *
	 * @param string $selected Selected source key.
	 * @return void
	 */
	private static function emailoctopus_email_source_dropdown( $selected ) {
		$source_fields  = EmailOctopusFieldMapper::get_email_source_fields();
		$is_stale       = '' !== $selected && ! self::has_source_field( $source_fields, $selected );
		$stale_selected = $is_stale ? $selected : '';
		$replacement    = $is_stale && 1 === count( $source_fields ) ? (string) $source_fields[0]['key'] : '';

		if ( '' === $selected && 1 === count( $source_fields ) ) {
			$selected = (string) $source_fields[0]['key'];
		} elseif ( '' !== $replacement ) {
			$selected = $replacement;
		}

		$needs_choice = '' === $selected;

		if ( empty( $source_fields ) ) {
			?>
			<select class="regular-text" id="ran-emailoctopus-jetpack-forms-emailoctopus-email-source" disabled>
				<option><?php esc_html_e( 'No Jetpack email fields detected', 'ran-emailoctopus-jetpack-forms' ); ?></option>
			</select>
			<p class="description error"><strong><?php esc_html_e( 'The configured contact form has no email field. EmailOctopus subscriptions are paused until you add one and save this setting.', 'ran-emailoctopus-jetpack-forms' ); ?></strong></p>
			<?php
			return;
		}

		?>
		<select class="regular-text" id="ran-emailoctopus-jetpack-forms-emailoctopus-email-source" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[emailoctopus_email_source]">
			<?php if ( $needs_choice ) : ?>
				<option value="" disabled hidden selected><?php esc_html_e( 'Choose an email source', 'ran-emailoctopus-jetpack-forms' ); ?></option>
			<?php endif; ?>
			<?php foreach ( $source_fields as $source_field ) : ?>
				<option value="<?php echo esc_attr( $source_field['key'] ); ?>" <?php selected( $selected, $source_field['key'] ); ?>>
					<?php echo esc_html( self::get_source_field_option_label( $source_field ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php if ( $is_stale ) : ?>
			<p class="description error"><strong><?php echo esc_html( sprintf( /* translators: %s: saved Jetpack field key. */ __( 'The saved email source "%s" is no longer a current email field on the configured contact form. The only current supported field is selected above; save settings to confirm this replacement. EmailOctopus subscriptions remain paused until it is saved.', 'ran-emailoctopus-jetpack-forms' ), self::get_source_key_label( $stale_selected ) ) ); ?></strong></p>
		<?php elseif ( $needs_choice ) : ?>
			<p class="description error"><strong><?php esc_html_e( 'Select a current email field before EmailOctopus subscriptions can run.', 'ran-emailoctopus-jetpack-forms' ); ?></strong></p>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'RAN EmailOctopus for Jetpack Forms sends this field as EmailOctopus email_address. The source is always explicit; it is never auto-detected during a submission.', 'ran-emailoctopus-jetpack-forms' ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Whether a source key appears in the current candidate list.
	 *
	 * @param array<int,array<string,string>> $source_fields Candidate fields.
	 * @param string                           $source        Saved source key.
	 * @return bool
	 */
	private static function has_source_field( $source_fields, $source ) {
		foreach ( $source_fields as $source_field ) {
			if ( ( $source_field['key'] ?? '' ) === $source ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Build a human-readable label for a source selector option.
	 *
	 * @param array<string,string> $source_field Detected Jetpack field.
	 * @return string
	 */
	private static function get_source_field_option_label( $source_field ) {
		$label = (string) ( $source_field['label'] ?? '' );
		$type  = (string) ( $source_field['type'] ?? '' );

		if ( 'consent' === $type ) {
			if ( 'explicit' === ( $source_field['consent_type'] ?? '' ) ) {
				return sprintf( /* translators: %s: Jetpack consent field label. */ __( '%1$s (consent checkbox)', 'ran-emailoctopus-jetpack-forms' ), $label );
			}

			return sprintf( /* translators: %s: Jetpack consent field label. */ __( '%1$s (consent; submitting this form subscribes the visitor)', 'ran-emailoctopus-jetpack-forms' ), $label );
		}

		return sprintf( '%s (%s)', $label, $type );
	}

	/**
	 * Turn a stored source key into a readable fallback label.
	 *
	 * @param string $source Saved source key.
	 * @return string
	 */
	private static function get_source_key_label( $source ) {
		return str_replace( '_', ' ', $source );
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
			<p class="description"><?php esc_html_e( 'Select an EmailOctopus destination, then save settings to map custom list fields.', 'ran-emailoctopus-jetpack-forms' ); ?></p>
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
			<p class="description"><?php esc_html_e( 'No Jetpack fields were detected on the configured contact page.', 'ran-emailoctopus-jetpack-forms' ); ?></p>
			<?php
			return;
		}

		?>
		<details class="ran-emailoctopus-jetpack-forms-details">
			<summary><?php esc_html_e( 'Map custom EmailOctopus fields', 'ran-emailoctopus-jetpack-forms' ); ?></summary>
			<div class="inside">
			<?php if ( empty( $custom_fields ) ) : ?>
				<p class="description"><?php esc_html_e( 'The selected list does not expose custom fields beyond EmailAddress.', 'ran-emailoctopus-jetpack-forms' ); ?></p>
			<?php else : ?>
			<p class="description"><?php esc_html_e( 'Only mapped fields are sent to EmailOctopus. Leave fields set to Do not send unless the mapping is intentional.', 'ran-emailoctopus-jetpack-forms' ); ?></p>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'EmailOctopus field', 'ran-emailoctopus-jetpack-forms' ); ?></th>
						<th><?php esc_html_e( 'Type', 'ran-emailoctopus-jetpack-forms' ); ?></th>
						<th><?php esc_html_e( 'Jetpack source', 'ran-emailoctopus-jetpack-forms' ); ?></th>
						<th><?php esc_html_e( 'Transform', 'ran-emailoctopus-jetpack-forms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $custom_fields as $field ) : ?>
						<?php
						$tag       = (string) ( $field['tag'] ?? '' );
						$mapping   = is_array( $field_map[ $tag ] ?? null ) ? $field_map[ $tag ] : array();
						$source    = (string) ( $mapping['source'] ?? '' );
						$transform = (string) ( $mapping['transform'] ?? 'as_is' );
						$is_stale  = '' !== $source && ! self::has_source_field( $source_fields, $source );
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( '' !== ( $field['label'] ?? '' ) ? $field['label'] : $tag ); ?></strong><br />
								<code><?php echo esc_html( $tag ); ?></code>
							</td>
							<td><?php echo esc_html( (string) ( $field['type'] ?? '' ) ); ?></td>
							<td>
								<?php if ( $is_stale ) : ?>
									<input type="hidden" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[emailoctopus_field_map][<?php echo esc_attr( $tag ); ?>][preserve_source]" value="<?php echo esc_attr( $source ); ?>" />
								<?php endif; ?>
								<select name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[emailoctopus_field_map][<?php echo esc_attr( $tag ); ?>][source]">
									<?php if ( $is_stale ) : ?>
										<option value="" disabled hidden selected><?php esc_html_e( 'Choose a replacement source', 'ran-emailoctopus-jetpack-forms' ); ?></option>
									<?php endif; ?>
									<option value=""><?php esc_html_e( 'Do not send', 'ran-emailoctopus-jetpack-forms' ); ?></option>
									<?php foreach ( $source_fields as $source_field ) : ?>
										<option value="<?php echo esc_attr( $source_field['key'] ); ?>" <?php selected( $source, $source_field['key'] ); ?>>
											<?php echo esc_html( sprintf( '%s (%s)', $source_field['label'], $source_field['type'] ) ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<?php if ( $is_stale ) : ?>
									<p class="description error"><strong><?php echo esc_html( sprintf( /* translators: %s: saved Jetpack field key. */ __( 'The saved Jetpack source "%s" is no longer on the configured contact form. Choose a current field or select Do not send, then save settings.', 'ran-emailoctopus-jetpack-forms' ), self::get_source_key_label( $source ) ) ); ?></strong></p>
								<?php endif; ?>
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
			if ( (string) ( $form['id'] ?? '' ) === $form_id ) {
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
		<?php /* translators: %s: health-check status. */ ?>
		<h3><?php echo esc_html( sprintf( __( 'Latest result: %s', 'ran-emailoctopus-jetpack-forms' ), ucfirst( (string) $health['overall'] ) ) ); ?></h3>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Check', 'ran-emailoctopus-jetpack-forms' ); ?></th>
					<th><?php esc_html_e( 'Status', 'ran-emailoctopus-jetpack-forms' ); ?></th>
					<th><?php esc_html_e( 'Detail', 'ran-emailoctopus-jetpack-forms' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $health['checks'] as $check ) : ?>
					<tr>
						<td><?php echo esc_html( $check['label'] ); ?></td>
						<td><strong class="<?php echo esc_attr( 'ran-emailoctopus-jetpack-forms-status-' . sanitize_html_class( $check['status'] ) ); ?>"><?php echo esc_html( 'error' === $check['status'] ? __( 'FAIL', 'ran-emailoctopus-jetpack-forms' ) : strtoupper( $check['status'] ) ); ?></strong></td>
						<td><?php echo esc_html( $check['message'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
