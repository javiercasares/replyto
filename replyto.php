<?php
/**
 * Plugin Name: Reply-To for WP_Mail
 * Description: Configure your "Reply-To:" for WP_Mail with validation, admin settings, and context-based routing.
 * Requires at least: 4.1
 * Requires PHP: 5.6
 * Version: 1.3.0
 * Author: Javier Casares
 * Author URI: https://www.javiercasares.com/
 * License: GPL-2.0-or-later
 * License URI: https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain: replyto
 * Domain Path: /languages
 *
 * @package replyto
 *
 * @version 1.3.0
 */

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

/**
 * Performs automatic migration from v1.2.0 to v1.3.0.
 *
 * Migrates old single email/name options to new context-based structure.
 * Runs once on plugin activation or when version changes.
 *
 * @since 1.3.0
 */
function wp_mail_replyto_migrate_to_v130() {
	// Check if migration has already been performed.
	$migration_done = get_option( 'wp_mail_replyto_migration_v130', false );
	if ( $migration_done ) {
		return;
	}

	// Get old settings.
	$old_email = get_option( 'wp_mail_replyto_email', '' );
	$old_name  = get_option( 'wp_mail_replyto_name', '' );

	// Check if old settings exist.
	if ( ! empty( $old_email ) || ! empty( $old_name ) ) {
		// Create new context structure with old values in 'default' context.
		$contexts = array(
			'default'        => array(
				'email'   => $old_email,
				'name'    => $old_name,
				'enabled' => true,
			),
			'authentication' => array(
				'email'   => '',
				'name'    => '',
				'enabled' => false,
			),
			'comments'       => array(
				'email'   => '',
				'name'    => '',
				'enabled' => false,
			),
			'users'          => array(
				'email'   => '',
				'name'    => '',
				'enabled' => false,
			),
			'system'         => array(
				'email'   => '',
				'name'    => '',
				'enabled' => false,
			),
		);

		// Add WooCommerce context only if WooCommerce is active.
		if ( class_exists( 'WooCommerce' ) ) {
			$contexts['woocommerce'] = array(
				'email'   => '',
				'name'    => '',
				'enabled' => false,
			);
		}

		// Save new structure.
		update_option( 'wp_mail_replyto_contexts', $contexts );

		// Keep old options for rollback safety (will be removed on uninstall).
	}

	// Mark migration as complete.
	update_option( 'wp_mail_replyto_migration_v130', true );
}

add_action( 'admin_init', 'wp_mail_replyto_migrate_to_v130' );

/**
 * Detects email context based on WordPress backtrace.
 *
 * Analyzes the call stack to determine what type of email is being sent
 * and returns the appropriate context identifier.
 *
 * @since 1.3.0
 *
 * @return string Context: 'authentication', 'comments', 'users', 'system', 'woocommerce', or 'default'.
 */
function wp_mail_replyto_detect_context() {
	static $context = null;

	// Use cached value if available (for performance).
	if ( null !== $context ) {
		return $context;
	}

	// Get backtrace with minimal overhead.
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace -- Not debug code: debug_backtrace() is essential for production context detection. It analyzes the call stack to identify which WordPress function is sending the email (e.g., retrieve_password, wp_notify_postauthor). This is the core functionality of the plugin, not debugging. The function is optimized with DEBUG_BACKTRACE_IGNORE_ARGS and limited to 20 frames for performance.
	$backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 20 );

	foreach ( $backtrace as $trace ) {
		if ( empty( $trace['function'] ) ) {
			continue;
		}

		$function = $trace['function'];
		$class    = isset( $trace['class'] ) ? $trace['class'] : '';

		// Authentication context.
		if ( in_array(
			$function,
			array(
				'retrieve_password',
				'reset_password',
				'wp_password_change_notification',
			),
			true
		) ) {
			$context = 'authentication';
			return $context;
		}

		// Comments context.
		if ( in_array(
			$function,
			array(
				'wp_notify_postauthor',
				'wp_notify_moderator',
				'wp_new_comment_notify_postauthor',
				'wp_new_comment_notify_moderator',
			),
			true
		) ) {
			$context = 'comments';
			return $context;
		}

		// Users context.
		if ( in_array(
			$function,
			array(
				'wp_new_user_notification',
				'wp_send_new_user_notifications',
				'register_new_user',
			),
			true
		) ) {
			$context = 'users';
			return $context;
		}

		// System context.
		if ( 'WP_Automatic_Updater' === $class ||
			'WP_Recovery_Mode' === $class ||
			in_array(
				$function,
				array(
					'wp_maybe_auto_update',
					'send_core_update_notification_email',
				),
				true
			) ) {
			$context = 'system';
			return $context;
		}

		// WooCommerce context.
		if ( ! empty( $class ) && false !== strpos( $class, 'WC_Email' ) ) {
			$context = 'woocommerce';
			return $context;
		}
	}

	// Default context if no specific match.
	$context = 'default';
	return $context;
}

/**
 * Resets the context cache after email is sent.
 *
 * Ensures each email detection starts fresh.
 *
 * @since 1.3.0
 *
 * @param array $args Email arguments.
 * @return array Unmodified email arguments.
 */
function wp_mail_replyto_reset_context( $args ) {
	// Reset static cache in detect function.
	wp_mail_replyto_detect_context();
	return $args;
}

add_filter( 'wp_mail', 'wp_mail_replyto_reset_context', 999 );

/**
 * Modifies the "Reply-To" header in emails sent using wp_mail().
 *
 * Detects email context and applies appropriate Reply-To based on configuration.
 * Falls back to default context if specific context is not configured.
 *
 * @since 1.3.0 Updated to support context-based Reply-To.
 *
 * @param array $args The email arguments passed to wp_mail().
 * @return array Modified email arguments with adjusted "Reply-To" header.
 */
function wp_mail_replyto( $args ) {
	// Detect email context.
	$detected_context = wp_mail_replyto_detect_context();

	// Get all contexts configuration.
	$contexts = get_option( 'wp_mail_replyto_contexts', array() );

	// Fallback chain: specific context -> default -> legacy.
	$reply_to_email = '';
	$reply_to_name  = '';

	// Try specific context first (if enabled).
	if ( isset( $contexts[ $detected_context ] ) &&
		! empty( $contexts[ $detected_context ]['enabled'] ) &&
		! empty( $contexts[ $detected_context ]['email'] ) ) {
		$reply_to_email = $contexts[ $detected_context ]['email'];
		$reply_to_name  = $contexts[ $detected_context ]['name'];
	} elseif ( isset( $contexts['default'] ) && ! empty( $contexts['default']['email'] ) ) {
		// Fall back to default context.
		$reply_to_email = $contexts['default']['email'];
		$reply_to_name  = $contexts['default']['name'];
	} else {
		// Final fallback: legacy single option (for migration period).
		$reply_to_email = get_option( 'wp_mail_replyto_email', '' );
		$reply_to_name  = get_option( 'wp_mail_replyto_name', '' );
	}

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
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional: error_log() is used for security audit logging, not debugging. Only active when WP_DEBUG_LOG is enabled. Records configuration changes with user info and IP for security compliance and troubleshooting. This is a production feature for administrators who enable logging in wp-config.php.
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
 * This function defines the context-based configuration structure.
 *
 * @since 1.3.0 Updated to support multiple contexts.
 */
function wp_mail_replyto_register_settings() {
	// Register the contexts setting.
	register_setting(
		'wp_mail_replyto_settings_group',
		'wp_mail_replyto_contexts',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'wp_mail_replyto_sanitize_contexts',
			'default'           => array(),
		)
	);

	// Keep legacy settings registered for backward compatibility during migration.
	register_setting(
		'wp_mail_replyto_settings_group',
		'wp_mail_replyto_email',
		array(
			'type'    => 'string',
			'default' => '',
		)
	);

	register_setting(
		'wp_mail_replyto_settings_group',
		'wp_mail_replyto_name',
		array(
			'type'    => 'string',
			'default' => '',
		)
	);
}

add_action( 'admin_init', 'wp_mail_replyto_register_settings' );

/**
 * Sanitizes and validates the contexts configuration.
 *
 * Validates all context email addresses and names, provides appropriate
 * error messages, and logs changes.
 *
 * @since 1.3.0
 *
 * @param array $input The input contexts array.
 * @return array The sanitized contexts array.
 */
function wp_mail_replyto_sanitize_contexts( $input ) {
	$sanitized = array();

	// Define available contexts.
	$available_contexts = array(
		'default'        => __( 'Default', 'replyto' ),
		'authentication' => __( 'Authentication', 'replyto' ),
		'comments'       => __( 'Comments', 'replyto' ),
		'users'          => __( 'Users', 'replyto' ),
		'system'         => __( 'System', 'replyto' ),
	);

	// Add WooCommerce context only if WooCommerce is active.
	if ( class_exists( 'WooCommerce' ) ) {
		$available_contexts['woocommerce'] = __( 'WooCommerce', 'replyto' );
	}

	foreach ( $available_contexts as $context_key => $context_label ) {
		// Get input for this context.
		$email   = isset( $input[ $context_key ]['email'] ) ? sanitize_email( $input[ $context_key ]['email'] ) : '';
		$name    = isset( $input[ $context_key ]['name'] ) ? sanitize_text_field( $input[ $context_key ]['name'] ) : '';
		$enabled = isset( $input[ $context_key ]['enabled'] ) && '1' === $input[ $context_key ]['enabled'];

		// Header injection prevention.
		if ( ! empty( $email ) ) {
			$email = str_replace( array( "\r", "\n", '%0a', '%0d', "\0" ), '', $email );
		}
		if ( ! empty( $name ) ) {
			$name = str_replace( array( "\r", "\n", '%0a', '%0d', "\0" ), '', $name );
		}

		// Validate email if provided.
		if ( ! empty( $email ) && ! wp_mail_replyto_validate_email_strict( $email ) ) {
			add_settings_error(
				'wp_mail_replyto_messages',
				'wp_mail_replyto_invalid_email_' . $context_key,
				/* translators: %s: context name */
				sprintf( esc_html__( 'Invalid email address for %s context.', 'replyto' ), $context_label ),
				'error'
			);
			$email = '';
		}

		// Limit name length.
		if ( strlen( $name ) > 255 ) {
			$name = substr( $name, 0, 255 );
			add_settings_error(
				'wp_mail_replyto_messages',
				'wp_mail_replyto_name_long_' . $context_key,
				/* translators: %s: context name */
				sprintf( esc_html__( 'Name for %s context was truncated to 255 characters.', 'replyto' ), $context_label ),
				'warning'
			);
		}

		// Default context is always enabled if it has an email.
		if ( 'default' === $context_key && ! empty( $email ) ) {
			$enabled = true;
		}

		// Store sanitized values.
		$sanitized[ $context_key ] = array(
			'email'   => $email,
			'name'    => $name,
			'enabled' => $enabled,
		);
	}

	// Log changes if in debug mode.
	$old_value = get_option( 'wp_mail_replyto_contexts', array() );
	if ( $old_value !== $sanitized ) {
		$user = wp_get_current_user();
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional: error_log() is used for security audit logging, not debugging. Only active when WP_DEBUG_LOG is enabled. Records context configuration changes with user info and IP for security compliance and troubleshooting. This is a production feature for administrators who enable logging in wp-config.php.
			error_log(
				sprintf(
					'[Reply-To Plugin] Contexts configuration updated by user %s (ID: %d) from IP: %s',
					$user->user_login,
					$user->ID,
					isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown'
				)
			);
		}

		add_settings_error(
			'wp_mail_replyto_messages',
			'wp_mail_replyto_updated',
			esc_html__( 'Reply-To configuration updated successfully.', 'replyto' ),
			'success'
		);
	}

	return $sanitized;
}

/**
 * Enqueues admin styles for the settings page.
 *
 * @since 1.3.0
 *
 * @param string $hook The current admin page hook.
 */
function wp_mail_replyto_admin_styles( $hook ) {
	if ( 'settings_page_replyto' !== $hook ) {
		return;
	}
	?>
	<style>
		.replyto-context-description {
			margin: 10px 0 20px;
			padding: 10px;
			background: #f0f6fc;
			border-left: 4px solid #2271b1;
		}
		.replyto-field-group {
			margin-bottom: 20px;
		}
		.replyto-field-group label {
			display: block;
			font-weight: 600;
			margin-bottom: 5px;
		}
		.replyto-field-group input[type="email"],
		.replyto-field-group input[type="text"] {
			width: 100%;
			max-width: 400px;
		}
		.replyto-field-group .description {
			margin-top: 5px;
			color: #646970;
		}
		.replyto-toggle-wrapper {
			margin-bottom: 20px;
			padding: 15px;
			background: #fff9e5;
			border-left: 4px solid #dba617;
		}
		.replyto-tab-content {
			margin-top: 20px;
		}
		.replyto-status-legend {
			font-size: 12px;
			color: #646970;
			margin: 10px 0 0 0;
			padding: 0;
		}
		.replyto-status-legend span {
			margin-right: 15px;
		}
	</style>
	<?php
}

add_action( 'admin_enqueue_scripts', 'wp_mail_replyto_admin_styles' );

/**
 * Renders the plugin's settings page with tab-based interface.
 *
 * Uses WordPress native nav-tab-wrapper for consistent admin UI.
 *
 * @since 1.3.0 Completely rewritten with tabs interface.
 */
function wp_mail_replyto_render_settings_page() {
	// Check if the current user has the required capability.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Get current contexts configuration.
	$contexts = get_option( 'wp_mail_replyto_contexts', array() );

	// Get active tab from URL, default to 'default'.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab parameter is for display only, not data modification.
	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'default';

	// Define contexts with descriptions.
	$context_config = array(
		'default'        => array(
			'label'       => __( 'Default', 'replyto' ),
			'description' => __( 'Default Reply-To used for all emails that do not match a specific context. This is the fallback when other contexts are not configured.', 'replyto' ),
			'examples'    => __( 'Newsletter confirmations, general notifications, and any emails not covered by other contexts.', 'replyto' ),
		),
		'authentication' => array(
			'label'       => __( 'Authentication & Security', 'replyto' ),
			'description' => __( 'Reply-To for password resets, password changes, and email address changes.', 'replyto' ),
			'examples'    => __( 'Password reset requests, password change confirmations, email address verifications.', 'replyto' ),
		),
		'comments'       => array(
			'label'       => __( 'Comments & Moderation', 'replyto' ),
			'description' => __( 'Reply-To for comment notifications and moderation alerts.', 'replyto' ),
			'examples'    => __( 'New comment notifications, comment moderation alerts, comment replies.', 'replyto' ),
		),
		'users'          => array(
			'label'       => __( 'Users & Registration', 'replyto' ),
			'description' => __( 'Reply-To for new user registrations, role changes, and user management emails.', 'replyto' ),
			'examples'    => __( 'New user welcome emails, user role changes, account activations.', 'replyto' ),
		),
		'system'         => array(
			'label'       => __( 'System & Updates', 'replyto' ),
			'description' => __( 'Reply-To for automatic updates, system alerts, and critical site health notifications.', 'replyto' ),
			'examples'    => __( 'WordPress core updates, plugin updates, theme updates, recovery mode, fatal error notifications.', 'replyto' ),
		),
	);

	// Add WooCommerce context only if WooCommerce is active.
	if ( class_exists( 'WooCommerce' ) ) {
		$context_config['woocommerce'] = array(
			'label'       => __( 'WooCommerce', 'replyto' ),
			'description' => __( 'Reply-To for WooCommerce order notifications, invoices, and customer communications.', 'replyto' ),
			'examples'    => __( 'Order confirmations, shipping notifications, invoices, customer notes.', 'replyto' ),
		);
	}

	// Validate active tab exists.
	if ( ! isset( $context_config[ $active_tab ] ) ) {
		$active_tab = 'default';
	}

	// Display settings errors, if any.
	settings_errors( 'wp_mail_replyto_messages' );
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<p><?php esc_html_e( 'Configure different Reply-To addresses based on the type of email being sent. Each context can have its own email address and display name.', 'replyto' ); ?></p>

		<h2 class="nav-tab-wrapper">
			<?php
			foreach ( $context_config as $key => $config ) {
				$tab_url = add_query_arg(
					array(
						'page' => 'replyto',
						'tab'  => $key,
					),
					admin_url( 'options-general.php' )
				);

				// Determine status indicator.
				$ctx_email   = isset( $contexts[ $key ]['email'] ) ? $contexts[ $key ]['email'] : '';
				$ctx_enabled = isset( $contexts[ $key ]['enabled'] ) ? $contexts[ $key ]['enabled'] : false;

				// For Default context: only check if email exists (always enabled).
				// For other contexts: check if enabled AND email exists.
				if ( 'default' === $key ) {
					$status_indicator = ! empty( $ctx_email ) ? '🟢 ' : '🔴 ';
				} else {
					$status_indicator = ( $ctx_enabled && ! empty( $ctx_email ) ) ? '🟢 ' : '🔴 ';
				}

				printf(
					'<a href="%s" class="nav-tab%s">%s%s</a>',
					esc_url( $tab_url ),
					$active_tab === $key ? ' nav-tab-active' : '',
					esc_html( $status_indicator ),
					esc_html( $config['label'] )
				);
			}
			?>
		</h2>

		<p class="replyto-status-legend">
			<span>🟢 <?php esc_html_e( 'Active with email configured', 'replyto' ); ?></span>
			<span>🔴 <?php esc_html_e( 'Inactive or no email configured', 'replyto' ); ?></span>
		</p>

		<form action="options.php" method="post">
			<?php settings_fields( 'wp_mail_replyto_settings_group' ); ?>

			<div class="replyto-tab-content">
				<?php
				$config  = $context_config[ $active_tab ];
				$email   = isset( $contexts[ $active_tab ]['email'] ) ? $contexts[ $active_tab ]['email'] : '';
				$name    = isset( $contexts[ $active_tab ]['name'] ) ? $contexts[ $active_tab ]['name'] : '';
				$enabled = isset( $contexts[ $active_tab ]['enabled'] ) ? $contexts[ $active_tab ]['enabled'] : false;
				?>

				<div class="replyto-context-description">
					<p><strong><?php esc_html_e( 'What this context covers:', 'replyto' ); ?></strong><br>
					<?php echo esc_html( $config['description'] ); ?></p>
					<p><strong><?php esc_html_e( 'Examples:', 'replyto' ); ?></strong><br>
					<?php echo esc_html( $config['examples'] ); ?></p>
				</div>

				<?php if ( 'default' !== $active_tab ) : ?>
				<div class="replyto-toggle-wrapper">
					<label>
						<input type="checkbox"
							name="wp_mail_replyto_contexts[<?php echo esc_attr( $active_tab ); ?>][enabled]"
							value="1"
							<?php checked( $enabled ); ?> />
						<strong><?php esc_html_e( 'Enable this context', 'replyto' ); ?></strong>
					</label>
					<p class="description">
						<?php esc_html_e( 'When disabled, emails in this context will use the Default Reply-To address.', 'replyto' ); ?>
					</p>
				</div>
				<?php endif; ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="replyto_<?php echo esc_attr( $active_tab ); ?>_email">
									<?php esc_html_e( 'Reply-To Email Address', 'replyto' ); ?>
								</label>
							</th>
							<td>
								<input type="email"
									id="replyto_<?php echo esc_attr( $active_tab ); ?>_email"
									name="wp_mail_replyto_contexts[<?php echo esc_attr( $active_tab ); ?>][email]"
									value="<?php echo esc_attr( $email ); ?>"
									class="regular-text" />
								<p class="description">
									<?php esc_html_e( 'Enter the email address where replies should be sent.', 'replyto' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="replyto_<?php echo esc_attr( $active_tab ); ?>_name">
									<?php esc_html_e( 'Reply-To Display Name (Optional)', 'replyto' ); ?>
								</label>
							</th>
							<td>
								<input type="text"
									id="replyto_<?php echo esc_attr( $active_tab ); ?>_name"
									name="wp_mail_replyto_contexts[<?php echo esc_attr( $active_tab ); ?>][name]"
									value="<?php echo esc_attr( $name ); ?>"
									class="regular-text" />
								<p class="description">
									<?php esc_html_e( 'Optional: Enter a name to display with the email (e.g., "Support Team").', 'replyto' ); ?>
								</p>
							</td>
						</tr>
					</tbody>
				</table>

				<?php if ( 'default' === $active_tab ) : ?>
				<p class="description">
					<strong><?php esc_html_e( 'Note:', 'replyto' ); ?></strong>
					<?php esc_html_e( 'The Default context is always active and acts as a fallback for all emails.', 'replyto' ); ?>
				</p>
				<?php endif; ?>

				<?php
				// Add hidden fields for all other contexts to preserve their values.
				foreach ( $context_config as $key => $config_item ) {
					if ( $key === $active_tab ) {
						continue;
					}

					$ctx_email   = isset( $contexts[ $key ]['email'] ) ? $contexts[ $key ]['email'] : '';
					$ctx_name    = isset( $contexts[ $key ]['name'] ) ? $contexts[ $key ]['name'] : '';
					$ctx_enabled = isset( $contexts[ $key ]['enabled'] ) ? $contexts[ $key ]['enabled'] : false;
					?>
					<input type="hidden" name="wp_mail_replyto_contexts[<?php echo esc_attr( $key ); ?>][email]" value="<?php echo esc_attr( $ctx_email ); ?>" />
					<input type="hidden" name="wp_mail_replyto_contexts[<?php echo esc_attr( $key ); ?>][name]" value="<?php echo esc_attr( $ctx_name ); ?>" />
					<?php if ( $ctx_enabled ) : ?>
					<input type="hidden" name="wp_mail_replyto_contexts[<?php echo esc_attr( $key ); ?>][enabled]" value="1" />
					<?php endif; ?>
					<?php
				}
				?>
			</div>

			<?php submit_button( esc_html__( 'Save Settings', 'replyto' ) ); ?>
		</form>
	</div>
	<?php
}
