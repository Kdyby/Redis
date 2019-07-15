<?php

declare(strict_types = 1);

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
use Nette\DI\Config;

class RedisExtension extends \Nette\DI\CompilerExtension
{

	public const DEFAULT_SESSION_PREFIX = Kdyby\Redis\RedisSessionHandler::NS_NETTE;
	private const PANEL_COUNT_MODE = 'count';

	/**
	 * @var array
	 */
	public $defaults = [
		'journal' => FALSE,
		'storage' => FALSE,
		'session' => FALSE,
		'clients' => [],
	];

	/**
	 * @var array
	 */
	public $clientDefaults = [
		'host' => '127.0.0.1',
		'port' => NULL,
		'timeout' => 10,
		'database' => 0,
		'auth' => NULL,
		'persistent' => FALSE,
		'connectionAttempts' => 1,
		'lockDuration' => 15,
		'lockAcquireTimeout' => FALSE,
		'debugger' => '%debugMode%',
		'versionCheck' => TRUE,
	];

	/**
	 * @var array
	 */
	private $configuredClients = [];

	public function loadConfiguration(): void
	{
		$this->configuredClients = [];

		$builder = $this->getContainerBuilder();
		$config = self::fixClientConfig(
			Config\Helpers::merge($this->getConfig(), $this->defaults + $this->clientDefaults)
		);

		$this->buildClient(NULL, $config);

		$phpRedisDriverClass = \phpversion('redis') >= '4.0.0' ? Kdyby\Redis\Driver\PhpRedisDriver::class : Kdyby\Redis\Driver\PhpRedisDriverOld::class;

		$builder->addDefinition($this->prefix('driver'))
			->setType($phpRedisDriverClass)
			->setFactory($this->prefix('@client') . '::getDriver');

		$this->loadJournal($config);
		$this->loadStorage($config);
		$this->loadSession($config);

		foreach ($config['clients'] as $name => $clientConfig) {
			$this->buildClient($name, $clientConfig);
		}
	}

	/**
	 * @param string|NULL $name
	 * @param array<mixed> $config
	 * @return \Nette\DI\ServiceDefinition
	 */
	protected function buildClient(?string $name, array $config): Nette\DI\ServiceDefinition
	{
		$builder = $this->getContainerBuilder();

		$defaultConfig = Config\Helpers::merge($this->getConfig(), $this->clientDefaults);
		$parentName = Config\Helpers::takeParent($config);
		if ($parentName) {
			Nette\Utils\Validators::assertField($this->configuredClients, $parentName, 'array', "parent configuration '%', are you sure it's defined?");
			$defaultConfig = Config\Helpers::merge($this->configuredClients[$parentName], $defaultConfig);
		}

		$config = Config\Helpers::merge($config, $defaultConfig);
		$config = \array_intersect_key(self::fixClientConfig($config), $this->clientDefaults);

		$client = $builder->addDefinition($clientName = $this->prefix(($name ? $name . '_' : '') . 'client'))
			->setType(Kdyby\Redis\RedisClient::class)
			->setArguments([
				'host' => $config['host'],
				'port' => $config['port'],
				'database' => $config['database'],
				'timeout' => $config['timeout'],
				'auth' => $config['auth'],
				'persistent' => $config['persistent'],
			]);

		if (empty($builder->parameters[$this->name]['defaultClient'])) {
			$builder->parameters[$this->name]['defaultClient'] = $clientName;

			$this->configuredClients['default'] = $config;
			$builder->addDefinition($this->prefix('default_client'))
				->setType(Kdyby\Redis\RedisClient::class)
				->setFactory('@' . $clientName)
				->setAutowired(FALSE);

		} else {
			$client->setAutowired(FALSE);
		}

		$this->configuredClients[$name] = $config;

		$client->addSetup('setupLockDuration', [$config['lockDuration'], $config['lockAcquireTimeout']]);
		$client->addSetup('setConnectionAttempts', [$config['connectionAttempts']]);
		$client->addTag('redis.client');

		if (\array_key_exists('debugger', $config) && $config['debugger']) {
			$builder->addDefinition($panelName = $clientName . '.panel')
				->setType(Kdyby\Redis\Diagnostics\Panel::class)
				->setFactory(Kdyby\Redis\Diagnostics\Panel::class . '::register')
				->addSetup('$renderPanel', [$config['debugger'] !== self::PANEL_COUNT_MODE])
				->addSetup('$name', [$name ?: 'default']);

			$client->addSetup('setPanel', ['@' . $panelName]);
		}

		return $client;
	}

	/**
	 * @param array<mixed> $config
	 */
	protected function loadJournal(array $config): void
	{
		if (!$config['journal']) {
			return;
		}

		$builder = $this->getContainerBuilder();

		// overwrite
		$journalService = $builder->getByType(Nette\Caching\Storages\IJournal::class) ?: 'nette.cacheJournal';
		$builder->removeDefinition($journalService);
		$builder->addDefinition($journalService)->setFactory($this->prefix('@cacheJournal'));

		$builder->addDefinition($this->prefix('cacheJournal'))
			->setType(Kdyby\Redis\RedisLuaJournal::class);
	}

	/**
	 * @param array<mixed> $config
	 */
	protected function loadStorage(array $config): void
	{
		if (!$config['storage']) {
			return;
		}

		$builder = $this->getContainerBuilder();

		$storageConfig = Nette\DI\Config\Helpers::merge(\is_array($config['storage']) ? $config['storage'] : [], [
			'locks' => TRUE,
		]);

		$storageService = $builder->getByType(Nette\Caching\IStorage::class) ?: 'cacheStorage';
		$builder->removeDefinition($storageService);
		$builder->addDefinition($storageService)->setFactory($this->prefix('@cacheStorage'));

		$cacheStorage = $builder->addDefinition($this->prefix('cacheStorage'))
			->setType(Kdyby\Redis\RedisStorage::class);

		if (!$storageConfig['locks']) {
			$cacheStorage->addSetup('disableLocking');
		}
	}

	/**
	 * @param array<mixed> $config
	 */
	protected function loadSession(array $config): void
	{
		if (!$config['session']) {
			return;
		}

		$builder = $this->getContainerBuilder();

		$sessionConfig = Nette\DI\Config\Helpers::merge(\is_array($config['session']) ? $config['session'] : [], [
			'host' => $config['host'],
			'port' => $config['port'],
			'weight' => 1,
			'timeout' => $config['timeout'],
			'database' => $config['database'],
			'prefix' => self::DEFAULT_SESSION_PREFIX,
			'auth' => $config['auth'],
			'native' => TRUE,
			'lockDuration' => $config['lockDuration'],
			'lockAcquireTimeout' => $config['lockAcquireTimeout'],
			'connectionAttempts' => $config['connectionAttempts'],
			'persistent' => $config['persistent'],
		]);
		$sessionConfig = self::fixClientConfig($sessionConfig);

		$this->buildClient('sessionHandler', ['debugger' => FALSE] + $sessionConfig);

		if ($sessionConfig['native']) {
			$this->loadNativeSessionHandler($sessionConfig);

		} else {
			$builder->addDefinition($this->prefix('sessionHandler'))
				->setType(Kdyby\Redis\RedisSessionHandler::class)
				->setArguments([$this->prefix('@sessionHandler_client')]);

			try {
				/** @var \Nette\DI\Definitions\ServiceDefinition $sessionService */
				$sessionService = $builder->getDefinitionByType(Nette\Http\Session::class);

			} catch (\Nette\DI\MissingServiceException $exception) {
				/** @var \Nette\DI\Definitions\ServiceDefinition $sessionService */
				$sessionService = $builder->getDefinitionByType('session');
			}

			$sessionService
				->addSetup('?->bind(?)', [$this->prefix('@sessionHandler'), '@self']);
		}
	}

	/**
	 * @param array<mixed> $session
	 */
	protected function loadNativeSessionHandler(array $session): void
	{
		$builder = $this->getContainerBuilder();

		$params = \array_intersect_key($session, \array_flip(['weight', 'timeout', 'database', 'prefix', 'auth', 'persistent']));
		if (\substr($session['host'], 0, 1) === '/') {
			$savePath = $session['host'];

		} else {
			$savePath = \sprintf('tcp://%s:%d', $session['host'], $session['port']);
		}

		if (!$params['persistent']) {
			unset($params['persistent']);
		}

		if (!$params['auth']) {
			unset($params['auth']);
		}

		$options = [
			'saveHandler' => 'redis',
			'savePath' => $savePath . ($params ? '?' . \http_build_query($params, '', '&') : ''),
		];

		/** @var \Nette\DI\Definitions\ServiceDefinition $serviceDefinition */
		$serviceDefinition = $builder->getDefinition('session.session');
		foreach ($serviceDefinition->getSetup() as $statement) {
			if ($statement->getEntity() === 'setOptions') {
				$statement->arguments[0] = Nette\DI\Config\Helpers::merge($options, $statement->arguments[0]);
				unset($options);
				break;
			}
		}

		if (isset($options)) {
			/** @var \Nette\DI\Definitions\ServiceDefinition $serviceDefinition */
			$serviceDefinition = $builder->getDefinition('session.session');
			$serviceDefinition
				->addSetup('setOptions', [$options]);
		}
	}

	/**
	 * Verify, that redis is installed, working and has the right version.
	 */
	public function beforeCompile(): void
	{
		foreach ($this->configuredClients as $config) {
			if (!$config['versionCheck']) {
				continue;
			}

			$client = new RedisClient($config['host'], $config['port'], $config['database'], $config['timeout'], $config['auth']);
			$client->assertVersion();
			$client->close();
		}
	}

	/**
	 * @param array<mixed> $config
	 * @return array<mixed>
	 */
	protected static function fixClientConfig(array $config): array
	{
		if ($config['host'][0] === '/') {
			$config['port'] = NULL; // sockets have no ports

		} elseif (!$config['port']) {
			$config['port'] = RedisClient::DEFAULT_PORT;
		}

		return $config;
	}

}
