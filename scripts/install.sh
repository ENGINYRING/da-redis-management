#!/bin/bash

# DirectAdmin Redis Management Plugin Installation Script
# Enhanced for better compatibility and security

set -e  # Exit on any error

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

echo_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

echo_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

echo_step() {
    echo -e "${BLUE}[STEP]${NC} $1"
}

# Function to detect OS
detect_os() {
    if [ -f /etc/redhat-release ]; then
        OS="rhel"
        if grep -q "CentOS" /etc/redhat-release; then
            OS="centos"
        fi
        VERSION=$(grep -oE '[0-9]+' /etc/redhat-release | head -n1)
    elif [ -f /etc/debian_version ]; then
        OS="debian"
        VERSION=$(cat /etc/debian_version | cut -d. -f1)
        if [ -f /etc/lsb-release ]; then
            . /etc/lsb-release
            if [ "$DISTRIB_ID" = "Ubuntu" ]; then
                OS="ubuntu"
                VERSION=$(echo $DISTRIB_RELEASE | cut -d. -f1)
            fi
        fi
    else
        echo_error "Unsupported operating system"
        exit 1
    fi
    
    echo_info "Detected: $OS $VERSION"
}

# Function to check if we're running as root
check_root() {
    if [ "$EUID" -ne 0 ]; then
        echo_error "This script must be run as root"
        exit 1
    fi
}

# Function to check prerequisites
check_prerequisites() {
    echo_step "Checking prerequisites..."
    
    # Check if DirectAdmin is installed
    if [ ! -d "/usr/local/directadmin" ]; then
        echo_error "DirectAdmin not found in /usr/local/directadmin"
        exit 1
    fi
    
    # Check if Redis is installed
    if ! command -v redis-server &> /dev/null; then
        echo_error "Redis server not found. Please install Redis first."
        echo_info "Run the setup script from the setup directory first."
        exit 1
    fi
    
    # Check if systemctl is available
    if ! command -v systemctl &> /dev/null; then
        echo_error "systemctl not found. This plugin requires systemd."
        exit 1
    fi
    
    # Check PHP version
    if command -v php &> /dev/null; then
        PHP_VERSION=$(php -r "echo PHP_VERSION_ID;")
        if [ "$PHP_VERSION" -lt "70400" ]; then
            echo_error "PHP 7.4 or higher is required"
            exit 1
        fi
        echo_info "PHP version check passed"
    else
        echo_warn "PHP not found in PATH, skipping version check"
    fi
    
    echo_info "Prerequisites check completed"
}

# Function to install EPEL repository (RHEL/CentOS only)
install_epel() {
    if [ "$OS" = "centos" ] || [ "$OS" = "rhel" ]; then
        echo_step "Installing EPEL repository..."
        if [ ! "$(rpm -qa | grep epel-release)" ]; then
            yum -y install epel-release
            echo_info "EPEL repository installed"
        else
            echo_info "EPEL repository already installed"
        fi
    fi
}

# Function to install Redis (RHEL/CentOS only)
install_redis_rhel() {
    if [ "$OS" = "centos" ] || [ "$OS" = "rhel" ]; then
        echo_step "Installing Redis..."
        if [ ! "$(rpm -qa | grep redis)" ]; then
            yum -y install redis
            echo_info "Redis installed"
        else
            echo_info "Redis already installed"
        fi
    fi
}

# Function to handle PHP Redis extension
install_php_redis() {
    echo_step "Installing PHP Redis extension..."
    
    # Determine PHP version
    PHP_VERSION=$(php -i 2>/dev/null | grep 'PHP Version' | head -n1 || echo "")
    
    # Remount /tmp with execute permissions if needed
    REMOUNT_TMP=false
    if mount | grep /tmp | grep -q noexec; then
        echo_info "Remounting /tmp with execute permissions"
        mount -o remount,exec /tmp
        REMOUNT_TMP=true
    fi
    
    # Install php-redis module if not installed
    if ! php -m 2>/dev/null | grep -q redis; then
        echo_info "Installing PHP Redis extension via PECL"
        if [[ $PHP_VERSION == *"7."* ]] || [[ $PHP_VERSION == *"8."* ]]; then
            yes '' | pecl install -f redis 2>/dev/null || true
        else
            yes '' | pecl install -f redis-2.2.8 2>/dev/null || true
        fi
        
        # Enable redis extension in custom php.ini
        if [ -f "/usr/local/lib/php.conf.d/20-custom.ini" ]; then
            if ! grep -q "redis.so" /usr/local/lib/php.conf.d/20-custom.ini; then
                echo -e "\n; Redis\nextension=redis.so" >> /usr/local/lib/php.conf.d/20-custom.ini
                echo_info "Redis extension enabled in PHP configuration"
            fi
        else
            echo_warn "PHP configuration file not found, you may need to manually enable Redis extension"
        fi
        
        # Restart Apache/httpd
        if systemctl is-active --quiet httpd; then
            systemctl restart httpd
            echo_info "Apache restarted"
        elif systemctl is-active --quiet apache2; then
            systemctl restart apache2
            echo_info "Apache2 restarted"
        fi
    else
        echo_info "PHP Redis extension already installed"
    fi
    
    # Remount /tmp with noexec if it was originally mounted that way
    if [ "$REMOUNT_TMP" = true ]; then
        echo_info "Restoring /tmp mount options"
        mount -o remount,noexec /tmp
    fi
}

# Function to create directories
create_directories() {
    echo_step "Creating plugin directories..."
    
    # Create log directory
    mkdir -p /usr/local/directadmin/plugins/redis_management/logs
    chmod 750 /usr/local/directadmin/plugins/redis_management/logs
    
    # Create data directory
    mkdir -p /usr/local/directadmin/plugins/redis_management/data
    chmod 750 /usr/local/directadmin/plugins/redis_management/data
    
    # Create Redis instances config directory
    mkdir -p /etc/redis/instances
    chmod 755 /etc/redis/instances
    
    echo_info "Directories created successfully"
}

# Function to set permissions
set_permissions() {
    echo_step "Setting file permissions..."
    
    # Fix general ownership
    chown -R diradmin:diradmin /usr/local/directadmin/plugins/redis_management
    
    # Data directory should be owned by redis user for security
    chown -R redis:redis /usr/local/directadmin/plugins/redis_management/data
    chown -R redis:redis /etc/redis/instances
    
    # Fix script permissions
    find /usr/local/directadmin/plugins/redis_management -name "*.html" -exec chmod 755 {} \;
    find /usr/local/directadmin/plugins/redis_management -name "*.php" -exec chmod 755 {} \;
    
    # Secure sensitive files
    if [ -f "/usr/local/directadmin/plugins/redis_management/php/Config/local.php" ]; then
        chmod 640 /usr/local/directadmin/plugins/redis_management/php/Config/local.php
        chown diradmin:redis /usr/local/directadmin/plugins/redis_management/php/Config/local.php
    fi
    
    echo_info "Permissions set successfully"
}

# Function to configure systemd services
configure_systemd() {
    echo_step "Configuring systemd services..."
    
    # Use the appropriate script based on OS
    if [ "$OS" = "debian" ] || [ "$OS" = "ubuntu" ]; then
        if [ -f "./debian.sh" ]; then
            echo_info "Running Debian/Ubuntu specific configuration"
            bash ./debian.sh
        else
            echo_warn "Debian configuration script not found"
        fi
    else
        # RHEL/CentOS configuration
        # Create instances folder for redis instances
        mkdir -p /etc/redis/instances
        chown -R redis:redis /etc/redis/instances
        
        # Remove existing systemctl script
        rm -f /lib/systemd/system/redis.service
        
        # Copy new systemctl scripts if they exist
        if [ -f "../setup/redis@.service" ]; then
            cp ../setup/redis@.service /lib/systemd/system/
        fi
        if [ -f "../setup/redis.service" ]; then
            cp ../setup/redis.service /lib/systemd/system/
        fi
        
        # Reload systemctl daemons
        systemctl daemon-reload
        
        # Enable main service
        systemctl enable redis.service || systemctl enable redis-server.service
    fi
    
    echo_info "Systemd configuration completed"
}

# Function to configure sudo permissions
configure_sudo() {
    echo_step "Configuring sudo permissions..."
    
    # Copy sudoers file
    if [ -f "../setup/redis.sudoers" ]; then
        cp ../setup/redis.sudoers /etc/sudoers.d/redis
        
        # Fix sudoers file permissions
        chown root:root /etc/sudoers.d/redis
        chmod 440 /etc/sudoers.d/redis
        
        # Validate sudoers file
        if visudo -c -f /etc/sudoers.d/redis; then
            echo_info "Sudoers configuration installed successfully"
        else
            echo_error "Invalid sudoers configuration"
            rm -f /etc/sudoers.d/redis
            exit 1
        fi
    else
        echo_error "Sudoers configuration file not found"
        exit 1
    fi
}

# Function to setup user destruction hook
setup_user_destroy_hook() {
    echo_step "Setting up user destruction hook..."
    
    # Create custom scripts directory if it doesn't exist
    mkdir -p /usr/local/directadmin/scripts/custom
    
    # Create hook script if it doesn't exist
    if [ ! -f "/usr/local/directadmin/scripts/custom/user_destroy_post.sh" ]; then
        echo_info "Creating user destroy hook script"
        cat > /usr/local/directadmin/scripts/custom/user_destroy_post.sh << 'EOF'
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
        chmod +x /usr/local/directadmin/scripts/custom/user_destroy_post.sh
    fi
    
    # Add Redis cleanup hook if not already present
    if ! grep -q "redis_management" /usr/local/directadmin/scripts/custom/user_destroy_post.sh 2>/dev/null; then
        echo_info "Adding Redis cleanup to user destroy hook"
        echo "" >> /usr/local/directadmin/scripts/custom/user_destroy_post.sh
        echo "# Redis Management Plugin cleanup" >> /usr/local/directadmin/scripts/custom/user_destroy_post.sh
        echo '/usr/local/directadmin/plugins/redis_management/php/Hooks/DirectAdmin/userDestroyPost.php "$username"' >> /usr/local/directadmin/scripts/custom/user_destroy_post.sh
    else
        echo_info "Redis cleanup hook already exists"
    fi
    
    # Make the PHP hook executable
    chmod +x /usr/local/directadmin/plugins/redis_management/php/Hooks/DirectAdmin/userDestroyPost.php
    
    echo_info "User destruction hook configured"
}

# Function to create log rotation
setup_log_rotation() {
    echo_step "Setting up log rotation..."
    
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
        # Reload systemd services to reopen log files
        systemctl reload-or-restart redis-server.service 2>/dev/null || true
        systemctl reload-or-restart redis.service 2>/dev/null || true
    endscript
}

/var/log/directadmin_redis_cleanup.log {
    weekly
    missingok
    rotate 12
    compress
    delaycompress
    notifempty
    create 640 root root
}
EOF
    
    echo_info "Log rotation configured"
}

# Function to run validation
validate_installation() {
    echo_step "Validating installation..."
    
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
    
    # Check Redis service
    if ! systemctl is-enabled redis-server.service >/dev/null 2>&1 && 
       ! systemctl is-enabled redis.service >/dev/null 2>&1; then
        echo_error "Redis service is not enabled"
        ((errors++))
    fi
    
    # Check sudoers file
    if ! visudo -c -f /etc/sudoers.d/redis >/dev/null 2>&1; then
        echo_error "Invalid sudoers configuration"
        ((errors++))
    fi
    
    # Check PHP Redis extension
    if ! php -m 2>/dev/null | grep -q redis; then
        echo_warn "PHP Redis extension not detected (this may cause issues)"
    fi
    
    if [ $errors -eq 0 ]; then
        echo_info "✓ Installation validation passed"
        return 0
    else
        echo_error "Installation validation failed with $errors errors"
        return 1
    fi
}

# Main execution function
main() {
    echo_info "Starting DirectAdmin Redis Management Plugin installation..."
    echo_info "Enhanced version with security improvements and Debian 12 compatibility"
    echo ""
    
    detect_os
    check_root
    check_prerequisites
    
    # OS-specific installations
    if [ "$OS" = "centos" ] || [ "$OS" = "rhel" ]; then
        install_epel
        install_redis_rhel
        install_php_redis
    fi
    
    create_directories
    set_permissions
    configure_systemd
    configure_sudo
    setup_user_destroy_hook
    setup_log_rotation
    
    if validate_installation; then
        echo ""
        echo_info "🎉 Plugin installation completed successfully!"
        echo ""
        echo_info "Installation Summary:"
        echo "  - Plugin files configured and secured"
        echo "  - Redis services configured"
        echo "  - User destruction hook installed"
        echo "  - Log rotation configured"
        echo "  - All validations passed"
        echo ""
        echo_info "Next Steps:"
        echo "  1. The Redis Management plugin is now available in DirectAdmin"
        echo "  2. Users can access it through their control panel"
        echo "  3. Admins can view all instances in the admin section"
        echo "  4. Monitor logs in /usr/local/directadmin/plugins/redis_management/logs/"
        echo ""
        echo_info "For troubleshooting, check the log files and ensure Redis service is running."
    else
        echo ""
        echo_error "❌ Installation completed with errors."
        echo_error "Please review the error messages above and fix any issues."
        echo_info "You can re-run this script after resolving the problems."
        exit 1
    fi
}

# Run main function
main "$@"
