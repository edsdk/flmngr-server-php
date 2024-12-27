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


namespace EdSDK\FlmngrServer\fs;

use EdSDK\FlmngrServer\model\Message;
use EdSDK\FlmngrServer\lib\file\blurHash\Blurhash;
use EdSDK\FlmngrServer\lib\file\Utils;
use EdSDK\FlmngrServer\lib\MessageException;

class CachedFile
{

  private $fileRelative;

  private $driverFiles;

  private $driverCache;

  private $cacheFileRelative; // path/to/file.jpg (.json|.png will be added later)

  private $cacheFileJsonRelative;

  private $cacheFilePreviewRelative;

  function __construct(
    $fileRelative, // Example: /path/to/file.jpg
    $driverFiles,
    $driverCache
  ) {
    $this->fileRelative = $fileRelative;
    $this->driverFiles = $driverFiles;
    $this->driverCache = $driverCache;

    $this->cacheFileRelative = '/previews' . $fileRelative;

    $this->cacheFileJsonRelative = $this->cacheFileRelative . '.json';
    $this->cacheFilePreviewRelative = $this->cacheFileRelative . '.png';

    $this->driverCache->makeRootDir();
  }

  // Clears cache for this file
  function delete()
  {
    if ($this->driverCache->exists($this->cacheFileJsonRelative)) {
      $this->driverCache->delete($this->cacheFileJsonRelative);
    }
    if ($this->driverCache->exists($this->cacheFilePreviewRelative)) {
      $this->driverCache->delete($this->cacheFilePreviewRelative);
    }
  }

  function getInfo()
  {
    if (!$this->driverCache->exists($this->cacheFileJsonRelative)) {

      try {

        // We do not calculate BlurHash/width/height here due to this is a long operation
        // BlurHash/width/height will be calculated and JSON file will be updated on the first getCachedImagePreview() call

        $info = [
          'mtime' => $this->driverFiles->lastModified($this->fileRelative),
          'size' => $this->driverFiles->size($this->fileRelative),
        ];
        $this->writeInfo($info);
      } catch (Exception $e) {
        error_log("Exception while getting image size of " . $this->fileRelative);
        error_log($e);
      }
    }

    $content = $this->driverCache->get($this->cacheFileJsonRelative);
    $json = json_decode($content, TRUE);
    if ($json === NULL) {
      error_log("Unable to parse JSON from file " . $this->cacheFileJsonRelative);
      return NULL;
    }

    return $json;
  }

  private function writeInfo($info)
  {
    $dirname = dirname($this->cacheFileJsonRelative);
    if (!$this->driverCache->exists($dirname)) {
      $this->driverCache->makeDirectory($dirname);
    }
    $this->driverCache->put($this->cacheFileJsonRelative, json_encode($info));
  }

  /**
   * override-core: #86c19vjg1 support du Webp
   */
  function getPreview($preview_width, $preview_height, $contents)
  {
    $cacheFilePreviewRelative = $this->cacheFileRelative . '.png';

    if ($this->driverCache->exists($cacheFilePreviewRelative)) {
      $info = $this->getInfo();
      if (
        $info == NULL ||
        $info['mtime'] !== $this->driverFiles->lastModified($this->fileRelative) ||
        $info['size'] !== $this->driverFiles->size($this->fileRelative)
      ) {
        // Delete preview if it was changed, will be recreated below
        $this->driverCache->delete($cacheFilePreviewRelative);
      }
    }

    $resizedImage = NULL;
    if (!$this->driverCache->exists($cacheFilePreviewRelative)) {

      if (Utils::getMimeType($this->fileRelative) === 'image/svg+xml') {
        return ['image/svg+xml', $this->fileRelative, FALSE]; // FALSE means from files folder
      }

      if ($contents === NULL) {
        $contents = $this->driverFiles->get($this->fileRelative);
      }

      // Check if the file is a WebP image
      $isWebP = Utils::getMimeType($this->fileRelative) === 'image/webp';

      if ($isWebP) {
        // For WebP files, we need to use GD's dedicated WebP function
        if (function_exists('imagecreatefromwebp')) {
          // Create a temporary file to handle WebP
          $tmpFile = tempnam(sys_get_temp_dir(), 'webp_');
          file_put_contents($tmpFile, $contents);
          $image = imagecreatefromwebp($tmpFile);
          unlink($tmpFile); // Clean up
        } else {
          throw new MessageException(
            Message::createMessage(FALSE, Message::IMAGE_PROCESS_ERROR . ' (WebP not supported in this PHP version)')
          );
        }
      } else {
        // For other image types, use imagecreatefromstring as before
        $image = imagecreatefromstring($contents);
      }

      if (!$image) {
        throw new MessageException(
          Message::createMessage(FALSE, Message::IMAGE_PROCESS_ERROR)
        );
      }

      // Rest of the image processing code remains the same
      $orientation = $this->driverFiles->getExifOrientation($this->fileRelative);
      if ($orientation === 3) {
        $image = imagerotate($image, 180, 0);
      } else if ($orientation === 6) {
        $image = imagerotate($image, -90, 0);
      } else if ($orientation === 8) {
        $image = imagerotate($image, 90, 0);
      }

      $original_width = imagesx($image);
      $original_height = imagesy($image);
      if ($original_width == FALSE || $original_height == FALSE) {
        throw new MessageException(
          Message::createMessage(FALSE, Message::IMAGE_PROCESS_ERROR)
        );
      }

      $original_ratio = $original_width / $original_height;

      if ($preview_width == NULL) {
        $preview_width = max(1, floor($original_ratio * $preview_height));
      } else {
        if ($preview_height == NULL) {
          $preview_height = max(1, floor((1 / $original_ratio) * $preview_width));
        }
      }

      $preview_ratio = $preview_width / $preview_height;

      if ($original_ratio >= $preview_ratio) {
        $preview_height = max(1, floor($original_height * $preview_width / $original_width));
      } else {
        $preview_width = max(1, floor($original_width * $preview_height / $original_height));
      }

      $resizedImage = imagecreatetruecolor($preview_width, $preview_height);

      $colorGray1 = imagecolorallocate($resizedImage, 240, 240, 240);
      $colorGray2 = imagecolorallocate($resizedImage, 250, 250, 250);
      $rectSize = 20;
      for ($x = 0; $x <= floor($preview_width / $rectSize); $x++) {
        for ($y = 0; $y <= floor($preview_height / $rectSize); $y++) {
          imagefilledrectangle($resizedImage, $x * $rectSize, $y * $rectSize, $preview_width, $preview_height, ($x + $y) % 2 == 0 ? $colorGray1 : $colorGray2);
        }
      }

      imagecopyresampled(
        $resizedImage,
        $image,
        0,
        0,
        0,
        0,
        $preview_width,
        $preview_height,
        $original_width,
        $original_height
      );

      //$i = strrpos($cacheFilePreviewRelative, '/');
      //$cacheDirPreviewRelative = substr($cacheFilePreviewRelative, 0, $i);
      // clearstatcache(TRUE, $cacheDirPreviewAbsolute);

      $imageContents = Utils::writeImageContents(Utils::getExt($cacheFilePreviewRelative), $resizedImage, 80);

      if ($this->driverCache->put($cacheFilePreviewRelative, $imageContents) === FALSE) {
        throw new MessageException(
          Message::createMessage(
            TRUE,
            Message::FM_UNABLE_TO_WRITE_PREVIEW_IN_CACHE_DIR,
            $cacheFilePreviewRelative
          )
        );
      }
    }

    // Update BlurHash
    if ($resizedImage == NULL) {
      $resizedImage = @imagecreatefromstring($this->driverCache->get($cacheFilePreviewRelative));
    }

    $pixels = [];
    $xxCache = imagesx($resizedImage);
    $yyCache = imagesy($resizedImage);
    for ($y = 0; $y < $yyCache; $y++) {
      $row = [];
      for ($x = 0; $x < $xxCache; $x++) {
        $index = imagecolorat($resizedImage, $x, $y);
        $colors = imagecolorsforindex($resizedImage, $index);
        $row[] = [$colors['red'], $colors['green'], $colors['blue']];
      }
      $pixels[] = $row;
    }

    $components_x = 4;
    $components_y = 3;

    $cachedImageInfo = $this->getInfo();
    if (count($pixels) > 0) {
      $cachedImageInfo["blurHash"] = Blurhash::encode($pixels, $components_x, $components_y);
      if (isset($original_width)) {
        $cachedImageInfo["width"] = $original_width;
      }
      if (isset($original_height)) {
        $cachedImageInfo["height"] = $original_height;
      }
      $this->writeInfo($cachedImageInfo);
    }

    return ['image/png', $cacheFilePreviewRelative, TRUE]; // TRUE means from cache folder
  }
}