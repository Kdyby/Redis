<?php

/**
 * Test: Kdyby\Redis\Extension.
 *
 * @testCase KdybyTests\Redis\ExtensionTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Redis
 */

namespace KdybyTests\Redis;

use Kdyby;
use Nette;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ExtensionTest extends Tester\TestCase
{

	/**
	 * @return \SystemContainer|\Nette\DI\Container
	 */
	protected function createContainer()
	{
		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);
		Kdyby\Redis\DI\RedisExtension::register($config);
		$config->addConfig(__DIR__ . '/files/config.neon');

		return $config->createContainer();
	}



	public function testFunctional()
	{
		$dic = $this->createContainer();
		Assert::true($dic->getService('redis.client') instanceof Kdyby\Redis\RedisClient);
		Assert::true($dic->getService('redis.cacheJournal') instanceof Kdyby\Redis\RedisLuaJournal);
		Assert::true($dic->getService('nette.cacheJournal') instanceof Kdyby\Redis\RedisLuaJournal);
		Assert::true($dic->getService('redis.cacheStorage') instanceof Kdyby\Redis\RedisStorage);
		Assert::true($dic->getService('cacheStorage') instanceof Kdyby\Redis\RedisStorage);
		Assert::same([
			'saveHandler' => 'redis',
			'savePath' => 'tcp://127.0.0.1:6379?weight=1&timeout=10&database=0&prefix=Nette.Session%3A',
			'referer_check' => '',
			'use_cookies' => 1,
			'use_only_cookies' => 1,
			'use_trans_sid' => 0,
			'cookie_lifetime' => 0,
			'cookie_path' => '/',
			'cookie_domain' => '',
			'cookie_secure' => FALSE,
			'cookie_httponly' => TRUE,
			'gc_maxlifetime' => 10800,
		], $dic->getService('session')->getOptions());
	}

}

\run(new ExtensionTest());
