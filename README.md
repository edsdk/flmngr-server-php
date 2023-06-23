[![Flmngr file manager logo](https://flmngr.com/img/favicons/favicon-64x64.png)](https://flmngr.com)

# Flmngr PHP backend

> Server-side part of the Flmngr file manager for PHP

[![Flmngr file manager screenshot](https://flmngr.com/img/browsing.jpg)](https://flmngr.com)

[Flmngr file manager](https://flmngr.com) is used to upload and manage files and images. Can be a standalone file manager (React/Angular/etc. or custom JavaScript or TypeScript integrations) or work together with [TinyMCE](https://flmngr.com/doc/install-tinymce-plugin), [CKEditor&nbsp;4](https://flmngr.com/doc/install-ckeditor-plugin), [CKEditor&nbsp;5](https://flmngr.com/doc/install-ckeditor-5-plugin), [N1ED](https://n1ed.com), or any other JS components.

This package is a server-side implementation needed to support requests from the file manager dialog when using PHP on the server. It will handle some single URL and let the file manager receive file lists and send file commands to the server.

## Install
Install the [Flmngr composer package](https://packagist.org/packages/edsdk/flmngr-server-php) using the console command in the project folder:

```
composer require edsdk/flmngr-server-php
```

Visit the page with a [detailed manual](https://flmngr.com/doc/install-file-manager-server) on how to install it.

If you prefer not to use Composer, there is a [no-Composer PHP script](https://flmngr.com/doc/install-php-file-manager-include) for such cases.

## Usage

To handle some URL you want in your web application, create a file which will be the entry point for all requests, e.g. `flmngr.php`:

```php
<?php

    \EdSDK\FlmngrServer\FlmngrServer::flmngrRequest(
        array(
            'dirFiles' => '/var/www/files',
        )
    );
```

The file `flmngr.php` should be placed on the same level as the `vendor` directory. It can be placed in some other location as well, but do not forget to change the path in the `require` call.

Do not forget to create the directories you specify and set the correct permissions (read and write) to allow access to them.

If you want to allow access to uploaded files (which is usually the case) please do not forget to open access to the files directory.

Also, please refer to the [example of using](https://flmngr.com/doc/open-file-manager) Flmngr with ImgPen for editing and uploading images.

## Debugging

In case of any problem, we have a **very** detailed Question-Answer [debug manual](https://flmngr.com/doc/file-manager-debug).

## See Also

- [Flmngr](https://flmngr.com) - Flmngr file manager.
- [Install Flmngr PHP backend](https://flmngr.com/doc/install-file-manager-server) - the detailed manual on how to install PHP file manager on the server.
- [Flmngr codepens](https://codepen.io/flmngr/pens/public) - collection of JS samples on CodePen.
- [Flmngr API](https://flmngr.com/doc/api) - API of Flmngr client.
- [N1ED](https://n1ed.com) - a website content builder with Flmngr file manager aboard, also works as a plugin for CKEditor 4, TinyMCE, which has modules for different CMSs.  


## License

GNU Lesser General Public License v3; see LICENSE.txt