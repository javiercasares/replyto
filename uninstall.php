<?php
/**
 * Uninstall script for Reply-To for WP_Mail
 *
 * This file is executed when the plugin is uninstalled via the WordPress admin.
 * It removes all plugin data from the database to ensure clean uninstallation.
 *
 * @package replyto
 * @since 1.1.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete plugin options from single site or main site.
 */
delete_option( 'wp_mail_replyto_email' );
delete_option( 'wp_mail_replyto_name' );

/**
 * For multisite installations, delete the options from all sites.
 */
if ( is_multisite() ) {
	global $wpdb;

	// Get all blog IDs.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$blog_ids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );

	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	foreach ( $blog_ids as $blog_id ) {
		switch_to_blog( $blog_id );
		delete_option( 'wp_mail_replyto_email' );
		delete_option( 'wp_mail_replyto_name' );
		restore_current_blog();
	}
}
