=== Reply-To for WP_Mail ===
Contributors: javiercasares
Tags: email, reply-to, mail, smtp
Requires at least: 4.1
Tested up to: 6.9
Stable tag: 2.0.0
Requires PHP: 5.6
Version: 2.0.0
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.txt

Configure different "Reply-To" addresses by email context with validation, modern tabbed UI, and automatic migration.

== Description ==

The Reply-To for WP_Mail plugin allows you to easily manage the "Reply-To" header for all emails sent from your WordPress site. With this plugin, you can specify a custom email address that recipients will use when replying to your site's emails, ensuring that responses go to the correct inbox.

== Installation ==

= Manual download =

Extract the contents of the ZIP and upload the contents to the `/wp-content/plugins/replyto/` directory. Once uploaded, it will appear in your plugin list.

== Compatibility ==

* WordPress: 4.1 - 6.9
* PHP: 5.6 - 8.5

== Changelog ==

= [2.0.0] - 2026-01-20 =

**Major Release - Context-Based Reply-To Routing**

This major release combines multiple enhancements including context-based email routing, security improvements, and Reply-To name support.

**Added**

* Context-based Reply-To routing - Configure different Reply-To addresses for different types of emails.
* Six email contexts: Default, Authentication & Security, Comments & Moderation, Users & Registration, System & Updates, WooCommerce.
* WooCommerce tab only visible when WooCommerce plugin is active.
* Intelligent context detection using backtrace analysis.
* Modern tabbed user interface using WordPress native nav-tab-wrapper.
* Visual status indicators on each tab (green = active with email, red = inactive or no email).
* Legend explaining status indicators for easy understanding.
* Enable/disable toggle for each context (except Default which is always active).
* Fallback chain: Specific context → Default context → Legacy settings.
* Detailed descriptions and examples for each context in the admin UI.
* Reply-To Name field - Now you can specify a name to display with the Reply-To email address (e.g., "Support Team <support@example.com>").
* Name sanitization with header injection prevention.
* Length validation for name field (255 characters maximum).
* Created uninstall.php for proper cleanup of plugin data on uninstallation.
* Implemented logging of configuration changes for security auditing (requires WP_DEBUG_LOG).
* Added DNS validation for email domains with user-friendly warnings.
* Enhanced email validation with additional security checks.
* Added success/error messages for better user feedback.

**Changed**

* Complete rewrite of settings page with modern tab-based interface.
* Email detection now uses backtrace analysis for accurate context identification.
* Settings structure changed from individual options to array-based configuration (wp_mail_replyto_contexts).
* Improved sanitization and validation for multiple contexts.
* Enhanced logging with context information in debug mode.
* Email header construction now supports both name and email format.
* Updated sanitize callback to use custom function with enhanced validation.
* Improved security documentation and code comments.

**Security Enhancements**

* Added explicit header injection prevention with defense-in-depth validation.
* Implemented strict RFC 5322 email format validation.
* Enhanced input sanitization with multiple validation layers.

**Technical**

* New database structure: Single serialized array instead of multiple options (more efficient).
* Backward compatible: Legacy options (v1.0.x) still work during migration period.
* Context detection covers: Password resets, comments, user registration, system updates, WooCommerce emails.
* Clean uninstallation: Removes all options including migration flags.
* Automatic migration from v1.0.x - Your existing settings are preserved in the Default context.

**Compatibility**

* WordPress: 4.1 - 6.9
* PHP: 5.6 - 8.5

**Tests**

* PHP Coding Standards: 3.13.4
* WordPress Coding Standards: 3.3.0
* Plugin Check (PCP): 1.4.0

= [1.0.3] - 2025-04-08 =

**Changed**

* Compatible with WordPress 6.8.
* Improved functions documentation.

**Compatibility**

* WordPress: 4.1 - 6.9
* PHP: 5.6 - 8.5

**Tests**

* PHP Coding Standards: 3.12.1
* WordPress Coding Standards: 3.1.0
* Plugin Check (PCP): 1.4.0

= [1.0.2] - 2024-11-02 =

**Fixed**

* Preparation for GlotPress.

= [1.0.1] - 2024-10-31 =

**Added**

* Translation ready.

= [1.0.0] - 2024-10-22 =

**Added**

* First version.
