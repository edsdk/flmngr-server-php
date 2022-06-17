<?php

namespace EdSDK\FlmngrServer\fs;

use EdSDK\FlmngrServer\model\Message;
use EdSDK\FlmngrServer\lib\file\Utils;
use EdSDK\FlmngrServer\lib\MessageException;

class DriverLocal {

  private $dir;

  private $isCacheDriver;

  // Link to cache driver
  // NULL if we are inside cache driver instance
  private $driverCache;

  // Some cached info (array of named chunks)
  // Access it only by getCacheChunk() and write by setCacheChunk()
  private $cacheChunks = [];

  // For use by FileSystem.php only, do not override
  public function setDriverCache($driverCache) {
    $this->driverCache = $driverCache;
  }

  function __construct($config, $isCacheDriver = FALSE) {
    $this->isCacheDriver = $isCacheDriver;

    if (!in_array('dir', array_keys($config)) || $config['dir'] === NULL) {
      try {
        throw new MessageException(
          Message::createMessage(
            $this->isCacheDriver,
            Message::FM_ROOT_DIR_IS_NOT_SET
          )
        );
      } catch (Exception $e) {
        error_log(print_r($e, TRUE));
      }
    }

    $this->dir = rtrim($config['dir'], '\\/');

    $this->makeRootDir();

    if (!is_readable($this->dir)) {
      throw new MessageException(
        Message::createMessage(
          $this->isCacheDriver,
          Message::FM_DIR_IS_NOT_READABLE,
          $this->dir
        )
      );
    }

    if (!is_writable($this->dir)) {
      throw new MessageException(
        Message::createMessage(
          $this->isCacheDriver,
          Message::FM_DIR_IS_NOT_WRITABLE,
          $this->dir
        )
      );
    }

  }

  private function getCacheChunkPath($chunkName) {
    return "/fs/" . $chunkName . ".json";
  }

  // Returns "path-to-cache/fs/driver-name/chunk-name.json" content
  // i. e. ".cache/fs/all-files.json"
  // if file modify time is not older then $validSeconds
  // Returns NULL if file does not exist of outdated
  function &getCacheChunk($chunkName, $validSeconds) {

    if (in_array($chunkName, $this->cacheChunks)) {
      return $this->cacheChunks[$chunkName];
    }

    $chunkPath = $this->getCacheChunkPath($chunkName);
    if ($this->driverCache->fileExists($chunkPath) && time() - $this->driverCache->lastModified($chunkPath) <= $validSeconds) {
      $content = $this->driverCache->get($chunkPath);
      $json = json_decode($content, JSON_OBJECT_AS_ARRAY);
      return $json;
    }
    else {
      $null = NULL;
      return $null;
    }

  }

  function setCacheChunk($chunkName, &$json) {
    $chunkPath = $this->getCacheChunkPath($chunkName);
    $chunkPathDir = dirname($chunkPath);
    $this->driverCache->makeDirectory($chunkPathDir, 0777, TRUE);
    $this->driverCache->put($chunkPath, json_encode($json, JSON_PRETTY_PRINT));
  }

  function deleteCacheChunk($chunkName) {
    try {
      $chunkPath = $this->getCacheChunkPath($chunkName);
      if ($this->driverCache->fileExists($chunkPath)) {
        $this->driverCache->delete($chunkPath);
      }
    } catch (Exception $e) {
      error_log("Error on deleting cache chunk");
      error_log($e);
    }
  }

  function getDriverName() {
    return "Local";
  }

  function getDir() {
    return $this->dir;
  }

  function size($path) {
    return filesize($this->dir . $path);
  }

  function lastModified($path) {
    return filemtime($this->dir . $path);
  }

  function makeDirectory($path) {

    if (file_exists($this->dir . $path) && is_dir($this->dir . $path)) {
      return;
    }

    $result = mkdir($this->dir . $path, 0777, TRUE);
    if (!$result) {
      throw new MessageException(
        Message::createMessage(
          $this->isCacheDriver,
          Message::FM_UNABLE_TO_CREATE_DIRECTORY,
          $path
        )
      );
    }
  }

  function getRootDirName() {
    $i = strrpos($this->dir, '/');
    if ($i === FALSE) {
      return $this->dir;
    }
    return substr($this->dir, $i + 1);
  }

  function makeRootDir() {
    if (!$this->directoryExists("")) {
      $this->makeDirectory("");
    }
  }

  const MAX_DEPTH = 20;

  function allDirectories() {
    $dirs = [];
    $fDir = $this->dir;

    if (!file_exists($fDir) || !is_dir($fDir)) {
      error_log("Root directory does not exist: " . $fDir);
      throw new MessageException(
        Message::createMessage(
          $this->isCacheDriver,
          Message::FM_ROOT_DIR_DOES_NOT_EXIST
        )
      );
    }
    $hideDirs[] = '.cache';

    $this->getDirs__fill($dirs, $fDir, $hideDirs, '', 0);
    return $dirs;
  }

  private function getDirs__fill(&$dirs, $fDir, $hideDirs, $path, $currDepth) {
    $i = strrpos($fDir, '/');
    if ($i !== FALSE) {
      $dirName = substr($fDir, $i + 1);
    }
    else {
      $dirName = $fDir;
    }

    $dirs[] = (strlen($path) > 0 ? '/' . $path : '') . '/' . $dirName;

    $rawDirs = glob($fDir . '/*', GLOB_ONLYDIR);
    if ($rawDirs === FALSE) {
      throw new MessageException(
        Message::createMessage(
          $this->isCacheDriver,
          Message::FM_UNABLE_TO_LIST_CHILDREN_IN_DIRECTORY
        )
      );
    }

    foreach ($rawDirs as $dir) {
      $dir = str_replace($fDir . '/', '', $dir);

      $isHide = FALSE;
      for ($j = 0; $j < count($hideDirs) && !$isHide; $j++) {
        $isHide = $isHide || fnmatch($hideDirs[$j], $dir);
      }

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

  function directories($path) {
    $dirs = [];
    try {
      $rawDirs = glob($this->dir . $path . '/*', GLOB_ONLYDIR);
    } catch (Exception $e) {
      throw new MessageException(
        Message::createMessage(
          $this->isCacheDriver,
          Message::DIR_DOES_NOT_EXIST,
          $path
        )
      );
    }
    foreach ($rawDirs as $dir) {
      $dirs[] = str_replace($this->dir . $path . '/', '', $dir);
    }
    return $dirs;
  }

  function files($path) {
    try {
      $rawFiles = glob($this->dir . $path . '/*');
    } catch (Exception $e) {
      error_log("Error while reading dir contents: " . $path);
      error_log($e);
      throw new MessageException(
        Message::createMessage(
          $this->isCacheDriver,
          Message::FM_DIR_CANNOT_BE_READ
        )
      );
    }

    $files = [];
    foreach ($rawFiles as $file) {
      if (is_file($file)) {
        $filename = basename($file);
        $files[] = [
          'name' => $filename,
          'mtime' => $this->lastModified($path . '/' . $filename),
          'size' => $this->size($path . '/' . $filename),
        ];
      }
    }
    return $files;
  }

  function move($path, $newName) {
    $res = rename($this->dir . $path, $this->dir . $newName);
    if ($res === FALSE) {
      throw new MessageException(
        Message::createMessage(
          $this->isCacheDriver,
          Message::FM_UNABLE_TO_RENAME
        )
      );
    }
  }

  function getMimeType($path) {
    return Utils::getMimeType($this->dir . $path);
  }

  function exists($path) {
    return file_exists($this->dir . $path);
  }

  function directoryExists($path) {
    return file_exists($this->dir . $path) && is_dir($this->dir . $path);
  }

  function fileExists($path) {
    return file_exists($this->dir . $path) && is_file($this->dir . $path);
  }

  // Get file contents
  function get($path) {
    return file_get_contents($this->dir . $path);
  }

  // Put file contents
  function put($path, $contents) {
    $this->makeDirectory(dirname($path)); // ensure dir exists
    file_put_contents($this->dir . $path, $contents);
  }

  // Dir (not empty) or file
  function delete($path) {
    if (is_file($this->dir . $path)) {
      $result = @unlink($this->dir . $path);
      if ($result === FALSE) {
        throw new MessageException(
          Message::createMessage(
            $this->isCacheDriver,
            Message::UNABLE_TO_DELETE_FILE,
            $path
          )
        );
      }

    }
    else {

      // There can be a try to delete unexisting file (in cache dir),
      // so we will have is_file() == false and fall here.
      // So there is required to check does this dir actually exists.
      if (is_dir($this->dir . $path)) {

        foreach ($this->directories($path) as $dir) {
          $this->delete($path . '/' . $dir);
        }

        foreach ($this->files($path) as $file) {
          $this->delete($path . '/' . $file['name']);
        }

        $this->deleteDirectory($path);

      }
    }
  }

  // Delete empty dir
  function deleteDirectory($path) {
    $result = rmdir($this->dir . $path);
    if ($result === FALSE) {
      throw new MessageException(
        Message::createMessage(
          $this->isCacheDriver,
          Message::FM_UNABLE_TO_DELETE_DIRECTORY
        )
      );
    }
  }

  // Returns stream for `fpassthru`
  function readStream($path) {
    return fopen($this->dir . $path, 'rb');
  }

  function copyFile($pathSrc, $pathDst) {
    $res = copy($this->dir . $pathSrc, $this->dir . $pathDst);
    if ($res === FALSE) {
      throw new MessageException(
        Message::createMessage(
          $this->isCacheDriver,
          Message::FM_ERROR_ON_COPYING_FILES
        )
      );
    }
  }

  function copyDirectory($src, $dst) {
    $this->copyDirectory__recurse($src, $dst, FALSE);
  }

  private function copyDirectory__recurse($src, $dst, $createThisDstDir) {
    // Do not create a root directory (target directory to copy inside already exists)
    if ($createThisDstDir) {
      $this->makeDirectory($dst);
    }

    $fFiles = $this->files($src);
    foreach ($fFiles as $file) {
      $fileName = $file['name'];
      if ($fileName != '.' && $fileName != '..') {
        if ($this->directoryExists(TRUE, $src . '/' . $fileName)) {
          $this->copyDir__recurse(
            $src . '/' . $fileName,
            $dst . '/' . $fileName,
            TRUE
          );
        }
        else {
          $this->copyFile($src . '/' . $fileName, $dst . '/' . $fileName);
        }
      }
    }
  }

  function uploadFile__getName($file, $dir, $isOverwrite) {
    if ($isOverwrite) {
      // Remove existing file if exists
      $name = $file['name'];
      if ($this->exists($dir . '/' . $name))
        $this->delete($dir . '/' . $name);
    } else {
      // Get free file name
      $i = -1;
      do {
        $i++;
        if ($i == 0) {
          $name = $file['name'];
        }
        else {
          $name =
            Utils::getNameWithoutExt($file['name']) .
            '_' .
            $i .
            (Utils::getExt($file['name']) != NULL
              ? '.' . Utils::getExt($file['name'])
              : '');
        }
        $ok = !$this->exists($dir . '/' . $name);
      } while (!$ok);
    }
    return $name;
  }

  function uploadFile($file, $dir, $isOverwrite) {

    $name = $this->uploadFile__getName($file, $dir, $isOverwrite);

    $result = move_uploaded_file($file['tmp_name'], $this->dir . $dir . '/' . $name);
    if (!$result) {
      throw new MessageException(
        Message::createMessage(
          $this->isCacheDriver,
          Message::WRITING_FILE_ERROR,
          $dir . '/' . $file['name']
        )
      );
    }

    return $name;
  }

}