<?php

/**
 * Test: Kdyby\Redis\RedisClient.
 *
 * @testCase Kdyby\Redis\RedisClientTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Redis
 */

namespace KdybyTests\Redis;

use Kdyby\Redis\ConnectionPool;
use Kdyby\Redis\RedisSharedClient;
use Kdyby\Redis\RedisClientException;
use Kdyby\Redis\Driver\PhpRedisSharedDriver;
use Nette;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class RedisSharedClientTest extends AbstractRedisTestCase
{

	/**
	 * @var ConnectionPool
	 */
	private $pool;

	/**
	 * @var RedisSharedClient
	 */
	private $client1;

	/**
	 * @var RedisSharedClient
	 */
	private $client2;



	public function setUp()
	{
		parent::setUp();
		$this->pool = new ConnectionPool;
		$this->client1 = new RedisSharedClient($this->pool, '127.0.0.1', NULL, 0);
		$this->client2 = new RedisSharedClient($this->pool, '127.0.0.1', NULL, 1);

		try {
			$this->client1->flushDb();
			$this->client2->flushDb();

		} catch (RedisClientException $e) {
			Tester\Assert::fail($e->getMessage());
		}

	}



	public function testConnectionPool_GetExistingConnection()
	{
		Assert::same($this->pool->getConnection('127.0.0.1', NULL), $this->client1->getDriver());
		Assert::same($this->pool->getConnection('127.0.0.1', NULL), $this->client2->getDriver());
	}



	public function testConnectionPool_GetNonexistingConnection()
	{
		Assert::same(NULL, $this->pool->getConnection('127.0.0.1', 1234));
	}



	public function testConnectionPool_AddExistingConnection()
	{
		$sharedDriver = new PhpRedisSharedDriver;
		Assert::exception(function () use ($sharedDriver) {
			$this->pool->addConnection('127.0.0.1', NULL, $sharedDriver);

		}, 'Kdyby\Redis\ConnectionAlreadyInPoolException');
	}



	public function testSharedClients()
	{
		$driver = $this->client1->getDriver();

		Assert::same(FALSE, $this->client1->test);
		Assert::same(FALSE, $this->client2->test);

		$this->client1->test = 'xx';
		Assert::same(0, $driver->getDatabase());

		Assert::same('xx', $this->client1->test);
		Assert::same(FALSE, $this->client2->test);

		$this->client2->test = 'yy';
		Assert::same(1, $driver->getDatabase());

		Assert::same('xx', $this->client1->test);
		Assert::same('yy', $this->client2->test);

		$this->client1->test = 'zz';
		Assert::same(0, $driver->getDatabase());

		Assert::same('zz', $this->client1->test);
		Assert::same('yy', $this->client2->test);
	}

}

\run(new RedisSharedClientTest());
