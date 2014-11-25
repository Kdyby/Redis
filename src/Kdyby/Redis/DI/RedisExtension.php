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
use Nette\DI\ContainerBuilder;
use Nette\Utils\Validators;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class RedisExtension extends Nette\DI\CompilerExtension
{

	const DEFAULT_SESSION_PREFIX = Kdyby\Redis\RedisSessionHandler::NS_NETTE;
	const PANEL_COUNT_MODE = 'count';

	/**
	 * @var array
	 */
	public $defaults = array(
		'journal' => FALSE,
		'storage' => FALSE,
		'session' => FALSE,
	);

	/**
	 * @var array
	 */
	public $clientDefaults = array(
		'host' => '127.0.0.1',
		'port' => NULL,
		'timeout' => 10,
		'database' => 0,
		'auth' => NULL,
		'persistent' => FALSE,
		'connectionAttempts' => 1,
		'lockDuration' => 15,
		'debugger' => '%debugMode%',
		'versionCheck' => TRUE,
	);

	/**
	 * @var array
	 */
	private $checkClients = array();



	public function loadConfiguration()
	{
		$this->checkClients = array();

		$builder = $this->getContainerBuilder();
		$config = self::fixClientConfig($this->getConfig($this->defaults + $this->clientDefaults));

		$this->buildClient('client', $config);

		$builder->addDefinition($this->prefix('driver'))
			->setClass(class_exists('Redis') ? 'Kdyby\Redis\Driver\PhpRedisDriver' : 'Kdyby\Redis\IRedisDriver')
			->setFactory($this->prefix('@client') . '::getDriver');

		$this->loadJournal($config);
		$this->loadStorage($config);
		$this->loadSession($config, $builder);
	}



	/**
	 * @param string $name
	 * @param array $config
	 * @return Nette\DI\ServiceDefinition
	 */
	protected function buildClient($name, $config)
	{
		$builder = $this->getContainerBuilder();

		$config = Config\Helpers::merge($config, $builder->expand($this->clientDefaults));
		$config = self::fixClientConfig($config);

		$client = $builder->addDefinition($clientName = $this->prefix($name))
			->setClass('Kdyby\Redis\RedisClient', array(
				'host' => $config['host'],
				'port' => $config['port'],
				'database' => $config['database'],
				'timeout' => $config['timeout'],
				'auth' => $config['auth'],
				'persistent' => $config['persistent'],
			));

		if (empty($builder->parameters[$this->name]['defaultClient'])) {
			$builder->parameters[$this->name]['defaultClient'] = $clientName;

		} else {
			$client->setAutowired(FALSE);
		}

		if ($config['versionCheck']) {
			$this->checkClients[] = $config;
		}

		$client->addSetup('setupLockDuration', array($config['lockDuration']));
		$client->addSetup('setConnectionAttempts', array($config['connectionAttempts']));

		if (array_key_exists('debugger', $config) && $config['debugger']) {
			$builder->addDefinition($this->prefix($panelName = $name . '.panel'))
				->setClass('Kdyby\Redis\Diagnostics\Panel')
				->setFactory('Kdyby\Redis\Diagnostics\Panel::register')
				->addSetup('$renderPanel', array($config['debugger'] !== self::PANEL_COUNT_MODE));

			$client->addSetup('setPanel', array($this->prefix('@' . $panelName)));
		}

		return $client;
	}



	protected function loadJournal(array $config)
	{
		if (!$config['journal']) {
			return;
		}

		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('cacheJournal'))
			->setClass('Kdyby\Redis\RedisLuaJournal');

		// overwrite
		$builder->removeDefinition('nette.cacheJournal');
		$builder->addDefinition('nette.cacheJournal')->setFactory($this->prefix('@cacheJournal'));
	}



	protected function loadStorage(array $config)
	{
		if (!$config['storage']) {
			return;
		}

		$builder = $this->getContainerBuilder();

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



	protected function loadSession(array $config)
	{
		if (!$config['session']) {
			return;
		}

		$builder = $this->getContainerBuilder();

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
			'connectionAttempts' => $config['connectionAttempts'],
			'persistent' => $config['persistent'],
		));
		$sessionConfig = self::fixClientConfig($sessionConfig);

		if ($sessionConfig['native']) {
			$this->loadNativeSessionHandler($sessionConfig);

		} else {
			$this->buildClient('sessionHandler_client', array('debugger' => FALSE) + $sessionConfig);

			$builder->addDefinition($this->prefix('sessionHandler'))
				->setClass('Kdyby\Redis\RedisSessionHandler', array($this->prefix('@sessionHandler_client')));

			$builder->getDefinition('session')
				->addSetup('setStorage', array($this->prefix('@sessionHandler')));
		}
	}



	protected function loadNativeSessionHandler(array $session)
	{
		$builder = $this->getContainerBuilder();

		$params = array_intersect_key($session, array_flip(array('weight', 'timeout', 'database', 'prefix', 'auth', 'persistent')));
		if (substr($session['host'], 0, 1) === '/') {
			$savePath = $session['host'];

		} else {
			$savePath = sprintf('tcp://%s:%d', $session['host'], $session['port']);
		}

		if (!$params['persistent']) {
			unset($params['persistent']);
		}

		if (!$params['auth']) {
			unset($params['auth']);
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
		foreach ($this->checkClients as $config) {
			$client = new RedisClient($config['host'], $config['port'], $config['database'], $config['timeout'], $config['auth']);
			$client->assertVersion();
			$client->close();
		}
	}



	protected static function fixClientConfig(array $config)
	{
		if ($config['host'][0] === '/') {
			$config['port'] = NULL; // sockets have no ports

		} elseif (!$config['port']) {
			$config['port'] = 6379;
		}

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
