<?php
namespace EdSDK\FlmngrServer;
use EdSDK\FlmngrServer\lib\CommonRequest;
use EdSDK\FlmngrServer\lib\IFmRequest;
class FlmngrFrontController
{
    public IFmRequest $request;

    public function __construct()
    {
        $request = new CommonRequest();
        $request->parseRequest();

        $this->request = $request;
    }
}

?>
