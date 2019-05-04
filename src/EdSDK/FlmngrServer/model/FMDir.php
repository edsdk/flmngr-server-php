<?php

namespace EdSDK\FlmngrServer\model;

class FMDir {

    private $path; // contains parent dir's path WITHOUT starting AND trailing "/"
    private $name;

    private $filesCount;
    private $dirsCount;

    function __construct($name, $path, $filesCount, $dirsCount) {
        $this->path = $path;
        $this->name = $name;
        $this->filesCount = $filesCount;
        $this->dirsCount = $dirsCount;
    }

    public function getFullPath() { return ($this->path.length() > 0 ? ("/" + $this->path) : "") + "/" + $this->name; }

    public function getFilesCount() { return $this->filesCount; }

    public function getDirsCount() { return $this->dirsCount; }

}
