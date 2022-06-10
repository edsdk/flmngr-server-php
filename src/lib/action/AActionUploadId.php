<?php

/**
 * File Uploader Server package
 * Developer: N1ED
 * Website: https://n1ed.com/
 * License: GNU General Public License Version 3 or later
 **/

namespace EdSDK\FlmngrServer\lib\action;

use EdSDK\FlmngrServer\model\Message;
use EdSDK\FlmngrServer\lib\MessageException;

abstract class AActionUploadId extends AAction {

  protected function validateUploadId($req) {
    if ($req->uploadId === NULL) {
      throw new MessageException(
        Message::createMessage(Message::UPLOAD_ID_NOT_SET)
      );
    }

    $dir = $this->m_config->getTmpDir() . '/' . $req->uploadId;
    if (!$this->getFS()->fsFileExists(FALSE, $dir) || !$this->getFS()
        ->fsIsDir(FALSE, $dir)) {
      throw new MessageException(
        Message::createMessage(Message::UPLOAD_ID_INCORRECT)
      );
    }
  }
}
