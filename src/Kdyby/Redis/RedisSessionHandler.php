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
use Kdyby\Application\UI\Form;
use Nette\Diagnostics\Debugger;
use Nette;



/**
 * Redis session handler allows to store session in redis using Nette\Http\Session.
 *
 * <code>
 * $session->setStorage(new Kdyby\Redis\RedisSessionHandler($redisClient));
 * </code>
 *
 * @author Filip Procházka <filip@prochazka.su>
 */
class RedisSessionHandler extends Nette\Object implements Nette\Http\ISessionStorage
{

	/** @internal cache structure */
	const NS_NETTE = 'Nette.Session';

	/**
	 * @var string
	 */
	private $savePath;

	/**
	 * @var array
	 */
	private $ssIds = array();

	/**
	 * @var RedisClient
	 */
	private $client;



	/**
	 * @param RedisClient $redisClient
	 */
	public function __construct(RedisClient $redisClient)
	{
		$this->client = $redisClient;
	}



	/**
	 * @param $savePath
	 * @param $sessionName
	 *
	 * @return bool
	 */
	public function open($savePath, $sessionName)
	{
		$this->savePath = $savePath;
		return TRUE;
	}



	/**
	 * @param string $id
	 *
	 * @return string
	 */
	public function read($id)
	{
		try {
			$key = $this->formatKey($id);
			$this->ssIds[$key] = $this->client->lock($key);
			return (string) $this->client->get($key);

		} catch (Nette\InvalidStateException $e) {
			Debugger::log($e);
			return FALSE;
		}
	}



	/**
	 * @param string $id
	 * @param string $data
	 *
	 * @return bool
	 */
	public function write($id, $data)
	{
		try {
			$key = $this->formatKey($id);
			$this->ssIds[$key] = $this->client->lock($key);
			$this->client->setex($key, ini_get("session.gc_maxlifetime"), $data);
			return TRUE;

		} catch (Nette\InvalidStateException $e) {
			Debugger::log($e);
			return FALSE;
		}
	}



	/**
	 * @param string $id
	 *
	 * @return bool
	 */
	public function remove($id)
	{
		try {
			$key = $this->formatKey($id);
			$this->client->multi();
			$this->client->del($key);
			$this->client->unlock($key);
			unset($this->ssIds[$key]);
			$this->client->exec();
			return TRUE;

		} catch (Nette\InvalidStateException $e) {
			Debugger::log($e);
			return FALSE;
		}
	}



	/**
	 * @param string $id
	 *
	 * @return string
	 */
	private function formatKey($id)
	{
		return self::NS_NETTE . ':' . substr(md5($this->savePath), 0, 10) . ':' . $id;
	}



	/**
	 * @return bool
	 */
	public function close()
	{
		foreach ($this->ssIds as $key => $yes) {
			$this->client->unlock($key);
		}
		$this->ssIds = array();

		return TRUE;
	}



	/**
	 * @param int $maxLifeTime
	 *
	 * @return bool
	 */
	public function clean($maxLifeTime)
	{
		return TRUE;
	}



	public function __destruct()
	{
		$this->close();
	}

}
