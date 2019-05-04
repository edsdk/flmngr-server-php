<?php

namespace EdSDK\FlmngrServer\fs;

use EdSDK\FileUploaderServer\lib\action\resp\Message;
use EdSDK\FileUploaderServer\lib\MessageException;
use EdSDK\FlmngrServer\model\FMDir;
use EdSDK\FlmngrServer\model\FMFile;
use EdSDK\FlmngrServer\model\FMMessage;
use EdSDK\FlmngrServer\model\ImageInfo;

class FMDiskFileSystem {

    private $dirFiles;
    private $dirCache;

    function __construct($config) {
        $this->dirFiles = $config['dirFiles'];
        $this->dirCache = $config['dirCache'];
    }

    function getDirs() {
        $dirs = [];
        $fDir = $this->dirFiles;
        if (!file_exists($fDir) || !is_dir($fDir))
            throw new MessageException(FMMessage::createMessage(FMMessage::FM_ROOT_DIR_DOES_NOT_EXIST));

        $this->getDirs__fill($dirs, $fDir, "");
        return $dirs;
    }

    private function getDirs__fill(&$dirs, $fDir, $path) {
        $files = scandir($fDir);

        if ($files === FALSE)
            throw new MessageException(FMMessage::createMessage(FMMessage::FM_UNABLE_TO_LIST_CHILDREN_IN_DIRECTORY));

        $dirsCount = 0;
        $filesCount = 0;
        for ($i=0; $i<count($files); $i++) {
            $file = $files[$i];
            if ($file === '.' || $file === '..')
                continue;
            if (is_file($fDir . $file))
                $filesCount ++;
            else if (is_dir($fDir . $file))
                $dirsCount ++;
        }

        $dir = new FMDir($fDir, $path, $filesCount, $dirsCount);
        $dirs[] = $dir;

        for ($i=0; $i<count($files); $i++)
            if ($files[$i] !== '.' && $files[$i] !== ' ..')
                if (is_dir($fDir . $files[$i]))
                    $this->getDirs__fill($dirs, $fDir . '/' . $files[$i], $path . (strlen($path) > 0 ? "/" : "") . $files[$i]);
    }

    private function getRelativePath($path) {
        if (strpos($path, "..") !== FALSE)
            throw new MessageException(FMMessage::createMessage(FMMessage::FM_DIR_NAME_CONTAINS_INVALID_SYMBOLS));

        $rootDirName = $this->getRootDirName();
        if (strpos($path, "/" . $rootDirName) !== 0)
            throw new MessageException(FMMessage::createMessage(FMMessage::FM_DIR_NAME_INCORRECT_ROOT));

        return substr($path, strlen("/" . $rootDirName));
    }

    private function getAbsolutePath($path) {
        return $this->dirFiles . "/" . $this->getRelativePath($path);
    }

    function deleteDir($dirPath) {
        $fullPath = $this->getAbsolutePath($dirPath);
        $res = unlink($fullPath);
        if ($res === FALSE)
            throw new MessageException(FMMessage::createMessage(FMMessage::FM_UNABLE_TO_DELETE_DIRECTORY));
    }

    function createDir($dirPath, $name) {
        if (strpos($name, "..") !== FALSE || strpos($name, "/") !== FALSE)
            throw new MessageException(FMMessage::createMessage(FMMessage::FM_DIR_NAME_CONTAINS_INVALID_SYMBOLS));

        $fullPath = $this->getAbsolutePath($dirPath) . "/" . $name;
        $res = mkdir($fullPath, 0777, TRUE);
        if ($res === FALSE)
            throw new MessageException(FMMessage::createMessage(FMMessage::FM_UNABLE_TO_CREATE_DIRECTORY));
    }

    private function renameFileOrDir($path, $newName) {

        if (strpos($newName, "..") !== FALSE || strpos($newName, "/") !== FALSE)
            throw new MessageException(FMMessage::createMessage(FMMessage::FM_DIR_NAME_CONTAINS_INVALID_SYMBOLS));

        $fullPath = $this->getAbsolutePath($path);

        $i = strrpos($fullPath, "/");
        $fullPathDst = substr($fullPath, 0, $i + 1) . $newName;
        if (is_file($fullPathDst))
            throw new MessageException(Message::createMessage(Message::FILE_ALREADY_EXISTS, $newName));

        $res = rename($fullPath, $fullPathDst);
        if ($res === FALSE)
            throw new MessageException(FMMessage::createMessage(FMMessage::FM_UNABLE_TO_RENAME));
    }

    public function renameFile($filePath, $newName) {
        $this->renameFileOrDir($filePath, $newName);
    }

    public function renameDir($dirPath, $newName) {
        $this->renameFileOrDir($dirPath, $newName);
    }

    public function getFiles($dirPath) { // with "/root_dir_name" in the start

        $fullPath = $this->getAbsolutePath($dirPath);

        if (!is_dir($fullPath))
            throw new MessageException(Message::createMessage(Message::DIR_DOES_NOT_EXIST, $dirPath));

        $fFiles = scandir($fullPath);
        if ($fFiles === FALSE)
            throw new MessageException(FMMessage::createMessage(FMMessage::FM_DIR_CANNOT_BE_READ));

        $files = [];
        for ($i=0; $i<count($fFiles); $i++) {
            $fFile = $fFiles[$i];
            if (is_file($fFile)) {
                $imageInfo = $this->getImageInfo($fFile);
                $file = new FMFile($dirPath, $fFile, filesize($fFile), filemtime($fFile), $imageInfo);
                $files[] = $file;
            }
        }

        return $files;
    }

    private static function getImageInfo($file) {

        $size = getimagesize($file);
        if ($size === FALSE)
            throw new MessageException(Message::createMessage(Message::IMAGE_PROCESS_ERROR));

        $imageInfo = new ImageInfo();
        $imageInfo->width = $size[0];
        $imageInfo->height = $size[1];
        return $imageInfo;
    }

    private function getRootDirName() {
        $i = strrpos($this->dirFiles, "/");
        if ($i === FALSE)
            return $this->dirFiles;
        return substr($this->dirFiles, $i + 1);
    }

    private function resizeImage($img, $width, $height) {
        // TODO:
    }

    function deleteFiles($filesPaths) {
        for ($i=0; $i<count($filesPaths); $i++) {
            $fullPath = $this->getAbsolutePath($filesPaths[$i]);
            $res = unlink($fullPath);
            if ($res !== FALSE)
                throw new MessageException(Message::createMessage(Message::UNABLE_TO_DELETE_FILE, $filesPaths[$i]));
        }
    }

    function copyFiles($filesPaths, $newPath) {
        for ($i=0; $i<count($filesPaths); $i++) {
            $fullPathSrc = $this->getAbsolutePath($filesPaths[$i]);

            $index = strrpos($fullPathSrc, "/");
            $name = $index === FALSE ? $fullPathSrc : substr($fullPathSrc, $index + 1);
            $fullPathDst = $this->getAbsolutePath($newPath) . "/" . $name;

            $res = copy($fullPathSrc, $fullPathDst);
            if ($res === FALSE)
                throw new MessageException(FMMessage::createMessage(FMMessage::FM_ERROR_ON_COPYING_FILES));
        }
    }

    function moveFiles($filesPaths, $newPath) {
        for ($i=0; $i<count($filesPaths); $i++) {
            $fullPathSrc = $this->getAbsolutePath($filesPaths[$i]);

            $index = strrpos($fullPathSrc, "/");
            $name = $index === FALSE ? $fullPathSrc : substr($fullPathSrc, $index + 1);
            $fullPathDst = $this->getAbsolutePath($newPath) . "/" . $name;

            $res = rename($fullPathSrc, $fullPathDst);
            if ($res === FALSE)
                throw new MessageException(FMMessage::createMessage(FMMessage::FM_ERROR_ON_MOVING_FILES));
        }
    }

    function moveDir($dirPath, $newPath) {
        $fullPathSrc = $this->getAbsolutePath($dirPath);

        $index = strrpos($fullPathSrc, "/");
        $name = $index === FALSE ? $fullPathSrc : substr($fullPathSrc, $index + 1);
        $fullPathDst = $this->getAbsolutePath($newPath) . "/" . $name;

        $res = rename($fullPathSrc, $fullPathDst);
        if ($res === FALSE)
            throw new MessageException(FMMessage::createMessage(FMMessage::FM_ERROR_ON_MOVING_FILES));
    }

    function copyDir($dirPath, $newPath) {
        $fullPathSrc = $this->getAbsolutePath($dirPath);

        $index = strrpos($fullPathSrc, "/");
        $name = $index === FALSE ? $fullPathSrc : substr($fullPathSrc, $index + 1);
        $fullPathDst = $this->getAbsolutePath($newPath) . "/" . $name;

        $res = $this->copyDir__recurse($fullPathSrc, $fullPathDst);
        if ($res === FALSE)
            throw new MessageException(FMMessage::createMessage(FMMessage::FM_ERROR_ON_MOVING_FILES));
    }

    private function copyDir__recurse($src,$dst) {
        $dir = opendir($src);
        mkdir($dst);
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if ( is_dir($src . '/' . $file) ) {
                    $res = $this->copyDir__recurse($src . '/' . $file,$dst . '/' . $file);
                    if ($res === FALSE)
                        return FALSE;
                }
                else {
                    $res = copy($src . '/' . $file,$dst . '/' . $file);
                    if ($res === FALSE)
                        return FALSE;
                }
            }
        }
        closedir($dir);
        return TRUE;
    }

}
