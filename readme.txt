=== AAWEB Bulk Product Attributes for WooCommerce ===
Contributors: antoapweb
Tags: woocommerce, product attributes, bulk edit, attributes, products
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Bulk assign WooCommerce global product attributes to multiple products using filters, bulk actions and a modern admin interface.

== Description ==

AAWEB Bulk Product Attributes for WooCommerce adds a modern admin screen for assigning WooCommerce global product attributes to multiple products at once.

The plugin is designed for WooCommerce stores that need faster attribute management without opening each product individually. It provides product filters, checkbox selection, global attribute term selection, append or replace mode and a clearer product table with existing attribute badges.

= Main Features =

* Bulk assign WooCommerce global attributes to multiple products.
* Supports all WooCommerce global product attributes dynamically.
* Automatically detects newly created WooCommerce attributes after refresh.
* Product filters by title, keyword/SKU/content, category and stock status.
* Append mode to add selected terms without removing existing terms.
* Replace mode to replace existing terms for selected attributes.
* Modern WooCommerce-style admin interface.
* Attribute panels with searchable term lists.
* Select visible terms and clear terms inside each attribute panel.
* Live selected product counter.
* Sticky bulk action bar.
* Product table with image, title, SKU, price, stock, categories and attribute badges.
* Works only inside the WordPress admin area.

= Important =

This plugin works with WooCommerce global attributes created under Products > Attributes. It does not create custom per-product attributes.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate the plugin from the Plugins screen.
3. Make sure WooCommerce is installed and active.
4. Go to Products > AAWEB Bulk Product Attributes.
5. Filter products, select products, choose attribute terms and apply them in append or replace mode.

== Frequently Asked Questions ==

= Does it require WooCommerce? =

Yes. WooCommerce must be installed and active.

= Does it work with all WooCommerce attributes? =

It works with WooCommerce global product attributes created from Products > Attributes.

= If I create a new product attribute, will the plugin detect it automatically? =

Yes. The plugin reads WooCommerce global attributes dynamically. After creating a new attribute or term, refresh the plugin admin page.

= Can I add terms without removing existing ones? =

Yes. Use append mode to keep existing terms and add the selected terms.

= Can I replace existing attribute terms? =

Yes. Use replace mode. It replaces existing terms only for the selected attributes on the selected products.

= Does it edit regular product categories or tags? =

No. It is focused on WooCommerce global product attributes.

= Does it run on the front end? =

No. It only adds an admin management screen.

= Who can use the plugin screen? =

Only users with the `manage_woocommerce` capability can access and apply changes.

== Screenshots ==

1. Modern bulk product attributes dashboard with filters and product count.
2. Searchable attribute panels with term checkboxes and bulk term tools.
3. Product table with live selected counter, sticky action bar and attribute badges.

== Changelog ==

= 1.0.0 =
* Initial release.
