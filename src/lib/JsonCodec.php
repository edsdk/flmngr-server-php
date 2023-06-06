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

class JsonCodec {

  protected $m_actions;

  public function __construct($actions) {
    $this->m_actions = $actions;
  }

  public function fromJson($json) {
    $json = stripslashes($json);
    $jsonObj = json_decode($json, FALSE);
    if ($jsonObj === NULL) {
      throw new Exception('Unable to parse JSON');
    }
    if (!isset($jsonObj->action)) {
      throw new Exception('"Unable to detect action in JSON"');
    }
    $action = $this->m_actions->getAction($jsonObj->action);
    if ($action === NULL) {
      throw new Exception('JSON action is incorrect: ' . $action);
    }
    return $jsonObj;
  }

  public function toJson($resp) {
    return JsonCodec::s_toJson($resp);
  }

  public static function s_toJson($resp) {
    $json = json_encode($resp);
    $json = str_replace('\\u0000*\\u0000', '', $json);
    $json = str_replace('\\u0000', '', $json);
    return $json;
  }
}
