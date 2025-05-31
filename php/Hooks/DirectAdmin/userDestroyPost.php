#!/usr/local/bin/php -c/usr/local/directadmin/plugins/redis_management/php/php.ini
<?php
/**
 * DirectAdmin User Destruction Hook for Redis Management Plugin
 * Enhanced with comprehensive cleanup and security measures
 * 
 * This script is called when a DirectAdmin user is deleted
 * It ensures all Redis instances and related files are properly cleaned up
 */

// Set error reporting for logging
error_reporting(E_ALL);
ini_set('log_errors', 1);

// Initialize logging
$logFile = '/var/log/directadmin_redis_cleanup.log';
function logMessage($message, $level = 'INFO') {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

// Validate input
if (!isset($argv[1]) || empty($argv[1])) {
    logMessage('No username provided to userDestroyPost hook', 'ERROR');
    exit(1);
}

$username = trim($argv[1]);

// Validate username format for security
if (!preg_match('/^[a-zA-Z0-9_][a-zA-Z0-9_-]*$/', $username) || strlen($username) > 32) {
    logMessage("Invalid username format: {$username}", 'ERROR');
    exit(1);
}

logMessage("Starting Redis cleanup for user: {$username}");

try {
    // Include the bootstrap and controller
    require_once dirname(dirname(dirname(__DIR__))) . '/php/bootstrap.php';
    
    $redisController = new \DirectAdmin\RedisManagement\Controllers\RedisController;
    
    // Check if user has Redis instances
    $instances = $redisController->getInstances($username);
    
    if ($instances) {
        logMessage("Found Redis instances for user {$username}, proceeding with cleanup");
        
        // Delete all user instances
        $result = $redisController->deleteAllUserInstances($username);
        
        if ($result === true) {
            logMessage("Successfully deleted Redis instances for user {$username}");
        } else {
            logMessage("Failed to delete Redis instances for user {$username}: {$result}", 'ERROR');
        }
        
        // Additional cleanup steps
        
        // 1. Stop and disable systemd service (redundant but safe)
        $serviceName = 'redis-server@' . escapeshellarg($username);
        $commands = [
            "sudo systemctl stop {$serviceName}",
            "sudo systemctl disable {$serviceName}"
        ];
        
        foreach ($commands as $command) {
            $output = shell_exec($command . ' 2>&1');
            if ($output) {
                logMessage("Command output for '{$command}': {$output}");
            }
        }
        
        // 2. Remove configuration files (redundant but thorough)
        $configFile = '/etc/redis/instances/' . $username . '.conf';
        if (file_exists($configFile)) {
            if (unlink($configFile)) {
                logMessage("Removed configuration file: {$configFile}");
            } else {
                logMessage("Failed to remove configuration file: {$configFile}", 'WARNING');
            }
        }
        
        // 3. Clean up user directories (be careful with paths)
        $userHome = '/home/' . $username;
        $redisDir = $userHome . '/redis';
        $tmpDir = $userHome . '/tmp';
        
        // Only clean up if the directories exist and are within the user's home
        if (is_dir($redisDir) && strpos(realpath($redisDir), realpath($userHome)) === 0) {
            // Remove Redis data files
            $dataFiles = [
                $redisDir . '/dump.rdb',
                $redisDir . '/appendonly.aof',
                $redisDir . '/redis.log',
                $redisDir . '/redis.pid'
            ];
            
            foreach ($dataFiles as $file) {
                if (file_exists($file)) {
                    if (unlink($file)) {
                        logMessage("Removed Redis data file: {$file}");
                    } else {
                        logMessage("Failed to remove Redis data file: {$file}", 'WARNING');
                    }
                }
            }
            
            // Try to remove the Redis directory if empty
            if (is_dir($redisDir)) {
                $files = scandir($redisDir);
                if (count($files) <= 2) { // Only . and .. entries
                    if (rmdir($redisDir)) {
                        logMessage("Removed empty Redis directory: {$redisDir}");
                    } else {
                        logMessage("Failed to remove Redis directory: {$redisDir}", 'WARNING');
                    }
                } else {
                    logMessage("Redis directory not empty, leaving intact: {$redisDir}");
                }
            }
        }
        
        // 4. Clean up socket file
        $socketFile = $tmpDir . '/redis.sock';
        if (file_exists($socketFile)) {
            if (unlink($socketFile)) {
                logMessage("Removed Redis socket file: {$socketFile}");
            } else {
                logMessage("Failed to remove Redis socket file: {$socketFile}", 'WARNING');
            }
        }
        
        // 5. Check for any remaining Redis processes for this user
        $psOutput = shell_exec("ps aux | grep '[r]edis.*{$username}' 2>/dev/null");
        if (!empty($psOutput)) {
            logMessage("Warning: Found remaining Redis processes for user {$username}:\n{$psOutput}", 'WARNING');
            
            // Attempt to kill any remaining processes
            $pids = [];
            $lines = explode("\n", trim($psOutput));
            foreach ($lines as $line) {
                if (preg_match('/^\S+\s+(\d+)/', $line, $matches)) {
                    $pids[] = $matches[1];
                }
            }
            
            foreach ($pids as $pid) {
                shell_exec("kill -TERM {$pid} 2>/dev/null");
                logMessage("Sent TERM signal to PID {$pid}");
            }
            
            // Give processes time to shutdown gracefully
            sleep(2);
            
            // Force kill if still running
            foreach ($pids as $pid) {
                $stillRunning = shell_exec("ps -p {$pid} >/dev/null 2>&1; echo $?");
                if (trim($stillRunning) === '0') {
                    shell_exec("kill -KILL {$pid} 2>/dev/null");
                    logMessage("Force killed PID {$pid}");
                }
            }
        }
        
        logMessage("Redis cleanup completed for user: {$username}");
        echo "User's Redis instances and data removed successfully.";
        
    } else {
        logMessage("No Redis instances found for user {$username}, no cleanup needed");
        echo "No Redis instances found for user.";
    }
    
} catch (Exception $e) {
    $errorMsg = "Exception during Redis cleanup for user {$username}: " . $e->getMessage();
    logMessage($errorMsg, 'ERROR');
    
    // Log the full stack trace
    logMessage("Stack trace: " . $e->getTraceAsString(), 'ERROR');
    
    echo "Error during Redis cleanup: " . $e->getMessage();
    exit(1);
} catch (Error $e) {
    $errorMsg = "Fatal error during Redis cleanup for user {$username}: " . $e->getMessage();
    logMessage($errorMsg, 'ERROR');
    
    echo "Fatal error during Redis cleanup: " . $e->getMessage();
    exit(1);
}

// Final verification
try {
    // Verify no Redis instances remain in the data file
    $redisController = new \DirectAdmin\RedisManagement\Controllers\RedisController;
    $remainingInstances = $redisController->getInstances($username);
    
    if ($remainingInstances) {
        logMessage("Warning: Redis instances still found in data file after cleanup for user {$username}", 'WARNING');
    } else {
        logMessage("Verification passed: No Redis instances remain for user {$username}");
    }
} catch (Exception $e) {
    logMessage("Could not verify cleanup completion: " . $e->getMessage(), 'WARNING');
}

exit(0);
?>
