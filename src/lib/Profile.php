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

class Profile {

    protected $title;
    protected $now;
    protected $start;

    public function __construct($functionTitle) {
        $this->title = $functionTitle;
        $this->now = microtime(TRUE);
        $this->start = $this->now;
    }

    public function profile($text) {
        $now = microtime(TRUE);
        $time = $now - $this->now;
        //error_log($this->title . ": " . number_format($time, 3, ",", "") . " sec   " . $text . "\n");
        $this->now = $now;
    }

    public function total() {
        $now = microtime(TRUE);
        $time = $now - $this->start;
        //error_log($this->title . ": " . number_format($time, 3, ",", "") . " sec   TOTAL\n");
    }

}