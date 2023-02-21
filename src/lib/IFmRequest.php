<?php

namespace EdSDK\FlmngrServer\lib;

abstract class IFmRequest {

  public $post;

  public $get;

  public $files;

  public $requestMethod;

  public $config;

  abstract public function parseRequest();

  public function __construct($config = NULL) {
    $this->config = $config;
  }
}
