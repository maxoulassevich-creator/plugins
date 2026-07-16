=== Foneona Cart Layout (Shortcode) ===
Contributors: (generated)
Tags: woocommerce, cart, shortcode, layout
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.8.4
License: GPLv2 or later

== Description ==
Custom WooCommerce cart + checkout + order received layout. Includes shortcodes [foneona_cart] and [foneona_checkout].

== Usage ==
1) Install and activate the plugin.
2) Create pages and add shortcodes: [foneona_cart] / [foneona_checkout]
3) In WooCommerce → Settings → Advanced set pages as Cart/Checkout (if needed).
4) Configure checkboxes, DaData, and checkout account registration in Settings → Foneona Woo Layout.

== Changelog ==

= 1.8.4 =
* Cart totals panel now shows only the final row “Итого”. The removed row is “Подытог”.
* The cart “Итого” value now uses only the products subtotal and does not include previously selected or session-saved shipping.
* Added a wide WordPress visual editor for the custom temporary-password email.
* Added separate CSS settings and a live wide preview for the custom temporary-password email.

= 1.8.2 =
* Compact mobile checkout styling aligned with the reference layout.
* Removed the checkout summary subtotal row.
* Reduced mobile spacing between checkout summary rows.
* Kept shortcode/page background transparent; only form controls and summary cards keep their own white surfaces.

= 1.8.1 =
* Improved compact mobile cart layout.
* Prevented visible quantity stepper from going below 1; item removal remains available through the remove link.

= 1.8.0 =
* Added custom WooCommerce checkout/thankyou.php override for the order received page.
* Added Amarèssence styled thank-you hero, order overview, product summary, delivery details and next-step cards.
* Added custom styled checkout login reminder/form override.
* Added responsive order received spacing: 65px desktop, 25px mobile.
* Checkout styles now also load on the WooCommerce order received endpoint without loading checkout-only scripts there.

= 1.7.1 =
* Fixed custom checkout account checkbox capture: the field is now copied into WooCommerce posted data, so account creation request is saved correctly.
* Added a separate HTML email with login and temporary random password for newly created checkout accounts.
* Added admin settings for temporary-password email enable/disable, subject, and HTML template.
* Added order notes/meta for password email delivery status without storing the plaintext password.
* Added extra defensive checks before reading RELOD Referral Points tables.
* Added custom checkout fields to WooCommerce posted data so final validation receives the same values as AJAX updates.

= 1.7.0 =
* Added secure guest account registration offer on checkout.
* Added admin settings for registration offer text, checkbox default state, existing-email policy and account creation timing.
* Default account creation happens after successful payment/order paid status, not before payment.
* Existing account email can be blocked for guest checkout to prevent points/referral abuse.
* Created accounts are linked to the paid order and billing/shipping data is copied to the customer profile.
* Added best-effort linking of an existing RELOD Referral Points profile to the new customer account by email when safe.
* Loyalty points used at checkout are now returned on failed orders as well as cancelled/refunded orders.

= 1.6.0 =
* Removed the bottom order-price formula from checkout summary.
* Added a dedicated discount row above subtotal.
* Added redemption of loyalty points from RELOD Referral Points directly on checkout.
* Points usage is validated against the active balance/profile from the referral points plugin.
* Redeemed points are written to the RRP ledger and automatically returned on cancelled/refunded orders.

= 1.5.0 =
* Three consent checkboxes (2 required + 1 optional) with admin settings.
* Fixed: All shipping methods now display (removed conditional wrapper).
* Fixed: Address field always visible below City.
* Fixed: DaData conflict with Yandex Delivery plugin resolved.

== Notes ==
- The plugin overrides WooCommerce templates only on cart, checkout, checkout login and order received pages.
- Shipping options are rendered using WooCommerce standard templates so shipping plugins can inject their methods.
- When Yandex Delivery has its own DaData token, Foneona DaData auto-disables on checkout to prevent conflicts.
