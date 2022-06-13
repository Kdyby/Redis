<?php

declare(strict_types = 1);

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Redis;

use Nette\Caching\Cache;
use Nette\Caching\Storages\Journal;

/**
 * Redis Storage.
 */
class RedisStorage implements \Kdyby\Redis\IMultiReadStorage
{

	use \Nette\SmartObject;

	/**
	 * cache structure
	 *
	 * @internal
	 */
	private const NS_NETTE = 'Nette.Storage';
	private const NAMESPACE_SEPARATOR = "\x00";

	/**
	 * cache meta structure: array of
	 *
	 * @internal
	 */
	private const META_TIME = 'time'; // timestamp
	private const META_SERIALIZED = 'serialized'; // is content serialized?
	private const META_EXPIRE = 'expire'; // expiration timestamp
	private const META_DELTA = 'delta'; // relative (sliding) expiration
	private const META_ITEMS = 'di'; // array of dependent items (file => timestamp)
	private const META_CALLBACKS = 'callbacks'; // array of callbacks (function, args)

	/**
	 * additional cache structure
	 */
	private const KEY = 'key';

	/**
	 * @var \Kdyby\Redis\RedisClient
	 */
	private $client;

	/**
	 * @var \Nette\Caching\Storages\Journal|NULL
	 */
	private $journal;

	/**
	 * @var bool
	 */
	private $useLocks = TRUE;

	public function __construct(\Kdyby\Redis\RedisClient $client, ?\Nette\Caching\Storages\Journal $journal = NULL)
	{
		$this->client = $client;
		$this->journal = $journal;
	}

	public function disableLocking(): void
	{
		$this->useLocks = FALSE;
	}

	/**
	 * Read from cache.
	 *
	 * @param string $key
	 * @return mixed|NULL
	 */
	public function read(string $key)
	{
		$stored = $this->doRead($key);
		if (!$stored || !$this->verify($stored[0])) {
			return NULL;
		}

		return self::getUnserializedValue($stored);
	}

	/**
	 * Read multiple entries from cache (using mget)
	 *
	 * @param array<mixed> $keys
	 * @return array<mixed>
	 * @throws \RedisException
	 */
	public function multiRead(array $keys): array
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
	 * @param array<mixed> $meta
	 * @return bool
	 * @throws \RedisException
	 */
	protected function verify(array $meta): bool
	{
		do {
			if (!empty($meta[self::META_DELTA])) {
				$this->client->send('expire', [$this->formatEntryKey($meta[self::KEY]), $meta[self::META_DELTA]]);

			} elseif (!empty($meta[self::META_EXPIRE]) && $meta[self::META_EXPIRE] < \time()) {
				break;
			}

			if (!empty($meta[self::META_CALLBACKS]) && !Cache::checkCallbacks($meta[self::META_CALLBACKS])) {
				break;
			}

			if (!empty($meta[self::META_ITEMS])) {
				foreach ($meta[self::META_ITEMS] as $itemKey => $time) {
					$m = $this->readMeta($itemKey);
					$metaTime = $m[self::META_TIME] ?? NULL;
					if ($metaTime !== $time || ($m && !$this->verify($m))) {
						break 2;
					}
				}
			}

			return TRUE;
		} while (FALSE);

		$this->remove($meta[self::KEY]); // meta[handle] & meta[file] was added by readMetaAndLock()
		return FALSE;
	}

	public function lock(string $key): void
	{
		if ($this->useLocks) {
			$this->client->lock($this->formatEntryKey($key));
		}
	}

	/**
	 * @internal
	 * @param string $key
	 */
	public function unlock(string $key): void
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
	 * @param array<mixed> $dp
	 * @throws \Nette\InvalidStateException
	 * @throws \RedisException
	 */
	public function write(string $key, $data, array $dp): void
	{
		$meta = [
			self::META_TIME => \microtime(),
		];

		if (isset($dp[Cache::EXPIRATION])) {
			if (empty($dp[Cache::SLIDING])) {
				$meta[self::META_EXPIRE] = $dp[Cache::EXPIRATION] + \time(); // absolute time

			} else {
				$meta[self::META_DELTA] = (int) $dp[Cache::EXPIRATION]; // sliding time
			}
		}

		if (isset($dp[Cache::ITEMS])) {
			foreach ((array) $dp[Cache::ITEMS] as $itemName) {
				$m = $this->readMeta($itemName);
				$meta[self::META_ITEMS][$itemName] = $m[self::META_TIME] ?? NULL; // may be NULL
				unset($m);
			}
		}

		if (isset($dp[Cache::CALLBACKS])) {
			$meta[self::META_CALLBACKS] = $dp[Cache::CALLBACKS];
		}

		$cacheKey = $this->formatEntryKey($key);

		if (isset($dp[Cache::TAGS]) || isset($dp[Cache::PRIORITY])) {
			if ($this->journal === NULL) {
				throw new \Nette\InvalidStateException('CacheJournal has not been provided.');
			}
			$this->journal->write($cacheKey, $dp);
		}

		if (!\is_string($data)) {
			$data = \serialize($data);
			$meta[self::META_SERIALIZED] = TRUE;
		}

		$store = \json_encode($meta) . self::NAMESPACE_SEPARATOR . $data;

		try {
			if (isset($dp[Cache::EXPIRATION])) {
				$this->client->send('setEX', [$cacheKey, $dp[Cache::EXPIRATION], $store]);

			} else {
				$this->client->send('set', [$cacheKey, $store]);
			}

			$this->unlock($key);

		} catch (\Kdyby\Redis\Exception\RedisClientException $e) {
			$this->remove($key);
			throw new \Nette\InvalidStateException($e->getMessage(), $e->getCode(), $e);
		}
	}

	/**
	 * Removes item from the cache.
	 *
	 * @param string $key
	 */
	public function remove(string $key): void
	{
		$this->client->send('del', [$this->formatEntryKey($key)]);
	}

	/**
	 * Removes items from the cache by conditions & garbage collector.
	 *
	 * @param array<mixed> $conds
	 * @throws \RedisException
	 */
	public function clean(array $conds): void
	{
		// cleaning using file iterator
		if (!empty($conds[Cache::ALL])) {
			$keys = $this->client->send('keys', [self::NS_NETTE . ':*']);
			if ($keys) {
				$this->client->send('del', $keys);
			}

			if ($this->journal) {
				$this->journal->clean($conds);
			}
			return;
		}

		// cleaning using journal
		if ($this->journal) {
			$keys = $this->journal->clean($conds);
			if ($keys) {
				$this->client->send('del', $keys);
			}
		}
	}

	protected function formatEntryKey(string $key): string
	{
		return self::NS_NETTE . ':' . \str_replace(self::NAMESPACE_SEPARATOR, ':', $key);
	}

	/**
	 * @param string $key
	 * @return array<mixed>|null
	 * @throws \RedisException
	 */
	protected function readMeta(string $key): ?array
	{
		$stored = $this->doRead($key);

		if (!$stored) {
			return NULL;
		}

		return $stored[0];
	}

	/**
	 * @param string $key
	 * @return array<mixed>|null
	 * @throws \RedisException
	 */
	private function doRead(string $key): ?array
	{
		$stored = $this->client->send('get', [$this->formatEntryKey($key)]);
		if (!$stored) {
			return NULL;
		}

		return self::processStoredValue($key, $stored);
	}

	/**
	 * @param array<mixed> $keys
	 * @return array<mixed>
	 * @throws \RedisException
	 */
	private function doMultiRead(array $keys): array
	{
		$formatedKeys = \array_map([$this, 'formatEntryKey'], $keys);

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
	 * @return array<mixed>
	 */
	private static function processStoredValue(string $key, string $storedValue): array
	{
		[$meta, $data] = \explode(self::NAMESPACE_SEPARATOR, $storedValue, 2) + [NULL, NULL];
		return [[self::KEY => $key] + \json_decode($meta, TRUE), $data];
	}

	/**
	 * @param mixed $stored
	 * @return mixed
	 */
	private static function getUnserializedValue($stored)
	{
		if (empty($stored[0][self::META_SERIALIZED])) {
			return $stored[1];

		}

		return @\unserialize($stored[1]); // intentionally @
	}

}
