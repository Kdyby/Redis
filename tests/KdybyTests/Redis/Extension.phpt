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
	 * @param string $path
	 * @return Nette\DI\Container|\SystemContainer
	 */
	protected function createContainer($path)
	{
		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);
		Kdyby\Redis\DI\RedisExtension::register($config);
		$config->addConfig($path, $config::NONE);

		return $config->createContainer();
	}

	/**
	 * @return array
	 */
	public function getConfigs()
	{
		$directory = __DIR__ . '/files';
		$files = array_diff(scandir($directory), array('..', '.'));
		$configs = array();
		foreach ($files as $file) {
			if (!preg_match('/config/', $file)) {
				continue;
			}
			$path = $directory . '/' . $file;
			$configs[] = array($path);
		}
		return $configs;
	}


	/**
	 * @dataProvider getConfigs
	 * @param string $path Path to neon config
	 */
	public function testFunctional($path)
	{
		$dic = $this->createContainer($path);
		Assert::true($dic->getService('redis.client') instanceof Kdyby\Redis\RedisClient);
		Assert::true($dic->getService('redis.cacheJournal') instanceof Kdyby\Redis\RedisLuaJournal);
		Assert::true($dic->getService('nette.cacheJournal') instanceof Kdyby\Redis\RedisLuaJournal);
		Assert::true($dic->getService('redis.cacheStorage') instanceof Kdyby\Redis\RedisStorage);
		Assert::true($dic->getService('cacheStorage') instanceof Kdyby\Redis\RedisStorage);
		Assert::same(array(
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
			'cache_limiter' => NULL,
			'cache_expire' => NULL,
			'hash_function' => NULL,
			'hash_bits_per_character' => NULL,
		), $dic->getService('session')->getOptions());
	}

}

\run(new ExtensionTest());
