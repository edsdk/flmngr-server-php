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

use EdSDK\FlmngrServer\lib\JsonCodec;
use EdSDK\FlmngrServer\lib\action\resp\Message;
use EdSDK\FlmngrServer\lib\MessageException;

ini_set('display_errors', 0);

class FlmngrServer
{
    static function flmngrRequest($config)
    {
        if (FlmngrServer::checkUploadLimit())
            return; // file size exceed the limit from php.ini

        if (!isset($config['dirCache']) && isset($config['driverFiles'])) {
            $resp = new Response("Set cache dir when using another files driver", null);
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

        $frontController = new FlmngrFrontController($config);
        $request = $frontController->request;
        $fileSystem = $frontController->filesystem;

        if (isset($request->post['embedPreviews'])) {
            $fileSystem->embedPreviews = $request->post['embedPreviews'];
        }

        $action = null;
        if ($request->requestMethod === 'POST') {
            if (isset($request->post['action']))
                $action = $request->post['action'];
        } else {
            if ($request->requestMethod === 'GET') {
                $action = $request->get['action'];
            } else {
                return;
            }
        }

        try {
            $data = true; // will be optionally filled by request
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
                case 'uploadFile':
                    $data = $fileSystem->reqUpload($request);
                    break;
                case 'getVersion':
                    $data = $fileSystem->reqGetVersion($request);
                    break;
                default:
                    throw new MessageException(Message::createMessage(Message::ACTION_NOT_FOUND));
            }
            $resp = new Response(null, $data);
        } catch (MessageException $e) {
            $resp = new Response($e->getFailMessage(), null);
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

    private static function iniGetBytes($val)
    {
        $val = trim(ini_get($val));
        if ($val != '') {
            $last = strtolower(substr($val, strlen($val) - 1));
        } else {
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

    private static function checkUploadLimit()
    {
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            if (
                $_SERVER['CONTENT_LENGTH'] >
                FlmngrServer::iniGetBytes('post_max_size')
            ) {
                $resp = new Response(
                    Message::createMessage(
                        Message::FILE_SIZE_EXCEEDS_SYSTEM_LIMIT,
                        '' . $_SERVER['CONTENT_LENGTH'],
                        '' . FlmngrServer::iniGetBytes('post_max_size')
                    ),
                    null
                );

                $strResp = JsonCodec::s_toJson($resp);

                try {
                    http_response_code(200);
                    header('Content-Type: application/json; charset=UTF-8');
                    print $strResp;
                } catch (Exception $e) {
                    error_log($e);
                }

                return true;
            }
        }
        return false;
    }

    private static function upload($config)
    {
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

            $dir = isset($config['request']->post['dir']) ? $config['request']->post['dir'] : null;
            $post = [
                'action' => $config['request']->post['action'],
                'dir' => $dir,
                'data' => JsonCodec::s_toJson([
                    'action' => $config['request']->post['action'],
                    'dir' => $dir
                ]),
            ];
            FileUploaderServer::fileUploadRequest(
                $configUploader,
                $post,
                $config['request']->files
            );
        } catch (MessageException $e) {
            return new Response($e->getFailMessage(), null);
        }
    }

}
