#!/bin/bash

# DirectAdmin Redis Management Plugin - Debian Setup Script
# Compatible with Debian 11+ and Ubuntu 20.04+
# Security and compatibility fixes applied

set -e  # Exit on any error

# Color output for better readability
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
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

# Function to detect OS
detect_os() {
    if [ -f /etc/debian_version ]; then
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
        echo_error "This script only supports Debian and Ubuntu systems"
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

# Function to backup existing configuration
backup_config() {
    local config_file="$1"
    if [ -f "$config_file" ]; then
        local backup_file="${config_file}.backup.$(date +%Y%m%d_%H%M%S)"
        echo_info "Backing up $config_file to $backup_file"
        cp "$config_file" "$backup_file"
    fi
}

# Function to install Redis
install_redis() {
    echo_info "Updating package lists..."
    apt-get update

    echo_info "Installing Redis server and tools..."
    apt-get install -y redis-server redis-tools

    # Verify installation
    if ! command -v redis-server &> /dev/null; then
        echo_error "Redis installation failed"
        exit 1
    fi
    
    echo_info "Redis installed successfully"
}

# Function to configure systemd
configure_systemd() {
    echo_info "Configuring systemd services..."
    
    # Stop and disable default Redis service
    systemctl stop redis-server.service || true
    systemctl disable redis-server.service || true

    # Determine correct systemd directory
    SYSTEMD_DIR="/lib/systemd/system"
    if [ -d "/usr/lib/systemd/system" ] && [ ! -L "/lib/systemd/system" ]; then
        SYSTEMD_DIR="/usr/lib/systemd/system"
    fi
    
    echo_info "Using systemd directory: $SYSTEMD_DIR"

    # Backup existing service files
    backup_config "$SYSTEMD_DIR/redis-server.service"
    backup_config "$SYSTEMD_DIR/redis-server@.service"

    # Remove existing systemd scripts
    rm -f "$SYSTEMD_DIR/redis-server.service"
    rm -f "$SYSTEMD_DIR/redis-server@.service"

    # Copy new systemd scripts
    if [ ! -f "redis-server.service" ] || [ ! -f "redis-server@.service" ]; then
        echo_error "Required systemd service files not found in current directory"
        exit 1
    fi
    
    cp redis-server.service "$SYSTEMD_DIR/"
    cp redis-server@.service "$SYSTEMD_DIR/"

    # Set proper permissions
    chmod 644 "$SYSTEMD_DIR/redis-server.service"
    chmod 644 "$SYSTEMD_DIR/redis-server@.service"

    # Reload systemd daemon
    systemctl daemon-reload

    # Enable main service
    systemctl enable redis-server.service

    # Start main service
    systemctl start redis-server.service
    
    echo_info "Systemd configuration completed"
}

# Function to configure Redis
configure_redis() {
    echo_info "Configuring Redis..."
    
    # Ensure proper permissions on Redis configuration directory
    chmod -R 0755 /etc/redis
    
    # Backup original config
    backup_config "/etc/redis/redis.conf"
    
    # Copy our configuration
    if [ ! -f "redis.conf" ]; then
        echo_error "redis.conf not found in current directory"
        exit 1
    fi
    
    cp redis.conf /etc/redis/redis.conf
    chmod 644 /etc/redis/redis.conf
    chown redis:redis /etc/redis/redis.conf

    # Create instances folder for redis instances
    mkdir -p /etc/redis/instances
    chown -R redis:redis /etc/redis/instances
    chmod 755 /etc/redis/instances
    
    echo_info "Redis configuration completed"
}

# Function to configure sudo permissions
configure_sudo() {
    echo_info "Configuring sudo permissions..."
    
    # Backup existing sudoers file if it exists
    backup_config "/etc/sudoers.d/redis"
    
    # Copy sudoers file
    if [ ! -f "redis.sudoers" ]; then
        echo_error "redis.sudoers not found in current directory"
        exit 1
    fi
    
    cp redis.sudoers /etc/sudoers.d/redis
    
    # Fix sudoers file permissions (must be 440)
    chown root:root /etc/sudoers.d/redis
    chmod 440 /etc/sudoers.d/redis
    
    # Validate sudoers file
    if ! visudo -c -f /etc/sudoers.d/redis; then
        echo_error "Invalid sudoers configuration"
        rm -f /etc/sudoers.d/redis
        exit 1
    fi
    
    echo_info "Sudo configuration completed"
}

# Function to verify installation
verify_installation() {
    echo_info "Verifying installation..."
    
    # Check if Redis service is running
    if systemctl is-active --quiet redis-server.service; then
        echo_info "✓ Redis main service is running"
    else
        echo_warn "Redis main service is not running"
    fi
    
    # Check if Redis is responding
    if redis-cli ping > /dev/null 2>&1; then
        echo_info "✓ Redis is responding to commands"
    else
        echo_warn "Redis is not responding (this may be normal if using socket-only configuration)"
    fi
    
    # Check required directories
    local dirs=("/etc/redis/instances" "/var/log/redis")
    for dir in "${dirs[@]}"; do
        if [ -d "$dir" ]; then
            echo_info "✓ Directory $dir exists"
        else
            echo_warn "Directory $dir does not exist"
        fi
    done
    
    # Check sudoers configuration
    if [ -f "/etc/sudoers.d/redis" ]; then
        echo_info "✓ Sudoers configuration installed"
    else
        echo_warn "Sudoers configuration missing"
    fi
    
    echo_info "Installation verification completed"
}

# Main execution
main() {
    echo_info "Starting DirectAdmin Redis Management Plugin setup for Debian/Ubuntu..."
    
    detect_os
    check_root
    
    # Ensure we're in the setup directory
    if [ ! -f "redis.conf" ] || [ ! -f "redis.sudoers" ]; then
        echo_error "Setup files not found. Please run this script from the setup directory."
        exit 1
    fi
    
    install_redis
    configure_redis
    configure_systemd
    configure_sudo
    verify_installation
    
    echo_info "Setup completed successfully!"
    echo_info "You can now run the plugin installation script from the scripts directory."
    
    # Show next steps
    echo ""
    echo_info "Next steps:"
    echo "1. cd ../scripts"
    echo "2. ./debian.sh"
    echo ""
}

# Run main function
main "$@"
