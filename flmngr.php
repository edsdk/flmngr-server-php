<?php

require __DIR__ . '/vendor/autoload.php';

use EdSDK\FlmngrServer\FlmngrServer;

FlmngrServer::flmngrRequest(
    array(
        'dirFiles' => 'files',
        'dirTmp'   => 'tmp',
        'dirCache' => 'cache'
    )
);