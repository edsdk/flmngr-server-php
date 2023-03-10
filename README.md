# Flmngr - file manager

> Module for PHP for server-side file management

Use Flmngr file manager to upload and manage files and images on your website. Works great together with [ImgPen](https://imgpen.com) which adds feature to edit images right from file manager.

Can be a standalone file manager (React/Angular/etc. or custom JavaScript or TypeScript integrations) or work together with CKEditor 4, CKEditor 5, [N1ED](https://n1ed.com) or any other JS components.

## Install

With [Composer](https://getcomposer.org/) installed, run

```
$ composer require edsdk/flmngr-server-php
```


## Usage

To handle some URL you want in your web application, create a file which will be entry point for all requests, e. g. `flmngr.php`:

```php
<?php

    \EdSDK\FlmngrServer\FlmngrServer::flmngrRequest(
        array(
            // Directory of your files storage
            'dirFiles' => '/var/www/files',
    
            // Optionally: if you wish to use separate directory for cache files
            // This is handy when your "dirFiles" is slower a local disk,
            // for example this is a drive mounted over a network.
            //'dirCache' => '/var/www/cache'
        )
    );
```

This file `flmngr.php` should be placed on the same level with `vendor` directory. If can be placed in some other place too, but do not forget to change path in `require` call.

Do not forget to create directories you point to and set correct permissions (read and write) for access to them.

If you want to allow access to uploaded files (usually you do) please do not forget to open access to files directory.

Please also see [example of usage](https://flmngr.com/doc/open-file-manager) Flmngr with ImgPen for editing and uploading images.


## Server languages support

Current package is targeted to serve uploads inside PHP environment of any version.

## See Also

- [Flmngr](https://flmngr.com) - Flmngr file manager.
- [Flmngr demo](https://flmngr.com/doc/open-file-manager) - Flmngr file manager demo.
- [Flmngr API](https://flmngr.com/doc/api) - API of Flmngr client.
- [Flmngr Composer docs](https://flmngr.com/doc/install-php-file-manager-composer) - more info about this package.
- [N1ED](https://n1ed.com) - WYSIWYG editor with Flmngr file manager aboard, also works as a plugin for CKEditor 4, TinyMCE, has modules for different CMSs.  


## License

GNU General Public License version 3 or later; see LICENSE.txt