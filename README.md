# Cart Rules for WooCommerce

**Minimum quantity and spend per product, a minimum order amount for the whole cart, and
allowed shipping methods per product — set on the product edit screen.**

Three small rules every shop eventually needs, done the WooCommerce way: native product-data
fields, a native settings tab, no custom tables, no JavaScript, no external services. Works on
the classic and the block cart/checkout.

---

## What it does

### Where to start: **WooCommerce → Cart Rules**

A guide page the plugin adds to the WooCommerce menu. It names the exact location of every
feature and links straight to it, shows a worked example of how several products in one cart are
combined, and lists the four things that usually go wrong. It is read-only; the settings live
where a WooCommerce user expects them (below).

### On each product (General & Shipping tabs)

| Field | Tab | Meaning |
|---|---|---|
| **Minimum quantity** | General | The smallest quantity of this product a customer may order. Variations count together. |
| **Minimum spend** | General | The smallest amount (quantity × price) a customer must spend on this product. |
| **No minimums** | General | Opt this product out of every minimum, including the store defaults. |
| **Allowed shipping methods** | Shipping | Only these methods are offered while this product is in the cart. Pick a zone instance (*Post — Tehran*) or a method type (*Any "Flat rate"*). |

### For the whole store (WooCommerce → Settings → Cart Rules)

- **Minimum order amount**, compared against the subtotal or the total after coupons.
- **Default minimum quantity / spend** every product inherits unless it sets its own.
- Whether to **ask** before adding below a minimum (on by default), or instead to silently start
  quantity fields and *Add to cart* buttons at the minimum.
- Whether the product page shows the minimum under the price — or place it yourself with the
  `[crfw_minimum]` shortcode (`id`, `class`, `before`, `after`) in page-builder templates.
- **Every customer-facing message**, with `{product}`, `{products}`, `{min}`, `{current}` placeholders.
- Whether to remove all data on uninstall (off by default).

### How the rules combine

- **Each product must clear its own bar.** With several products in the cart, if any one is
  below its minimum, checkout is blocked and the customer is told which product and by how much.
- **Shipping is the intersection.** Only the methods *every* product allows are offered. An
  unrestricted product accepts anything and never narrows the choice. If restricted products have
  nothing in common, no rate is offered and the "no shipping methods" slot explains which
  products cannot ship together.

### Customers are asked before they are refused

1. Clicking *Add to cart* with less than the minimum opens a dialog: *"“Widget” is sold in a minimum
   of 5 items. Add 5?"*. Yes adds exactly enough to clear the rule (quantity **or** spend); No adds
   nothing at all. Once the minimum is met, later clicks pass without a word.
2. It is not bound to any markup. The click is caught on `document` in the **capture phase**, before
   handlers bound on the element or delegated through jQuery, so WooCommerce's own buttons and forms,
   a page builder's `?add-to-cart=` link and a shop's hand-written JavaScript all work. The product is
   read from `data-product_id` / `data-product-id`, the link, or a `form.cart`; the agreed quantity is
   written into **every** quantity field for that product (a second, mobile copy included) and the
   page's own values are restored ~1.5s later.
3. Whatever slips through is still refused server-side: `woocommerce_add_to_cart_validation`,
   `woocommerce_check_cart_items` and `woocommerce_store_api_cart_errors`. The dialog is courtesy,
   not enforcement.

### Works on any WooCommerce site

- Shipping choices are read live from WooCommerce: every method type that has at least one
  enabled instance (core or third-party) and every **enabled** zone instance — a method switched off in WooCommerce is not
  offered, unless the product already uses it (then it stays, marked "(disabled)"). Matching uses
  both the rate id and the method id, so methods that do not use zones still work through
  "Any *method*".
- Classic (shortcode) and block cart/checkout, HPOS declared compatible.
- Fully translatable (text domain `cart-rules-for-woocommerce`); ships with Persian, Arabic and
  German, and wordpress.org language packs apply automatically. RTL needs nothing extra — the
  plugin has no stylesheet of its own.

## Install

Download the latest zip from [Releases](https://github.com/babak-majd/cart-rules-for-woocommerce/releases)
(or build one — see below), then **Plugins → Add New → Upload Plugin → Activate**.
Requires WordPress 6.0+, PHP 7.4+, WooCommerce 7.4+.

## For developers

Everything lives in `includes/`:

```
cart-rules-for-woocommerce.php   headers, constants, WooCommerce check, boot
includes/crfw-functions.php      option defaults, messages, number helpers
includes/class-crfw-rules.php    the engine: rules per product, cart evaluation, shipping intersection (no hooks)
includes/class-crfw-cart.php     customer-facing enforcement (classic + Store API)
includes/class-crfw-shipping.php woocommerce_package_rates filter, method choices
includes/class-crfw-frontend.php the REST route + assets behind the "add the rest?" dialog
assets/js|css/                   the dialog itself (no build step, no dependencies)
includes/class-crfw-plugin.php   bootstrap, plugin links, credit line
includes/admin/                  product edit fields, the WC settings tab, the guide screen
uninstall.php                    opt-in cleanup
```

### Filters

| Filter | Purpose |
|---|---|
| `crfw_product_rules( $rules, $product )` | Change the effective rules for a product (`min_qty`, `min_amount`, `shipping`, `exempt`). |
| `crfw_cart_item_group( $group_id, $item )` | Group cart lines differently than by parent product. |
| `crfw_amounts_include_tax( $bool )` | Compare amounts including/excluding tax. |
| `crfw_cart_amount( $amount, $cart, $basis )` | The figure the minimum order is checked against. |
| `crfw_cart_violations( $violations, $cart )` | Add, remove or reword violations. |
| `crfw_allowed_shipping( $allowed, $items )` | The allow-list for a set of items (`null` = unrestricted, `[]` = conflict). |
| `crfw_package_rates( $kept, $rates, $allowed, $package )` | The rates left after filtering. |
| `crfw_shipping_method_choices( $groups, $selected )` | The choices offered on the product edit screen. |
| `crfw_show_disabled_shipping_methods( $bool, $selected )` | Offer methods disabled in WooCommerce too (default `false`). |
| `crfw_message_template( $message, $key, $replacements )` | The message template before placeholders. |
| `crfw_settings( $settings )` | The settings-tab fields. |

### Data

Post meta on the (parent) product: `_crfw_min_qty`, `_crfw_min_amount`, `_crfw_exempt`,
`_crfw_shipping_methods` (array of `method_id:instance_id` or `method_id`). Options are all
prefixed `crfw_`. Nothing else is written.

### Build & lint

```bash
./build.sh                       # dist/cart-rules-for-woocommerce.zip
composer install && composer lint   # PHPCS with WordPress + WooCommerce standards
wp i18n make-pot . languages/cart-rules-for-woocommerce.pot   # refresh the template
```

## Contributing

Issues and pull requests are welcome on
[GitHub](https://github.com/babak-majd/cart-rules-for-woocommerce). Translations: the plugin
ships English, Persian, Arabic and German; other locales come through
[translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/cart-rules-for-woocommerce/).

---

Licensed GPL-2.0-or-later.

Site: [bobclub.ir](https://bobclub.ir) · Telegram: [@bob_club](https://t.me/bob_club) ·
If it saves you time: [buy me a coffee ☕](https://bobclub.ir/coffee)
