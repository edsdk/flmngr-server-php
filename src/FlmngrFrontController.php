<?php
namespace EdSDK\FlmngrServer;
use EdSDK\FlmngrServer\lib\CommonRequest;
use EdSDK\FlmngrServer\fs\FMDiskFileSystem;

class FlmngrFrontController
{
    public $request;

    public $filesystem;

    public function __construct($config)
    {
        $request = new CommonRequest();
        $request->parseRequest();
        $this->request = $request;

        $this->filesystem = new FMDiskFileSystem($config);
        //creating filesystem instance based on config
        /*$class_name = 'EdSDK\FlmngrServer\fs\\' . $config['storage']['type'];
        if (class_exists($class_name)) {
            $this->filesystem = new $class_name($config);
        } else {
            die('FS driver not found');
        }*/
    }
}

?>
