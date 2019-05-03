<?php

namespace EdSDK\FlmngrServer;

use EdSDK\FileUploaderServer\FileUploader;
use EdSDK\FileUploaderServer\JsonCodec;
use EdSDK\FileUploaderServer\Message;
use EdSDK\FileUploaderServer\MessageException;
use EdSDK\FlmngrServer\FMDiskFileSystem;
use EdSDK\FlmngrServer\Response;


class Flmngr {

    static function flmngrRequest($config) {

        $action = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $action = $_POST['action'];
            if ($action == null && isset($_POST["data"])) {
                $configUploader = array(
                    "dir" => $config["dirFiles"],
                    "config" => $config["uploader"]
                );
                FileUploader::fileUploadRequest($configUploader);
                return;
            }
        } else
            $action = $_GET['action'];

        try {
            switch ($action) {
                case 'dirList':
                    $resp = Flmngr::reqDirList($config);
                    break;
                case 'dirCreate':
                    $resp = Flmngr::reqDirCreate($config);
                    break;
                case 'dirRename':
                    $resp = Flmngr::reqDirRename($config);
                    break;
                case 'dirDelete':
                    $resp = Flmngr::reqDirDelete($config);
                    break;
                case 'dirCopy':
                    $resp = Flmngr::reqDirCopy($config);
                    break;
                case 'dirMove':
                    $resp = Flmngr::reqDirMove($config);
                    break;
                case 'dirDownload':
                    $resp = Flmngr::reqDirDownload($config);
                    break;
                case 'fileList':
                    $resp = Flmngr::reqFileList($config);
                    break;
                case 'fileDelete':
                    $resp = Flmngr::reqFileDelete($config);
                    break;
                case 'fileCopy':
                    $resp = Flmngr::reqFileCopy($config);
                    break;
                case 'fileRename':
                    $resp = Flmngr::reqFileRename($config);
                    break;
                case 'fileMove':
                    $resp = Flmngr::reqFileMove($config);
                    break;
                case 'fileOriginal':
                    $resp = Flmngr::reqFileOriginal($config);
                    break;
                case 'filePreview':
                    $resp = Flmngr::reqFilePreview($config);
                    break;
                default:
                    $resp = new Response(Message::createMessage(Message::ACTION_NOT_FOUND), null);
            }
        } catch (MessageException $e) {
            $resp = new Response($e->getFailMessage(), null);
        }

        $json = JsonCodec::s_toJson($resp);

        // TODO: send response

    }

    private static function reqDirCopy($config) {
        $dirPath = $_GET['d'];
        $newPath = $_GET['n'];
        try {
            $fileSystem = new FMDiskFileSystem($config);
            $fileSystem->copyDir($dirPath, $newPath);
            return new Response(null, true);
        } catch (MessageException $e) {
            return new Response($e->getFailMessage(), null);
        }
    }

    private static function reqDirCreate($config) {
        $dirPath = $_GET['d'];
        $name = $_GET['n'];
        try {
            $fileSystem = new FMDiskFileSystem($config);
            $fileSystem->createDir($dirPath, $name);
            return new Response(null, true);
        } catch (MessageException $e) {
            return new Response($e->getFailMessage(), null);
        }
    }

    private static function reqDirDelete($config) {
        $dirPath = $_GET['d'];
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
        $dirPath = $_GET['d'];
        $newPath = $_GET['n'];
        try {
            $fileSystem = new FMDiskFileSystem($config);
            $fileSystem->moveDir($dirPath, $newPath);
            return new Response(null, true);
        } catch (MessageException $e) {
            return new Response($e->getFailMessage(), null);
        }
    }

    private static function reqDirRename($config) {
        $dirPath = $_GET['d'];
        $newName = $_GET['n'];
        try {
            $fileSystem = new FMDiskFileSystem($config);
            $fileSystem->renameDir($dirPath, $newName);
            return new Response(null, true);
        } catch (MessageException $e) {
            return new Response($e->getFailMessage(), null);
        }
    }

    private static function reqFileCopy($config) {
        $files = $_GET['fs'];
        $newPath = $_GET['n'];

        $filesPaths = preg_split($files, "/|/");

        try {
            $fileSystem = new FMDiskFileSystem($config);
            $fileSystem->copyFiles($filesPaths, $newPath);
            return new Response(null, true);
        } catch (MessageException $e) {
            return new Response($e->getFailMessage(), null);
        }
    }

    private static function reqFileDelete($config) {
        $files = $_GET['fs'];

        $filesPaths = preg_split($files, "/|/");

        try {
            $fileSystem = new FMDiskFileSystem($config);
            $fileSystem->deleteFiles($filesPaths);
            return new Response(null, true);
        } catch (MessageException $e) {
            return new Response($e->getFailMessage(), null);
        }
    }

    private static function reqFileList($config) {
        $path = $_GET['d'];

        try {
            $fileSystem = new FMDiskFileSystem($config);
            $files = $fileSystem->getFiles($path);
            return new Response(null, $files);
        } catch (MessageException $e) {
            return new Response($e->getFailMessage(), null);
        }
    }

    private static function reqFileMove($config) {
        $files = $_GET['fs'];
        $newPath = $_GET['n'];

        $filesPaths = preg_split($files, "/|/");

        try {
            $fileSystem = new FMDiskFileSystem($config);
            $fileSystem->moveDir($filesPaths, $newPath);
            return new Response(null, true);
        } catch (MessageException $e) {
            return new Response($e->getFailMessage(), null);
        }
    }

    private static function reqFileOriginal($config) {
        $filePath = $_GET['f'];
        // TODO:
    }

    private static function reqFilePreview($config) {
        $filePath = $_GET['f'];
        // TODO:
    }

    private static function reqFileRename($config) {
        $filePath = $_GET['f'];
        $newName = $_GET['n'];

        try {
            $fileSystem = new FMDiskFileSystem($config);
            $fileSystem->renameFile($filePath, $newName);
            return new Response(null, true);
        } catch (MessageException $e) {
            return new Response($e->getFailMessage(), null);
        }
    }

}

