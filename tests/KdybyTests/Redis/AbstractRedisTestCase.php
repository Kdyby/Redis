<?php

namespace KdybyTests\Redis;

use Kdyby;
use Kdyby\Redis\RedisClient;
use Kdyby\Redis\RedisClientException;
use Nette\PhpGenerator as Code;
use Nette\Reflection\ClassType;
use Nette\Reflection\GlobalFunction;
use Nette\Utils\AssertionException;
use Nette;
use Nette\Utils\Strings;
use Tester;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
abstract class AbstractRedisTestCase extends Tester\TestCase
{

	/**
	 * @var \Kdyby\Redis\RedisClient
	 */
	protected $client;

	/**
	 * @var resource
	 */
	private static $lock;



	protected function getClient()
	{
		if ($this->client) {
			return $this->client;
		}

		flock(self::$lock = fopen(dirname(TEMP_DIR) . '/lock-redis', 'w'), LOCK_EX);

		$client = new RedisClient();
		try {
			$client->connect();

		} catch (RedisClientException $e) {
			Tester\Helpers::skip($e->getMessage());
		}

		try {
			$client->assertVersion();

		} catch (AssertionException $e) {
			Tester\Helpers::skip($e->getMessage());
		}

		try {
			$client->flushDb();

		} catch (RedisClientException $e) {
			Tester\Assert::fail($e->getMessage());
		}

		return $this->client = $client;
	}



	protected function setUp()
	{
		$this->getClient(); // make sure it's created
	}



	protected function tearDown()
	{
		if (self::$lock) {
			@flock(self::$lock, LOCK_UN);
			@fclose(self::$lock);
			self::$lock = NULL;
		}

		$this->client = NULL;
	}

}
