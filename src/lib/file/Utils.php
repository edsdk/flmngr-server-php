<?php

/**
 * File Uploader Server package
 * Developer: N1ED
 * Website: https://n1ed.com/
 * License: GNU General Public License Version 3 or later
 **/

namespace EdSDK\FlmngrServer\lib\file;

class Utils
{
    public static function getNameWithoutExt($filename)
    {
        $ext = Utils::getExt($filename);
        if ($ext == null) {
            return $filename;
        }
        return substr($filename, 0, strlen($filename) - strlen($ext) - 1);
    }

    public static function getExt($name)
    {
        $i = strrpos($name, '.');
        if ($i !== false) {
            return substr($name, $i + 1);
        }
        return null;
    }

    public static function getFreeFileName($dir, $defaultName, $alwaysWithIndex)
    {
        $i = $alwaysWithIndex ? 0 : -1;
        do {
            $i++;
            if ($i == 0) {
                $name = $defaultName;
            } else {
                $name =
                    Utils::getNameWithoutExt($defaultName) .
                    '_' .
                    $i .
                    (Utils::getExt($defaultName) != null
                        ? '.' . Utils::getExt($defaultName)
                        : '');
            }
            $filePath = $dir . $name;
            $ok = !file_exists($filePath);
        } while (!$ok);
        return $name;
    }

    const PROHIBITED_SYMBOLS = "/\\?%*:|\"<>";

    public static function fixFileName($name)
    {
        $newName = '';
        for ($i = 0; $i < strlen($name); $i++) {
            $ch = substr($name, $i, 1);
            if (strpos(Utils::PROHIBITED_SYMBOLS, $ch) !== false) {
                $ch = '_';
            }
            $newName = $newName . $ch;
        }
        return $newName;
    }

    public static function isFileNameSyntaxOk($name)
    {
        if (strlen($name) == 0 || $name == '.' || strpos($name, '..') > -1) {
            return false;
        }

        for ($i = 0; $i < strlen(Utils::PROHIBITED_SYMBOLS); $i++) {
            if (
                strpos($name, substr(Utils::PROHIBITED_SYMBOLS, $i, 1)) !==
                false
            ) {
                return false;
            }
        }

        if (strlen($name) > 260) {
            return false;
        }

        /*
         * TODO: fix this and uncomment
         * On Windows + IIS + PHP produces:
         * Warning:  preg_match(): Unknown modifier '\' in <b>...\vendor\edsdk\file-uploader-server-php\src\lib\file\Utils.php on line 83
         *
         * if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // https://stackoverflow.com/questions/6730009/validate-a-file-name-on-windows
            // https://msdn.microsoft.com/en-us/library/aa365247(v=vs.85).aspx#file_and_directory_names
            $pattern =
                "/" .
                "^" .
                "(?!" .
                "  (?:" .
                "    CON|PRN|AUX|NUL|" .
                "    COM[1-9]|LPT[1-9]" .
                "  )" .
                "  (?:\\.[^.]*)?" .
                "  $" .
                ")" .
                "[^<>:\"/\\\\|?*\\x00-\\x1F]*" .
                "[^<>:\"/\\\\|?*\\x00-\\x1F\\ .]" .
                "$/ui";
            if (!preg_match($pattern, $name))
                return false;
        }*/

        return true;
    }

    public static function isImage($name)
    {
        $exts = ['gif', 'jpg', 'jpeg', 'png'];
        $ext = Utils::getExt($name);
        for ($i = 0; $i < count($exts); $i++) {
            if ($exts[$i] === strtolower($ext)) {
                return true;
            }
        }
        return false;
    }
}
