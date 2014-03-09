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
use Nette\DI\Config;



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
	const TAG_SHARD = 'kdyby.redis.shard';

	/**
	 * @var array
	 */
	public $defaults = array(
		'shards' => array(),
		'remoteShards' => array(),
		'journal' => FALSE,
		'storage' => FALSE,
		'session' => FALSE,
		'debugger' => '%debugMode%',
		'versionCheck' => TRUE,
	);

	/**
	 * @var array
	 */
	public $connectionDefaults = array(
		'host' => '127.0.0.1',
		'port' => NULL,
		'timeout' => 10,
		'database' => 0,
		'lockDuration' => 15,
		'auth' => NULL,
	);



	public function loadConfiguration()
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig($this->defaults + $this->connectionDefaults);

		$builder->addDefinition($this->prefix('driver'))
			->setClass(class_exists('Redis') ? 'Kdyby\Redis\Driver\PhpRedisDriver' : 'Kdyby\Redis\IRedisDriver')
			->setFactory($this->prefix('@client') . '::getDriver');

		$builder->addDefinition($this->prefix('panel'))
			->setClass('Kdyby\Redis\Diagnostics\Panel')
			->setFactory('Kdyby\Redis\Diagnostics\Panel::register')
			->addSetup('$renderPanel', array($config['debugger']));

		$this->loadClient($config);

		if ($config['journal']) {
			$this->loadJournal($config);
		}

		if ($config['storage']) {
			$this->loadStorage($config);
		}

		if ($config['session']) {
			$this->loadSession($config);
		}
	}



	private static function isSharded(array $config)
	{
		return !empty($config['shards']) || !empty($config['remoteShards']);
	}



	protected function loadClient(array $config)
	{
		$builder = $this->getContainerBuilder();

		if (!self::isSharded($config)) {
			$name = $this->registerClient($config, 'client');

			$builder->addDefinition($this->prefix('clientPool'))
				->setClass('Kdyby\Redis\ClientsPool', array(array($this->prefix('@' . $name)), array($this->prefix('@' . $name))));

			return;
		}

		$defaults = array_intersect_key(Config\Helpers::merge($config, $this->connectionDefaults), $this->connectionDefaults);

		$localeShards = array();
		foreach ($config['shards'] as $clientConfig) {
			$localeShards[] = $this->registerShard($clientConfig, $defaults);
		}

		$remoteShards = array();
		foreach ($config['remoteShards'] as $clientConfig) {
			$remoteShards[] = $this->registerShard($clientConfig, $defaults);
		}

		$builder->addDefinition($this->prefix('clientPool'))
			->setClass('Kdyby\Redis\ClientsPool', array($localeShards, $remoteShards));

		$builder->addDefinition($this->prefix('client'))
			->setClass('Kdyby\Redis\RedisClient')
			->setFactory(reset($localeShards));
	}



	/**
	 * @param int|array $clientConfig
	 * @param array $defaults
	 * @return string
	 */
	protected function registerShard($clientConfig, array $defaults)
	{
		$builder = $this->getContainerBuilder();

		$clientConfig = is_numeric($clientConfig) ? array('port' => $clientConfig) : $clientConfig;
		$clientConfig = Config\Helpers::merge($clientConfig, $defaults);

		$clientName = $this->registerClient($clientConfig, 'client_' . substr(md5(serialize($clientConfig)), 0, 6));
		$builder->getDefinition($this->prefix($clientName))
			->setAutowired(FALSE)
			->addTag(self::TAG_SHARD);

		return $this->prefix('@' . $clientName);
	}



	/**
	 * @param array $config
	 * @param string $name
	 * @return string
	 */
	protected function registerClient(array $config, $name)
	{
		$builder = $this->getContainerBuilder();
		$builder->addDefinition($this->prefix($name))
			->setClass('Kdyby\Redis\RedisClient', array(
				'host' => $config['host'],
				'port' => $config['port'],
				'database' => $config['database'],
				'timeout' => $config['timeout'],
				'auth' => $config['auth']
			))
			->addSetup('setupLockDuration', array($config['lockDuration']))
			->addSetup('setPanel', array($this->prefix('@panel')));

		return $name;
	}



	protected function loadJournal(array $config)
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('cacheJournal'))
			->setClass(self::isSharded($config) ? 'Kdyby\Redis\JournalRouter' : 'Kdyby\Redis\RedisLuaJournal');

		// overwrite
		$builder->removeDefinition('nette.cacheJournal');
		$builder->addDefinition('nette.cacheJournal')->setFactory($this->prefix('@cacheJournal'));
	}



	protected function loadStorage(array $config)
	{
		$builder = $this->getContainerBuilder();

		$storageConfig = Config\Helpers::merge(is_array($config['storage']) ? $config['storage'] : array(), array(
			'locks' => TRUE,
		));

		$cacheStorage = $builder->addDefinition($this->prefix('cacheStorage'))
			->setClass(self::isSharded($config) ? 'Kdyby\Redis\StorageRouter' : 'Kdyby\Redis\RedisStorage');

		if (!$storageConfig['locks']) {
			$cacheStorage->addSetup('disableLocking');
		}

		$builder->removeDefinition('cacheStorage');
		$builder->addDefinition('cacheStorage')->setFactory($this->prefix('@cacheStorage'));
	}



	protected function loadSession(array $config)
	{
		$builder = $this->getContainerBuilder();

		$sessionConfig = Config\Helpers::merge(is_array($config['session']) ? $config['session'] : array(), array(
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
			return;
		}

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
				$statement->arguments[0] = Config\Helpers::merge($options, $statement->arguments[0]);
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
		$config = $this->getConfig($this->defaults + $this->connectionDefaults);
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
