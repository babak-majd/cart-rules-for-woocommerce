<?php
/**
 * Customer-facing enforcement of the minimums.
 *
 * Three layers, so the shopper is guided before being refused:
 *  1. The quantity fields default to the minimum (single product, archive
 *     buttons, the block cart/checkout selectors).
 *  2. Adding or updating a line below its minimum is refused with a clear message.
 *  3. The cart as a whole is checked on the cart and checkout pages (classic)
 *     and through the Store API (block checkout); any violation blocks checkout.
 *
 * @package CartRulesForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Front-end enforcement of the minimums.
 */
class CRFW_Cart {

	/**
	 * Hook everything up.
	 */
	public static function init() {
		// 1) Guide: quantity inputs and add-to-cart buttons default to the minimum.
		if ( 'yes' === crfw_get_option( 'enforce_qty_input' ) ) {
			add_filter( 'woocommerce_quantity_input_min', array( __CLASS__, 'quantity_input_min' ), 10, 2 );
			add_filter( 'woocommerce_quantity_input_args', array( __CLASS__, 'quantity_input_args' ), 10, 2 );
			add_filter( 'woocommerce_loop_add_to_cart_args', array( __CLASS__, 'loop_add_to_cart_args' ), 10, 2 );
			add_filter( 'woocommerce_store_api_product_quantity_minimum', array( __CLASS__, 'store_api_quantity_minimum' ), 10, 2 );
		}

		// 2) Refuse: adding / updating below the minimum quantity.
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_add_to_cart' ), 10, 3 );
		add_filter( 'woocommerce_update_cart_validation', array( __CLASS__, 'validate_update_cart' ), 10, 4 );

		// 3) Check the whole cart: classic pages and the Store API.
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'check_cart_items' ) );
		add_action( 'woocommerce_store_api_cart_errors', array( __CLASS__, 'store_api_cart_errors' ), 10, 2 );

		// Tell the shopper on the product page before they reach the cart.
		if ( 'yes' === crfw_get_option( 'show_product_notice' ) ) {
			add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'product_notice' ), 25 );
		}
		// The same note as a shortcode, for page-builder product templates that
		// do not run the single-product hooks (Elementor, Divi, block templates).
		add_shortcode( 'crfw_minimum', array( __CLASS__, 'shortcode_minimum' ) );
	}

	/**
	 * Raise the `min` of the quantity input to the product's minimum quantity.
	 *
	 * @param int        $min     Current minimum.
	 * @param WC_Product $product Product (a variation on the cart page).
	 * @return int
	 */
	public static function quantity_input_min( $min, $product ) {
		if ( ! $product instanceof WC_Product ) {
			return $min;
		}
		$rules = CRFW_Rules::get_product_rules( $product );
		return $rules['min_qty'] > 0 ? max( (int) $min, $rules['min_qty'] ) : $min;
	}

	/**
	 * The cart template passes an explicit `min_value`, which wins over the
	 * `_min` filter above; raise it here so the cart's quantity fields agree.
	 *
	 * @param array      $args    Quantity input args.
	 * @param WC_Product $product Product.
	 * @return array
	 */
	public static function quantity_input_args( $args, $product ) {
		if ( ! $product instanceof WC_Product || ! isset( $args['min_value'] ) ) {
			return $args;
		}
		$rules = CRFW_Rules::get_product_rules( $product );
		if ( $rules['min_qty'] > 0 && (int) $args['min_value'] < $rules['min_qty'] ) {
			$args['min_value'] = $rules['min_qty'];
			if ( isset( $args['input_value'] ) && (int) $args['input_value'] < $rules['min_qty'] && ! is_cart() ) {
				$args['input_value'] = $rules['min_qty'];
			}
		}
		return $args;
	}

	/**
	 * Make the archive "Add to cart" button add the minimum quantity, not 1.
	 *
	 * @param array      $args    Button args.
	 * @param WC_Product $product Product.
	 * @return array
	 */
	public static function loop_add_to_cart_args( $args, $product ) {
		$rules = CRFW_Rules::get_product_rules( $product );
		if ( $rules['min_qty'] > 1 ) {
			$args['quantity'] = $rules['min_qty'];
		}
		return $args;
	}

	/**
	 * The minimum the block cart/checkout quantity selectors allow (Store API).
	 *
	 * @param int        $minimum Current minimum.
	 * @param WC_Product $product Product.
	 * @return int
	 */
	public static function store_api_quantity_minimum( $minimum, $product ) {
		$rules = CRFW_Rules::get_product_rules( $product );
		return $rules['min_qty'] > 0 ? max( (int) $minimum, $rules['min_qty'] ) : $minimum;
	}

	/**
	 * Refuse adding a product when the resulting quantity would be below its minimum.
	 *
	 * @param bool $passed       Whether validation passed so far.
	 * @param int  $product_id   Product id.
	 * @param int  $quantity     Quantity being added.
	 * @return bool
	 */
	public static function validate_add_to_cart( $passed, $product_id, $quantity ) {
		if ( ! $passed ) {
			return $passed;
		}
		$rules = CRFW_Rules::get_product_rules( $product_id );
		if ( $rules['min_qty'] <= 0 || ! $rules['product'] ) {
			return $passed;
		}

		$in_cart = self::quantity_in_cart( $rules['product']->get_id() );
		$total   = $in_cart + max( 0, (int) $quantity );
		if ( $total >= $rules['min_qty'] ) {
			return $passed;
		}

		wc_add_notice(
			crfw_message(
				'min_qty',
				array(
					'product' => $rules['product']->get_name(),
					'min'     => number_format_i18n( $rules['min_qty'] ),
					'current' => number_format_i18n( $total ),
				)
			),
			'error'
		);
		return false;
	}

	/**
	 * Refuse a cart quantity update that would drop a product below its minimum.
	 *
	 * Removing a line (quantity 0) is always allowed.
	 *
	 * @param bool   $passed        Whether validation passed so far.
	 * @param string $cart_item_key Cart item key.
	 * @param array  $values        Cart item.
	 * @param int    $quantity      New quantity.
	 * @return bool
	 */
	public static function validate_update_cart( $passed, $cart_item_key, $values, $quantity ) {
		if ( ! $passed || (int) $quantity <= 0 || empty( $values['data'] ) ) {
			return $passed;
		}
		$rules = CRFW_Rules::get_product_rules( $values['data'] );
		if ( $rules['min_qty'] <= 0 || ! $rules['product'] ) {
			return $passed;
		}

		$others = self::quantity_in_cart( $rules['product']->get_id(), $cart_item_key );
		$total  = $others + (int) $quantity;
		if ( $total >= $rules['min_qty'] ) {
			return $passed;
		}

		wc_add_notice(
			crfw_message(
				'min_qty',
				array(
					'product' => $rules['product']->get_name(),
					'min'     => number_format_i18n( $rules['min_qty'] ),
					'current' => number_format_i18n( $total ),
				)
			),
			'error'
		);
		return false;
	}

	/**
	 * Quantity of a (parent) product already in the cart, across its variations.
	 *
	 * @param int    $parent_id   Parent product id.
	 * @param string $exclude_key A cart line to leave out (the one being updated).
	 * @return int
	 */
	private static function quantity_in_cart( $parent_id, $exclude_key = '' ) {
		if ( ! WC()->cart ) {
			return 0;
		}
		$groups = CRFW_Rules::group_cart_items( WC()->cart->get_cart() );
		if ( ! isset( $groups[ $parent_id ] ) ) {
			return 0;
		}
		$qty = $groups[ $parent_id ]['qty'];
		if ( '' !== $exclude_key && in_array( $exclude_key, $groups[ $parent_id ]['keys'], true ) ) {
			$item = WC()->cart->get_cart_item( $exclude_key );
			$qty -= isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;
		}
		return max( 0, $qty );
	}

	/**
	 * Classic cart & checkout: surface every violation as an error notice.
	 *
	 * WooCommerce refuses to place the order while an error notice exists, so
	 * this is what actually blocks checkout on the shortcode pages.
	 */
	public static function check_cart_items() {
		if ( ! WC()->cart ) {
			return;
		}
		foreach ( CRFW_Rules::evaluate_cart( WC()->cart ) as $violation ) {
			if ( ! wc_has_notice( $violation['message'], 'error' ) ) {
				wc_add_notice( $violation['message'], 'error' );
			}
		}
	}

	/**
	 * Block cart & checkout: report violations through the Store API.
	 *
	 * The checkout block shows these and will not submit the order.
	 *
	 * @param WP_Error $errors Errors collected so far.
	 * @param WC_Cart  $cart   Cart.
	 */
	public static function store_api_cart_errors( $errors, $cart ) {
		if ( ! $errors instanceof WP_Error || ! $cart instanceof WC_Cart ) {
			return;
		}
		foreach ( CRFW_Rules::evaluate_cart( $cart ) as $violation ) {
			$errors->add( 'crfw_' . $violation['type'], $violation['message'] );
		}
	}

	/**
	 * A one-line note under the price on the product page.
	 */
	public static function product_notice() {
		global $product;
		$text = self::minimum_text( $product );
		if ( '' !== $text ) {
			echo '<p class="crfw-product-notice">' . esc_html( $text ) . '</p>';
		}
	}

	/**
	 * `[crfw_minimum]` — the same note, for templates built with a page builder.
	 *
	 * Attributes: `id` (product id; defaults to the current product),
	 * `class` (extra CSS class), `before` / `after` (plain text around the note).
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode_minimum( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'     => 0,
				'class'  => '',
				'before' => '',
				'after'  => '',
			),
			$atts,
			'crfw_minimum'
		);
		if ( $atts['id'] ) {
			$product = wc_get_product( absint( $atts['id'] ) );
		} else {
			$product = isset( $GLOBALS['product'] ) && $GLOBALS['product'] instanceof WC_Product ? $GLOBALS['product'] : wc_get_product( get_the_ID() );
		}
		$text = self::minimum_text( $product );
		if ( '' === $text ) {
			return '';
		}
		$class = trim( 'crfw-product-notice ' . $atts['class'] );
		return '<p class="' . esc_attr( $class ) . '">' . esc_html( $atts['before'] . $text . $atts['after'] ) . '</p>';
	}

	/**
	 * The "Minimum order: …" text for a product, or '' when it has no minimum.
	 *
	 * @param WC_Product|mixed $product Product.
	 * @return string
	 */
	public static function minimum_text( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return '';
		}
		$rules = CRFW_Rules::get_product_rules( $product );
		$parts = array();
		if ( $rules['min_qty'] > 1 ) {
			/* translators: %s: quantity. */
			$parts[] = sprintf( _n( '%s item', '%s items', $rules['min_qty'], 'cart-rules-for-woocommerce' ), number_format_i18n( $rules['min_qty'] ) );
		}
		if ( $rules['min_amount'] > 0 ) {
			$parts[] = crfw_format_price( $rules['min_amount'] );
		}
		if ( empty( $parts ) ) {
			return '';
		}
		return crfw_message( 'product_notice', array( 'min' => implode( ' / ', $parts ) ) );
	}
}
