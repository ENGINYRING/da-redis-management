<?php

global $_POST, $_GET;

parse_str(getenv('QUERY_STRING'), $_GET);
parse_str(getenv('POST'), $_POST);

require_once dirname(__DIR__) . '/php/Controllers/RedisController.php';
?>
<link href="https://cdn.bootcss.com/twitter-bootstrap/4.4.1/css/bootstrap.min.css" rel="stylesheet">
<style>
    #iframe-container {
        display: inline-block;
        padding: 1rem;
        width: 100vw;
        overflow: auto;
    }
</style>