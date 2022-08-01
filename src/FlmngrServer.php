<?php

/**
 * Flmngr Server package
 * Developer: N1ED
 * Website: https://n1ed.com/
 * License: GNU General Public License Version 3 or later
 **/

namespace EdSDK\FlmngrServer;

use EdSDK\FlmngrServer\fs\FileSystem;
use EdSDK\FlmngrServer\lib\CommonRequest;
use EdSDK\FlmngrServer\resp\Response;
use Exception;

use EdSDK\FlmngrServer\lib\JsonCodec;
use EdSDK\FlmngrServer\model\Message;
use EdSDK\FlmngrServer\lib\MessageException;

ini_set('display_errors', 0);

class FlmngrServer {

  static function flmngrRequest($config) {

    if (!isset($config['dirCache']) && isset($config['driverFiles'])) {
      $resp = new Response("Set cache dir when using another files driver", NULL);
      $strResp = JsonCodec::s_toJson($resp);
      try {
        http_response_code(200);
        header('Content-Type: application/json; charset=UTF-8');
        print $strResp;
      } catch (Exception $e) {
        error_log($e);
      }
      return;
    }

    try {

      if (isset($config['request'])) {
        $request = $config['request'];
      }
      else {
        $request = new CommonRequest();
      }
      $request->parseRequest();

      $fileSystem = new FileSystem($config);

      if (FlmngrServer::checkUploadLimit($request)) {
        return;
      } // file size exceed the limit from php.ini

      if (isset($request->post['embedPreviews'])) {
        $fileSystem->embedPreviews = $request->post['embedPreviews'];
      }

      $action = NULL;
      if ($request->requestMethod === 'POST') {
        if (isset($request->post['action'])) {
          $action = $request->post['action'];
        }
      }
      else {
        if ($request->requestMethod === 'GET') {
          $action = $request->get['action'];
        }
        else {
          return;
        }
      }

      $data = TRUE; // will be optionally filled by request
      switch ($action) {
        case 'dirList':
          $data = $fileSystem->reqGetDirs($request);
          break;
        case 'dirCreate':
          $fileSystem->reqCreateDir($request);
          break;
        case 'dirRename':
          $fileSystem->reqRename($request);
          break;
        case 'dirDelete':
          $fileSystem->reqDeleteDir($request);
          break;
        case 'dirCopy':
          $fileSystem->reqCopyDir($request);
          break;
        case 'dirMove':
          $fileSystem->reqMove($request);
          break;
        case 'fileList':
          $data = $fileSystem->reqGetFiles($request);
          break;
        case 'fileListPaged':
          $data = $fileSystem->reqGetFilesPaged($request);
          break;
        case 'fileListSpecified':
          $data = $fileSystem->reqGetFilesSpecified($request);
          break;
        case 'fileDelete':
          $fileSystem->reqDeleteFiles($request);
          break;
        case 'fileCopy':
          $fileSystem->reqCopyFiles($request);
          break;
        case 'fileRename':
          $fileSystem->reqRename($request);
          break;
        case 'fileMove':
          $fileSystem->reqMoveFiles($request);
          break;
        case 'fileResize':
          $data = $fileSystem->reqResizeFile($request);
          break;
        case 'fileOriginal':
          list($mimeType, $data) = $fileSystem->reqGetImageOriginal($request);
          header('Content-Type:' . $mimeType);
          fpassthru($data);
          die();
        case 'filePreview':
          list($mimeType, $data) = $fileSystem->reqGetImagePreview($request);
          header('Content-Type:' . $mimeType);
          fpassthru($data);
          die();
        case 'filePreviewAndResolution':
          $data = $fileSystem->reqGetImagePreviewAndResolution($request);
          break;
        case 'uploadFile':
          $data = $fileSystem->reqUpload($request);
          break;
        case 'getVersion':
          $data = $fileSystem->reqGetVersion($request);
          break;
        default:
          throw new MessageException(Message::createMessage(FALSE,Message::ACTION_NOT_FOUND));
      }
      $resp = new Response(NULL, $data);
    } catch (MessageException $e) {
      $resp = new Response($e->getFailMessage(), NULL);
    }

    $strResp = JsonCodec::s_toJson($resp);

    try {
      http_response_code(200);
      header('Content-Type: application/json; charset=UTF-8');
      print $strResp;
    } catch (Exception $e) {
      error_log($e);
    }
  }

  private static function iniGetBytes($val) {
    $val = trim(ini_get($val));
    if ($val != '') {
      $last = strtolower(substr($val, strlen($val) - 1));
    }
    else {
      $last = '';
    }
    if ($last !== '') {
      $val = substr($val, 0, strlen($val) - 1);
    }

    switch ($last) {
      // The 'G' modifier is available since PHP 5.1.0
      case 'g':
        $val *= 1024;
      // fall through
      case 'm':
        $val *= 1024;
      // fall through
      case 'k':
        $val *= 1024;
      // fall through
    }

    return $val;
  }

  private static function checkUploadLimit($request) {
    $isError = FALSE;
    $maxSizeParameter = NULL;
    if (isset($_SERVER['CONTENT_LENGTH'])) {
      if (
        $_SERVER['CONTENT_LENGTH'] >
        FlmngrServer::iniGetBytes('post_max_size')
      ) {
        $isError = TRUE;
        $maxSizeParameter = 'post_max_size';
      }
    }
    if (!$isError) {
      if (isset($request->files['file'])) {
        $file = $request->files['file'];
        if ($file['tmp_name'] === '') {
          $isError = TRUE;
          $maxSizeParameter = 'upload_max_filesize';
        }
      }
    }

    if ($isError) {
      $maxSizeValueRaw = ini_get($maxSizeParameter);
      $maxSizeValueFormatted = FlmngrServer::iniGetBytes($maxSizeParameter);

      $resp = new Response(
        Message::createMessage(
          FALSE,
          Message::FILE_SIZE_EXCEEDS_SYSTEM_LIMIT,
          '' . $_SERVER['CONTENT_LENGTH'],
          '' . $maxSizeValueFormatted,
          $maxSizeParameter . " = " . $maxSizeValueRaw
        ),
        NULL
      );

      $strResp = JsonCodec::s_toJson($resp);

      try {
        http_response_code(200);
        header('Content-Type: application/json; charset=UTF-8');
        print $strResp;
      } catch (Exception $e) {
        error_log($e);
      }

      return TRUE;
    }
    else {
      return FALSE;
    }

  }

  private static function upload($config) {
    try {
      $configUploader = [
        'dirFiles' => $config['dirFiles'],
        'dirTmp' => $config['dirTmp'],
        'filesystem' => $config['filesystem'],
        'config' => isset($config['uploader'])
          ? $config['uploader']
          : [],
        'request' => $config['request'],
      ];

      $dir = isset($config['request']->post['dir']) ? $config['request']->post['dir'] : NULL;
      $post = [
        'action' => $config['request']->post['action'],
        'dir' => $dir,
        'data' => JsonCodec::s_toJson([
          'action' => $config['request']->post['action'],
          'dir' => $dir,
        ]),
      ];
      FileUploaderServer::fileUploadRequest(
        $configUploader,
        $post,
        $config['request']->files
      );
    } catch (MessageException $e) {
      return new Response($e->getFailMessage(), NULL);
    }
  }

}
