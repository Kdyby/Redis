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
class JournalRouter extends RedisLuaJournal
{

	/**
	 * @var ClientsPool
	 */
	private $clients;



	public function __construct(ClientsPool $clients)
	{
		$this->clients = $clients;
	}



	public function write($key, array $dependencies)
	{
		$this->client = $this->clients->chooseClient($key);
		parent::write($key, $dependencies);
	}



	public function clean(array $conditions, Nette\Caching\IStorage $storage = NULL)
	{
		$result = array();
		foreach ($this->clients as $client) {
			$this->client = $client;
			$result = array_merge($result, parent::clean($conditions, $storage));
		}

		return $result;
	}

}
