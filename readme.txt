=== Cart Rules for WooCommerce ===
Contributors: babak-majd
Donate link: https://bobclub.ir/coffee
Tags: woocommerce, minimum order, minimum quantity, shipping methods, cart
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Minimum quantity and spend per product, a minimum order amount for the cart, and allowed shipping methods per product — set on the product edit screen.

== Description ==

Three small rules every shop eventually needs, done the WooCommerce way — no page builder, no custom tables, no JavaScript, no external services.

**Per product** (on the product's own edit screen):

* **Minimum quantity** — the smallest quantity of that product a customer may order. Variations of a variable product count together.
* **Minimum spend** — the smallest amount (quantity × price) a customer must spend on that product.
* **Allowed shipping methods** — pick the shipping methods (a specific zone instance, or "any Flat rate" across every zone) this product may ship with.

**Whole cart** (WooCommerce → Settings → Cart Rules):

* **Minimum order amount** — checked against the subtotal or the total after coupons, your choice.
* **Store-wide defaults** for minimum quantity and minimum spend, which every product inherits unless it sets its own or opts out.
* **Every customer-facing message is editable**, with `{product}`, `{min}` and `{current}` placeholders.

= How the rules combine =

* **Each product must clear its own bar.** With several products in the cart, if any one of them is below its minimum quantity or spend, checkout is blocked and the customer is told exactly which product and by how much.
* **Shipping is the intersection.** Only the methods *every* product in the cart allows are offered. A product with no restriction accepts anything, so it never narrows the choice. If restricted products have nothing in common, the "no shipping methods" slot explains which products cannot ship together instead of a generic message.

= Customers are guided, not just refused =

* Quantity fields and "Add to cart" buttons start at the product's minimum (single product page, shop archive, and the block cart/checkout selectors).
* Adding or updating a line below its minimum is refused with a clear message.
* The product page shows the minimum under the price (optional). Page-builder templates (Elementor, Divi, block templates) can place it anywhere with the `[crfw_minimum]` shortcode.

= Works everywhere =

* Classic (shortcode) cart & checkout **and** the block-based cart & checkout — enforced through `woocommerce_check_cart_items` and the Store API alike.
* Declares compatibility with High-Performance Order Storage (HPOS).
* Fully translatable; ships with Persian, Arabic and German. Right-to-left layouts need nothing extra.
* Every decision is filterable (`crfw_product_rules`, `crfw_cart_violations`, `crfw_allowed_shipping`, `crfw_package_rates`, `crfw_message_template`, …).

Licensed GPL-2.0-or-later. Free to use. If it saves you time, buy me a coffee ☕: https://bobclub.ir/coffee

Site: https://bobclub.ir · Telegram: https://t.me/bob_club · Source: https://github.com/babak-majd/cart-rules-for-woocommerce

== Installation ==

1. Upload the plugin zip via Plugins → Add New → Upload Plugin, then Activate. (WooCommerce 7.4 or newer must be active.)
2. Open any product → **General** tab: set *Minimum quantity* and/or *Minimum spend*. **Shipping** tab: pick the *Allowed shipping methods*.
3. Optionally set a store-wide minimum order amount and defaults under **WooCommerce → Settings → Cart Rules**.

== Frequently Asked Questions ==

= Does the minimum quantity apply per variation or per product? =
Per product: a minimum of 10 on a T-shirt means 10 across all sizes and colours combined. Use the `crfw_cart_item_group` filter to group differently.

= Is tax included in the amounts? =
The comparison follows WooCommerce's "Display prices during cart and checkout" setting: including tax if the cart shows prices including tax, otherwise excluding. Shipping is never included. Override with the `crfw_amounts_include_tax` filter.

= What happens when two products allow different shipping methods? =
Only the methods both allow are offered. If there are none, no shipping method is shown and the customer is told which products cannot be shipped together, so they can split the order.

= Does it work with the block cart and checkout? =
Yes. Minimums are reported through the Store API (`woocommerce_store_api_cart_errors`), quantity selectors respect the minimum, and shipping restrictions apply through `woocommerce_package_rates`, which both checkouts use.

= My product page is built with Elementor and the minimum note does not show =
Page-builder templates often skip WooCommerce's single-product hooks. Drop a Shortcode widget with `[crfw_minimum]` where you want the note (attributes: `id`, `class`, `before`, `after`).

= Which shipping methods can I pick? =
Whatever your site has: every shipping method type registered with WooCommerce (core ones and third-party plugins alike) and every instance you configured in every zone. The list is read live from WooCommerce, nothing is hard-coded.

= Can I change the wording? =
Every message has a field under WooCommerce → Settings → Cart Rules, with placeholders. Or translate the defaults — the plugin is fully internationalised.

= Does deleting the plugin remove my per-product rules? =
Not unless you tick "Remove data" in the settings first. By default nothing is deleted, so reinstalling picks up where you left off.

== Screenshots ==

1. Minimum quantity and minimum spend on the product's General tab.
2. Allowed shipping methods on the product's Shipping tab, grouped by zone.
3. The Cart Rules settings tab: minimum order, defaults, messages.
4. A cart below the minimum, with the exact product named.

== Changelog ==

= 1.0.0 =
* Initial release: per-product minimum quantity and minimum spend, store-wide minimum order amount, per-product allowed shipping methods (intersection across the cart), editable messages, classic + block cart/checkout, HPOS compatible, `[crfw_minimum]` shortcode, Persian/Arabic/German translations.

== Upgrade Notice ==

= 1.0.0 =
First release.
