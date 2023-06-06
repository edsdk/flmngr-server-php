<?php

/**
 *
 * Flmngr server package for PHP.
 *
 * This file is a part of the server side implementation of Flmngr -
 * the JavaScript/TypeScript file manager widely used for building apps and editors.
 *
 * Comes as a standalone package for custom integrations,
 * and as a part of N1ED web content builder.
 *
 * Flmngr file manager:       https://flmngr.com
 * N1ED web content builder:  https://n1ed.com
 * Developer website:         https://edsdk.com
 *
 * License: GNU General Public License Version 3 or later
 *
 **/

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
