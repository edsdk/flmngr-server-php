<?php

/**
 * Flmngr Server package
 * Developer: N1ED
 * Website: https://n1ed.com/
 * License: GNU General Public License Version 3 or later
 **/

namespace EdSDK\FlmngrServer;

use EdSDK\FlmngrServer\resp\Response;
use Exception;

use EdSDK\FlmngrServer\FileUploaderServer;
use EdSDK\FlmngrServer\lib\JsonCodec;
use EdSDK\FlmngrServer\lib\action\resp\Message;
use EdSDK\FlmngrServer\lib\MessageException;
use EdSDK\FlmngrServer\FlmngrFrontController;
use EdSDK\FlmngrServer\fs\FMDiskFileSystem;

class FlmngrServer {

  static function flmngrRequest($config) {


    $request = (new FlmngrFrontController())->request;

    $action = NULL;
    if ($request->requestMethod === 'POST') {

      // Default action is "upload" if requester tries to upload a file
      // This is support for generic files upload in WYSIWYG editors
      if ((isset($request->files["file"]) || isset($request->files["upload"])) && (!isset($request->post["action"])) && (!isset($request->post['data']))) {
        $json = array(
          "action" => "upload"
        );
        $request->post["data"] = json_encode($json);
      }

      if (isset($request->post['action'])) {
        $action = $request->post['action'];
      }
      if ($action == NULL && isset($request->post["data"])) {
        $configUploader = [
          "dirFiles" => $config["dirFiles"],
          "dirTmp" => $config["dirTmp"],
          "config" => isset($config["uploader"]) ? $config["uploader"] : [],
        ];
        FileUploaderServer::fileUploadRequest($configUploader, $request->post, $request->files);
        return;
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
    $config['request'] = $request;

    try {
      switch ($action) {
        case 'dirList':
          $resp = FlmngrServer::reqDirList($config);
          break;
        case 'dirCreate':
          $resp = FlmngrServer::reqDirCreate($config);
          break;
        case 'dirRename':
          $resp = FlmngrServer::reqDirRename($config);
          break;
        case 'dirDelete':
          $resp = FlmngrServer::reqDirDelete($config);
          break;
        case 'dirCopy':
          $resp = FlmngrServer::reqDirCopy($config);
          break;
        case 'dirMove':
          $resp = FlmngrServer::reqDirMove($config);
          break;
        case 'dirDownload':
          $resp = FlmngrServer::reqDirDownload($config);
          break;
        case 'fileList':
          $resp = FlmngrServer::reqFileList($config);
          break;
        case 'fileDelete':
          $resp = FlmngrServer::reqFileDelete($config);
          break;
        case 'fileCopy':
          $resp = FlmngrServer::reqFileCopy($config);
          break;
        case 'fileRename':
          $resp = FlmngrServer::reqFileRename($config);
          break;
        case 'fileMove':
          $resp = FlmngrServer::reqFileMove($config);
          break;
        case 'fileResize':
          $resp = FlmngrServer::reqFileResize($config);
          break;
        case 'fileOriginal':
          $resp = FlmngrServer::reqFileOriginal($config); // will die after valid response or throw MessageException
          break;
        case 'filePreview':
          $resp = FlmngrServer::reqFilePreview($config); // will die after valid response or throw MessageException
          break;
        case 'upload':
          $resp = FlmngrServer::upload($config); // will die after valid response or throw MessageException
          break;
        case 'getVersion':
          $resp = FlmngrServer::getVersion();
          break;
        default:
          $resp = new Response(Message::createMessage(Message::ACTION_NOT_FOUND), NULL);
      }
    } catch (MessageException $e) {
      $resp = new Response($e->getFailMessage(), NULL);
    }

    //print_r($resp);
    $strResp = JsonCodec::s_toJson($resp);

    try {
      http_response_code(200);
      header('Content-Type: application/json; charset=UTF-8');
      print($strResp);
    } catch (Exception $e) {
      error_log($e);
    }

  }

  private static function reqDirCopy($config) {
    $dirPath = $config['request']->post['d'];
    $newPath = $config['request']->post['n'];
    try {
      $fileSystem = new FMDiskFileSystem($config);
      $fileSystem->copyDir($dirPath, $newPath);
      return new Response(NULL, TRUE);
    } catch (MessageException $e) {
      return new Response($e->getFailMessage(), NULL);
    }
  }

  private static function reqDirCreate($config) {
    $dirPath = $config['request']->post['d'];
    $name = $config['request']->post['n'];
    try {
      $fileSystem = new FMDiskFileSystem($config);
      $fileSystem->createDir($dirPath, $name);
      return new Response(NULL, TRUE);
    } catch (MessageException $e) {
      return new Response($e->getFailMessage(), NULL);
    }
  }

  private static function reqDirDelete($config) {
    $dirPath = $config['request']->post['d'];
    try {
      $fileSystem = new FMDiskFileSystem($config);
      $fileSystem->deleteDir($dirPath);
      return new Response(NULL, TRUE);
    } catch (MessageException $e) {
      return new Response($e->getFailMessage(), NULL);
    }
  }

  private static function reqDirDownload($config) {
    $dirPath = $config['request']->get['d'];
    // TODO:
  }

  private static function reqDirList($config) {
    try {
      $fileSystem = new FMDiskFileSystem($config);
      $dirs = $fileSystem->getDirs();
    } catch (MessageException $e) {
      return new Response($e->getFailMessage(), NULL);
    }
    return new Response(NULL, $dirs);
  }

  private static function reqDirMove($config) {
    $dirPath = $config['request']->post['d'];
    $newPath = $config['request']->post['n'];
    try {
      $fileSystem = new FMDiskFileSystem($config);
      $fileSystem->moveDir($dirPath, $newPath);
      return new Response(NULL, TRUE);
    } catch (MessageException $e) {
      return new Response($e->getFailMessage(), NULL);
    }
  }

  private static function reqDirRename($config) {
    $dirPath = $config['request']->post['d'];
    $newName = $config['request']->post['n'];
    try {
      $fileSystem = new FMDiskFileSystem($config);
      $fileSystem->renameDir($dirPath, $newName);
      return new Response(NULL, TRUE);
    } catch (MessageException $e) {
      return new Response($e->getFailMessage(), NULL);
    }
  }

  private static function reqFileCopy($config) {
    $files = $config['request']->post['fs'];
    $newPath = $config['request']->post['n'];

    $filesPaths = preg_split("/\|/", $files);

    try {
      $fileSystem = new FMDiskFileSystem($config);
      $fileSystem->copyFiles($filesPaths, $newPath);
      return new Response(NULL, TRUE);
    } catch (MessageException $e) {
      return new Response($e->getFailMessage(), NULL);
    }
  }

  private static function reqFileDelete($config) {
    $files = $config['request']->post['fs'];

    $filesPaths = preg_split("/\|/", $files);

    try {
      $fileSystem = new FMDiskFileSystem($config);
      $fileSystem->deleteFiles($filesPaths);
      return new Response(NULL, TRUE);
    } catch (MessageException $e) {
      return new Response($e->getFailMessage(), NULL);
    }
  }

  private static function reqFileList($config) {
    $path = $config['request']->post['d'];

    try {
      $fileSystem = new FMDiskFileSystem($config);
      $files = $fileSystem->getFiles($path);
      return new Response(NULL, $files);
    } catch (MessageException $e) {
      return new Response($e->getFailMessage(), NULL);
    }
  }

  private static function reqFileMove($config) {
    $files = $config['request']->post['fs'];
    $newPath = $config['request']->post['n'];

    $filesPaths = preg_split("/\|/", $files);

    try {
      $fileSystem = new FMDiskFileSystem($config);
      $fileSystem->moveFiles($filesPaths, $newPath);
      return new Response(NULL, TRUE);
    } catch (MessageException $e) {
      return new Response($e->getFailMessage(), NULL);
    }
  }

  private static function reqFileOriginal($config) {
    $filePath = $config['request']->get['f'];

    try {
      $fileSystem = new FMDiskFileSystem($config);
      list($mimeType, $f) = $fileSystem->getImageOriginal($filePath);
      header('Content-Type:' . $mimeType);
      fpassthru($f);
      die;
    } catch (MessageException $e) {
      return new Response($e->getFailMessage(), NULL);
    }
  }

  private static function reqFilePreview($config) {
    $filePath = $config['request']->get['f'];
    $width = $config['request']->get['width'];
    $height = $config['request']->get['height'];

    try {
      $fileSystem = new FMDiskFileSystem($config);
      list($mimeType, $f) = $fileSystem->getImagePreview($filePath, $width, $height);
      header('Content-Type:' . $mimeType);
      fpassthru($f);
      die;
    } catch (MessageException $e) {
      return new Response($e->getFailMessage(), NULL);
    }
  }

  private static function reqFileResize($config) {
    $filePath = $config['request']->post['f'];
    $newFileNameWithoutExt = $config['request']->post['n'];
    $maxWidth = $config['request']->post['mw'];
    $maxHeight = $config['request']->post['mh'];

    $mode = $config['request']->post['mode'];

    try {
      $fileSystem = new FMDiskFileSystem($config);
      $resizedFilePath = $fileSystem->resizeFile($filePath, $newFileNameWithoutExt, $maxWidth, $maxHeight, $mode);
      return new Response(NULL, $resizedFilePath);
    } catch (MessageException $e) {
      return new Response($e->getFailMessage(), NULL);
    }
  }

  private static function reqFileRename($config) {
    $filePath = $config['request']->post['f'];
    $newName = $config['request']->post['n'];

    try {
      $fileSystem = new FMDiskFileSystem($config);
      $fileSystem->renameFile($filePath, $newName);
      return new Response(NULL, TRUE);
    } catch (MessageException $e) {
      return new Response($e->getFailMessage(), NULL);
    }
  }

  private static function upload($config){
    try {
      $configUploader = [
          'dirFiles' => $config['dirFiles'],
          'dirTmp' => $config['dirTmp'],
          'config' => isset($config['uploader'])
              ? $config['uploader']
              : [],
          'request' => $config['request']
      ];

      $post = [
        'action' => $config['request']->post['action'],
        'dir' => $config['request']->post['dir'],
        'data' => JsonCodec::s_toJson(['action' => $config['request']->post['action'], 'dir' => $config['request']->post['dir']])
      ];
      FileUploaderServer::fileUploadRequest($configUploader, $post, $config['request']->files);
  } catch (MessageException $e) {
      return new Response($e->getFailMessage(), null);
  }
  }

  private static function getVersion() {
    return new Response(NULL, ["version" => "3", "language" => "php"]);
  }

}

