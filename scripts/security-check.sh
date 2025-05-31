#!/bin/bash

# DirectAdmin Redis Management Plugin - Security Verification Script
# This script verifies that all security fixes are properly applied

set -e

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo_pass() {
    echo -e "${GREEN}[PASS]${NC} $1"
}

echo_fail() {
    echo -e "${RED}[FAIL]${NC} $1"
}

echo_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

echo_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

# Global counters
CHECKS=0
PASSED=0
FAILED=0
WARNINGS=0

# Function to check and record result
check_security() {
    local description="$1"
    local test_command="$2"
    local expected_result="$3"  # 0 for success, 1 for failure expected
    
    ((CHECKS++))
    
    if eval "$test_command" >/dev/null 2>&1; then
        local result=0
    else
        local result=1
    fi
    
    if [ "$result" -eq "$expected_result" ]; then
        echo_pass "$description"
        ((PASSED++))
        return 0
    else
        echo_fail "$description"
        ((FAILED++))
        return 1
    fi
}

# Function to check and record warning
check_warning() {
    local description="$1"
    local test_command="$2"
    
    ((CHECKS++))
    
    if eval "$test_command" >/dev/null 2>&1; then
        echo_pass "$description"
        ((PASSED++))
        return 0
    else
        echo_warn "$description"
        ((WARNINGS++))
        return 1
    fi
}

echo_info "DirectAdmin Redis Management Plugin - Security Verification"
echo_info "================================================================"
echo ""

# Check 1: Verify plugin files exist
echo_info "1. Checking Plugin Installation..."
check_security "Plugin directory exists" "[ -d '/usr/local/directadmin/plugins/redis_management' ]" 0
check_security "RedisController.php exists" "[ -f '/usr/local/directadmin/plugins/redis_management/php/Controllers/RedisController.php' ]" 0
check_security "Bootstrap.php exists" "[ -f '/usr/local/directadmin/plugins/redis_management/php/bootstrap.php' ]" 0

# Check 2: Verify security fixes in RedisController
echo ""
echo_info "2. Checking RedisController Security Fixes..."

REDIS_CONTROLLER="/usr/local/directadmin/plugins/redis_management/php/Controllers/RedisController.php"

if [ -f "$REDIS_CONTROLLER" ]; then
    # Check for input validation
    check_security "Username validation function exists" "grep -q '_validateUsername' '$REDIS_CONTROLLER'" 0
    check_security "Input sanitization implemented" "grep -q 'preg_replace.*[^a-zA-Z0-9_-]' '$REDIS_CONTROLLER'" 0
    check_security "Path traversal protection" "grep -q 'strpos.*\.\.' '$REDIS_CONTROLLER'" 0
    
    # Check for secure command execution
    check_security "Secure systemctl execution" "grep -q '_execSystemctl' '$REDIS_CONTROLLER'" 0
    check_security "Command whitelisting" "grep -q 'allowedActions.*=.*array' '$REDIS_CONTROLLER'" 0
    check_security "Shell argument escaping" "grep -q 'escapeshellarg' '$REDIS_CONTROLLER'" 0
    
    # Check for access controls
    check_security "User ownership verification" "grep -q 'canUserDeleteInstance' '$REDIS_CONTROLLER'" 0
    check_security "Timestamp validation" "grep -q '_validateTimestamp' '$REDIS_CONTROLLER'" 0
    
    # Check for logging
    check_security "Security logging implemented" "grep -q '_log.*function' '$REDIS_CONTROLLER'" 0
    
    # Check that old vulnerable _exec method is disabled
    if grep -q "function _exec(" "$REDIS_CONTROLLER"; then
        if grep -q "return false.*security" "$REDIS_CONTROLLER"; then
            echo_pass "Old _exec method properly disabled"
            ((PASSED++))
        else
            echo_fail "Old _exec method still active (security risk)"
            ((FAILED++))
        fi
        ((CHECKS++))
    else
        echo_pass "Old _exec method removed"
        ((PASSED++))
        ((CHECKS++))
    fi
else
    echo_fail "RedisController.php not found"
    ((FAILED++))
    ((CHECKS++))
fi

# Check 3: File Permissions
echo ""
echo_info "3. Checking File Permissions..."

check_security "Plugin directory permissions" "[ \$(stat -c '%a' /usr/local/directadmin/plugins/redis_management) = '755' ]" 0
check_security "Data directory permissions" "[ \$(stat -c '%a' /usr/local/directadmin/plugins/redis_management/data) = '750' ]" 0
check_security "Logs directory permissions" "[ \$(stat -c '%a' /usr/local/directadmin/plugins/redis_management/logs) = '750' ]" 0

# Check data directory ownership
if [ -d "/usr/local/directadmin/plugins/redis_management/data" ]; then
    OWNER=$(stat -c '%U:%G' /usr/local/directadmin/plugins/redis_management/data)
    if [ "$OWNER" = "redis:redis" ]; then
        echo_pass "Data directory owned by redis:redis"
        ((PASSED++))
    else
        echo_fail "Data directory ownership incorrect: $OWNER (should be redis:redis)"
        ((FAILED++))
    fi
    ((CHECKS++))
fi

# Check local.php permissions if it exists
if [ -f "/usr/local/directadmin/plugins/redis_management/php/Config/local.php" ]; then
    PERMS=$(stat -c '%a' /usr/local/directadmin/plugins/redis_management/php/Config/local.php)
    if [ "$PERMS" = "640" ]; then
        echo_pass "local.php permissions are secure (640)"
        ((PASSED++))
    else
        echo_fail "local.php permissions insecure: $PERMS (should be 640)"
        ((FAILED++))
    fi
    ((CHECKS++))
fi

# Check 4: Sudo Configuration
echo ""
echo_info "4. Checking Sudo Configuration..."

SUDOERS_FILE="/etc/sudoers.d/redis"
if [ -f "$SUDOERS_FILE" ]; then
    echo_pass "Redis sudoers file exists"
    ((PASSED++))
    
    # Check file permissions
    PERMS=$(stat -c '%a' "$SUDOERS_FILE")
    if [ "$PERMS" = "440" ]; then
        echo_pass "Sudoers file permissions correct (440)"
        ((PASSED++))
    else
        echo_fail "Sudoers file permissions incorrect: $PERMS (should be 440)"
        ((FAILED++))
    fi
    
    # Check for regex patterns (security improvement)
    if grep -q '\[a-zA-Z0-9_\]' "$SUDOERS_FILE"; then
        echo_pass "Sudoers uses secure regex patterns"
        ((PASSED++))
    else
        echo_warn "Sudoers may use unsafe wildcards"
        ((WARNINGS++))
    fi
    
    # Validate sudoers syntax
    if visudo -c -f "$SUDOERS_FILE" >/dev/null 2>&1; then
        echo_pass "Sudoers file syntax is valid"
        ((PASSED++))
    else
        echo_fail "Sudoers file has syntax errors"
        ((FAILED++))
    fi
    
    ((CHECKS+=4))
else
    echo_fail "Redis sudoers file missing"
    ((FAILED++))
    ((CHECKS++))
fi

# Check 5: SystemD Configuration
echo ""
echo_info "5. Checking SystemD Configuration..."

# Check for service files
if [ -f "/lib/systemd/system/redis-server.service" ] || [ -f "/usr/lib/systemd/system/redis-server.service" ]; then
    echo_pass "Redis main service file exists"
    ((PASSED++))
else
    echo_fail "Redis main service file missing"
    ((FAILED++))
fi
((CHECKS++))

if [ -f "/lib/systemd/system/redis-server@.service" ] || [ -f "/usr/lib/systemd/system/redis-server@.service" ]; then
    echo_pass "Redis instance template service exists"
    ((PASSED++))
    
    # Check for security settings in service file
    SERVICE_FILE=""
    if [ -f "/lib/systemd/system/redis-server@.service" ]; then
        SERVICE_FILE="/lib/systemd/system/redis-server@.service"
    elif [ -f "/usr/lib/systemd/system/redis-server@.service" ]; then
        SERVICE_FILE="/usr/lib/systemd/system/redis-server@.service"
    fi
    
    if [ -n "$SERVICE_FILE" ]; then
        if grep -q "NoNewPrivileges=yes" "$SERVICE_FILE"; then
            echo_pass "Service has NoNewPrivileges security setting"
            ((PASSED++))
        else
            echo_warn "Service missing NoNewPrivileges setting"
            ((WARNINGS++))
        fi
        
        if grep -q "PrivateTmp=yes" "$SERVICE_FILE"; then
            echo_pass "Service has PrivateTmp security setting"
            ((PASSED++))
        else
            echo_warn "Service missing PrivateTmp setting"
            ((WARNINGS++))
        fi
        
        if grep -q "ProtectSystem=" "$SERVICE_FILE"; then
            echo_pass "Service has ProtectSystem security setting"
            ((PASSED++))
        else
            echo_warn "Service missing ProtectSystem setting"
            ((WARNINGS++))
        fi
        
        ((CHECKS+=3))
    fi
else
    echo_fail "Redis instance template service missing"
    ((FAILED++))
fi
((CHECKS++))

# Check if main Redis service is enabled
if systemctl is-enabled redis-server.service >/dev/null 2>&1 || systemctl is-enabled redis.service >/dev/null 2>&1; then
    echo_pass "Redis main service is enabled"
    ((PASSED++))
else
    echo_fail "Redis main service is not enabled"
    ((FAILED++))
fi
((CHECKS++))

# Check 6: Redis Configuration Security
echo ""
echo_info "6. Checking Redis Configuration Security..."

REDIS_CONF="/etc/redis/redis.conf"
if [ -f "$REDIS_CONF" ]; then
    echo_pass "Redis configuration file exists"
    ((PASSED++))
    
    # Check for security settings
    if grep -q "^port 0" "$REDIS_CONF"; then
        echo_pass "TCP port disabled (port 0)"
        ((PASSED++))
    else
        echo_warn "TCP port may be enabled (security risk)"
        ((WARNINGS++))
    fi
    
    if grep -q "^protected-mode yes" "$REDIS_CONF"; then
        echo_pass "Protected mode enabled"
        ((PASSED++))
    else
        echo_warn "Protected mode not explicitly enabled"
        ((WARNINGS++))
    fi
    
    if grep -q "^bind 127.0.0.1" "$REDIS_CONF"; then
        echo_pass "Redis bound to localhost"
        ((PASSED++))
    else
        echo_warn "Redis binding configuration may be insecure"
        ((WARNINGS++))
    fi
    
    ((CHECKS+=4))
else
    echo_warn "Redis configuration file not found"
    ((WARNINGS++))
    ((CHECKS++))
fi

# Check instances directory
if [ -d "/etc/redis/instances" ]; then
    echo_pass "Redis instances directory exists"
    ((PASSED++))
    
    OWNER=$(stat -c '%U:%G' /etc/redis/instances)
    if [ "$OWNER" = "redis:redis" ]; then
        echo_pass "Instances directory owned by redis:redis"
        ((PASSED++))
    else
        echo_warn "Instances directory ownership: $OWNER (should be redis:redis)"
        ((WARNINGS++))
    fi
    
    ((CHECKS+=2))
else
    echo_fail "Redis instances directory missing"
    ((FAILED++))
    ((CHECKS++))
fi

# Check 7: DirectAdmin Integration
echo ""
echo_info "7. Checking DirectAdmin Integration..."

# Check plugin.conf
PLUGIN_CONF="/usr/local/directadmin/plugins/redis_management/plugin.conf"
if [ -f "$PLUGIN_CONF" ]; then
    echo_pass "Plugin configuration exists"
    ((PASSED++))
    
    if grep -q "version=3\." "$PLUGIN_CONF"; then
        echo_pass "Plugin version 3.x detected (security enhanced)"
        ((PASSED++))
    else
        echo_warn "Plugin version may be outdated"
        ((WARNINGS++))
    fi
    
    if grep -q "security_enhanced=yes" "$PLUGIN_CONF"; then
        echo_pass "Security enhanced flag detected"
        ((PASSED++))
    else
        echo_warn "Security enhanced flag missing"
        ((WARNINGS++))
    fi
    
    ((CHECKS+=3))
else
    echo_fail "Plugin configuration missing"
    ((FAILED++))
    ((CHECKS++))
fi

# Check user destroy hook
if [ -f "/usr/local/directadmin/scripts/custom/user_destroy_post.sh" ]; then
    if grep -q "redis_management" /usr/local/directadmin/scripts/custom/user_destroy_post.sh; then
        echo_pass "User destruction hook properly configured"
        ((PASSED++))
    else
        echo_warn "User destruction hook not configured"
        ((WARNINGS++))
    fi
else
    echo_warn "User destruction hook script missing"
    ((WARNINGS++))
fi
((CHECKS++))

# Check 8: Log Files and Monitoring
echo ""
echo_info "8. Checking Logging and Monitoring..."

# Check log directory
if [ -d "/usr/local/directadmin/plugins/redis_management/logs" ]; then
    echo_pass "Plugin logs directory exists"
    ((PASSED++))
else
    echo_fail "Plugin logs directory missing"
    ((FAILED++))
fi
((CHECKS++))

# Check logrotate configuration
if [ -f "/etc/logrotate.d/redis-management" ]; then
    echo_pass "Log rotation configured"
    ((PASSED++))
else
    echo_warn "Log rotation not configured"
    ((WARNINGS++))
fi
((CHECKS++))

# Check 9: PHP Security
echo ""
echo_info "9. Checking PHP Security..."

# Check PHP version
if command -v php >/dev/null 2>&1; then
    PHP_VERSION=$(php -r "echo PHP_VERSION_ID;")
    if [ "$PHP_VERSION" -ge "70400" ]; then
        echo_pass "PHP version is 7.4+ (secure)"
        ((PASSED++))
    else
        echo_fail "PHP version is outdated (security risk)"
        ((FAILED++))
    fi
    
    # Check PHP Redis extension
    if php -m 2>/dev/null | grep -q redis; then
        echo_pass "PHP Redis extension installed"
        ((PASSED++))
    else
        echo_warn "PHP Redis extension not detected"
        ((WARNINGS++))
    fi
    
    ((CHECKS+=2))
else
    echo_warn "PHP not found in PATH"
    ((WARNINGS++))
    ((CHECKS++))
fi

# Check 10: Network Security
echo ""
echo_info "10. Checking Network Security..."

# Check for listening Redis TCP ports (should be none)
if netstat -ln 2>/dev/null | grep -q ":6379"; then
    echo_fail "Redis TCP port 6379 is listening (security risk)"
    ((FAILED++))
else
    echo_pass "No Redis TCP ports listening"
    ((PASSED++))
fi
((CHECKS++))

# Check for Redis Unix sockets
SOCKET_COUNT=$(find /home/*/tmp -name "redis.sock" 2>/dev/null | wc -l)
if [ "$SOCKET_COUNT" -gt 0 ]; then
    echo_pass "Found $SOCKET_COUNT Redis Unix socket(s)"
    ((PASSED++))
else
    echo_info "No Redis Unix sockets found (may be normal if no instances created)"
    ((PASSED++))
fi
((CHECKS++))

# Generate Report
echo ""
echo_info "================================================================"
echo_info "                    SECURITY VERIFICATION REPORT"
echo_info "================================================================"
echo ""

if [ $FAILED -eq 0 ]; then
    if [ $WARNINGS -eq 0 ]; then
        echo_pass "🛡️  SECURITY STATUS: EXCELLENT"
        echo_pass "All security checks passed! Your installation is secure."
    else
        echo_warn "🛡️  SECURITY STATUS: GOOD"
        echo_warn "All critical checks passed, but some recommendations should be addressed."
    fi
else
    echo_fail "🛡️  SECURITY STATUS: NEEDS ATTENTION"
    echo_fail "Critical security issues detected that should be resolved immediately."
fi

echo ""
echo_info "Check Summary:"
echo_info "  Total Checks: $CHECKS"
echo_pass "  Passed: $PASSED"
if [ $WARNINGS -gt 0 ]; then
    echo_warn "  Warnings: $WARNINGS"
fi
if [ $FAILED -gt 0 ]; then
    echo_fail "  Failed: $FAILED"
fi

echo ""
if [ $FAILED -gt 0 ]; then
    echo_fail "❌ Please address the failed security checks before using the plugin in production."
    exit 1
elif [ $WARNINGS -gt 0 ]; then
    echo_warn "⚠️  Consider addressing the warnings to improve security posture."
    exit 2
else
    echo_pass "✅ All security checks passed! The plugin is ready for production use."
    exit 0
fi
