<?php
namespace EdSDK\FlmngrServer\lib;

use EdSDK\FlmngrServer\lib\IFmRequest;

class FMRequestCommon extends IFmRequest
{
    public function parseRequest()
    {
        $this->requestMethod = $_SERVER['REQUEST_METHOD'];
        $this->files = $_FILES;
        $this->post = $_POST;
        $this->get = $_GET;
    }
}
