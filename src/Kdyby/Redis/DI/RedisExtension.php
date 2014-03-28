<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Redis\DI;

use Kdyby;
use Kdyby\Redis\RedisClient;
use Nette;
use Nette\DI\Compiler;
use Nette\DI\ContainerBuilder;
use Nette\Utils\Validators;



if (!class_exists('Nette\DI\CompilerExtension')) {
	class_alias('Nette\Config\CompilerExtension', 'Nette\DI\CompilerExtension');
	class_alias('Nette\Config\Compiler', 'Nette\DI\Compiler');
	class_alias('Nette\Config\Helpers', 'Nette\DI\Config\Helpers');
}

if (isset(Nette\Loaders\NetteLoader::getInstance()->renamed['Nette\Configurator']) || !class_exists('Nette\Configurator')) {
	unset(Nette\Loaders\NetteLoader::getInstance()->renamed['Nette\Configurator']); // fuck you
	class_alias('Nette\Config\Configurator', 'Nette\Configurator');
}

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class RedisExtension extends Nette\DI\CompilerExtension
{

	const DEFAULT_SESSION_PREFIX = Kdyby\Redis\RedisSessionHandler::NS_NETTE;

	/**
	 * @var array
	 */
	public $defaults = array(
		'journal' => FALSE,
		'storage' => FALSE,
		'session' => FALSE,
		'lockDuration' => 15,
		'host' => '127.0.0.1',
		'port' => NULL,
		'timeout' => 10,
		'database' => 0,
		'debugger' => '%debugMode%',
		'versionCheck' => TRUE,
		'auth' => NULL,
	);



	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults);

		$client = $builder->addDefinition($this->prefix('client'))
			->setClass('Kdyby\Redis\RedisClient', array(
				'host' => $config['host'],
				'port' => $config['port'],
				'database' => $config['database'],
				'timeout' => $config['timeout'],
				'auth' => $config['auth']
			))
			->addSetup('setupLockDuration', array($config['lockDuration']));

		$builder->addDefinition($this->prefix('driver'))
			->setClass(class_exists('Redis') ? 'Kdyby\Redis\Driver\PhpRedisDriver' : 'Kdyby\Redis\IRedisDriver')
			->setFactory($this->prefix('@client') . '::getDriver');

		if ($builder->parameters['debugMode'] || $config['debugger']) {
			$builder->addDefinition($this->prefix('panel'))
				->setClass('Kdyby\Redis\Diagnostics\Panel')
				->setFactory('Kdyby\Redis\Diagnostics\Panel::register')
				->addSetup('$renderPanel', array($config['debugger']));

			$client->addSetup('setPanel', array($this->prefix('@panel')));
		}

		if ($config['journal']) {
			$builder->addDefinition($this->prefix('cacheJournal'))
				->setClass('Kdyby\Redis\RedisLuaJournal');

			// overwrite
			$builder->removeDefinition('nette.cacheJournal');
			$builder->addDefinition('nette.cacheJournal')->setFactory($this->prefix('@cacheJournal'));
		}

		if ($config['storage']) {
			$storageConfig = Nette\DI\Config\Helpers::merge(is_array($config['storage']) ? $config['storage'] : array(), array(
				'locks' => TRUE,
			));

			$cacheStorage = $builder->addDefinition($this->prefix('cacheStorage'))
				->setClass('Kdyby\Redis\RedisStorage');

			if (!$storageConfig['locks']) {
				$cacheStorage->addSetup('disableLocking');
			}

			$builder->removeDefinition('cacheStorage');
			$builder->addDefinition('cacheStorage')->setFactory($this->prefix('@cacheStorage'));
		}

		if ($config['session']) {
			$sessionConfig = Nette\DI\Config\Helpers::merge(is_array($config['session']) ? $config['session'] : array(), array(
				'host' => $config['host'],
				'port' => $config['port'],
				'weight' => 1,
				'timeout' => $config['timeout'],
				'database' => $config['database'],
				'prefix' => self::DEFAULT_SESSION_PREFIX,
				'auth' => $config['auth'],
				'native' => TRUE,
				'lockDuration' => $config['lockDuration'],
			));

			if ($sessionConfig['native']) {
				$this->loadNativeSessionHandler($sessionConfig);

			} else {
				$builder->addDefinition($this->prefix('sessionHandler_client'))
					->setClass('Kdyby\Redis\RedisClient', array(
						'host' => $sessionConfig['host'],
						'port' => $sessionConfig['port'],
						'database' => $sessionConfig['database'],
						'timeout' => $sessionConfig['timeout'],
						'auth' => $sessionConfig['auth']
					))
					->addSetup('setupLockDuration', array($sessionConfig['lockDuration']))
					->setAutowired(FALSE);

				$builder->addDefinition($this->prefix('sessionHandler'))
					->setClass('Kdyby\Redis\RedisSessionHandler', array($this->prefix('@sessionHandler_client')));

				$builder->getDefinition('session')
					->addSetup('setStorage', array($this->prefix('@sessionHandler')));
			}
		}
	}



	protected function loadNativeSessionHandler(array $session)
	{
		$builder = $this->getContainerBuilder();

		$params = array_intersect_key($session, array_flip(array('weight', 'timeout', 'database', 'prefix', 'auth')));
		if (substr($session['host'], 0, 1) === '/') {
			$savePath = $session['host'];

		} else {
			$savePath = sprintf('tcp://%s:%d', $session['host'], $session['port']);
		}

		$options = array(
			'saveHandler' => 'redis',
			'savePath' => $savePath . ($params ? '?' . http_build_query($params, '', '&') : ''),
		);

		foreach ($builder->getDefinition('session')->setup as $statement) {
			if ($statement->entity === 'setOptions') {
				$statement->arguments[0] = Nette\DI\Config\Helpers::merge($options, $statement->arguments[0]);
				unset($options);
				break;
			}
		}

		if (isset($options)) {
			$builder->getDefinition('session')
				->addSetup('setOptions', array($options));
		}
	}



	/**
	 * Verify, that redis is installed, working and has the right version.
	 */
	public function beforeCompile()
	{
		$config = $this->getConfig($this->defaults);
		if ($config['versionCheck'] && ($config['journal'] || $config['storage'] || $config['session'])) {
			$client = new RedisClient($config['host'], $config['port'], $config['database'], $config['timeout'], $config['auth']);
			$client->assertVersion();
			$client->close();
		}
	}



	/**
	 * @param array $defaults
	 * @param bool $expand
	 * @return array
	 */
	public function getConfig(array $defaults = NULL, $expand = TRUE)
	{
		$config = parent::getConfig($defaults, $expand);
		$config['port'] = ($config['host'][0] !== '/' && !$config['port']) ? 6379 : $config['port'];

		return $config;
	}



	/**
	 * @param \Nette\Configurator $config
	 */
	public static function register(Nette\Configurator $config)
	{
		$config->onCompile[] = function ($config, Compiler $compiler) {
			$compiler->addExtension('redis', new RedisExtension());
		};
	}

}
