<?php

/**
 * File Uploader Server package
 * Developer: N1ED
 * Website: https://n1ed.com/
 * License: GNU General Public License Version 3 or later
 **/

namespace EdSDK\FlmngrServer\lib\file;

use Exception;

class UtilsPHP
{
    public static function cleanDirectory($dir)
    {
        UtilsPHP::delete($dir, false);
    }

    public static function delete($dirOrFile, $deleteSelfDir = true)
    {
        if (is_file($dirOrFile)) {
            $result = is_dir($dirOrFile)
                ? rmdir($dirOrFile)
                : unlink($dirOrFile);
            if (!$result) {
                throw new Exception('Unable to delete file: ' . $dirOrFile);
            }
        } elseif (is_dir($dirOrFile)) {
            $scan = glob(rtrim($dirOrFile, '/') . '/*');
            foreach ($scan as $index => $path) {
                UtilsPHP::delete($path);
            }
            if ($deleteSelfDir) {
                if (!rmdir($dirOrFile)) {
                    throw new Exception(
                        'Unable to delete directory: ' . $dirOrFile
                    );
                }
            }
        }
    }

    public static function copyFile($src, $dst)
    {
        if (!copy($src, $dst)) {
            throw new Exception('Unable to copy file ' . $src . ' to ' . $dst);
        }
    }

    public static function normalizeNoEndSeparator($path)
    {
        // TODO: normalize
        return rtrim($path, '/');
    }
}
