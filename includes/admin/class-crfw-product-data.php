<?php
/**
 * The fields on the product edit screen.
 *
 * Minimum quantity / minimum spend sit in the *General* tab under the price,
 * where the official Min/Max Quantities extension puts them, so merchants
 * find them where they expect. The allowed shipping methods sit in the
 * *Shipping* tab next to the shipping class. Native WooCommerce field helpers
 * throughout: nothing to style, nothing to enqueue.
 *
 * @package CartRulesForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Product edit screen fields.
 */
class CRFW_Product_Data {

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'render_minimum_fields' ) );
		add_action( 'woocommerce_product_options_shipping_product_data', array( __CLASS__, 'render_shipping_fields' ) );
		add_action( 'woocommerce_admin_process_product_object', array( __CLASS__, 'save' ) );
	}

	/**
	 * Minimum quantity, minimum spend, exempt flag — General tab.
	 */
	public static function render_minimum_fields() {
		global $product_object;

		$default_qty    = crfw_to_qty( crfw_get_option( 'default_min_qty' ) );
		$default_amount = crfw_to_amount( crfw_get_option( 'default_min_amount' ) );

		echo '<div class="options_group crfw-minimums">';

		woocommerce_wp_text_input(
			array(
				'id'                => CRFW_Rules::META_MIN_QTY,
				'label'             => __( 'Minimum quantity', 'cart-rules-for-woocommerce' ),
				'placeholder'       => $default_qty > 0
					/* translators: %s: the store-wide default minimum quantity. */
					? sprintf( __( 'Store default: %s', 'cart-rules-for-woocommerce' ), number_format_i18n( $default_qty ) )
					: __( 'No minimum', 'cart-rules-for-woocommerce' ),
				'desc_tip'          => true,
				'description'       => __( 'The smallest quantity of this product a customer may order. For a variable product the variations count together. Leave empty to use the store default.', 'cart-rules-for-woocommerce' ),
				'type'              => 'number',
				'custom_attributes' => array(
					'min'  => '0',
					'step' => '1',
				),
				'value'             => self::meta_value( $product_object, CRFW_Rules::META_MIN_QTY ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => CRFW_Rules::META_MIN_AMOUNT,
				/* translators: %s: currency symbol. */
				'label'       => sprintf( __( 'Minimum spend (%s)', 'cart-rules-for-woocommerce' ), get_woocommerce_currency_symbol() ),
				'placeholder' => $default_amount > 0
					/* translators: %s: the store-wide default minimum spend. */
					? sprintf( __( 'Store default: %s', 'cart-rules-for-woocommerce' ), wc_format_localized_price( $default_amount ) )
					: __( 'No minimum', 'cart-rules-for-woocommerce' ),
				'desc_tip'    => true,
				'description' => __( 'The smallest amount a customer must spend on this product (quantity × price). Leave empty to use the store default.', 'cart-rules-for-woocommerce' ),
				'data_type'   => 'price',
				'value'       => self::meta_value( $product_object, CRFW_Rules::META_MIN_AMOUNT ),
			)
		);

		woocommerce_wp_checkbox(
			array(
				'id'          => CRFW_Rules::META_EXEMPT,
				'label'       => __( 'No minimums', 'cart-rules-for-woocommerce' ),
				'description' => __( 'Exempt this product from every minimum, including the store defaults.', 'cart-rules-for-woocommerce' ),
				'value'       => self::meta_value( $product_object, CRFW_Rules::META_EXEMPT ),
			)
		);

		echo '</div>';
	}

	/**
	 * Allowed shipping methods — Shipping tab.
	 */
	public static function render_shipping_fields() {
		global $product_object;

		$selected = self::meta_value( $product_object, CRFW_Rules::META_SHIPPING );
		$selected = is_array( $selected ) ? array_map( 'strval', $selected ) : array();
		$groups   = CRFW_Shipping::get_method_choices( $selected );
		$field_id = CRFW_Rules::META_SHIPPING;
		?>
		<div class="options_group crfw-shipping">
			<p class="form-field <?php echo esc_attr( $field_id ); ?>_field">
				<label for="<?php echo esc_attr( $field_id ); ?>"><?php esc_html_e( 'Allowed shipping methods', 'cart-rules-for-woocommerce' ); ?></label>
				<select id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_id ); ?>[]" class="wc-enhanced-select" multiple="multiple" style="width: 50%;" data-placeholder="<?php esc_attr_e( 'Any shipping method', 'cart-rules-for-woocommerce' ); ?>">
					<?php foreach ( $groups as $group ) : ?>
						<optgroup label="<?php echo esc_attr( $group['label'] ); ?>">
							<?php foreach ( $group['options'] as $id => $label ) : ?>
								<option value="<?php echo esc_attr( $id ); ?>" <?php selected( in_array( (string) $id, $selected, true ) ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</optgroup>
					<?php endforeach; ?>
				</select>
				<?php echo wc_help_tip( __( 'Only these methods are offered when this product is in the cart. With several restricted products in one cart, only the methods they all allow are offered. Leave empty to allow every method. Methods you have switched off in WooCommerce are not listed.', 'cart-rules-for-woocommerce' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wc_help_tip() escapes. ?>
			</p>
			<?php if ( empty( $groups ) ) : ?>
				<p class="form-field"><span class="description"><?php esc_html_e( 'No shipping methods are configured yet. Add some under WooCommerce → Settings → Shipping.', 'cart-rules-for-woocommerce' ); ?></span></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Persist the fields. WooCommerce has already verified the meta-box nonce
	 * and the user's capability before this action fires.
	 *
	 * @param WC_Product $product The product being saved.
	 */
	public static function save( $product ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by WooCommerce (woocommerce_meta_nonce) before this hook.
		$qty    = isset( $_POST[ CRFW_Rules::META_MIN_QTY ] ) ? crfw_to_qty( sanitize_text_field( wp_unslash( $_POST[ CRFW_Rules::META_MIN_QTY ] ) ) ) : 0;
		$amount = isset( $_POST[ CRFW_Rules::META_MIN_AMOUNT ] ) ? crfw_to_amount( sanitize_text_field( wp_unslash( $_POST[ CRFW_Rules::META_MIN_AMOUNT ] ) ) ) : 0.0;
		$exempt = isset( $_POST[ CRFW_Rules::META_EXEMPT ] ) ? 'yes' : 'no';

		$shipping = array();
		if ( isset( $_POST[ CRFW_Rules::META_SHIPPING ] ) && is_array( $_POST[ CRFW_Rules::META_SHIPPING ] ) ) {
			$shipping = array_map( 'sanitize_text_field', wp_unslash( $_POST[ CRFW_Rules::META_SHIPPING ] ) );
			$shipping = array_values( array_unique( array_filter( $shipping, array( __CLASS__, 'is_valid_method_id' ) ) ) );
		}
		// phpcs:enable

		self::set_or_delete( $product, CRFW_Rules::META_MIN_QTY, $qty > 0 ? (string) $qty : '' );
		self::set_or_delete( $product, CRFW_Rules::META_MIN_AMOUNT, $amount > 0 ? wc_format_decimal( $amount ) : '' );
		self::set_or_delete( $product, CRFW_Rules::META_EXEMPT, 'yes' === $exempt ? 'yes' : '' );

		$previous = $product->get_meta( CRFW_Rules::META_SHIPPING, true );
		$previous = is_array( $previous ) ? array_values( $previous ) : array();
		self::set_or_delete( $product, CRFW_Rules::META_SHIPPING, $shipping ? $shipping : '' );

		// Shipping rates are cached per session; a changed allow-list must invalidate them.
		if ( $previous !== $shipping ) {
			WC_Cache_Helper::get_transient_version( 'shipping', true );
		}
	}

	/**
	 * Save a value, or remove the meta entirely when it is empty, so an
	 * untouched product carries no rows at all.
	 *
	 * @param WC_Product   $product Product.
	 * @param string       $key     Meta key.
	 * @param string|array $value   Value; '' deletes.
	 */
	private static function set_or_delete( $product, $key, $value ) {
		if ( '' === $value ) {
			$product->delete_meta_data( $key );
		} else {
			$product->update_meta_data( $key, $value );
		}
	}

	/**
	 * A method id is `slug` or `slug:instance`, nothing else.
	 *
	 * @param string $id Candidate.
	 * @return bool
	 */
	public static function is_valid_method_id( $id ) {
		return (bool) preg_match( '/^[a-z0-9_\-]+(:\d+)?$/i', $id );
	}

	/**
	 * Read a meta value off the product being edited.
	 *
	 * @param WC_Product|null $product Product object on the edit screen.
	 * @param string          $key     Meta key.
	 * @return mixed
	 */
	private static function meta_value( $product, $key ) {
		return $product instanceof WC_Product ? $product->get_meta( $key, true ) : '';
	}
}
