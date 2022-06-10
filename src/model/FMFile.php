<?php

/**
 * Flmngr Server package
 * Developer: N1ED
 * Website: https://n1ed.com/
 * License: GNU General Public License Version 3 or later
 **/

namespace EdSDK\FlmngrServer\model;

class FMFile {

  public $p; // contains parent dir's path WITHOUT starting AND trailing "/"

  public $s;

  public $t;

  public $w;

  public $h;

  function __construct($path, $name, $cachedImageInfo) {
    $this->p = "/" . $path . "/" . $name;
    $this->s = $cachedImageInfo['size'];
    $this->t = $cachedImageInfo['mtime'];
    $this->w = $cachedImageInfo['width'] == 0 ? NULL : $cachedImageInfo['width'];
    $this->h = $cachedImageInfo['height'] == 0 ? NULL : $cachedImageInfo['height'];
  }

}
