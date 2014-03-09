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
class ClientsPool extends Nette\Object implements \IteratorAggregate
{

	/**
	 * Local clients are cache shards.
	 *
	 * @var array|RedisClient[]
	 */
	private $local = array();

	/**
	 * Remote  clients for invalidation of data only.
	 *
	 * @var array|RedisClient[]
	 */
	private $remote = array();



	public function __construct(array $local, array $remote)
	{
		foreach ($local as $client) {
			if (!$client instanceof RedisClient) {
				throw new InvalidArgumentException('Instance of Kdyby\\Redis\\RedisClient expected, ' . gettype($client) . ' given.');
			}

			$this->local[] = $client;
		}

		foreach ($remote as $client) {
			if (!$client instanceof RedisClient) {
				throw new InvalidArgumentException('Instance of Kdyby\\Redis\\RedisClient expected, ' . gettype($client) . ' given.');
			}

			$this->remote[] = $client;
		}
	}



	/**
	 * @param string $key
	 * @return RedisClient
	 */
	public function choose($key)
	{
		$num = array_sum(str_split(md5($key), 1));
		return $this->local[$num % count($this->local)];
	}



	/**
	 * @param int $i
	 * @return RedisClient
	 * @throws InvalidArgumentException
	 */
	public function get($i)
	{
		if (!isset($this->local[$i])) {
			throw new InvalidArgumentException("Client with index $i not found, there are only " . count($this->local) . ' clients.');
		}

		return $this->local[$i];
	}



	/**
	 * @return \ArrayIterator|\Traversable
	 */
	public function getIterator()
	{
		return new \ArrayIterator(array_merge($this->local, $this->remote));
	}

}
