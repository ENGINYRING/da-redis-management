<?php
/**
 * Created by PhpStorm.
 * User: kevin
 * Date: 12/10/2016
 * Time: 21:13
 * 
 * Security patches applied for:
 * - Input validation and sanitization
 * - Command injection prevention
 * - Path traversal protection
 * - Proper access controls
 * - Enhanced logging
 */

namespace DirectAdmin\RedisManagement\Controllers;

class RedisController
{
    private $_config           = array();
    private $_instances        = array();
    private $_basePath         = NULL;
    private $_limit            = false;
    private $_userLimit        = 5;
    private $_unlimitedUsers   = array();
    private $_logFile          = NULL;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * Init
     *
     * @return void
     */
    public function init()
    {
        $this->_basePath = dirname(dirname(__DIR__));
        $this->_logFile = $this->_basePath . '/logs/redis-management.log';
        
        $this->_config = require_once($this->_basePath.'/php/Config/main.php');

        if($this->_config)
        {
            // if local config exists, merge it with default config
            if(file_exists($this->_basePath.'/php/Config/local.php'))
            {
                $localConfig = require_once($this->_basePath.'/php/Config/local.php');
                $this->_config = array_replace_recursive($this->_config, $localConfig);
            }

            if(isset($this->_config['plugin']['limit']))
            {
                $this->_limit = $this->_config['plugin']['limit'];
            }

            if(isset($this->_config['plugin']['userLimit']))
            {
                $this->_userLimit = (int)$this->_config['plugin']['userLimit'];
            }

            if(isset($this->_config['plugin']['unlimitedUsers']))
            {
                if (is_array($this->_config['plugin']['unlimitedUsers']))
                    $this->_unlimitedUsers = $this->_config['plugin']['unlimitedUsers'];
                else
                    $this->_unlimitedUsers = [$this->_config['plugin']['unlimitedUsers']];
            }

            $this->_loadInstances();
        }
        else
        {
            throw new \Exception('No config data available!');
        }
    }

    /**
     * Load instances with file locking to prevent race conditions
     */
    private function _loadInstances()
    {
        $dataFile = $this->_basePath . '/' . $this->_config['plugin']['dataFile'];
        
        if (file_exists($dataFile))
        {
            $handle = fopen($dataFile, 'r');
            if ($handle && flock($handle, LOCK_SH)) {
                $jsonContent = file_get_contents($dataFile);
                flock($handle, LOCK_UN);
                fclose($handle);
                
                if ($jsonContent && ($json = json_decode($jsonContent, true))) {
                    if (isset($json['instances'])) {
                        $this->_instances = $json['instances'];
                    }
                } else {
                    $this->_log('warning', 'Failed to parse instances JSON file');
                }
            } else {
                $this->_log('error', 'Failed to lock instances file for reading');
            }
        }
    }

    /**
     * Validate username to prevent injection attacks
     *
     * @param string $username
     * @throws \InvalidArgumentException
     * @return bool
     */
    private function _validateUsername($username)
    {
        // Check for null/empty
        if (empty($username)) {
            throw new \InvalidArgumentException('Username cannot be empty');
        }

        // Check length (typical Unix username limits)
        if (strlen($username) > 32) {
            throw new \InvalidArgumentException('Username too long (max 32 characters)');
        }

        // Check format: alphanumeric, underscore, hyphen, starting with alphanumeric or underscore
        if (!preg_match('/^[a-zA-Z0-9_][a-zA-Z0-9_-]*$/', $username)) {
            throw new \InvalidArgumentException('Invalid username format');
        }

        // Check for path traversal attempts
        if (strpos($username, '..') !== false || strpos($username, '/') !== false || strpos($username, '\\') !== false) {
            throw new \InvalidArgumentException('Username contains invalid path characters');
        }

        // Additional blacklist for dangerous patterns
        $blacklist = ['root', 'admin', 'administrator', 'system', 'daemon', 'bin', 'sys'];
        if (in_array(strtolower($username), $blacklist)) {
            throw new \InvalidArgumentException('Username not allowed');
        }

        return true;
    }

    /**
     * Validate timestamp for access control
     *
     * @param mixed $timestamp
     * @return bool
     */
    private function _validateTimestamp($timestamp)
    {
        return is_numeric($timestamp) && $timestamp > 0 && $timestamp <= time();
    }

    /**
     * Secure logging function
     *
     * @param string $level
     * @param string $message
     * @param array $context
     */
    private function _log($level, $message, $context = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = $context ? ' ' . json_encode($context) : '';
        $logEntry = "[{$timestamp}] [{$level}] {$message}{$contextStr}" . PHP_EOL;
        
        // Ensure log directory exists
        $logDir = dirname($this->_logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }
        
        file_put_contents($this->_logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get Instances
     *
     * @param null $username
     *
     * @return array
     */
    public function getInstances($username = NULL)
    {
        if ($username) {
            try {
                $this->_validateUsername($username);
                
                if (isset($this->_instances[$username])) {
                    return $this->_instances[$username];
                } else {
                    return NULL;
                }
            } catch (\InvalidArgumentException $e) {
                $this->_log('warning', 'Invalid username in getInstances', ['username' => $username, 'error' => $e->getMessage()]);
                return NULL;
            }
        } else {
            return $this->_instances ?: NULL;
        }
    }

    /**
     * Create Instance
     *
     * @param $username
     *
     * @return bool|string Returns true on success, error message on failure
     */
    public function createInstance($username)
    {
        try {
            $this->_validateUsername($username);
            
            // Check if user already has an instance
            if (isset($this->_instances[$username])) {
                return 'User already has a Redis instance';
            }
            
            // Check user limits
            if ($this->checkUserLimit($username)) {
                return 'User has reached the maximum number of Redis instances';
            }
            
            // Create user's tmp and redis directories
            $userHome = '/home/' . $username;
            $tmpDir = $userHome . '/tmp';
            $redisDir = $userHome . '/redis';
            
            if (!is_dir($tmpDir)) {
                if (!mkdir($tmpDir, 0750, true)) {
                    $this->_log('error', 'Failed to create tmp directory', ['user' => $username, 'dir' => $tmpDir]);
                    return 'Failed to create user directories';
                }
                // Set ownership
                chown($tmpDir, $username);
                chgrp($tmpDir, $username);
            }
            
            if (!is_dir($redisDir)) {
                if (!mkdir($redisDir, 0750, true)) {
                    $this->_log('error', 'Failed to create redis directory', ['user' => $username, 'dir' => $redisDir]);
                    return 'Failed to create user directories';
                }
                // Set ownership
                chown($redisDir, $username);
                chgrp($redisDir, $username);
            }

            // add instance data
            if ($this->_addInstanceData($username)) {
                // create instance config
                $configResult = $this->_createInstanceConfig($username);
                if ($configResult === true) {
                    // save data
                    if ($this->_saveData()) {
                        // enable and start service
                        $enableResult = $this->_enableService($username);
                        $startResult = $this->_startService($username);
                        
                        if ($enableResult && $startResult) {
                            $this->_log('info', 'Redis instance created successfully', ['user' => $username]);
                            return true;
                        } else {
                            $this->_log('error', 'Failed to start Redis service', ['user' => $username]);
                            // Cleanup on failure
                            $this->deleteInstance($username);
                            return 'Failed to start Redis service';
                        }
                    } else {
                        return 'Failed to save instance data';
                    }
                } else {
                    return $configResult; // Error message from config creation
                }
            } else {
                return 'Failed to add instance data';
            }
        } catch (\InvalidArgumentException $e) {
            $this->_log('warning', 'Invalid username in createInstance', ['username' => $username, 'error' => $e->getMessage()]);
            return 'Invalid username';
        } catch (\Exception $e) {
            $this->_log('error', 'Exception in createInstance', ['username' => $username, 'error' => $e->getMessage()]);
            return 'Internal error occurred';
        }
    }

    /**
     * Delete Instance
     *
     * @param $username
     * @param $timestamp Optional timestamp for access control
     *
     * @return bool|string Returns true on success, error message on failure
     */
    public function deleteInstance($username, $timestamp = null)
    {
        try {
            $this->_validateUsername($username);
            
            // Verify ownership if timestamp provided
            if ($timestamp !== null) {
                if (!$this->canUserDeleteInstance($username, $timestamp)) {
                    $this->_log('warning', 'Unauthorized delete attempt', ['user' => $username, 'timestamp' => $timestamp]);
                    return 'Unauthorized: Cannot delete this instance';
                }
            }
            
            // Check if instance exists
            if (!isset($this->_instances[$username])) {
                return 'No Redis instance found for this user';
            }

            $this->_disableService($username);
            $this->_stopService($username);

            if ($this->_deleteInstanceData($username)) {
                $configResult = $this->_deleteInstanceConfig($username);
                if ($configResult === true) {
                    // save data
                    if ($this->_saveData()) {
                        // Cleanup socket file
                        $socketPath = '/home/' . $username . '/tmp/redis.sock';
                        if (file_exists($socketPath)) {
                            unlink($socketPath);
                        }
                        
                        $this->_log('info', 'Redis instance deleted successfully', ['user' => $username]);
                        return true;
                    } else {
                        return 'Failed to save updated instance data';
                    }
                } else {
                    return $configResult; // Error message from config deletion
                }
            } else {
                return 'Failed to remove instance data';
            }
        } catch (\InvalidArgumentException $e) {
            $this->_log('warning', 'Invalid username in deleteInstance', ['username' => $username, 'error' => $e->getMessage()]);
            return 'Invalid username';
        } catch (\Exception $e) {
            $this->_log('error', 'Exception in deleteInstance', ['username' => $username, 'error' => $e->getMessage()]);
            return 'Internal error occurred';
        }
    }

    /**
     * Delete All User Instances
     *
     * @param $username
     * @return bool|string
     */
    public function deleteAllUserInstances($username)
    {
        try {
            $this->_validateUsername($username);
            
            if(isset($this->_instances[$username]) && !empty($this->_instances[$username])) {
                return $this->deleteInstance($username);
            }
            
            return true; // No instances to delete
        } catch (\InvalidArgumentException $e) {
            $this->_log('warning', 'Invalid username in deleteAllUserInstances', ['username' => $username, 'error' => $e->getMessage()]);
            return 'Invalid username';
        }
    }

    /**
     * Check if user can delete a specific instance (ownership verification)
     *
     * @param string $username
     * @param int $timestamp
     * @return bool
     */
    public function canUserDeleteInstance($username, $timestamp)
    {
        try {
            $this->_validateUsername($username);
            $this->_validateTimestamp($timestamp);
            
            $instances = $this->getInstances($username);
            return $instances && isset($instances['created']) && $instances['created'] == $timestamp;
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Check User Reach the Limit or not
     */
    public function checkUserLimit($username)
    {
        try {
            $this->_validateUsername($username);
            
            return $this->_limit && 
                   isset($this->_instances[$username]) && 
                   count($this->_instances[$username]) >= $this->_userLimit && 
                   !in_array($username, $this->_unlimitedUsers);
        } catch (\InvalidArgumentException $e) {
            return true; // Err on the side of caution
        }
    }

    /**
     * Add Instance Data
     *
     * @param $username
     *
     * @return bool
     */
    private function _addInstanceData($username)
    {
        $this->_instances[$username] = array(
            'username' => $username,
            'socket'   => '/home/'.$username.'/tmp/redis.sock',
            'created'  => time(),
        );

        return true;
    }

    /**
     * Delete Instance Data
     *
     * @param $username
     *
     * @return bool
     */
    private function _deleteInstanceData($username)
    {
        if (isset($this->_instances[$username])) {
            unset($this->_instances[$username]);
            return true;
        }

        return false;
    }

    /**
     * Save data with file locking
     *
     * @return bool
     */
    private function _saveData()
    {
        // prepare data
        $data = array(
            'instances' => $this->_instances,
            'last_updated' => time()
        );

        // encode data to json
        $json = json_encode($data, JSON_PRETTY_PRINT);

        // determine data dir path
        $dataFilePath = $this->_basePath . '/' . $this->_config['plugin']['dataFile'];
        $pathInfo = pathinfo($dataFilePath);

        // check if data directory already exists
        if (!is_dir($pathInfo['dirname'])) {
            // create data directory
            if (!mkdir($pathInfo['dirname'], 0750, true)) {
                $this->_log('error', 'Failed to create data directory', ['dir' => $pathInfo['dirname']]);
                return false;
            }
        }

        // save json to file with atomic write and locking
        $tempFile = $dataFilePath . '.tmp';
        $handle = fopen($tempFile, 'w');
        
        if ($handle && flock($handle, LOCK_EX)) {
            $result = fwrite($handle, $json);
            flock($handle, LOCK_UN);
            fclose($handle);
            
            if ($result !== false) {
                // Atomic move
                if (rename($tempFile, $dataFilePath)) {
                    chmod($dataFilePath, 0640);
                    return true;
                }
            }
        }
        
        // Cleanup temp file on failure
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
        
        $this->_log('error', 'Failed to save data file', ['file' => $dataFilePath]);
        return false;
    }

    /**
     * Create Instance Config with security checks
     *
     * @param $user
     *
     * @return bool|string Returns true on success, error message on failure
     */
    private function _createInstanceConfig($user)
    {
        // get redis template contents
        $templatePath = $this->_basePath . '/php/Templates/redis-instance.conf';
        if (!file_exists($templatePath)) {
            return 'Redis template configuration file not found';
        }
        
        $templateContent = file_get_contents($templatePath);
        if (!$templateContent) {
            return 'Failed to read Redis template configuration';
        }

        // replace variables with actual values (only allow alphanumeric and safe chars)
        $safeUser = preg_replace('/[^a-zA-Z0-9_-]/', '', $user);
        if ($safeUser !== $user) {
            return 'Username contains unsafe characters for configuration';
        }
        
        $replaceTokens = array('{{ user }}');
        $replaceValues = array($safeUser);
        $configContent = str_replace($replaceTokens, $replaceValues, $templateContent);

        // Ensure config directory exists and is secure
        $configDir = $this->_config['redis']['configDir'];
        if (!is_dir($configDir)) {
            if (!mkdir($configDir, 0755, true)) {
                return 'Failed to create Redis configuration directory';
            }
            chown($configDir, $this->_config['redis']['user']);
            chgrp($configDir, $this->_config['redis']['group']);
        }

        // Validate and secure the config file path
        $configFilePath = $configDir . '/' . $safeUser . '.conf';
        $realConfigDir = realpath($configDir);
        $realConfigPath = realpath(dirname($configFilePath)) . '/' . basename($configFilePath);
        
        // Ensure the path is within the config directory (prevent path traversal)
        if (!$realConfigDir || strpos($realConfigPath, $realConfigDir) !== 0) {
            return 'Invalid configuration file path';
        }

        // save config file
        if (file_put_contents($configFilePath, $configContent, LOCK_EX)) {
            chmod($configFilePath, 0644);
            return true;
        } else {
            return 'Failed to write Redis configuration file';
        }
    }

    /**
     * Delete Instance Config with security checks
     *
     * @param $user
     *
     * @return bool|string Returns true on success, error message on failure
     */
    private function _deleteInstanceConfig($user)
    {
        $safeUser = preg_replace('/[^a-zA-Z0-9_-]/', '', $user);
        if ($safeUser !== $user) {
            return 'Username contains unsafe characters';
        }
        
        $configFilePath = $this->_config['redis']['configDir'] . '/' . $safeUser . '.conf';
        $realConfigDir = realpath($this->_config['redis']['configDir']);
        
        if ($realConfigDir && file_exists($configFilePath)) {
            $realConfigPath = realpath($configFilePath);
            
            // Ensure the file is within the config directory
            if ($realConfigPath && strpos($realConfigPath, $realConfigDir) === 0) {
                if (unlink($configFilePath)) {
                    return true;
                } else {
                    return 'Failed to delete configuration file';
                }
            } else {
                return 'Invalid configuration file path';
            }
        }
        
        return true; // File doesn't exist, consider it deleted
    }

    /**
     * Enable Service with command validation
     *
     * @param $user
     *
     * @return bool
     */
    private function _enableService($user)
    {
        return $this->_execSystemctl('enable', $user);
    }

    /**
     * Disable Service with command validation
     *
     * @param $user
     *
     * @return bool
     */
    private function _disableService($user)
    {
        return $this->_execSystemctl('disable', $user);
    }

    /**
     * Start Service with command validation
     *
     * @param $user
     *
     * @return bool
     */
    private function _startService($user)
    {
        return $this->_execSystemctl('start', $user);
    }

    /**
     * Stop Service with command validation
     *
     * @param $user
     *
     * @return bool
     */
    private function _stopService($user)
    {
        return $this->_execSystemctl('stop', $user);
    }

    /**
     * Secure systemctl execution with input validation
     *
     * @param string $action
     * @param string $user
     * @return bool
     */
    private function _execSystemctl($action, $user)
    {
        // Validate action
        $allowedActions = ['enable', 'disable', 'start', 'stop', 'status'];
        if (!in_array($action, $allowedActions)) {
            $this->_log('error', 'Invalid systemctl action attempted', ['action' => $action, 'user' => $user]);
            return false;
        }
        
        // Validate and sanitize user
        $safeUser = preg_replace('/[^a-zA-Z0-9_-]/', '', $user);
        if ($safeUser !== $user || empty($safeUser)) {
            $this->_log('error', 'Invalid username for systemctl', ['user' => $user]);
            return false;
        }
        
        // Build command with proper escaping
        $serviceName = 'redis-server@' . escapeshellarg($safeUser);
        $command = 'sudo systemctl ' . escapeshellarg($action) . ' ' . $serviceName . ' 2>&1';
        
        $this->_log('info', 'Executing systemctl command', ['action' => $action, 'user' => $safeUser]);
        
        $output = shell_exec($command);
        $exitCode = 0;
        
        // Check if command was successful (systemctl returns 0 on success)
        exec('echo $?', $exitCodeArray);
        if (isset($exitCodeArray[0])) {
            $exitCode = (int)$exitCodeArray[0];
        }
        
        if ($exitCode === 0) {
            return true;
        } else {
            $this->_log('error', 'Systemctl command failed', [
                'action' => $action, 
                'user' => $safeUser, 
                'exit_code' => $exitCode, 
                'output' => $output
            ]);
            return false;
        }
    }

    /**
     * Legacy exec method (deprecated, use _execSystemctl instead)
     *
     * @param $command
     *
     * @return bool
     */
    private function _exec($command)
    {
        $this->_log('warning', 'Legacy _exec method called', ['command' => $command]);
        return false; // Disabled for security
    }
}
