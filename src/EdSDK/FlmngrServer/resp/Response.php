<?php

namespace EdSDK\FlmngrServer;

class Response {

    public $error;
    public $data;

    function __construct($message, $data) {
        $this->error = $message;
        $this->data = $data;
    }

}
