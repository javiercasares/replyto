#!/bin/bash
################################################################################
# Deploy Script - Reply-To for WP_Mail
#
# This script creates a clean, production-ready ZIP file of the plugin
# excluding all development files and dependencies.
#
# Usage:
#   ./bin/deploy.sh 1.1.0
#
# Output:
#   Creates ../replyto-1.1.0.zip in the parent directory
#
# @package replyto
# @since 1.1.0
################################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script configuration
PLUGIN_SLUG="replyto"
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PLUGIN_DIR="$( cd "$SCRIPT_DIR/.." && pwd )"
PARENT_DIR="$( cd "$PLUGIN_DIR/.." && pwd )"

################################################################################
# Functions
################################################################################

# Print colored message
print_message() {
    local color=$1
    local message=$2
    echo -e "${color}${message}${NC}"
}

# Print error and exit
error_exit() {
    print_message "$RED" "❌ ERROR: $1"
    exit 1
}

# Print success message
success_message() {
    print_message "$GREEN" "✅ $1"
}

# Print info message
info_message() {
    print_message "$BLUE" "ℹ️  $1"
}

# Print warning message
warning_message() {
    print_message "$YELLOW" "⚠️  $1"
}

# Check if version is provided
check_version() {
    if [ -z "$1" ]; then
        error_exit "Version number is required. Usage: ./bin/deploy.sh 1.1.0"
    fi

    # Validate version format (x.x.x)
    if ! [[ $1 =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        error_exit "Invalid version format. Expected format: x.x.x (e.g., 1.1.0)"
    fi
}

# Check if we're in the correct directory
check_directory() {
    if [ ! -f "$PLUGIN_DIR/replyto.php" ]; then
        error_exit "Plugin main file not found. Are you in the correct directory?"
    fi
    success_message "Plugin directory verified"
}

# Verify version matches in plugin files
verify_version() {
    local version=$1
    local main_file="$PLUGIN_DIR/replyto.php"
    local readme_file="$PLUGIN_DIR/readme.txt"

    info_message "Verifying version $version in plugin files..."

    # Check main plugin file
    if ! grep -q "Version: $version" "$main_file"; then
        error_exit "Version $version not found in replyto.php header"
    fi

    # Check readme.txt
    if ! grep -q "Stable tag: $version" "$readme_file"; then
        error_exit "Version $version not found in readme.txt"
    fi

    success_message "Version $version verified in all files"
}

# Copy files to build directory
copy_files() {
    local temp_dir=$1
    local dest_dir="$temp_dir/$PLUGIN_SLUG"

    info_message "Copying plugin files..."

    # Copy main plugin files
    cp "$PLUGIN_DIR/replyto.php" "$dest_dir/"
    cp "$PLUGIN_DIR/uninstall.php" "$dest_dir/"
    cp "$PLUGIN_DIR/readme.txt" "$dest_dir/"
    cp "$PLUGIN_DIR/LICENSE" "$dest_dir/"

    # Copy directories if they exist
    if [ -d "$PLUGIN_DIR/assets" ]; then
        cp -r "$PLUGIN_DIR/assets" "$dest_dir/"
        success_message "Copied assets/"
    fi

    if [ -d "$PLUGIN_DIR/languages" ]; then
        cp -r "$PLUGIN_DIR/languages" "$dest_dir/"
        success_message "Copied languages/"
    fi

    success_message "All production files copied"
}

# Create ZIP file
create_zip() {
    local version=$1
    local temp_dir=$2
    local zip_file="$PARENT_DIR/${PLUGIN_SLUG}-${version}.zip"

    info_message "Creating ZIP file..."

    # Remove existing ZIP if present
    if [ -f "$zip_file" ]; then
        warning_message "ZIP file exists, removing..."
        rm "$zip_file"
    fi

    # Create ZIP (cd to temp dir to avoid including full path)
    cd "$temp_dir"
    zip -r "$zip_file" "$PLUGIN_SLUG" -q
    cd "$PLUGIN_DIR"

    success_message "ZIP file created: $zip_file"
}

# Calculate and display file sizes
show_file_info() {
    local zip_file=$1

    info_message "Package information:"

    # Get file size
    local size=$(du -h "$zip_file" | cut -f1)
    echo "  📦 File: $(basename "$zip_file")"
    echo "  📏 Size: $size"

    # Count files in ZIP
    local file_count=$(unzip -l "$zip_file" | tail -1 | awk '{print $2}')
    echo "  📄 Files: $file_count"
}

# Clean up temporary directory
cleanup() {
    local temp_dir=$1

    info_message "Cleaning up temporary files..."

    if [ -d "$temp_dir" ]; then
        rm -rf "$temp_dir"
        success_message "Temporary files removed"
    fi
}

# Display excluded files info
show_excluded_files() {
    info_message "Excluded from deployment:"
    echo "  🚫 .git/"
    echo "  🚫 .github/"
    echo "  🚫 .claude/"
    echo "  🚫 vendor/"
    echo "  🚫 bin/"
    echo "  🚫 docs/"
    echo "  🚫 composer.json"
    echo "  🚫 composer.lock"
    echo "  🚫 CLAUDE.md"
    echo "  🚫 Development files"
}

# Main deployment process
main() {
    local version=$1

    print_message "$BLUE" "=================================="
    print_message "$BLUE" "  Deploy Script - $PLUGIN_SLUG"
    print_message "$BLUE" "=================================="
    echo ""

    # Step 1: Validate
    info_message "Step 1/6: Validating..."
    check_version "$version"
    check_directory
    verify_version "$version"
    echo ""

    # Step 2: Create temp directory
    info_message "Step 2/6: Preparing build..."
    local temp_dir="$PARENT_DIR/${PLUGIN_SLUG}-build-${version}"

    if [ -d "$temp_dir" ]; then
        warning_message "Temporary directory exists, removing..."
        rm -rf "$temp_dir"
    fi

    mkdir -p "$temp_dir/$PLUGIN_SLUG"
    success_message "Temporary directory created"
    echo ""

    # Step 3: Copy files
    info_message "Step 3/6: Copying production files..."
    copy_files "$temp_dir"
    echo ""

    # Step 4: Show excluded files
    info_message "Step 4/6: Excluding development files..."
    show_excluded_files
    echo ""

    # Step 5: Create ZIP
    info_message "Step 5/6: Building package..."
    zip_file="$PARENT_DIR/${PLUGIN_SLUG}-${version}.zip"
    create_zip "$version" "$temp_dir"
    echo ""

    # Step 6: Cleanup
    info_message "Step 6/6: Finalizing..."
    cleanup "$temp_dir"
    show_file_info "$zip_file"
    echo ""

    # Success message
    print_message "$GREEN" "=================================="
    print_message "$GREEN" "  ✅ Deployment Complete!"
    print_message "$GREEN" "=================================="
    echo ""
    success_message "Package ready: $(basename "$zip_file")"
    success_message "Location: $zip_file"
    echo ""
    info_message "Next steps:"
    echo "  1. Test the ZIP file in a clean WordPress installation"
    echo "  2. Verify all functionality works correctly"
    echo "  3. Upload to WordPress.org (if applicable)"
    echo ""
}

################################################################################
# Script Execution
################################################################################

# Check if script is being sourced or executed
if [ "${BASH_SOURCE[0]}" == "${0}" ]; then
    # Script is being executed directly
    main "$1"
else
    # Script is being sourced
    error_exit "This script should be executed, not sourced"
fi
