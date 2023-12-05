<?php
/**
 * Created by PhpStorm.
 * User: kevin
 * Date: 12/10/2016
 * Time: 21:13
 */

return [
    'plugin' => [
        'dataFile'  => 'data/instances.json',
        'path' => 'tmp',
    ],
    'redis' => [
        'user'      => 'redis',
        'group'     => 'redis',
        'configDir' => '/etc/redis/instances',
    ],
];