<?php

/**
 * Test: Kdyby\Redis\StorageRouter.
 *
 * @testCase Kdyby\Redis\StorageRouterTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Redis
 */

namespace KdybyTests\Redis;

use Kdyby\Redis\StorageRouter;
use Nette;
use Nette\Caching\Cache;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class StorageRouterTest extends AbstractRedisTestCase
{

	/**
	 * @var \Kdyby\Redis\StorageRouter
	 */
	private $storage;

	/**
	 * @var \Kdyby\Redis\ClientsPool
	 */
	private $pool;



	public function setUp()
	{
		parent::setUp();

		$container = $this->createContainer('sharding');
		$this->pool = $container->getByType('Kdyby\Redis\ClientsPool');

		foreach ($this->pool as $client) {
			$this->getClient($client); // flushdb
		}

		$this->storage = new StorageRouter($this->pool);
	}



	/**
	 * key and data with special chars
	 *
	 * @return array
	 */
	public function basicData()
	{
		return array(
			$key = array(1, TRUE),
			$value = range("\x00", "\xFF"),
		);
	}



	public function testBasicData()
	{
		list($key, $value) = $this->basicData();

		$cache = new Cache($this->storage);
		Assert::false(isset($cache[$key]), "Is cached?");
		Assert::null($cache[$key], "Cache content");

		// Writing cache...
		$cache[$key] = $value;
		Assert::true(isset($cache[$key]), "Is cached?");
		Assert::same($value, $cache[$key], "Is cache ok?");

		// Removing from cache using unset()...
		unset($cache[$key]);
		Assert::false(isset($cache[$key]), "Is cached?");

		// Removing from cache using set NULL...
		$cache[$key] = $value;
		$cache[$key] = NULL;
		Assert::false(isset($cache[$key]), "Is cached?");

		// Writing cache...
		$cache->save($key, $value);
		Assert::same($value, $cache->load($key), "Is cache ok?");
	}



	public function testRoundRobin()
	{
		$cache = new Cache($this->storage);

		$cache['a'] = 'a';
		$cache['b'] = 'b';
		$cache['c'] = 'c';
		$cache['d'] = 'd';
		$cache['e'] = 'e';
		$cache['f'] = 'f';
		$cache['g'] = 'g';
		$cache['h'] = 'h';
		$cache['i'] = 'i';
		$cache['j'] = 'j';
		$cache['k'] = 'k';
		$cache['l'] = 'l';

		Assert::same(2, count($this->pool->get(0)->keys('*')));
		Assert::same(2, count($this->pool->get(1)->keys('*')));
		Assert::same(1, count($this->pool->get(2)->keys('*')));
		Assert::same(2, count($this->pool->get(3)->keys('*')));
		Assert::same(1, count($this->pool->get(4)->keys('*')));
		Assert::same(1, count($this->pool->get(5)->keys('*')));
		Assert::same(1, count($this->pool->get(6)->keys('*')));
		Assert::same(0, count($this->pool->get(7)->keys('*')));
		Assert::same(0, count($this->pool->get(8)->keys('*')));
		Assert::same(2, count($this->pool->get(9)->keys('*')));
	}

}

\run(new StorageRouterTest());
