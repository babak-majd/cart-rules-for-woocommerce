<?php
/**
 * Plugin bootstrap: wires the front-end enforcement and the admin screens.
 *
 * @package CartRulesForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin bootstrap.
 */
final class CRFW_Plugin {

	/** The WooCommerce → Settings tab slug. */
	const SETTINGS_TAB = 'crfw';

	/**
	 * The single instance.
	 *
	 * @var CRFW_Plugin
	 */
	private static $instance;

	/**
	 * The single instance.
	 *
	 * @return CRFW_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire the front-end enforcement and, in wp-admin, the screens.
	 */
	private function __construct() {
		CRFW_Cart::init();
		CRFW_Shipping::init();

		if ( is_admin() ) {
			require_once CRFW_PATH . 'includes/admin/class-crfw-product-data.php';
			CRFW_Product_Data::init();

			require_once CRFW_PATH . 'includes/admin/class-crfw-help.php';
			CRFW_Help::init();

			add_filter( 'woocommerce_get_settings_pages', array( $this, 'settings_page' ) );
			add_filter( 'plugin_action_links_' . plugin_basename( CRFW_FILE ), array( $this, 'action_links' ) );
			add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
		}
	}

	/**
	 * Register the "Cart Rules" tab under WooCommerce → Settings.
	 *
	 * @param array $pages Settings pages.
	 * @return array
	 */
	public function settings_page( $pages ) {
		require_once CRFW_PATH . 'includes/admin/class-crfw-settings.php';
		$pages[] = new CRFW_Settings();
		return $pages;
	}

	/**
	 * "Settings" link on the Plugins screen.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		require_once CRFW_PATH . 'includes/admin/class-crfw-help.php';
		$url = admin_url( 'admin.php?page=wc-settings&tab=' . self::SETTINGS_TAB );
		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'cart-rules-for-woocommerce' ) . '</a>',
			'<a href="' . esc_url( CRFW_Help::url() ) . '">' . esc_html__( 'Guide', 'cart-rules-for-woocommerce' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Docs / support / coffee links in the plugin row.
	 *
	 * @param string[] $links Links.
	 * @param string   $file  Plugin file.
	 * @return string[]
	 */
	public function row_meta( $links, $file ) {
		if ( plugin_basename( CRFW_FILE ) !== $file ) {
			return $links;
		}
		$links[] = '<a href="' . esc_url( CRFW_REPO_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Docs', 'cart-rules-for-woocommerce' ) . '</a>';
		$links[] = '<a href="' . esc_url( CRFW_SUPPORT_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Support', 'cart-rules-for-woocommerce' ) . '</a>';
		$links[] = '<a href="' . esc_url( CRFW_COFFEE_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Buy me a coffee ☕', 'cart-rules-for-woocommerce' ) . '</a>';
		return $links;
	}

	/**
	 * The shared credit line shown at the bottom of the plugin's screens.
	 *
	 * @return string HTML.
	 */
	public static function credit_html() {
		$links  = sprintf(
			'<a href="%1$s" target="_blank" rel="noopener">bobclub.ir</a> · <a href="%2$s" target="_blank" rel="noopener">%3$s</a> · <a href="%4$s" target="_blank" rel="noopener">GitHub</a>',
			esc_url( CRFW_SITE_URL ),
			esc_url( CRFW_TELEGRAM_URL ),
			esc_html__( 'Telegram', 'cart-rules-for-woocommerce' ),
			esc_url( CRFW_REPO_URL )
		);
		$coffee = sprintf(
			/* translators: %s: link reading "Buy me a coffee". */
			esc_html__( 'Enjoying it? %s', 'cart-rules-for-woocommerce' ),
			'<a href="' . esc_url( CRFW_COFFEE_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Buy me a coffee ☕', 'cart-rules-for-woocommerce' ) . '</a>'
		);
		return '<p class="description crfw-credit">' . esc_html( CRFW_PRODUCT . ' for WooCommerce v' . CRFW_VERSION ) . ' · GPL-2.0-or-later · ' . $links . ' · ' . $coffee . '</p>';
	}
}
