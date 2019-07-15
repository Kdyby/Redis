<?php

declare(strict_types = 1);

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Redis\Exception;

class LockException extends \Kdyby\Redis\Exception\RedisClientException
{

	private const PROCESS_TIMEOUT = 1;
	private const ACQUIRE_TIMEOUT = 2;

	public static function highConcurrency(): \Kdyby\Redis\Exception\LockException
	{
		return new static("Lock couldn't be acquired. Concurrency is way too high. I died of old age.", self::PROCESS_TIMEOUT);
	}

	public static function acquireTimeout(): \Kdyby\Redis\Exception\LockException
	{
		return new static("Lock couldn't be acquired. The locking mechanism is giving up. You should kill the request.", self::ACQUIRE_TIMEOUT);
	}

	public static function durabilityTimedOut(): \Kdyby\Redis\Exception\LockException
	{
		return new static('Process ran too long. Increase lock duration, or extend lock regularly.');
	}

	public static function invalidDuration(): \Kdyby\Redis\Exception\LockException
	{
		return new static('Some rude client have messed up the lock duration.');
	}

}
