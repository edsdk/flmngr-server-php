<?php

// The package's own classes for the tests, with or without a Composer install
spl_autoload_register(function ($class) {
  $prefix = 'EdSDK\\FlmngrServer\\';
  if (strncmp($class, $prefix, strlen($prefix)) === 0) {
    $file = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
      require $file;
    }
  }
});
