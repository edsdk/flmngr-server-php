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
