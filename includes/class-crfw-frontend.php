<?php
/**
 * Asking the customer before a minimum refuses them.
 *
 * The server always has the last word (CRFW_Cart), but a refusal after the
 * click is a poor way to learn about a rule. This class adds the polite half:
 * when a customer is about to add less than a product's minimum, a small
 * dialog offers to make up the difference, and only then does the request go
 * out.
 *
 * It deliberately knows nothing about the markup of any one theme. The script
 * listens for clicks in the capture phase, works out which product the clicked
 * control is for and how many the customer meant to add, and — if the rules
 * are not met — stops the event before any other handler sees it. That is what
 * makes it work with WooCommerce's own buttons, with a page builder's
 * "add to cart" link, and with a shop's hand-written JavaScript alike.
 *
 * The rules themselves never leave PHP: the script asks this REST route what a
 * product needs, so the answer is the same one CRFW_Rules gives the cart.
 *
 * @package CartRulesForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Front-end assets and the REST route behind the "add the rest?" dialog.
 */
class CRFW_Frontend {

	/** REST namespace. */
	const REST_NS = 'crfw/v1';

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );

		if ( 'yes' === crfw_get_option( 'ask_before_add' ) ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		}
	}

	/**
	 * The route the dialog reads its figures from.
	 *
	 * Public, like the Store API's cart route: it answers with a product's
	 * published minimums and what the caller's own cart holds — nothing that
	 * is not already on the shop page or in the caller's session.
	 */
	public static function register_routes() {
		register_rest_route(
			self::REST_NS,
			'/minimums',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_minimums' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'ids' => array(
						'required'          => true,
						'type'              => 'string',
						'description'       => 'Comma-separated product or variation ids.',
						'validate_callback' => function ( $value ) {
							return (bool) preg_match( '/^[0-9]+(,[0-9]+)*$/', (string) $value );
						},
					),
				),
			)
		);
	}

	/**
	 * What each product needs, and what the caller's cart already has of it.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function rest_minimums( $request ) {
		$ids = array_slice( array_unique( array_map( 'absint', explode( ',', (string) $request['ids'] ) ) ), 0, 100 );
		$out = array();

		// REST does not load the cart on its own; the figures are useless without it.
		if ( ! WC()->cart && function_exists( 'wc_load_cart' ) ) {
			wc_load_cart();
		}
		$groups = WC()->cart ? CRFW_Rules::group_cart_items( WC()->cart->get_cart() ) : array();

		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}
			$rules  = CRFW_Rules::get_product_rules( $product );
			$parent = $rules['product'];
			if ( ! $parent || ( $rules['min_qty'] <= 0 && $rules['min_amount'] <= 0 ) ) {
				$out[ (string) $id ] = array(
					'min_qty'    => 0,
					'min_amount' => 0.0,
				);
				continue;
			}

			$group = isset( $groups[ $parent->get_id() ] ) ? $groups[ $parent->get_id() ] : array(
				'qty'    => 0,
				'amount' => 0.0,
			);

			$out[ (string) $id ] = array(
				'name'            => wp_strip_all_tags( $parent->get_name() ),
				'min_qty'         => (int) $rules['min_qty'],
				'min_amount'      => (float) $rules['min_amount'],
				'min_amount_html' => $rules['min_amount'] > 0 ? crfw_format_price( $rules['min_amount'] ) : '',
				'price'           => (float) wc_get_price_to_display( $product, array( 'price' => $product->get_price() ) ),
				'in_cart_qty'     => (int) $group['qty'],
				'in_cart_amount'  => (float) $group['amount'],
			);
		}

		$response = rest_ensure_response( array( 'products' => $out ) );

		// These figures describe one visitor's own cart, so nothing may keep a copy.
		// The headers cover browsers and ordinary proxies; the action is how
		// LiteSpeed Cache is told to leave a REST response alone, which it does not
		// infer from the headers (it is a no-op when that plugin is absent).
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'Expires', 'Wed, 11 Jan 1984 05:00:00 GMT' );
		$response->header( 'Vary', 'Cookie' );
		/**
		 * Tell LiteSpeed Cache not to keep this response.
		 *
		 * The hook belongs to that plugin — it is called here, not defined — and the
		 * call is harmless when the plugin is absent.
		 *
		 * @since 1.3.0
		 *
		 * @param string $reason Why the response must not be cached.
		 */
		do_action( 'litespeed_control_set_nocache', 'cart-rules: per-visitor cart figures' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache's own hook.

		return $response;
	}

	/**
	 * Load the script and its stylesheet on the shop's own pages.
	 */
	public static function enqueue() {
		if ( is_admin() || is_cart() || is_checkout() ) {
			return; // Those pages report violations in place; there is nothing to ask there.
		}

		wp_enqueue_style( 'crfw-add-to-cart', CRFW_URL . 'assets/css/crfw-add-to-cart.css', array(), CRFW_VERSION );
		wp_enqueue_script( 'crfw-add-to-cart', CRFW_URL . 'assets/js/crfw-add-to-cart.js', array(), CRFW_VERSION, true );

		wp_localize_script(
			'crfw-add-to-cart',
			'crfwAddToCart',
			array(
				'endpoint' => rest_url( self::REST_NS . '/minimums' ),
				'i18n'     => array(
					/* translators: 1: product name, 2: the minimum, 3: how many will be added. */
					'askQty'    => __( '“%1$s” is sold in a minimum of %2$s. Add %3$s?', 'cart-rules-for-woocommerce' ),
					/* translators: 1: product name, 2: the minimum amount, 3: how many will be added. */
					'askAmount' => __( '“%1$s” has a minimum purchase of %2$s. Add %3$s?', 'cart-rules-for-woocommerce' ),
					/* translators: %s: a quantity. */
					'items'     => __( '%s item(s)', 'cart-rules-for-woocommerce' ),
					'confirm'   => __( 'Yes, add them', 'cart-rules-for-woocommerce' ),
					'cancel'    => __( 'Cancel', 'cart-rules-for-woocommerce' ),
					'title'     => __( 'Minimum order', 'cart-rules-for-woocommerce' ),
				),
			)
		);
	}
}
