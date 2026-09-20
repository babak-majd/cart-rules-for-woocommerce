<?php
/**
 * Per-product shipping restrictions.
 *
 * At rate-calculation time the package's rates are reduced to the methods
 * every product in it allows (the intersection). An unrestricted product
 * accepts anything, so it never narrows the choice. When restricted products
 * have nothing in common no rate survives, and the "no shipping" message
 * explains why instead of leaving the shopper guessing.
 *
 * Works on the classic and the block checkout alike: both go through
 * `woocommerce_package_rates`.
 *
 * @package CartRulesForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Per-product shipping restrictions.
 */
class CRFW_Shipping {

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_filter( 'woocommerce_package_rates', array( __CLASS__, 'filter_package_rates' ), 20, 2 );
		add_filter( 'woocommerce_no_shipping_available_html', array( __CLASS__, 'no_shipping_html' ) );
		add_filter( 'woocommerce_cart_no_shipping_available_html', array( __CLASS__, 'no_shipping_html' ) );
	}

	/**
	 * Keep only the rates every product in the package allows.
	 *
	 * @param WC_Shipping_Rate[] $rates   Rates keyed by rate id.
	 * @param array              $package Shipping package.
	 * @return WC_Shipping_Rate[]
	 */
	public static function filter_package_rates( $rates, $package ) {
		if ( empty( $package['contents'] ) || ! is_array( $rates ) ) {
			return $rates;
		}
		$allowed = CRFW_Rules::allowed_shipping_for_items( $package['contents'] );
		if ( null === $allowed ) {
			return $rates; // Nothing in this package is restricted.
		}

		$kept = array();
		foreach ( $rates as $id => $rate ) {
			if ( $rate instanceof WC_Shipping_Rate && CRFW_Rules::rate_allowed( $rate, $allowed ) ) {
				$kept[ $id ] = $rate;
			}
		}

		/**
		 * Filter the rates left after the per-product restrictions are applied.
		 *
		 * @since 1.0.0
		 *
		 * @param WC_Shipping_Rate[] $kept    Surviving rates.
		 * @param WC_Shipping_Rate[] $rates   All rates before filtering.
		 * @param string[]           $allowed The allow-list that was applied.
		 * @param array              $package The package.
		 */
		return apply_filters( 'crfw_package_rates', $kept, $rates, $allowed, $package );
	}

	/**
	 * When the products conflict, say so in the "no shipping options" slot.
	 *
	 * @param string $html Default message.
	 * @return string
	 */
	public static function no_shipping_html( $html ) {
		if ( ! WC()->cart ) {
			return $html;
		}
		$allowed = CRFW_Rules::allowed_shipping_for_items( WC()->cart->get_cart() );
		if ( ! is_array( $allowed ) || ! empty( $allowed ) ) {
			return $html;
		}
		foreach ( CRFW_Rules::evaluate_cart( WC()->cart ) as $violation ) {
			if ( 'shipping_conflict' === $violation['type'] ) {
				return esc_html( $violation['message'] );
			}
		}
		return $html;
	}

	/**
	 * Every shipping method a merchant can pick for a product, grouped for a
	 * `<select>`: first "any instance of a method type", then each zone's
	 * configured instances.
	 *
	 * @return array<int,array{label:string,options:array<string,string>}>
	 */
	public static function get_method_choices() {
		$groups = array();

		$types = array();
		foreach ( WC()->shipping()->get_shipping_methods() as $method ) {
			/* translators: %s: shipping method type, e.g. "Flat rate". */
			$types[ $method->id ] = sprintf( __( 'Any “%s” method', 'cart-rules-for-woocommerce' ), $method->get_method_title() );
		}
		if ( $types ) {
			$groups[] = array(
				'label'   => __( 'By method type (every zone)', 'cart-rules-for-woocommerce' ),
				'options' => $types,
			);
		}

		$zones   = WC_Shipping_Zones::get_zones();
		$zones[] = array( 'zone_id' => 0 ); // "Locations not covered by your other zones".
		foreach ( $zones as $zone_data ) {
			$zone    = new WC_Shipping_Zone( $zone_data['zone_id'] );
			$options = array();
			foreach ( $zone->get_shipping_methods() as $instance ) {
				$label = $instance->get_title();
				if ( ! $instance->is_enabled() ) {
					/* translators: %s: shipping method title. */
					$label = sprintf( __( '%s (disabled)', 'cart-rules-for-woocommerce' ), $label );
				}
				$options[ $instance->id . ':' . $instance->get_instance_id() ] = $label;
			}
			if ( $options ) {
				$groups[] = array(
					'label'   => $zone->get_zone_name(),
					'options' => $options,
				);
			}
		}

		/**
		 * Filter the shipping method choices offered on the product edit screen.
		 *
		 * @since 1.0.0
		 *
		 * @param array $groups Groups of { label, options: id => label }.
		 */
		return apply_filters( 'crfw_shipping_method_choices', $groups );
	}

	/**
	 * Human labels for a stored allow-list (for the settings/product screens).
	 *
	 * @param string[] $ids Rate / method ids.
	 * @return string[]
	 */
	public static function labels_for( array $ids ) {
		$lookup = array();
		foreach ( self::get_method_choices() as $group ) {
			foreach ( $group['options'] as $id => $label ) {
				$lookup[ $id ] = $label;
			}
		}
		$labels = array();
		foreach ( $ids as $id ) {
			$labels[] = isset( $lookup[ $id ] ) ? $lookup[ $id ] : $id;
		}
		return $labels;
	}
}
