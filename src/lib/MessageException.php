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

use Exception;

class MessageException extends Exception {

  protected $message;
  protected $sourceException;

  public function __construct($message, $sourceException = NULL) {
    parent::__construct();
    $this->message = (array) $message;
    $this->sourceException = $sourceException;
    if ($sourceException == NULL) {
      try {
        throw new Exception("(manually created exception)");
      } catch (Exception $e) {
        $this->sourceException = $e;
      }
    }
  }

  public function getFailMessage() {
    return $this->message;
  }

  public function getSourceException() {
    return $this->sourceException;
  }

}
