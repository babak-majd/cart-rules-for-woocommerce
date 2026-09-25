<?php
/**
 * Plugin Name:          Cart Rules for WooCommerce
 * Plugin URI:           https://github.com/babak-majd/cart-rules-for-woocommerce
 * Description:          Minimum quantity and minimum spend per product, a minimum order amount for the whole cart, and allowed shipping methods per product — all set on the product edit screen.
 * Version:              1.2.0
 * Author:               Baabak Majd
 * Author URI:           https://bobclub.ir
 * Text Domain:          cart-rules-for-woocommerce
 * Domain Path:          /languages
 * Requires at least:    6.0
 * Requires PHP:         7.4
 * Requires Plugins:     woocommerce
 * WC requires at least: 7.4
 * WC tested up to:      11.1
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package CartRulesForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'CRFW_VERSION', '1.2.0' );
define( 'CRFW_PRODUCT', 'Cart Rules' );
define( 'CRFW_FILE', __FILE__ );
define( 'CRFW_PATH', plugin_dir_path( __FILE__ ) );
define( 'CRFW_URL', plugin_dir_url( __FILE__ ) );
define( 'CRFW_MIN_WC', '7.4' );

// Where the plugin lives, for the links in the dashboard. One place to change.
define( 'CRFW_SITE_URL', 'https://bobclub.ir' );
define( 'CRFW_REPO_URL', 'https://github.com/babak-majd/cart-rules-for-woocommerce' );
define( 'CRFW_SUPPORT_URL', 'https://wordpress.org/support/plugin/cart-rules-for-woocommerce/' );
define( 'CRFW_COFFEE_URL', 'https://bobclub.ir/coffee' );
define( 'CRFW_TELEGRAM_URL', 'https://t.me/bob_club' );

/**
 * Declare WooCommerce feature compatibility (HPOS and the block cart/checkout).
 *
 * Runs before WooCommerce initialises so the declarations are in place when
 * the features screen is rendered.
 */
function crfw_declare_wc_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
}
add_action( 'before_woocommerce_init', 'crfw_declare_wc_compatibility' );

/**
 * Load translations.
 *
 * WordPress.org language packs win over the bundled files; the bundled
 * `languages/` folder is the fallback for locales the packs do not cover yet.
 */
function crfw_load_textdomain() {
	load_plugin_textdomain( 'cart-rules-for-woocommerce', false, dirname( plugin_basename( CRFW_FILE ) ) . '/languages' );
}
add_action( 'init', 'crfw_load_textdomain' );

/**
 * Tell the admin why nothing happens when WooCommerce is missing or too old.
 */
function crfw_missing_wc_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	$msg = class_exists( 'WooCommerce' )
		/* translators: 1: plugin name, 2: minimum WooCommerce version. */
		? sprintf( __( '%1$s needs WooCommerce %2$s or newer. Please update WooCommerce.', 'cart-rules-for-woocommerce' ), 'Cart Rules for WooCommerce', CRFW_MIN_WC )
		/* translators: %s: plugin name. */
		: sprintf( __( '%s needs WooCommerce to be installed and active.', 'cart-rules-for-woocommerce' ), 'Cart Rules for WooCommerce' );
	echo '<div class="notice notice-error"><p>' . esc_html( $msg ) . '</p></div>';
}

/**
 * Boot the plugin once every plugin is loaded, so the WooCommerce check is reliable.
 */
function crfw_boot() {
	if ( ! class_exists( 'WooCommerce' ) || version_compare( WC()->version, CRFW_MIN_WC, '<' ) ) {
		add_action( 'admin_notices', 'crfw_missing_wc_notice' );
		return;
	}

	require_once CRFW_PATH . 'includes/crfw-functions.php';
	require_once CRFW_PATH . 'includes/class-crfw-rules.php';
	require_once CRFW_PATH . 'includes/class-crfw-cart.php';
	require_once CRFW_PATH . 'includes/class-crfw-shipping.php';
	require_once CRFW_PATH . 'includes/class-crfw-plugin.php';

	CRFW_Plugin::instance();
}
add_action( 'plugins_loaded', 'crfw_boot' );
