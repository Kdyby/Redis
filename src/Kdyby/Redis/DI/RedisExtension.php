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
use Nette\Config;
use Nette\Config\Compiler;
use Nette\Config\Configurator;
use Nette\DI\ContainerBuilder;
use Nette\DI\Statement;
use Nette\Utils\Validators;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class RedisExtension extends Nette\Config\CompilerExtension
{

	const DEFAULT_SESSION_PREFIX = 'Nette.Session:';

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
		'debugger' => '%debugMode%'
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
				'timeout' => $config['timeout']
			))
			->addSetup('setupLockDuration', array($config['lockDuration']));

		if ($config['debugger']) {
			$client->addSetup('setPanel', array(
				new Statement('Kdyby\Redis\Diagnostics\Panel::register')
			));
		}

		if ($config['journal']) {
			$builder->addDefinition($this->prefix('cacheJournal'))
				->setClass('Kdyby\Redis\RedisJournal');

			// overwrite
			$builder->removeDefinition('nette.cacheJournal');
			$builder->addDefinition('nette.cacheJournal')->setFactory($this->prefix('@cacheJournal'));
		}

		if ($config['storage']) {
			$builder->addDefinition($this->prefix('cacheStorage'))
				->setClass('Kdyby\Redis\RedisStorage');

			$builder->removeDefinition('cacheStorage');
			$builder->addDefinition('cacheStorage')->setFactory($this->prefix('@cacheStorage'));
		}

		if ($config['session']) {
			$session = Config\Helpers::merge(is_array($config['session']) ? $config['session'] : array(), array(
				'host' => $config['host'],
				'port' => $config['port'],
				'weight' => 1,
				'timeout' => $config['timeout'],
				'database' => $config['database'],
				'prefix' => self::DEFAULT_SESSION_PREFIX,
			));

			$params = array_diff_key($session, array_flip(array('host', 'port')));
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
	}



	/**
	 * Verify, that redis is installed, working and has the right version.
	 */
	public function beforeCompile()
	{
		$config = $this->getConfig($this->defaults);
		if ($config['journal'] || $config['storage'] || $config['session']) {
			$client = new RedisClient($config['host'], $config['port'], $config['database'], $config['timeout']);
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
	 * @param \Nette\Config\Configurator $config
	 */
	public static function register(Configurator $config)
	{
		$config->onCompile[] = function (Configurator $config, Compiler $compiler) {
			$compiler->addExtension('redis', new RedisExtension());
		};
	}

}
