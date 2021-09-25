<?php

/**
 * Flmngr Server package
 * Developer: N1ED
 * Website: https://n1ed.com/
 * License: GNU General Public License Version 3 or later
 **/

namespace EdSDK\FlmngrServer\fs;

use EdSDK\FlmngrServer\lib\file\blurHash\Blurhash;
use EdSDK\FlmngrServer\lib\action\resp\Message;
use EdSDK\FlmngrServer\lib\file\Utils;
use EdSDK\FlmngrServer\lib\file\UtilsPHP;
use EdSDK\FlmngrServer\lib\MessageException;
use EdSDK\FlmngrServer\model\FMDir;
use EdSDK\FlmngrServer\model\FMFile;
use EdSDK\FlmngrServer\model\FMMessage;
use EdSDK\FlmngrServer\model\ImageInfo;
use Exception;

class FMDiskFileSystem extends AFileSystem
{
    private $dirFiles;

    private $dirCache;

    function __construct($config)
    {
        $this->dirFiles = $config['dirFiles'];
        $this->dirCache = $config['dirCache'];
    }

    function getDirs($hideDirs)
    {
        $dirs = [];
        $fDir = $this->dirFiles;
        if (!file_exists($fDir) || !is_dir($fDir)) {
            throw new MessageException(
                FMMessage::createMessage(FMMessage::FM_ROOT_DIR_DOES_NOT_EXIST)
            );
        }

        $this->getDirs__fill($dirs, $fDir, $hideDirs, '');
        return $dirs;
    }

    private function getDirs__fill(&$dirs, $fDir, $hideDirs, $path)
    {
        $files = scandir($fDir);

        $i = strrpos($fDir, '/');
        if ($i !== false) {
            $dirName = substr($fDir, $i + 1);
        } else {
            $dirName = $fDir;
        }

        if ($files === false) {
            throw new MessageException(
                FMMessage::createMessage(
                    FMMessage::FM_UNABLE_TO_LIST_CHILDREN_IN_DIRECTORY
                )
            );
        }

        $dirsCount = 0;
        $filesCount = 0;
        for ($i = 0; $i < count($files); $i++) {
            $file = $files[$i];
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (is_file($fDir . '/' . $file)) {
                $filesCount++;
            } else {
                if (is_dir($fDir . '/' . $file)) {
                    $dirsCount++;
                }
            }
        }

        $dir = new FMDir($dirName, $path, $filesCount, $dirsCount);
        $dirs[] = $dir;

        for ($i = 0; $i < count($files); $i++) {
            if ($files[$i] !== '.' && $files[$i] !== '..') {

                $isHide = FALSE;
                for ($j = 0; $j < count($hideDirs) && !$isHide; $j ++)
                    $isHide = $isHide || fnmatch($hideDirs[$j], $files[$j]);

                if (is_dir($fDir . '/' . $files[$i]) && !$isHide) {
                    $this->getDirs__fill(
                        $dirs,
                        $fDir . '/' . $files[$i],
                        $hideDirs,
                        $path . (strlen($path) > 0 ? '/' : '') . $dirName
                    );
                }
            }
        }
    }

    private function getRelativePath($path)
    {
        if (strpos($path, '..') !== false) {
            throw new MessageException(
                FMMessage::createMessage(
                    FMMessage::FM_DIR_NAME_CONTAINS_INVALID_SYMBOLS
                )
            );
        }

        if (strpos($path, '/') !== 0) {
            $path = '/' . $path;
        }

        $rootDirName = $this->getRootDirName();

        if (strpos($path, '/' . $rootDirName) !== 0) {
            throw new MessageException(
                FMMessage::createMessage(FMMessage::FM_DIR_NAME_INCORRECT_ROOT)
            );
        }

        return substr($path, strlen('/' . $rootDirName));
    }

    function getAbsolutePath($path)
    {
        return $this->dirFiles . $this->getRelativePath($path);
    }

    private function rmDirRecursive($dir)
    {
        if (!file_exists($dir)) {
            return true;
        }
        if (!is_dir($dir)) {
            return unlink($dir);
        }
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            if (!$this->rmDirRecursive($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }
        return rmdir($dir);
    }

    function deleteDir($dirPath)
    {
        $fullPath = $this->getAbsolutePath($dirPath);
        $res = $this->rmDirRecursive($fullPath);
        if ($res === false) {
            throw new MessageException(
                FMMessage::createMessage(
                    FMMessage::FM_UNABLE_TO_DELETE_DIRECTORY
                )
            );
        }
    }

    function createDir($dirPath, $name)
    {
        if (strpos($name, '..') !== false || strpos($name, '/') !== false) {
            throw new MessageException(
                FMMessage::createMessage(
                    FMMessage::FM_DIR_NAME_CONTAINS_INVALID_SYMBOLS
                )
            );
        }

        $fullPath = $this->getAbsolutePath($dirPath) . '/' . $name;
        $res = file_exists($fullPath) || mkdir($fullPath, 0777, true);
        if ($res === false) {
            throw new MessageException(
                FMMessage::createMessage(
                    FMMessage::FM_UNABLE_TO_CREATE_DIRECTORY
                )
            );
        }
    }

    private function renameFileOrDir($path, $newName)
    {
        if (
            strpos($newName, '..') !== false ||
            strpos($newName, '/') !== false
        ) {
            throw new MessageException(
                FMMessage::createMessage(
                    FMMessage::FM_DIR_NAME_CONTAINS_INVALID_SYMBOLS
                )
            );
        }

        $fullPath = $this->getAbsolutePath($path);

        $i = strrpos($fullPath, '/');
        $fullPathDst = substr($fullPath, 0, $i + 1) . $newName;
        if (is_file($fullPathDst)) {
            throw new MessageException(
                Message::createMessage(Message::FILE_ALREADY_EXISTS, $newName)
            );
        }

        $res = rename($fullPath, $fullPathDst);
        if ($res === false) {
            throw new MessageException(
                FMMessage::createMessage(FMMessage::FM_UNABLE_TO_RENAME)
            );
        }
    }

    public function renameFile($filePath, $newName)
    {
        $this->renameFileOrDir($filePath, $newName);
    }

    public function renameDir($dirPath, $newName)
    {
        $this->renameFileOrDir($dirPath, $newName);
    }

    public function getFilesPaged(
        $dirPath,
        $maxFiles,
        $lastFile,
        $lastIndex,
        $whiteList,
        $blackList,
        $filter,
        $orderBy,
        $orderAsc,
        $formatIds,
        $formatSuffixes
    )
    {
        $fullPath = $this->getAbsolutePath($dirPath);

        if (!is_dir($fullPath)) {
            throw new MessageException(
                Message::createMessage(Message::DIR_DOES_NOT_EXIST, $dirPath)
            );
        }

        $files = array(); // file name to sort values (like [filename, date, size])
        $formatFiles = array(); // format to array(owner file name to file name)
        foreach ($formatIds as $formatId) {
            $formatFiles[$formatId] = array();
        }

        $fFiles = scandir($fullPath);
        if ($fFiles === false) {
            throw new MessageException(
                FMMessage::createMessage(FMMessage::FM_DIR_CANNOT_BE_READ)
            );
        }

        foreach ($fFiles as $file) {

            if ($file == '.' || $file == '..' || !is_file($fullPath . '/' . $file))
                continue;

            $format = null;
            $name = Utils::getNameWithoutExt($file);
            if (Utils::isImage($file)) {
                for ($i = 0; $i < count($formatIds); $i++) {
                    $isFormatFile = FMDiskFileSystem::endsWith($name, $formatSuffixes[$i]);
                    if ($isFormatFile) {
                        $format = $formatSuffixes[$i];
                        $name = substr($name, 0, -strlen($formatSuffixes[$i]));
                        break;
                    }
                }
            }

            $ext = Utils::getExt($file);
            if ($ext != NULL)
                $name = $name . '.' . $ext;

            $fieldName = $file;
            $fieldDate = filemtime($fullPath . '/' . $file);
            $fieldSize = filesize($fullPath . '/' . $file);
            if ($format == NULL) {
                switch ($orderBy) {
                    case 'date':
                        $files[$file] = [$fieldDate, $fieldName, $fieldSize];
                        break;
                    case 'size':
                        $files[$file] = [$fieldSize, $fieldName, $fieldDate];
                        break;
                    case 'name':
                    default:
                        $files[$file] = [$fieldName, $fieldDate, $fieldSize];
                        break;
                }
            } else {
                $formatFiles[$format][$name] = $file;
            }
        }

        // Remove files outside of white list, and their formats too
        if (count($whiteList) > 0) { // only if whitelist is set
            foreach ($files as $file => $v) {

                $isMatch = false;
                foreach ($whiteList as $mask) {
                    if (fnmatch($mask, $file) === TRUE)
                        $isMatch = true;
                }

                if (!$isMatch) {
                    unset($files[$file]);
                    foreach ($formatFiles as $format => $formatFilesCurr) {
                        if (isset($formatFilesCurr[$file]))
                            unset($formatFilesCurr[$file]);
                    }
                }
            }
        }

        // Remove files outside of black list, and their formats too
        foreach ($files as $file => $v) {

            $isMatch = false;
            foreach ($blackList as $mask) {
                if (fnmatch($mask, $file) === TRUE)
                    $isMatch = true;
            }

            if ($isMatch) {
                unset($files[$file]);
                foreach ($formatFiles as $format => $formatFilesCurr) {
                    if (isset($formatFilesCurr[$file]))
                        unset($formatFilesCurr[$file]);
                }
            }
        }

        // Remove files not matching the filter, and their formats too
        foreach ($files as $file => $v) {

            $isMatch = fnmatch($filter, $file) === TRUE;

            if (!$isMatch) {
                unset($files[$file]);
                foreach ($formatFiles as $format => $formatFilesCurr) {
                    if (isset($formatFilesCurr[$file]))
                        unset($formatFilesCurr[$file]);
                }
            }
        }

        uasort($files, function ($arr1, $arr2) {

            for ($i=0; $i<count($arr1); $i++) {
                if (is_string($arr1[$i])) {
                    $v = strnatcmp($arr1[$i], $arr2[$i]);
                    if ($v !== 0)
                        return $v;
                } else {
                    if ($arr1[$i] > $arr2[$i])
                        return 1;
                    if ($arr1[$i] < $arr2[$i])
                        return -1;
                }
            }

            return 0;
        });

        $fileNames = array_keys($files);

        if (strtolower($orderAsc) !== "true") {
            $fileNames = array_reverse($fileNames);
        }

        $startIndex = 0;
        if ($lastIndex)
            $startIndex = $lastIndex + 1;
        if ($lastFile) { // $lastFile priority is higher than $lastIndex
            $i = array_search($lastFile, $fileNames);
            if ($i !== FALSE) {
                $startIndex = $i + 1;
            }
        }

        $isEnd = $startIndex + $maxFiles >= count($fileNames); // are there any files after current page?
        $fileNames = array_slice($fileNames, $startIndex, $maxFiles);

        $resultFiles = array();

        // Create result file list for output,
        // attach image attributes and image formats for image files.
        foreach ($fileNames as $fileName) {

            $resultFile = $this->getFileStructure($dirPath, $fileName);

            // Find formats of these files
            foreach ($formatIds as $formatId) {
                if (array_key_exists($fileName, $formatFiles[$formatId])) {
                    $formatFileName = $formatFiles[$formatId][$fileName];

                    $formatFile = $this->getFileStructure($dirPath, $formatFileName);
                    $resultFile['formats'][$formatId] = $formatFile;
                }
            }

            $resultFiles[] = $resultFile;
        }

        return array(
            'files' => $resultFiles,
            'isEnd' => $isEnd
        );
    }

    public function getFileStructure($dirPath, $fileName) {

        $fullPath = $this->getAbsolutePath($dirPath);

        $resultFile = array(
            'name' => $fileName,
            'size' => filesize($fullPath . '/' . $fileName),
            'timestamp' => filemtime($fullPath . '/' . $fileName) * 1000,
        );

        if (Utils::isImage($fileName)) {

            $imageInfo = $this->getCachedImageInfo($dirPath . '/' . $fileName);
            $resultFile['width'] = $imageInfo['width'];
            $resultFile['height'] = $imageInfo['height'];
            $resultFile['blurHash'] = isset($imageInfo['blurHash']) ? $imageInfo['blurHash'] : NULL;

            $resultFile['formats'] = array();
        }

        return $resultFile;
    }

    public function getFiles($dirPath)
    {
        // with "/root_dir_name" in the start

        $fullPath = $this->getAbsolutePath($dirPath);

        if (!is_dir($fullPath)) {
            throw new MessageException(
                Message::createMessage(Message::DIR_DOES_NOT_EXIST, $dirPath)
            );
        }

        $fFiles = scandir($fullPath);
        if ($fFiles === false) {
            throw new MessageException(
                FMMessage::createMessage(FMMessage::FM_DIR_CANNOT_BE_READ)
            );
        }

        $files = [];
        for ($i = 0; $i < count($fFiles); $i++) {
            $fFile = $fFiles[$i];
            $fileFullPath = $fullPath . '/' . $fFile;
            if (is_file($fileFullPath)) {
                try {
                    $imageInfo = $this->getImageInfo($fileFullPath);
                } catch (Exception $e) {
                    $imageInfo = new ImageInfo();
                    $imageInfo->width = null;
                    $imageInfo->height = null;
                }
                $file = new FMFile(
                    $dirPath,
                    $fFile,
                    filesize($fileFullPath),
                    filemtime($fileFullPath),
                    $imageInfo
                );

                $files[] = $file;
            }
        }

        return $files;
    }

    public function getImageSize($file)
    {
        return @getimagesize($file);
    }

    private static function getImageInfo($file)
    {
        $size = getimagesize($file);
        if ($size === false) {
            throw new MessageException(
                Message::createMessage(Message::IMAGE_PROCESS_ERROR)
            );
        }

        $imageInfo = new ImageInfo();
        $imageInfo->width = $size[0];
        $imageInfo->height = $size[1];
        return $imageInfo;
    }

    private function getRootDirName()
    {
        $i = strrpos($this->dirFiles, '/');
        if ($i === false) {
            return $this->dirFiles;
        }
        return substr($this->dirFiles, $i + 1);
    }

    function deleteFiles($filesPaths)
    {
        for ($i = 0; $i < count($filesPaths); $i++) {
            $fullPath = $this->getAbsolutePath($filesPaths[$i]);
            $res = is_dir($fullPath) ? rmdir($fullPath) : unlink($fullPath);
            if ($res === false) {
                throw new MessageException(
                    Message::createMessage(
                        Message::UNABLE_TO_DELETE_FILE,
                        $filesPaths[$i]
                    )
                );
            }
        }
    }

    function copyFiles($filesPaths, $newPath)
    {
        for ($i = 0; $i < count($filesPaths); $i++) {
            $fullPathSrc = $this->getAbsolutePath($filesPaths[$i]);

            $index = strrpos($fullPathSrc, '/');
            $name =
                $index === false
                    ? $fullPathSrc
                    : substr($fullPathSrc, $index + 1);
            $fullPathDst = $this->getAbsolutePath($newPath) . '/' . $name;

            $res = copy($fullPathSrc, $fullPathDst);
            if ($res === false) {
                throw new MessageException(
                    FMMessage::createMessage(
                        FMMessage::FM_ERROR_ON_COPYING_FILES
                    )
                );
            }
        }
    }

    function moveFiles($filesPaths, $newPath)
    {
        for ($i = 0; $i < count($filesPaths); $i++) {
            $fullPathSrc = $this->getAbsolutePath($filesPaths[$i]);

            $index = strrpos($fullPathSrc, '/');
            $name =
                $index === false
                    ? $fullPathSrc
                    : substr($fullPathSrc, $index + 1);
            $fullPathDst = $this->getAbsolutePath($newPath) . '/' . $name;

            $res = rename($fullPathSrc, $fullPathDst);
            if ($res === false) {
                throw new MessageException(
                    FMMessage::createMessage(
                        FMMessage::FM_ERROR_ON_MOVING_FILES
                    )
                );
            }
        }
    }

    // $mode:
    // "ALWAYS"
    // To recreate image preview in any case (even it is already generated before)
    // Used when user uploads a new image and needs to get its preview

    // "DO_NOT_UPDATE"
    // To create image only if it does not exist, if exists - its path will be returned
    // Used when user selects existing image in file manager and needs its preview

    // "IF_EXISTS"
    // To recreate preview if it already exists
    // Used when file was reuploaded, edited and we recreate previews for all formats we do not need right now, but used somewhere else

    // File uploaded / saved in image editor and reuploaded: $mode is "ALWAYS" for required formats, "IF_EXISTS" for the others
    // User selected image in file manager:                  $mode is "DO_NOT_UPDATE" for required formats and there is no requests for the otheres
    function resizeFile(
        $filePath,
        $newFileNameWithoutExt,
        $width,
        $height,
        $mode
    ) {
        // $filePath here starts with "/", not with "/root_dir"
        $rootDir = $this->getRootDirName();
        $filePath = '/' . $rootDir . $filePath;
        $srcPath = $this->getAbsolutePath($filePath);
        $index = strrpos($srcPath, '/');
        $oldFileNameWithExt = substr($srcPath, $index + 1);
        $newExt = 'png';
        $oldExt = strtolower(Utils::getExt($srcPath));
        if ($oldExt === 'jpg' || $oldExt === 'jpeg') {
            $newExt = 'jpg';
        }
        if ($oldExt === 'webp') {
            $newExt = 'webp';
        }
        $dstPath =
            substr($srcPath, 0, $index) .
            '/' .
            $newFileNameWithoutExt .
            '.' .
            $newExt;

        if (
            Utils::getNameWithoutExt($dstPath) ===
            Utils::getNameWithoutExt($srcPath)
        ) {
            // This is `default` format request - we need to process the image itself without changing its extension
            $dstPath = $srcPath;
        }

        if ($mode === 'IF_EXISTS' && !file_exists($dstPath)) {
            throw new MessageException(
                Message::createMessage(
                    FMMessage::FM_NOT_ERROR_NOT_NEEDED_TO_UPDATE
                )
            );
        }

        if ($mode === 'DO_NOT_UPDATE' && file_exists($dstPath)) {
            $url = substr($dstPath, strlen($this->dirFiles) + 1);
            if (strpos($url, '/') !== 0) {
                $url = '/' . $url;
            }
            return $url;
        }

        $image = null;
        switch (FMDiskFileSystem::getMimeType($srcPath)) {
            case 'image/gif':
                $image = @imagecreatefromgif($srcPath);
                break;
            case 'image/jpeg':
                $image = @imagecreatefromjpeg($srcPath);
                break;
            case 'image/png':
                $image = @imagecreatefrompng($srcPath);
                break;
            case 'image/bmp':
                $image = @imagecreatefromwbmp($srcPath);
                break;
            case 'image/webp':
                // If you get problems with WEBP preview creation, please consider updating GD > 2.2.4
                // https://stackoverflow.com/questions/59621626/converting-webp-to-jpeg-in-with-php-gd-library
                $image = @imagecreatefromwebp($srcPath);
                break;
            case 'image/svg+xml':
                // Return SVG as is
                $url = substr($srcPath, strlen($this->dirFiles) + 1);
                if (strpos($url, '/') !== 0) {
                    $url = '/' . $url;
                }
                return $url;
        }

        // Somewhy it can not read ONLY SOME JPEG files, we've caught it on Windows + IIS + PHP
        // Solution from here: https://github.com/libgd/libgd/issues/206
        if (!$image) {
            $image = imagecreatefromstring(file_get_contents($srcPath));
        }
        // end of fix

        if (!$image) {
            throw new MessageException(
                Message::createMessage(Message::IMAGE_PROCESS_ERROR)
            );
        }
        imagesavealpha($image, true);

        $imageInfo = FMDiskFileSystem::getImageInfo($srcPath);

        $originalWidth = $imageInfo->width;
        $originalHeight = $imageInfo->height;

        $needToFitWidth = $originalWidth > $width && $width > 0;
        $needToFitHeight = $originalHeight > $height && $height > 0;
        if ($needToFitWidth && $needToFitHeight) {
            if ($width / $originalWidth < $height / $originalHeight) {
                $needToFitHeight = false;
            } else {
                $needToFitWidth = false;
            }
        }

        if (!$needToFitWidth && !$needToFitHeight) {
            // if we generated the preview in past, we need to update it in any case
            if (
                !file_exists($dstPath) ||
                $newFileNameWithoutExt . '.' . $oldExt === $oldFileNameWithExt
            ) {
                // return old file due to it has correct width/height to be used as a preview
                $url = substr($srcPath, strlen($this->dirFiles) + 1);
                if (strpos($url, '/') !== 0) {
                    $url = '/' . $url;
                }
                return $url;
            } else {
                $width = $originalWidth;
                $height = $originalHeight;
            }
        }

        if ($needToFitWidth) {
            $ratio = $width / $originalWidth;
            $height = $originalHeight * $ratio;
        } elseif ($needToFitHeight) {
            $ratio = $height / $originalHeight;
            $width = $originalWidth * $ratio;
        }

        $resizedImage = imagecreatetruecolor($width, $height);
        imagealphablending($resizedImage, false);
        imagesavealpha($resizedImage, true);
        imagecopyresampled(
            $resizedImage,
            $image,
            0,
            0,
            0,
            0,
            $width,
            $height,
            $originalWidth,
            $originalHeight
        );

        $result = false;
        $ext = strtolower(Utils::getExt($dstPath));
        if ($ext === 'png') {
            $result = imagepng($resizedImage, $dstPath);
        } elseif ($ext === 'jpg' || $ext === 'jpeg') {
            $result = imagejpeg($resizedImage, $dstPath);
        } elseif ($ext === 'bmp') {
            $result = imagebmp($resizedImage, $dstPath);
        } elseif ($ext === 'webp') {
            $result = imagewebp($resizedImage, $dstPath);
        } else {
            $result = true;
        } // do not resize other formats (i. e. GIF)

        if ($result === false) {
            throw new MessageException(
                FMMessage::createMessage(
                    FMMessage::FM_UNABLE_TO_WRITE_PREVIEW_IN_CACHE_DIR,
                    $dstPath
                )
            );
        }

        $url = substr($dstPath, strlen($this->dirFiles) + 1);
        if (strpos($url, '/') !== 0) {
            $url = '/' . $url;
        }
        return $url;
    }

    function copyCommited($from, $to)
    {
        return UtilsPHP::copyFile($from, $to);
    }

    function moveDir($dirPath, $newPath)
    {
        $fullPathSrc = $this->getAbsolutePath($dirPath);

        $index = strrpos($fullPathSrc, '/');
        $name =
            $index === false ? $fullPathSrc : substr($fullPathSrc, $index + 1);
        $fullPathDst = $this->getAbsolutePath($newPath) . '/' . $name;

        $res = rename($fullPathSrc, $fullPathDst);
        if ($res === false) {
            throw new MessageException(
                FMMessage::createMessage(FMMessage::FM_ERROR_ON_MOVING_FILES)
            );
        }
    }

    function copyDir($dirPath, $newPath)
    {
        $fullPathSrc = $this->getAbsolutePath($dirPath);

        $index = strrpos($fullPathSrc, '/');
        $name =
            $index === false ? $fullPathSrc : substr($fullPathSrc, $index + 1);
        $fullPathDst = $this->getAbsolutePath($newPath) . '/' . $name;

        $res = $this->copyDir__recurse($fullPathSrc, $fullPathDst);
        if ($res === false) {
            throw new MessageException(
                FMMessage::createMessage(FMMessage::FM_ERROR_ON_MOVING_FILES)
            );
        }
    }

    private function copyDir__recurse($src, $dst)
    {
        $dir = opendir($src);
        mkdir($dst);
        while (false !== ($file = readdir($dir))) {
            if ($file != '.' && $file != '..') {
                if (is_dir($src . '/' . $file)) {
                    $res = $this->copyDir__recurse(
                        $src . '/' . $file,
                        $dst . '/' . $file
                    );
                    if ($res === false) {
                        return false;
                    }
                } else {
                    $res = copy($src . '/' . $file, $dst . '/' . $file);
                    if ($res === false) {
                        return false;
                    }
                }
            }
        }
        closedir($dir);
        return true;
    }

    private static function endsWith($str, $ends)
    {
        return substr($str, -strlen($ends)) === $ends;
    }

    private static function getMimeType($filePath)
    {
        $mimeType = null;
        $filePath = strtolower($filePath);
        if (FMDiskFileSystem::endsWith($filePath, '.png')) {
            $mimeType = 'image/png';
        }
        if (FMDiskFileSystem::endsWith($filePath, '.gif')) {
            $mimeType = 'image/gif';
        }
        if (FMDiskFileSystem::endsWith($filePath, '.bmp')) {
            $mimeType = 'image/bmp';
        }
        if (
            FMDiskFileSystem::endsWith($filePath, '.jpg') ||
            FMDiskFileSystem::endsWith($filePath, '.jpeg')
        ) {
            $mimeType = 'image/jpeg';
        }
        if (FMDiskFileSystem::endsWith($filePath, '.webp')) {
            $mimeType = 'image/webp';
        }
        if (FMDiskFileSystem::endsWith($filePath, '.svg')) {
            $mimeType = 'image/svg+xml';
        }

        return $mimeType;
    }

    function getCacheFile($filePath)
    {
        $fullPath = $this->getAbsolutePath($filePath);
        return $this->dirCache . '/' . str_replace('\\', '_', str_replace('/', '_', $filePath)) . "__" . filemtime($fullPath) . "__" . filesize($fullPath);
    }

    function getCachedImageInfo($filePath)
    {
        $fullPath = $this->getAbsolutePath($filePath);
        $cacheFileJson = $this->getCacheFile($filePath) . '.json';
        if (!file_exists($cacheFileJson)) {

            $size = @getimagesize($fullPath);

            if ($size == FALSE) {
                error_log("Unable to get size in file " . $cacheFileJson);
                return NULL;
            }

            $width = $size[0];
            $height = $size[1];

            // We do not calculate BlurHash here due to this is a long operation
            // BlurHash will be calculated and JSON file will be updated on the first getImagePreview() call

            $f = fopen($cacheFileJson, 'w');
            fwrite($f, json_encode(array(
                'width' => $width,
                'height' => $height
            )));
            fclose($f);
        }

        $content = file_get_contents($cacheFileJson);
        if ($content === FALSE) {
            error_log("Unable to read file " . $cacheFileJson);
            return NULL;
        }

        $json = json_decode($content, true);
        if ($json === null) {
            error_log("Unable to parse JSON from file " . $cacheFileJson);
            return NULL;
        }

        return $json;
    }

    function getImagePreview($filePath, $width, $height)
    {
        $fullPath = $this->getAbsolutePath($filePath);

        if (!file_exists($this->dirCache)) {
            if (!mkdir($this->dirCache)) {
                throw new MessageException(
                    FMMessage::createMessage(
                        FMMessage::FM_UNABLE_TO_CREATE_DIRECTORY
                    )
                );
            }
        }

        $fileCachedPath = $this->getCacheFile($filePath) . '__' . $width . '__' . $height . '.png';
        if (!file_exists($fileCachedPath)) {
            $image = null;
            switch (FMDiskFileSystem::getMimeType($fullPath)) {
                case 'image/gif':
                    $image = @imagecreatefromgif($fullPath);
                    break;
                case 'image/jpeg':
                    $image = @imagecreatefromjpeg($fullPath);
                    break;
                case 'image/png':
                    $image = @imagecreatefrompng($fullPath);
                    break;
                case 'image/bmp':
                    $image = @imagecreatefromwbmp($fullPath);
                    break;
                case 'image/webp':
                    // If you get problems with WEBP preview creation, please consider updating GD > 2.2.4
                    // https://stackoverflow.com/questions/59621626/converting-webp-to-jpeg-in-with-php-gd-library
                    $image = @imagecreatefromwebp($fullPath);
                    break;
                case 'image/svg+xml':
                    return ['image/svg+xml', fopen($fullPath, 'rb')];
            }

            // Somewhy it can not read ONLY SOME JPEG files, we've caught it on Windows + IIS + PHP
            // Solution from here: https://github.com/libgd/libgd/issues/206
            if (!$image) {
                $image = imagecreatefromstring(file_get_contents($fullPath));
            }
            // end of fix

            if (!$image) {
                throw new MessageException(
                    Message::createMessage(Message::IMAGE_PROCESS_ERROR)
                );
            }
            imagesavealpha($image, true);

            // TODO:
            // throw new MessageException(FMMessage.createMessage(FMMessage.FM_UNABLE_TO_CREATE_PREVIEW));

            $imageInfo = FMDiskFileSystem::getImageInfo($fullPath);
            $xx = $imageInfo->width;
            $yy = $imageInfo->height;
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
                    imagefilledrectangle($resizedImage, $x*$rectSize, $y*$rectSize, $width, $height, ($x + $y) % 2 == 0 ? $colorGray1 : $colorGray2);


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

            if (imagepng($resizedImage, $fileCachedPath) === false) {
                throw new MessageException(
                    FMMessage::createMessage(
                        FMMessage::FM_UNABLE_TO_WRITE_PREVIEW_IN_CACHE_DIR,
                        $fileCachedPath
                    )
                );
            }
        }


        // Update BlurHash if required
        $cachedImageInfo = $this->getCachedImageInfo($filePath);
        if (!isset($cachedImageInfo["blurHash"])) {

            $pixels = [];
            for ($y = 0; $y < $height; $y++) {
                $row = [];
                for ($x = 0; $x < $width; $x++) {
                    $index = imagecolorat($resizedImage, $x, $y);
                    $colors = imagecolorsforindex($resizedImage, $index);
                    $row[] = [$colors['red'], $colors['green'], $colors['blue']];
                }
                $pixels[] = $row;
            }

            $components_x = 4;
            $components_y = 3;

            $cachedImageInfo["blurHash"] = Blurhash::encode($pixels, $components_x, $components_y);
            $f = fopen($this->getCacheFile($filePath) . ".json", 'w');
            fwrite($f, json_encode($cachedImageInfo));
            fclose($f);
        }


        return ['image/png', $fileCachedPath];
    }

    function getImageOriginal($filePath)
    {
        $mimeType = FMDiskFileSystem::getMimeType($filePath);
        if ($mimeType == null) {
            throw new MessageException(
                FMMessage::createMessage(FMMessage::FM_FILE_IS_NOT_IMAGE)
            );
        }

        $fullPath = $this->getAbsolutePath($filePath);

        if (file_exists($fullPath)) {
            $f = fopen($fullPath, 'rb');
            if ($f) {
                return [$mimeType, $f];
            }
        }
        throw new MessageException(
            FMMessage::createMessage(FMMessage::FM_FILE_DOES_NOT_EXIST)
        );
    }

    function passThrough($fullPath, $mimeType)
    {
        $f = fopen($fullPath, 'rb');
        header('Content-Type:' . $mimeType);
        fpassthru($f);
    }

    function getDirZipArchive($dirPath, $out)
    {
        // TODO: Implement getDirZipArchive() method.
    }
}
