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
use Nette\Utils\Callback;
use Tracy\Debugger;



/**
 * <code>
 * $client = new Kdyby\Redis\RedisClient();
 * $client->set('key', 'value');
 * $value = $client->get('key');
 * </code>
 *
 * Full list of commands you can see on http://redis.io/commands
 *
 * @method int          append(string $key, string $value) Append a value to a key, return size of value after the append
 * @method bool         auth(string $password) Authenticate to the server
 * @method bool         bgRewriteAof() Asynchronously rewrite the append-only file
 * @method bool         bgSave() Asynchronously save the dataset to disk
 * @method int          bitCount(string $key, int $start , int $end) Count set bits in a string
 * @method int          bitOp(string $operation, string $destKey, string $key1, string $key2 = NULL) Perform bitwise operations between strings, return the size of the string stored in the destination key
 * @method array        blPop(string $key1, string $key2 = NULL, int $timeout = NULL) Remove and get the first element in a list, or block until one is available
 * @method array        brPop(string $key1, string $key2 = NULL, int $timeout = NULL) Remove and get the last element in a list, or block until one is available
 * @method string       brPopLPush(string $source, string $destination, int $timeout) Pop a value from a list, push it to another list and return it; or block until one is available
 * @method array|bool   config(string $operation, string $key, mixed $value = NULL) Get os Redis the Redis server configuration parameters
 * @method int          dbSize() Return the number of keys in the selected database
 * @method int          decr(string $key) Decrement the integer value of a key by one, return new value
 * @method int          decrBy(string $key, int $decrement) Decrement the integer value of a key by the given number, return new value
 * @method int          del(string $key1, string $key2 = NULL) Delete a key
 * @method void         discard() Discard all commands issued after MULTI
 * @method string|bool  dump(string $key) Return a serialized version of the value stored at the specified key.
 * @method string       echo(string $message) Echo the given string, returns the same message
 * @method bool         exists(string $key) Determine if a key exists
 * @method bool         expire(string $key, int $seconds) Set a key's time to live in seconds
 * @method bool         expireAt(string $key, int $timestamp) Set the expiration for a key as a UNIX timestamp
 * @method bool         flushAll() Remove all keys from all databases
 * @method bool         flushDb() Remove all keys from the current database
 * @method string|bool  get(string $key) Get the value of a key. If key didn't exist, FALSE is returned
 * @method int          getBit(string $key, int $offset = 0) Returns the bit value at offset in the string value stored at key
 * @method string       getRange(string $key, int $start, int $end) Get a substring of the string stored at a key
 * @method string       getSet(string $key, string $value) Set the string value of a key and return its old value, return previous value located at this key
 * @method int          hDel(string $key, string $field1, string $field2 = NULL) Delete one or more hash fields
 * @method bool         hExists(string $key, string $field) Determine if a hash field exists
 * @method string       hGet(string $key, string $field) Get the value of a hash field
 * @method array        hGetAll(string $key) Get all the fields and values in a hash
 * @method int          hIncrBy(string $key, string $field, int $increment) Increment the integer value of a hash field by the given number. Return the new value
 * @method float        hIncrByFloat(string $key, string $field, float $increment) Increment the float value of a hash field by the given amount. Return the new value
 * @method array        hKeys(string $key) Get all the fields in a hash
 * @method int          hLen(string $key) Get the number of fields in a hash
 * @method array        hmGet(string $key, array $fields) Get the values of all the given hash fields
 * @method bool         hmSet(string $key, array $values) Set multiple hash fields to multiple values
 * @method int          hSet(string $key, string $field, string $value) Set the string value of a hash field. Return 1 if value didn't exist and was added, 0 if the value was replaced
 * @method int          hSetNX(string $key, string $field, string $value) Set the value of a hash field, only if the field does not exist. Return TRUE if field was set, FALSE if it was already present.
 * @method array        hVals(string $key) Get all the values in a hash
 * @method int          incr(string $key) Increment the integer value of a key by one, return new value
 * @method int          incrBy(string $key, int $increment) Increment the integer value of a key by the given amount, return new value
 * @method float        incrByFloat(string $key, float $increment) Increment the float value of a key by the given amount, return new value
 * @method array        keys(string $pattern) Find all keys matching the given pattern
 * @method int          lastSave() Get the UNIX time stamp of the last successful save to disk
 * @method string|bool  lIndex(string $key, int $index) Get an element from a list by its index
 * @method int          lInsert(string $key, string $position, int $pivot, string $value) Insert an element before or after another element in a list. Return the number of the elements in the list, -1 if the pivot didn't exists.
 * @method int          lLen(string $key) Get the length of a list
 * @method string|bool  lPop(string $key) Remove and get the first element in a list. Return false if the list was empty.
 * @method int          lPush(string $key, string $value1, string $value2 = NULL) Prepend one or multiple values to a list. Return the new length of the list
 * @method int          lPushX(string $key, string $value) Prepend a value to a list, only if the list exists. Return the new length of the list
 * @method array        lRange(string $key, int $start, int $stop) Get a range of elements from a list
 * @method int          lRem(string $key, string $value, int $count = 0) Remove elements from a list. Return the number of elements to remove
 * @method bool         lSet(string $key, int $index, string $value) Set the value of an element in a list by its index
 * @method bool         lTrim(string $key, int $start, int $stop) Trim a list to the specified range
 * @method array        mGet(array $keys) Get the values of all the given keys
 * @method void         migrate(string $host, int $port, string $key, string $destinationDb, int $timeout) Atomically transfer a key from a Redis instance to another one.
 * @method bool         move(string $key, int $dbIndex) Move a key to another database
 * @method bool         mSet(array $values) Set multiple keys to multiple values
 * @method bool         mSetNX(array $values) Set multiple keys to multiple values, only if none of the keys exist
 * @method mixed        object(string $subCommand, string $key) Inspect the internals of Redis objects
 * @method bool         persist(string $key) Remove the expiration from a key
 * @method bool         pExpire(string $key, int $milliseconds) Set a key's time to live in milliseconds
 * @method bool         pExpireAt(string $key, int $timestampMs) Set the expiration for a key as a UNIX timestamp specified in milliseconds
 * @method string       ping() Ping the server, returns "+PONG" on success
 * @method bool         pSetEX(string $key, int $milliseconds, string $value) Set the value and expiration in milliseconds of a key
 * @method void         pSubscribe(array $patterns, callable $callback) Listen for messages published to channels matching the given patterns
 * @method int          pTTL(string $key) Get the time to live for a key in milliseconds. If the key has no ttl, -1 will be returned, and -2 if the key doesn't exist.
 * @method int          publish(string $channel, string $message) Post a message to a channel
 * @method mixed        pubsub(string $subCommand, mixed $options = NULL) A command allowing you to get information on the Redis pub/sub system
 * @method string       randomKey() Return a random key from the keyspace
 * @method bool         rename(string $key, string $newKey) Rename a key
 * @method bool         renameNX(string $key, string $newKey) Rename a key, only if the new key does not exist
 * @method bool         resetStat() Reset the stats returned by INFO
 * @method bool         restore(string $key, int $ttl, string $serializedValue) Create a key using the provided serialized value, previously obtained using DUMP.
 * @method string|bool  rPop(string $key) Remove and get the last element in a list
 * @method string       rPopLPush(string $source, string $destination) Remove the last element in a list, append it to another list and return it
 * @method int          rPush(string $key, string $value1, string $value2 = NULL) Append one or multiple values to a list. Return the new length of the list
 * @method int          rPushX(string $key, string $value) Append a value to a list, only if the list exists. Return the new length of the list
 * @method int          sAdd(string $key, string $member1, string $member2 = NULL) Add one or more members to a set. Return the number of elements added to the set
 * @method bool         save() Synchronously save the dataset to disk
 * @method int          sCard(string $key) Get the number of members in a set
 * @method array        sDiff(string $key1, string $key2 = NULL) Subtract multiple sets
 * @method int          sDiffStore(string $destination, string $key1, string $key2 = NULL) Subtract multiple sets and store the resulting set in a key. Return the cardinality of the resulting set
 * @method bool         select(int $index) Change the selected database for the current connection
 * @method bool         set(string $key, string $value) Set the string value of a key
 * @method int          setBit(string $key, int $offset, int $value) Sets or clears the bit at offset in the string value stored at key, return 0 or 1 - the value of the bit before it was set
 * @method bool         setEX(string $key, int $seconds, string $value) Set the value and expiration of a key
 * @method bool         setNX(string $key, string $value) Set the value of a key, only if the key does not exist
 * @method int          setRange(string $key, int $offset, string $value) Overwrite part of a string at key starting at the specified offset, return the length of the string after it was modified
 * @method array        sInter(string $key1, string $key2 = NULL) Intersect multiple sets
 * @method int          sInterStore(string $destination, string $key1, string $key2 = NULL) Intersect multiple sets and store the resulting set in a key. Return the cardinality of the resulting set,
 * @method bool         sIsMember(string $key, string $member) Determine if a given value is a member of a set
 * @method bool         slaveOf(string $host, int $port) Make the server a slave of another instance, or promote it as master
 * @method mixed        slowLog(string $subCommand, mixed $arg = NULL) Manages the Redis slow queries log
 * @method array        sMembers(string $key) Get all the members in a set
 * @method bool         sMove(string $source, string $destination, string $member) Move a member from one set to another
 * @method array|int    sort(string $key, array $options) Sort the elements in a list, set or sorted set
 * @method string|bool  sPop(string $key) Remove and return a random member from a set
 * @method string|array|bool sRandMember(string $key) Get a random member from a set
 * @method int          sRem(string $key, string $member1, string $member2 = NULL) Remove one or more members from a set. Return the number of elements removed from the set
 * @method int          strLen(string $key) Get the length of the value stored in a key
 * @method void         subscribe(array $channels, callable $callback) Listen for messages published to the given channels
 * @method array        sUnion(string $key1, string $key2 = NULL) Add multiple sets
 * @method int          sUnionStore(string $destination, string $key1, string $key2 = NULL) Add multiple sets and store the resulting set in a key. Return the cardinality of the resulting set
 * @method int          time() Return the current server time
 * @method int          ttl(string $key) Get the time to live for a key. If the key has no ttl, -1 will be returned, and -2 if the key doesn't exist.
 * @method string       type(string $key) Determine the type stored at key
 * @method void         unwatch() Forget about all watched keys
 * @method void         watch(string $key1, string $key2 = NULL) Watch the given keys to determine execution of the MULTI/EXEC block
 * @method int          zAdd(string $key, float $score1, string $member1, float $score2 = NULL, string $member2 = NULL) Add one or more members to a sorted set, or update its score if it already exists. Return the number of added elements
 * @method int          zCard(string $key) Get the number of members in a sorted set
 * @method int          zCount(string $key, int $min, int $max) Count the members in a sorted set with scores within the given values
 * @method float        zIncrBy(string $key, float $increment, string $member) Increment the score of a member in a sorted set. Return the new value
 * @method int          zInter(string $destination, array $zsetKeys, array $weights = array(), string $aggregateFunction = NULL) Intersect multiple sorted sets and store the resulting sorted set in a new key. Return the number of values in the new sorted set
 * @method array        zRange(string $key, int $start, int $stop, bool $withScores = FALSE) Return a range of members in a sorted set, by index
 * @method array        zRangeByScore(string $key, float $min, float $max, array $options = array()) Return a range of members in a sorted set, by score
 * @method int          zRank(string $key, string $member) Determine the index of a member in a sorted set
 * @method int          zRem(string $key, string $member1, string $member2 = NULL) Remove one or more members from a sorted set. Return the number of removed members
 * @method int          zRemRangeByRank(string $key, int $start, int $stop) Remove all members in a sorted set within the given indexes. Return the number of values deleted from the set
 * @method int          zRemRangeByScore(string $key, float $min, float $max) Remove all members in a sorted set within the given scores. Return the number of values deleted from the set
 * @method array        zRevRange(string $key, int $start, int $stop, bool $withScores = FALSE) Return a range of members in a sorted set, by index, with scores ordered from high to low
 * @method array        zRevRangeByScore(string $key, float $max, float $min, array $options = array()) Return a range of members in a sorted set, by score, with scores ordered from high to low
 * @method int          zRevRank(string $key, string $member) Determine the index of a member in a sorted set, with scores ordered from high to low
 * @method float        zScore(string $key, string $member) Get the score associated with the given member in a sorted set
 * @method int          zUnion(string $destination, array $setKeys, array $weights = array(), string $aggregateFunction = NULL) Add multiple sorted sets and store the resulting sorted set in a new key</ul>
 *
 * @author Filip Procházka <filip@prochazka.su>
 */
class RedisClient implements \ArrayAccess
{
	use Nette\SmartObject;

	/** @deprecated */
	const WITH_SCORES = 'WITHSCORES';

	const DEFAULT_PORT = 6379;

	/**
	 * @var Driver\PhpRedisDriver
	 */
	private $driver;

	/**
	 * @var bool
	 */
	private $isConnected = FALSE;

	/**
	 * @var int
	 */
	private $connectionAttempts = 1;

	/**
	 * @var Diagnostics\Panel
	 */
	private $panel;

	/**
	 * @var string
	 */
	private $host;

	/**
	 * @var string
	 */
	private $port;

	/**
	 * @var int
	 */
	private $timeout;

	/**
	 * @var int
	 */
	private $database;

	/**
	 * @var ExclusiveLock
	 */
	private $lock;

	/**
	 * @var string
	 */
	private $auth;

	/**
	 * @var bool
	 */
	private $persistent;

	/**
	 * @var array
	 */
	private static $exceptionCmd = [
		'evalsha' => 0
	];



	/**
	 * @param string $host
	 * @param int $port
	 * @param int $database
	 * @param int $timeout
	 * @param string $auth
	 * @param bool $persistent
	 * @throws MissingExtensionException
	 */
	public function __construct($host = '127.0.0.1', $port = NULL, $database = 0, $timeout = 10, $auth = NULL, $persistent = FALSE)
	{
		$this->host = $host;
		$this->port = $port;
		$this->database = $database;
		$this->timeout = $timeout;
		$this->auth = $auth;
		$this->persistent = $persistent;
	}



	/**
	 * Close the connection
	 */
	public function __destruct()
	{
		$this->close();
	}



	/**
	 * @return \Kdyby\Redis\IRedisDriver
	 */
	public function getDriver()
	{
		$this->connect();
		return $this->driver;
	}



	/**
	 * Returns database index
	 *
	 * @return int
	 */
	public function getDatabase()
	{
		return $this->database;
	}



	public function connect()
	{
		if (!extension_loaded('redis')) {
			throw new MissingExtensionException("Please install and enable the redis extension. \nhttps://github.com/nicolasff/phpredis/");
		}

		if (!$this->driver) {
			$this->driver = new Driver\PhpRedisDriver();
		}

		if ($this->driver->isConnected()) {
			return;
		}

		$remaining = $this->connectionAttempts;
		$errors = [];

		do {
			try {
				if ($this->persistent) {
					$isConnected = $this->driver->pconnect($this->host, $this->port, $this->timeout);

				} else {
					$isConnected = $this->driver->connect($this->host, $this->port, $this->timeout);
				}

				if (!$isConnected || !$this->driver->isConnected()) {
					$errorMessage = $this->driver->getLastError();
					$this->driver->clearLastError();
					throw new ConnectionException(sprintf('Connecting to %s failed: %s', $this->formatServerAddress(), $errorMessage));
				}

				if (isset($this->auth)) {
					$this->driver->auth($this->auth);
				}

				$this->driver->select($this->database);
				$this->isConnected = $this->driver->isConnected();

				return;

			} catch (\Exception $e) {
				$errors[] = $e;
				if (!Debugger::$productionMode) {
					break;
				}

				usleep(1000 * $this->connectionAttempts);

			} catch (\Throwable $e) {
				$errors[] = $e;
				break;
			}

		} while(--$remaining > 0);

		if ($e = reset($errors)) {
			$errorMessage = $this->driver->getLastError();
			$this->driver->clearLastError();
			throw new RedisClientException(sprintf('Client of %s; %s; %s', $this->formatServerAddress(), $e->getMessage(), $errorMessage), $e->getCode(), $e);
		}
	}



	/**
	 * Close the connection
	 */
	public function close()
	{
		try {
			if ($this->driver && $this->driver->isConnected()) {
				$this->getLock()->releaseAll();
				$this->driver->close();
			}

		} catch (\RedisException $e) {
			// doesn't matter, it's closing anyway
		}

		$this->isConnected = FALSE;
	}



	/**
	 * @param int $attempts
	 * @return RedisClient
	 */
	public function setConnectionAttempts($attempts)
	{
		$this->connectionAttempts = max((int) $attempts, 1);
		return $this;
	}



	/**
	 * @param Diagnostics\Panel $panel
	 */
	public function setPanel(Diagnostics\Panel $panel)
	{
		$this->panel = $panel;
	}



	/**
	 * @internal
	 * @param string $cmd
	 * @param array $args
	 *
	 * @throws \RedisException
	 * @throws RedisClientException
	 * @return mixed
	 */
	public function send($cmd, array $args = [])
	{
		if (!$this->isConnected) {
			$this->connect();
		}

		try {
			if ($this->panel) {
				$request = $args;
				array_unshift($request, $cmd);
				$this->panel->begin($request, $this->database);
			}

			$result = call_user_func_array([$this->driver, $cmd], $args);

			if ($result === TRUE && strtolower($cmd) === 'select') {
				$this->database = $args[0];
			}

			if ($result instanceof \Redis) {
				$result = strtolower($cmd) === 'multi' ? 'OK' : 'QUEUED';
			} elseif ($result === FALSE && ($msg = $this->driver->getLastError())) {
				if (!isset(self::$exceptionCmd[strtolower($cmd)])) {
					throw new \RedisException($msg);
				}

			} else {
				$this->driver->clearLastError();
			}

			if ($this->panel) {
				$this->panel->end();
			}

		} catch (\RedisException $e) {
			if ($this->panel) {
				$this->panel->error($e);
			}
			throw new RedisClientException(sprintf('Client of %s; %s', $this->formatServerAddress(), $e->getMessage()), $e->getCode(), $e);
		}

		return $result;
	}



	/**
	 * Get information and statistics about the server
	 *
	 * @param string $returnKey
	 * @return array|string
	 */
	public function info($returnKey = NULL)
	{
		$info = $this->send('info');

		$dbs = array_map(function ($db) {
			$info = array_map(function ($item) {
				return explode('=', $item, 2);
			}, explode(',', $db));

			$result = [];
			foreach ($info as $item) {
				$result[$item[0]] = $item[1];
			}

			return $result;

		}, preg_grep('~^keys=[0-9]+,~', $info));

		$info = $dbs + $info; // replace

		if ($returnKey !== NULL) {
			return array_key_exists($returnKey, $info) ? $info[$returnKey] : NULL;
		}

		return $info;
	}



	/**
	 * Mark the start of a transaction block
	 *
	 * @param callable $callback
	 * @throws \Exception|RedisClientException
	 * @throws \Exception
	 * @return mixed
	 */
	public function multi($callback = NULL)
	{
		$ok = $this->send('multi');

		if ($callback === NULL) {
			return $ok;
		}

		try {
			Callback::invoke($callback, $this);
			return $this->exec();

		} catch (RedisClientException $e) {
			throw $e;

		} catch (\Exception $e) {
			$this->send('discard');
			throw $e;

		} catch (\Throwable $e) {
			$this->send('discard');
			throw $e;
		}
	}



	/**
	 * @return array|bool|null|string
	 * @throws TransactionException
	 */
	public function exec()
	{
		$response = $this->send('exec');
		if ($response === NULL || $response === FALSE) {
			throw new TransactionException(sprintf('Client of %s; Transaction was aborted', $this->formatServerAddress()));
		}
		return $response;
	}



	/**
	 * Scan the keyspace for keys (Redis >= 2.8)
	 *
	 * @param null|int $iterator Iterator reference, initialized to NULL
	 * @param string $pattern Optional pattern to match
	 * @param int $count Count of keys per iteration (only a suggestion to Redis)
	 * @return array|bool array of keys of FALSE if there are no more keys
	 */
	public function scan(&$iterator, $pattern = NULL, $count = NULL)
	{
		return call_user_func([$this, 'send'], __FUNCTION__, [&$iterator, $pattern, $count]);
	}



	/**
	 * Scan a HASH value for members (Redis >= 2.8)
	 *
	 * @param string $key
	 * @param null|int $iterator Iterator reference, initialized to NULL
	 * @param string $pattern Optional pattern to match
	 * @param int $count Count of hash members per iteration (only a suggestion to Redis)
	 * @return array|bool list of members or FALSE  if there are no more members
	 */
	public function hScan($key, &$iterator, $pattern = NULL, $count = NULL)
	{
		return call_user_func([$this, 'send'], __FUNCTION__, [$key, &$iterator, $pattern, $count]);
	}



	/**
	 * Scan a set for members (Redis >= 2.8)
	 *
	 * @param string $key
	 * @param null|int $iterator Iterator reference, initialized to NULL
	 * @param string $pattern Optional pattern to match
	 * @param int $count Count of hash members per iteration (only a suggestion to Redis)
	 * @return array|bool list of members or FALSE  if there are no more members
	 */
	public function sScan($key, &$iterator, $pattern = NULL, $count = NULL)
	{
		return call_user_func([$this, 'send'], __FUNCTION__, [$key, &$iterator, $pattern, $count]);
	}



	/**
	 * Scan a sorted set for members (Redis >= 2.8)
	 *
	 * @param string $key
	 * @param null|int $iterator Iterator reference, initialized to NULL
	 * @param string $pattern Optional pattern to match
	 * @param int $count Count of hash members per iteration (only a suggestion to Redis)
	 * @return array|bool list of members or FALSE  if there are no more members
	 */
	public function zScan($key, &$iterator, $pattern = NULL, $count = NULL)
	{
		return call_user_func([$this, 'send'], __FUNCTION__, [$key, &$iterator, $pattern, $count]);
	}



	/**
	 * Execute a Lua script server side
	 */
	public function evalScript($script, array $keys = [], array $args = [])
	{
		$script = trim($script);

		$result = $this->send('evalsha', [sha1($script), array_merge($keys, $args), count($keys)]);
		if ($result === FALSE && stripos($this->driver->getLastError(), 'NOSCRIPT') !== FALSE) {
			$this->driver->clearLastError();
			$sha = $this->driver->script('load', $script);
			$result = $this->send('evalsha', [$sha, array_merge($keys, $args), count($keys)]);
		}

		return $result;
	}



	/**
	 * @return ExclusiveLock
	 */
	protected function getLock()
	{
		if ($this->lock === NULL) {
			$this->lock = new ExclusiveLock($this);
		}

		return $this->lock;
	}



	/**
	 * @param ExclusiveLock $lock
	 */
	public function setLock(ExclusiveLock $lock)
	{
		$lock->setClient($this);

		if ($this->lock) {
			$lock->duration = $this->lock->duration;
			$lock->acquireTimeout = $this->lock->acquireTimeout;
		}

		$this->lock = $lock;
	}



	/**
	 * @internal
	 * @param int $duration
	 * @param int|boolean $timeout
	 */
	public function setupLockDuration($duration, $timeout = FALSE)
	{
		$this->getLock()->duration = abs((int)$duration);
		$this->getLock()->acquireTimeout = abs((int) $timeout) ?: FALSE;
	}



	/**
	 * @param string $key
	 * @return bool
	 */
	public function lock($key)
	{
		return $this->getLock()->acquireLock($key);
	}



	/**
	 * @param string $key
	 */
	public function unlock($key)
	{
		$this->getLock()->release($key);
	}



	/**
	 * @internal
	 * @throws Nette\Utils\AssertionException
	 */
	public function assertVersion()
	{
		$version = $this->info('redis_version');
		if (version_compare($version, '2.6.0', '<')) {
			throw new Nette\Utils\AssertionException(
				"Minimum required version for this Redis client is 2.6.0, your version is $version. Please upgrade your software."
			);
		}
	}



	private function formatServerAddress()
	{
		if (stripos($this->host, '/') === 0) { // socket
			return $this->host;
		}

		return sprintf('tcp://%s:%s', $this->host, $this->port ?: self::DEFAULT_PORT);
	}



	/************************ syntax sugar ************************/



	/**
	 * Magic method for sending redis messages.
	 *
	 * <code>
	 * $redisClient->command($argument);
	 * </code>
	 *
	 * @param string $name
	 * @param array $args
	 *
	 * @throws RedisClientException
	 * @return array|null|string
	 */
	public function __call($name, $args)
	{
		return $this->send($name, $args);
	}



	/**
	 * Magic method as alias for get command.
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function &__get($name)
	{
		$res = $this->send('get', [$name]);
		return $res;
	}



	/**
	 * Magic method as alias for set command.
	 *
	 * @param string $name
	 * @param mixed $value
	 */
	public function __set($name, $value)
	{
		return $this->send('set', [$name, $value]);
	}



	/**
	 * Magic method as alias for exists command.
	 *
	 * @param string $name
	 *
	 * @return bool|void
	 */
	public function __isset($name)
	{
		return $this->send('exists', [$name]);
	}



	/**
	 * Magic method as alias for del command.
	 *
	 * @param string $name
	 */
	public function __unset($name)
	{
		return $this->send('del', [$name]);
	}



	/********************************* \ArrayAccess *********************************/



	/**
	 * ArrayAccess method as alias for exists command.
	 *
	 * @param mixed $offset
	 * @return bool
	 */
	public function offsetExists($offset)
	{
		return $this->__isset($offset);
	}



	/**
	 * ArrayAccess method as alias for get command.
	 *
	 * @param string $offset
	 * @return mixed
	 */
	public function offsetGet($offset)
	{
		return $this->__get($offset);
	}



	/**
	 * ArrayAccess method as alias for set command.
	 *
	 * @param string $offset
	 * @param mixed $value
	 */
	public function offsetSet($offset, $value)
	{
		$this->__set($offset, $value);
	}



	/**
	 * ArrayAccess method as alias for del command.
	 *
	 * @param string $offset
	 */
	public function offsetUnset($offset)
	{
		$this->__unset($offset);
	}

}
