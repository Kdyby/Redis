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



	public function testAcquireTimeoutLongerThenLockDuration()
	{
		$first = new ExclusiveLock($this->client);
		$first->acquireTimeout = 4;
		$first->duration = 1;
		$second = new ExclusiveLock(new RedisClient());
		$second->acquireTimeout = 4;
		$second->duration = 1;

		Assert::true($first->acquireLock('foo:bar'));
		sleep(2);

		Assert::exception(function () use ($second) {
			$second->acquireLock('foo:bar');
		}, 'Kdyby\Redis\LockException', 'Lock couldn\'t be acquired. The locking mechanism is giving up. You should kill the request.');
		sleep(2);

		Assert::true($second->acquireLock('foo:bar'));

		Assert::false($first->release('foo:bar'));
		Assert::true($second->release('foo:bar'));

		Assert::same(1, $this->client->lLen('foo:bar:lock'));
	}

}

\run(new ExclusiveLockTest());
