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
use Nette;
use Nette\Diagnostics\Debugger;



/**
 * TinyRedisClient - the most lightweight Redis client written in PHP
 *
 * <code>
 * $client = new Kdyby\Extension\Redis\RedisClient();
 * $client->set('key', 'value');
 * $value = $client->get('key');
 * </code>
 *
 * Full list of commands you can see on http://redis.io/commands
 *
 * @method append(string $key, string $value) Append a value to a key
 * @method auth(string $password) Authenticate to the server
 * @method bgRewriteAof() Asynchronously rewrite the append-only file
 * @method bfSave() Asynchronously save the dataset to disk
 * @method bitCount(string $key, int $start , int $end) Count set bits in a string
 * @method bitOp(string $operation, string $destKey, $key1, $key2 = NULL) Perform bitwise operations between strings
 * @method blPop(string $key1, $key2 = NULL, $timeout = NULL) Remove and get the first element in a list, or block until one is available
 * @method brPop(string $key1, $key2 = NULL, $timeout = NULL) Remove and get the last element in a list, or block until one is available
 * @method brPopLPush(string $source, string $destination, int $timeout) Pop a value from a list, push it to another list and return it; or block until one is available
 * @method config_get(string $parameter) Get the value of a configuration parameter
 * @method config_set(string $parameter, string $value) Set a configuration parameter to the given value
 * @method config_resetStat(string $parameter, string $value) '>Reset the stats returned by INFO
 * @method dbSize() Return the number of keys in the selected database
 * @method debug_object(string $key) Get debugging information about a key
 * @method debug_segfault() Make the server crash
 * @method decr(string $key) Decrement the integer value of a key by one
 * @method decrBy(string $key, int $decrement) Decrement the integer value of a key by the given number
 * @method del(string $key1, string $key2 = NULL) Delete a key
 * @method discard() Discard all commands issued after MULTI
 * @method dump(string $key) Return a serialized version of the value stored at the specified key.
 * @method echo(string $message) Echo the given string
 * @method eval(string $script, int $numkeys, string $key1, string $key2 = NULL, string $arg1 = NULL, string $arg2 = NULL) Execute a Lua script server side
 * @method evalSha(string $sha1, int $numkeys, string $key1, string $key2 = NULL, string $arg1 = NULL, string $arg2 = NULL) Execute a Lua script server side
 * @method exists(string $key) Determine if a key exists
 * @method expire(string $key, int $seconds) Set a key's time to live in seconds
 * @method expireAt(string $key, int $timestamp) Set the expiration for a key as a UNIX timestamp
 * @method flushAll() Remove all keys from all databases
 * @method flushDb() Remove all keys from the current database
 * @method get(string $key) Get the value of a key
 * @method getBit(string $key, int $offset = 0) Returns the bit value at offset in the string value stored at key
 * @method getRange(string $key, int $start, int $end) Get a substring of the string stored at a key
 * @method getSet(string $key, string $value) Set the string value of a key and return its old value
 * @method hDel(string $key, string $field1, string $field2 = NULL) Delete one or more hash fields
 * @method hExists(string $key, string $field) Determine if a hash field exists
 * @method hGet(string $key, $field) Get the value of a hash field
 * @method hGetAll(string $key) Get all the fields and values in a hash
 * @method hIncrBy(string $key, string $field, int $increment) Increment the integer value of a hash field by the given number
 * @method hIncrByFloat(string $key, string $field, float $increment) Increment the float value of a hash field by the given amount
 * @method hKeys(string $key) Get all the fields in a hash
 * @method hLen(string $key) Get the number of fields in a hash
 * @method hmGet(string $key, string $field1, string $field2 = NULL) Get the values of all the given hash fields
 * @method hmSet(string $key, string $field1, string $value1, string $field2 = NULL, string $value2 = NULL) Set multiple hash fields to multiple values
 * @method hSet(string $key, string $field, string $value) Set the string value of a hash field
 * @method hSetNX(string $key, string $field, string $value) Set the value of a hash field, only if the field does not exist
 * @method hVals(string $key) Get all the values in a hash
 * @method incr(string $key) Increment the integer value of a key by one
 * @method incrBy(string $key, int $increment) Increment the integer value of a key by the given amount
 * @method incrByFloat(string $key, float $increment) Increment the float value of a key by the given amount
 * @method keys(string $pattern) Find all keys matching the given pattern
 * @method lastSave() Get the UNIX time stamp of the last successful save to disk
 * @method lIndex(string $key, int $index) Get an element from a list by its index
 * @method lInsert(string $key, int $position, string $value) Insert an element before or after another element in a list
 * @method lLen(string $key) Get the length of a list
 * @method lPop(string $key) Remove and get the first element in a list
 * @method lPush(string $key, string $value1, string $value2 = NULL) Prepend one or multiple values to a list
 * @method lPushX(string $key, string $value) Prepend a value to a list, only if the list exists
 * @method lRange(string $key, int $start, int $stop) Get a range of elements from a list
 * @method lRem(string $key, int $count = 0, string $value) Remove elements from a list
 * @method lSet(string $key, int $index, string $value) Set the value of an element in a list by its index
 * @method lTrim(string $key, int $start, int $stop) Trim a list to the specified range
 * @method mGet(string $key1, string $key2 = NULL) Get the values of all the given keys
 * @method migrate(string $host, int $port, string $key, string $destinationDb, int $timeout) Atomically transfer a key from a Redis instance to another one.
 * @method monitor() Listen for all requests received by the server in real time
 * @method move(string $key, string $db) Move a key to another database
 * @method mSet(string $key1, string $value1, string $key2 = NULL, string $value2 = NULL) Set multiple keys to multiple values
 * @method mSetNX(string $key1, string $value1, string $key2 = NULL, string $value2 = NULL) Set multiple keys to multiple values, only if none of the keys exist
 * @method object(string $subCommand, $arg1 = NULL, $arg2 = NULL) Inspect the internals of Redis objects
 * @method persist(string $key) Remove the expiration from a key
 * @method pExpire(string $key, int $milliseconds) Set a key's time to live in milliseconds
 * @method pExpireAt(string $key, int $timestampMs) Set the expiration for a key as a UNIX timestamp specified in milliseconds
 * @method ping() Ping the server
 * @method pSetEX(string $key, int $milliseconds, string $value) Set the value and expiration in milliseconds of a key
 * @method pSubscribe(string $pattern1, string $pattern2 = NULL) Listen for messages published to channels matching the given patterns
 * @method pTTL(string $key) Get the time to live for a key in milliseconds
 * @method publish(string $channel, string $message) Post a message to a channel
 * @method pUnsubscribe(string $pattern1, string $pattern2 = NULL) Stop listening for messages posted to channels matching the given patterns
 * @method quit() Close the connection
 * @method randomKey() Return a random key from the keyspace
 * @method rename(string $key, string $newKey) Rename a key
 * @method renameNX(string $key, string $newKey) Rename a key, only if the new key does not exist
 * @method restore(string $key, int $ttl, string $serializedValue) Create a key using the provided serialized value, previously obtained using DUMP.
 * @method rPop(string $key) Remove and get the last element in a list
 * @method rPopLPush(string $source, string $destination) Remove the last element in a list, append it to another list and return it
 * @method rPush(string $key, string $value1, string $value2 = NULL) Append one or multiple values to a list
 * @method rPushX(string $key, string $value) Append a value to a list, only if the list exists
 * @method sAdd(string $key, string $member1, string $member2 = NULL) Add one or more members to a set
 * @method save() Synchronously save the dataset to disk
 * @method sCard(string $key) Get the number of members in a set
 * @method script_exists(string $script1, string $script2 = NULL) Check existence of scripts in the script cache.
 * @method script_flush() Remove all the scripts from the script cache.
 * @method script_kill() Kill the script currently in execution.
 * @method script_load(string $script) Load the specified Lua script into the script cache.
 * @method sDiff(string $key1, string $key2 = NULL) Subtract multiple sets
 * @method sDiffStore(string $destination, string $key1, string $key2 = NULL) Subtract multiple sets and store the resulting set in a key
 * @method select(int $index) Change the selected database for the current connection
 * @method set(string $key, string $value) Set the string value of a key
 * @method setBit(string $key, int $offset, string $value) Sets or clears the bit at offset in the string value stored at key
 * @method setEX(string $key, int $seconds, string $value) Set the value and expiration of a key
 * @method setNX(string $key, string $value) Set the value of a key, only if the key does not exist
 * @method setRange(string $key, int $offset, string $value) Overwrite part of a string at key starting at the specified offset
 * @method shutdown(string $save = "SAVE") Synchronously save the dataset to disk and then shut down the server
 * @method sInter(string $key1, string $key2 = NULL) Intersect multiple sets
 * @method sInterStore(string $destination, string $key1, string $key2 = NULL) Intersect multiple sets and store the resulting set in a key
 * @method sIsMember(string $key, string $member) Determine if a given value is a member of a set
 * @method slaveOf(string $host, int $port) Make the server a slave of another instance, or promote it as master
 * @method slowLog(string $subCommand, $arg = NULL) Manages the Redis slow queries log
 * @method sMembers(string $key) Get all the members in a set
 * @method sMove(string $source, string $destination, string $member) Move a member from one set to another
 * @method sort(string $key, $option1 = NULL, $option2 = NULL) Sort the elements in a list, set or sorted set
 * @method sPop(string $key) Remove and return a random member from a set
 * @method sRandMember(string $key) Get a random member from a set
 * @method sRem(string $key, string $member1, string $member2 = NULL) Remove one or more members from a set
 * @method strLen(string $key) Get the length of the value stored in a key
 * @method subscribe(string $channel1, string $channel2 = NULL) Listen for messages published to the given channels
 * @method sUnion(string $key1, string $key2 = NULL) Add multiple sets
 * @method sUnionStore(string $destination, string $key1, string $key2 = NULL) Add multiple sets and store the resulting set in a key
 * @method sync() Internal command used for replication
 * @method time() Return the current server time
 * @method ttl(string $key) Get the time to live for a key
 * @method type(string $key) Determine the type stored at key
 * @method unsubscribe(string $channel1, string $channel2 = NULL) Stop listening for messages posted to the given channels
 * @method unwatch() Forget about all watched keys
 * @method watch(string $key1, string $key2 = NULL) Watch the given keys to determine execution of the MULTI/EXEC block
 * @method zAdd(string $key, int $score1, string $member1, int $score2 = NULL, string $member2 = NULL) Add one or more members to a sorted set, or update its score if it already exists
 * @method zCard(string $key) Get the number of members in a sorted set
 * @method zCount(string $key, int $min, int $max) Count the members in a sorted set with scores within the given values
 * @method zIncrBy(string $key, int $increment, string $member) Increment the score of a member in a sorted set
 * @method zInterStore(string $destination, $numkeys, string $key1, string $key2 = NULL, $option1 = NULL, $option2 = NULL) Intersect multiple sorted sets and store the resulting sorted set in a new key
 * @method zRange(string $key, int $start, int $stop, $option1 = NULL) Return a range of members in a sorted set, by index
 * @method zRangeByScore(string $key, int $min, int $max, $option1 = NULL, $option2 = NULL) Return a range of members in a sorted set, by score
 * @method zRang(string $key, string $member) Determine the index of a member in a sorted set
 * @method zRem(string $key, string $member1, string $member2 = NULL) Remove one or more members from a sorted set
 * @method zRemRangeByRank(string $key, int $start, int $stop) Remove all members in a sorted set within the given indexes
 * @method zRemRangeByScore(string $key, int $min, int $max) Remove all members in a sorted set within the given scores
 * @method zRevRange(string $key, int $start, int $stop, $option = NULL) Return a range of members in a sorted set, by index, with scores ordered from high to low
 * @method zRevRangeByScore(string $key, int $max, int $min, $option = NULL, $option2 = NULL) Return a range of members in a sorted set, by score, with scores ordered from high to low
 * @method zRevRang(string $key, string $member) Determine the index of a member in a sorted set, with scores ordered from high to low
 * @method zScore(string $key, string $member) Get the score associated with the given member in a sorted set
 * @method zUnionStore(string $destination, string $numkeys, string $key1, string $key2 = NULL, $option1 = NULL, $option2 = NULL) Add multiple sorted sets and store the resulting sorted set in a new key</ul>
 *
 * @author ptrofimov https://github.com/ptrofimov
 * @author Filip Procházka <filip@prochazka.su>
 */
class RedisClient extends Nette\Object implements \ArrayAccess
{

	/** @internal */
	const MAX_BUFFER_SIZE = 8192;
	const MAX_ATTEMPTS = 3;

	/** commands */
	const WITH_SCORES = 'WITHSCORES';

	/**
	 * @var resource
	 */
	private $session;

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
	 * @param string $host
	 * @param int $port
	 * @param int $database
	 * @param int $timeout
	 */
	public function __construct($host = 'localhost', $port = 6379, $database = 0, $timeout = 10)
	{
		$this->host = $host;
		$this->port = $port;
		$this->database = $database;
		$this->timeout = $timeout;
	}



	/**
	 * @throws RedisClientException
	 */
	public function connect()
	{
		if (is_resource($this->session)) {
			return;
		}

		$this->session = stream_socket_client($this->host . ':' . $this->port, $errno, $errstr, $this->timeout);
		if (!$this->session) {
			throw new RedisClientException('Cannot connect to server: ' . $errstr, $errno);
		}
		stream_set_blocking($this->session, 1);
		stream_set_timeout($this->session, $this->timeout); // ?

		if ($this->database > 0) {
			$this->select($this->database);
		}
	}



	/**
	 * Close the connection
	 */
	public function close()
	{
		$this->getLock()->releaseAll();
		@fclose($this->session);
		$this->session = FALSE;
	}



	/**
	 * @param Diagnostics\Panel $panel
	 */
	public function setPanel(Diagnostics\Panel $panel)
	{
		$this->panel = $panel;
	}



	/**
	 * @param string $cmd
	 * @param array $args
	 *
	 * @throws \Nette\InvalidStateException
	 * @return mixed
	 */
	protected function send($cmd, array $args = array())
	{
		$this->connect();
		$cmd = strtolower($cmd);

		if ($this->panel) {
			$request = $args;
			array_unshift($request, $cmd);
			$this->panel->begin($request);
		}

		$result = FALSE;
		$message = $this->buildMessage($cmd, $args);

		$i = in_array($cmd, array('exec', 'discard')) ? 1 : self::MAX_ATTEMPTS;
		do {
			if (isset($e)) { // reconnect
				$this->close();
				$this->connect();
				unset($e);
			}

			try {
				$response = $this->sendMessage($message);
				$result = $this->parseResponse($response);

			} catch (RedisClientException $e) {
				$request = $args;
				array_unshift($request, $cmd);
				$e->request = $request;
				Debugger::log($e, 'redis-error');
				if ($this->panel) {
					$this->panel->error($e);
				}
			}

		} while (isset($e) && --$i > 0);

		if ($this->panel) {
			$this->panel->end();
		}

		if (isset($e)) {
			throw $e;
		}

		return $result;
	}



	/**
	 * @param string $message
	 *
	 * @throws RedisClientException
	 * @return object|\stdClass
	 */
	private function sendMessage($message)
	{
		fwrite($this->session, $message);
		return $this->readLine(TRUE);
	}



	/**
	 * @param string $method
	 * @param array $args
	 *
	 * @return string
	 */
	private function buildMessage($method, array $args)
	{
		array_unshift($args, str_replace('_', ' ', strtoupper($method)));
		$cmd = '*' . count($args) . "\r\n";
		foreach ($args as $item) {
			$cmd .= '$' . strlen($item) . "\r\n" . $item . "\r\n";
		}
		return $cmd;
	}



	/**
	 * @param object|\stdClass $response
	 *
	 * @return array|null|string
	 */
	private function parseResponse($response)
	{
		$result = $response->result;
		if ($response->result == -1) {
			$result = null;

		} elseif ($response->type == '$') {
			$line = $this->readResponse($response->result + 2);
			$result = substr($line, 0, strlen($line) - 2);

		} elseif ($response->type == '*') {
			$count = (int)$response->result;
			$result = array();
			for ($i = 0; $i < $count; $i++) {
				$result[] = $this->parseResponse($this->readLine());
			}
		}

		return $result;
	}



	/**
	 * @param bool $first
	 * @return object
	 * @throws RedisClientException
	 */
	private function readLine($first = FALSE)
	{
		if (!$line = stream_get_line($this->session, 4096, "\r\n")) {
			throw new RedisClientException("Request failed");
		}

		if ($this->panel) {
			$this->panel->dataSize(strlen($line) + 2);
		}

		$result = substr($line, 1);
		if ($line[0] == '-') {
			$result = $e = new RedisClientException($result);

		} elseif (!in_array($line[0], array('+', ':', '$', '*', '-'))) {
			$result = $e = new RedisClientException("Invalid answer");
			$e->response = $line;
		}

		if (isset($e) && $first) {
			throw $e;
		}

		return (object)array('type' => $line[0], 'result' => $result);
	}



	/**
	 * @param int $length
	 *
	 * @throws RedisClientException
	 * @return null|string
	 */
	private function readResponse($length)
	{
		if ($this->panel) {
			$this->panel->dataSize($length);
		}

		$buffer = NULL;
		if ($length <= self::MAX_BUFFER_SIZE) {
			$buffer = fread($this->session, $length);

		} else {
			while ($length > self::MAX_BUFFER_SIZE) {
				$buffer .= fread($this->session, self::MAX_BUFFER_SIZE);
				$length -= self::MAX_BUFFER_SIZE;
			}
			$buffer .= fread($this->session, $length);
		}

		if (empty($buffer) && $length) {
			throw new RedisClientException("Timeout");
		}

		return $buffer;
	}



	/**
	 * Get information and statistics about the server
	 *
	 * @param string $returnKey
	 * @return array
	 */
	public function info($returnKey = NULL)
	{
		$info = array();
		foreach (explode("\r\n", $this->send('info')) as $row) {
			if (empty($row) || substr($row, 0, 1) === '#') {
				continue;
			}

			list($key, $value) = explode(':', $row, 2) + array(1 => NULL);
			if (strpos($value, ',') === FALSE) {
				$info[$key] = $value;
				continue;
			}

			foreach (explode(',', $value) as $item) {
				list($k, $v) = explode('=', $item, 2) + array(1 => NULL);
				$info[$key][$k] = $v;
			}
		}

		if ($returnKey !== NULL) {
			return $info[$returnKey];
		}

		return $info;
	}



	/**
	 * Mark the start of a transaction block
	 *
	 * @param callable $callback
	 * @throws RedisClientException
	 * @throws \Exception
	 * @return array|bool|mixed|null|string
	 */
	public function multi($callback = NULL)
	{
		$ok = $this->send('multi');

		if ($callback === NULL) {
			return $ok;
		}

		try {
			callback($callback)->invoke($this);
			return $this->exec();

		} catch (RedisClientException $e) {
			throw $e;

		} catch (\Exception $e) {
			$this->discard();
			throw $e;
		}

		return $ok;
	}



	/**
	 * @return array|bool|null|string
	 * @throws TransactionException
	 */
	public function exec()
	{
		$response = $this->send('exec');
		if ($response === NULL) {
			throw new TransactionException("Transaction was aborted");
		}
		return $response;
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
		if (version_compare($version, '2.2.0', '<')) {
			throw new Nette\Utils\AssertionException(
				"Minimum required version for this Redis client is 2.2.0, your version is $version. Please upgrade your software."
			);
		}
	}



	/**
	 * Close the connection
	 */
	public function __destruct()
	{
		$this->close();
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
		return $this->get($name);
	}



	/**
	 * Magic method as alias for set command.
	 *
	 * @param string $name
	 * @param mixed $value
	 */
	public function __set($name, $value)
	{
		return $this->set($name, $value);
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
		return $this->exists($name);
	}



	/**
	 * Magic method as alias for del command.
	 *
	 * @param string $name
	 */
	public function __unset($name)
	{
		return $this->del($name);
	}



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



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class RedisClientException extends \RuntimeException
{

	/**
	 * @var string
	 */
	public $request;

	/**
	 * @var string
	 */
	public $response;

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class TransactionException extends RedisClientException
{

}
