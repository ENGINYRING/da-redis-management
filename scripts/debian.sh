#!/bin/bash

# DirectAdmin Redis Management Plugin Installation Script
# For Debian/Ubuntu systems

set -e

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

echo_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

echo_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
check_root() {
    if [ "$EUID" -ne 0 ]; then
        echo_error "This script must be run as root"
        exit 1
    fi
}

# Create required directories
create_directories() {
    echo_info "Creating required directories..."
    
    # Plugin directories
    mkdir -p /usr/local/directadmin/plugins/redis_management/logs
    mkdir -p /usr/local/directadmin/plugins/redis_management/data
    
    # Set secure permissions
    chmod 750 /usr/local/directadmin/plugins/redis_management/logs
    chmod 750 /usr/local/directadmin/plugins/redis_management/data
    
    echo_info "Directories created successfully"
}

# Fix ownership and permissions
fix_permissions() {
    echo_info "Setting proper ownership and permissions..."
    
    # Fix general ownership
    chown -R diradmin:diradmin /usr/local/directadmin/plugins/redis_management
    
    # Data directory should be owned by redis user for security
    chown -R redis:redis /usr/local/directadmin/plugins/redis_management/data
    
    # Fix script permissions
    find /usr/local/directadmin/plugins/redis_management -name "*.html" -exec chmod 755 {} \;
    find /usr/local/directadmin/plugins/redis_management -name "*.php" -exec chmod 755 {} \;
    
    # Secure configuration files
    if [ -f "/usr/local/directadmin/plugins/redis_management/php/Config/local.php" ]; then
        chmod 640 /usr/local/directadmin/plugins/redis_management/php/Config/local.php
        chown diradmin:redis /usr/local/directadmin/plugins/redis_management/php/Config/local.php
    fi
    
    echo_info "Permissions set successfully"
}

# Update template configuration for Debian
update_template() {
    echo_info "Updating Redis template for Debian..."
    
    local template_file="/usr/local/directadmin/plugins/redis_management/php/Templates/redis-instance.conf"
    
    if [ -f "$template_file" ]; then
        # Backup original
        cp "$template_file" "${template_file}.backup.$(date +%Y%m%d_%H%M%S)"
        
        # Update path for Debian
        sed -i 's@/etc/redis.conf@/etc/redis/redis.conf@g' "$template_file"
        
        echo_info "Template updated successfully"
    else
        echo_warn "Template file not found, skipping update"
    fi
}

# Setup user destruction hook
setup_user_destroy_hook() {
    echo_info "Setting up user destruction hook..."
    
    local hook_script="/usr/local/directadmin/scripts/custom/user_destroy_post.sh"
    local hook_entry='/usr/local/directadmin/plugins/redis_management/php/Hooks/DirectAdmin/userDestroyPost.php "$username"'
    
    # Create custom scripts directory if it doesn't exist
    mkdir -p "$(dirname "$hook_script")"
    
    # Create hook script if it doesn't exist
    if [ ! -f "$hook_script" ]; then
        echo_info "Creating user destroy hook script..."
        cat > "$hook_script" << 'EOF'
#!/bin/bash
# DirectAdmin user destruction hooks
# This script is called when a user is deleted

username="$1"

if [ -z "$username" ]; then
    echo "Error: No username provided"
    exit 1
fi

# Log the deletion
echo "$(date): Processing user deletion for: $username" >> /var/log/directadmin_user_destroy.log
EOF
        chmod +x "$hook_script"
    fi
    
    # Add Redis cleanup hook if not already present
    if ! grep -q "redis_management" "$hook_script" 2>/dev/null; then
        echo_info "Adding Redis cleanup to user destroy hook..."
        echo "" >> "$hook_script"
        echo "# Redis Management Plugin cleanup" >> "$hook_script"
        echo "$hook_entry" >> "$hook_script"
    else
        echo_info "Redis cleanup hook already exists"
    fi
    
    # Make the hook executable
    local hook_php="/usr/local/directadmin/plugins/redis_management/php/Hooks/DirectAdmin/userDestroyPost.php"
    if [ -f "$hook_php" ]; then
        chmod +x "$hook_php"
        echo_info "Made userDestroyPost.php executable"
    else
        echo_warn "userDestroyPost.php not found"
    fi
}

# Validate installation
validate_installation() {
    echo_info "Validating installation..."
    
    local errors=0
    
    # Check required directories
    local required_dirs=(
        "/usr/local/directadmin/plugins/redis_management/logs"
        "/usr/local/directadmin/plugins/redis_management/data"
        "/etc/redis/instances"
    )
    
    for dir in "${required_dirs[@]}"; do
        if [ ! -d "$dir" ]; then
            echo_error "Required directory missing: $dir"
            ((errors++))
        fi
    done
    
    # Check required files
    local required_files=(
        "/usr/local/directadmin/plugins/redis_management/php/Controllers/RedisController.php"
        "/usr/local/directadmin/plugins/redis_management/plugin.conf"
        "/etc/sudoers.d/redis"
    )
    
    for file in "${required_files[@]}"; do
        if [ ! -f "$file" ]; then
            echo_error "Required file missing: $file"
            ((errors++))
        fi
    done
    
    # Check systemd services
    if ! systemctl is-enabled redis-server.service >/dev/null 2>&1; then
        echo_error "Redis server service is not enabled"
        ((errors++))
    fi
    
    # Check sudoers file syntax
    if ! visudo -c -f /etc/sudoers.d/redis >/dev/null 2>&1; then
        echo_error "Invalid sudoers configuration"
        ((errors++))
    fi
    
    if [ $errors -eq 0 ]; then
        echo_info "✓ Installation validation passed"
        return 0
    else
        echo_error "Installation validation failed with $errors errors"
        return 1
    fi
}

# Create log rotation configuration
setup_log_rotation() {
    echo_info "Setting up log rotation..."
    
    cat > /etc/logrotate.d/redis-management << 'EOF'
/usr/local/directadmin/plugins/redis_management/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 640 diradmin redis
    postrotate
        # Send HUP signal to any running Redis instances to reopen log files
        systemctl reload-or-restart redis-server.service 2>/dev/null || true
    endscript
}
EOF
    
    echo_info "Log rotation configured"
}

# Main execution
main() {
    echo_info "Starting DirectAdmin Redis Management Plugin installation..."
    
    check_root
    create_directories
    fix_permissions
    update_template
    setup_user_destroy_hook
    setup_log_rotation
    
    if validate_installation; then
        echo_info "Plugin installation completed successfully!"
        echo ""
        echo_info "Installation Summary:"
        echo "- Plugin files configured and secured"
        echo "- User destruction hook installed"
        echo "- Log rotation configured"
        echo "- All validations passed"
        echo ""
        echo_info "The Redis Management plugin is now ready for use."
        echo_info "Users can access it through their DirectAdmin control panel."
    else
        echo_error "Installation completed with errors. Please check the logs and fix any issues."
        exit 1
    fi
}

# Run main function
main "$@"
