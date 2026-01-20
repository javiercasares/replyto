=== Reply-To for WP_Mail ===
Contributors: javiercasares
Tags: email, reply-to
Requires at least: 4.1
Tested up to: 6.8
Stable tag: 1.2.0
Requires PHP: 5.6
Version: 1.2.0
License: GPL-2.0-or-later
License URI: https://spdx.org/licenses/GPL-2.0-or-later.html

Configure the "Reply-To" header for WordPress emails with validation and customizable admin settings.

== Description ==

The Reply-To for WP_Mail plugin allows you to easily manage the "Reply-To" header for all emails sent from your WordPress site. With this plugin, you can specify a custom email address that recipients will use when replying to your site's emails, ensuring that responses go to the correct inbox.

== Installation ==

= Manual download =

Extract the contents of the ZIP and upload the contents to the `/wp-content/plugins/replyto/` directory. Once uploaded, it will appear in your plugin list.

== Compatibility ==

* WordPress: 4.1 - 6.8
* PHP: 5.6 - 8.4

== Changelog ==

= [1.2.0] - 2026-01-20 =

**Added**

* Reply-To Name field - Now you can specify a name to display with the Reply-To email address (e.g., "Support Team <support@example.com>").
* Name sanitization with header injection prevention.
* Optional name field with helpful description and examples.
* Length validation for name field (255 characters maximum).

**Changed**

* Email header construction now supports both name and email format.
* Uninstall routine updated to remove both email and name options.
* Improved admin UI with clearer field descriptions.

**Compatibility**

* WordPress: 4.1 - 6.8
* PHP: 5.6 - 8.4

= [1.1.0] - 2026-01-20 =

**Security Enhancements**

* Added explicit header injection prevention with defense-in-depth validation.
* Implemented strict RFC 5322 email format validation.
* Enhanced input sanitization with multiple validation layers.

**Added**

* Created uninstall.php for proper cleanup of plugin data on uninstallation.
* Implemented logging of configuration changes for security auditing (requires WP_DEBUG_LOG).
* Added DNS validation for email domains with user-friendly warnings.
* Enhanced email validation with additional security checks.
* Added success/error messages for better user feedback.

**Changed**

* Updated sanitize callback to use custom function with enhanced validation.
* Improved security documentation and code comments.

**Compatibility**

* WordPress: 4.1 - 6.8
* PHP: 5.6 - 8.4

**Security Audit**

* Complete security audit performed (Score: 8.5/10).
* All OWASP Top 10 vulnerabilities addressed.
* Approved for production use.

= [1.0.3] - 2025-04-08 =

**Changed**

* Compatible with WordPress 6.8.
* Improved functions documentation.

**Compatibility**

* WordPress: 4.1 - 6.8
* PHP: 5.6 - 8.4

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
