<?php
/**
 * Conflict-safe profile administration.
 *
 * @package RAN_EmailOctopus_Jetpack_Forms
 */

namespace RAN\EmailOctopusJetpackForms;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders and mutates independent integration profiles.
 */
final class Admin {
	/** Settings page slug. */
	const PAGE_SLUG = 'ran-emailoctopus-jetpack-forms';

	/** Per-user health result prefix. */
	const HEALTH_TRANSIENT_PREFIX = 'ran_emailoctopus_jetpack_forms_health_';

	/** Per-user rejected-input prefix. */
	const INPUT_TRANSIENT_PREFIX = 'ran_emailoctopus_jetpack_forms_admin_input_';

	/** Register admin hooks. */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_post_ran_emailoctopus_jetpack_forms_create_profile', array( __CLASS__, 'create_profile' ) );
		add_action( 'admin_post_ran_emailoctopus_jetpack_forms_save_profile_identity', array( __CLASS__, 'save_profile_identity' ) );
		add_action( 'admin_post_ran_emailoctopus_jetpack_forms_save_profile_behaviour', array( __CLASS__, 'save_profile_behaviour' ) );
		add_action( 'admin_post_ran_emailoctopus_jetpack_forms_delete_profile', array( __CLASS__, 'delete_profile' ) );
		add_action( 'admin_post_ran_emailoctopus_jetpack_forms_run_health_check', array( __CLASS__, 'run_health_check' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( RAN_EMAILOCTOPUS_JETPACK_FORMS_PLUGIN_FILE ), array( __CLASS__, 'plugin_action_links' ) );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );
	}

	/** Add the integrations page. */
	public static function add_page() {
		add_options_page(
			__( 'RAN EmailOctopus for Jetpack Forms', 'ran-emailoctopus-jetpack-forms' ),
			__( 'RAN EmailOctopus for Jetpack Forms', 'ran-emailoctopus-jetpack-forms' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/** Add the plugin-list settings link. */
	public static function plugin_action_links( $links ) {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( self::page_url() ), esc_html__( 'Settings', 'ran-emailoctopus-jetpack-forms' ) )
		);
		return $links;
	}

	/** Add project metadata. */
	public static function plugin_row_meta( $links, $plugin_file ) {
		if ( plugin_basename( RAN_EMAILOCTOPUS_JETPACK_FORMS_PLUGIN_FILE ) === $plugin_file ) {
			$links[] = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( 'https://github.com/RocketsAreNostalgic/' ),
				esc_html__( 'RAN GitHub', 'ran-emailoctopus-jetpack-forms' )
			);
		}
		return $links;
	}

	/** Render the selected server-side view. */
	public static function render_page() {
		self::require_capability();

		$view       = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'index'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin view routing.
		$profile_id = self::request_profile_id( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin view routing.
		?>
		<div class="wrap ran-emailoctopus-jetpack-forms-admin">
			<style>
				.ran-emailoctopus-jetpack-forms-admin .ran-profile-panel { background: #fff; border: 1px solid #c3c4c7; margin: 20px 0; max-width: 1000px; padding: 20px; }
				.ran-emailoctopus-jetpack-forms-admin fieldset { margin: 0 0 24px; }
				.ran-emailoctopus-jetpack-forms-admin legend { font-size: 14px; font-weight: 600; margin-bottom: 8px; }
				.ran-emailoctopus-jetpack-forms-admin .ran-form-choice { display: block; margin: 8px 0; }
				.ran-emailoctopus-jetpack-forms-admin .ran-actions { display: flex; flex-wrap: wrap; gap: 8px; }
				.ran-emailoctopus-jetpack-forms-admin .ran-health-pass { color: #008a20; }
				.ran-emailoctopus-jetpack-forms-admin .ran-health-error { color: #b32d2e; }
				.ran-emailoctopus-jetpack-forms-admin .ran-health-warning { color: #996800; }
				.ran-emailoctopus-jetpack-forms-admin .ran-health-skipped { color: #646970; }
			</style>
			<h1><?php esc_html_e( 'RAN EmailOctopus for Jetpack Forms', 'ran-emailoctopus-jetpack-forms' ); ?></h1>
			<?php self::render_notice(); ?>
			<?php
			if ( 'create' === $view ) {
				self::render_identity_editor();
			} elseif ( 'edit' === $view ) {
				self::render_profile_editor( $profile_id );
			} elseif ( 'delete' === $view ) {
				self::render_delete_confirmation( $profile_id );
			} elseif ( 'health' === $view ) {
				self::render_health_view( $profile_id );
			} else {
				self::render_index();
			}
			?>
		</div>
		<?php
	}

	/** Create one profile from stage-one input. */
	public static function create_profile() {
		self::require_capability();
		check_admin_referer( 'ran_emailoctopus_jetpack_forms_create_profile' );
		$input  = self::posted_profile_input();
		$result = Settings::create_profile( self::identity_input( $input ) );

		if ( is_wp_error( $result ) ) {
			self::redirect_with_error( $result, '', 'identity', $input, 'create' );
			return;
		}

		self::redirect(
			array(
				'view'       => 'edit',
				'profile_id' => $result,
				'notice'     => 'created',
			)
		);
	}

	/** Save profile identity, forms and destination only. */
	public static function save_profile_identity() {
		self::require_capability();
		$profile_id = self::request_profile_id( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Profile ID selects the nonce action verified immediately below.
		check_admin_referer( 'ran_emailoctopus_jetpack_forms_save_identity_' . $profile_id );
		$input  = self::posted_profile_input();
		$result = Settings::update_profile_stage_one(
			$profile_id,
			self::posted_revision(),
			self::identity_input( $input )
		);

		if ( is_wp_error( $result ) ) {
			self::redirect_with_error( $result, $profile_id, 'identity', $input, 'edit' );
			return;
		}

		self::redirect(
			array(
				'view'       => 'edit',
				'profile_id' => $profile_id,
				'notice'     => 'identity_saved',
			)
		);
	}

	/** Save mapping, success page and messages only. */
	public static function save_profile_behaviour() {
		self::require_capability();
		$profile_id = self::request_profile_id( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Profile ID selects the nonce action verified immediately below.
		check_admin_referer( 'ran_emailoctopus_jetpack_forms_save_behaviour_' . $profile_id );
		$input  = self::posted_profile_input();
		$result = Settings::update_profile_stage_two( $profile_id, self::posted_revision(), $input );

		if ( is_wp_error( $result ) ) {
			self::redirect_with_error( $result, $profile_id, 'behaviour', $input, 'edit' );
			return;
		}

		self::redirect(
			array(
				'view'       => 'edit',
				'profile_id' => $profile_id,
				'notice'     => 'behaviour_saved',
			)
		);
	}

	/** Delete exactly one current profile revision. */
	public static function delete_profile() {
		self::require_capability();
		$profile_id = self::request_profile_id( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Profile ID selects the nonce action verified immediately below.
		check_admin_referer( 'ran_emailoctopus_jetpack_forms_delete_profile_' . $profile_id );
		$result = Settings::delete_profile( $profile_id, self::posted_revision() );

		if ( is_wp_error( $result ) ) {
			self::redirect_with_error( $result, $profile_id, 'delete', array(), 'delete' );
			return;
		}

		delete_transient( self::health_transient_key( $profile_id ) );
		self::redirect( array( 'notice' => 'deleted' ) );
	}

	/** Run an explicit remote health check for one current profile revision. */
	public static function run_health_check() {
		self::require_capability();
		$profile_id = self::request_profile_id( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Profile ID selects the nonce action verified immediately below.
		check_admin_referer( 'ran_emailoctopus_jetpack_forms_health_' . $profile_id );
		$profile = Settings::get_profile( $profile_id );

		if ( null === $profile || self::posted_revision() !== (int) $profile['revision'] ) {
			self::redirect_with_error(
				new \WP_Error( 'ran_emailoctopus_jetpack_forms_profile_conflict', __( 'This profile changed before the health check ran. Reload and try again.', 'ran-emailoctopus-jetpack-forms' ) ),
				$profile_id,
				'health',
				array(),
				'health'
			);
			return;
		}

		$result = HealthCheck::run( $profile_id );

		if ( is_wp_error( $result ) ) {
			self::redirect_with_error( $result, $profile_id, 'health', array(), 'health' );
			return;
		}

		set_transient( self::health_transient_key( $profile_id ), $result, 30 * MINUTE_IN_SECONDS );
		self::redirect(
			array(
				'view'       => 'health',
				'profile_id' => $profile_id,
				'notice'     => 'health_complete',
			)
		);
	}

	/** Render the integrations index without remote provider calls. */
	private static function render_index() {
		$store = Settings::get_store();
		?>
		<p><?php esc_html_e( 'Each integration profile owns its saved Jetpack forms, EmailOctopus destination, mappings, success page and visitor messages.', 'ran-emailoctopus-jetpack-forms' ); ?></p>
		<p><a class="button button-primary" href="<?php echo esc_url( self::page_url( array( 'view' => 'create' ) ) ); ?>"><?php esc_html_e( 'Add integration profile', 'ran-emailoctopus-jetpack-forms' ); ?></a></p>
		<?php
		if ( is_wp_error( $store ) ) {
			self::render_error_box( $store->get_error_message() );
			return;
		}

		$profiles = IntegrationResolver::get_profiles();

		if ( empty( $profiles ) ) {
			?>
			<div class="ran-profile-panel">
				<h2><?php esc_html_e( 'No integration profiles yet', 'ran-emailoctopus-jetpack-forms' ); ?></h2>
				<p><?php esc_html_e( 'Create a profile to assign saved Jetpack forms and configure their EmailOctopus behaviour.', 'ran-emailoctopus-jetpack-forms' ); ?></p>
			</div>
			<?php
			return;
		}
		?>
		<table class="widefat striped">
			<thead><tr>
				<th scope="col"><?php esc_html_e( 'Profile', 'ran-emailoctopus-jetpack-forms' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Assigned forms', 'ran-emailoctopus-jetpack-forms' ); ?></th>
				<th scope="col"><?php esc_html_e( 'EmailOctopus destination', 'ran-emailoctopus-jetpack-forms' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Coverage', 'ran-emailoctopus-jetpack-forms' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Last health result', 'ran-emailoctopus-jetpack-forms' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Actions', 'ran-emailoctopus-jetpack-forms' ); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ( $profiles as $profile ) : ?>
				<?php
				$profile_id = $profile->get_id();
				$summary    = HealthCheck::get_profile_summary( $profile_id );
				$health     = get_transient( self::health_transient_key( $profile_id ) );
				$current    = HealthCheck::is_current_result( $health, $profile_id );
				?>
				<tr>
					<th scope="row"><?php echo esc_html( $profile->get_label() ); ?></th>
					<td><?php echo esc_html( self::form_ids_label( $profile->get_form_ids() ) ); ?></td>
					<td><?php echo esc_html( self::destination_label( $profile->get_configuration() ) ); ?></td>
					<td>
						<?php
						if ( is_wp_error( $summary ) ) {
							echo esc_html( $summary->get_error_message() );
						} else {
							echo esc_html( sprintf( /* translators: 1: routing count, 2: selected count, 3: subscription count. */ __( '%1$d/%2$d routed; %3$d subscribed', 'ran-emailoctopus-jetpack-forms' ), $summary['routing_count'], $summary['selected_count'], $summary['subscription_count'] ) );
						}
						?>
					</td>
					<td><?php echo esc_html( $current ? ucfirst( (string) $health['overall'] ) : ( false === $health ? __( 'Not checked', 'ran-emailoctopus-jetpack-forms' ) : __( 'Stale', 'ran-emailoctopus-jetpack-forms' ) ) ); ?></td>
					<td><div class="ran-actions">
						<a class="button button-small" href="
						<?php
						echo esc_url(
							self::page_url(
								array(
									'view'       => 'edit',
									'profile_id' => $profile_id,
								)
							)
						);
						?>
																"><?php esc_html_e( 'Edit', 'ran-emailoctopus-jetpack-forms' ); ?></a>
						<?php self::render_health_form( $profile ); ?>
						<a class="button button-small" href="
						<?php
						echo esc_url(
							self::page_url(
								array(
									'view'       => 'delete',
									'profile_id' => $profile_id,
								)
							)
						);
						?>
																"><?php esc_html_e( 'Delete', 'ran-emailoctopus-jetpack-forms' ); ?></a>
					</div></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/** Render both stages for one persisted profile. */
	private static function render_profile_editor( $profile_id ) {
		$profile = Settings::get_profile( $profile_id );

		if ( null === $profile ) {
			self::render_error_box( __( 'That integration profile no longer exists.', 'ran-emailoctopus-jetpack-forms' ) );
			return;
		}

		self::render_identity_editor( $profile_id, $profile );
		self::render_behaviour_editor( $profile_id, $profile );
	}

	/** Render stage one for a new or existing profile. */
	private static function render_identity_editor( $profile_id = '', $profile = null ) {
		$is_new   = '' === $profile_id;
		$defaults = null === $profile ? Settings::get_profile_defaults() : $profile;
		$input    = self::rejected_input( $profile_id, 'identity' );
		$values   = is_array( $input ) ? array_replace( $defaults, self::identity_input( $input ) ) : $defaults;
		$revision = (int) ( $defaults['revision'] ?? 0 );
		$selected = Settings::normalize_form_ids( $values['form_ids'] ?? array() );
		?>
		<div class="ran-profile-panel">
			<h2><?php echo esc_html( $is_new ? __( 'Create integration profile', 'ran-emailoctopus-jetpack-forms' ) : __( 'Step 1 — Identity and assignment', 'ran-emailoctopus-jetpack-forms' ) ); ?></h2>
			<p><?php esc_html_e( 'Save this stage to refresh the field choices in Step 2. It never clears behaviour saved below.', 'ran-emailoctopus-jetpack-forms' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( $is_new ? 'ran_emailoctopus_jetpack_forms_create_profile' : 'ran_emailoctopus_jetpack_forms_save_profile_identity' ); ?>" />
				<?php if ( $is_new ) : ?>
					<?php wp_nonce_field( 'ran_emailoctopus_jetpack_forms_create_profile' ); ?>
				<?php else : ?>
					<input type="hidden" name="profile_id" value="<?php echo esc_attr( $profile_id ); ?>" />
					<input type="hidden" name="profile_revision" value="<?php echo esc_attr( $revision ); ?>" />
					<?php wp_nonce_field( 'ran_emailoctopus_jetpack_forms_save_identity_' . $profile_id ); ?>
				<?php endif; ?>
				<p>
					<label for="ran-profile-label"><strong><?php esc_html_e( 'Profile name', 'ran-emailoctopus-jetpack-forms' ); ?></strong></label><br />
					<input class="regular-text" id="ran-profile-label" name="profile[label]" required type="text" value="<?php echo esc_attr( (string) ( $values['label'] ?? '' ) ); ?>" />
				</p>
				<?php self::render_saved_form_choices( $profile_id, $selected ); ?>
				<?php self::render_destination_choice( $values['destination'] ?? array() ); ?>
				<?php submit_button( __( 'Save and refresh field choices', 'ran-emailoctopus-jetpack-forms' ) ); ?>
			</form>
		</div>
		<?php
	}

	/** Render stage two from persisted stage-one choices. */
	private static function render_behaviour_editor( $profile_id, $profile ) {
		$input  = self::rejected_input( $profile_id, 'behaviour' );
		$values = is_array( $input ) ? array_replace( $profile, $input ) : $profile;
		$forms  = Settings::normalize_form_ids( $profile['form_ids'] ?? array() );
		$all    = EmailOctopusFieldMapper::get_source_fields( $forms );
		$email  = EmailOctopusFieldMapper::get_email_source_fields( $forms );
		$optin  = EmailOctopusFieldMapper::get_newsletter_source_fields( $forms );
		?>
		<div class="ran-profile-panel">
			<h2><?php esc_html_e( 'Step 2 — Behaviour', 'ran-emailoctopus-jetpack-forms' ); ?></h2>
			<p><?php esc_html_e( 'These choices are derived from the forms persisted in Step 1. This save never changes form ownership or the EmailOctopus destination.', 'ran-emailoctopus-jetpack-forms' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ran_emailoctopus_jetpack_forms_save_profile_behaviour" />
				<input type="hidden" name="profile_id" value="<?php echo esc_attr( $profile_id ); ?>" />
				<input type="hidden" name="profile_revision" value="<?php echo esc_attr( (int) $profile['revision'] ); ?>" />
				<?php wp_nonce_field( 'ran_emailoctopus_jetpack_forms_save_behaviour_' . $profile_id ); ?>
				<?php self::render_source_select( 'email_source', __( 'Email source', 'ran-emailoctopus-jetpack-forms' ), $email, (string) ( $values['email_source'] ?? '' ) ); ?>
				<?php self::render_source_select( 'consent_source', __( 'Newsletter consent source', 'ran-emailoctopus-jetpack-forms' ), $optin, (string) ( $values['consent_source'] ?? '' ) ); ?>
				<?php self::render_field_mapping( $profile_id, $values, $all ); ?>
				<p>
					<label for="ran-success-page"><strong><?php esc_html_e( 'Success page', 'ran-emailoctopus-jetpack-forms' ); ?></strong></label><br />
					<?php
					wp_dropdown_pages(
						array(
							'name'              => 'profile[success_page_id]',
							'id'                => 'ran-success-page',
							'selected'          => absint( $values['success_page_id'] ?? 0 ),
							'show_option_none'  => esc_html__( '— Select a published page —', 'ran-emailoctopus-jetpack-forms' ),
							'option_none_value' => '0',
							'post_status'       => 'publish',
						)
					);
					?>
				</p>
				<?php self::render_message_fields( $values['messages'] ?? array() ); ?>
				<p><strong><?php esc_html_e( 'Success-page shortcode', 'ran-emailoctopus-jetpack-forms' ); ?></strong><br /><input class="large-text code" readonly type="text" value="[<?php echo esc_attr( SubmissionMessages::SHORTCODE ); ?>]" /></p>
				<?php submit_button( __( 'Save profile behaviour', 'ran-emailoctopus-jetpack-forms' ) ); ?>
			</form>
		</div>
		<?php
	}

	/** Render a nonce-protected deletion confirmation. */
	private static function render_delete_confirmation( $profile_id ) {
		$profile = IntegrationResolver::get_profile( $profile_id );
		if ( null === $profile ) {
			self::render_error_box( __( 'That integration profile no longer exists.', 'ran-emailoctopus-jetpack-forms' ) );
			return;
		}
		$form_ids            = $profile->get_form_ids();
		$form_summary        = empty( $form_ids ) ? __( 'None assigned', 'ran-emailoctopus-jetpack-forms' ) : implode( ', ', array_map( 'strval', $form_ids ) );
		$destination         = $profile->get_destination();
		$destination_summary = empty( $destination['type'] ) || empty( $destination['id'] )
			? __( 'Not configured', 'ran-emailoctopus-jetpack-forms' )
			: self::destination_label( $destination['type'], $destination['id'] );
		?>
		<div class="ran-profile-panel">
			<h2><?php esc_html_e( 'Delete integration profile', 'ran-emailoctopus-jetpack-forms' ); ?></h2>
			<p><?php echo esc_html( sprintf( /* translators: %s: profile label. */ __( 'Delete “%s”? Its assigned forms will return to native Jetpack handling.', 'ran-emailoctopus-jetpack-forms' ), $profile->get_label() ) ); ?></p>
			<p><strong><?php esc_html_e( 'Assigned form IDs:', 'ran-emailoctopus-jetpack-forms' ); ?></strong> <?php echo esc_html( $form_summary ); ?></p>
			<p><strong><?php esc_html_e( 'EmailOctopus destination:', 'ran-emailoctopus-jetpack-forms' ); ?></strong> <?php echo esc_html( $destination_summary ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ran_emailoctopus_jetpack_forms_delete_profile" />
				<input type="hidden" name="profile_id" value="<?php echo esc_attr( $profile_id ); ?>" />
				<input type="hidden" name="profile_revision" value="<?php echo esc_attr( $profile->get_revision() ); ?>" />
				<?php wp_nonce_field( 'ran_emailoctopus_jetpack_forms_delete_profile_' . $profile_id ); ?>
				<?php submit_button( __( 'Delete profile', 'ran-emailoctopus-jetpack-forms' ), 'delete' ); ?>
				<a href="<?php echo esc_url( self::page_url() ); ?>"><?php esc_html_e( 'Cancel', 'ran-emailoctopus-jetpack-forms' ); ?></a>
			</form>
		</div>
		<?php
	}

	/** Render cached or local health details. */
	private static function render_health_view( $profile_id ) {
		$profile = IntegrationResolver::get_profile( $profile_id );
		if ( null === $profile ) {
			self::render_error_box( __( 'That integration profile no longer exists.', 'ran-emailoctopus-jetpack-forms' ) );
			return;
		}

		$cached = get_transient( self::health_transient_key( $profile_id ) );
		$health = HealthCheck::is_current_result( $cached, $profile_id ) ? $cached : HealthCheck::get_profile_summary( $profile_id );
		?>
		<div class="ran-profile-panel">
			<h2><?php echo esc_html( sprintf( /* translators: %s: profile label. */ __( 'Health — %s', 'ran-emailoctopus-jetpack-forms' ), $profile->get_label() ) ); ?></h2>
			<?php if ( is_wp_error( $health ) ) : ?>
				<?php self::render_error_box( $health->get_error_message() ); ?>
			<?php else : ?>
				<p><strong><?php esc_html_e( 'State:', 'ran-emailoctopus-jetpack-forms' ); ?></strong> <?php echo esc_html( ucfirst( (string) $health['overall'] ) ); ?></p>
				<ul>
				<?php foreach ( (array) $health['checks'] as $check ) : ?>
					<li class="ran-health-<?php echo esc_attr( sanitize_html_class( (string) ( $check['status'] ?? '' ) ) ); ?>"><strong><?php echo esc_html( (string) ( $check['label'] ?? '' ) ); ?>:</strong> <?php echo esc_html( (string) ( $check['message'] ?? '' ) ); ?></li>
				<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php self::render_health_form( $profile ); ?>
			<p><a href="<?php echo esc_url( self::page_url() ); ?>"><?php esc_html_e( 'Back to integrations', 'ran-emailoctopus-jetpack-forms' ); ?></a></p>
		</div>
		<?php
	}

	/** Render a health POST form. */
	private static function render_health_form( $profile ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ran_emailoctopus_jetpack_forms_run_health_check" />
			<input type="hidden" name="profile_id" value="<?php echo esc_attr( $profile->get_id() ); ?>" />
			<input type="hidden" name="profile_revision" value="<?php echo esc_attr( $profile->get_revision() ); ?>" />
			<?php wp_nonce_field( 'ran_emailoctopus_jetpack_forms_health_' . $profile->get_id() ); ?>
			<button class="button button-small" type="submit"><?php esc_html_e( 'Health', 'ran-emailoctopus-jetpack-forms' ); ?></button>
		</form>
		<?php
	}

	/** Render all published forms plus unavailable selected rows. */
	private static function render_saved_form_choices( $profile_id, $selected ) {
		$forms  = get_posts(
			array(
				'post_type'   => 'jetpack_form',
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		$owners = array();
		$seen   = array();

		foreach ( IntegrationResolver::get_profiles() as $other ) {
			foreach ( $other->get_form_ids() as $form_id ) {
				$owners[ $form_id ] = array(
					'id'    => $other->get_id(),
					'label' => $other->get_label(),
				);
			}
		}
		?>
		<fieldset>
			<legend><?php esc_html_e( 'Saved Jetpack forms', 'ran-emailoctopus-jetpack-forms' ); ?></legend>
			<p class="description"><?php esc_html_e( 'One saved form can belong to only one profile. Clear every checkbox to leave this profile intentionally unassigned.', 'ran-emailoctopus-jetpack-forms' ); ?></p>
			<?php foreach ( $forms as $form ) : ?>
				<?php
				$form_id  = (int) $form->ID;
				$seen[]   = $form_id;
				$owner    = $owners[ $form_id ] ?? null;
				$disabled = is_array( $owner ) && $owner['id'] !== $profile_id;
				$label    = '' !== trim( (string) $form->post_title ) ? $form->post_title : __( 'Untitled saved form', 'ran-emailoctopus-jetpack-forms' );
				?>
				<label class="ran-form-choice">
					<input name="profile[form_ids][]" type="checkbox" value="<?php echo esc_attr( $form_id ); ?>" <?php checked( in_array( $form_id, $selected, true ) ); ?> <?php disabled( $disabled ); ?> />
					<?php echo esc_html( sprintf( '%s (#%d)', $label, $form_id ) ); ?>
					<?php
					if ( $disabled ) :
						?>
						<span class="description"><?php echo esc_html( sprintf( /* translators: %s: owning profile label. */ __( ' — assigned to %s', 'ran-emailoctopus-jetpack-forms' ), $owner['label'] ) ); ?></span><?php endif; ?>
				</label>
			<?php endforeach; ?>
			<?php foreach ( array_diff( $selected, $seen ) as $form_id ) : ?>
				<label class="ran-form-choice"><input checked name="profile[form_ids][]" type="checkbox" value="<?php echo esc_attr( $form_id ); ?>" /> <?php echo esc_html( sprintf( /* translators: %d: unavailable stored form ID. */ __( 'Unavailable selected form #%d — uncheck to remove', 'ran-emailoctopus-jetpack-forms' ), $form_id ) ); ?></label>
			<?php endforeach; ?>
			<?php
			if ( empty( $forms ) && empty( $selected ) ) :
				?>
				<p><?php esc_html_e( 'No published saved Jetpack forms are available.', 'ran-emailoctopus-jetpack-forms' ); ?></p><?php endif; ?>
		</fieldset>
		<?php
	}

	/** Render optional EmailOctopus destination choices. */
	private static function render_destination_choice( $destination ) {
		$destination = is_array( $destination ) ? $destination : array();
		$selected    = self::destination_value( $destination );
		$forms       = EmailOctopusApi::get_forms();
		$options     = array();

		if ( ! is_wp_error( $forms ) ) {
			foreach ( $forms as $form ) {
				$form_id = (string) ( $form['id'] ?? '' );
				$list_id = (string) ( $form['list_id'] ?? '' );
				if ( '' !== $form_id ) {
					$options[ 'form:' . $form_id ] = sprintf( /* translators: 1: form name, 2: form ID. */ __( 'Form: %1$s (%2$s)', 'ran-emailoctopus-jetpack-forms' ), (string) ( $form['name'] ?? __( 'Untitled', 'ran-emailoctopus-jetpack-forms' ) ), $form_id );
				}
				if ( '' !== $list_id ) {
					$options[ 'list:' . $list_id ] = sprintf( /* translators: 1: list name, 2: list ID. */ __( 'List: %1$s (%2$s)', 'ran-emailoctopus-jetpack-forms' ), (string) ( $form['list_name'] ?? __( 'Untitled', 'ran-emailoctopus-jetpack-forms' ) ), $list_id );
				}
			}
		}

		if ( '' !== $selected && ! isset( $options[ $selected ] ) ) {
			$options[ $selected ] = sprintf( /* translators: %s: stored destination value. */ __( 'Stored destination unavailable from API: %s', 'ran-emailoctopus-jetpack-forms' ), $selected );
		}
		?>
		<p><label for="ran-profile-destination"><strong><?php esc_html_e( 'EmailOctopus destination', 'ran-emailoctopus-jetpack-forms' ); ?></strong></label><br />
		<select id="ran-profile-destination" name="profile[destination_value]">
			<option value=""><?php esc_html_e( '— Not configured —', 'ran-emailoctopus-jetpack-forms' ); ?></option>
			<?php
			foreach ( $options as $value => $label ) :
				?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected, $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
		</select></p>
		<?php
		if ( is_wp_error( $forms ) ) :
			?>
			<p class="description"><?php echo esc_html( $forms->get_error_message() ); ?> <?php esc_html_e( 'The stored choice is preserved.', 'ran-emailoctopus-jetpack-forms' ); ?></p><?php endif; ?>
		<?php
	}

	/** Render one explicit source selector, preserving stale values. */
	private static function render_source_select( $key, $label, $fields, $selected ) {
		$known = array_column( $fields, 'key' );
		?>
		<p><label for="ran-<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label><br />
		<select id="ran-<?php echo esc_attr( $key ); ?>" name="profile[<?php echo esc_attr( $key ); ?>]">
			<option value=""><?php esc_html_e( '— Not configured —', 'ran-emailoctopus-jetpack-forms' ); ?></option>
			<?php
			if ( '' !== $selected && ! in_array( $selected, $known, true ) ) :
				?>
				<option selected value="<?php echo esc_attr( $selected ); ?>"><?php echo esc_html( sprintf( /* translators: %s: stale source key. */ __( 'Stored unavailable source: %s', 'ran-emailoctopus-jetpack-forms' ), $selected ) ); ?></option><?php endif; ?>
			<?php
			foreach ( $fields as $field ) :
				?>
				<option value="<?php echo esc_attr( (string) $field['key'] ); ?>" <?php selected( $selected, (string) $field['key'] ); ?>><?php echo esc_html( self::field_label( $field ) ); ?></option><?php endforeach; ?>
		</select></p>
		<?php
	}

	/** Render remote custom fields and every stored mapping. */
	private static function render_field_mapping( $profile_id, $values, $source_fields ) {
		$stored = is_array( $values['field_map'] ?? null ) ? $values['field_map'] : array();
		$remote = self::get_remote_custom_fields( $profile_id );
		$fields = is_wp_error( $remote ) ? array() : $remote;
		$tags   = array();

		foreach ( $fields as $field ) {
			$tag = (string) ( $field['tag'] ?? '' );
			if ( '' !== $tag ) {
				$tags[ $tag ] = (string) ( $field['label'] ?? $tag );
			}
		}
		foreach ( $stored as $tag => $mapping ) {
			if ( is_array( $mapping ) ) {
				$tags[ $tag ] = $tags[ $tag ] ?? $tag;
			}
		}
		?>
		<fieldset><legend><?php esc_html_e( 'Custom field mappings', 'ran-emailoctopus-jetpack-forms' ); ?></legend>
		<?php
		if ( is_wp_error( $remote ) ) :
			?>
			<p class="description"><?php echo esc_html( $remote->get_error_message() ); ?> <?php esc_html_e( 'Stored mappings remain available and will not be rewritten unless this stage is saved.', 'ran-emailoctopus-jetpack-forms' ); ?></p><?php endif; ?>
		<?php
		if ( empty( $tags ) ) :
			?>
			<p><?php esc_html_e( 'No custom EmailOctopus fields are available for this destination.', 'ran-emailoctopus-jetpack-forms' ); ?></p><?php endif; ?>
		<?php foreach ( $tags as $tag => $label ) : ?>
			<?php $mapping = is_array( $stored[ $tag ] ?? null ) ? $stored[ $tag ] : array(); ?>
			<p><strong><?php echo esc_html( sprintf( '%s (%s)', $label, $tag ) ); ?></strong><br />
			<select name="profile[field_map][<?php echo esc_attr( $tag ); ?>][source]">
				<option value=""><?php esc_html_e( '— Do not send —', 'ran-emailoctopus-jetpack-forms' ); ?></option>
				<?php $current = (string) ( $mapping['source'] ?? '' ); ?>
				<?php
				if ( '' !== $current && ! in_array( $current, array_column( $source_fields, 'key' ), true ) ) :
					?>
					<?php /* translators: %s: stale source key. */ ?>
					<option selected value="<?php echo esc_attr( $current ); ?>"><?php echo esc_html( sprintf( __( 'Stored unavailable source: %s', 'ran-emailoctopus-jetpack-forms' ), $current ) ); ?></option><?php endif; ?>
				<?php
				foreach ( $source_fields as $field ) :
					?>
					<option value="<?php echo esc_attr( (string) $field['key'] ); ?>" <?php selected( $current, (string) $field['key'] ); ?>><?php echo esc_html( self::field_label( $field ) ); ?></option><?php endforeach; ?>
			</select>
			<select aria-label="<?php echo esc_attr( sprintf( /* translators: %s: EmailOctopus field tag. */ __( 'Transform for %s', 'ran-emailoctopus-jetpack-forms' ), $tag ) ); ?>" name="profile[field_map][<?php echo esc_attr( $tag ); ?>][transform]">
				<?php
				foreach ( EmailOctopusFieldMapper::get_transform_options() as $value => $option_label ) :
					?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( (string) ( $mapping['transform'] ?? 'as_is' ), $value ); ?>><?php echo esc_html( $option_label ); ?></option><?php endforeach; ?>
			</select></p>
		<?php endforeach; ?>
		</fieldset>
		<?php
	}

	/** Render the four outcome messages. */
	private static function render_message_fields( $messages ) {
		$messages = is_array( $messages ) ? $messages : array();
		$labels   = array(
			'pending'    => __( 'Pending confirmation message', 'ran-emailoctopus-jetpack-forms' ),
			'subscribed' => __( 'Subscribed message', 'ran-emailoctopus-jetpack-forms' ),
			'existing'   => __( 'Existing subscriber message', 'ran-emailoctopus-jetpack-forms' ),
			'failed'     => __( 'Subscription failed message', 'ran-emailoctopus-jetpack-forms' ),
		);
		foreach ( $labels as $key => $label ) {
			?>
			<p><label for="ran-message-<?php echo esc_attr( $key ); ?>"><strong><?php echo esc_html( $label ); ?></strong></label><br /><textarea class="large-text" id="ran-message-<?php echo esc_attr( $key ); ?>" name="profile[messages][<?php echo esc_attr( $key ); ?>]" rows="3"><?php echo esc_textarea( (string) ( $messages[ $key ] ?? '' ) ); ?></textarea></p>
			<?php
		}
	}

	/** Resolve custom EmailOctopus fields for the profile destination. */
	private static function get_remote_custom_fields( $profile_id ) {
		$configuration = IntegrationResolver::get_profile( $profile_id );
		if ( null === $configuration ) {
			return array();
		}
		$config  = $configuration->get_configuration();
		$list_id = trim( (string) ( $config['emailoctopus_list_id'] ?? '' ) );
		$form_id = trim( (string) ( $config['emailoctopus_form_id'] ?? '' ) );

		if ( '' === $list_id && '' !== $form_id ) {
			$form = EmailOctopusApi::get_form( $form_id );
			if ( is_wp_error( $form ) ) {
				return $form;
			}
			$list_id = sanitize_text_field( (string) ( $form['list_id'] ?? '' ) );
		}
		if ( '' === $list_id ) {
			return array();
		}
		$list = EmailOctopusApi::get_list( $list_id );
		return is_wp_error( $list ) ? $list : EmailOctopusFieldMapper::get_custom_fields( $list );
	}

	/** Parse stage-one destination input. */
	private static function identity_input( $input ) {
		$value = sanitize_text_field( (string) ( $input['destination_value'] ?? self::destination_value( $input['destination'] ?? array() ) ) );
		$type  = '';
		$id    = '';
		if ( preg_match( '/^(form|list):(.+)$/', $value, $matches ) ) {
			$type = $matches[1];
			$id   = sanitize_text_field( $matches[2] );
		}
		return array(
			'label'       => (string) ( $input['label'] ?? '' ),
			'form_ids'    => $input['form_ids'] ?? array(),
			'destination' => array(
				'type' => $type,
				'id'   => $id,
			),
		);
	}

	/** Read rejected editor values from a one-time per-user transient. */
	private static function rejected_input( $profile_id, $section ) {
		$token = isset( $_GET['state'] ) ? sanitize_key( wp_unslash( $_GET['state'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Opaque admin-only display token.
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
			return null;
		}
		$key   = self::INPUT_TRANSIENT_PREFIX . get_current_user_id() . '_' . $token;
		$state = get_transient( $key );
		if ( ! is_array( $state ) || ( $state['profile_id'] ?? '' ) !== $profile_id || ( $state['section'] ?? '' ) !== $section ) {
			return null;
		}
		delete_transient( $key );
		return is_array( $state['input'] ?? null ) ? $state['input'] : null;
	}

	/** Persist rejected values and redirect without mutating settings. */
	private static function redirect_with_error( $error, $profile_id, $section, $input, $view ) {
		$token = md5( wp_generate_uuid4() . wp_rand() );
		set_transient(
			self::INPUT_TRANSIENT_PREFIX . get_current_user_id() . '_' . $token,
			array(
				'profile_id' => $profile_id,
				'section'    => $section,
				'input'      => $input,
				'message'    => $error->get_error_message(),
			),
			10 * MINUTE_IN_SECONDS
		);
		self::redirect(
			array(
				'view'       => $view,
				'profile_id' => $profile_id,
				'notice'     => 'error',
				'state'      => $token,
				'message'    => $error->get_error_message(),
			)
		);
	}

	/** Render a concise redirected notice. */
	private static function render_notice() {
		$notice   = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only admin notice.
		$messages = array(
			'created'         => __( 'Profile created. Dependent field choices have been refreshed from the saved forms.', 'ran-emailoctopus-jetpack-forms' ),
			'identity_saved'  => __( 'Identity and assignment saved. Dependent field choices have been refreshed.', 'ran-emailoctopus-jetpack-forms' ),
			'behaviour_saved' => __( 'Profile behaviour saved.', 'ran-emailoctopus-jetpack-forms' ),
			'deleted'         => __( 'Profile deleted.', 'ran-emailoctopus-jetpack-forms' ),
			'health_complete' => __( 'Health check completed.', 'ran-emailoctopus-jetpack-forms' ),
		);
		if ( 'error' === $notice ) {
			$message = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : __( 'The change was not saved.', 'ran-emailoctopus-jetpack-forms' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Escaped display-only message.
			echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
		} elseif ( isset( $messages[ $notice ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $notice ] ) . '</p></div>';
		}
	}

	/** Get posted profile array. */
	private static function posted_profile_input() {
		return isset( $_POST['profile'] ) && is_array( $_POST['profile'] ) ? wp_unslash( $_POST['profile'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Every caller verifies its action-specific nonce first.
	}

	/** Get a strict positive submitted revision. */
	private static function posted_revision() {
		$value = isset( $_POST['profile_revision'] ) ? wp_unslash( $_POST['profile_revision'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Every caller verifies its action-specific nonce first.
		return is_scalar( $value ) && preg_match( '/^[1-9]\d*$/', (string) $value ) ? (int) $value : 0;
	}

	/** Read a UUID from a request array. */
	private static function request_profile_id( $request ) {
		$value = is_array( $request ) && isset( $request['profile_id'] ) ? strtolower( sanitize_text_field( wp_unslash( $request['profile_id'] ) ) ) : '';
		return Settings::is_valid_profile_id( $value ) ? $value : '';
	}

	/** Require administrator capability. */
	private static function require_capability() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You are not allowed to manage integration profiles.', 'ran-emailoctopus-jetpack-forms' ),
				'',
				array( 'response' => 403 )
			);
		}
	}

	/** Redirect back to the integrations page. */
	private static function redirect( $args = array() ) {
		wp_safe_redirect( self::page_url( $args ) );
	}

	/** Build the integrations page URL. */
	private static function page_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGE_SLUG ), $args ), admin_url( 'options-general.php' ) );
	}

	/** Per-user, per-profile health transient key. */
	private static function health_transient_key( $profile_id ) {
		return self::HEALTH_TRANSIENT_PREFIX . get_current_user_id() . '_' . sanitize_key( $profile_id );
	}

	/** Convert destination array to editor value. */
	private static function destination_value( $destination ) {
		$type = is_array( $destination ) ? (string) ( $destination['type'] ?? '' ) : '';
		$id   = is_array( $destination ) ? (string) ( $destination['id'] ?? '' ) : '';
		return in_array( $type, array( 'form', 'list' ), true ) && '' !== $id ? $type . ':' . $id : '';
	}

	/** Human-readable destination. */
	private static function destination_label( $configuration ) {
		$form_id = trim( (string) ( $configuration['emailoctopus_form_id'] ?? '' ) );
		$list_id = trim( (string) ( $configuration['emailoctopus_list_id'] ?? '' ) );
		if ( '' !== $list_id ) {
			/* translators: %s: EmailOctopus list ID. */
			return sprintf( __( 'List %s', 'ran-emailoctopus-jetpack-forms' ), $list_id );
		}
		if ( '' !== $form_id ) {
			/* translators: %s: EmailOctopus form ID. */
			return sprintf( __( 'Form %s', 'ran-emailoctopus-jetpack-forms' ), $form_id );
		}
		return __( 'Not configured', 'ran-emailoctopus-jetpack-forms' );
	}

	/** Human-readable form IDs. */
	private static function form_ids_label( $form_ids ) {
		$form_ids = Settings::normalize_form_ids( $form_ids );
		return empty( $form_ids ) ? __( 'None', 'ran-emailoctopus-jetpack-forms' ) : implode( ', ', $form_ids );
	}

	/** Human-readable Jetpack source field. */
	private static function field_label( $field ) {
		$label = (string) ( $field['label'] ?? $field['key'] ?? '' );
		$type  = (string) ( $field['type'] ?? '' );
		return '' !== $type ? sprintf( '%s (%s)', $label, $type ) : $label;
	}

	/** Render an escaped error panel. */
	private static function render_error_box( $message ) {
		echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
	}
}
