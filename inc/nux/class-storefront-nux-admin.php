<?php
/**
 * Storefront NUX Admin Class
 *
 * @author   WooThemes
 * @package  storefront
 * @since    2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Storefront_NUX_Admin' ) ) :

	/**
	 * The Storefront NUX Admin class
	 */
	class Storefront_NUX_Admin {
		/**
		 * Setup class.
		 *
		 * @since 2.2
		 */
		public function __construct() {
			add_action( 'admin_enqueue_scripts',                   array( $this, 'enqueue_scripts' ) );
			add_action( 'admin_notices',                           array( $this, 'admin_notices' ), 99 );
			add_action( 'wp_ajax_storefront_dismiss_notice',       array( $this, 'dismiss_nux' ) );
			add_action( 'admin_post_storefront_guided_tour',       array( $this, 'redirect_customizer' ) );
			add_action( 'activated_plugin',                        array( $this, 'activated_plugin' ) );
			add_action( 'after_theme_setup',                       array( $this, 'log_fresh_site_state' ) );
		}

		/**
		 * Enqueue scripts.
		 *
		 * @since 2.2
		 */
		public function enqueue_scripts() {
			global $wp_customize, $storefront_version;

			if ( isset( $wp_customize ) || true === (bool) get_option( 'storefront_nux_dismissed' ) ) {
				return;
			}

			wp_enqueue_style( 'storefront-admin-nux', get_template_directory_uri() . '/inc/nux/assets/css/admin.css', '', $storefront_version );

			wp_enqueue_script( 'storefront-admin-nux', get_template_directory_uri() . '/inc/nux/assets/js/admin.min.js', array( 'jquery' ), $storefront_version, 'all' );

			$storefront_nux = array(
				'nonce' => wp_create_nonce( 'storefront_notice_dismiss' )
			);

			wp_localize_script( 'storefront-admin-nux', 'storefrontNUX', $storefront_nux );
		}

		/**
		 * Output admin notices.
		 *
		 * @since 2.2
		 */
		public function admin_notices() {
			if ( true === (bool) get_option( 'storefront_nux_dismissed' ) ) {
				return;
			}
			?>

			<div class="notice notice-info sf-notice-nux is-dismissible">
				<span class="sf-icon">
					<?php echo '<img src="' . esc_url( get_template_directory_uri() ) . '/inc/nux/assets/images/storefront-icon.svg" alt="Storefront" width="250" />'; ?>
				</span>

				<div class="notice-content">
				<?php
				if ( ! storefront_is_woocommerce_activated() && current_user_can( 'install_plugins' ) && current_user_can( 'activate_plugins' ) ) :
					if ( $url = $this->_is_woocommerce_installed() ) {
						$button = array(
							'message' => esc_attr__( 'Activate WooCommerce', 'storefront' ),
							'url'     => esc_url( $url ),
							'classes' => ' activate-now'
						);
					} else {
						$url = wp_nonce_url( add_query_arg( array(
							'action' => 'install-plugin',
							'plugin' => 'woocommerce',
							), self_admin_url( 'update.php' ) ), 'install-plugin_woocommerce' );

						$button = array(
							'message' => esc_attr__( 'Install WooCommerce', 'storefront' ),
							'url'     => esc_url( $url ),
							'classes' => ' install-now sf-install-woocommerce'
						);
					}
				?>
					<h2><?php esc_attr_e( 'Thanks for installing Storefront <3', 'storefront' ); ?></h2>
					<p><?php esc_attr_e( 'To add eCommerce features you need to install the WooCommerce plugin.', 'storefront' ); ?></p>
					<p><a href="<?php echo $button['url']; ?>" class="sf-nux-button<?php echo $button['classes']; ?>" data-originaltext="<?php echo $button['message']; ?>" aria-label="<?php echo $button['message']; ?>"><?php echo $button['message']; ?></a></p>
				<?php endif; ?>

				<?php if ( storefront_is_woocommerce_activated() ) : ?>
					<h2><?php printf( esc_html__( 'Getting started with  %sStorefront%s', 'storefront' ), '<strong>', '</strong>' ); ?></h2>
					<p><?php esc_attr_e( 'Now it\'s time to make it your own. Allow us to guide you through a tour of the Storefront options.', 'storefront' ); ?></p>
					<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
						<input type="hidden" name="action" value="storefront_guided_tour">
						<?php wp_nonce_field( 'storefront_guided_tour' ); ?>

						<?php if ( true === (bool) get_option( 'storefront_nux_fresh_site' ) ) : ?>
							<input type="hidden" name="homepage" value="on">
							<input type="hidden" name="products" value="on">
						<?php endif; ?>

						<?php if ( false === (bool) get_option( 'storefront_nux_fresh_site' ) ) : ?>
							<label>
								<input type="checkbox" name="homepage" checked>
								<?php
									if ( 'page' === get_option( 'show_on_front' ) ) {
										esc_attr_e( 'Apply Storefront homepage template to your static homepage', 'storefront' );
									} else {
										esc_attr_e( 'Create a homepage using the Storefront homepage template', 'storefront' );
									}
								?>
							</label>

							<label>
								<input type="checkbox" name="products" checked>
								<?php esc_attr_e( 'Add example products', 'storefront' ); ?>
							</label>
						<?php endif; ?>

						<label>
							<input type="submit" name="storefront-guided-tour" class="sf-nux-button" value="<?php esc_attr_e( 'Let\'s go!', 'storefront' ); ?>">
						</label>
					</form>
				<?php endif; ?>
				</div>
			</div>
		<?php }

		/**
		 * AJAX dismiss notice.
		 *
		 * @since 2.2
		 */
		public function dismiss_nux() {
			$nonce = ! empty( $_POST['nonce'] ) ? $_POST['nonce'] : false;

			if ( ! $nonce || ! wp_verify_nonce( $nonce, 'storefront_notice_dismiss' ) || ! current_user_can( 'manage_options' ) ) {
				die();
			}

			update_option( 'storefront_nux_dismissed', true );
		}

		/**
		 * Redirects to the customizer with the correct variables.
		 *
		 * @since 2.2
		 */
		public function redirect_customizer() {
			check_admin_referer( 'storefront_guided_tour' );

			if ( current_user_can( 'manage_options' ) ) {
				// Make sure the fresh_site flag is set to true.
				update_option( 'fresh_site', true );

				// Dismiss notice.
				update_option( 'storefront_nux_dismissed', true );
			}

			if ( current_user_can( 'edit_pages' ) ) {
				$this->_set_woocommerce_pages_full_width();
			}

			$args = array( 'sf_guided_tour' => '1' );

			$tasks = array();

			if ( ! empty( $_REQUEST['homepage'] ) && 'on' === $_REQUEST['homepage'] ) {
				if ( current_user_can( 'edit_pages' ) && 'page' === get_option( 'show_on_front' ) ) {
					$this->_assign_page_template( get_option( 'page_on_front' ), 'template-homepage.php' );
				} else {
					$tasks[] = 'homepage';
				}
			}

			if ( ! empty( $_REQUEST['products'] ) && 'on' === $_REQUEST['products'] ) {
				$tasks[] = 'products';
			}

			if ( ! empty( $tasks ) ) {
				$args['sf_tasks'] = implode( ',', $tasks );
			}

			wp_safe_redirect( add_query_arg( $args, admin_url( 'customize.php' ) ) );

			die();
		}

		/**
		 * Get WooCommerce page ids.
		 *
		 * @since 2.2
		 */
		public static function get_woocommerce_pages() {
			$woocommerce_pages = array();

			$wc_pages_options = apply_filters( 'storefront_page_option_names', array(
				'woocommerce_cart_page_id',
				'woocommerce_checkout_page_id',
				'woocommerce_myaccount_page_id',
				'woocommerce_shop_page_id',
				'woocommerce_terms_page_id'
			) );

			foreach ( $wc_pages_options as $option ) {
				$page_id = get_option( $option );

				if ( ! empty( $page_id ) ) {
					$page_id = intval( $page_id );

					if ( null !== get_post( $page_id ) ) {
						$woocommerce_pages[ $option ] = $page_id;
					}
				}
			}

			return $woocommerce_pages;
		}

		/**
		 * Update Storefront fresh site flag after WooCommerce activation.
		 *
		 * @since 2.2
		 * @param string $plugin
		 * @return void
		 */
		public function activated_plugin( $plugin ) {
			if ( 'woocommerce/woocommerce.php' === $plugin ) {
				$this->log_fresh_site_state();
			}
		}

		/**
		 * Update Storefront fresh site flag.
		 *
		 * @since 2.2
		 */
		public function log_fresh_site_state() {
			if ( current_user_can( 'manage_options' ) ) {
				update_option( 'storefront_nux_fresh_site', get_option( 'fresh_site' ) );
			}
		}

		/**
		 * Check if WooCommerce is installed.
		 *
		 * @since 2.2
		 */
		private function _is_woocommerce_installed() {
			if ( file_exists( WP_PLUGIN_DIR . '/woocommerce' ) ) {
				$plugins = get_plugins( '/woocommerce' );

				if ( ! empty( $plugins ) ) {
					$keys        = array_keys( $plugins );
					$plugin_file = 'woocommerce/' . $keys[0];
					$url         = wp_nonce_url( add_query_arg( array(
						'action' => 'activate',
						'plugin' => $plugin_file
					), admin_url( 'plugins.php' ) ), 'activate-plugin_' . $plugin_file );

					return $url;
				}
			}

			return false;
		}

		/**
		 * Set WooCommerce pages to use the full width template.
		 *
		 * @since 2.2
		 */
		private function _set_woocommerce_pages_full_width() {
			$wc_pages = $this->get_woocommerce_pages();

			foreach ( $wc_pages as $option => $page_id ) {
				$this->_assign_page_template( $page_id, 'template-fullwidth.php' );
			}
		}

		/**
		 * Given a page id assign a given page template to it.
		 *
		 * @since 2.2
		 * @param int $page_id
		 * @param string $template
		 * @return void
		 */
		private function _assign_page_template( $page_id, $template ) {
			if ( empty( $page_id ) || empty( $template ) || '' === locate_template( $template ) ) {
				return false;
			}

			update_post_meta( $page_id, '_wp_page_template', $template );
		}
	}

endif;

return new Storefront_NUX_Admin();