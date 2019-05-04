<?php

namespace EdSDK\FlmngrServer\resp;

class Response {

    public $error;
    public $data;

    function __construct($message, $data) {
        $this->error = $message;
        $this->data = $data;
    }

}
