# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [SemVer](https://semver.org/).

## [1.3.1] — 2026-09-25

### Fixed
- The dialog opened on clicks inside a quantity widget. Where the quantity box sits in an element
  that carries the product's id, `+`, `−` and the field itself were caught as if they were the
  button, so the customer could not change the number. Interception is now limited to an actual
  add-to-cart control (`button`/`input` naming the product, WooCommerce's own classes, a `form.cart`
  submit, or a link whose href or class says it adds to the cart), and anything inside a quantity
  widget is ignored outright.

## [1.3.0] — 2026-09-25

### Added
- A dialog that asks before a minimum refuses the customer: "minimum is 5 — add 5?". Agreeing adds
  exactly enough to clear the rule (quantity **or** spend); declining adds nothing. Once the minimum
  is met, later additions are silent.
- It hooks no particular markup: the click is caught on the document in the capture phase, so it
  works with WooCommerce's buttons and forms, page-builder links and a shop's own JavaScript alike.
  Every quantity field for that product is set to the agreed number and restored afterwards.
- Public REST route `crfw/v1/minimums` (the rules stay in PHP) and the setting
  **Adding to the cart → Ask the customer …** (`crfw_ask_before_add`, default on).

### Changed
- "Start quantity fields at the minimum" now applies only when the dialog is switched off; with the
  dialog on, the fields are left alone so the customer is asked instead of silently corrected.

## [1.2.1] — 2026-09-25

### Fixed
- The "Any *method*" group listed every registered shipping method type, including types with no
  enabled instance in any zone. Choosing one silently narrowed the product's shipping options to
  nothing useful. Such a type is now left out unless the product already uses it, in which case it
  is listed as "(no method enabled)".

## [1.2.0] — 2026-09-25

### Changed
- The *Allowed shipping methods* field lists only methods that are **enabled** in
  WooCommerce → Settings → Shipping. A store that has reorganised its zones can carry dozens of
  disabled leftovers, which made the field unusable. A disabled method a product already uses is
  still listed, marked "(disabled)", so an existing rule is never dropped silently.

### Added
- Filter `crfw_show_disabled_shipping_methods` (default `false`) to list disabled methods anyway.
- `crfw_shipping_method_choices` now also receives the product's stored method ids.

## [1.1.0] — 2026-09-22

### Added
- **WooCommerce → Cart Rules** guide page: where every feature lives, in plain language, with
  links to the products list, the shipping zones and the settings tab, a worked example of how
  several products in one cart combine, and a troubleshooting table. Read-only — it stores nothing.
- "Guide" action link on the Plugins screen; a link to the guide from the settings tab.

### Changed
- Translations updated for the new screen (Persian, Arabic, German).

## [1.0.0] — 2026-09-20

### Added
- Per-product **minimum quantity** and **minimum spend** (General tab); variations count together.
- Per-product **"No minimums"** opt-out.
- Per-product **allowed shipping methods** (Shipping tab), by zone instance or by method type.
- Store-wide **minimum order amount**, against the subtotal or the total after coupons.
- Store-wide **default** minimum quantity / spend that products inherit.
- Shipping intersection across the cart; an explanatory message when products cannot ship together.
- Quantity inputs, archive buttons and Store API quantity limits start at the minimum.
- Editable customer messages with placeholders.
- Classic and block cart/checkout support; HPOS compatibility declared.
- `[crfw_minimum]` shortcode for page-builder product templates.
- English, Persian (fa_IR), Arabic (ar) and German (de_DE) translations.
- Opt-in data removal on uninstall.
