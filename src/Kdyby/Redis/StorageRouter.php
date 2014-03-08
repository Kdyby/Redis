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
class StorageRouter extends RedisStorage
{

	/**
	 * @var ClientsPool
	 */
	private $clients;



	public function __construct(ClientsPool $clients, JournalRouter $journal = NULL)
	{
		$this->clients = $clients;
		$this->journal = $journal;
	}


	public function read($key)
	{
		$this->client = $this->clients->chooseClient($key);
		return parent::read($key);
	}



	public function lock($key)
	{
		$this->client = $this->clients->chooseClient($key);
		parent::lock($key);
	}



	public function write($key, $data, array $dependencies)
	{
		$this->client = $this->clients->chooseClient($key);
		parent::write($key, $data, $dependencies);
	}



	public function remove($key)
	{
		$this->client = $this->clients->chooseClient($key);
		return parent::remove($key);
	}



	public function clean(array $conditions)
	{
		foreach ($this->clients as $client) {
			$this->client = $client;
			parent::clean($conditions);
		}
	}

}
