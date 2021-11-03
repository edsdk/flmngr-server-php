<?php
namespace EdSDK\FlmngrServer\lib;

use EdSDK\FlmngrServer\lib\IFmRequest;

class CommonRequest extends IFmRequest
{
    public function parseRequest()
    {
        $request = $this->config['drupalRequestStack']->getCurrentRequest()
            ->request;
        $this->requestMethod = $request->getMethod();
        $this->files = $_FILES;
        $this->post = $request->request;
        $this->get = $request->query;
    }
}
