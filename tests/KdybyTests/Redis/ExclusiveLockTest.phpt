<?php

declare(strict_types = 1);

/**
 * Test: Kdyby\Redis\ExclusiveLock.
 *
 * @testCase Kdyby\Redis\ExclusiveLockTest
 */

namespace KdybyTests\Redis;

use Kdyby\Redis\ExclusiveLock;
use Kdyby\Redis\RedisClient;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



class ExclusiveLockTest extends \KdybyTests\Redis\AbstractRedisTestCase
{

	public function testLockExpired(): void
	{
		$client = $this->client;
		Assert::exception(static function () use ($client): void {
			$first = new ExclusiveLock($client);
			$first->duration = 1;

			Assert::true($first->acquireLock('foo:bar'));
			\sleep(3);

			$first->increaseLockTimeout('foo:bar');
		}, 'Kdyby\Redis\Exception\LockException', 'Process ran too long. Increase lock duration, or extend lock regularly.');
	}

	public function testDeadlockHandling(): void
	{
		$first = new ExclusiveLock($this->client);
		$first->duration = 1;
		$second = new ExclusiveLock(new RedisClient());
		$second->duration = 1;

		Assert::true($first->acquireLock('foo:bar'));
		\sleep(3); // first died?

		Assert::true($second->acquireLock('foo:bar'));
	}

}

(new ExclusiveLockTest())->run();
