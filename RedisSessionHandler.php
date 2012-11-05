<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008, 2012 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Extension\Redis;

use Kdyby;
use Kdyby\Application\UI\Form;
use Nette\Diagnostics\Debugger;
use Nette;



/**
 * Redis session handler allows to store session in redis using Nette\Http\Session.
 *
 * <code>
 * $session->setStorage(new Kdyby\Extension\Redis\RedisSessionHandler($redisClient));
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
	 * @var RedisClient
	 */
	private $client;

	/**
	 * @var array
	 */
	private $locks = array();

	/**
	 * @var string
	 */
	private $locksDir;



	/**
	 * @param RedisClient $redisClient
	 * @param string $tempDir
	 */
	public function __construct(RedisClient $redisClient, $tempDir)
	{
		$this->client = $redisClient;
		$this->locksDir = $tempDir . '/session';
		if (!file_exists($this->locksDir)) {
			@mkdir($this->locksDir, 0777);
		}
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
		return true;
	}



	/**
	 * @param string $id
	 *
	 * @return string
	 */
	public function read($id)
	{
		try {
			$key = $this->getKeyId($id);
			$this->lock($key, 'r+');
			return (string) $this->client->get($key);

		} catch (Nette\InvalidStateException $e) {
			Debugger::log($e);
			return false;
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
			$key = $this->getKeyId($id);
			$this->lock($key, 'w+');
			$this->client->setex($key, ini_get("session.gc_maxlifetime"), $data);
			return true;

		} catch (Nette\InvalidStateException $e) {
			Debugger::log($e);
			return false;
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
			$key = $this->getKeyId($id);
			$this->client->del($key);
			if (isset($this->locks[$key])) {
				flock($this->locks[$key], LOCK_UN);
				fclose($this->locks[$key]);
			}
			return true;

		} catch (Nette\InvalidStateException $e) {
			Debugger::log($e);
			return false;
		}
	}



	/**
	 * @param string $key
	 * @param string $mode
	 */
	protected function lock($key, $mode = 'r+')
	{
		if (isset($this->locks[$key])) {
			return;
		}

		if ($fp = @fopen($this->locksDir . '/' . $key, $mode)) {
			flock($this->locks[$key] = $fp, LOCK_EX);
		}
	}



	/**
	 * @param string $id
	 *
	 * @return string
	 */
	private function getKeyId($id)
	{
		return self::NS_NETTE . ':' . substr(md5($this->savePath), 0, 10) . ':' . $id;
	}



	/**
	 * @return bool
	 */
	public function close()
	{
		while ($lock = array_shift($this->locks)) {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
		return true;
	}



	/**
	 * @param int $maxLifeTime
	 *
	 * @return bool
	 */
	public function clean($maxLifeTime)
	{
		return true;
	}



	public function __destruct()
	{
		$this->close();
	}

}
