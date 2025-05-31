<?php
/**
 * DirectAdmin Redis Management Plugin - Main Configuration
 * Enhanced with security settings and Debian 12 compatibility
 * 
 * This file contains the default configuration settings.
 * DO NOT modify this file directly as it may be overwritten during updates.
 * Instead, create a local.php file to override specific settings.
 */

return [
    // Plugin metadata and paths
    'plugin' => [
        // Data file to store instance information
        'dataFile' => 'data/instances.json',
        
        // Temporary directory for plugin operations
        'path' => 'tmp',
        
        // Instance limits
        'limit' => true,
        'userLimit' => 1, // Default: 1 instance per user
        
        // Users exempt from limits (array of usernames)
        'unlimitedUsers' => [],
        
        // Security settings
        'security' => [
            // Maximum username length
            'maxUsernameLength' => 32,
            
            // Allowed username pattern (alphanumeric, underscore, hyphen)
            'usernamePattern' => '/^[a-zA-Z0-9_][a-zA-Z0-9_-]*$/',
            
            // Blacklisted usernames
            'blacklistedUsernames' => [
                'root', 'admin', 'administrator', 'system', 'daemon', 
                'bin', 'sys', 'mail', 'www', 'nobody', 'redis'
            ],
            
            // Enable CSRF protection
            'csrfProtection' => true,
            
            // Enable access logging
            'accessLogging' => true,
        ],
        
        // Logging configuration
        'logging' => [
            'enabled' => true,
            'level' => 'INFO', // DEBUG, INFO, WARNING, ERROR
            'maxFileSize' => '10MB',
            'maxFiles' => 10,
            'logRotation' => true,
        ],
        
        // Performance settings
        'performance' => [
            // Cache instance data for better performance
            'cacheEnabled' => true,
            'cacheTTL' => 300, // 5 minutes
            
            // File locking timeout (seconds)
            'lockTimeout' => 30,
            
            // Maximum execution time for operations (seconds)
            'maxExecutionTime' => 120,
        ],
        
        // Backup settings
        'backup' => [
            'enabled' => false,
            'path' => 'backups',
            'retention' => 30, // days
        ]
    ],
    
    // Redis configuration
    'redis' => [
        // System user and group for Redis
        'user' => 'redis',
        'group' => 'redis',
        
        // Configuration directory for instance configs
        'configDir' => '/etc/redis/instances',
        
        // Main Redis configuration file
        'mainConfig' => '/etc/redis/redis.conf',
        
        // SystemD service name template
        'serviceTemplate' => 'redis-server@%s',
        
        // Default memory limit per instance
        'defaultMemoryLimit' => '256mb',
        
        // Default client limit per instance
        'defaultClientLimit' => 10,
        
        // Default timeout settings
        'defaultTimeout' => 300,
        
        // Security settings for Redis instances
        'security' => [
            // Disable dangerous commands
            'disabledCommands' => [
                'FLUSHDB', 'FLUSHALL', 'KEYS', 'CONFIG', 
                'DEBUG', 'EVAL', 'SCRIPT', 'SHUTDOWN'
            ],
            
            // Use Unix sockets only (no TCP)
            'unixSocketOnly' => true,
            
            // Socket permissions
            'socketPermissions' => '700',
            
            // Protected mode
            'protectedMode' => true,
        ],
        
        // Data persistence settings
        'persistence' => [
            // Enable AOF (Append Only File)
            'aofEnabled' => true,
            'aofFsync' => 'everysec',
            
            // RDB save intervals
            'rdbSave' => [
                '900 1',   // After 900 seconds if at least 1 key changed
                '300 10',  // After 300 seconds if at least 10 keys changed
                '60 10000' // After 60 seconds if at least 10000 keys changed
            ],
            
            // Compression
            'rdbCompression' => true,
            'rdbChecksum' => true,
        ],
        
        // Performance tuning
        'performance' => [
            // Memory policy when limit is reached
            'maxMemoryPolicy' => 'allkeys-lru',
            
            // Background save behavior
            'stopWritesOnBgsaveError' => true,
            
            // Hash optimization
            'hashMaxZiplistEntries' => 512,
            'hashMaxZiplistValue' => 64,
            
            // List optimization
            'listMaxZiplistSize' => -2,
            'listCompressDepth' => 0,
            
            // Set optimization
            'setMaxIntsetEntries' => 512,
            
            // Sorted set optimization
            'zsetMaxZiplistEntries' => 128,
            'zsetMaxZiplistValue' => 64,
        ],
        
        // Monitoring and logging
        'monitoring' => [
            // Slow log settings
            'slowlogLogSlowerThan' => 10000, // microseconds
            'slowlogMaxLen' => 128,
            
            // Log level
            'logLevel' => 'notice',
            
            // Client timeout
            'clientTimeout' => 300,
            
            // TCP keepalive
            'tcpKeepalive' => 300,
        ]
    ],
    
    // System paths and commands
    'system' => [
        // System commands
        'commands' => [
            'systemctl' => '/usr/bin/systemctl',
            'redis-server' => '/usr/bin/redis-server',
            'redis-cli' => '/usr/bin/redis-cli',
            'mkdir' => '/bin/mkdir',
            'chown' => '/bin/chown',
            'chmod' => '/bin/chmod',
        ],
        
        // Directory permissions
        'permissions' => [
            'pluginDir' => 0755,
            'dataDir' => 0750,
            'logDir' => 0750,
            'configDir' => 0755,
            'userRedisDir' => 0750,
            'userTmpDir' => 0750,
        ],
        
        // File permissions
        'filePermissions' => [
            'config' => 0644,
            'data' => 0640,
            'log' => 0640,
            'socket' => 0700,
        ]
    ],
    
    // Environment detection
    'environment' => [
        'os' => php_uname('s'),
        'phpVersion' => PHP_VERSION,
        'directAdminPath' => '/usr/local/directadmin',
        
        // Feature detection
        'features' => [
            'systemd' => file_exists('/usr/bin/systemctl'),
            'redis' => file_exists('/usr/bin/redis-server'),
            'sudo' => file_exists('/usr/bin/sudo'),
        ]
    ],
    
    // Compatibility settings
    'compatibility' => [
        // Debian/Ubuntu specific settings
        'debian' => [
            'servicePrefix' => 'redis-server@',
            'configInclude' => '/etc/redis/redis.conf',
            'systemdPath' => '/lib/systemd/system',
        ],
        
        // CentOS/RHEL specific settings
        'rhel' => [
            'servicePrefix' => 'redis@',
            'configInclude' => '/etc/redis.conf',
            'systemdPath' => '/usr/lib/systemd/system',
        ]
    ],
    
    // Version information
    'version' => [
        'plugin' => '3.0.0',
        'config' => '1.0.0',
        'requiredPhp' => '7.4.0',
        'requiredRedis' => '5.0.0',
    ]
];
