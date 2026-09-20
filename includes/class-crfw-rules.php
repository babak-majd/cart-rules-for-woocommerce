<?php
/**
 * The rules engine.
 *
 * Pure logic, no hooks: reads a product's rules, groups the cart by product,
 * evaluates every minimum, and works out which shipping methods all items agree
 * on. Everything customer-facing hangs off this class (see CRFW_Cart and
 * CRFW_Shipping), which keeps the behaviour identical on the classic shortcode
 * cart/checkout and on the block-based ones.
 *
 * @package CartRulesForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rules engine: per-product rules, cart evaluation, shipping intersection.
 */
class CRFW_Rules {

	const META_MIN_QTY    = '_crfw_min_qty';
	const META_MIN_AMOUNT = '_crfw_min_amount';
	const META_EXEMPT     = '_crfw_exempt';
	const META_SHIPPING   = '_crfw_shipping_methods';

	/**
	 * Resolve a product (or id, or variation) to the parent product the rules live on.
	 *
	 * Rules are set on the parent: a minimum of 10 T-shirts means 10 across all
	 * sizes and colours. Variations inherit.
	 *
	 * @param int|WC_Product $product Product, variation, or id.
	 * @return WC_Product|null
	 */
	public static function parent_product( $product ) {
		if ( ! $product instanceof WC_Product ) {
			$product = wc_get_product( $product );
		}
		if ( ! $product ) {
			return null;
		}
		$parent_id = $product->get_parent_id();
		if ( $parent_id ) {
			$parent = wc_get_product( $parent_id );
			if ( $parent ) {
				return $parent;
			}
		}
		return $product;
	}

	/**
	 * The effective rules for a product.
	 *
	 * A product value overrides the store-wide default; an empty product value
	 * inherits it; the "exempt" flag switches the minimums off for this product
	 * entirely. Shipping restrictions are per product only.
	 *
	 * @param int|WC_Product $product Product, variation, or id.
	 * @return array{min_qty:int,min_amount:float,shipping:string[],exempt:bool,product:WC_Product|null}
	 */
	public static function get_product_rules( $product ) {
		$product = self::parent_product( $product );
		$rules   = array(
			'min_qty'    => 0,
			'min_amount' => 0.0,
			'shipping'   => array(),
			'exempt'     => false,
			'product'    => $product,
		);
		if ( ! $product ) {
			return $rules;
		}

		$rules['exempt'] = 'yes' === $product->get_meta( self::META_EXEMPT, true );

		if ( ! $rules['exempt'] ) {
			$qty    = crfw_to_qty( $product->get_meta( self::META_MIN_QTY, true ) );
			$amount = crfw_to_amount( $product->get_meta( self::META_MIN_AMOUNT, true ) );

			$rules['min_qty']    = $qty > 0 ? $qty : crfw_to_qty( crfw_get_option( 'default_min_qty' ) );
			$rules['min_amount'] = $amount > 0 ? $amount : crfw_to_amount( crfw_get_option( 'default_min_amount' ) );

			// A minimum quantity above 1 cannot apply to a product sold one per order.
			if ( $product->is_sold_individually() ) {
				$rules['min_qty'] = 0;
			}
		}

		$shipping = $product->get_meta( self::META_SHIPPING, true );
		if ( is_array( $shipping ) ) {
			$rules['shipping'] = array_values( array_filter( array_map( 'strval', $shipping ) ) );
		}

		/**
		 * Filter the effective rules for a product.
		 *
		 * @since 1.0.0
		 *
		 * @param array      $rules   min_qty, min_amount, shipping (rate/method ids), exempt.
		 * @param WC_Product $product The parent product.
		 */
		return apply_filters( 'crfw_product_rules', $rules, $product );
	}

	/**
	 * Group cart items by the product the rules live on, totalling quantity and spend.
	 *
	 * @param array $cart_contents WC()->cart->get_cart() (or a shipping package's `contents`).
	 * @return array<int,array{product:WC_Product,qty:int,amount:float,keys:string[]}>
	 */
	public static function group_cart_items( array $cart_contents ) {
		$groups   = array();
		$incl_tax = self::amounts_include_tax();

		foreach ( $cart_contents as $key => $item ) {
			if ( empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) {
				continue;
			}
			$parent = self::parent_product( $item['data'] );
			if ( ! $parent ) {
				continue;
			}

			/**
			 * Filter the key cart lines are grouped under for the per-product minimums.
			 * Default: the parent product id, so variations count together.
			 *
			 * @since 1.0.0
			 *
			 * @param int   $group_id Group key.
			 * @param array $item     Cart item.
			 */
			$group_id = (int) apply_filters( 'crfw_cart_item_group', $parent->get_id(), $item );

			if ( ! isset( $groups[ $group_id ] ) ) {
				$groups[ $group_id ] = array(
					'product' => $parent,
					'qty'     => 0,
					'amount'  => 0.0,
					'keys'    => array(),
				);
			}
			$line = isset( $item['line_subtotal'] ) ? (float) $item['line_subtotal'] : 0.0;
			if ( $incl_tax && isset( $item['line_subtotal_tax'] ) ) {
				$line += (float) $item['line_subtotal_tax'];
			}
			$groups[ $group_id ]['qty']    += isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;
			$groups[ $group_id ]['amount'] += $line;
			$groups[ $group_id ]['keys'][]  = (string) $key;
		}

		return $groups;
	}

	/**
	 * Whether amounts are compared including tax — follows the store's cart display setting.
	 *
	 * @return bool
	 */
	public static function amounts_include_tax() {
		$incl = wc_tax_enabled() && 'incl' === get_option( 'woocommerce_tax_display_cart' );
		/**
		 * Filter whether minimum amounts are compared against tax-inclusive figures.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $incl Default: true when the cart displays prices including tax.
		 */
		return (bool) apply_filters( 'crfw_amounts_include_tax', $incl );
	}

	/**
	 * The cart figure the store-wide minimum is compared against.
	 *
	 * @param WC_Cart $cart Cart.
	 * @return float
	 */
	public static function cart_amount( WC_Cart $cart ) {
		$basis    = crfw_get_option( 'cart_amount_basis' );
		$incl_tax = self::amounts_include_tax();

		if ( 'after_discount' === $basis ) {
			$amount = (float) $cart->get_cart_contents_total();
			if ( $incl_tax ) {
				$amount += (float) $cart->get_cart_contents_tax();
			}
		} else {
			$amount = (float) $cart->get_subtotal();
			if ( $incl_tax ) {
				$amount += (float) $cart->get_subtotal_tax();
			}
		}

		/**
		 * Filter the cart amount the store-wide minimum is compared against.
		 *
		 * @since 1.0.0
		 *
		 * @param float   $amount Amount.
		 * @param WC_Cart $cart   Cart.
		 * @param string  $basis  'subtotal' or 'after_discount'.
		 */
		return (float) apply_filters( 'crfw_cart_amount', $amount, $cart, $basis );
	}

	/**
	 * Evaluate every rule against a cart and return the violations, in display order.
	 *
	 * Each violation is `type` (min_qty | min_amount | cart_min | shipping_conflict),
	 * a ready-to-show `message`, and the figures behind it.
	 *
	 * @param WC_Cart $cart Cart.
	 * @return array<int,array<string,mixed>>
	 */
	public static function evaluate_cart( WC_Cart $cart ) {
		$violations = array();
		$contents   = $cart->get_cart();

		if ( empty( $contents ) ) {
			return $violations;
		}

		// 1) Per-product minimums — every product must clear its own bar.
		foreach ( self::group_cart_items( $contents ) as $group ) {
			$rules = self::get_product_rules( $group['product'] );
			$name  = $group['product']->get_name();

			if ( $rules['min_qty'] > 0 && $group['qty'] < $rules['min_qty'] ) {
				$violations[] = array(
					'type'    => 'min_qty',
					'product' => $group['product'],
					'min'     => $rules['min_qty'],
					'current' => $group['qty'],
					'message' => crfw_message(
						'min_qty',
						array(
							'product' => $name,
							'min'     => number_format_i18n( $rules['min_qty'] ),
							'current' => number_format_i18n( $group['qty'] ),
						)
					),
				);
			}

			if ( $rules['min_amount'] > 0 && $group['amount'] < $rules['min_amount'] ) {
				$violations[] = array(
					'type'    => 'min_amount',
					'product' => $group['product'],
					'min'     => $rules['min_amount'],
					'current' => $group['amount'],
					'message' => crfw_message(
						'min_amount',
						array(
							'product' => $name,
							'min'     => crfw_format_price( $rules['min_amount'] ),
							'current' => crfw_format_price( $group['amount'] ),
						)
					),
				);
			}
		}

		// 2) Store-wide minimum order amount.
		$cart_min = crfw_to_amount( crfw_get_option( 'cart_min_amount' ) );
		if ( $cart_min > 0 ) {
			$amount = self::cart_amount( $cart );
			if ( $amount < $cart_min ) {
				$violations[] = array(
					'type'    => 'cart_min',
					'min'     => $cart_min,
					'current' => $amount,
					'message' => crfw_message(
						'cart_min',
						array(
							'min'     => crfw_format_price( $cart_min ),
							'current' => crfw_format_price( $amount ),
						)
					),
				);
			}
		}

		// 3) Shipping: the products must agree on at least one method.
		if ( $cart->needs_shipping() ) {
			$allowed = self::allowed_shipping_for_items( $contents );
			if ( is_array( $allowed ) && empty( $allowed ) ) {
				$names = array();
				foreach ( self::group_cart_items( $contents ) as $group ) {
					$rules = self::get_product_rules( $group['product'] );
					if ( ! empty( $rules['shipping'] ) ) {
						$names[] = $group['product']->get_name();
					}
				}
				$violations[] = array(
					'type'     => 'shipping_conflict',
					'products' => $names,
					'message'  => crfw_message(
						'shipping_conflict',
						array( 'products' => wp_sprintf_l( '%l', array_map( 'wp_strip_all_tags', $names ) ) )
					),
				);
			}
		}

		/**
		 * Filter the list of rule violations for a cart.
		 *
		 * @since 1.0.0
		 *
		 * @param array   $violations Violations.
		 * @param WC_Cart $cart       Cart.
		 */
		return apply_filters( 'crfw_cart_violations', $violations, $cart );
	}

	/**
	 * The shipping methods every item in a set allows.
	 *
	 * `null` means no item is restricted (leave the rates alone). An empty array
	 * means the restricted items have nothing in common — a conflict. Entries are
	 * rate ids (`flat_rate:12`) or bare method ids (`flat_rate`, any instance).
	 *
	 * @param array $items Cart contents or a package's `contents`.
	 * @return string[]|null
	 */
	public static function allowed_shipping_for_items( array $items ) {
		$allowed = null;
		$seen    = array();

		foreach ( $items as $item ) {
			if ( empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) {
				continue;
			}
			$product = self::parent_product( $item['data'] );
			if ( ! $product || isset( $seen[ $product->get_id() ] ) ) {
				continue;
			}
			$seen[ $product->get_id() ] = true;

			$rules = self::get_product_rules( $product );
			if ( empty( $rules['shipping'] ) ) {
				continue; // Unrestricted: happy with whatever the others allow.
			}
			$allowed = null === $allowed ? $rules['shipping'] : self::intersect_shipping( $allowed, $rules['shipping'] );
		}

		/**
		 * Filter the set of shipping methods allowed for a set of cart items.
		 *
		 * @since 1.0.0
		 *
		 * @param string[]|null $allowed null = unrestricted, [] = conflict.
		 * @param array         $items   Cart items.
		 */
		return apply_filters( 'crfw_allowed_shipping', $allowed, $items );
	}

	/**
	 * Intersect two allow-lists, understanding that a bare method id (`flat_rate`)
	 * covers every instance (`flat_rate:12`) of it.
	 *
	 * @param string[] $a First list.
	 * @param string[] $b Second list.
	 * @return string[]
	 */
	public static function intersect_shipping( array $a, array $b ) {
		$result = array();
		foreach ( $a as $entry_a ) {
			foreach ( $b as $entry_b ) {
				if ( $entry_a === $entry_b ) {
					$result[] = $entry_a;
				} elseif ( self::method_of( $entry_a ) === $entry_b && false !== strpos( $entry_a, ':' ) ) {
					$result[] = $entry_a; // A specific instance narrowed by "any instance".
				} elseif ( self::method_of( $entry_b ) === $entry_a && false !== strpos( $entry_b, ':' ) ) {
					$result[] = $entry_b;
				}
			}
		}
		return array_values( array_unique( $result ) );
	}

	/**
	 * Whether a shipping rate is in an allow-list.
	 *
	 * @param WC_Shipping_Rate $rate    Rate.
	 * @param string[]         $allowed Allow-list (rate ids and/or method ids).
	 * @return bool
	 */
	public static function rate_allowed( WC_Shipping_Rate $rate, array $allowed ) {
		$rate_id   = $rate->get_id();
		$method_id = $rate->get_method_id();
		foreach ( $allowed as $entry ) {
			if ( $entry === $rate_id || $entry === $method_id ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The method part of a rate id: `flat_rate:12` → `flat_rate`.
	 *
	 * @param string $entry Rate or method id.
	 * @return string
	 */
	public static function method_of( $entry ) {
		$pos = strpos( $entry, ':' );
		return false === $pos ? $entry : substr( $entry, 0, $pos );
	}
}
