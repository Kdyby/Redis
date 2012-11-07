<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008, 2012 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Extension\Redis;

use Kdyby;
use Nette;



/**
 * @author Ondřej Nešpor
 * @author Filip Procházka <filip@prochazka.su>
 */
class ExclusiveLock extends Nette\Object
{

	/**
	 * @var RedisClient
	 */
	private $client;

	/**
	 * @var array
	 */
	private $keys = array();

	/**
	 * @var int
	 */
	public $timeout = 15;



	/**
	 * @param RedisClient $redisClient
	 */
	public function __construct(RedisClient $redisClient)
	{
		$this->client = $redisClient;
	}



	/**
	 * @return int
	 */
	public function getTimeout()
	{
		return time() + $this->timeout + 1;
	}



	/**
	 * Acquires an indexing lock if possible.
	 *
	 * @param string $key
	 * @throws LockException
	 * @return bool
	 */
	public function acquireLock($key)
	{
		if (isset($this->keys[$key])) {
			return true;
		}

		$lockKey = $this->formatLock($key);
		do {
			if ($this->client->setNX($lockKey, $this->getTimeout())) {
				$this->keys[$key] = $lockKey;
				return true;
			}

			$lockExpiration = $this->client->get($lockKey);
		} while (empty($lockExpiration) || ($lockExpiration >= time() && !usleep(10000)));

		throw new LockException('Deadlock');

//		$this->client->watch($lockKey);
//		if (($lockExpiration = $this->client->get($lockKey)) < time()) {
//			try {
//				$this->client->multi();
//				$this->updateLockTimeout($key);
//				$this->client->exec();
//
//				$this->keys[] = $key;
//				return true;
//
//			} catch (TransactionException $e) {
//				throw new LockException($e->getMessage(), 0, $e);
//			}
//		}
//
//		$this->client->unwatch();
//		throw new LockException("Lock could not be acquired.");
	}



	/**
	 * @param string $key
	 */
	public function release($key)
	{
		$this->client->del($this->formatLock($key));
		unset($this->keys[$key]);
	}



	/**
	 * @param string $key
	 */
	public function updateLockTimeout($key)
	{
		$this->client->set($this->formatLock($key), $this->getTimeout());
	}



	/**
	 * Release all acquired locks.
	 */
	public function releaseAll()
	{
		foreach ((array)$this->keys as $key => $lockKey) {
			$this->release($key);
		}
	}



	/**
	 * Updates the indexing locks timeout.
	 */
	public function updateLocksTimeout()
	{
		foreach ($this->keys as $key => $lockKey) {
			$this->updateLockTimeout($key);
		}
	}



	/**
	 * @param string $key
	 * @return string
	 */
	protected function formatLock($key)
	{
		return $key . ':lock';
	}



	public function __destruct()
	{
		$this->releaseAll();
	}

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class LockException extends RedisClientException
{

}
