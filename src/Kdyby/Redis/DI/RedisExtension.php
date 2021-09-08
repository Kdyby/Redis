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

class RedisExtension extends \Nette\DI\CompilerExtension
{

	public const DEFAULT_SESSION_PREFIX = \Kdyby\Redis\RedisSessionHandler::NS_NETTE;
	private const PANEL_COUNT_MODE = 'count';

	/**
	 * @var array<string, array<string, mixed>>
	 */
	private array $configuredClients = [];


	public function getConfigSchema(): \Nette\Schema\Schema
	{
		return new \Kdyby\Redis\DI\Config\RedisSchema($this->getContainerBuilder());
	}


	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$config = $this->getConfig();

		// we need to register default client before session is processed
		$this->buildClient(NULL, $config['clients']['']);

		$phpRedisDriverClass = \Kdyby\Redis\Driver\PhpRedisDriver::class;

		$builder->addDefinition($this->prefix('driver'))
			->setType($phpRedisDriverClass)
			->setFactory($this->prefix('@client') . '::getDriver');

		$this->loadJournal($config);
		$this->loadStorage($config);
		$this->loadSession($config);

		unset($config['clients']['']);
		foreach ($config['clients'] as $name => $clientConfig) {
			$this->buildClient($name, $clientConfig);
		}
	}

	/**
	 * @param string|NULL $name
	 * @param array<mixed> $config
	 */
	protected function buildClient(?string $name, array $config): \Nette\DI\Definitions\ServiceDefinition
	{
		$builder = $this->getContainerBuilder();

		$client = $builder->addDefinition($clientName = $this->prefix(($name ? $name . '_' : '') . 'client'))
			->setType(\Kdyby\Redis\RedisClient::class)
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
				->setType(\Kdyby\Redis\RedisClient::class)
				->setFactory('@' . $clientName)
				->setAutowired(FALSE);

		} else {
			$client->setAutowired(FALSE);
		}

		$this->configuredClients[$name] = $config;

		$client->addSetup('setupLockDuration', [$config['lockDuration'], $config['lockAcquireTimeout']]);
		$client->addSetup('setConnectionAttempts', [$config['connectionAttempts']]);
		$client->addTag('redis.client');

		if ($builder->parameters['debugMode'] && $config['debugger']) {
			$builder->addDefinition($panelName = $clientName . '.panel')
				->setType(\Kdyby\Redis\Diagnostics\Panel::class)
				->setFactory(\Kdyby\Redis\Diagnostics\Panel::class . '::register')
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
		$journalService = $builder->getByType(\Nette\Caching\Storages\IJournal::class) ?: 'nette.cacheJournal';
		$builder->removeDefinition($journalService);
		$builder->addDefinition($journalService)->setFactory($this->prefix('@cacheJournal'));

		$builder->addDefinition($this->prefix('cacheJournal'))
			->setType(\Kdyby\Redis\RedisLuaJournal::class);
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

		$storageConfig = \Nette\DI\Config\Helpers::merge(\is_array($config['storage']) ? $config['storage'] : [], [
			'locks' => TRUE,
		]);

		$storageService = $builder->getByType(\Nette\Caching\Storage::class) ?: 'cacheStorage';
		$builder->removeDefinition($storageService);
		$builder->addDefinition($storageService)->setFactory($this->prefix('@cacheStorage'));

		$cacheStorage = $builder->addDefinition($this->prefix('cacheStorage'))
			->setType(\Kdyby\Redis\RedisStorage::class);

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

		$clientConfig = $config['clients'][NULL];

		$sessionConfig = \Nette\DI\Config\Helpers::merge(\is_array($config['session']) ? $config['session'] : [], [
			'host' => $clientConfig['host'],
			'port' => $clientConfig['port'],
			'weight' => 1,
			'timeout' => $clientConfig['timeout'],
			'database' => $clientConfig['database'],
			'prefix' => self::DEFAULT_SESSION_PREFIX,
			'auth' => $clientConfig['auth'],
			'native' => TRUE,
			'lockDuration' => $clientConfig['lockDuration'],
			'lockAcquireTimeout' => $clientConfig['lockAcquireTimeout'],
			'connectionAttempts' => $clientConfig['connectionAttempts'],
			'persistent' => $clientConfig['persistent'],
			'versionCheck' => $clientConfig['versionCheck'],
		]);

		$sessionConfig['debugger'] = FALSE;

		$this->buildClient('sessionHandler', $sessionConfig);

		if ($sessionConfig['native']) {
			$this->loadNativeSessionHandler($sessionConfig);

		} else {
			$builder->addDefinition($this->prefix('sessionHandler'))
				->setType(\Kdyby\Redis\RedisSessionHandler::class)
				->setArguments([$this->prefix('@sessionHandler_client')]);

			try {
				/** @var \Nette\DI\Definitions\ServiceDefinition $sessionService */
				$sessionService = $builder->getDefinitionByType(\Nette\Http\Session::class);

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
				$statement->arguments[0] = \Nette\DI\Config\Helpers::merge($options, $statement->arguments[0]);
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

			$client = new \Kdyby\Redis\RedisClient($config['host'], $config['port'], $config['database'], $config['timeout'], $config['auth']);
			$client->assertVersion();
			$client->close();
		}
	}

}
