<?php
namespace EdSDK\FlmngrServer\lib;

abstract class IFmRequest
{
    public array $post;

    public array $get;

    public array $files;

    public string $requestMethod;

    abstract public function parseRequest();
}
