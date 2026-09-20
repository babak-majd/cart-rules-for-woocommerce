<?php
/**
 * Small shared helpers.
 *
 * @package CartRulesForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read a plugin option with its default.
 *
 * Every option the plugin owns is prefixed `crfw_` and listed here with its
 * default, so `uninstall.php` and the settings screen share one source of truth.
 *
 * @param string $key Option key without the `crfw_` prefix.
 * @return mixed
 */
function crfw_get_option( $key ) {
	$defaults = crfw_option_defaults();
	$default  = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
	return get_option( 'crfw_' . $key, $default );
}

/**
 * Every option the plugin stores, with its default value.
 *
 * @return array<string,mixed>
 */
function crfw_option_defaults() {
	return array(
		'cart_min_amount'          => '',
		'cart_amount_basis'        => 'subtotal',
		'default_min_qty'          => '',
		'default_min_amount'       => '',
		'enforce_qty_input'        => 'yes',
		'show_product_notice'      => 'yes',
		'msg_min_qty'              => '',
		'msg_min_amount'           => '',
		'msg_cart_min'             => '',
		'msg_shipping_conflict'    => '',
		'msg_product_notice'       => '',
		'delete_data_on_uninstall' => 'no',
	);
}

/**
 * Default customer-facing messages. Kept in one place so the settings screen
 * can show them as placeholders and the checks can fall back to them.
 *
 * @param string $key Message key (min_qty, min_amount, cart_min, shipping_conflict, product_notice).
 * @return string
 */
function crfw_default_message( $key ) {
	switch ( $key ) {
		case 'min_qty':
			return __( '“{product}” must be ordered in a quantity of at least {min} (you have {current}).', 'cart-rules-for-woocommerce' );
		case 'min_amount':
			return __( '“{product}” requires a minimum purchase of {min} (you have {current}).', 'cart-rules-for-woocommerce' );
		case 'cart_min':
			return __( 'A minimum order of {min} is required to check out (your order is {current}).', 'cart-rules-for-woocommerce' );
		case 'shipping_conflict':
			return __( 'The items in your cart cannot be shipped together: no shipping method is available for all of them. Please order {products} separately.', 'cart-rules-for-woocommerce' );
		case 'product_notice':
			return __( 'Minimum order: {min}', 'cart-rules-for-woocommerce' );
	}
	return '';
}

/**
 * Resolve a message: the merchant's custom text if set, otherwise the default,
 * with `{placeholders}` substituted.
 *
 * @param string               $key          Message key.
 * @param array<string,string> $replacements Placeholder => value (already escaped/formatted for display).
 * @return string
 */
function crfw_message( $key, array $replacements = array() ) {
	$custom  = (string) crfw_get_option( 'msg_' . $key );
	$message = '' !== trim( $custom ) ? $custom : crfw_default_message( $key );

	/**
	 * Filter a customer-facing message before placeholders are substituted.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message      The template.
	 * @param string $key          Message key.
	 * @param array  $replacements Placeholder values.
	 */
	$message = apply_filters( 'crfw_message_template', $message, $key, $replacements );

	$search  = array();
	$replace = array();
	foreach ( $replacements as $placeholder => $value ) {
		$search[]  = '{' . $placeholder . '}';
		$replace[] = $value;
	}
	return str_replace( $search, $replace, $message );
}

/**
 * Normalise a merchant-entered amount ("1,500.00", "۱۵۰۰", "") to a float, 0 when empty.
 *
 * @param mixed $value Raw value.
 * @return float
 */
function crfw_to_amount( $value ) {
	if ( is_array( $value ) || null === $value ) {
		return 0.0;
	}
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return 0.0;
	}
	return (float) wc_format_decimal( $value );
}

/**
 * Normalise a quantity to a non-negative integer.
 *
 * @param mixed $value Raw value.
 * @return int
 */
function crfw_to_qty( $value ) {
	if ( is_array( $value ) || null === $value ) {
		return 0;
	}
	return max( 0, absint( wc_format_decimal( trim( (string) $value ) ) ) );
}

/**
 * A price as plain text ("1,500,000 ریال", "$25.00") for use inside a message.
 *
 * `wc_price()` returns markup with HTML entities; messages travel through
 * notices, Store API JSON and the block checkout, so they carry plain text.
 *
 * @param float $amount Amount.
 * @return string
 */
function crfw_format_price( $amount ) {
	return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
}
