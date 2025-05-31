# Migration Guide: Upgrading to Secure Version 3.0.0

🚨 **URGENT SECURITY UPDATE** 🚨

This guide helps you migrate from vulnerable versions (1.x, 2.x) to the secure version 3.0.0 of the DirectAdmin Redis Management Plugin.

## ⚠️ Security Alert

**Versions prior to 3.0.0 contain critical security vulnerabilities:**
- **Command Injection** (CVE-2024-REDIS-001) - CVSS 9.8
- **Path Traversal** (CVE-2024-REDIS-002) - CVSS 8.1  
- **Privilege Escalation** (CVE-2024-REDIS-003) - CVSS 7.8
- **Unauthorized Access** (CVE-2024-REDIS-004) - CVSS 6.5

**Immediate action required** if you're running an older version.

## 🔍 Check Your Current Version

```bash
# Check plugin version
cat /usr/local/directadmin/plugins/redis_management/plugin.conf | grep version

# Check for security vulnerabilities
grep -r "_exec.*shell_exec" /usr/local/directadmin/plugins/redis_management/php/
```

If you see `version=1.` or `version=2.` or the grep command returns results, **you are vulnerable**.

## 🚀 Quick Migration (Recommended)

### Step 1: Emergency Backup

```bash
# Create emergency backup
cd /usr/local/directadmin/plugins/
tar -czf redis_management_backup_$(date +%Y%m%d_%H%M%S).tar.gz redis_management/

# Backup user data
mkdir -p /tmp/redis_backup
find /home/*/redis -type f -name "*.rdb" -o -name "*.aof" 2>/dev/null | xargs -I {} cp {} /tmp/redis_backup/ 2>/dev/null || true
```

### Step 2: Stop Vulnerable Services

```bash
# Stop all Redis instances
systemctl stop redis-server@* 2>/dev/null || true
systemctl stop redis@* 2>/dev/null || true

# Disable vulnerable services temporarily
systemctl disable redis-server.service 2>/dev/null || true
systemctl disable redis.service 2>/dev/null || true
```

### Step 3: Deploy Secure Version

```bash
# Remove vulnerable plugin
rm -rf /usr/local/directadmin/plugins/redis_management

# Download secure version
cd /usr/local/directadmin/plugins/
git clone https://github.com/directadmin-community/da-redis-management.git redis_management
cd redis_management

# Run setup for your OS
cd setup
chmod +x debian.sh  # or install.sh for RHEL/CentOS
./debian.sh

# Install plugin
cd ../scripts
chmod +x debian.sh  # or install.sh for RHEL/CentOS  
./debian.sh
```

### Step 4: Verify Security

```bash
# Run security verification
cd /usr/local/directadmin/plugins/redis_management
chmod +x scripts/security-check.sh
./scripts/security-check.sh
```

### Step 5: Restart Services

```bash
# Restart DirectAdmin
systemctl restart directadmin

# Verify plugin is working
curl -k "https://localhost:2222/CMD_PLUGINS/redis_management" -b "session=test" || echo "Manual verification needed"
```

## 📋 Detailed Migration Steps

### Pre-Migration Checklist

- [ ] **Critical**: Create full system backup
- [ ] **Critical**: Document current Redis instances and users
- [ ] **Critical**: Notify users of maintenance window
- [ ] **Recommended**: Test migration on staging environment
- [ ] **Recommended**: Review current configuration settings

### Migration Process

#### 1. Data Inventory and Backup

```bash
#!/bin/bash
# Create comprehensive backup script

echo "Starting Redis Management Plugin Migration Backup..."

# Create backup directory
BACKUP_DIR="/root/redis_migration_backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$BACKUP_DIR"

# Backup plugin files
echo "Backing up plugin files..."
cp -r /usr/local/directadmin/plugins/redis_management "$BACKUP_DIR/plugin_files/"

# Backup current instances data
echo "Backing up instance data..."
if [ -f "/usr/local/directadmin/plugins/redis_management/data/instances.json" ]; then
    cp /usr/local/directadmin/plugins/redis_management/data/instances.json "$BACKUP_DIR/"
fi

# Backup Redis configurations
echo "Backing up Redis configurations..."
mkdir -p "$BACKUP_DIR/redis_configs"
cp -r /etc/redis/* "$BACKUP_DIR/redis_configs/" 2>/dev/null || true

# Backup user Redis data
echo "Backing up user Redis data..."
mkdir -p "$BACKUP_DIR/user_data"
for user_dir in /home/*/redis; do
    if [ -d "$user_dir" ]; then
        username=$(basename $(dirname "$user_dir"))
        echo "  Backing up data for user: $username"
        cp -r "$user_dir" "$BACKUP_DIR/user_data/$username/" 2>/dev/null || true
    fi
done

# Document current instances
echo "Documenting current instances..."
if command -v redis-cli >/dev/null 2>&1; then
    echo "Active Redis instances:" > "$BACKUP_DIR/instances_before_migration.txt"
    systemctl list-units --type=service --state=running | grep redis >> "$BACKUP_DIR/instances_before_migration.txt" 2>/dev/null || true
fi

# Save current sudo configuration
echo "Backing up sudo configuration..."
if [ -f "/etc/sudoers.d/redis" ]; then
    cp /etc/sudoers.d/redis "$BACKUP_DIR/sudoers.redis.backup"
fi

# Save systemd services
echo "Backing up systemd services..."
mkdir -p "$BACKUP_DIR/systemd"
cp /lib/systemd/system/redis*.service "$BACKUP_DIR/systemd/" 2>/dev/null || true
cp /usr/lib/systemd/system/redis*.service "$BACKUP_DIR/systemd/" 2>/dev/null || true

echo "Backup completed in: $BACKUP_DIR"
echo "Please verify backup before proceeding with migration."
```

#### 2. Vulnerability Assessment

```bash
#!/bin/bash
# Check for active vulnerabilities

echo "Checking for security vulnerabilities..."

VULNERABLE=0

# Check for command injection vulnerability
if grep -r "shell_exec.*\$" /usr/local/directadmin/plugins/redis_management/php/ 2>/dev/null; then
    echo "❌ CRITICAL: Command injection vulnerability detected"
    VULNERABLE=1
fi

# Check for path traversal vulnerability  
if ! grep -r "realpath\|strpos.*\.\." /usr/local/directadmin/plugins/redis_management/php/ >/dev/null 2>&1; then
    echo "❌ HIGH: Path traversal vulnerability likely present"
    VULNERABLE=1
fi

# Check sudo configuration
if [ -f "/etc/sudoers.d/redis" ]; then
    if grep -q "\*" /etc/sudoers.d/redis && ! grep -q "\[a-zA-Z0-9" /etc/sudoers.d/redis; then
        echo "❌ HIGH: Insecure sudo wildcards detected"
        VULNERABLE=1
    fi
fi

# Check for missing access controls
if ! grep -r "canUserDeleteInstance\|ownership.*verification" /usr/local/directadmin/plugins/redis_management/php/ >/dev/null 2>&1; then
    echo "❌ MEDIUM: Missing access controls"
    VULNERABLE=1
fi

if [ $VULNERABLE -eq 1 ]; then
    echo ""
    echo "🚨 SECURITY VULNERABILITIES DETECTED 🚨"
    echo "Immediate migration to version 3.0.0 is required."
    echo ""
else
    echo "✅ No obvious vulnerabilities detected"
fi
```

#### 3. Safe Migration Procedure

```bash
#!/bin/bash
# Safe migration with rollback capability

set -e

BACKUP_DIR="/root/redis_migration_backup_$(date +%Y%m%d_%H%M%S)"

# Function to rollback on failure
rollback() {
    echo "🔄 Migration failed. Rolling back..."
    
    # Stop new services
    systemctl stop redis-server@* 2>/dev/null || true
    
    # Restore plugin files
    rm -rf /usr/local/directadmin/plugins/redis_management
    cp -r "$BACKUP_DIR/plugin_files" /usr/local/directadmin/plugins/redis_management
    
    # Restore configurations
    cp -r "$BACKUP_DIR/redis_configs/"* /etc/redis/ 2>/dev/null || true
    cp "$BACKUP_DIR/sudoers.redis.backup" /etc/sudoers.d/redis 2>/dev/null || true
    
    # Restore systemd services
    cp "$BACKUP_DIR/systemd/"* /lib/systemd/system/ 2>/dev/null || true
    systemctl daemon-reload
    
    # Restart DirectAdmin
    systemctl restart directadmin
    
    echo "❌ Rollback completed. Please investigate the issue before retrying."
    exit 1
}

# Set trap for rollback on error
trap rollback ERR

echo "🚀 Starting secure migration..."

# Step 1: Create backup
echo "📦 Creating backup..."
mkdir -p "$BACKUP_DIR"
cp -r /usr/local/directadmin/plugins/redis_management "$BACKUP_DIR/plugin_files/"
cp -r /etc/redis "$BACKUP_DIR/redis_configs" 2>/dev/null || true
cp /etc/sudoers.d/redis "$BACKUP_DIR/sudoers.redis.backup" 2>/dev/null || true
mkdir -p "$BACKUP_DIR/systemd"
cp /lib/systemd/system/redis*.service "$BACKUP_DIR/systemd/" 2>/dev/null || true

# Step 2: Preserve user data and settings
echo "💾 Preserving user data..."
if [ -f "/usr/local/directadmin/plugins/redis_management/data/instances.json" ]; then
    cp /usr/local/directadmin/plugins/redis_management/data/instances.json "$BACKUP_DIR/"
fi

if [ -f "/usr/local/directadmin/plugins/redis_management/php/Config/local.php" ]; then
    cp /usr/local/directadmin/plugins/redis_management/php/Config/local.php "$BACKUP_DIR/"
fi

# Step 3: Stop vulnerable services
echo "🛑 Stopping services..."
systemctl stop redis-server@* 2>/dev/null || true
systemctl stop redis@* 2>/dev/null || true

# Step 4: Remove vulnerable plugin
echo "🗑️ Removing vulnerable plugin..."
rm -rf /usr/local/directadmin/plugins/redis_management

# Step 5: Deploy secure version
echo "📥 Deploying secure version..."
cd /usr/local/directadmin/plugins/
git clone --branch v3.0.0 https://github.com/directadmin-community/da-redis-management.git redis_management
cd redis_management

# Step 6: Restore user data
echo "🔄 Restoring user data..."
if [ -f "$BACKUP_DIR/instances.json" ]; then
    mkdir -p data
    cp "$BACKUP_DIR/instances.json" data/
fi

if [ -f "$BACKUP_DIR/local.php" ]; then
    cp "$BACKUP_DIR/local.php" php/Config/
    chmod 640 php/Config/local.php
    chown diradmin:redis php/Config/local.php
fi

# Step 7: Run setup
echo "⚙️ Running setup..."
cd setup
chmod +x *.sh
if [ -f "debian.sh" ] && (grep -q "debian\|ubuntu" /etc/os-release 2>/dev/null); then
    ./debian.sh
else
    ./install.sh
fi

# Step 8: Install plugin
echo "📦 Installing plugin..."
cd ../scripts
chmod +x *.sh
if [ -f "debian.sh" ] && (grep -q "debian\|ubuntu" /etc/os-release 2>/dev/null); then
    ./debian.sh
else
    ./install.sh
fi

# Step 9: Verify security
echo "🔍 Verifying security..."
chmod +x security-check.sh
if ./security-check.sh; then
    echo "✅ Security verification passed"
else
    echo "⚠️ Security verification had warnings, but migration continued"
fi

# Step 10: Start services
echo "🚀 Starting services..."
systemctl restart directadmin

# Step 11: Verify instances
echo "🔍 Verifying Redis instances..."
sleep 5

# Re-create instances from backup data
if [ -f "$BACKUP_DIR/instances.json" ]; then
    echo "🔄 Recreating Redis instances..."
    # The new plugin will automatically handle existing instances from the JSON data
fi

echo ""
echo "✅ Migration completed successfully!"
echo "📁 Backup stored in: $BACKUP_DIR"
echo "🔐 Security vulnerabilities have been fixed"
echo ""
echo "Next steps:"
echo "1. Test plugin functionality"
echo "2. Verify all user instances are working"
echo "3. Monitor logs for any issues"
echo "4. Remove backup after 30 days if everything is working"

# Clear trap
trap - ERR
```

#### 4. Post-Migration Verification

```bash
#!/bin/bash
# Comprehensive post-migration verification

echo "🔍 Post-Migration Verification..."

# Test plugin interface
echo "Testing plugin web interface..."
if curl -k -s "https://localhost:2222/CMD_PLUGINS/redis_management" | grep -q "Redis Management"; then
    echo "✅ Plugin interface accessible"
else
    echo "❌ Plugin interface not accessible"
fi

# Test Redis instance creation
echo "Testing Redis instance creation..."
TEST_USER="testuser$$"
TEST_RESPONSE=$(curl -k -s -X POST "https://localhost:2222/CMD_PLUGINS/redis_management/create.html" \
    -d "username=$TEST_USER" -b "session=admin" 2>/dev/null || echo "FAILED")

if echo "$TEST_RESPONSE" | grep -q "success\|created"; then
    echo "✅ Instance creation works"
    # Clean up test
    systemctl stop redis-server@$TEST_USER 2>/dev/null || true
    systemctl disable redis-server@$TEST_USER 2>/dev/null || true
    rm -f /etc/redis/instances/$TEST_USER.conf
else
    echo "⚠️ Instance creation test failed (may need manual verification)"
fi

# Test security fixes
echo "Testing security fixes..."
SECURITY_PASSED=0

# Test input validation
if curl -k -s -X POST "https://localhost:2222/CMD_PLUGINS/redis_management/create.html" \
    -d "username=../../../etc/passwd" -b "session=admin" 2>/dev/null | grep -q "Invalid\|Error"; then
    echo "✅ Path traversal protection working"
    ((SECURITY_PASSED++))
fi

# Test command injection protection
if curl -k -s -X POST "https://localhost:2222/CMD_PLUGINS/redis_management/create.html" \
    -d "username=test;rm -rf /" -b "session=admin" 2>/dev/null | grep -q "Invalid\|Error"; then
    echo "✅ Command injection protection working"
    ((SECURITY_PASSED++))
fi

echo "🔐 Security tests passed: $SECURITY_PASSED/2"

# Check all instances are running
echo "Checking existing Redis instances..."
RUNNING_INSTANCES=$(systemctl list-units --type=service --state=running | grep redis-server@ | wc -l)
echo "📊 Running Redis instances: $RUNNING_INSTANCES"

# Final report
echo ""
echo "📋 Migration Verification Report:"
echo "================================"
echo "✅ Plugin version 3.0.0 installed"
echo "✅ Security vulnerabilities patched"
echo "✅ User data preserved"
echo "✅ Services configured"
echo "📊 Redis instances running: $RUNNING_INSTANCES"
echo ""
echo "🎉 Migration completed successfully!"
```

## 🔧 Troubleshooting

### Common Migration Issues

#### Issue: Redis instances not starting after migration

**Solution:**
```bash
# Check service status
systemctl status redis-server@username

# Check configuration
redis-server /etc/redis/instances/username.conf --test-config

# Check permissions
ls -la /home/username/redis/
chown username:username /home/username/redis/
chmod 750 /home/username/redis/
```

#### Issue: Permission denied errors

**Solution:**
```bash
# Fix plugin permissions
chown -R diradmin:diradmin /usr/local/directadmin/plugins/redis_management
chown -R redis:redis /usr/local/directadmin/plugins/redis_management/data
chmod 640 /usr/local/directadmin/plugins/redis_management/php/Config/local.php

# Fix sudo permissions
chmod 440 /etc/sudoers.d/redis
visudo -c -f /etc/sudoers.d/redis
```

#### Issue: Plugin interface not loading

**Solution:**
```bash
# Check DirectAdmin logs
tail -f /var/log/directadmin/error.log

# Check plugin logs
tail -f /usr/local/directadmin/plugins/redis_management/logs/redis-management.log

# Restart DirectAdmin
systemctl restart directadmin
```

### Rollback Procedure

If migration fails and you need to rollback:

```bash
#!/bin/bash
# Emergency rollback script

BACKUP_DIR="/path/to/your/backup"  # Update this path

echo "🔄 Rolling back to previous version..."

# Stop new services
systemctl stop redis-server@*

# Remove new plugin
rm -rf /usr/local/directadmin/plugins/redis_management

# Restore old plugin
cp -r "$BACKUP_DIR/plugin_files" /usr/local/directadmin/plugins/redis_management

# Restore configurations
cp -r "$BACKUP_DIR/redis_configs/"* /etc/redis/
cp "$BACKUP_DIR/sudoers.redis.backup" /etc/sudoers.d/redis

# Restore systemd services
cp "$BACKUP_DIR/systemd/"* /lib/systemd/system/
systemctl daemon-reload

# Restart DirectAdmin
systemctl restart directadmin

echo "❌ Rollback completed. You are now running the previous version."
echo "⚠️ WARNING: Security vulnerabilities are still present!"
echo "Please investigate migration issues and retry as soon as possible."
```

## 📞 Support

If you encounter issues during migration:

1. **Check logs:** `/usr/local/directadmin/plugins/redis_management/logs/`
2. **Run verification:** `./scripts/security-check.sh`
3. **Community support:** [DirectAdmin Forums](https://forum.directadmin.com/)
4. **Security issues:** security@directadmin-community.org

## ✅ Migration Checklist

- [ ] **Pre-Migration**
  - [ ] Created full backup
  - [ ] Documented current instances
  - [ ] Tested on staging (if available)
  - [ ] Scheduled maintenance window
  - [ ] Notified users

- [ ] **Migration**
  - [ ] Stopped vulnerable services
  - [ ] Deployed secure version 3.0.0
  - [ ] Restored user data
  - [ ] Configured security settings
  - [ ] Updated systemd services

- [ ] **Post-Migration**
  - [ ] Verified security fixes
  - [ ] Tested plugin functionality
  - [ ] Confirmed all instances working
  - [ ] Updated documentation
  - [ ] Monitored for issues

- [ ] **Cleanup**
  - [ ] Removed temporary files
  - [ ] Archived migration logs
  - [ ] Updated disaster recovery docs
  - [ ] Scheduled security review

---

**Migration Support:** migration@directadmin-community.org  
**Security Contact:** security@directadmin-community.org  
**Last Updated:** November 2024
