<?php

if (file_exists(__DIR__ . '/../../../autoload.php'))
{
	require __DIR__ . '/../../../autoload.php';
}
else
{
	require __DIR__ . '/../vendor/autoload.php';
}

use Infira\console\Bin;

Bin::init(realpath(__DIR__));
/**
 * @var \Symfony\Component\Console\Application $app
 */

/**
 * @var \Symfony\Component\Console\Application $app
 */
Bin::run('poesis-mg', function (&$app)
{
	$app->add(new \Infira\pmg\Pmg());
});