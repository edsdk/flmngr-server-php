<?php

/**
 * Flmngr Server package
 * Developer: N1ED
 * Website: https://n1ed.com/
 * License: GNU General Public License Version 3 or later
 **/

namespace EdSDK\FlmngrServer\fs;

interface IFMDiskFileSystem {

    function getImagePreview($filePath, $width, $height);
    function getImageOriginal($filePath);
    function getDirs();
    function deleteDir($dirPath);
    function createDir($dirPath, $name);
    function renameFile($filePath, $newName);
    function renameDir($dirPath, $newName);
    function getFiles($dirPath); // with "/root_dir_name" in the start
    function deleteFiles($filesPaths);
    function copyFiles($filesPaths, $newPath);
    function moveFiles($filesPaths, $newPath);
    function moveDir($dirPath, $newPath);
    function copyDir($dirPath, $newPath);
    function getDirZipArchive($dirPath, $out);

}
