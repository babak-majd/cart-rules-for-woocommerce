<?php
/**
 * WooCommerce → Cart Rules — the guide screen.
 *
 * The plugin's home in wp-admin. It teaches nothing but *where* each feature
 * lives and what it does, in the order a merchant meets them: set a rule on a
 * product, set a rule for the whole cart, see what the customer gets. Every
 * location is a real link, so nobody has to hunt for a tab.
 *
 * Deliberately not a settings screen: it writes nothing. The settings live in
 * the native WooCommerce settings tab (CRFW_Settings) and on the product edit
 * screen (CRFW_Product_Data), which is where a WooCommerce user expects them.
 *
 * @package CartRulesForWooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * The read-only guide screen under the WooCommerce menu.
 */
class CRFW_Help {

	/** The admin page slug. */
	const PAGE = 'crfw-guide';

	/**
	 * Hook the submenu up.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
	}

	/**
	 * Add the page under the WooCommerce menu, next to Settings and Status.
	 */
	public static function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Cart Rules', 'cart-rules-for-woocommerce' ),
			__( 'Cart Rules', 'cart-rules-for-woocommerce' ),
			'manage_woocommerce', // phpcs:ignore WordPress.WP.Capabilities.Unknown -- registered by WooCommerce.
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * The URL of this screen.
	 *
	 * @return string
	 */
	public static function url() {
		return admin_url( 'admin.php?page=' . self::PAGE );
	}

	/**
	 * The URL of the plugin's settings tab.
	 *
	 * @return string
	 */
	public static function settings_url() {
		return admin_url( 'admin.php?page=wc-settings&tab=' . CRFW_Plugin::SETTINGS_TAB );
	}

	/**
	 * Render the guide.
	 */
	public static function render() {
		$products_url = admin_url( 'edit.php?post_type=product' );
		$shipping_url = admin_url( 'admin.php?page=wc-settings&tab=shipping' );
		$settings_url = self::settings_url();
		?>
		<div class="wrap crfw-guide">
			<h1><?php esc_html_e( 'Cart Rules', 'cart-rules-for-woocommerce' ); ?> <span class="crfw-ver">v<?php echo esc_html( CRFW_VERSION ); ?></span></h1>
			<p class="crfw-lede">
				<?php esc_html_e( 'This plugin adds three rules to your shop: a minimum per product, a minimum for the whole order, and which shipping methods each product may use. This page shows where every one of them is.', 'cart-rules-for-woocommerce' ); ?>
			</p>

			<?php self::print_styles(); ?>

			<div class="crfw-card">
				<h2><?php esc_html_e( 'In short', 'cart-rules-for-woocommerce' ); ?></h2>
				<ol class="crfw-steps">
					<li>
						<strong><?php esc_html_e( 'A rule for one product', 'cart-rules-for-woocommerce' ); ?></strong><br />
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: 1: link to the products list, opening tag, 2: closing tag. */
								__( '%1$sProducts%2$s → open a product → the <em>Product data</em> box → the <em>General</em> tab (minimums) and the <em>Shipping</em> tab (shipping methods).', 'cart-rules-for-woocommerce' ),
								'<a href="' . esc_url( $products_url ) . '">',
								'</a>'
							)
						);
						?>
					</li>
					<li>
						<strong><?php esc_html_e( 'A rule for the whole order', 'cart-rules-for-woocommerce' ); ?></strong><br />
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: 1: link to the settings tab, opening tag, 2: closing tag. */
								__( '%1$sWooCommerce → Settings → Cart Rules%2$s — the minimum order amount, the store-wide defaults and every message the customer reads.', 'cart-rules-for-woocommerce' ),
								'<a href="' . esc_url( $settings_url ) . '">',
								'</a>'
							)
						);
						?>
					</li>
					<li>
						<strong><?php esc_html_e( 'Nothing else to set up', 'cart-rules-for-woocommerce' ); ?></strong><br />
						<?php esc_html_e( 'The rules apply immediately, on the cart and the checkout, classic or block-based.', 'cart-rules-for-woocommerce' ); ?>
					</li>
				</ol>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Open the settings', 'cart-rules-for-woocommerce' ); ?></a>
					<a class="button" href="<?php echo esc_url( $products_url ); ?>"><?php esc_html_e( 'Open the products list', 'cart-rules-for-woocommerce' ); ?></a>
				</p>
			</div>

			<div class="crfw-card">
				<h2><?php esc_html_e( '1. Minimums for one product', 'cart-rules-for-woocommerce' ); ?></h2>
				<p class="crfw-where">
					<span class="dashicons dashicons-location"></span>
					<?php esc_html_e( 'Products → (open a product) → Product data → General tab, under the prices.', 'cart-rules-for-woocommerce' ); ?>
				</p>
				<table class="widefat striped crfw-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Field', 'cart-rules-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'What it does', 'cart-rules-for-woocommerce' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><strong><?php esc_html_e( 'Minimum quantity', 'cart-rules-for-woocommerce' ); ?></strong></td>
							<td>
								<?php esc_html_e( 'The customer must order at least this many of this product. Example: 5 means the cart is refused with 4, and accepted from 5 upwards.', 'cart-rules-for-woocommerce' ); ?>
								<br /><em><?php esc_html_e( 'On a variable product the variations are counted together: with a minimum of 5, three of one size plus two of another is fine.', 'cart-rules-for-woocommerce' ); ?></em>
							</td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Minimum spend', 'cart-rules-for-woocommerce' ); ?></strong></td>
							<td><?php esc_html_e( 'The customer must spend at least this much on this product — quantity × price, this product alone. Use it when the price varies but the order value is what matters.', 'cart-rules-for-woocommerce' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'No minimums', 'cart-rules-for-woocommerce' ); ?></strong></td>
							<td><?php esc_html_e( 'Exempts this product from everything above, including the store-wide defaults. Use it for a sample, a spare part or a gift card.', 'cart-rules-for-woocommerce' ); ?></td>
						</tr>
					</tbody>
				</table>
				<p class="crfw-tip">
					<?php esc_html_e( 'Leave a field empty and the product follows the store default (set under Settings → Cart Rules). Set a value and it wins over the default.', 'cart-rules-for-woocommerce' ); ?>
				</p>
			</div>

			<div class="crfw-card">
				<h2><?php esc_html_e( '2. Allowed shipping methods for one product', 'cart-rules-for-woocommerce' ); ?></h2>
				<p class="crfw-where">
					<span class="dashicons dashicons-location"></span>
					<?php esc_html_e( 'Products → (open a product) → Product data → Shipping tab, under the shipping class.', 'cart-rules-for-woocommerce' ); ?>
				</p>
				<p><?php esc_html_e( 'Pick the shipping methods this product may be sent with. Leave the field empty and every method is allowed — that is the default for every product.', 'cart-rules-for-woocommerce' ); ?></p>
				<p><?php esc_html_e( 'The list is read from your own shipping settings and is grouped in two ways:', 'cart-rules-for-woocommerce' ); ?></p>
				<ul class="crfw-bullets">
					<li>
						<strong><?php esc_html_e( 'By method type (every zone)', 'cart-rules-for-woocommerce' ); ?></strong> —
						<?php esc_html_e( 'for example “Any Flat rate method”: every flat rate you have, in every zone, now and in the future. Best when the same kind of delivery exists in several zones.', 'cart-rules-for-woocommerce' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( 'By zone', 'cart-rules-for-woocommerce' ); ?></strong> —
						<?php esc_html_e( 'one exact method you configured in one zone, under its own name. Best when only that one delivery service can carry the product.', 'cart-rules-for-woocommerce' ); ?>
					</li>
				</ul>
				<p>
					<?php
					echo wp_kses_post(
						sprintf(
							/* translators: 1: link to the shipping settings, opening tag, 2: closing tag. */
							__( 'The methods themselves are created in %1$sWooCommerce → Settings → Shipping%2$s. Anything you add there appears here automatically, including methods added by other plugins.', 'cart-rules-for-woocommerce' ),
							'<a href="' . esc_url( $shipping_url ) . '">',
							'</a>'
						)
					);
					?>
				</p>
			</div>

			<div class="crfw-card crfw-highlight">
				<h2><?php esc_html_e( '3. How several products in one cart are handled', 'cart-rules-for-woocommerce' ); ?></h2>
				<p><strong><?php esc_html_e( 'Minimums: every product must clear its own bar.', 'cart-rules-for-woocommerce' ); ?></strong>
					<?php esc_html_e( 'One product reaching its minimum does not help another. If any product in the cart is below its own minimum, checkout is blocked and the message names that product and the figure it is missing.', 'cart-rules-for-woocommerce' ); ?>
				</p>
				<p><strong><?php esc_html_e( 'Shipping: only the methods they all allow are offered.', 'cart-rules-for-woocommerce' ); ?></strong>
					<?php esc_html_e( 'A product with no restriction accepts anything, so it never narrows the list.', 'cart-rules-for-woocommerce' ); ?>
				</p>
				<div class="crfw-example">
					<h3><?php esc_html_e( 'Example', 'cart-rules-for-woocommerce' ); ?></h3>
					<ul>
						<li><?php esc_html_e( 'Product A allows: Post, Courier', 'cart-rules-for-woocommerce' ); ?></li>
						<li><?php esc_html_e( 'Product B allows: Courier, Freight', 'cart-rules-for-woocommerce' ); ?></li>
						<li><?php esc_html_e( 'Product C has no restriction', 'cart-rules-for-woocommerce' ); ?></li>
					</ul>
					<p><?php esc_html_e( 'A + B + C in one cart → only Courier is offered. If B allowed Freight alone, nothing would be shared: no shipping method is shown and the customer is told which products cannot travel together, so they can order them separately.', 'cart-rules-for-woocommerce' ); ?></p>
				</div>
			</div>

			<div class="crfw-card">
				<h2><?php esc_html_e( '4. Rules for the whole order', 'cart-rules-for-woocommerce' ); ?></h2>
				<p class="crfw-where">
					<span class="dashicons dashicons-location"></span>
					<?php
					echo wp_kses_post(
						sprintf(
							/* translators: 1: link to the settings tab, opening tag, 2: closing tag. */
							__( '%1$sWooCommerce → Settings → Cart Rules%2$s', 'cart-rules-for-woocommerce' ),
							'<a href="' . esc_url( $settings_url ) . '">',
							'</a>'
						)
					);
					?>
				</p>
				<table class="widefat striped crfw-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Section', 'cart-rules-for-woocommerce' ); ?></th>
							<th><?php esc_html_e( 'What you set there', 'cart-rules-for-woocommerce' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><strong><?php esc_html_e( 'Whole cart', 'cart-rules-for-woocommerce' ); ?></strong></td>
							<td><?php esc_html_e( 'The minimum order amount — below it nobody can check out — and whether it is compared against the subtotal or the total after coupons. Shipping costs are never counted.', 'cart-rules-for-woocommerce' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Per-product defaults', 'cart-rules-for-woocommerce' ); ?></strong></td>
							<td><?php esc_html_e( 'A minimum quantity and a minimum spend for every product that has no value of its own — so you set a rule once instead of on a thousand products. Also: whether quantity fields start at the minimum, and whether the product page shows a note.', 'cart-rules-for-woocommerce' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Messages', 'cart-rules-for-woocommerce' ); ?></strong></td>
							<td><?php esc_html_e( 'The exact wording the customer reads for each rule. Leave a field empty to keep the default text shown in grey inside it.', 'cart-rules-for-woocommerce' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Uninstall', 'cart-rules-for-woocommerce' ); ?></strong></td>
							<td><?php esc_html_e( 'Whether deleting the plugin also deletes your rules. Off by default, so nothing is lost by accident.', 'cart-rules-for-woocommerce' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="crfw-card">
				<h2><?php esc_html_e( '5. Wording the messages', 'cart-rules-for-woocommerce' ); ?></h2>
				<p class="crfw-where">
					<span class="dashicons dashicons-location"></span>
					<?php esc_html_e( 'Settings → Cart Rules → Messages', 'cart-rules-for-woocommerce' ); ?>
				</p>
				<p><?php esc_html_e( 'Write your own text and drop these placeholders in it — they are replaced with the real values:', 'cart-rules-for-woocommerce' ); ?></p>
				<table class="widefat striped crfw-table">
					<tbody>
						<tr>
							<td><code>{product}</code></td>
							<td><?php esc_html_e( 'The name of the product that is below its minimum.', 'cart-rules-for-woocommerce' ); ?></td>
						</tr>
						<tr>
							<td><code>{min}</code></td>
							<td><?php esc_html_e( 'The required minimum (a quantity or an amount, whichever the rule is about).', 'cart-rules-for-woocommerce' ); ?></td>
						</tr>
						<tr>
							<td><code>{current}</code></td>
							<td><?php esc_html_e( 'What the customer currently has.', 'cart-rules-for-woocommerce' ); ?></td>
						</tr>
						<tr>
							<td><code>{products}</code></td>
							<td><?php esc_html_e( 'The list of products that cannot be shipped together (shipping message only).', 'cart-rules-for-woocommerce' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="crfw-card">
				<h2><?php esc_html_e( '6. What the customer sees', 'cart-rules-for-woocommerce' ); ?></h2>
				<ul class="crfw-bullets">
					<li><strong><?php esc_html_e( 'On the product page:', 'cart-rules-for-woocommerce' ); ?></strong> <?php esc_html_e( 'the quantity field starts at the minimum, and a short note under the price says what the minimum is.', 'cart-rules-for-woocommerce' ); ?></li>
					<li><strong><?php esc_html_e( 'When adding to the cart:', 'cart-rules-for-woocommerce' ); ?></strong> <?php esc_html_e( 'a quantity below the minimum is refused right away, with the reason.', 'cart-rules-for-woocommerce' ); ?></li>
					<li><strong><?php esc_html_e( 'In the cart and at checkout:', 'cart-rules-for-woocommerce' ); ?></strong> <?php esc_html_e( 'any unmet rule is shown as an error and the order cannot be placed until it is fixed.', 'cart-rules-for-woocommerce' ); ?></li>
					<li><strong><?php esc_html_e( 'In the shipping choices:', 'cart-rules-for-woocommerce' ); ?></strong> <?php esc_html_e( 'only the methods allowed for everything in the cart are listed.', 'cart-rules-for-woocommerce' ); ?></li>
				</ul>
				<p class="crfw-tip">
					<?php
					echo wp_kses_post(
						sprintf(
							/* translators: %s: the [crfw_minimum] shortcode, in a code tag. */
							__( 'Building your product page with Elementor, Divi or another builder? Those templates skip WooCommerce\'s own hooks, so the note under the price may not appear. Place it yourself with the %s shortcode — it accepts id, class, before and after.', 'cart-rules-for-woocommerce' ),
							'<code>[crfw_minimum]</code>'
						)
					);
					?>
				</p>
			</div>

			<div class="crfw-card">
				<h2><?php esc_html_e( 'If something does not work', 'cart-rules-for-woocommerce' ); ?></h2>
				<table class="widefat striped crfw-table">
					<tbody>
						<tr>
							<td><strong><?php esc_html_e( 'A rule has no effect', 'cart-rules-for-woocommerce' ); ?></strong></td>
							<td><?php esc_html_e( 'Check you set it on the product itself and saved (Update). On a variable product the rule belongs on the parent, not on a single variation.', 'cart-rules-for-woocommerce' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'A shipping method still shows up', 'cart-rules-for-woocommerce' ); ?></strong></td>
							<td><?php esc_html_e( 'Shipping choices are cached per visitor. Empty the cart, or change the delivery address once, to see the new list. A caching plugin may need its cache cleared too.', 'cart-rules-for-woocommerce' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Another minimum-order message appears', 'cart-rules-for-woocommerce' ); ?></strong></td>
							<td><?php esc_html_e( 'Another plugin or a snippet in your theme is enforcing its own minimum. Turn that one off, or leave the minimum order amount here empty, so your customers are told one thing only.', 'cart-rules-for-woocommerce' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'The quantity field does not start at the minimum', 'cart-rules-for-woocommerce' ); ?></strong></td>
							<td><?php esc_html_e( 'That behaviour has a switch: Settings → Cart Rules → Per-product defaults → Quantity fields.', 'cart-rules-for-woocommerce' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>

			<?php echo wp_kses_post( CRFW_Plugin::credit_html() ); ?>
		</div>
		<?php
	}

	/**
	 * A small scoped stylesheet, printed inline.
	 *
	 * Inline rather than enqueued: it is a few rules on one screen, so a file
	 * (and an HTTP request) would cost more than it saves. Every rule that has
	 * a side uses a logical property, so the page mirrors itself on an RTL
	 * locale with no second stylesheet.
	 */
	private static function print_styles() {
		?>
		<style>
			.crfw-guide { max-width: 940px; }
			.crfw-guide .crfw-ver { font-size: 13px; color: #666; font-weight: 400; }
			.crfw-guide .crfw-lede { font-size: 15px; max-width: 760px; margin-block: 6px 18px; }
			.crfw-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 6px; padding: 4px 20px 18px; margin-block-end: 18px; }
			.crfw-card h2 { font-size: 16px; margin-block: 18px 6px; }
			.crfw-card h3 { font-size: 13px; margin-block-end: 4px; text-transform: uppercase; letter-spacing: .04em; color: #555; }
			.crfw-highlight { border-inline-start: 4px solid #7f54b3; }
			.crfw-where { background: #f6f7f7; border-radius: 4px; padding: 8px 12px; margin-block: 10px; display: flex; gap: 8px; align-items: center; }
			.crfw-where .dashicons { color: #7f54b3; }
			.crfw-steps { margin-inline-start: 18px; line-height: 1.9; }
			.crfw-steps li { margin-block-end: 10px; }
			.crfw-bullets { list-style: disc; margin-inline-start: 22px; }
			.crfw-bullets li { margin-block-end: 6px; }
			.crfw-table { margin-block: 10px; }
			.crfw-table td, .crfw-table th { padding: 10px 12px; vertical-align: top; }
			.crfw-table td:first-child { inline-size: 26%; }
			.crfw-example { background: #f6f7f7; border-radius: 4px; padding: 12px 16px; margin-block-start: 10px; }
			.crfw-example ul { list-style: disc; margin-inline-start: 22px; }
			.crfw-tip { color: #555; font-style: italic; }
			.crfw-credit { margin-block-start: 4px; }
			.crfw-guide code { direction: ltr; unicode-bidi: embed; }
		</style>
		<?php
	}
}
