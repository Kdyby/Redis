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



/**
 * Redis journal for tags and priorities of cached values.
 *
 * @author Filip Procházka <filip@prochazka.su>
 */
class RedisJournal extends Nette\Object implements Nette\Caching\Storages\IJournal
{

	/** @internal cache structure */
	const NS_NETTE = 'Nette.Journal';

	/** dependency */
	const PRIORITY = 'priority',
		TAGS = 'tags',
		KEYS = 'keys';

	/** @internal batch delete size */
	const BATCH_SIZE = 8000;

	/**
	 * @var RedisClient
	 */
	protected $client;



	/**
	 * @param RedisClient $client
	 */
	public function __construct(RedisClient $client)
	{
		$this->client = $client;
	}



	/**
	 * Writes entry information into the journal.
	 *
	 * @param  string $key
	 * @param  array  $dp
	 *
	 * @return void
	 */
	public function write($key, array $dp)
	{
		$this->cleanEntry($key);

		$this->client->multi();

		// add entry to each tag & tag to entry
		$tags = empty($dp[Cache::TAGS]) ? array() : (array)$dp[Cache::TAGS];
		foreach (array_unique($tags) as $tag) {
			$this->client->sAdd($this->formatKey($tag, self::KEYS), $key);
			$this->client->sAdd($this->formatKey($key, self::TAGS), $tag);
		}

		if (isset($dp[Cache::PRIORITY])) {
			$this->client->zAdd($this->formatKey(self::PRIORITY), $dp[Cache::PRIORITY], $key);
		}

		$this->client->exec();
	}



	/**
	 * Deletes all keys from associated tags and all priorities
	 *
	 * @todo optimize
	 * @param string $keys
	 */
	private function cleanEntry($keys)
	{
		foreach (is_array($keys) ? $keys : array($keys) as $key) {
			$entries = $this->entryTags($key);

			$this->client->multi();
			foreach ($entries as $tag) {
				$this->client->sRem($this->formatKey($tag, self::KEYS), $key);
			}

			// drop tags of entry and priority, in case there are some
			$this->client->del($this->formatKey($key, self::TAGS), $this->formatKey($key, self::PRIORITY));
			$this->client->zRem($this->formatKey(self::PRIORITY), $key);

			$this->client->exec();
		}
	}



	/**
	 * Cleans entries from journal.
	 *
	 * @param  array  $conds
	 * @param \Nette\Caching\IStorage $storage
	 *
	 * @return array of removed items or NULL when performing a full cleanup
	 */
	public function clean(array $conds, Nette\Caching\IStorage $storage = NULL)
	{
		if (!empty($conds[Cache::ALL])) {
			$all = $this->client->keys(self::NS_NETTE . ':*');
			if ($storage instanceof RedisStorage) {
				$all = array_merge($all, $this->client->keys(RedisStorage::NS_NETTE . ':*'));
			}

			call_user_func_array(array($this->client, 'del'), $all);
			return NULL;
		}

		$entries = array();
		if (!empty($conds[Cache::TAGS])) {
			$removingTagKeys = array();
			foreach ((array)$conds[Cache::TAGS] as $tag) {
				$found = $this->tagEntries($tag);
				$removingTagKeys[] = $this->formatKey($tag, self::KEYS);
				$entries = array_merge($entries, $found);
			}
			if ($removingTagKeys) {
				call_user_func_array(array($this->client, 'del'), $removingTagKeys);
			}
		}

		if (isset($conds[Cache::PRIORITY])) {
			$found = $this->priorityEntries($conds[Cache::PRIORITY]);
			call_user_func_array(array($this->client, 'zRemRangeByScore'), array($this->formatKey(self::PRIORITY), 0, (int)$conds[Cache::PRIORITY]));
			$entries = array_merge($entries, $found);
		}

		$entries = array_unique($entries);

		$removingKeys = array();
		$removingKeyTags = array();
		$removingKeyPriorities = array();
		foreach ($entries as $key) {
			if ($storage instanceof RedisStorage) {
				$removingKeys[] = $key;
			}
			$removingKeyTags[] = $this->formatKey($key, self::TAGS);
			$removingKeyPriorities[] = $this->formatKey($key, self::PRIORITY);
			if (count($removingKeyTags) >= self::BATCH_SIZE) {
				$this->cleanBatchData($removingKeys, $removingKeyPriorities, $removingKeyTags, $entries);
				$removingKeys = array();
				$removingKeyTags = array();
				$removingKeyPriorities = array();
			}
		}

		$this->cleanBatchData($removingKeys, $removingKeyPriorities, $removingKeyTags, $entries);

		return $storage instanceof RedisStorage ? array() : $entries;
	}



	private function cleanBatchData(array $removingKeys, array $removingKeyPriorities, array $removingKeyTags, array $keys)
	{
		if ($removingKeyTags) {
			if ($keys) {
				$affectedTags = call_user_func_array(array($this->client, 'sunion'), array($removingKeyTags));
				foreach ($affectedTags as $tag) {
					if ($tag) {
						call_user_func_array(array($this->client, 'sRem'), array_merge(array($this->formatKey($tag, self::KEYS)), $keys));
					}
				}
			}
			call_user_func_array(array($this->client, 'del'), $removingKeyTags);
		}
		if ($removingKeyPriorities) {
			call_user_func_array(array($this->client, 'del'), $removingKeyPriorities);
		}
		if ($removingKeys) {
			call_user_func_array(array($this->client, 'del'), $removingKeys);
		}
	}



	/**
	 * @param int $priority
	 * @return array
	 */
	private function priorityEntries($priority)
	{
		return $this->client->zRangeByScore($this->formatKey(self::PRIORITY), 0, (int)$priority) ?: array();
	}



	/**
	 * @param string $key
	 *
	 * @return array
	 */
	private function entryTags($key)
	{
		return $this->client->sMembers($this->formatKey($key, self::TAGS)) ? : array();
	}



	/**
	 * @param string $tag
	 *
	 * @return array
	 */
	private function tagEntries($tag)
	{
		return $this->client->sMembers($this->formatKey($tag, self::KEYS)) ? : array();
	}



	/**
	 * @param string $key
	 * @param string $suffix
	 *
	 * @return string
	 */
	protected function formatKey($key, $suffix = NULL)
	{
		return self::NS_NETTE . ':' . $key . ($suffix ? ':' . $suffix : NULL);
	}

}
