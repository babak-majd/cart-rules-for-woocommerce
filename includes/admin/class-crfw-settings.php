<?php
/**
 * WooCommerce → Settings → Cart Rules.
 *
 * A native settings tab built on WC_Settings_Page: store-wide minimum order,
 * defaults every product inherits, and the customer-facing messages.
 *
 * @package CartRulesForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * The "Cart Rules" settings tab.
 */
class CRFW_Settings extends WC_Settings_Page {

	/**
	 * Register the tab and the custom credit-line field type.
	 */
	public function __construct() {
		$this->id    = CRFW_Plugin::SETTINGS_TAB;
		$this->label = __( 'Cart Rules', 'cart-rules-for-woocommerce' );
		parent::__construct();

		add_action( 'woocommerce_admin_field_crfw_credit', array( $this, 'render_credit' ) );
	}

	/**
	 * The fields, in WooCommerce's settings-array format.
	 *
	 * @return array
	 */
	protected function get_settings_for_default_section() {
		$symbol = get_woocommerce_currency_symbol();

		$settings = array(
			array(
				'title' => __( 'Whole cart', 'cart-rules-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Rules that apply to the order as a whole. Per-product minimums and allowed shipping methods are set on each product\'s edit screen (General and Shipping tabs).', 'cart-rules-for-woocommerce' ),
				'id'    => 'crfw_cart_section',
			),
			array(
				'title'       => sprintf(
					/* translators: %s: currency symbol. */
					__( 'Minimum order amount (%s)', 'cart-rules-for-woocommerce' ),
					$symbol
				),
				'desc'        => __( 'Customers cannot check out below this amount. Leave empty for no minimum.', 'cart-rules-for-woocommerce' ),
				'id'          => 'crfw_cart_min_amount',
				'type'        => 'text',
				'class'       => 'wc_input_price',
				'css'         => 'width: 120px;',
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => __( 'No minimum', 'cart-rules-for-woocommerce' ),
			),
			array(
				'title'    => __( 'Compare against', 'cart-rules-for-woocommerce' ),
				'desc'     => __( 'Which cart figure the minimum order amount is checked against. Shipping is never included; tax follows the “Display prices during cart and checkout” setting.', 'cart-rules-for-woocommerce' ),
				'id'       => 'crfw_cart_amount_basis',
				'type'     => 'select',
				'class'    => 'wc-enhanced-select',
				'css'      => 'min-width: 300px;',
				'desc_tip' => true,
				'default'  => 'subtotal',
				'options'  => array(
					'subtotal'       => __( 'Subtotal (before coupons)', 'cart-rules-for-woocommerce' ),
					'after_discount' => __( 'Total after coupons', 'cart-rules-for-woocommerce' ),
				),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'crfw_cart_section',
			),

			array(
				'title' => __( 'Per-product defaults', 'cart-rules-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'Applied to every product that has no value of its own. A product can override these, or opt out with “No minimums”.', 'cart-rules-for-woocommerce' ),
				'id'    => 'crfw_defaults_section',
			),
			array(
				'title'             => __( 'Default minimum quantity', 'cart-rules-for-woocommerce' ),
				'desc'              => __( 'The smallest quantity of any one product a customer may order.', 'cart-rules-for-woocommerce' ),
				'id'                => 'crfw_default_min_qty',
				'type'              => 'number',
				'css'               => 'width: 120px;',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
				'desc_tip'          => true,
				'default'           => '',
				'placeholder'       => __( 'No minimum', 'cart-rules-for-woocommerce' ),
			),
			array(
				'title'       => sprintf(
					/* translators: %s: currency symbol. */
					__( 'Default minimum spend (%s)', 'cart-rules-for-woocommerce' ),
					$symbol
				),
				'desc'        => __( 'The smallest amount a customer must spend on any one product.', 'cart-rules-for-woocommerce' ),
				'id'          => 'crfw_default_min_amount',
				'type'        => 'text',
				'class'       => 'wc_input_price',
				'css'         => 'width: 120px;',
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => __( 'No minimum', 'cart-rules-for-woocommerce' ),
			),
			array(
				'title'   => __( 'Quantity fields', 'cart-rules-for-woocommerce' ),
				'desc'    => __( 'Start quantity fields and “Add to cart” buttons at the product\'s minimum quantity', 'cart-rules-for-woocommerce' ),
				'id'      => 'crfw_enforce_qty_input',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'title'   => __( 'Product page', 'cart-rules-for-woocommerce' ),
				'desc'    => __( 'Show the product\'s minimum under its price', 'cart-rules-for-woocommerce' ),
				'id'      => 'crfw_show_product_notice',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'crfw_defaults_section',
			),

			array(
				'title' => __( 'Messages', 'cart-rules-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => __( 'What customers read when a rule is not met. Leave a field empty to use the default shown as its placeholder. Placeholders: {product}, {products}, {min}, {current}.', 'cart-rules-for-woocommerce' ),
				'id'    => 'crfw_messages_section',
			),
			array(
				'title'       => __( 'Minimum quantity not met', 'cart-rules-for-woocommerce' ),
				'id'          => 'crfw_msg_min_qty',
				'type'        => 'textarea',
				'css'         => 'width: 100%; max-width: 640px; min-height: 60px;',
				'default'     => '',
				'placeholder' => crfw_default_message( 'min_qty' ),
			),
			array(
				'title'       => __( 'Minimum spend not met', 'cart-rules-for-woocommerce' ),
				'id'          => 'crfw_msg_min_amount',
				'type'        => 'textarea',
				'css'         => 'width: 100%; max-width: 640px; min-height: 60px;',
				'default'     => '',
				'placeholder' => crfw_default_message( 'min_amount' ),
			),
			array(
				'title'       => __( 'Minimum order amount not met', 'cart-rules-for-woocommerce' ),
				'id'          => 'crfw_msg_cart_min',
				'type'        => 'textarea',
				'css'         => 'width: 100%; max-width: 640px; min-height: 60px;',
				'default'     => '',
				'placeholder' => crfw_default_message( 'cart_min' ),
			),
			array(
				'title'       => __( 'No shipping method shared by all items', 'cart-rules-for-woocommerce' ),
				'id'          => 'crfw_msg_shipping_conflict',
				'type'        => 'textarea',
				'css'         => 'width: 100%; max-width: 640px; min-height: 60px;',
				'default'     => '',
				'placeholder' => crfw_default_message( 'shipping_conflict' ),
			),
			array(
				'title'       => __( 'Product page note', 'cart-rules-for-woocommerce' ),
				'id'          => 'crfw_msg_product_notice',
				'type'        => 'text',
				'css'         => 'width: 100%; max-width: 640px;',
				'default'     => '',
				'placeholder' => crfw_default_message( 'product_notice' ),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'crfw_messages_section',
			),

			array(
				'title' => __( 'Uninstall', 'cart-rules-for-woocommerce' ),
				'type'  => 'title',
				'id'    => 'crfw_uninstall_section',
			),
			array(
				'title'   => __( 'Remove data', 'cart-rules-for-woocommerce' ),
				'desc'    => __( 'Delete all settings and per-product rules when the plugin is deleted', 'cart-rules-for-woocommerce' ),
				'id'      => 'crfw_delete_data_on_uninstall',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'crfw_uninstall_section',
			),
			array(
				'type' => 'crfw_credit',
			),
		);

		/**
		 * Filter the plugin's settings fields.
		 *
		 * @since 1.0.0
		 *
		 * @param array $settings WooCommerce settings-array entries.
		 */
		return apply_filters( 'crfw_settings', $settings );
	}

	/**
	 * Normalise the numeric fields before WooCommerce stores them.
	 */
	public function save() {
		parent::save();

		$qty = crfw_to_qty( get_option( 'crfw_default_min_qty', '' ) );
		update_option( 'crfw_default_min_qty', $qty > 0 ? (string) $qty : '' );

		foreach ( array( 'crfw_cart_min_amount', 'crfw_default_min_amount' ) as $key ) {
			$amount = crfw_to_amount( get_option( $key, '' ) );
			update_option( $key, $amount > 0 ? wc_format_decimal( $amount ) : '' );
		}
	}

	/**
	 * The credit line, as a custom settings-field type so it sits inside the form.
	 */
	public function render_credit() {
		echo '<tr valign="top"><td colspan="2" style="padding-inline-start:0">' . wp_kses_post( CRFW_Plugin::credit_html() ) . '</td></tr>';
	}
}
