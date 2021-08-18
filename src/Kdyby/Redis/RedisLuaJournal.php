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
class RedisLuaJournal extends \Kdyby\Redis\RedisJournal
{

	private const DELETE_ENTRIES = 'delete-entries';

	/**
	 * @var array
	 */
	private $script = [];

	/**
	 * Cleans entries from journal.
	 *
	 * @param array<mixed> $conds
	 * @param \Nette\Caching\IStorage $storage
	 * @throws \Kdyby\Redis\Exception\RedisClientException
	 * @throws \Nette\Utils\JsonException
	 * @return array<mixed>|NULL of removed items or NULL when performing a full cleanup
	 */
	public function clean(array $conds, ?\Nette\Caching\IStorage $storage = NULL): ?array
	{
		if ($storage instanceof \Kdyby\Redis\RedisStorage) {
			$conds[self::DELETE_ENTRIES] = '1';
		}

		$args = self::flattenDp($conds);

		$result = $this->client->evalScript($this->getScript('clean'), [], [$args]);
		if (!\is_array($result) && $result !== TRUE) {
			throw new \Kdyby\Redis\Exception\RedisClientException('Failed to successfully execute lua script journal.clean(): ' . $this->client->getDriver()->getLastError());
		}

		if ($storage instanceof \Kdyby\Redis\RedisStorage) {
			return [];
		}

		return \is_array($result) ? \array_unique($result) : NULL;
	}

	/**
	 * @param array<mixed> $array
	 * @return string
	 * @throws \Nette\Utils\JsonException
	 */
	private static function flattenDp(array $array): string
	{
		if (isset($array[\Nette\Caching\Cache::TAGS])) {
			$array[\Nette\Caching\Cache::TAGS] = (array) $array[\Nette\Caching\Cache::TAGS];
		}
		$filtered = \array_intersect_key($array, \array_flip([
			\Nette\Caching\Cache::TAGS,
			\Nette\Caching\Cache::PRIORITY,
			\Nette\Caching\Cache::ALL,
			self::DELETE_ENTRIES,
		]));

		return \Nette\Utils\Json::encode($filtered);
	}

	/**
	 * @param string $name
	 * @return mixed
	 */
	private function getScript(string $name)
	{
		if (isset($this->script[$name])) {
			return $this->script[$name];
		}

		$script = \file_get_contents(__DIR__ . '/scripts/common.lua');
		$script .= \file_get_contents(__DIR__ . '/scripts/journal.' . $name . '.lua');

		return $this->script[$name] = $script;
	}

}
