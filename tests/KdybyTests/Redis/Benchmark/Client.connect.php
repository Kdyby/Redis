<?php

/**
 * Test: Kdyby\Redis\StorageRouter.
 *
 * @testCase Kdyby\Redis\StorageRouterTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Redis
 */

namespace KdybyTests\Redis\Benchmark;

use Kdyby\Redis\RedisClient;
use KdybyTests\Redis\AbstractRedisTestCase;
use Nette;
use Tester;

require_once __DIR__ . '/../../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ClientTest extends AbstractRedisTestCase
{

	public function testConnect()
	{
		$container = $this->createContainer('sharding');

		/** @var \Kdyby\Redis\ClientsPool|RedisClient[] $pool */
		$pool = $container->getByType('Kdyby\Redis\ClientsPool');

		$connect = $close = 0;

		for ($i = 1; $i <= 1000 ;$i++) {
			$time = microtime(TRUE);

			foreach ($pool as $client) {
				$client->connect();
			}

			$connect += microtime(TRUE) - $time;
			$time = microtime(TRUE);

			foreach ($pool as $client) {
				$client->close();
			}

			$close += microtime(TRUE) - $time;
		}

		var_dump($connect * 1000); // time / 10 connections / 1000 repetitions = ms per one connect
		var_dump(($connect / 10) * 9); // difference between 1 and 10 opened connections

		var_dump($close * 1000);
	}

}

\run(new ClientTest());
