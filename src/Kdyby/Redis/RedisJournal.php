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

/**
 * Redis journal for tags and priorities of cached values.
 */
class RedisJournal implements \Nette\Caching\Storages\Journal
{

	use \Nette\SmartObject;

	/**
	 * cache structure
	 *
	 * @internal
	 */
	private const NS_NETTE = 'Nette.Journal';

	/** dependency */
	private const PRIORITY = 'priority';
	private const TAGS = 'tags';
	private const KEYS = 'keys';

	/**
	 * @var \Kdyby\Redis\RedisClient
	 */
	protected $client;

	public function __construct(\Kdyby\Redis\RedisClient $client)
	{
		$this->client = $client;
	}

	/**
	 * Writes entry information into the journal.
	 *
	 * @param string $key
	 * @param array<mixed> $dp
	 * @return void
	 * @throws \Exception
	 */
	public function write(string $key, array $dp): void
	{
		$this->cleanEntry($key);

		$this->client->multi();

		// add entry to each tag & tag to entry
		$tags = empty($dp[\Nette\Caching\Cache::TAGS]) ? [] : (array) $dp[\Nette\Caching\Cache::TAGS];
		foreach (\array_unique($tags) as $tag) {
			$this->client->sAdd($this->formatKey($tag, self::KEYS), $key);
			$this->client->sAdd($this->formatKey($key, self::TAGS), $tag);
		}

		if (isset($dp[\Nette\Caching\Cache::PRIORITY])) {
			$this->client->zAdd($this->formatKey(self::PRIORITY), $dp[\Nette\Caching\Cache::PRIORITY], $key);
		}

		$this->client->exec();
	}

	/**
	 * Deletes all keys from associated tags and all priorities
	 *
	 * @todo optimize
	 * @param array<mixed>|string $keys
	 * @throws \Exception
	 */
	private function cleanEntry($keys): void
	{
		foreach (\is_array($keys) ? $keys : [$keys] as $key) {
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
	 * @param  array<mixed> $conds
	 * @return array<mixed> of removed items or NULL when performing a full cleanup
	 * @throws \Exception
	 */
	public function clean(array $conds): ?array
	{
		if (!empty($conds[\Nette\Caching\Cache::ALL])) {
			$all = $this->client->keys(self::NS_NETTE . ':*');

			$this->client->multi();
			\call_user_func_array([$this->client, 'del'], $all);
			$this->client->exec();
			return NULL;
		}

		$entries = [];
		if (!empty($conds[\Nette\Caching\Cache::TAGS])) {
			foreach ((array) $conds[\Nette\Caching\Cache::TAGS] as $tag) {
				$this->cleanEntry($found = $this->tagEntries($tag));
				$entries = \array_merge($entries, $found);
			}
		}

		if (isset($conds[\Nette\Caching\Cache::PRIORITY])) {
			$this->cleanEntry($found = $this->priorityEntries($conds[\Nette\Caching\Cache::PRIORITY]));
			$entries = \array_merge($entries, $found);
		}

		return \array_unique($entries);
	}

	/**
	 * @param int $priority
	 * @return array<mixed>
	 */
	private function priorityEntries(int $priority): array
	{
		return $this->client->zRangeByScore($this->formatKey(self::PRIORITY), 0, (int) $priority) ?: [];
	}

	/**
	 * @param string $key
	 * @return array<mixed>
	 */
	private function entryTags(string $key): array
	{
		return $this->client->sMembers($this->formatKey($key, self::TAGS)) ? : [];
	}

	/**
	 * @param string $tag
	 * @return array<mixed>
	 */
	private function tagEntries(string $tag): array
	{
		return $this->client->sMembers($this->formatKey($tag, self::KEYS)) ? : [];
	}

	protected function formatKey(string $key, ?string $suffix = NULL): string
	{
		return self::NS_NETTE . ':' . $key . ($suffix ? ':' . $suffix : NULL);
	}

}
