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
 * Redis session handler allows to store session in redis using Nette\Http\Session.
 *
 * <code>
 * $session->setStorage(new Kdyby\Redis\RedisSessionHandler($redisClient));
 * </code>
 *
 * @author Filip Procházka <filip@prochazka.su>
 */
class RedisSessionHandler implements \SessionHandlerInterface
{
	use Nette\SmartObject;

	/** @internal cache structure */
	const NS_NETTE = 'Nette.Session:';

	/**
	 * @var array
	 */
	private $ssIds = [];

	/**
	 * @var RedisClient
	 */
	private $client;

	/**
	 * @var Nette\Http\Session
	 */
	private $session;

	/**
	 * @var integer
	 */
	private $ttl;



	/**
	 * @param RedisClient $redisClient
	 */
	public function __construct(RedisClient $redisClient)
	{
		$this->client = $redisClient;
	}



	/**
	 * @internal
	 * @param Nette\Http\Session $session
	 * @return RedisSessionHandler
	 */
	public function bind(Nette\Http\Session $session)
	{
		$this->session = $session;
		$session->setHandler($this);
		return $this;
	}



	/**
	 * @return int|string
	 */
	protected function getTtl()
	{
		if ($this->ttl === NULL) {
			if ($this->session !== NULL) {
				$options = $this->session->getOptions();
				$ttl = min(array_filter([$options['cookie_lifetime'], $options['gc_maxlifetime']], function ($v) { return $v > 0; })) ?: 0;

			} else {
				$ttl = ini_get('session.gc_maxlifetime');
			}

			if ($ttl <= 0) {
				throw new \InvalidArgumentException('PHP settings "cookie_lifetime" or "gc_maxlifetime" must be greater than 0');
			}

			$this->ttl = $ttl;
		}

		return $this->ttl;
	}



	/**
	 * @param int $ttl
	 */
	public function setTtl($ttl)
	{
		$this->ttl = max($ttl, 0);
	}



	/**
	 * @param string $savePath
	 * @param string $sessionName
	 * @return bool
	 */
	public function open($savePath, $sessionName)
	{
		return TRUE;
	}



	/**
	 * @param string $id
	 * @throws SessionHandlerException
	 * @return string
	 */
	public function read($id)
	{
		return (string) $this->client->get($this->lock($id));
	}



	/**
	 * @param string $id
	 * @param string $data
	 * @return bool
	 */
	public function write($id, $data)
	{
		if (!isset($this->ssIds[$id])) {
			return FALSE;
		}

		return $this->client->setex($this->formatKey($id), $this->getTtl(), $data);
	}



	/**
	 * @param string $id
	 *
	 * @return bool
	 */
	public function destroy($id)
	{
		if (!isset($this->ssIds[$id])) {
			return FALSE;
		}

		$key = $this->formatKey($id);
		$this->client->multi(function (RedisClient $client) use ($key) {
			$client->del($key);
			$client->unlock($key);
		});

		return TRUE;
	}



	/**
	 * @return bool
	 */
	public function close()
	{
		foreach ($this->ssIds as $id => $key) {
			$this->client->unlock($key);
		}
		$this->ssIds = [];

		return TRUE;
	}



	/**
	 * @param int $maxLifeTime
	 *
	 * @return bool
	 */
	public function gc($maxLifeTime)
	{
		return TRUE;
	}



	/**
	 * @param string $id
	 * @return string
	 */
	protected function lock($id)
	{
		try {
			$key = $this->formatKey($id);
			$this->client->lock($key);
			$this->ssIds[$id] = $key;

			return $key;

		} catch (LockException $e) {
			throw new SessionHandlerException(sprintf('Cannot work with non-locked session id %s: %s', $id, $e->getMessage()), 0, $e);
		}
	}



	/**
	 * @param string $id
	 *
	 * @return string
	 */
	private function formatKey($id)
	{
		return self::NS_NETTE . $id;
	}

}
