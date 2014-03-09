<?php

/**
 * Test: Kdyby\Redis\ClientsPool.
 *
 * @testCase Kdyby\Redis\ClientsPoolTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Redis
 */

namespace KdybyTests\Redis;

use Kdyby\Redis\DI\RedisExtension;
use Kdyby\Redis\RedisClient;
use Nette;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ClientsPoolTest extends AbstractRedisTestCase
{

	/**
	 * @var string
	 */
	private $ns;



	public function setUp()
	{
		parent::setUp();
		$this->ns = Nette\Utils\Strings::random();
	}



	public function testIterableShards()
	{
		$container = $this->createContainer('sharding');

		/** @var \Kdyby\Redis\ClientsPool $pool */
		$pool = $container->getByType('Kdyby\Redis\ClientsPool');

		$actualList = iterator_to_array($pool);
		sort($actualList);
		Assert::same(self::shardsList($container), $actualList);
	}



	public function testChooseClient()
	{
		$container = $this->createContainer('sharding');

		/** @var \Kdyby\Redis\ClientsPool $pool */
		$pool = $container->getByType('Kdyby\Redis\ClientsPool');

		Assert::same($container->getService('redis.client_c22699'), $pool->choose('lister')); // 3592764338 % 10 ... 8
		Assert::same($container->getService('redis.client_29d2f8'), $pool->choose('rimmer')); // 1288232970 % 10 ... 0
		Assert::same($container->getService('redis.client_b6aef3'), $pool->choose('kryten')); // 2813071794 % 10 ... 4
	}



	/**
	 * @param Nette\DI\Container $container
	 * @return RedisClient[]
	 */
	protected static function shardsList(Nette\DI\Container $container)
	{
		$shardsList = array_map(function ($name) use ($container) {
			return $container->getService($name);
		}, array_keys($container->findByTag(RedisExtension::TAG_SHARD)));

		sort($shardsList);
		return array_values($shardsList);
	}

}

\run(new ClientsPoolTest());
