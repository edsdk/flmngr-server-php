<?php

/**
 * File Uploader Server package
 * Developer: N1ED
 * Website: https://n1ed.com/
 * License: GNU General Public License Version 3 or later
 **/

namespace EdSDK\FlmngrServer\servlet;

use EdSDK\FlmngrServer\lib\config\IConfig;
use EdSDK\FlmngrServer\lib\file\Utils;
use Exception;

class ServletConfig implements IConfig {

  public $m_conf;

  protected $m_testConf = [];

  public function __construct($m_conf) {
    $this->m_conf = $m_conf;
  }

  public function getFS() {
    return $this->m_conf['filesystem'];
  }

  public function setTestConfig($testConf) {
    $this->m_testConf = (array) $testConf;
  }

  protected function getParameter($name, $defaultValue, $doAddTrailingSlash) {
    if (array_key_exists($name, $this->m_testConf)) {
      if ($doAddTrailingSlash) {
        return Utils::addTrailingSlash($this->m_testConf[$name]);
      }
      else {
        return $this->m_testConf[$name];
      }
    }
    else {
      if (array_key_exists($name, $this->m_conf)) {
        if ($doAddTrailingSlash) {
          return Utils::addTrailingSlash($this->m_conf[$name]);
        }
        else {
          return $this->m_conf[$name];
        }
      }
      return $defaultValue;
    }
  }

  protected function getParameterStr($name, $defaultValue) {
    return $this->getParameter($name, $defaultValue, FALSE);
  }

  protected function getParameterInt($name, $defaultValue) {
    $value = $this->getParameter($name, $defaultValue, FALSE);
    if (is_int($value) !== FALSE) {
      return $value;
    }
    else {
      error_log("Incorrect '" . $name . "' parameter integer value");
      return $defaultValue;
    }
  }

  protected function getParameterBool($name, $defaultValue) {
    $value = $this->getParameter($name, $defaultValue, FALSE);
    if (is_bool($value) !== FALSE) {
      return $value;
    }
    else {
      error_log("Incorrect '" . $name . "' parameter boolean value");
      return $defaultValue;
    }
  }

  public function getBaseDir() {
    $dir = $this->getParameter('dirFiles', NULL, TRUE);
    if ($dir == NULL) {
      throw new Exception('dirFiles not set');
    }
    if (!$this->getFS()->fsFileExists(TRUE, $dir)) {
      if (!$this->getFS()->fsMkDir(TRUE, $dir, 0777, TRUE)) {
        throw new Exception(
          "Unable to create files directory '" . $dir . "''"
        );
      }
    }
    return Utils::normalizeNoEndSeparator($dir);
  }

  public function getTmpDir() {
    $dir = $this->getParameter(
      "dirTmp",
      Utils::normalizeNoEndSeparator($this->getBaseDir()) . '/.cache/.tmp',
      TRUE
    );

    if (!$this->getFS()->fsFileExists(FALSE, $dir)) {
      if (!$this->getFS()->fsMkDir(FALSE, $dir, 0777, TRUE)) {
        throw new Exception(
          "Unable to create temporary files directory '" . $dir . "''"
        );
      }
    }
    return Utils::normalizeNoEndSeparator($dir);
  }

  public function getMaxUploadFileSize() {
    return $this->getParameterInt('maxUploadFileSize', 0);
  }

  public function getAllowedExtensions() {
    $value = $this->getParameterStr('allowedExtensions', NULL);
    if ($value === NULL) {
      return [];
    }
    $exts = explode(',', $value);
    for ($i = 0; $i < count($exts); $i++) {
      $exts[$i] = strtolower($exts[$i]);
    }
    return $exts;
  }

  public function getJpegQuality() {
    return $this->getParameterInt('jpegQuality', 95);
  }

  public function getMaxImageResizeWidth() {
    return $this->getParameterInt('maxImageResizeWidth', 5000);
  }

  public function getMaxImageResizeHeight() {
    return $this->getParameterInt('maxImageResizeHeight', 5000);
  }

  public function getCrossDomainUrl() {
    return $this->getParameterStr('crossDomainUrl', NULL);
  }

  public function doKeepUploads() {
    return $this->getParameterBool('keepUploads', FALSE);
  }

  public function isTestAllowed() {
    return $this->getParameterBool('isTestAllowed', FALSE);
  }

  public function getRelocateFromHosts() {
    $hostsStr = $this->getParameterStr('relocateFromHosts', '');
    $hostsFound = explode(',', $hostsStr);
    $hosts = [];
    for ($i = count($hostsFound) - 1; $i >= 0; $i--) {
      $host = strtolower(trim($hostsFound[$i]));
      if (strlen($host) > 0) {
        $hosts[] = $host;
      }
    }
    return $hosts;
  }
}
