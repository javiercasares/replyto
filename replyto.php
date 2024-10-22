<?php
/**
 * Plugin Name: Reply-To for WP_Mail
 * Description: Configure your "Reply-To:" for WP_Mail with validation and admin settings.
 * Requires at least: 4.1
 * Requires PHP: 5.6
 * Version: 1.0.0
 * Author: Javier Casares
 * Author URI: https://www.javiercasares.com/
 * License: GPL-2.0-or-later
 * License URI: https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain: wp-mail-replyto
 *
 * @package wp-mail-replyto
 *
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

/**
 * Configure the "Reply-To" header for emails sent by WP Mail.
 *
 * @param array $args Email arguments.
 * @return array Modified email arguments.
 */
function wp_mail_replyto( $args ) {
	// Get the "Reply-To" email from plugin settings.
	$reply_to_email = get_option( 'wp_mail_replyto_email' );

	// Define the new "Reply-To" if configured.
	if ( ! empty( $reply_to_email ) ) {
		$new_reply_to = 'Reply-To: <' . sanitize_email( $reply_to_email ) . '>';
	}

	// Initialize variables for "From" and existing "Reply-To".
	$from              = '';
	$existing_reply_to = '';

	// Check if there are existing headers.
	if ( ! empty( $args['headers'] ) ) {
		// Normalize headers to an array.
		if ( ! is_array( $args['headers'] ) ) {
			$args['headers'] = array_filter( explode( "\n", str_replace( "\r\n", "\n", $args['headers'] ) ) );
		}

		// Loop through headers to find "From" and "Reply-To".
		foreach ( $args['headers'] as $header ) {
			if ( stripos( $header, 'from:' ) === 0 ) {
				$from = trim( substr( $header, strlen( 'from:' ) ) );
			}
			if ( stripos( $header, 'reply-to:' ) === 0 ) {
				$existing_reply_to = trim( substr( $header, strlen( 'reply-to:' ) ) );
			}
		}

		// Check if "Reply-To" exists and compare with "From".
		if ( ! empty( $existing_reply_to ) && strtolower( $existing_reply_to ) === strtolower( $from ) ) {
			// If they are the same, replace "Reply-To" with the new one.
			$args['headers']   = array_filter(
				$args['headers'],
				function ( $header ) {
					return stripos( $header, 'reply-to:' ) !== 0;
				}
			);
			$args['headers'][] = $new_reply_to;
		} elseif ( isset( $new_reply_to ) ) {
			// If "Reply-To" does not exist and new_reply_to is set, add it.
			$args['headers'][] = $new_reply_to;
		}
	} elseif ( isset( $new_reply_to ) ) {
		// If there are no headers and new_reply_to is set, create headers with "Reply-To".
		$args['headers'] = array( $new_reply_to );
	}

	return $args;
}
add_filter( 'wp_mail', 'wp_mail_replyto' );

/**
 * Add a settings page to the WordPress admin menu.
 */
function wp_mail_replyto_add_settings_page() {
	add_options_page(
		esc_html__( 'WP Mail Reply-To Settings', 'wp-mail-replyto' ),
		esc_html__( 'Reply-To', 'wp-mail-replyto' ),
		'manage_options',
		'wp-mail-replyto',
		'wp_mail_replyto_render_settings_page'
	);
}
add_action( 'admin_menu', 'wp_mail_replyto_add_settings_page' );

/**
 * Register the plugin settings.
 */
function wp_mail_replyto_register_settings() {
	register_setting(
		'wp_mail_replyto_settings_group',
		'wp_mail_replyto_email',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_email',
			'default'           => '',
		)
	);

	add_settings_section(
		'wp_mail_replyto_main_section',
		esc_html__( 'Reply-To Configuration', 'wp-mail-replyto' ),
		'wp_mail_replyto_main_section_callback',
		'wp-mail-replyto'
	);

	add_settings_field(
		'wp_mail_replyto_email_field',
		esc_html__( 'Reply-To Email Address', 'wp-mail-replyto' ),
		'wp_mail_replyto_email_field_callback',
		'wp-mail-replyto',
		'wp_mail_replyto_main_section'
	);
}
add_action( 'admin_init', 'wp_mail_replyto_register_settings' );

/**
 * Callback for the settings section description.
 */
function wp_mail_replyto_main_section_callback() {
	echo '<p>' . esc_html__( 'Set the email address to be used in the "Reply-To" header of outgoing emails.', 'wp-mail-replyto' ) . '</p>';
}

/**
 * Callback to render the email address field.
 */
function wp_mail_replyto_email_field_callback() {
	$email = get_option( 'wp_mail_replyto_email', '' );
	echo '<input type="email" id="wp_mail_replyto_email" name="wp_mail_replyto_email" value="' . esc_attr( $email ) . '" size="50" />';
	echo '<p class="description">' . esc_html__( 'Enter the email address to be used as "Reply-To".', 'wp-mail-replyto' ) . '</p>';
}

/**
 * Render the plugin settings page.
 */
function wp_mail_replyto_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	settings_errors( 'wp_mail_replyto_messages' );
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form action="options.php" method="post">
			<?php
			wp_nonce_field();
			settings_fields( 'wp_mail_replyto_settings_group' );
			do_settings_sections( 'wp-mail-replyto' );
			submit_button( esc_html__( 'Save Settings', 'wp-mail-replyto' ) );
			?>
		</form>
	</div>
	<?php
}
