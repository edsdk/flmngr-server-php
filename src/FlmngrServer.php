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

use EdSDK\FileUploaderServer\FileUploaderServer;
use EdSDK\FileUploaderServer\lib\JsonCodec;
use EdSDK\FileUploaderServer\lib\action\resp\Message;
use EdSDK\FileUploaderServer\lib\MessageException;

use EdSDK\FlmngrServer\fs\FMDiskFileSystem;

class FlmngrServer {

    static function flmngrRequest($config) {

        $action = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['action']))
                $action = $_POST['action'];
            if ($action == null && isset($_POST["data"])) {
                $configUploader = array(
                    "dirFiles" => $config["dirFiles"],
                    "dirTmp" => $config["dirTmp"],
                    "config" => isset($config["uploader"]) ? $config["uploader"] : array()
                );
                FileUploaderServer::fileUploadRequest($configUploader);
                return;
            }
        } else
            $action = $_GET['action'];

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
                case 'fileOriginal':
                    $resp = FlmngrServer::reqFileOriginal($config); // will die after valid response or throw MessageException
                    break;
                case 'filePreview':
                    $resp = FlmngrServer::reqFilePreview($config); // will die after valid response or throw MessageException
                    break;
                default:
                    $resp = new Response(Message::createMessage(Message::ACTION_NOT_FOUND), null);
            }
        } catch (MessageException $e) {
            $resp = new Response($e->getFailMessage(), null);
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
        $dirPath = $_POST['d'];
        $newPath = $_POST['n'];
        try {
            $fileSystem = new FMDiskFileSystem($config);
            $fileSystem->copyDir($dirPath, $newPath);
            return new Response(null, true);
        } catch (MessageException $e) {
            return new Response($e->getFailMessage(), null);
        }
    }

    private static function reqDirCreate($config) {
        $dirPath = $_POST['d'];
        $name = $_POST['n'];
        try {
            $fileSystem = new FMDiskFileSystem($config);
            $fileSystem->createDir($dirPath, $name);
            return new Response(null, true);
        } catch (MessageException $e) {
            return new Response($e->getFailMessage(), null);
        }
    }

    private static function reqDirDelete($config) {
        $dirPath = $_POST['d'];
        try {
            $fileSystem = new FMDiskFileSystem($config);
            $fileSystem->deleteDir($dirPath);
            return new Response(null, true);
        } catch (MessageException $e) {
            return new Response($e->getFailMessage(), null);
        }
    }

    private static function reqDirDownload($config) {
        $dirPath = $_GET['d'];
        // TODO:
    }

    private static function reqDirList($config) {
        try {
            $fileSystem = new FMDiskFileSystem($config);
            $dirs = $fileSystem->getDirs();
        } catch (MessageException $e) {
            return new Response($e->getFailMessage(), null);
        }
        return new Response(null, $dirs);
    }

    private static function reqDirMove($config) {
        $dirPath = $_POST['d'];
        $newPath = $_POST['n'];
        try {
            $fileSystem = new FMDiskFileSystem($config);
            $fileSystem->moveDir($dirPath, $newPath);
            return new Response(null, true);
        } catch (MessageException $e) {
            return new Response($e->getFailMessage(), null);
        }
    }

    private static function reqDirRename($config) {
        $dirPath = $_POST['d'];
        $newName = $_POST['n'];
        try {
            $fileSystem = new FMDiskFileSystem($config);
            $fileSystem->renameDir($dirPath, $newName);
            return new Response(null, true);
        } catch (MessageException $e) {
            return new Response($e->getFailMessage(), null);
        }
    }

    private static function reqFileCopy($config) {
        $files = $_POST['fs'];
        $newPath = $_POST['n'];

        $filesPaths = preg_split("/\|/", $files);

        try {
            $fileSystem = new FMDiskFileSystem($config);
            $fileSystem->copyFiles($filesPaths, $newPath);
            return new Response(null, true);
        } catch (MessageException $e) {
            return new Response($e->getFailMessage(), null);
        }
    }

    private static function reqFileDelete($config) {
        $files = $_POST['fs'];

        $filesPaths = preg_split("/\|/", $files);

        try {
            $fileSystem = new FMDiskFileSystem($config);
            $fileSystem->deleteFiles($filesPaths);
            return new Response(null, true);
        } catch (MessageException $e) {
            return new Response($e->getFailMessage(), null);
        }
    }

    private static function reqFileList($config) {
        $path = $_POST['d'];

        try {
            $fileSystem = new FMDiskFileSystem($config);
            $files = $fileSystem->getFiles($path);
            return new Response(null, $files);
        } catch (MessageException $e) {
            return new Response($e->getFailMessage(), null);
        }
    }

    private static function reqFileMove($config) {
        $files = $_POST['fs'];
        $newPath = $_POST['n'];

        $filesPaths = preg_split("/\|/", $files);

        try {
            $fileSystem = new FMDiskFileSystem($config);
            $fileSystem->moveFiles($filesPaths, $newPath);
            return new Response(null, true);
        } catch (MessageException $e) {
            return new Response($e->getFailMessage(), null);
        }
    }

    private static function reqFileOriginal($config) {
        $filePath = $_GET['f'];

        try {
            $fileSystem = new FMDiskFileSystem($config);
            list($mimeType, $f) = $fileSystem->getImageOriginal($filePath);
            header('Content-Type:' . $mimeType);
            fpassthru($f);
            die;
        } catch (MessageException $e) {
            return new Response($e->getFailMessage(), null);
        }
    }

    private static function reqFilePreview($config) {
        $filePath = $_GET['f'];
        $width = $_GET['width'];
        $height = $_GET['height'];

        try {
            $fileSystem = new FMDiskFileSystem($config);
            list($mimeType, $f) = $fileSystem->getImagePreview($filePath, $width, $height);
            header('Content-Type:' . $mimeType);
            fpassthru($f);
            die;
        } catch (MessageException $e) {
            return new Response($e->getFailMessage(), null);
        }
    }

    private static function reqFileRename($config) {
        $filePath = $_POST['f'];
        $newName = $_POST['n'];

        try {
            $fileSystem = new FMDiskFileSystem($config);
            $fileSystem->renameFile($filePath, $newName);
            return new Response(null, true);
        } catch (MessageException $e) {
            return new Response($e->getFailMessage(), null);
        }
    }

}

