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
use Nette\Caching\Cache;
use Nette\Caching\Storages\IJournal;



/**
 * Redis Storage.
 *
 * @author Filip Procházka <filip@prochazka.su>
 */
class RedisStorage implements IMultiReadStorage
{
	use Nette\SmartObject;

	/** @internal cache structure */
	const NS_NETTE = 'Nette.Storage';

	/** @internal cache meta structure: array of */
	const META_TIME = 'time', // timestamp
		META_SERIALIZED = 'serialized', // is content serialized?
		META_EXPIRE = 'expire', // expiration timestamp
		META_DELTA = 'delta', // relative (sliding) expiration
		META_ITEMS = 'di', // array of dependent items (file => timestamp)
		META_CALLBACKS = 'callbacks'; // array of callbacks (function, args)

	/** additional cache structure */
	const KEY = 'key';

	/**
	 * @var RedisClient
	 */
	private $client;

	/**
	 * @var \Nette\Caching\Storages\IJournal
	 */
	private $journal;

	/**
	 * @var bool
	 */
	private $useLocks = TRUE;



	/**
	 * @param RedisClient $client
	 * @param \Nette\Caching\Storages\IJournal $journal
	 */
	public function __construct(RedisClient $client, IJournal $journal = NULL)
	{
		$this->client = $client;
		$this->journal = $journal;
	}



	public function disableLocking()
	{
		$this->useLocks = FALSE;
	}



	/**
	 * Read from cache.
	 *
	 * @param string $key
	 *
	 * @return mixed|NULL
	 */
	public function read($key)
	{
		if (!($stored = $this->doRead($key)) || !$this->verify($stored[0])) {
			return NULL;
		}

		return self::getUnserializedValue($stored);
	}





	/**
	 * Read multiple entries from cache (using mget)
	 *
	 * @param array $keys
	 * @return array
	 */
	public function multiRead(array $keys)
	{
		$values = [];
		foreach ($this->doMultiRead($keys) as $key => $stored) {
			$values[$key] = NULL;
			if ($stored !== NULL && $this->verify($stored[0])) {
				$values[$key] = self::getUnserializedValue($stored);
			}
		}

		return $values;
	}



	/**
	 * Verifies dependencies.
	 *
	 * @param  array
	 *
	 * @return bool
	 */
	protected function verify($meta)
	{
		do {
			if (!empty($meta[self::META_DELTA])) {
				$this->client->send('expire', [$this->formatEntryKey($meta[self::KEY]), $meta[self::META_DELTA]]);

			} elseif (!empty($meta[self::META_EXPIRE]) && $meta[self::META_EXPIRE] < time()) {
				break;
			}

			if (!empty($meta[self::META_CALLBACKS]) && !Cache::checkCallbacks($meta[self::META_CALLBACKS])) {
				break;
			}

			if (!empty($meta[self::META_ITEMS])) {
				foreach ($meta[self::META_ITEMS] as $itemKey => $time) {
					$m = $this->readMeta($itemKey);
					if ($m[self::META_TIME] !== $time || ($m && !$this->verify($m))) {
						break 2;
					}
				}
			}

			return TRUE;
		} while (FALSE);

		$this->remove($meta[self::KEY]); // meta[handle] & meta[file] was added by readMetaAndLock()
		return FALSE;
	}



	/**
	 * @param string $key
	 * @return void
	 */
	public function lock($key)
	{
		if ($this->useLocks) {
			$this->client->lock($this->formatEntryKey($key));
		}
	}



	/**
	 * @internal
	 * @param string $key
	 */
	public function unlock($key)
	{
		if ($this->useLocks) {
			$this->client->unlock($this->formatEntryKey($key));
		}
	}



	/**
	 * Writes item into the cache.
	 *
	 * @param string $key
	 * @param mixed $data
	 * @param array $dp
	 *
	 * @throws \Nette\InvalidStateException
	 * @return void
	 */
	public function write($key, $data, array $dp)
	{
		$meta = [
			self::META_TIME => microtime(),
		];

		if (isset($dp[Cache::EXPIRATION])) {
			if (empty($dp[Cache::SLIDING])) {
				$meta[self::META_EXPIRE] = $dp[Cache::EXPIRATION] + time(); // absolute time

			} else {
				$meta[self::META_DELTA] = (int)$dp[Cache::EXPIRATION]; // sliding time
			}
		}

		if (isset($dp[Cache::ITEMS])) {
			foreach ((array)$dp[Cache::ITEMS] as $itemName) {
				$m = $this->readMeta($itemName);
				$meta[self::META_ITEMS][$itemName] = $m[self::META_TIME]; // may be NULL
				unset($m);
			}
		}

		if (isset($dp[Cache::CALLBACKS])) {
			$meta[self::META_CALLBACKS] = $dp[Cache::CALLBACKS];
		}

		$cacheKey = $this->formatEntryKey($key);

		if (isset($dp[Cache::TAGS]) || isset($dp[Cache::PRIORITY])) {
			if (!$this->journal) {
				throw new Nette\InvalidStateException('CacheJournal has not been provided.');
			}
			$this->journal->write($cacheKey, $dp);
		}

		if (!is_string($data) || $data === NULL) {
			$data = serialize($data);
			$meta[self::META_SERIALIZED] = TRUE;
		}

		$store = json_encode($meta) . Cache::NAMESPACE_SEPARATOR . $data;

		try {
			if (isset($dp[Cache::EXPIRATION])) {
				$this->client->send('setEX', [$cacheKey, $dp[Cache::EXPIRATION], $store]);

			} else {
				$this->client->send('set', [$cacheKey, $store]);
			}

			$this->unlock($key);

		} catch (RedisClientException $e) {
			$this->remove($key);
			throw new Nette\InvalidStateException($e->getMessage(), $e->getCode(), $e);
		}
	}



	/**
	 * Removes item from the cache.
	 *
	 * @param string $key
	 */
	public function remove($key)
	{
		$this->client->send('del', [$this->formatEntryKey($key)]);
	}



	/**
	 * Removes items from the cache by conditions & garbage collector.
	 *
	 * @param array $conds
	 *
	 * @return void
	 */
	public function clean(array $conds)
	{
		// cleaning using file iterator
		if (!empty($conds[Cache::ALL])) {
			if ($keys = $this->client->send('keys', [self::NS_NETTE . ':*'])) {
				$this->client->send('del', $keys);
			}

			if ($this->journal) {
				$this->journal->clean($conds);
			}
			return;
		}

		// cleaning using journal
		if ($this->journal) {
			if ($keys = $this->journal->clean($conds, $this)) {
				$this->client->send('del', $keys);
			}
		}
	}



	/**
	 * @param string $key
	 * @return string
	 */
	protected function formatEntryKey($key)
	{
		return self::NS_NETTE . ':' . str_replace(Cache::NAMESPACE_SEPARATOR, ':', $key);
	}



	/**
	 * @param string $key
	 *
	 * @return array
	 */
	protected function readMeta($key)
	{
		if (!$stored = $this->doRead($key)) {
			return NULL;

		}

		return $stored[0];
	}



	/**
	 * @param string $key
	 * @return array|null
	 */
	private function doRead($key)
	{
		if (!$stored = $this->client->send('get', [$this->formatEntryKey($key)])) {
			return NULL;
		}

		return self::processStoredValue($key, $stored);
	}



	/**
	 * @param array $keys
	 * @return array
	 */
	private function doMultiRead(array $keys)
	{
		$formatedKeys = array_map([$this, 'formatEntryKey'], $keys);

		$result = [];
		foreach ($this->client->send('mget', [$formatedKeys]) as $index => $stored) {
			$key = $keys[$index];
			$result[$key] = $stored !== FALSE ? self::processStoredValue($key, $stored) : NULL;
		}

		return $result;
	}



	/**
	 * @param string $key
	 * @param string $storedValue
	 * @return array
	 */
	private static function processStoredValue($key, $storedValue)
	{
		list($meta, $data) = explode(Cache::NAMESPACE_SEPARATOR, $storedValue, 2) + [NULL, NULL];
		return [[self::KEY => $key] + json_decode($meta, TRUE), $data];
	}



	/**
	 * @param $stored
	 * @return mixed
	 */
	private static function getUnserializedValue($stored)
	{
		if (empty($stored[0][self::META_SERIALIZED])) {
			return $stored[1];

		} else {
			return @unserialize($stored[1]); // intentionally @
		}
	}

}
