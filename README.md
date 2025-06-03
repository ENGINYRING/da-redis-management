# WORK IN PROGRESS! DO NOT USE IN PRODUCTION

## DirectAdmin Redis Management Plugin

🚀 **Enhanced Security & Debian 12 Compatible** 🚀

A secure, feature-rich DirectAdmin plugin for managing Redis instances with comprehensive security enhancements, Debian 12 compatibility, and modern best practices.

## 🌟 Features

### Core Functionality
- ✅ **Per-user Redis instances** with isolated configurations
- ✅ **Unix socket communication** for enhanced security
- ✅ **Automatic service management** via systemd
- ✅ **User-friendly web interface** with Bootstrap 5
- ✅ **Admin overview** of all Redis instances
- ✅ **Automatic cleanup** when users are deleted

### Security Enhancements
- 🔒 **Input validation and sanitization** - Prevents injection attacks
- 🔒 **Path traversal protection** - Secure file operations
- 🔒 **Command injection prevention** - Safe system command execution
- 🔒 **CSRF protection** - Secure form submissions
- 🔒 **Access control verification** - User ownership validation
- 🔒 **Disabled dangerous Redis commands** - Prevents system abuse
- 🔒 **Secure sudo configuration** - Minimal required permissions

### System Compatibility
- 🐧 **Debian 12** (Bookworm) - Full support
- 🐧 **Ubuntu 20.04+** - Tested and compatible
- 🐧 **CentOS/RHEL 7+** - Legacy support maintained
- ⚙️ **SystemD** - Modern service management
- 🔧 **PHP 7.4+** - Modern PHP compatibility

### User Experience
- 🎨 **Modern Bootstrap 5 UI** - Responsive and accessible
- 📱 **Mobile-friendly interface** - Works on all devices
- 🔄 **Real-time status updates** - Live feedback
- 📋 **Copy-to-clipboard functionality** - Easy socket path copying
- 🔍 **Search and filtering** - Admin can easily find instances
- 📊 **Usage statistics** - Monitor Redis usage

## 📋 Requirements

### System Requirements
- **DirectAdmin 1.60.0+**
- **PHP 7.4 or higher**
- **Redis 5.0 or higher**
- **SystemD** (systemctl)
- **sudo** with proper configuration

### Operating System Support
| OS | Version | Status |
|---|---|---|
| Debian | 11, 12 | ✅ Fully Supported |
| Ubuntu | 20.04, 22.04, 24.04 | ✅ Fully Supported |
| CentOS | 7, 8 | ⚠️ Legacy Support |
| RHEL | 7, 8, 9 | ⚠️ Legacy Support |

## 🚀 Installation

### Quick Install (Recommended)

1. **Download and extract the plugin**
   ```bash
   cd /usr/local/directadmin/plugins
   git clone https://github.com/directadmin-community/da-redis-management.git redis_management
   cd redis_management
   ```

2. **Run the setup script (installs Redis and configures system)**
   ```bash
   cd setup
   chmod +x debian.sh  # or install.sh for RHEL/CentOS
   ./debian.sh
   ```

3. **Install the plugin**
   ```bash
   cd ../scripts
   chmod +x debian.sh  # or install.sh for RHEL/CentOS
   ./debian.sh
   ```

4. **Restart DirectAdmin**
   ```bash
   systemctl restart directadmin
   ```

### Manual Installation

If you prefer manual installation or need to customize the process:

<details>
<summary>Click to expand manual installation steps</summary>

#### Step 1: System Setup

**For Debian/Ubuntu:**
```bash
# Update package list
apt-get update

# Install Redis
apt-get install -y redis-server redis-tools

# Stop default Redis service
systemctl stop redis-server.service
systemctl disable redis-server.service
```

**For CentOS/RHEL:**
```bash
# Install EPEL repository
yum install -y epel-release

# Install Redis
yum install -y redis

# Install PHP Redis extension
pecl install redis
echo "extension=redis.so" >> /usr/local/lib/php.conf.d/20-custom.ini
```

#### Step 2: Configure SystemD Services

Copy the provided service files:
```bash
cp setup/redis-server.service /lib/systemd/system/
cp setup/redis-server@.service /lib/systemd/system/
systemctl daemon-reload
systemctl enable redis-server.service
systemctl start redis-server.service
```

#### Step 3: Configure Sudo

```bash
cp setup/redis.sudoers /etc/sudoers.d/redis
chmod 440 /etc/sudoers.d/redis
visudo -c  # Validate configuration
```

#### Step 4: Set Up Plugin

```bash
mkdir -p /usr/local/directadmin/plugins/redis_management/{logs,data}
chown -R diradmin:diradmin /usr/local/directadmin/plugins/redis_management
chown -R redis:redis /usr/local/directadmin/plugins/redis_management/data
chmod 755 /usr/local/directadmin/plugins/redis_management/user/*.html
chmod 755 /usr/local/directadmin/plugins/redis_management/admin/*.html
```

</details>

## ⚙️ Configuration

### Basic Configuration

The plugin works out of the box with secure defaults. For custom configurations:

1. **Copy the example configuration:**
   ```bash
   cp php/Config/local.example.php php/Config/local.php
   ```

2. **Edit the configuration:**
   ```bash
   nano php/Config/local.php
   ```

3. **Set secure permissions:**
   ```bash
   chmod 640 php/Config/local.php
   chown diradmin:redis php/Config/local.php
   ```

### Key Configuration Options

```php
return [
    'plugin' => [
        'userLimit' => 1,  // Redis instances per user
        'unlimitedUsers' => ['admin'],  // Users exempt from limits
    ],
    'redis' => [
        'defaultMemoryLimit' => '256mb',  // Memory per instance
        'defaultClientLimit' => 10,      // Max connections per instance
    ]
];
```

### Security Configuration

```php
return [
    'plugin' => [
        'security' => [
            'csrfProtection' => true,
            'accessLogging' => true,
            'blacklistedUsernames' => ['root', 'admin'],
        ]
    ],
    'redis' => [
        'security' => [
            'unixSocketOnly' => true,
            'protectedMode' => true,
            'disabledCommands' => ['FLUSHDB', 'FLUSHALL', 'CONFIG'],
        ]
    ]
];
```

## 🎯 Usage

### For End Users

1. **Access the plugin:**
   - Log into DirectAdmin
   - Navigate to "Additional Features" → "Redis Management"

2. **Create a Redis instance:**
   - Click "Create Redis Instance"
   - Wait for the instance to be configured
   - Note the socket path for your applications

3. **Connect to Redis:**
   ```bash
   # Using Redis CLI
   redis-cli -s /home/username/tmp/redis.sock
   
   # From PHP
   $redis = new Redis();
   $redis->connect('/home/username/tmp/redis.sock');
   ```

4. **Delete an instance:**
   - Click "Delete Instance" in the interface
   - Confirm the deletion

### For Administrators

1. **View all instances:**
   - Log into DirectAdmin as admin
   - Navigate to "Admin Tools" → "All User Redis Instances"

2. **Monitor usage:**
   - Search for specific users
   - View instance creation dates
   - Check system statistics

3. **Check logs:**
   ```bash
   tail -f /usr/local/directadmin/plugins/redis_management/logs/redis-management.log
   ```

## 🛡️ Security Features

### Implemented Security Measures

1. **Input Validation:**
   - Username format validation (alphanumeric + underscore/hyphen)
   - Length limits (max 32 characters)
   - Path traversal prevention
   - Command injection prevention

2. **Access Controls:**
   - User ownership verification
   - Admin-only admin interface
   - CSRF token validation
   - Session validation

3. **Redis Security:**
   - Unix socket communication only
   - Disabled dangerous commands
   - Memory limits per instance
   - Connection limits per instance
   - Protected mode enabled

4. **System Security:**
   - Minimal sudo permissions
   - Secure file permissions
   - Proper directory isolation
   - Service isolation per user

### Security Hardening Checklist

- ✅ All user inputs are validated and sanitized
- ✅ Path traversal attacks are prevented
- ✅ Command injection is impossible
- ✅ Users can only manage their own instances
- ✅ Redis dangerous commands are disabled
- ✅ Unix sockets provide secure communication
- ✅ File permissions are properly restricted
- ✅ Sudo access is minimally scoped
- ✅ System logs capture all operations

## 🔧 Troubleshooting

### Common Issues

#### Redis Instance Won't Start
```bash
# Check service status
systemctl status redis-server@username

# Check logs
journalctl -u redis-server@username -f

# Verify configuration
redis-server /etc/redis/instances/username.conf --test-config
```

#### Permission Denied Errors
```bash
# Check file ownership
ls -la /home/username/tmp/redis.sock
ls -la /home/username/redis/

# Fix permissions if needed
chown username:username /home/username/tmp/redis.sock
chown username:username /home/username/redis/
```

#### Plugin Interface Not Loading
```bash
# Check DirectAdmin error logs
tail -f /var/log/directadmin/error.log

# Check plugin logs
tail -f /usr/local/directadmin/plugins/redis_management/logs/redis-management.log

# Verify PHP Redis extension
php -m | grep redis
```

#### Sudo Errors
```bash
# Test sudo configuration
sudo -u redis systemctl status redis-server@testuser

# Validate sudoers file
visudo -c -f /etc/sudoers.d/redis

# Check sudoers file permissions
ls -la /etc/sudoers.d/redis  # Should be 440 root:root
```

### Debug Mode

Enable debug mode for detailed logging:

```php
// In php/Config/local.php
return [
    'development' => [
        'enabled' => true,
        'debugMode' => true,
        'verboseLogging' => true,
    ]
];
```

### Log Files

| Log File | Purpose |
|---|---|
| `/usr/local/directadmin/plugins/redis_management/logs/redis-management.log` | Plugin operations |
| `/usr/local/directadmin/plugins/redis_management/logs/php-error.log` | PHP errors |
| `/var/log/directadmin_redis_cleanup.log` | User deletion cleanup |
| `/var/log/directadmin/error.log` | DirectAdmin errors |
| `journalctl -u redis-server@username` | Redis service logs |

## 🔄 Updating

### Automatic Update (Git)
```bash
cd /usr/local/directadmin/plugins/redis_management
git pull origin main
systemctl restart directadmin
```

### Manual Update
1. Backup your `php/Config/local.php` file
2. Download the new version
3. Extract over the existing installation
4. Restore your configuration file
5. Run the update script if provided
6. Restart DirectAdmin

## 🗑️ Uninstallation

### Complete Removal

```bash
# Stop all Redis instances
systemctl stop redis-server@*

# Remove the plugin
rm -rf /usr/local/directadmin/plugins/redis_management

# Remove systemd services
rm -f /lib/systemd/system/redis-server.service
rm -f /lib/systemd/system/redis-server@.service
systemctl daemon-reload

# Remove sudo configuration
rm -f /etc/sudoers.d/redis

# Remove Redis configuration
rm -rf /etc/redis/instances

# Remove log rotation
rm -f /etc/logrotate.d/redis-management

# Clean up user destruction hook
sed -i '/redis_management/d' /usr/local/directadmin/scripts/custom/user_destroy_post.sh

# Restart DirectAdmin
systemctl restart directadmin
```

### Keep User Data
If you want to preserve user Redis data:
```bash
# Backup user data before uninstallation
tar -czf redis-user-data-backup.tar.gz /home/*/redis/
```

## 📚 Advanced Configuration

### Custom Redis Settings

You can customize Redis settings per instance by modifying the template:

```bash
nano php/Templates/redis-instance.conf
```

### Performance Tuning

For high-traffic sites:

```php
return [
    'redis' => [
        'defaultMemoryLimit' => '1gb',
        'defaultClientLimit' => 100,
        'performance' => [
            'maxMemoryPolicy' => 'allkeys-lfu',
            'hashMaxZiplistEntries' => 1024,
        ]
    ]
];
```

### Multi-Instance Support

To allow multiple instances per user:

```php
return [
    'plugin' => [
        'userLimit' => 3,  // Allow 3 instances per user
        'unlimitedUsers' => ['premium_user'],
    ]
];
```

## 🤝 Contributing

We welcome contributions! Please:

1. Fork the repository
2. Create a feature branch
3. Make your changes with tests
4. Submit a pull request

### Development Setup

```bash
git clone https://github.com/directadmin-community/da-redis-management.git
cd da-redis-management
cp php/Config/local.example.php php/Config/local.php
# Edit local.php to enable development mode
```

### Security Reporting

Report security vulnerabilities privately to: security@directadmin-community.org

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Original plugin by Kevin Bentlage
- Enhanced by the DirectAdmin Community
- Security improvements by cybersecurity contributors
- Debian 12 compatibility by Linux system administrators

## 📞 Support

- **Documentation:** This README and inline code comments
- **Issues:** [GitHub Issues](https://github.com/directadmin-community/da-redis-management/issues)
- **Community:** [DirectAdmin Forums](https://forum.directadmin.com/)
- **Security:** security@directadmin-community.org

---

## 📈 Version History

### v3.0.0 (Current)
- 🔒 Complete security overhaul
- 🐧 Debian 12 compatibility
- 🎨 Modern Bootstrap 5 UI
- 📱 Mobile-responsive design
- 🚀 Performance improvements
- 📊 Enhanced admin interface
- 🛡️ Comprehensive input validation
- 🔧 Improved error handling

### v2.0.0 (Legacy)
- Basic DirectAdmin integration
- Ubuntu/Debian support
- Simple web interface

### v1.0.0 (Original)
- Initial release
- CentOS/RHEL support only
- Basic functionality

---

**Made with ❤️ by the DirectAdmin Community**
