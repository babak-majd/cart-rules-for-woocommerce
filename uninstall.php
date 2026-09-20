<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * Nothing is removed unless the merchant opted in under
 * WooCommerce → Settings → Cart Rules → "Remove data", so deleting the plugin
 * by mistake (or to reinstall) never loses per-product rules.
 *
 * @package CartRulesForWooCommerce
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( 'yes' !== get_option( 'crfw_delete_data_on_uninstall', 'no' ) ) {
	return;
}

global $wpdb;

// Options — every key the plugin owns starts with crfw_.
$crfw_options = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'crfw\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
foreach ( $crfw_options as $crfw_option ) {
	delete_option( $crfw_option );
}

// Per-product rules.
foreach ( array( '_crfw_min_qty', '_crfw_min_amount', '_crfw_exempt', '_crfw_shipping_methods' ) as $crfw_meta_key ) {
	delete_post_meta_by_key( $crfw_meta_key );
}

wp_cache_flush();
