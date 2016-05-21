<?php
/**
 * @package WPENC
 * @version 0.5.0
 * @author Felix Arntz <felix-arntz@leaves-and-love.net>
 */

namespace WPENC;

use WPENC\Core\Util as CoreUtil;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

if ( ! class_exists( 'WPENC\Admin' ) ) {
	/**
	 * This class performs the necessary actions in the WordPress admin to generate the settings page.
	 *
	 * @internal
	 * @since 0.5.0
	 */
	class Admin {
		const PAGE_SLUG = 'wp_encrypt';

		protected $context = 'site';

		public function __construct( $context = 'site' ) {
			$this->context = $context;
		}

		/**
		 * Initialization method.
		 *
		 * @since 0.5.0
		 */
		public function run() {
			$menu_hook = 'admin_menu';
			$option_hook = 'pre_update_option_wp_encrypt_settings';
			if ( 'network' === $this->context ) {
				$menu_hook = 'network_admin_menu';
				$option_hook = 'pre_update_site_option_wp_encrypt_settings';
			}

			add_action( 'admin_init', array( $this, 'init_settings' ) );
			add_action( $menu_hook, array( $this, 'init_menu' ) );

			add_filter( $option_hook, array( $this, 'check_valid' ) );
		}

		public function init_settings() {
			register_setting( 'wp_encrypt_settings', 'wp_encrypt_settings', array( $this, 'validate_settings' ) );
			add_settings_section( 'wp_encrypt_settings', __( 'Settings', 'wp-encrypt' ), array( $this, 'render_settings_description' ), self::PAGE_SLUG );
			add_settings_field( 'organization', __( 'Organization Name', 'wp-encrypt' ), array( $this, 'render_settings_field' ), self::PAGE_SLUG, 'wp_encrypt_settings', array( 'id' => 'organization' ) );
			add_settings_field( 'country_name', __( 'Country Name', 'wp-encrypt' ), array( $this, 'render_settings_field' ), self::PAGE_SLUG, 'wp_encrypt_settings', array( 'id' => 'country_name' ) );
			add_settings_field( 'country_code', __( 'Country Code', 'wp-encrypt' ), array( $this, 'render_settings_field' ), self::PAGE_SLUG, 'wp_encrypt_settings', array( 'id' => 'country_code' ) );
		}

		public function init_menu() {
			$parent = 'options-general.php';
			if ( 'network' === $this->context ) {
				$parent = 'settings.php';
			}
			add_submenu_page( $parent, __( 'WP Encrypt', 'wp-encrypt' ), __( 'WP Encrypt', 'wp-encrypt' ), 'manage_certificates', self::PAGE_SLUG, array( $this, 'render_page' ) );
		}

		public function render_page() {
			if ( CoreUtil::needs_filesystem_credentials() ) {
				?>
				<div class="notice notice-warning">
					<p><?php printf( __( 'The directories %1$s and %2$s that WP Encrypt needs access to are not automatically writable by the site. Unless you change this, it is not possible to auto-renew certificates.', 'wp-encrypt' ), '<code>' . CoreUtil::get_letsencrypt_certificates_dir_path() . '</code>', '<code>' . CoreUtil::get_letsencrypt_challenges_dir_path() . '</code>' ); ?></p>
					<p><?php _e( 'Note that you can still manually renew certificates by providing valid filesystem credentials each time.', 'wp-encrypt' ); ?></p>
				</div>
				<?php
			}

			$form_action = 'network' === $this->context ? 'settings.php' : 'options.php';

			?>
			<style type="text/css">
				.wp-encrypt-form {
					margin-bottom: 40px;
				}

				.delete-button {
					margin-left: 6px;
					line-height: 28px;
					text-decoration: none;
				}

				.delete-button:hover {
					color: #f00;
					text-decoration: none;
					border: none;
				}
			</style>
			<div class="wrap">
				<h1><?php _e( 'WP Encrypt', 'wp-encrypt' ); ?></h1>

				<form class="wp-encrypt-form" method="post" action="<?php echo $form_action; ?>">
					<?php settings_fields( 'wp_encrypt_settings' ); ?>
					<?php do_settings_sections( self::PAGE_SLUG ); ?>
					<?php submit_button( '', 'primary large', 'submit', false ); ?>
				</form>

				<?php if ( Util::get_option( 'valid' ) ) : ?>
					<h2><?php _e( 'Let&rsquo;s Encrypt Account', 'wp-encrypt' ); ?></h2>

					<form class="wp-encrypt-form" method="post" action="<?php echo $form_action; ?>">
						<p class="description">
							<?php _e( 'By clicking on this button, you will register an account for the above organization with Let&apos;s Encrypt.', 'wp-encrypt' ); ?>
						</p>
						<?php $this->action_post_fields( 'wpenc_register_account' ); ?>
						<?php submit_button( __( 'Register Account', 'wp-encrypt' ), 'secondary', 'submit', false, array( 'id' => 'register-account-button' ) ); ?>
					</form>

					<?php if ( Util::can_generate_certificate() ) : ?>
						<h2><?php _e( 'Let&rsquo;s Encrypt Certificate', 'wp-encrypt' ); ?></h2>

						<form class="wp-encrypt-form" method="post" action="<?php echo $form_action; ?>">
							<p class="description">
								<?php _e( 'Here you can manage the actual certificate.', 'wp-encrypt' ); ?>
								<?php if ( 'network' === $this->context ) : ?>
									<?php _e( 'The certificate will be valid for all the sites in your network by default.', 'wp-encrypt' ); ?>
								<?php endif; ?>
							</p>
							<?php $this->action_post_fields( 'wpenc_generate_certificate' ); ?>
							<?php if ( App::is_multinetwork() ) : ?>
								<p>
									<input type="checkbox" id="include_all_networks" name="include_all_networks" value="1" />
									<span><?php _e( 'Generate a global certificate for all networks? This will ensure that all sites in your entire WordPress setup are covered.', 'wp-encrypt' ); ?></span>
								</p>
							<?php endif; ?>
							<?php submit_button( __( 'Generate Certificate', 'wp-encrypt' ), 'secondary', 'submit', false, array( 'id' => 'generate-certificate-button' ) ); ?>
							<a id="revoke-certificate-button" class="delete-button" href="<?php echo $this->action_get_url( 'wpenc_revoke_certificate' ); ?>"><?php _e( 'Revoke Certificate', 'wp-encrypt' ); ?></a>
						</form>
					<?php endif; ?>
				<?php endif; ?>
			</div>
			<?php

			// for AJAX
			if ( CoreUtil::needs_filesystem_credentials() ) {
				wp_print_request_filesystem_credentials_modal();
			}
		}

		public function render_settings_description() {
			echo '<p class="description">' . __( 'The following settings are required to generate a certificate.', 'wp-encrypt' ) . '</p>';
		}

		public function render_settings_field( $args = array() ) {
			$value = Util::get_option( $args['id'] );

			$more_args = '';
			if ( 'country_code' === $args['id'] ) {
				$more_args .= ' maxlength="2"';
			}

			echo '<input type="text" id="' . $args['id'] . '" name="wp_encrypt_settings[' . $args['id'] . ']" value="' . $value . '"' . $more_args . ' />';
			switch ( $args['id'] ) {
				case 'organization':
					$description = __( 'The name of the organization behind this site.', 'wp-encrypt' );
					break;
				case 'country_name':
					$description = __( 'The name of the country the organization resides in.', 'wp-encrypt' );
					break;
				case 'country_code':
					$description = __( 'The two-letter country code for the country specified above.', 'wp-encrypt' );
					break;
				default:
			}
			if ( isset( $description ) ) {
				echo ' <span class="description">' . $description . '</span>';
			}
		}

		public function validate_settings( $options = array() ) {
			$options = array_map( 'strip_tags', array_map( 'trim', $options ) );

			if ( isset( $options['country_code'] ) ) {
				$options['country_code'] = strtoupper( substr( $options['country_code'], 0, 2 ) );
			}
			return $options;
		}

		public function check_valid( $options ) {
			$required_fields = array( 'country_code', 'country_name', 'organization' );
			$valid = true;
			foreach ( $required_fields as $required_field ) {
				if ( ! isset( $options[ $required_field ] ) || empty( $options[ $required_field ] ) ) {
					$valid = false;
					break;
				}
			}
			$options['valid'] = $valid;
			return $options;
		}

		protected function action_post_fields( $action ) {
			echo '<input type="hidden" name="action" value="' . $action . '" />';
			wp_nonce_field( 'wp_encrypt_action' );
		}

		protected function action_get_url( $action ) {
			$url = ( 'network' === $this->context ) ? network_admin_url( 'settings.php' ) : admin_url( 'options-general.php' );
			$url = add_query_arg( 'action', $action, $url );
			$url = wp_nonce_url( $url, 'wp_encrypt_action' );
			return $url;
		}
	}
}
