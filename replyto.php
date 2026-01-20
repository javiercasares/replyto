<?php
/**
 * Plugin Name: Reply-To for WP_Mail
 * Description: Configure your "Reply-To:" for WP_Mail with validation and admin settings.
 * Requires at least: 4.1
 * Requires PHP: 5.6
 * Version: 1.2.0
 * Author: Javier Casares
 * Author URI: https://www.javiercasares.com/
 * License: GPL-2.0-or-later
 * License URI: https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain: replyto
 * Domain Path: /languages
 *
 * @package replyto
 *
 * @version 1.2.0
 */

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

/**
 * Modifies the "Reply-To" header in emails sent using wp_mail().
 *
 * Retrieves the "Reply-To" email address from the plugin settings, validates it,
 * and injects it into the email headers if appropriate.
 *
 * @param array $args The email arguments passed to wp_mail().
 * @return array Modified email arguments with adjusted "Reply-To" header.
 */
function wp_mail_replyto( $args ) {
	// Retrieve the "Reply-To" email address and name from plugin settings.
	$reply_to_email = get_option( 'wp_mail_replyto_email' );
	$reply_to_name  = get_option( 'wp_mail_replyto_name', '' );

	// Explicit header injection prevention - defense in depth.
	if ( ! empty( $reply_to_email ) ) {
		$reply_to_email = str_replace( array( "\r", "\n", '%0a', '%0d', "\0" ), '', $reply_to_email );
	}

	if ( ! empty( $reply_to_name ) ) {
		$reply_to_name = str_replace( array( "\r", "\n", '%0a', '%0d', "\0" ), '', $reply_to_name );
	}

	// Construct the new "Reply-To" header if a valid email address is set.
	if ( ! empty( $reply_to_email ) && is_email( $reply_to_email ) ) {
		if ( ! empty( $reply_to_name ) ) {
			// Include name in Reply-To header.
			$new_reply_to = 'Reply-To: ' . sanitize_text_field( $reply_to_name ) . ' <' . sanitize_email( $reply_to_email ) . '>';
		} else {
			// Email only.
			$new_reply_to = 'Reply-To: <' . sanitize_email( $reply_to_email ) . '>';
		}
	}

	// Initialize variables to track the "From" and existing "Reply-To" headers.
	$from              = '';
	$existing_reply_to = '';

	// Check if email headers exist.
	if ( ! empty( $args['headers'] ) ) {
		// Normalize headers into an array if they are not already.
		if ( ! is_array( $args['headers'] ) ) {
			$args['headers'] = array_filter( explode( "\n", str_replace( "\r\n", "\n", $args['headers'] ) ) );
		}

		// Loop through the headers to locate "From" and "Reply-To" values.
		foreach ( $args['headers'] as $header ) {
			if ( stripos( $header, 'from:' ) === 0 ) {
				$from = trim( substr( $header, strlen( 'from:' ) ) );
			}
			if ( stripos( $header, 'reply-to:' ) === 0 ) {
				$existing_reply_to = trim( substr( $header, strlen( 'reply-to:' ) ) );
			}
		}

		// If "Reply-To" exists and matches the "From" address, replace it with the new one.
		if ( ! empty( $existing_reply_to ) && strtolower( $existing_reply_to ) === strtolower( $from ) ) {
			// Remove the existing "Reply-To" header.
			$args['headers'] = array_filter(
				$args['headers'],
				function ( $header ) {
					return stripos( $header, 'reply-to:' ) !== 0;
				}
			);

			// Add the new "Reply-To" header.
			$args['headers'][] = $new_reply_to;

		} elseif ( isset( $new_reply_to ) ) {
			// If no conflicting "Reply-To" is found, append the new one.
			$args['headers'][] = $new_reply_to;
		}
	} elseif ( isset( $new_reply_to ) ) {
		// If no headers exist at all, create a new headers array with the "Reply-To".
		$args['headers'] = array( $new_reply_to );
	}

	// Return the modified email arguments.
	return $args;
}

add_filter( 'wp_mail', 'wp_mail_replyto' );

/**
 * Registers a settings page for the plugin in the WordPress admin menu.
 *
 * Adds a new page under the "Settings" menu where administrators can configure
 * the "Reply-To" email address used in outgoing emails.
 */
function wp_mail_replyto_add_settings_page() {
	add_options_page(
		esc_html__( 'WP Mail Reply-To Settings', 'replyto' ),
		esc_html__( 'Reply-To', 'replyto' ),
		'manage_options',
		'replyto',
		'wp_mail_replyto_render_settings_page'
	);
}

add_action( 'admin_menu', 'wp_mail_replyto_add_settings_page' );

/**
 * Validates email address with strict RFC 5322 format checking.
 *
 * Performs additional validation beyond WordPress's is_email() function
 * to ensure strict compliance with email standards.
 *
 * @since 1.1.0
 *
 * @param string $email The email address to validate.
 * @return bool True if email is valid, false otherwise.
 */
function wp_mail_replyto_validate_email_strict( $email ) {
	// Basic WordPress email validation.
	if ( ! is_email( $email ) ) {
		return false;
	}

	// Ensure no angle brackets in the email address itself.
	if ( false !== strpos( $email, '<' ) || false !== strpos( $email, '>' ) ) {
		return false;
	}

	// Additional validation for special characters that could cause issues.
	if ( preg_match( '/[\r\n\0]/', $email ) ) {
		return false;
	}

	return true;
}

/**
 * Sanitizes and logs changes to the Reply-To email setting.
 *
 * This function sanitizes the input email address and logs any changes
 * for security auditing purposes. It's used as the sanitize_callback
 * for the plugin settings.
 *
 * @since 1.1.0
 *
 * @param string $input The input email address to sanitize.
 * @return string The sanitized email address.
 */
function wp_mail_replyto_sanitize_and_log( $input ) {
	// Sanitize the email address.
	$sanitized = sanitize_email( $input );

	// Get the old value for comparison.
	$old_value = get_option( 'wp_mail_replyto_email', '' );

	// Perform strict validation.
	if ( ! empty( $sanitized ) && ! wp_mail_replyto_validate_email_strict( $sanitized ) ) {
		add_settings_error(
			'wp_mail_replyto_messages',
			'wp_mail_replyto_invalid_email',
			esc_html__( 'The email address format is not valid. Please check and try again.', 'replyto' ),
			'error'
		);
		// Return the old value if validation fails.
		return $old_value;
	}

	// Check domain existence (optional validation with warning only).
	if ( ! empty( $sanitized ) ) {
		$domain = substr( strrchr( $sanitized, '@' ), 1 );
		if ( ! empty( $domain ) && function_exists( 'checkdnsrr' ) ) {
			if ( ! checkdnsrr( $domain, 'MX' ) && ! checkdnsrr( $domain, 'A' ) ) {
				add_settings_error(
					'wp_mail_replyto_messages',
					'wp_mail_replyto_domain_warning',
					esc_html__( 'Warning: The email domain does not appear to have valid DNS records. The email may not work correctly.', 'replyto' ),
					'warning'
				);
			}
		}
	}

	// Log changes for security auditing.
	if ( $old_value !== $sanitized ) {
		$user = wp_get_current_user();

		// Use error_log for logging (can be configured in wp-config.php).
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log(
				sprintf(
					'[Reply-To Plugin] Email changed from "%s" to "%s" by user %s (ID: %d) from IP: %s',
					$old_value,
					$sanitized,
					$user->user_login,
					$user->ID,
					isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown'
				)
			);
		}

		// Add success message if change was successful.
		if ( ! empty( $sanitized ) || ! empty( $old_value ) ) {
			add_settings_error(
				'wp_mail_replyto_messages',
				'wp_mail_replyto_updated',
				esc_html__( 'Reply-To email address updated successfully.', 'replyto' ),
				'success'
			);
		}
	}

	return $sanitized;
}

/**
 * Registers the plugin settings, section, and field in the WordPress Settings API.
 *
 * This function defines:
 * - A setting for the "Reply-To" email address with sanitization.
 * - A settings section with a description callback.
 * - A field for entering the "Reply-To" email address.
 */
function wp_mail_replyto_register_settings() {
	// Register the "Reply-To" email setting with sanitization and logging.
	register_setting(
		'wp_mail_replyto_settings_group',
		'wp_mail_replyto_email',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'wp_mail_replyto_sanitize_and_log',
			'default'           => '',
		)
	);

	// Register the "Reply-To" name setting.
	register_setting(
		'wp_mail_replyto_settings_group',
		'wp_mail_replyto_name',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'wp_mail_replyto_sanitize_name',
			'default'           => '',
		)
	);

	// Add a section to the settings page for organizing fields.
	add_settings_section(
		'wp_mail_replyto_main_section',
		esc_html__( 'Reply-To Configuration', 'replyto' ),
		'wp_mail_replyto_main_section_callback',
		'replyto'
	);

	// Add the input field for the "Reply-To" email address.
	add_settings_field(
		'wp_mail_replyto_email_field',
		esc_html__( 'Reply-To Email Address', 'replyto' ),
		'wp_mail_replyto_email_field_callback',
		'replyto',
		'wp_mail_replyto_main_section'
	);

	// Add the input field for the "Reply-To" name.
	add_settings_field(
		'wp_mail_replyto_name_field',
		esc_html__( 'Reply-To Name', 'replyto' ),
		'wp_mail_replyto_name_field_callback',
		'replyto',
		'wp_mail_replyto_main_section'
	);
}

add_action( 'admin_init', 'wp_mail_replyto_register_settings' );

/**
 * Outputs the description for the main settings section.
 *
 * This callback is used by the Settings API to display a description
 * under the "Reply-To Configuration" section on the plugin settings page.
 */
function wp_mail_replyto_main_section_callback() {
	echo '<p>' . esc_html__( 'Set the email address to be used in the "Reply-To" header of outgoing emails.', 'replyto' ) . '</p>';
}

/**
 * Renders the input field for the "Reply-To" email address.
 *
 * This callback is used by the Settings API to display the input field
 * where administrators can enter the email address to be used in the "Reply-To" header.
 */
function wp_mail_replyto_email_field_callback() {
	// Get the current "Reply-To" email address from the plugin settings.
	$email = get_option( 'wp_mail_replyto_email', '' );

	// Output the input field HTML.
	echo '<input type="email" id="wp_mail_replyto_email" name="wp_mail_replyto_email" value="' . esc_attr( $email ) . '" size="50" />';
	echo '<p class="description">' . esc_html__( 'Enter the email address to be used as "Reply-To".', 'replyto' ) . '</p>';
}

/**
 * Renders the input field for the "Reply-To" name.
 *
 * This callback is used by the Settings API to display the input field
 * where administrators can enter an optional name to be used in the "Reply-To" header.
 *
 * @since 1.2.0
 */
function wp_mail_replyto_name_field_callback() {
	// Get the current "Reply-To" name from the plugin settings.
	$name = get_option( 'wp_mail_replyto_name', '' );

	// Output the input field HTML.
	echo '<input type="text" id="wp_mail_replyto_name" name="wp_mail_replyto_name" value="' . esc_attr( $name ) . '" size="50" />';
	echo '<p class="description">' . esc_html__( 'Optional: Enter a name to display with the Reply-To email (e.g., "Support Team").', 'replyto' ) . '</p>';
}

/**
 * Sanitizes the Reply-To name field.
 *
 * Removes dangerous characters and validates the name input.
 *
 * @since 1.2.0
 *
 * @param string $input The input name to sanitize.
 * @return string The sanitized name.
 */
function wp_mail_replyto_sanitize_name( $input ) {
	// Sanitize the name using WordPress text field sanitization.
	$sanitized = sanitize_text_field( $input );

	// Additional security: remove potential header injection characters.
	$sanitized = str_replace( array( "\r", "\n", '%0a', '%0d', "\0" ), '', $sanitized );

	// Limit length to prevent abuse (255 characters is reasonable for a name).
	if ( strlen( $sanitized ) > 255 ) {
		$sanitized = substr( $sanitized, 0, 255 );
		add_settings_error(
			'wp_mail_replyto_messages',
			'wp_mail_replyto_name_too_long',
			esc_html__( 'Reply-To name was truncated to 255 characters.', 'replyto' ),
			'warning'
		);
	}

	return $sanitized;
}

/**
 * Renders the plugin's settings page in the WordPress admin area.
 *
 * This function outputs the HTML structure and form elements for the plugin settings page.
 * It includes the registered settings fields and sections for configuring the "Reply-To" email.
 */
function wp_mail_replyto_render_settings_page() {
	// Check if the current user has the required capability.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Display settings errors, if any.
	settings_errors( 'wp_mail_replyto_messages' );
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form action="options.php" method="post">
			<?php
			// Output security fields and registered settings sections.
			settings_fields( 'wp_mail_replyto_settings_group' );
			do_settings_sections( 'replyto' );
			submit_button( esc_html__( 'Save Settings', 'replyto' ) );
			?>
		</form>
	</div>
	<?php
}
