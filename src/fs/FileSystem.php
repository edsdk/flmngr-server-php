<?php

/**
 * Flmngr Server package
 * Developer: N1ED
 * Website: https://n1ed.com/
 * License: GNU General Public License Version 3 or later
 **/

namespace EdSDK\FlmngrServer\fs;

use EdSDK\FlmngrServer\model\Message;
use EdSDK\FlmngrServer\lib\file\Utils;
use EdSDK\FlmngrServer\lib\MessageException;
use EdSDK\FlmngrServer\model\FMDir;
use EdSDK\FlmngrServer\model\FMFile;
use Exception;

class FileSystem {

  private $driverFiles;

  private $driverCache;

  public $embedPreviews = FALSE;

  function __construct($config) {
    $dirFiles = in_array('dirFiles', array_keys($config)) ? $config['dirFiles'] : NULL; // NULL will cause exception later
    $dirCache = in_array('dirCache', array_keys($config)) ? $config['dirCache'] : ($dirFiles === NULL ? NULL : $dirFiles . '/.cache');
    $this->driverFiles = in_array('driverFiles', array_keys($config)) ? $config['driverFiles'] : new DriverLocal(['dir' => $dirFiles]);
    $this->driverCache = in_array('driverCache', array_keys($config)) ? $config['driverCache'] : new DriverLocal(['dir' => $dirCache], TRUE);
    $this->driverFiles->setDriverCache($this->driverCache);
  }

  private function getRelativePath($path) {
    if (strpos($path, '..') !== FALSE) {
      throw new MessageException(
        Message::createMessage(
          FALSE,
          Message::FM_DIR_NAME_CONTAINS_INVALID_SYMBOLS
        )
      );
    }

    if (strpos($path, '/') !== 0) {
      $path = '/' . $path;
    }

    $rootDirName = $this->driverFiles->getRootDirName();

    if ($path === '/Files') {
      $path = '/' . $rootDirName;
    }
    else {
      if (strpos($path, '/Files/') === 0) {
        $path = '/' . $rootDirName . '/' . substr($path, 7);
      }
    }
    if (strpos($path, '/' . $rootDirName) !== 0) {
      throw new MessageException(
        Message::createMessage(
          FALSE,
          Message::FM_DIR_NAME_INCORRECT_ROOT
        )
      );
    }

    return substr($path, strlen('/' . $rootDirName));
  }

  /**
   * Requests from controller
   */
  function reqGetDirs($request) {
    $hideDirs = isset($request->post['hideDirs']) ? $request->post['hideDirs'] : [];

    $dirs = [];
    $hideDirs[] = '.cache';

    // Add root directory if it is ""
    $dirRoot = $this->driverFiles->getRootDirName();
    $i = strrpos($dirRoot, '/');
    if ($i !== FALSE) {
      $dirRoot = substr($dirRoot, $i + 1);
    }

    $addFilesPrefix = $dirRoot === "";

    $dirs[] = new FMDir($addFilesPrefix ? 'Files' : $dirRoot, '');

    // A flat list of child directories
    $dirsStr = $this->driverFiles->allDirectories();

    // Add files
    foreach ($dirsStr as $dirStr) {
      $dirArr = preg_split('/\\//', $dirStr);
      if ($addFilesPrefix) {
        array_unshift($dirArr, "Files");
      }
      $dirs[] = new FMDir(
        $dirArr[count($dirArr) - 1],
        join('/', array_splice($dirArr, 0, count($dirArr) - 1))
      );
    }

    return $dirs;
  }

  // Legacy request for Flmngr v1
  public function reqGetFiles($request) {
    $path = $request->post['d'];

    // with "/root_dir_name" in the start
    $path = $this->getRelativePath($path);

    $fFiles = $this->driverFiles->files($path);

    $files = [];
    for ($i = 0; $i < count($fFiles); $i++) {
      $fFile = $fFiles[$i]['name'];

      if (preg_match('/-(preview|medium|original)\\.[^.]+$/', $fFile) === 1) {
        continue;
      }

      $filePath = $path . '/' . $fFile;
      if (is_file($filePath)) {
        $preview = NULL;
        try {
          $imageInfo = Utils::getImageInfo($filePath);
          if ($this->embedPreviews === TRUE) {
            $preview = $this->getCachedImagePreview($filePath, NULL);
            $preview[1] = ($preview[2] === FALSE ? $this->driverFiles : $this->driverCache)->get($preview[1]); // convert path to content
            $preview = "data:" . $preview[0] . ";base64," . base64_encode($preview[1]);
          }

        } catch (Exception $e) {
          $imageInfo = new ImageInfo();
          $imageInfo->width = NULL;
          $imageInfo->height = NULL;
        }
        $file = new FMFile(
          $path,
          $fFile,
          filesize($filePath),
          filemtime($filePath),
          $imageInfo
        );
        if ($preview != NULL) {
          $file->preview = $preview;
        }

        $files[] = $file;
      }
    }

    return $files;
  }

  public function reqGetFilesPaged($request) {
    $dirPath = $request->post['dir'];
    $maxFiles = $request->post['maxFiles'];
    $lastFile = isset($request->post['lastFile']) ? $request->post['lastFile'] : NULL;
    $lastIndex = isset($request->post['lastIndex']) ? $request->post['lastIndex'] : NULL;
    $whiteList = isset($request->post['whiteList']) ? $request->post['whiteList'] : [];
    $blackList = isset($request->post['blackList']) ? $request->post['blackList'] : [];
    $filter = isset($request->post['filter']) ? $request->post['filter'] : "**";
    $orderBy = $request->post['orderBy'];
    $orderAsc = $request->post['orderAsc'];
    $formatIds = $request->post['formatIds'];
    $formatSuffixes = $request->post['formatSuffixes'];

    // Convert /root_dir/1/2/3 to 1/2/3
    $dirPath = $this->getRelativePath($dirPath);

    $now = microtime(TRUE);
    $start = $now;

    $files = []; // file name to sort values (like [filename, date, size])
    $formatFiles = []; // format to array(owner file name to file name)
    foreach ($formatIds as $formatId) {
      $formatFiles[$formatId] = [];
    }

    $fFiles = $this->driverFiles->files($dirPath);
    $now = $this->profile("Scan dir", $now);

    foreach ($fFiles as $file) {

      $format = NULL;
      $name = Utils::getNameWithoutExt($file['name']);
      if (Utils::isImage($file['name'])) {
        for ($i = 0; $i < count($formatIds); $i++) {
          $isFormatFile = Utils::endsWith($name, $formatSuffixes[$i]);
          if ($isFormatFile) {
            $format = $formatSuffixes[$i];
            $name = substr($name, 0, -strlen($formatSuffixes[$i]));
            break;
          }
        }
      }

      $ext = Utils::getExt($file['name']);
      if ($ext != NULL) {
        $name = $name . '.' . $ext;
      }

      if ($format == NULL) {
        switch ($orderBy) {
          case 'date':
            $files[$file['name']] = [
              $file['mtime'],
              $file['name'],
              $file['size'],
            ];
            break;
          case 'size':
            $files[$file['name']] = [
              $file['size'],
              $file['name'],
              $file['mtime'],
            ];
            break;
          case 'name':
          default:
            $files[$file['name']] = [
              $file['name'],
              $file['mtime'],
              $file['size'],
            ];
            break;
        }
      }
      else {
        $formatFiles[$format][$name] = $file['name'];
      }
    }
    $now = $this->profile("Fill image formats", $now);

    // Remove files outside of white list, and their formats too
    if (count($whiteList) > 0) { // only if whitelist is set
      foreach ($files as $file => $v) {

        $isMatch = FALSE;
        foreach ($whiteList as $mask) {
          if (fnmatch($mask, $file) === TRUE) {
            $isMatch = TRUE;
          }
        }

        if (!$isMatch) {
          unset($files[$file]);
          foreach ($formatFiles as $format => $formatFilesCurr) {
            if (isset($formatFilesCurr[$file])) {
              unset($formatFilesCurr[$file]);
            }
          }
        }
      }
    }

    $now = $this->profile("White list", $now);

    // Remove files outside of black list, and their formats too
    foreach ($files as $file => $v) {

      $isMatch = FALSE;
      foreach ($blackList as $mask) {
        if (fnmatch($mask, $file) === TRUE) {
          $isMatch = TRUE;
        }
      }

      if ($isMatch) {
        unset($files[$file]);
        foreach ($formatFiles as $format => $formatFilesCurr) {
          if (isset($formatFilesCurr[$file])) {
            unset($formatFilesCurr[$file]);
          }
        }
      }
    }

    $now = $this->profile("Black list", $now);

    // Remove files not matching the filter, and their formats too
    foreach ($files as $file => $v) {

      $isMatch = fnmatch($filter, $file) === TRUE;
      if (!$isMatch) {
        unset($files[$file]);
        foreach ($formatFiles as $format => $formatFilesCurr) {
          if (isset($formatFilesCurr[$file])) {
            unset($formatFilesCurr[$file]);
          }
        }
      }
    }

    $now = $this->profile("Filter", $now);

    uasort($files, function ($arr1, $arr2) {

      for ($i = 0; $i < count($arr1); $i++) {
        if (is_string($arr1[$i])) {
          $v = strnatcmp($arr1[$i], $arr2[$i]);
          if ($v !== 0) {
            return $v;
          }
        }
        else {
          if ($arr1[$i] > $arr2[$i]) {
            return 1;
          }
          if ($arr1[$i] < $arr2[$i]) {
            return -1;
          }
        }
      }

      return 0;
    });

    $fileNames = array_keys($files);

    if (strtolower($orderAsc) !== "true") {
      $fileNames = array_reverse($fileNames);
    }

    $now = $this->profile("Sorting", $now);

    $startIndex = 0;
    if ($lastIndex) {
      $startIndex = $lastIndex + 1;
    }
    if ($lastFile) { // $lastFile priority is higher than $lastIndex
      $i = array_search($lastFile, $fileNames);
      if ($i !== FALSE) {
        $startIndex = $i + 1;
      }
    }

    $isEnd = $startIndex + $maxFiles >= count($fileNames); // are there any files after current page?
    $fileNames = array_slice($fileNames, $startIndex, $maxFiles);

    $now = $this->profile("Page slice", $now);

    $resultFiles = [];


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

    $now = $this->profile("Create output list", $now);
    $this->profile("Total", $start);

    return [
      'files' => $resultFiles,
      'isEnd' => $isEnd,
    ];
  }

  public function getFileStructure($dirPath, $fileName) {
    $cachedImageInfo = $this->getCachedImageInfo($dirPath . '/' . $fileName);
    $resultFile = [
      'name' => "" . $fileName,
      'size' => $cachedImageInfo['size'],
      'timestamp' => $cachedImageInfo['mtime'],
    ];

    if (Utils::isImage($fileName)) {

      $resultFile['width'] = isset($cachedImageInfo['width']) ? $cachedImageInfo['width'] : NULL;
      $resultFile['height'] = isset($cachedImageInfo['height']) ? $cachedImageInfo['height'] : NULL;
      $resultFile['blurHash'] = isset($cachedImageInfo['blurHash']) ? $cachedImageInfo['blurHash'] : NULL;

      $resultFile['formats'] = [];
    }

    return $resultFile;
  }

  function reqGetImagePreview($request) {
    $filePath = isset($request->get['f']) ? $request->get['f'] : $request->post['f'];
    $width = isset($request->get['width']) ? $request->get['width'] :
      (isset($request->post['width']) ? $request->post['width'] : NULL);
    $height = isset($request->get['height']) ? $request->get['height'] :
      (isset($request->post['height']) ? $request->post['height'] : NULL);

    $filePath = $this->getRelativePath($filePath);
    $result = $this->getCachedImagePreview($filePath, NULL);

    // Convert path to contents
    $result[1] = ($result[2] === FALSE ? $this->driverFiles : $this->driverCache)->readStream($result[1]);
    return $result;
  }

  function reqGetImagePreviewAndResolution($request) {

    $filePath = isset($request->get['f']) ? $request->get['f'] : $request->post['f'];
    $width = isset($request->get['width']) ? $request->get['width'] :
      (isset($request->post['width']) ? $request->post['width'] : NULL);
    $height = isset($request->get['height']) ? $request->get['height'] :
      (isset($request->post['height']) ? $request->post['height'] : NULL);

    $filePath = $this->getRelativePath($filePath);
    $previewAndResolution = $this->getCachedImagePreviewAndResolution($filePath, NULL);

    $result = [
      'width' => $previewAndResolution[1],
      'height' => $previewAndResolution[2],
      'preview' => $previewAndResolution[0] != NULL ? ("data:" . $previewAndResolution[0][0] . ";base64," . base64_encode(($previewAndResolution[0][2] === FALSE ? $this->driverFiles : $this->driverCache)->get($previewAndResolution[0][1]))) : NULL,
    ];

    return $result;
  }

  function reqCopyDir($request) {
    $dirPath = $request->post['d']; // full path
    $newPath = $request->post['n']; // full path

    $dirPath = $this->getRelativePath($dirPath);
    $newPath = $this->getRelativePath($newPath);

    $this->driverFiles->copyDirectory($dirPath, $newPath);
  }

  function reqCopyFiles($request) {
    $files = $request->post['fs'];
    $newPath = $request->post['n'];

    $filesPaths = preg_split('/\|/', $files);
    for ($i = 0; $i < count($filesPaths); $i++) {
      $filesPaths[$i] = $this->getRelativePath($filesPaths[$i]);
    }
    $newPath = $this->getRelativePath($newPath);

    for ($i = 0; $i < count($filesPaths); $i++) {
      $this->driverFiles->copyFile($filesPaths[$i], rtrim($newPath, '\\/') . '/' . basename($filesPaths[$i]));
    }
  }

  function getCachedFile($filePath) {
    return new CachedFile(
      $filePath,
      $this->driverFiles,
      $this->driverCache
    );
  }

  private $PREVIEW_WIDTH = 159;

  private $PREVIEW_HEIGHT = 139;

  function getCachedImageInfo($filePath) {
    $start = microtime(TRUE);
    $result = $this->getCachedFile($filePath)->getInfo();
    $this->profile("getCachedImageInfo: " . $filePath, $start);
    return $result;
  }

  function getCachedImagePreview($filePath, $contents) {
    $start = microtime(TRUE);
    $result = $this->getCachedFile($filePath)
      ->getPreview($this->PREVIEW_WIDTH, $this->PREVIEW_HEIGHT, $contents);
    $this->profile("getCachedImagePreview: " . $filePath, $start);
    return $result;
  }

  function getCachedImagePreviewAndResolution($filePath, $contents) {
    $start = microtime(TRUE);
    $cachedFile = $this->getCachedFile($filePath);

    $preview = $cachedFile->getPreview($this->PREVIEW_WIDTH, $this->PREVIEW_HEIGHT, $contents);
    $info = $cachedFile->getInfo();

    $result = [
      $preview,
      isset($info['width']) ? $info['width'] : NULL,
      isset($info['height']) ? $info['height'] : NULL,
    ];
    $this->profile("getCachedImagePreviewAndResolution: " . $filePath, $start);
    return $result;
  }

  function reqCreateDir($request) {
    $dirPath = $request->post['d'];
    $name = $request->post['n'];

    $dirPath = $this->getRelativePath($dirPath);
    if (strpos($name, '/') !== FALSE) {
      throw new MessageException(
        Message::createMessage(
          FALSE,
          Message::FM_DIR_NAME_CONTAINS_INVALID_SYMBOLS
        )
      );
    }
    $this->driverFiles->makeDirectory($dirPath . '/' . $name);
  }

  function reqDeleteDir($request) {
    $dirPath = $request->post['d'];

    $dirPath = $this->getRelativePath($dirPath);

    $this->driverFiles->delete($dirPath);
  }

  function reqMove($request) {
    $path = $request->post['d'];
    $newPath = $request->post['n']; // path without name

    $path = $this->getRelativePath($path);
    $newPath = $this->getRelativePath($newPath);

    try {
      $this->driverFiles->move($path, $newPath . '/' . basename($path));
    } catch (Exception $e) {
      throw new MessageException(
        Message::createMessage(
          FALSE,
          Message::FM_ERROR_ON_MOVING_FILES
        )
      );
    }
  }

  function reqRename($request) {
    $path = isset($request->post['d']) ? $request->post['d'] : $request->post['f'];
    $newName = $request->post['n']; // name without path

    if (strpos($newName, '/') !== FALSE) {
      throw new MessageException(
        Message::createMessage(
          FALSE,
          Message::FM_DIR_NAME_CONTAINS_INVALID_SYMBOLS
        )
      );
    }

    $path = $this->getRelativePath($path);

    $this->driverFiles->move($path, rtrim(dirname($path), '\\/') . '/' . $newName);
  }

  function reqMoveFiles($request) {
    $filesPaths = preg_split('/\|/', $request->post['fs']); // array of file paths
    $newPath = $request->post['n']; // dir without filename

    for ($i = 0; $i < count($filesPaths); $i++) {
      $filesPaths[$i] = $this->getRelativePath($filesPaths[$i]);
    }
    $newPath = $this->getRelativePath($newPath);

    for ($i = 0; $i < count($filesPaths); $i++) {
      $filePath = $filesPaths[$i];
      $index = strrpos($filePath, '/');
      $name = $index === FALSE ? $filePath : substr($filePath, $index + 1);
      $this->driverFiles->move($filePath, $newPath . '/' . $name);
    }
  }

  // TODO: Currently we delete another image formats, probably we should regenerate them
  protected function updateFormatsAndClearCachePreviewForFile($filePath, $formatSuffixes) {
    $fullPaths = [];

    $index = strrpos($filePath, '.');
    if ($index > -1) {
      $fullPathPrefix = substr($filePath, 0, $index);
    }
    else {
      $fullPathPrefix = $filePath;
    }
    if (isset($formatSuffixes) && is_array($formatSuffixes)) {
      for ($j = 0; $j < count($formatSuffixes); $j++) {
        $exts = ["png", "jpg", "jpeg", "webp"];
        for ($k = 0; $k < count($exts); $k++) {
          $fullPaths[] = $fullPathPrefix . $formatSuffixes[$j] . '.' . $exts[$k];
        }
      }
    }

    $cachedFile = $this->getCachedFile($filePath);
    $cachedFile->delete();

    for ($j = 0; $j < count($fullPaths); $j++) {
      if ($this->driverFiles->fileExists($fullPaths[$j])) {
        $this->driverFiles->delete($fullPaths[$j]);
      }
    }
  }

  // "suffixes" is an optional parameter (does not supported by Flmngr UI v1)
  function reqDeleteFiles($request) {
    $filesPaths = preg_split('/\|/', $request->post['fs']);
    $formatSuffixes = $request->post['formatSuffixes'];

    for ($i = 0; $i < count($filesPaths); $i++) {
      $filesPaths[$i] = $this->getRelativePath($filesPaths[$i]);
      $this->driverFiles->delete($filesPaths[$i]);
    }

    foreach ($filesPaths as $filePath) {
      $this->updateFormatsAndClearCachePreviewForFile($filePath, $formatSuffixes);
    }
  }

  // $files are like: "file.jpg" or "dir/file.png" - they start not with "/root_dir/"
  // This is because we need to validate files before dir tree is loaded on a client
  public function reqGetFilesSpecified($request) {
    $files = $request->post['files'];

    $result = [];
    for ($i = 0; $i < count($files); $i++) {

      $file = '/' . $files[$i];

      if (strpos($file, '..') !== FALSE) {
        throw new MessageException(
          Message::createMessage(
            FALSE,
            Message::FM_DIR_NAME_CONTAINS_INVALID_SYMBOLS
          )
        );
      }

      if ($this->driverFiles->fileExists($file)) {

        $result[] = [
          "dir" => dirname($file),
          "file" => $this->getFileStructure(dirname($file), basename($file)),
        ];
      }

    }
    return $result;
  }

  // mode:
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
  function reqResizeFile($request) {
    // $filePath here starts with "/", not with "/root_dir" as usual
    // so there will be no getRelativePath call
    $filePath = $request->post['f'];
    $newFileNameWithoutExt = $request->post['n'];
    $width = $request->post['mw'];
    $height = $request->post['mh'];
    $mode = $request->post['mode'];

    if (strpos($filePath, '..') !== FALSE) {
      throw new MessageException(
        Message::createMessage(
          FALSE,
          Message::FM_DIR_NAME_CONTAINS_INVALID_SYMBOLS
        )
      );
    }

    if (
      strpos($newFileNameWithoutExt, '..') !== FALSE ||
      strpos($newFileNameWithoutExt, '/') !== FALSE ||
      strpos($newFileNameWithoutExt, '\\') !== FALSE
    ) {
      throw new MessageException(
        Message::createMessage(
          FALSE,
          Message::FM_DIR_NAME_CONTAINS_INVALID_SYMBOLS
        )
      );
    }

    $index = strrpos($filePath, '/');
    $oldFileNameWithExt = substr($filePath, $index + 1);
    $newExt = 'png';
    $oldExt = strtolower(Utils::getExt($filePath));
    if ($oldExt === 'jpg' || $oldExt === 'jpeg') {
      $newExt = 'jpg';
    }
    if ($oldExt === 'webp') {
      $newExt = 'webp';
    }
    $dstPath =
      substr($filePath, 0, $index) .
      '/' .
      $newFileNameWithoutExt .
      '.' .
      $newExt;

    if (
      Utils::getNameWithoutExt($dstPath) ===
      Utils::getNameWithoutExt($filePath)
    ) {
      // This is `default` format request - we need to process the image itself without changing its extension
      $dstPath = $filePath;
    }

    $isDstPathExists = $this->driverFiles->fileExists($dstPath);

    if ($mode === 'IF_EXISTS' && !$isDstPathExists) {
      throw new MessageException(
        Message::createMessage(
          FALSE,
          Message::FM_NOT_ERROR_NOT_NEEDED_TO_UPDATE
        )
      );
    }

    if ($mode === 'DO_NOT_UPDATE' && $isDstPathExists) {
      return $dstPath;
    }

    $contents = $this->driverFiles->get($filePath);
    $image = imagecreatefromstring($contents);

    if (!$image) {
      throw new MessageException(
        FALSE,
        Message::createMessage(
          FALSE,
          Message::IMAGE_PROCESS_ERROR
        )
      );
    }
    imagesavealpha($image, TRUE);

    $this->getCachedImagePreview($filePath, $contents); // to force writing image/width into cache file
    $imageInfo = $this->getCachedImageInfo($filePath);

    $originalWidth = $imageInfo['width'];
    $originalHeight = $imageInfo['height'];

    $needToFitWidth = $originalWidth > $width && $width > 0;
    $needToFitHeight = $originalHeight > $height && $height > 0;
    if ($needToFitWidth && $needToFitHeight) {
      if ($width / $originalWidth < $height / $originalHeight) {
        $needToFitHeight = FALSE;
      }
      else {
        $needToFitWidth = FALSE;
      }
    }

    if (!$needToFitWidth && !$needToFitHeight) {
      // if we generated the preview in past, we need to update it in any case
      if (
        !$isDstPathExists ||
        $newFileNameWithoutExt . '.' . $oldExt === $oldFileNameWithExt
      ) {
        // return old file due to it has correct width/height to be used as a preview
        return $filePath;
      }
      else {
        $width = $originalWidth;
        $height = $originalHeight;
      }
    }

    if ($needToFitWidth) {
      $ratio = $width / $originalWidth;
      $height = $originalHeight * $ratio;
    }
    elseif ($needToFitHeight) {
      $ratio = $height / $originalHeight;
      $width = $originalWidth * $ratio;
    }

    $resizedImage = imagecreatetruecolor($width, $height);
    imagealphablending($resizedImage, FALSE);
    imagesavealpha($resizedImage, TRUE);

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

    $ext = strtolower(Utils::getExt($dstPath));
    ob_start();
    if ($ext === 'png') {
      imagepng($resizedImage);
    }
    elseif ($ext === 'jpg' || $ext === 'jpeg') {
      imagejpeg($resizedImage);
    }
    elseif ($ext === 'bmp') {
      imagebmp($resizedImage);
    }
    elseif ($ext === 'webp') {
      imagewebp($resizedImage);
    } // do not resize other formats (i. e. GIF)

    $stringData = ob_get_contents(); // read from buffer
    ob_end_clean(); // delete buffer
    $this->driverFiles->put($dstPath, $stringData);

    return $dstPath;
  }

  function reqGetImageOriginal($request) {
    $filePath = isset($request->get['f']) ? $request->get['f'] : $request->post['f'];

    $filePath = $this->getRelativePath($filePath);

    $mimeType = Utils::getMimeType($filePath);
    if ($mimeType == NULL) {
      throw new MessageException(
        Message::createMessage(
          FALSE,
          Message::FM_FILE_IS_NOT_IMAGE
        )
      );
    }

    $stream = $this->driverFiles->readStream($filePath);

    return [$mimeType, $stream];
  }

  function reqGetVersion($request) {
    return [
      'version' => '5',
      'language' => 'php',
      'storage' => $this->driverFiles->getDriverName(),
      'dirFiles' => $this->driverFiles->getDir(),
      'dirCache' => $this->driverCache->getDir(),
    ];
  }

  public function reqUpload($request) {
    $dir = isset($request->post['dir']) ? $request->post['dir'] : '/' . $this->driverFiles->getRootDirName();

    $isOverwrite = isset($request->post['mode']) && $request->post['mode'] === "OVERWRITE";

    if (!isset($request->files['file'])) {
      throw new MessageException(
        Message::createMessage(
          FALSE,
          Message::FILES_NOT_SET
        )
      );
    }
    $file = $request->files['file'];

    $name = $this->driverFiles->uploadFile($file, $dir, $isOverwrite);


    if ($isOverwrite) {
      $formatSuffixes = $request->post['formatSuffixes'];
      $this->updateFormatsAndClearCachePreviewForFile($dir . '/' . $name, $formatSuffixes);
    }

    $resultFile = $this->getFileStructure($dir, $name);

    return [
      'file' => $resultFile,
    ];
  }

  private function profile($text, $start) {
    $now = microtime(TRUE);
    $time = $now - $start;
    //error_log(number_format($time, 3, ",", "")." sec   " . $text . "\n");
    return $now;
  }


}
