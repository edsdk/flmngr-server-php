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

use EdSDK\FlmngrServer\lib\IFmRequest;

class CommonRequest extends IFmRequest {

  public function parseRequest() {
    $this->requestMethod = $_SERVER['REQUEST_METHOD'];
    $this->files = $_FILES;
    $this->post = $_POST;
    $this->get = $_GET;
  }
}
