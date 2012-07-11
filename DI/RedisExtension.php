<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008, 2012 Filip Procházka (filip.prochazka@kdyby.org)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Extension\Redis\DI;

use Kdyby;
use Nette;
use Nette\Config\Configurator;
use Nette\Config\Compiler;
use Nette\DI\ContainerBuilder;
use Nette\Utils\Validators;



/**
 * @author Filip Procházka <filip.prochazka@kdyby.org>
 */
class RedisExtension extends Nette\Config\CompilerExtension
{

	/**
	 * @var array
	 */
	public $defaults = array(
		'journal' => FALSE,
		'storage' => FALSE,
		'host' => 'localhost',
		'port' => 6379,
		'timeout' => 10,
		'database' => 0
	);



	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		if ($config !== $this->defaults) {
			$builder->addDefinition($this->prefix('client'))
				->setClass('Kdyby\Extension\Redis\RedisClient', array(array(
					'host' => $config['host'],
					'port' => $config['port'],
					'timeout' => $config['timeout'],
					'database' => $config['database']
				)));

			if ($config['journal']) {
				$builder->removeDefinition('nette.cacheJournal');
				$builder->addDefinition('nette.cacheJournal')
					->setClass('Kdyby\Extension\Redis\RedisJournal');
			}

			if ($config['storage']) {
				$builder->removeDefinition('cacheStorage');
				$builder->addDefinition('cacheStorage')
					->setClass('Kdyby\Extension\Redis\RedisStorage');
			}
		}

		$builder->addDefinition($this->prefix('panel'))
			->setFactory('Kdyby\Extension\Redis\Diagnostics\Panel::register');
	}



	/**
	 * @param \Nette\Config\Configurator $config
	 */
	public static function register(Configurator $config)
	{
		$config->onCompile[] = function (Configurator $config, Compiler $compiler) {
			$compiler->addExtension('redis', new RedisExtension());
		};
	}

}
