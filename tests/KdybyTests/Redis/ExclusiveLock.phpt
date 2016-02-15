<?php

/**
 * Test: Kdyby\Redis\ExclusiveLock.
 *
 * @testCase Kdyby\Redis\ExclusiveLockTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Redis
 */

namespace KdybyTests\Redis;

use Kdyby\Redis\ExclusiveLock;
use Kdyby\Redis\RedisClient;
use Nette;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ExclusiveLockTest extends AbstractRedisTestCase
{

	public function testLockExpired()
	{
		$client = $this->client;
		Assert::exception(function () use ($client) {
			$first = new ExclusiveLock($client);
			$first->duration = 1;

			Assert::true($first->acquireLock('foo:bar'));
			sleep(3);

			$first->increaseLockTimeout('foo:bar');

		}, 'Kdyby\Redis\LockException', 'Process ran too long. Increase lock duration, or extend lock regularly.');
	}

	public function testLockExpiredWithNamespace()
	{
		$client = $this->client;
		Assert::exception(function () use ($client) {
			$first = new ExclusiveLock($client, 'foo');
			$first->duration = 1;

			Assert::true($first->acquireLock('foo:bar'));
			sleep(3);

			$first->increaseLockTimeout('foo:bar');

		}, 'Kdyby\Redis\LockException', 'Process ran too long. Increase lock duration, or extend lock regularly.');
	}



	public function testDeadlockHandling()
	{
		$first = new ExclusiveLock($this->client);
		$first->duration = 1;
		$second = new ExclusiveLock(new RedisClient());
		$second->duration = 1;

		Assert::true($first->acquireLock('foo:bar'));
		sleep(3); // first died?

		Assert::true($second->acquireLock('foo:bar'));
	}

	public function testDeadlockHandlingWithNamespace()
	{
		$first = new ExclusiveLock($this->client, 'foo');
		$first->duration = 1;
		$second = new ExclusiveLock(new RedisClient());
		$second->duration = 1;

		Assert::true($first->acquireLock('foo:bar'));
		sleep(3); // first died?

		Assert::true($second->acquireLock('foo:bar'));
	}

	/**
	 * @return array
	 */
	public function getLockKeyTestData()
	{
		return array(
			array('bar'),
			array('foo:bar'),
			array('xxx:foo:bar'),
		);
	}

	/**
	 * @dataProvider getLockKeyTestData
	 * @param $key
	 */
	public function testLockKey($key)
	{
		$client = \Mockery::mock('Kdyby\Redis\RedisClient');
		$client->shouldReceive('setNX')->once()->andReturn(false);
		$client->shouldReceive('get')->once()->with($key . ':lock')->andReturn(10);
		$client->shouldReceive('getSet')->once()->andReturn(10);
		$client->shouldReceive('del')->with($key . ':lock');
		$client->shouldReceive('close')->with();
		$lock = new ExclusiveLock($client);
		$lock->duration = 5;
		$lock->acquireLock($key);
		Assert::true((0 < $lock->getLockTimeout($key)));
		\Mockery::close();
	}
}

\run(new ExclusiveLockTest());
