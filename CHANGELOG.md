# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow [SemVer](https://semver.org/).

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
