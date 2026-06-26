<?php
/**
 * Uninstall handler for AAWEB Bulk Product Attributes for WooCommerce.
 *
 * The plugin does not create custom database tables, persistent options,
 * scheduled events or custom post meta. Attribute assignments are native
 * WooCommerce product taxonomy terms and must remain untouched.
 *
 * @package AAWEB_Bulk_Product_Attributes
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Nothing to delete intentionally.
