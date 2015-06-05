<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Redis;

use Kdyby;
use Nette;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class RedisSharedClient extends RedisClient
{

	/**
	 * @var ConnectionPool
	 */
	private $connectionPool;


	/**
	 * @param ConnectionPool $connectionPool
	 * @param string $host
	 * @param int $port
	 * @param int $database
	 * @param int $timeout
	 * @param string $auth
	 * @param bool $persistent
	 * @throws MissingExtensionException
	 */
	public function __construct(ConnectionPool $connectionPool, $host = '127.0.0.1', $port = NULL, $database = 0, $timeout = 10, $auth = NULL, $persistent = FALSE)
	{
		parent::__construct($host, $port, $database, $timeout, $auth, $persistent);
		$this->connectionPool = $connectionPool;
	}



	public function connect()
	{
		if (!$this->driver) {
			$driver = $this->connectionPool->getConnection($this->getHost(), $this->getPort());

			if ($driver === NULL) {
				$driver = new Driver\PhpRedisSharedDriver();
				$this->connectionPool->addConnection($this->getHost(), $this->getPort(), $driver);
			}

			$this->driver = $driver;
		}

		parent::connect();

		$this->synchronizeClientDatabase();
	}



	/**
	 * {@inheritdoc}
	 */
	public function send($cmd, array $args = array())
	{
		if ($this->driver) {
			if (!$this->driver->isConnected()) {
				$this->connect();
			}
			$this->synchronizeClientDatabase();
		}

		return parent::send($cmd, $args);
	}



	/**
	 * @throws RedisClientException
	 */
	private function synchronizeClientDatabase()
	{
		if (!($this->driver instanceof Driver\PhpRedisSharedDriver)) {
			throw new RedisClientException('Only PhpRedisSharedDriver allow synchronizing database');
		}

		if ($this->driver->getDatabase() != $this->getDatabase()) {
			if (call_user_func_array(array($this->driver, 'select'), [$this->getDatabase()]) === FALSE) {
				throw new RedisClientException('Can\'t set client database on driver');
			}
		}
	}

}
