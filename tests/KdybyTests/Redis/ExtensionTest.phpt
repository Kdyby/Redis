<?php

declare(strict_types = 1);

/**
 * Test: Kdyby\Redis\Extension.
 *
 * @testCase KdybyTests\Redis\ExtensionTest
 */

namespace KdybyTests\Redis;

use Kdyby;
use Nette;
use Nette\DI\Container;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



class ExtensionTest extends \Tester\TestCase
{

	protected function createContainer(): Container
	{
		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);
		$config->onCompile[] = static function ($config, Nette\DI\Compiler $compiler): void {
			$compiler->addExtension('redis', new Kdyby\Redis\DI\RedisExtension());
		};

		$config->addConfig(__DIR__ . '/files/config.neon');

		return $config->createContainer();
	}

	public function testFunctional(): void
	{
		$dic = $this->createContainer();
		Assert::true($dic->getService('redis.client') instanceof Kdyby\Redis\RedisClient);
		Assert::true($dic->getService('redis.cacheJournal') instanceof Kdyby\Redis\RedisLuaJournal);
		Assert::true($dic->getService('nette.cacheJournal') instanceof Kdyby\Redis\RedisLuaJournal);
		Assert::true($dic->getService('redis.cacheStorage') instanceof Kdyby\Redis\RedisStorage);
		Assert::true($dic->getService('cacheStorage') instanceof Kdyby\Redis\RedisStorage);

		$sessionOptions = $dic->getService('session')->getOptions();

		if (isset($sessionOptions['save_handler'])) {
			Assert::true(isset($sessionOptions['save_handler']));
			Assert::same('redis', $sessionOptions['save_handler']);

		} else {
			Assert::true(isset($sessionOptions['saveHandler']));
			Assert::same('redis', $sessionOptions['saveHandler']);
		}

		if (isset($sessionOptions['savePath'])) {
			Assert::true(isset($sessionOptions['savePath']));
			Assert::same('tcp://127.0.0.1:6379?weight=1&timeout=10&database=0&prefix=Nette.Session%3A', $sessionOptions['savePath']);

		} else {
			Assert::true(isset($sessionOptions['save_path']));
			Assert::same('tcp://127.0.0.1:6379?weight=1&timeout=10&database=0&prefix=Nette.Session%3A', $sessionOptions['save_path']);
		}

		if (isset($sessionOptions['referer_check'])) {
			Assert::same('', $sessionOptions['referer_check']);
		}

		if (isset($sessionOptions['use_cookies'])) {
			Assert::same(1, $sessionOptions['use_cookies']);
		}

		if (isset($sessionOptions['use_only_cookies'])) {
			Assert::same(1, $sessionOptions['use_only_cookies']);
		}

		if (isset($sessionOptions['use_trans_sid'])) {
			Assert::same(0, $sessionOptions['use_trans_sid']);
		}

		if (isset($sessionOptions['cookie_httponly'])) {
			Assert::same(TRUE, $sessionOptions['cookie_httponly']);
		}

		Assert::true(isset($sessionOptions['gc_maxlifetime']));
		Assert::same(10800, $sessionOptions['gc_maxlifetime']);

		Assert::true(isset($sessionOptions['cookie_path']));
		Assert::same('/', $sessionOptions['cookie_path']);

		Assert::true(isset($sessionOptions['cookie_domain']));
		Assert::same('', $sessionOptions['cookie_domain']);

		Assert::true(isset($sessionOptions['cookie_secure']));
		Assert::same(FALSE, $sessionOptions['cookie_secure']);
	}

}

(new ExtensionTest())->run();
