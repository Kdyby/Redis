<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Redis;

use Kdyby;
use Nette;



/**
 * @author Filip Procházka <filip@prochazka.su>
 *
 *
 * @method append(string $key, string $value) Append a value to a key
 * @method auth(string $password) Authenticate to the server
 * @method bgRewriteAof() Asynchronously rewrite the append-only file
 * @method bfSave() Asynchronously save the dataset to disk
 * @method bitCount(string $key, int $start, int $end) Count set bits in a string
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
 * @method echo (string $message) Echo the given string
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
 * @method lRem(string $key, string $value, int $count = 0) Remove elements from a list
 * @method lSet(string $key, int $index, string $value) Set the value of an element in a list by its index
 * @method lTrim(string $key, int $start, int $stop) Trim a list to the specified range
 * @method mGet(string $key1, string $key2 = NULL) Get the values of all the given keys
 * @method migrate(string $host, int $port, string $key, string $destinationDb, int $timeout) Atomically transfer a key from a Redis instance to another one.
 * @method monitor() Listen for all requests received by the server in real time
 * @method move(string $key, string $db) Move a key to another database
 * @method mSet(array $values) Set multiple keys to multiple values
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
 * @method sDiff(string $key1, string $key2 = NULL) Subtract multiple sets
 * @method sDiffStore(string $destination, string $key1, string $key2 = NULL) Subtract multiple sets and store the resulting set in a key
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
 */
interface IRedisDriver
{

	/**
	 * A method to determine if a phpredis object thinks it's connected to a server
	 *
	 * @return bool
	 */
	function isConnected();

	/**
	 * Connects to a Redis instance or reuse a connection already established with pconnect/popen.
	 *
	 * The connection will not be closed on close or end of request until the php process ends.
	 * So be patient on to many open FD's (specially on redis server side)
	 * when using persistent connections on many servers connecting to one redis server.
	 *
	 * Also more than one persistent connection can be made identified
	 * by either host + port + timeout or host + persistent_id or unix socket + timeout.
	 *
	 * This feature is not available in threaded versions.
	 * pconnect and popen then working like their non persistent equivalents.
	 *
	 * @param string $host can be a host, or the path to a unix domain socket
	 * @param int $port
	 * @param int $timeout value in seconds (optional, default is 0 meaning unlimited)
	 * @return bool
	 */
	function connect($host, $port = NULL, $timeout = 0);

	/**
	 * Change the selected database for the current connection.
	 *
	 * @param int $database
	 * @return bool
	 */
	function select($database);



	/**
	 * Disconnects from the Redis instance, except when pconnect is used.
	 */
	function close();



	/**
	 * The last error message (if any)
	 *
	 * @return string|NULL
	 */
	function getLastError();



	/**
	 * Clear the last error message
	 */
	function clearLastError();



	/**
	 * Execute the Redis SCRIPT command to perform various operations on the scripting subsystem.
	 *
	 * @param $command
	 * @param $script
	 * @return mixed
	 */
	function script($command, $script = NULL);



	/**
	 * @param string $scriptSha The sha1 encoded hash of the script you want to run.
	 * @param array $argsArray Arguments to pass to the LUA script.
	 * @param int $numKeys The number of arguments that should go into the KEYS array, vs. the ARGV array when Redis spins the script
	 * @return mixed
	 */
	function evalsha($scriptSha, $argsArray = array(), $numKeys = 0);

}

