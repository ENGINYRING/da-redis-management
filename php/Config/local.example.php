<?php
/**
 * DirectAdmin Redis Management Plugin - Local Configuration Example
 * 
 * Copy this file to 'local.php' and customize the settings as needed.
 * Settings in local.php will override those in main.php
 * 
 * IMPORTANT: Rename this file to 'local.php' to activate your custom settings.
 * SECURITY: Ensure this file has proper permissions (640) and is owned by diradmin:redis
 */

return [
    // Plugin configuration overrides
    'plugin' => [
        // Adjust user limits - set to false to disable limits entirely
        'limit' => true,
        
        // Maximum Redis instances per user (recommended: 1-3 for shared hosting)
        'userLimit' => 1,
        
        // Users who can create unlimited instances
        'unlimitedUsers' => [
            // 'admin',
            // 'premium_user',
        ],
        
        // Security settings
        'security' => [
            // Enable/disable CSRF protection (recommended: true)
            'csrfProtection' => true,
            
            // Enable access logging (recommended: true for monitoring)
            'accessLogging' => true,
            
            // Additional blacklisted usernames
            'blacklistedUsernames' => [
                'root', 'admin', 'administrator', 'system', 'daemon',
                'bin', 'sys', 'mail', 'www', 'nobody', 'redis',
                // Add more usernames to blacklist here
                // 'test', 'demo', 'guest',
            ],
        ],
        
        // Logging configuration
        'logging' => [
            'enabled' => true,
            'level' => 'INFO', // Change to 'DEBUG' for troubleshooting
            'maxFileSize' => '50MB', // Increase if you have high usage
            'maxFiles' => 30, // Keep more log files for analysis
        ],
        
        // Performance tuning
        'performance' => [
            'cacheEnabled' => true,
            'cacheTTL' => 600, // Increase cache time for better performance
            'lockTimeout' => 60, // Increase if you have slow storage
            'maxExecutionTime' => 180, // Increase for slow systems
        ],
        
        // Backup settings
        'backup' => [
            'enabled' => true, // Enable automatic backups
            'path' => 'backups',
            'retention' => 14, // Keep backups for 14 days
        ]
    ],
    
    // Redis configuration overrides
    'redis' => [
        // Change Redis user/group if different on your system
        'user' => 'redis',
        'group' => 'redis',
        
        // Customize data directory location
        // 'dataDir' => '/var/lib/redis',
        
        // Adjust memory limits based on your server capacity
        'defaultMemoryLimit' => '512mb', // Increase for servers with more RAM
        
        // Client connection limits
        'defaultClientLimit' => 25, // Increase for high-traffic sites
        
        // Timeout settings
        'defaultTimeout' => 600, // Increase for long-running operations
        
        // Security settings
        'security' => [
            // Customize disabled commands based on your needs
            'disabledCommands' => [
                'FLUSHDB', 'FLUSHALL', 'KEYS', 'CONFIG',
                'DEBUG', 'EVAL', 'SCRIPT', 'SHUTDOWN',
                // Uncomment to disable additional commands
                // 'DEL', 'RENAME', 'UNLINK',
            ],
            
            // Unix socket settings
            'unixSocketOnly' => true, // Keep true for security
            'socketPermissions' => '700', // Restrictive permissions
            'protectedMode' => true,
        ],
        
        // Persistence settings
        'persistence' => [
            'aofEnabled' => true, // Recommended for data safety
            'aofFsync' => 'everysec', // Balance between safety and performance
            
            // Adjust save intervals based on your needs
            'rdbSave' => [
                '3600 1',    // Every hour if at least 1 change
                '300 100',   // Every 5 minutes if at least 100 changes
                '60 10000'   // Every minute if at least 10000 changes
            ],
            
            'rdbCompression' => true,
            'rdbChecksum' => true,
        ],
        
        // Performance tuning for your environment
        'performance' => [
            'maxMemoryPolicy' => 'allkeys-lru', // Good for caching
            // 'maxMemoryPolicy' => 'volatile-lru', // Use if you set TTLs
            
            'stopWritesOnBgsaveError' => true,
            
            // Hash optimization (tune based on your data)
            'hashMaxZiplistEntries' => 1024,
            'hashMaxZiplistValue' => 128,
            
            // List optimization
            'listMaxZiplistSize' => -1, // Use for small lists
            'listCompressDepth' => 1,   // Compress list nodes
            
            // Set optimization
            'setMaxIntsetEntries' => 1024,
            
            // Sorted set optimization
            'zsetMaxZiplistEntries' => 256,
            'zsetMaxZiplistValue' => 128,
        ],
        
        // Monitoring settings
        'monitoring' => [
            'slowlogLogSlowerThan' => 5000, // More sensitive slow log
            'slowlogMaxLen' => 256, // Keep more slow log entries
            'logLevel' => 'notice', // Change to 'verbose' for debugging
            'clientTimeout' => 0, // Disable timeout for persistent connections
            'tcpKeepalive' => 60, // More frequent keepalives
        ]
    ],
    
    // System configuration overrides
    'system' => [
        // Override command paths if different on your system
        'commands' => [
            // Uncomment and adjust if commands are in different locations
            // 'systemctl' => '/bin/systemctl',
            // 'redis-server' => '/usr/local/bin/redis-server',
            // 'redis-cli' => '/usr/local/bin/redis-cli',
        ],
        
        // Adjust permissions if needed
        'permissions' => [
            'pluginDir' => 0755,
            'dataDir' => 0750,
            'logDir' => 0750,
            'configDir' => 0755,
            'userRedisDir' => 0750,
            'userTmpDir' => 0750,
        ],
    ],
    
    // Environment-specific settings
    'environment' => [
        // Force specific OS detection if auto-detection fails
        // 'forceOS' => 'debian', // or 'ubuntu', 'centos', 'rhel'
        
        // Override feature detection
        // 'features' => [
        //     'systemd' => true,
        //     'redis' => true,
        //     'sudo' => true,
        // ]
    ],
    
    // Custom settings for specific hosting environments
    'hosting' => [
        // Shared hosting optimizations
        'shared' => [
            'enabled' => false, // Set to true for shared hosting
            'maxMemoryPerInstance' => '128mb',
            'maxClientsPerInstance' => 5,
            'disableBackgroundSave' => false,
            'prioritizeMemoryUsage' => true,
        ],
        
        // VPS/Dedicated server optimizations
        'dedicated' => [
            'enabled' => false, // Set to true for dedicated servers
            'maxMemoryPerInstance' => '1gb',
            'maxClientsPerInstance' => 100,
            'enableAdvancedFeatures' => true,
            'allowCustomConfig' => false, // Security consideration
        ]
    ],
    
    // Development/debugging settings
    'development' => [
        'enabled' => false, // Set to true only for development
        'debugMode' => false,
        'verboseLogging' => false,
        'disableSecurityChecks' => false, // NEVER enable in production
        'allowDangerousCommands' => false, // NEVER enable in production
    ],
    
    // Notification settings (future feature)
    'notifications' => [
        'enabled' => false,
        'email' => [
            'adminEmail' => 'admin@yourdomain.com',
            'notifyOnInstanceCreate' => false,
            'notifyOnInstanceDelete' => false,
            'notifyOnErrors' => true,
        ],
        'webhook' => [
            'enabled' => false,
            'url' => '',
            'secret' => '',
        ]
    ],
    
    // Integration settings
    'integrations' => [
        // CloudFlare integration (future feature)
        'cloudflare' => [
            'enabled' => false,
            'apiKey' => '',
            'email' => '',
        ],
        
        // Monitoring integration (future feature)
        'monitoring' => [
            'enabled' => false,
            'service' => 'none', // 'newrelic', 'datadog', 'prometheus'
            'apiKey' => '',
        ]
    ]
];

/*
 * Configuration Notes:
 * 
 * 1. Security Considerations:
 *    - Keep 'unixSocketOnly' => true for security
 *    - Don't disable CSRF protection in production
 *    - Be careful with unlimited users list
 *    - Never enable dangerous commands in production
 * 
 * 2. Performance Tuning:
 *    - Adjust memory limits based on available RAM
 *    - Tune persistence settings for your I/O capacity
 *    - Monitor slow log and adjust thresholds
 * 
 * 3. Backup Strategy:
 *    - Enable backups for important data
 *    - Adjust retention based on available disk space
 *    - Consider external backup solutions for critical data
 * 
 * 4. Monitoring:
 *    - Enable access logging for security auditing
 *    - Monitor log files for errors and warnings
 *    - Set up external monitoring for production systems
 * 
 * 5. Maintenance:
 *    - Regularly review and update these settings
 *    - Test configuration changes in development first
 *    - Keep backups of working configurations
 */
