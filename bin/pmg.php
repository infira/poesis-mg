<?php
if (file_exists(__DIR__.'/../../../autoload.php')) {
    require __DIR__.'/../../../autoload.php';
}
else {
    require __DIR__.'/../vendor/autoload.php';
}

use Infira\Console\Bin;
use Infira\pmg\Pmg;
use Symfony\Component\Console\Application;

Bin::init();
Bin::run('poesis-mg', function (Application $app) {
    $app->add(new Pmg());
});