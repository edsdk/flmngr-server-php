<?php

namespace EdSDK\FlmngrServer\model;

class FMFile {

    public $p; // contains parent dir's path WITHOUT starting AND trailing "/"

    public $s;
    public $t;
    public $w;
    public $h;

    function __construct($path, $name, $size, $timeModification, $imageInfo) {
        $this->p = "/" . $path . "/" . $name;
        $this->s = $size;
        $this->t = $timeModification;
        $this->w = $imageInfo->width == 0 ? null : $imageInfo->width;
        $this->h = $imageInfo->height == 0 ? null : $imageInfo->height;
    }

    public function getFullPath() { return ; }

}
