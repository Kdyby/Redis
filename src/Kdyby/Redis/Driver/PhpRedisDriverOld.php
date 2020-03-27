<?php

declare(strict_types = 1);

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Redis\Driver;

class PhpRedisDriverOld extends \Kdyby\Redis\Driver\PhpRedisDriver
{

	/**
	 * Connects to a Redis instance or reuse a connection already established with pconnect/popen.
	 *
	 * The connection will not be closed on close or end of request until the php process ends.
	 * So be patient on to many open FD's (specially on redis server side)
	 * when using persistent connections on many servers connecting to one redis server.
	 *
	 * Also more than one persistent connection can be made identified
	 * by either host + port + timeout or host + persistent_id or unix socket + timeout.
	 *
	 * This feature is not available in threaded versions.
	 * pconnect and popen then working like their non persistent equivalents.
	 *
	 * @param string $host can be a host, or the path to a unix domain socket
	 * @param int $port
	 * @param int $timeout value in seconds (optional, default is 0 meaning unlimited)
	 * @return bool
	 */
	public function connect(string $host, ?int $port = NULL, int $timeout = 0): bool
	{
		return parent::connect($host, $port, $timeout);
	}

	/**
	 * Execute the Redis SCRIPT command to perform various operations on the scripting subsystem.
	 *
	 * @param mixed $command
	 * @param mixed $script
	 * @return mixed
	 */
	public function script($command, $script = NULL)
	{
		return parent::script($command, $script);
	}

}
