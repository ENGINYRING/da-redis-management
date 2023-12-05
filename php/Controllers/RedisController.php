<?php
/**
 * Created by PhpStorm.
 * User: kevin
 * Date: 12/10/2016
 * Time: 21:13
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
        $this->_config   = require_once($this->_basePath.'/php/Config/main.php');

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
                $this->_userLimit = $this->_config['plugin']['userLimit'];
            }

            if(isset($this->_config['plugin']['unlimitedUsers']))
            {
                if (is_array($this->_config['plugin']['unlimitedUsers']))
                    $this->_unlimitedUsers = $this->_config['plugin']['unlimitedUsers'];
                else
                    $this->_unlimitedUsers = [$this->_config['plugin']['unlimitedUsers']];
            }

            if (file_exists($this->_basePath . '/' . $this->_config['plugin']['dataFile']))
            {
                $jsonContent = file_get_contents($this->_basePath . '/' . $this->_config['plugin']['dataFile']);

                if (@json_decode($jsonContent))
                {
                    $json = json_decode($jsonContent, TRUE);

                    if (isset($json['instances']))
                    {
                        $this->_instances = $json['instances'];
                    }
                }
            }
        }
        else
        {
            throw new \Exception('No config data available!');
        }
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
        if ($username)
        {
            if (isset($this->_instances[$username]))
            {
                return $this->_instances[$username];
            }
            else
            {
                return NULL;
            }
        }
        else
        {
            if($this->_instances)
            {
                return $this->_instances;
            }
            else
            {
                return NULL;
            }
        }
    }

    /**
     * Create Instance
     *
     * @param $username
     *
     * @return bool
     */
    public function createInstance($username)
    {
        // add instance
        if ($this->_addInstanceData($username))
        {
            // create instance config
            if ($this->_createInstanceConfig($username))
            {
                // save data
                if ($this->_saveData())
                {
                    // enable and start service
                    $this->_enableService($username);
                    $this->_startService($username);

                    return TRUE;
                }
            }
        }

        return FALSE;
    }

    /**
     * Delete Instance
     *
     * @param $username
     *
     * @return bool
     */
    public function deleteInstance($username)
    {
        $this->_disableService($username);
        $this->_stopService($username);

        if ($this->_deleteInstanceData($username))
        {
            if ($this->_deleteInstanceConfig($username))
            {
                // save data
                if ($this->_saveData())
                {
                    return TRUE;
                }
            }
        }

        return FALSE;
    }

    /**
     * Delete All User Instances
     *
     * @param $username
     * @return bool
     */
    public function deleteAllUserInstances($username)
    {
        if(isset($this->_instances[$username]) && !empty($this->_instances[$username]))
        {
            $this->deleteInstance($username);
            return TRUE;
        }
    }

    /**
     * Check User Reach the Limit or not
     */
    public function checkUserLimit($username)
    {
        return $this->_limit&&isset($this->_instances[$username])&&
            count($this->_instances[$username])>=$this->_userLimit&&
            !in_array($username,$this->_unlimitedUsers);
    }

    /**
     * Add Instance Data
     *
     * @param $username
     * @param $socket
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

        return TRUE;
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
        if (isset($this->_instances[$username]))
        {
            unset($this->_instances[$username]);
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Save
     *
     * @return bool
     */
    private function _saveData()
    {
        // prepare data
        $data = array(
            'instances'        => $this->_instances,
        );

        // encode data to json
        $json = json_encode($data);

        // determine data dir path
        $pathInfo = pathinfo($this->_basePath . '/' . $this->_config['plugin']['dataFile']);

        // check if data direcory already exists
        if (!is_dir($pathInfo['dirname']))
        {
            // create data directory
            mkdir($pathInfo['dirname'], 0755, TRUE);
        }

        // save json to file
        if (file_put_contents($this->_basePath . '/' . $this->_config['plugin']['dataFile'], $json))
        {
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Create Instance Config
     *
     * @param $user
     *
     * @return bool
     */
    private function _createInstanceConfig($user)
    {
        // get redis template contents
        if ($templateContent = file_get_contents($this->_basePath . '/php/Templates/redis-instance.conf'))
        {
            // replace variables with actual values
            $replaceTokens = array(
                '{{ user }}',
            );
            $replaceValues = array(
                $user,
            );
            $configContent = str_replace($replaceTokens, $replaceValues, $templateContent);

            // check if redis instance config dir needs to be created
            if (!is_dir($this->_config['redis']['configDir'].'/'))
            {
                mkdir($this->_config['redis']['configDir'].'/', 0755);
                chown($this->_config['redis']['configDir'].'/', $this->_config['redis']['user']);
                chgrp($this->_config['redis']['configDir'].'/', $this->_config['redis']['user']);
            }

            // save config file
            if (file_put_contents($this->_config['redis']['configDir'] . '/' . $user . '.conf', $configContent))
            {
                chmod($this->_config['redis']['configDir'] . '/' . $user . '.conf',0644);
                return TRUE;
            }
        }

        return FALSE;
    }

    /**
     * Delete Instance Config
     *
     * @param $user
     *
     * @return bool
     */
    public function _deleteInstanceConfig($user)
    {
        if (file_exists($this->_config['redis']['configDir'] . '/' . $user . '.conf'))
        {
            unlink($this->_config['redis']['configDir'] . '/' . $user . '.conf');
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Enable Service
     *
     * @param $user
     *
     * @return bool
     */
    public function _enableService($user)
    {
        return $this->_exec('sudo systemctl enable redis@' . $user);
    }

    /**
     * Disable Service
     *
     * @param $user
     *
     * @return bool
     */
    public function _disableService($user)
    {
        return $this->_exec('sudo systemctl disable redis@' . $user);
    }

    /**
     * Start Service
     *
     * @param $user
     *
     * @return bool
     */
    public function _startService($user)
    {
        return $this->_exec('sudo systemctl start redis@' . $user);
    }

    /**
     * Stop Service
     *
     * @param $user
     *
     * @return bool
     */
    public function _stopService($user)
    {
        return $this->_exec('sudo systemctl stop redis@' . $user);
    }

    /**
     * Exec
     *
     * @param $command
     *
     * @return bool
     */
    public function _exec($command)
    {
        if ($output = shell_exec($command))
        {
            return $output;
        }

        return FALSE;
    }
}