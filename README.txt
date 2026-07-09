=== License Shipper ===
Contributors: licenseshipper
Tags: woocommerce, license, digital products, license keys
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Automatically deliver license keys for digital WooCommerce products via your LicenseShipper App.

== Description ==

License Shipper connects your WooCommerce store to [LicenseShipper](https://licenseshipper.com) so you can deliver license keys after purchase.

**Features:**

* Map WooCommerce products to LicenseShipper SKUs
* Auto-complete orders for instant digital delivery
* Customer license retrieval on order pages and My Account
* Admin license management with React dashboard (Tailwind + shadcn/ui)
* Optional download links and activation guides
* Email notifications after key redemption
* SSO link to the LicenseShipper app

== Installation ==

1. Upload the plugin to `/wp-content/plugins/license-shipper/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Go to **License Shipper → Settings → API** and enter your API key
4. Map products under **Products → Edit → License Shipper** tab

== Frequently Asked Questions ==

= Does this require WooCommerce? =

Yes. WooCommerce must be installed and active.

= Where do customers get their keys? =

On the order details page, the thank-you page, and the **My Keys** section in My Account.

== Changelog ==

= 1.1.0 =
* React admin UI for License Keys, Settings, Download Links, and Activation Guides
* REST API layer (`license-shipper/v1`) with legacy AJAX fallback
* Tailwind CSS + shadcn/ui design system scoped to `#ls-app-root`
* Feature flag `ls_admin_ui_version` for instant rollback to legacy UI

= 1.0.7 =
* Security fixes for activation guide downloads and admin AJAX endpoints
* HPOS compatibility for order metabox and delivery column
* Variable product mapping fallback when variation support is disabled
* Email send mode fix for mixed carts
* Bug fixes across admin ping, test email template, and change-license flow

= 1.0.6 =
* Initial public release improvements
