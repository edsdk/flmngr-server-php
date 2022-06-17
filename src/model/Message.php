<?php

/**
 * File Uploader Server package
 * Developer: N1ED
 * Website: https://n1ed.com/
 * License: GNU General Public License Version 3 or later
 **/

namespace EdSDK\FlmngrServer\model;

class Message {

  const FILE_ERROR_SYNTAX = -1; // args: name

  const FILE_ERROR_DOES_NOT_EXIST = -2;

  const FILE_ERROR_INCORRECT_IMAGE_EXT_CHANGE = -3; // args: oldExt, newExt

  const ACTION_NOT_FOUND = 0;

  const UNABLE_TO_CREATE_UPLOAD_DIR = 1;

  const UPLOAD_ID_NOT_SET = 2;

  const UPLOAD_ID_INCORRECT = 3;

  const MALFORMED_REQUEST = 4;

  const NO_FILE_UPLOADED = 5;

  const FILE_SIZE_EXCEEDS_LIMIT = 6; // args: name, size, maxSize

  const INCORRECT_EXTENSION = 7; // args: name, allowedExtsStr

  const WRITING_FILE_ERROR = 8; // args: name

  const UNABLE_TO_DELETE_UPLOAD_DIR = 9;

  const UNABLE_TO_DELETE_FILE = 10; // args: name

  const DIR_DOES_NOT_EXIST = 11; // args: name

  const FILES_NOT_SET = 12;

  const FILE_IS_NOT_IMAGE = 13;

  const DUPLICATE_NAME = 14;

  const FILE_ALREADY_EXISTS = 15; // args: name

  const FILES_ERRORS = 16; // files args: filesWithErrors

  const UNABLE_TO_COPY_FILE = 17; // args: name, dstName

  const IMAGE_PROCESS_ERROR = 18;

  const MAX_RESIZE_WIDTH_EXCEEDED = 19; // args: width, maxWidth, name

  const MAX_RESIZE_HEIGHT_EXCEEDED = 20; // args: height, maxHeight, name

  const UNABLE_TO_WRITE_IMAGE_TO_FILE = 21; // args: name

  const INTERNAL_ERROR = 22;

  const DOWNLOAD_FAIL_CODE = 23; // args: httpCode

  const DOWNLOAD_FAIL_IO = 24; // args: IO_Exceptions_text

  const DOWNLOAD_FAIL_HOST_DENIED = 25; // args: host name

  const DOWNLOAD_FAIL_INCORRECT_URL = 26; // args: url

  // 27 and 28 reserved for demo server
  const FILE_SIZE_EXCEEDS_SYSTEM_LIMIT = 29; // args: size, maxSize, like #6, but a limit from php.ini file

  const FILE_SIZE_EXCEEDS_SYSTEM_LIMIT_2 = 30; // args: size, maxSize, strParameterInfo, like #30, but with info about wrong parameter

  const FM_FILE_DOES_NOT_EXIST = 10001; // File does not exist: %1

  const FM_UNABLE_TO_WRITE_PREVIEW_IN_CACHE_DIR = 10002; // Unable to write a preview into cache directory

  const FM_UNABLE_TO_CREATE_PREVIEW = 10003; // Unable to create a preview

  const FM_DIR_NAME_CONTAINS_INVALID_SYMBOLS = 10004; // Directory name contains invalid symbols

  const FM_DIR_NAME_INCORRECT_ROOT = 10005; // Directory has incorrect root

  const FM_FILE_IS_NOT_IMAGE = 10006; // File is not an image

  const FM_ROOT_DIR_DOES_NOT_EXIST = 10007; // Root directory does not exists

  const FM_UNABLE_TO_LIST_CHILDREN_IN_DIRECTORY = 10008; // Unable to list children in the directory

  const FM_UNABLE_TO_DELETE_DIRECTORY = 10009; // Unable to delete the directory

  const FM_UNABLE_TO_CREATE_DIRECTORY = 10010; // Unable to create a directory: %1

  const FM_UNABLE_TO_RENAME = 10011; // Unable to rename

  const FM_DIR_CANNOT_BE_READ = 10012; // Directory can not be read

  const FM_ERROR_ON_COPYING_FILES = 10013; // Error on copying files

  const FM_ERROR_ON_MOVING_FILES = 10014; // Error on moving files

  const FM_NOT_ERROR_NOT_NEEDED_TO_UPDATE = 10015;

  const FM_ROOT_DIR_IS_NOT_SET = 10016; // Shows incorrect configuration

  const FM_DIR_IS_NOT_READABLE = 10017; // %1 is dir

  const FM_DIR_IS_NOT_WRITABLE = 10018; // %1 is dir

  public $code;

  public $args;

  public $isCacheIssue;

  private function __construct($isCacheIssue) {
    $this->isCacheIssue = $isCacheIssue;
  }

  public static function createMessage(
    $isCacheException,
    $code,
    $arg1 = NULL,
    $arg2 = NULL,
    $arg3 = NULL
  ) {
    $msg = new Message($isCacheException);
    $msg->code = $code;
    if ($arg1 != NULL) {
      $msg->args = [];
      $msg->args[] = $arg1;
      if ($arg2 != NULL) {
        $msg->args[] = $arg2;
        if ($arg3 != NULL) {
          $msg->args[] = $arg3;
        }
      }
    }
    return $msg;
  }

}
