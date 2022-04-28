<?php

namespace EdSDK\FlmngrServer\fs;

use EdSDK\FlmngrServer\lib\action\resp\Message;
use EdSDK\FlmngrServer\lib\file\Utils;
use EdSDK\FlmngrServer\lib\MessageException;
use EdSDK\FlmngrServer\model\FMMessage;
use Mockery\Exception;

class DriverLocal {

    private $dir;

    function __construct($config)
    {
        $this->dir = rtrim($config['dir'], '\\/');
    }

    function getDriverName() {
        return "Local";
    }

    function size($path)
    {
        return filesize($this->dir . $path);
    }

    function lastModified($path)
    {
        return filemtime($this->dir . $path);
    }

    function makeDirectory($path)
    {
        $result = mkdir($this->dir . $path, 0777, TRUE);
        if (!$result) {
            throw new MessageException(
                FMMessage::createMessage(
                    FMMessage::FM_UNABLE_TO_CREATE_DIRECTORY,
                    $path
                )
            );
        }
    }

    function getRootDirName()
    {
        $i = strrpos($this->dir, '/');
        if ($i === false) {
            return $this->dir;
        }
        return substr($this->dir, $i + 1);
    }

    function makeRootDir()
    {
        if (!$this->directoryExists("")) {
            if (!$this->makeDirectory("")) {
                error_log("Unable to create a directory: " . $this->dir);

                ob_start();
                debug_print_backtrace();
                $trace = ob_get_contents();
                ob_end_clean();
                error_log($trace);

                throw new MessageException(
                    FMMessage::createMessage(
                        FMMessage::FM_UNABLE_TO_CREATE_DIRECTORY,
                        $this->dir
                    )
                );
            }
        }
    }

    const MAX_DEPTH = 20;
    function allDirectories()
    {
        $dirs = [];
        $fDir = $this->dir;

        if (!file_exists($fDir) || !is_dir($fDir)) {
            throw new MessageException(
                FMMessage::createMessage(FMMessage::FM_ROOT_DIR_DOES_NOT_EXIST)
            );
        }
        $hideDirs[] = '.cache';

        $this->getDirs__fill($dirs, $fDir, $hideDirs, '', 0);
        return $dirs;
    }

    private function getDirs__fill(&$dirs, $fDir, $hideDirs, $path, $currDepth)
    {
        $i = strrpos($fDir, '/');
        if ($i !== false) {
            $dirName = substr($fDir, $i + 1);
        } else {
            $dirName = $fDir;
        }

        $dirs[] = (strlen($path) > 0 ? '/' . $path : '') . '/' . $dirName;

        $rawDirs = glob($fDir . '/*', GLOB_ONLYDIR);
        if ($rawDirs === FALSE) {
            throw new MessageException(
                FMMessage::createMessage(
                    FMMessage::FM_UNABLE_TO_LIST_CHILDREN_IN_DIRECTORY
                )
            );
        }

        foreach($rawDirs as $dir) {
            $dir = str_replace($fDir . '/', '', $dir);

            $isHide = FALSE;
            for ($j = 0; $j < count($hideDirs) && !$isHide; $j ++)
                $isHide = $isHide || fnmatch($hideDirs[$j], $dir);

            if (is_dir($fDir . '/' . $dir) && !$isHide && $currDepth < self::MAX_DEPTH) {
                $this->getDirs__fill(
                    $dirs,
                    $fDir . '/' . $dir,
                    $hideDirs,
                    $path . (strlen($path) > 0 ? '/' : '') . $dirName,
                    $currDepth + 1
                );
            }
        }
    }

    function directories($path)
    {
        $dirs = [];
        try {
            $rawDirs = glob($this->dir . $path . '/*', GLOB_ONLYDIR);
        } catch (Exception $e) {
            throw new MessageException(
                Message::createMessage(Message::DIR_DOES_NOT_EXIST, $path)
            );
        }
        foreach($rawDirs as $dir) {
            $dirs[] = str_replace($this->dir . $path . '/', '', $dir);
        }
        return $dirs;
    }

    function files($path)
    {
        try {
            $rawFiles = glob($this->dir . $path . '/*');
        } catch (Exception $e) {
            error_log("Error while reading dir contents: " . $path);
            error_log($e);
            throw new MessageException(
                FMMessage::createMessage(FMMessage::FM_DIR_CANNOT_BE_READ)
            );
        }

        $files = [];
        foreach ($rawFiles as $file)
            if (is_file($file))
                $files[] = basename($file);
        return $files;
    }

    function move($path, $newName)
    {
        $res = rename($this->dir . $path, $this->dir . $newName);
        if ($res === false) {
            throw new MessageException(
                FMMessage::createMessage(FMMessage::FM_UNABLE_TO_RENAME)
            );
        }
    }

    function getMimeType($path)
    {
        return Utils::getMimeType($this->dir . $path);
    }

    function exists($path)
    {
        return file_exists($this->dir . $path);
    }

    function directoryExists($path)
    {
        return file_exists($this->dir . $path) && is_dir($this->dir . $path);
    }

    function fileExists($path)
    {
        return file_exists($this->dir . $path) && is_file($this->dir . $path);
    }

    // Get file contents
    function get($path)
    {
        return file_get_contents($this->dir . $path);
    }

    // Put file contents
    function put($path, $contents)
    {
        return file_put_contents($this->dir . $path, $contents);
    }

    // Dir (not empty) or file
    function delete($path)
    {
        if (is_file($this->dir . $path)) {
            $result = @unlink($this->dir . $path);
            if ($result === FALSE) {
                throw new MessageException(
                    Message::createMessage(
                        Message::UNABLE_TO_DELETE_FILE,
                        $path
                    )
                );
            }
        } else {
            foreach ($this->directories($path) as $dir)
                $this->delete($path . '/' . $dir);

            foreach ($this->files($path) as $file)
                $this->delete($path . '/' . $file);

            $this->deleteDirectory($path);
        }
    }

    // Delete empty dir
    function deleteDirectory($path)
    {
        $result = rmdir($this->dir . $path);
        if ($result === FALSE) {
            throw new MessageException(
                FMMessage::createMessage(
                    FMMessage::FM_UNABLE_TO_DELETE_DIRECTORY
                )
            );
        }
    }

    // Returns stream for `fpassthru`
    function readStream($path)
    {
        return fopen($this->dir . $path, 'rb');
    }

    function copyFile($pathSrc, $pathDst)
    {
        $res = copy($this->dir . $pathSrc, $this->dir . $pathDst);
        if ($res === FALSE) {
            throw new MessageException(
                FMMessage::createMessage(
                    FMMessage::FM_ERROR_ON_COPYING_FILES
                )
            );
        }
    }

    function copyDirectory($src, $dst)
    {
        $this->copyDirectory__recurse($src, $dst, false);
    }

    private function copyDirectory__recurse($src, $dst, $createThisDstDir)
    {
        // Do not create a root directory (target directory to copy inside already exists)
        if ($createThisDstDir)
            $this->makeDirectory($dst);

        $fFiles = $this->files($src);
        foreach ($fFiles as $file) {
            if ($file != '.' && $file != '..') {
                if ($this->directoryExists(true, $src . '/' . $file)) {
                    $this->copyDir__recurse(
                        $src . '/' . $file,
                        $dst . '/' . $file,
                        true
                    );
                } else {
                    $this->copyFile($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
    }

    function uploadFile($file, $dir) {

        // Get free file name
        $i = -1;
        do {
            $i++;
            if ($i == 0) {
                $name = $file['name'];
            } else {
                $name =
                    Utils::getNameWithoutExt($file['name']) .
                    '_' .
                    $i .
                    (Utils::getExt($file['name']) != null
                        ? '.' . Utils::getExt($file['name'])
                        : '');
            }
            $filePath = $dir . '/' . $name;
            $ok = !$this->exists($filePath);
        } while (!$ok);

        $result = move_uploaded_file($file['tmp_name'], $this->dir . $dir . '/' . $name);
        if (!$result) {
            throw new MessageException(
                Message::createMessage(
                    Message::WRITING_FILE_ERROR,
                    $dir . '/' . $file['name']
                )
            );
        }

        return $name;
    }

}