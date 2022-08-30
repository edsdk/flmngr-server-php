<?php

/**
 * Flmngr Server package
 * Developer: N1ED
 * Website: https://n1ed.com/
 * License: GNU General Public License Version 3 or later
 **/

namespace EdSDK\FlmngrServer\model;

class FMDir {

  private $path; // contains parent dir's path WITHOUT starting AND trailing "/"

  private $name;

  public $f;

  public $d;

  public $p; // property exists in PHP version only, for JSON generation

  public $filled; // false if its children were not listed (dynamic listing is used and this is the last level dir)

  function __construct($name, $path, $filled) {
    $this->path = $path;
    $this->name = $name;
    $this->f = 0; // legacy
    $this->d = 0; // legacy
    $this->filled = $filled;

    $this->p =
      (strlen($this->path) > 0 ? '/' . $this->path : '') .
      '/' .
      $this->name;
  }
}
