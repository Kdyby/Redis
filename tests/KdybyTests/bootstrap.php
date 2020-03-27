<?php

declare(strict_types = 1);

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

$loader = include __DIR__ . '/../../vendor/autoload.php';
if (!$loader) {
	echo 'Install Nette Tester using `composer update --dev`';
	exit(1);
}

// because of old nette/tester
error_reporting(E_ALL & ~(E_DEPRECATED | E_WARNING));

// configure environment
Tester\Environment::setup();
\class_alias('Tester\Assert', 'Assert');
\date_default_timezone_set('Europe/Prague');

// create temporary directory
\define('TEMP_DIR', __DIR__ . '/../tmp/' . (isset($_SERVER['argv']) ? \md5(\serialize($_SERVER['argv'])) : \getmypid()));
Tester\Helpers::purge(TEMP_DIR);
Tracy\Debugger::$logDirectory = TEMP_DIR;


$_SERVER = \array_intersect_key($_SERVER, \array_flip([
	'PHP_SELF',
	'SCRIPT_NAME',
	'SERVER_ADDR',
	'SERVER_SOFTWARE',
	'HTTP_HOST',
	'DOCUMENT_ROOT',
	'OS',
	'argc', 'argv']));
$_SERVER['REQUEST_TIME'] = 1234567890;
// phpcs:disable SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
$_ENV = $_GET = $_POST = [];

return $loader;
