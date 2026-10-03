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

		/**
		 * What the script treats as an add-to-cart control, as a quantity field, and
		 * how long it leaves the agreed quantity in the page.
		 *
		 * Shops build their own buttons, and no selector list can know them all. A
		 * theme that this does not recognise can say so here instead of forking the
		 * script; the defaults cover WooCommerce's own markup and the common
		 * `data-product_id` pattern.
		 *
		 * @since 1.4.0
		 *
		 * @param array $config {
		 *     @type array $selectors     addToCart, productButton, productLink, cartForm,
		 *                                quantityField, quantityWidget, scope — CSS selectors.
		 *     @type int   $restoreDelay  Milliseconds before the page's own quantity values
		 *                                are put back after the click is replayed.
		 * }
		 */
		$config = apply_filters(
			'crfw_frontend_config',
			array(
				'selectors'    => array(
					'addToCart'      => '.add_to_cart_button, .ajax_add_to_cart, .single_add_to_cart_button, button[name="add-to-cart"], input[name="add-to-cart"], a[href*="add-to-cart="]',
					'productButton'  => 'button[data-product_id], button[data-product-id], input[data-product_id], input[data-product-id]',
					'productLink'    => 'a[data-product_id], a[data-product-id]',
					'cartForm'       => 'form.cart',
					'quantityField'  => 'input[name="quantity"], input.qty, .qty input, input[data-qty], input[class*="qty"], input[type="number"]',
					'quantityWidget' => '.quantity, .qty, [class*="qty-"], [class*="-qty"], [class*="quantity"]',
					'scope'          => 'form.cart, .product, .elementor-widget-container, li, article, div',
				),
				'restoreDelay' => 1500,
			)
		);

		wp_localize_script(
			'crfw-add-to-cart',
			'crfwAddToCart',
			array(
				'endpoint'     => rest_url( self::REST_NS . '/minimums' ),
				'selectors'    => isset( $config['selectors'] ) ? $config['selectors'] : array(),
				'restoreDelay' => isset( $config['restoreDelay'] ) ? (int) $config['restoreDelay'] : 1500,
				// The wording is the merchant's: these come from the settings screen,
				// falling back to the translated defaults (see crfw_default_message()).
				'i18n'         => array(
					'askQty'    => crfw_message_template( 'ask_qty' ),
					'askAmount' => crfw_message_template( 'ask_amount' ),
					'items'     => crfw_message_template( 'ask_items' ),
					'title'     => crfw_message_template( 'ask_title' ),
					'confirm'   => crfw_message_template( 'ask_confirm' ),
					'cancel'    => crfw_message_template( 'ask_cancel' ),
				),
			)
		);
	}
}
