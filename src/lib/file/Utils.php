<?php

/**
 * File Uploader Server package
 * Developer: N1ED
 * Website: https://n1ed.com/
 * License: GNU General Public License Version 3 or later
 **/

namespace EdSDK\FlmngrServer\lib\file;

use EdSDK\FlmngrServer\model\Message;
use EdSDK\FlmngrServer\lib\MessageException;
use EdSDK\FlmngrServer\model\ImageInfo;
use Exception;

class Utils {

  public static function getNameWithoutExt($filename) {
    $ext = Utils::getExt($filename);
    if ($ext == NULL) {
      return $filename;
    }
    return substr($filename, 0, strlen($filename) - strlen($ext) - 1);
  }

  public static function getExt($name) {
    $i = strrpos($name, '.');
    if ($i !== FALSE) {
      return substr($name, $i + 1);
    }
    return NULL;
  }

  const PROHIBITED_SYMBOLS = "/\\?%*:|\"<>";

  public static function fixFileName($name) {
    $newName = '';
    for ($i = 0; $i < strlen($name); $i++) {
      $ch = substr($name, $i, 1);
      if (strpos(Utils::PROHIBITED_SYMBOLS, $ch) !== FALSE) {
        $ch = '_';
      }
      $newName = $newName . $ch;
    }
    return $newName;
  }

  public static function isFileNameSyntaxOk($name) {
    if (strlen($name) == 0 || $name == '.' || strpos($name, '..') > -1) {
      return FALSE;
    }

    for ($i = 0; $i < strlen(Utils::PROHIBITED_SYMBOLS); $i++) {
      if (
        strpos($name, substr(Utils::PROHIBITED_SYMBOLS, $i, 1)) !==
        FALSE
      ) {
        return FALSE;
      }
    }

    if (strlen($name) > 260) {
      return FALSE;
    }

    /*
     * TODO: fix this and uncomment
     * On Windows + IIS + PHP produces:
     * Warning:  preg_match(): Unknown modifier '\' in <b>...\vendor\edsdk\file-uploader-server-php\src\lib\file\Utils.php on line 83
     *
     * if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // https://stackoverflow.com/questions/6730009/validate-a-file-name-on-windows
        // https://msdn.microsoft.com/en-us/library/aa365247(v=vs.85).aspx#file_and_directory_names
        $pattern =
            "/" .
            "^" .
            "(?!" .
            "  (?:" .
            "    CON|PRN|AUX|NUL|" .
            "    COM[1-9]|LPT[1-9]" .
            "  )" .
            "  (?:\\.[^.]*)?" .
            "  $" .
            ")" .
            "[^<>:\"/\\\\|?*\\x00-\\x1F]*" .
            "[^<>:\"/\\\\|?*\\x00-\\x1F\\ .]" .
            "$/ui";
        if (!preg_match($pattern, $name))
            return false;
    }*/

    return TRUE;
  }

  public static function isImage($name) {
    $exts = ['gif', 'jpg', 'jpeg', 'png', 'svg', 'webp', 'bmp'];
    $ext = Utils::getExt($name);
    for ($i = 0; $i < count($exts); $i++) {
      if ($exts[$i] === strtolower($ext)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  public static function addTrailingSlash($value) {
    if (
      $value != NULL &&
      (strlen($value) == 0 || !substr($value, strlen($value) - 1) == '/')
    ) {
      $value .= '/';
    }
    return $value;
  }

  public static function removeTrailingSlash($value) {
    if (
      $value != NULL &&
      (strlen($value) > 0 && substr($value, strlen($value) - 1) == '/')
    ) {
      $value = substr(0, strlen($value) - 1);
    }
    return $value;
  }

  public static function getMimeType($filePath) {
    $mimeType = NULL;
    $filePath = strtolower($filePath);
    if (Utils::endsWith($filePath, '.png')) {
      $mimeType = 'image/png';
    }
    if (Utils::endsWith($filePath, '.gif')) {
      $mimeType = 'image/gif';
    }
    if (Utils::endsWith($filePath, '.bmp')) {
      $mimeType = 'image/bmp';
    }
    if (
      Utils::endsWith($filePath, '.jpg') ||
      Utils::endsWith($filePath, '.jpeg')
    ) {
      $mimeType = 'image/jpeg';
    }
    if (Utils::endsWith($filePath, '.webp')) {
      $mimeType = 'image/webp';
    }
    if (Utils::endsWith($filePath, '.svg')) {
      $mimeType = 'image/svg+xml';
    }

    return $mimeType;
  }

  public static function endsWith($str, $ends) {
    return substr($str, -strlen($ends)) === $ends;
  }

  public static function normalizeNoEndSeparator($path) {
    // TODO: normalize
    return rtrim($path, '/');
  }

  public static function writeImageContents($ext, $image, $jpegQuality) {
    ob_clean();
    ob_start();
    switch (strtolower($ext)) {
      case 'gif':
        imagegif($image);
        break;
      case 'jpeg':
      case 'jpg':
        imagejpeg(
          $image,
          NULL,
          $jpegQuality
        );
        break;
      case 'png':
        imagepng($image);
        break;
      case 'bmp':
        imagewbmp($image);
        break;
    }
    $contents = ob_get_clean();
    return $contents;
  }

  public static function getImageInfo($file) {
    $size = @getimagesize($file);
    if ($size === FALSE) {
      //error_log("Unable to read image info of " . $file);
      throw new MessageException(
        Message::createMessage(
          FALSE,
          Message::IMAGE_PROCESS_ERROR
        )
      );
    }

    $imageInfo = new ImageInfo();
    $imageInfo->width = $size[0];
    $imageInfo->height = $size[1];
    return $imageInfo;
  }

}
