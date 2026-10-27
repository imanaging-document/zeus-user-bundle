<?php

$autoload = getenv('SSO_TEST_AUTOLOAD') ?: dirname(__DIR__) . '/vendor/autoload.php';
require $autoload;
spl_autoload_register(static function (string $class): void {
  $prefix = 'Imanaging\\ZeusUserBundle\\';
  if (str_starts_with($class, $prefix)) {
    $path = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
      require $path;
    }
  }
}, true, true);
