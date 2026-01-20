# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Reply-To for WP_Mail is a simple WordPress plugin that allows site administrators to configure a custom "Reply-To" email address for all emails sent via `wp_mail()`. The plugin provides an admin settings page and automatically modifies email headers to ensure replies go to the correct inbox.

**Key Details:**
- WordPress plugin (single file)
- Minimum WordPress: 4.1 | Tested up to: 6.9
- Minimum PHP: 5.6 | Tested up to: 8.5
- Text Domain: `replyto`
- Translation ready (uses WordPress i18n functions)

## Architecture

### Core Functionality

The entire plugin logic resides in `replyto.php` with three main components:

1. **Email Header Modification** (`wp_mail_replyto()`)
   - Hooks into `wp_mail` filter
   - Retrieves stored Reply-To email from options
   - Intelligently handles existing headers (replaces Reply-To if it matches From address)
   - Normalizes header arrays/strings automatically

2. **Settings Page Registration**
   - Adds "Reply-To" submenu under Settings in WordPress admin
   - Uses WordPress Settings API for proper integration
   - Single setting: `wp_mail_replyto_email` (stored in wp_options table)

3. **Settings Rendering**
   - Standard WordPress settings form with email validation
   - Uses `sanitize_email()` for input sanitization
   - All strings are internationalized with `esc_html__()` and text domain

### Key WordPress Integration Points

- **Filter**: `wp_mail` - Intercepts all outgoing emails
- **Action**: `admin_menu` - Registers settings page
- **Action**: `admin_init` - Registers settings fields
- **Option**: `wp_mail_replyto_email` - Stores configured email address

## Development Commands

### Deployment

```bash
# Create production-ready ZIP package
./bin/deploy.sh 1.1.0

# This will:
# - Validate version matches in replyto.php and readme.txt
# - Create clean build directory
# - Copy only production files
# - Exclude: vendor/, bin/, docs/, .git/, composer files, etc.
# - Generate: ../replyto-1.1.0.zip
```

See [bin/README.md](bin/README.md) for detailed deployment documentation.

### Code Quality & Standards

```bash
# Run WordPress Coding Standards check on plugin file
vendor/bin/phpcs replyto.php --standard=WordPress

# Run PHP Compatibility check (PHP 5.6+)
vendor/bin/phpcs replyto.php --standard=PHPCompatibilityWP --runtime-set testVersion 5.6-

# Check specific WordPress coding standards
vendor/bin/phpcs replyto.php --standard=WordPress-Core
vendor/bin/phpcs replyto.php --standard=WordPress-Docs
vendor/bin/phpcs replyto.php --standard=WordPress-Extra

# Auto-fix coding standards issues (where possible)
vendor/bin/phpcbf replyto.php --standard=WordPress
```

### Dependencies Management

```bash
# Install all development dependencies
composer install

# Update development dependencies
composer update
```

### Available Coding Standards

The following PHPCS standards are installed via Composer:
- **WordPress**, WordPress-Core, WordPress-Docs, WordPress-Extra (default standard)
- **PHPCompatibility**, PHPCompatibilityWP - Check PHP version compatibility
- PSR1, PSR2, PSR12, Squiz, PEAR, Zend, MySource
- Universal, Modernize, NormalizedArrays

## Development Guidelines

### Coding Standards

- Follow **WordPress Coding Standards** strictly (default standard is configured)
- Maintain PHP 5.6+ compatibility (no modern PHP syntax like typed properties, return types, etc.)
- All user-facing strings must be internationalized with text domain `replyto`
- Use WordPress escaping functions (`esc_html__`, `esc_attr`, `sanitize_email`)
- Use WordPress validation functions (`is_email()`)

### Security Practices

- Always sanitize input: `sanitize_email()` for email inputs
- Always escape output: `esc_html()`, `esc_attr()` for HTML attributes
- Check capabilities: `current_user_can( 'manage_options' )` for admin pages
- Use nonces via Settings API (handled automatically by `settings_fields()`)

### WordPress Hooks Pattern

When modifying email behavior:
- The `wp_mail` filter receives a single `$args` array parameter
- Must return the modified `$args` array
- Headers can be string (newline-separated) or array - normalize before processing

### Translation Notes

- Text domain: `replyto`
- Domain path: `/languages`
- Use `esc_html__()` for translatable strings that need HTML escaping
- Use `esc_html_e()` for translatable strings that need immediate echo + escaping

## File Structure

```
replyto/
├── replyto.php          # Main plugin file (all functionality)
├── readme.txt           # WordPress.org plugin readme
├── composer.json        # Development dependencies
├── languages/           # Translation files (empty, ready for translations)
├── assets/              # Plugin assets
├── vendor/              # Composer dependencies (dev only)
└── .claude/            # Claude Code skills (WordPress-specific)
```

## WordPress Skills Available

This repository includes WordPress-specific Claude Code skills in `.claude/skills/`:
- `wp-plugin-development` - WordPress plugin development patterns
- `wp-wpcli-and-ops` - WP-CLI operations and WordPress management
- `wp-phpstan` - PHPStan static analysis for WordPress
- `wp-performance` - WordPress performance optimization
- `wp-playground` - WordPress Playground integration
- `wordpress-router` - WordPress project routing and classification

When working on WordPress-specific tasks, these skills will be automatically activated.
