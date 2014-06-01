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
class RedisLuaJournal extends Nette\Object implements Nette\Caching\Storages\IJournal
{

	/** @internal cache structure */
	const NS_NETTE = 'Nette.Journal';

	/** dependency */
	const PRIORITY = 'priority',
		TAGS = 'tags',
		KEYS = 'keys';

	const DELETE_ENTRIES = 'delete-entries';

	/**
	 * @var RedisClient
	 */
	private $client;

	/**
	 * @var array
	 */
	private $script = array();



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
		$args = self::flattenDp($dp);
		$key = str_replace(array('\\', ':', Cache::NAMESPACE_SEPARATOR), array('\\\\', '\\:', ':'), $key);

		$result = $this->client->evalScript($this->getScript('write'), array($key), array($args));
		if ($result !== TRUE) {
			throw new RedisClientException("Failed to successfully execute lua script journal.write($key): " . $this->client->getDriver()->getLastError());
		}
	}



	/**
	 * Cleans entries from journal.
	 *
	 * @param  array $conds
	 * @param \Nette\Caching\IStorage $storage
	 * @throws RedisClientException
	 * @return array of removed items or NULL when performing a full cleanup
	 */
	public function clean(array $conds, Nette\Caching\IStorage $storage = NULL)
	{
		if ($storage instanceof RedisStorage) {
			$conds[self::DELETE_ENTRIES] = '1';
		}

		$args = self::flattenDp($conds);

		$result = $this->client->evalScript($this->getScript('clean'), array(), array($args));
		if (!is_array($result) && $result !== TRUE) {
			throw new RedisClientException("Failed to successfully execute lua script journal.clean(): " . $this->client->getDriver()->getLastError());
		}

		if ($storage instanceof RedisStorage) {
			return array();
		}

		$unescape = function($key) {
			return preg_replace(array("~(?<!\\\\):~", "~\\\\:~", "~\\\\\\\\~"), array(Cache::NAMESPACE_SEPARATOR, ":", "\\"), $key);
		};
		return is_array($result) ? array_map($unescape, array_unique($result)) : NULL;
	}



	private static function flattenDp($array)
	{
		if (isset($array[Cache::TAGS])) {
			$array[Cache::TAGS] = (array) $array[Cache::TAGS];
		}
		$filtered = array_intersect_key($array, array_flip(array(Cache::TAGS, Cache::PRIORITY, Cache::ALL, self::DELETE_ENTRIES)));

		return Nette\Utils\Json::encode($filtered);
	}



	private function getScript($name)
	{
		if (isset($this->script[$name])) {
			return $this->script[$name];
		}

		$script = file_get_contents(__DIR__ . '/scripts/common.lua');
		$script .= file_get_contents(__DIR__ . '/scripts/journal.' . $name . '.lua');

		return $this->script[$name] = $script;
	}

}
