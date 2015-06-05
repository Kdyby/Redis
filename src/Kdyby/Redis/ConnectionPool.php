<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Redis;

use Kdyby;
use Nette;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ConnectionPool extends Nette\Object
{

	/**
	 * @var IRedisDriver[]
	 */
	private $connections = array();


	/**
	 * Add new connection to pool
	 * @param string $host
	 * @param int $port
	 * @param IRedisDriver $connection
	 * @throws ConnectionAlreadyInPoolException
	 */
	public function addConnection($host, $port, IRedisDriver $connection)
	{
		$key = $this->getKey($host, $port);

		if (isset($this->connections[$key])) {
			throw new ConnectionAlreadyInPoolException;
		}

		$this->connections[$key] = $connection;
	}



	/**
	 * Get connection from pool
	 * @param string $host
	 * @param int $port
	 * @return Driver\PhpRedisDriver
	 */
	public function getConnection($host, $port)
	{
		$key = $this->getKey($host, $port);

		if (!isset($this->connections[$key])) {
			return NULL;
		}

		return $this->connections[$key];
	}



	private function getKey($host, $port)
	{
		return strtolower($host) . ':' . $port;
	}

}
