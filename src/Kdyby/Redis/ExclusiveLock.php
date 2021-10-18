<?php

declare(strict_types = 1);

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Redis;

class ExclusiveLock
{

	use \Nette\SmartObject;

	/**
	 * @var \Kdyby\Redis\RedisClient
	 */
	private $client;

	/**
	 * @var array
	 */
	private $keys = [];

	/**
	 * Duration of the lock, this is time in seconds, how long any other process can't work with the row.
	 *
	 * @var int
	 */
	public $duration = 15;

	/**
	 * When there are too many requests trying to acquire the lock, you can set this timeout,
	 * to make them manually die in case they would be taking too long and the user would lock himself out.
	 *
	 * @var bool
	 */
	public $acquireTimeout = FALSE;

	public function __construct(\Kdyby\Redis\RedisClient $redisClient)
	{
		$this->client = $redisClient;
	}

	public function setClient(\Kdyby\Redis\RedisClient $client): void
	{
		$this->client = $client;
	}

	public function calculateTimeout(): int
	{
		return \time() + \abs((int) $this->duration) + 1;
	}

	/**
	 * Tries to acquire a key lock, otherwise waits until it's released and repeats.
	 *
	 * @param string $key
	 * @throws \Kdyby\Redis\Exception\LockException
	 * @return bool
	 */
	public function acquireLock(string $key): bool
	{
		if (isset($this->keys[$key])) {
			return $this->increaseLockTimeout($key);
		}

		$start = \microtime(TRUE);

		$lockKey = $this->formatLock($key);
		$maxAttempts = 10;
		do {
			$sleepTime = 5000;
			do {
				$timeout = $this->calculateTimeout();
				if ($this->client->setNX($lockKey, (string) $timeout)) {
					$this->keys[$key] = $timeout;
					return TRUE;
				}

				if ($this->acquireTimeout !== FALSE && (\microtime(TRUE) - $start) >= $this->acquireTimeout) {
					throw \Kdyby\Redis\Exception\LockException::acquireTimeout();
				}

				$lockExpiration = $this->client->get($lockKey);
				$sleepTime += 2500;

			} while (empty($lockExpiration) || ($lockExpiration >= \time() && !\usleep($sleepTime)));

			$oldExpiration = $this->client->getSet($lockKey, (string) $timeout = $this->calculateTimeout());
			if ($oldExpiration === $lockExpiration) {
				$this->keys[$key] = $timeout;
				return TRUE;
			}

		} while (--$maxAttempts > 0);

		throw \Kdyby\Redis\Exception\LockException::highConcurrency();
	}

	public function release(string $key): bool
	{
		if (!isset($this->keys[$key])) {
			return FALSE;
		}

		if ($this->keys[$key] <= \time()) {
			unset($this->keys[$key]);
			return FALSE;
		}

		$this->client->del($this->formatLock($key));
		unset($this->keys[$key]);
		return TRUE;
	}

	/**
	 * @param string $key
	 * @return bool
	 * @throws \Kdyby\Redis\Exception\LockException
	 */
	public function increaseLockTimeout(string $key): bool
	{
		if (!isset($this->keys[$key])) {
			return FALSE;
		}

		if ($this->keys[$key] <= \time()) {
			throw \Kdyby\Redis\Exception\LockException::durabilityTimedOut();
		}

		$oldTimeout = $this->client->getSet($this->formatLock($key), (string) $timeout = $this->calculateTimeout());
		if ((int) $oldTimeout !== (int) $this->keys[$key]) {
			throw \Kdyby\Redis\Exception\LockException::invalidDuration();
		}
		$this->keys[$key] = $timeout;
		return TRUE;
	}

	/**
	 * Release all acquired locks.
	 */
	public function releaseAll(): void
	{
		foreach (\array_keys($this->keys) as $key) {
			$this->release($key);
		}
	}

	/**
	 * Updates the indexing locks timeout.
	 */
	public function increaseLocksTimeout(): void
	{
		foreach (\array_keys($this->keys) as $key) {
			$this->increaseLockTimeout($key);
		}
	}

	public function getLockTimeout(string $key): int
	{
		if (!isset($this->keys[$key])) {
			return 0;
		}

		return $this->keys[$key] - \time();
	}

	protected function formatLock(string $key): string
	{
		return $key . ':lock';
	}

	public function __destruct()
	{
		$this->releaseAll();
	}

}
