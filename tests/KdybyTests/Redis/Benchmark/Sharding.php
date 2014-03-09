<?php

/**
 * Test: Kdyby\Redis\StorageRouter.
 *
 * @testCase Kdyby\Redis\StorageRouterTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Redis
 */

namespace KdybyTests\Redis\Benchmark;

use KdybyTests\Redis\AbstractRedisTestCase;
use Nette;
use Tester;

require_once __DIR__ . '/../../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ShardingTest extends AbstractRedisTestCase
{

	public function testBenchmark()
	{

		$container = $this->createContainer('sharding');
		foreach ($container->getByType('Kdyby\Redis\ClientsPool') as $client) {
			$this->getClient($client); // flushdb
		}

		$containerFile = Nette\Reflection\ClassType::from($container)->getFileName();
		$containerClass = get_class($container);

		$statsFile = TEMP_DIR . '/stats.log';

		$time = microtime(TRUE);

		$this->threadStress(function () use ($containerFile, $containerClass, $statsFile) {
			require_once $containerFile;
			/** @var Nette\DI\Container|\SystemContainer $container */
			$container = new $containerClass();
			$container->initialize();
			Nette\Diagnostics\Debugger::enable(TRUE, TEMP_DIR);

			/** @var \Kdyby\Redis\RedisStorage $storage */
			$storage = $container->getByType('Kdyby\Redis\RedisStorage');
			// $storage->disableLocking();
			$cache = new \Nette\Caching\Cache($storage);
			$stats = array('miss' => 0, 'queries' => 0, 'time' => 0, 'keys' => array());

			// simulate overkilling doctrine cache with 300 entities
			for ($e = 1; $e <= 300; $e += 15) {
				$key = rand($e, $e + 15);
				$cache->load($e, function (&$dp) use ($key, $cache, &$stats) {
					$stats['miss'] += 1;

					// each entity has 20 properties
					for ($a = 1; $a <= 20; $a++) {
						$cache->load($a, function (&$dp) use ($a, $cache, &$stats) {
							$stats['miss'] += 1;
							return md5($a);
						});
					}

					return new Nette\Http\Request(new Nette\Http\UrlScript(), array('e' => $key));
				});
			}

			// simulate query cache
			for ($i = 1; $i <= 5000; $i += 500) {
				$key = rand($i, $i + 500);
				$cache->load($key, function (&$dp) use ($key, $cache, &$stats) {
					$stats['miss'] += 1;
					$dp[$cache::TAGS] = array_unique(array(md5($key % 100), md5($key % 10)));
					return array_fill(0, 30, range('a', 'z'));
				});
			}

			// simulate template cache
			for ($i = 1; $i <= 100000; $i += 5000) {
				$key = rand($i, $i + 5000);
				$cache->load($key, function (&$dp) use ($key, $cache, &$stats) {
					$stats['miss'] += 1;
					$dp[$cache::TAGS] = array_unique(array(md5($key % 100), md5($key % 10)));
					return str_repeat('<a href="javascript:;">click me!</a>', 100);
				});
			}

			if (rand(1, 300) === 1) {
				$cache->clean(array(
					$cache::TAGS => $stats['cleaning'] = array(md5(rand(0, 9)), md5(rand(0, 9))),
				));
			}

			/** @var \Kdyby\Redis\Diagnostics\Panel $panel */
			$panel = $container->getByType('Kdyby\Redis\Diagnostics\Panel');
			$stats['queries'] = $panel->getQueryCount();
			$stats['time'] = sprintf('%0.1f', $panel->getTotalTime());

			/** @var \Kdyby\Redis\ClientsPool $pool */
			$pool = $container->getByType('Kdyby\Redis\ClientsPool');
			foreach ($pool as $client) {
				/** @var \Kdyby\Redis\RedisClient $client */
				$info = $client->info();
				$stats['keys'][$info['tcp_port']] = $info['db0']['keys'];
			}

			$stats['args'] = $_SERVER['argv'][1];

			file_put_contents($statsFile, json_encode($stats) . "\n", FILE_APPEND);

		}, 6000, 20);

		var_dump(microtime(TRUE) - $time);

		// convert whole file to json
		$stats = implode(",\n", array_filter(explode("\n", file_get_contents($statsFile))));
		file_put_contents($statsFile, "[$stats]");

		// archive
		rename($statsFile, dirname(TEMP_DIR) . '/sharding-benchmark.' . time() . '.json');
	}

}

\run(new ShardingTest());
