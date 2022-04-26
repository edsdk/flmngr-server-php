<?php

namespace EdSDK\FlmngrServer\fs;

use EdSDK\FlmngrServer\lib\action\resp\Message;
use EdSDK\FlmngrServer\lib\file\blurHash\Blurhash;
use EdSDK\FlmngrServer\lib\file\Utils;
use EdSDK\FlmngrServer\lib\MessageException;
use EdSDK\FlmngrServer\model\FMMessage;

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
        $driverCache,
        $isCacheInFiles
    )
    {
        $this->fileRelative = $fileRelative;
        $this->driverFiles = $driverFiles;
        $this->driverCache = $driverCache;

        $this->cacheFileRelative = $fileRelative;

        if ($isCacheInFiles) {
            $i = strrpos($this->cacheFileRelative, '/');
            $this->cacheFileRelative = substr($this->cacheFileRelative, 0, $i + 1) .
                '.cache/' . substr($this->cacheFileRelative, $i + 1);
        }

        $this->cacheFileJsonRelative = $this->cacheFileRelative . '.json';
        $this->cacheFilePreviewRelative = $this->cacheFileRelative . '.png';

        $this->driverCache->makeRootDir();
    }

    // Clears cache for this file
    function delete()
    {
        $this->driverCache->delete($this->cacheFileJsonRelative);
        $this->driverCache->delete($this->cacheFilePreviewRelative);
    }

    function getInfo()
    {
        if (!$this->driverCache->exists($this->cacheFileJsonRelative)) {

            try {

                // We do not calculate BlurHash/width/height here due to this is a long operation
                // BlurHash/width/height will be calculated and JSON file will be updated on the first getCachedImagePreview() call

                $info = array(
                    'mtime' => $this->driverFiles->lastModified($this->fileRelative),
                    'size' => $this->driverFiles->size($this->fileRelative)
                );
                $this->writeInfo($info);

            } catch (Exception $e) {
                error_log("Exception while getting image size of " . $this->fileRelative);
                error_log($e);
            }
        }

        $content = $this->driverCache->get($this->cacheFileJsonRelative);
        $json = json_decode($content, true);
        if ($json === null) {
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

    function getPreview($width, $height, $contents)
    {
        $cacheFilePreviewRelative = $this->cacheFileRelative . '.png';

        if ($this->driverCache->exists($cacheFilePreviewRelative)) {

            $info = $this->getInfo();
            if (
                $info == NULL

                // Amazon S3 is very slow here - 2 additional requests
                // ||
                //$info['mtime'] !== $this->fs->fsFileModifyTime(true, $this->fileAbsolute) ||
                //$info['size'] !== $this->fs->fsFileSize(true, $this->fileAbsolute)
            ) {
                // Delete preview if it was changed, will be recreated below
                $this->driverCache->delete($cacheFilePreviewRelative);
            }
        }

        $resizedImage = null;
        if (!$this->driverCache->exists($cacheFilePreviewRelative)) {
            if ($contents === null)
                $contents = $this->driverFiles->get($this->fileRelative);
            $image = imagecreatefromstring($contents);
            if (!$image) {
                throw new MessageException(
                    Message::createMessage(Message::IMAGE_PROCESS_ERROR)
                );
            }

            $xx = imagesx($image);
            $yy = imagesy($image);
            if ($width === FALSE || $height === FALSE) {
                throw new MessageException(
                    Message::createMessage(Message::IMAGE_PROCESS_ERROR)
                );
            }

            $ratio_original = $xx / $yy; // ratio original

            if ($width == NULL) {
                $width = floor($ratio_original * $height);
            } else if ($height == NULL) {
                $height = floor((1 / $ratio_original) * $width);
            }

            $ratio_thumb = $width / $height; // ratio thumb

            if ($ratio_original >= $ratio_thumb) {
                $yo = $yy;
                $xo = ceil(($yo * $width) / $height);
                $xo_ini = ceil(($xx - $xo) / 2);
                $xy_ini = 0;
            } else {
                $xo = $xx;
                $yo = ceil(($xo * $height) / $width);
                $xy_ini = ceil(($yy - $yo) / 2);
                $xo_ini = 0;
            }

            $resizedImage = imagecreatetruecolor($width, $height);

            $colorGray1 = imagecolorallocate($resizedImage, 240, 240, 240);
            $colorGray2 = imagecolorallocate($resizedImage, 250, 250, 250);
            $rectSize = 20;
            for ($x = 0; $x <= floor($width / $rectSize); $x++)
                for ($y = 0; $y <= floor($height / $rectSize); $y++)
                    imagefilledrectangle($resizedImage, $x * $rectSize, $y * $rectSize, $width, $height, ($x + $y) % 2 == 0 ? $colorGray1 : $colorGray2);


            imagecopyresampled(
                $resizedImage,
                $image,
                0,
                0,
                $xo_ini,
                $xy_ini,
                $width,
                $height,
                $xo,
                $yo
            );

            $i = strrpos($cacheFilePreviewRelative, '/');
            $cacheDirPreviewRelative = substr($cacheFilePreviewRelative, 0, $i);
            // clearstatcache(TRUE, $cacheDirPreviewAbsolute);

            $this->driverCache->makeRootDir();

            $imageContents = Utils::writeImageContents(Utils::getExt($cacheFilePreviewRelative), $resizedImage, 80);

            if ($this->driverCache->put($cacheFilePreviewRelative, $imageContents) === FALSE) {
                throw new MessageException(
                    FMMessage::createMessage(
                        FMMessage::FM_UNABLE_TO_WRITE_PREVIEW_IN_CACHE_DIR,
                        $cacheFilePreviewRelative
                    )
                );
            }
        }

        // Update BlurHash if required
        if (!isset($cachedImageInfo["blurHash"])) {

            if ($resizedImage == null)
                $resizedImage = @imagecreatefromstring($this->driverCache->get($cacheFilePreviewRelative));

            $pixels = [];
            $xx = imagesx($resizedImage);
            $yy = imagesy($resizedImage);
            for ($y = 0; $y < $yy; $y++) {
                $row = [];
                for ($x = 0; $x < $xx; $x++) {
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
                $cachedImageInfo["width"] = $xx;
                $cachedImageInfo["height"] = $yy;
                $this->writeInfo($cachedImageInfo);
            }
        }

        return ['image/png', $cacheFilePreviewRelative];
    }

}