<?php

namespace EdSDK\FlmngrServer\model;

class FMFile {

    private $path; // contains parent dir's path WITHOUT starting AND trailing "/"
    private $name;

    private $size;
    private $timeModification;
    private $imageInfo;

    function __construct($path, $name, $size, $timeModification, $imageInfo) {
        $this->path = $path;
        $this->name = $name;
        $this->size = $size;
        $this->timeModification = $timeModification;
        $this->imageInfo = $imageInfo;
    }

    public function getFullPath() { return "/" . $this->path . "/" . $this->name; }

    public function getSize() { return $this->size; }

    public function getTimeModification() { return $this->timeModification; }

    public function getImgWidth() { return $this->imageInfo->width == 0 ? null : $this->imageInfo->width; }

    public function getImgHeight() { return $this->imageInfo->height == 0 ? null : $this->imageInfo->height; }

}
