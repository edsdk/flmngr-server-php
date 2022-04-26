<?php

/**
 * File Uploader Server package
 * Developer: N1ED
 * Website: https://n1ed.com/
 * License: GNU General Public License Version 3 or later
 **/

namespace EdSDK\FlmngrServer\lib\action;

use EdSDK\FlmngrServer\fs\FMDiskFileSystem;
use EdSDK\FlmngrServer\lib\file\FileUploadedQuick;
use EdSDK\FlmngrServer\lib\action\resp\RespUploadAddFile;
use EdSDK\FlmngrServer\lib\action\resp\Message;
use EdSDK\FlmngrServer\lib\MessageException;

class ActionQuickUpload extends AActionUploadId
{
    public function getName()
    {
        return 'upload';
    }
    public function run($req)
    {
        if ($req->m_file) {
            if (
                isset($this->m_config->request->post['dir']) &&
                $this->m_config->request->post['dir'] &&
                $this->m_config->request->post['dir'] != '/' &&
                $this->m_config->request->post['dir'] != '' &&
                $this->m_config->request->post['dir'] != '.'
            ) {
                $target_dir = basename($this->m_config->request->post['dir']);
                $path =
                    dirname($this->m_config->request->post['dir']) == '.' ||
                    dirname($this->m_config->request->post['dir']) == '/'
                        ? ''
                        : '/' . dirname($this->m_config->request->post['dir']);

                $fullPath = basename($this->m_config->getBaseDir()) . $path;
                $this->getFS()->fsMkDir(true, $fullPath . '/' . $target_dir);
                $uploadDir = '/' . $target_dir;
                $req->m_relativePath = $this->m_config->request->post['dir'];
            } else {
                $target_dir = '';
                $fullPath = basename($this->m_config->getBaseDir());
                $uploadDir =
                    $fullPath .
                    '/' .
                    $target_dir .
                    '/';
                $req->m_relativePath = '/';
            }

            $file = new FileUploadedQuick(
                $this->m_config,
                $uploadDir,
                $req->m_fileName,
                $req->m_fileName,
                $req->m_relativePath
            );
            $file->upload($req->m_file);

            $resp = new RespUploadAddFile();
            $resp->file = $file->getData();

            return $resp;
        } else {
            throw new MessageException(
                Message::createMessage(Message::FILES_NOT_SET)
            );
        }
    }
}
