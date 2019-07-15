<?php

declare(strict_types = 1);

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Redis\Driver;

/**
 * @method bfSave() Asynchronously save the dataset to disk
 * @method config_get(string $parameter) Get the value of a configuration parameter
 * @method config_set(string $parameter, string $value) Set a configuration parameter to the given value
 * @method config_resetStat(string $parameter, string $value) '>Reset the stats returned by INFO
 * @method debug_object(string $key) Get debugging information about a key
 * @method debug_segfault() Make the server crash
 * @method monitor() Listen for all requests received by the server in real time
 * @method pUnsubscribe(string $pattern1, string $pattern2 = NULL) Stop listening for messages posted to channels matching the given patterns
 * @method quit() Close the connection
 * @method shutdown(string $save = "SAVE") Synchronously save the dataset to disk and then shut down the server
 * @method sync() Internal command used for replication
 * @method unsubscribe(string $channel1, string $channel2 = NULL) Stop listening for messages posted to the given channels
 * @method zInterStore(string $destination, $numkeys, string $key1, string $key2 = NULL, $option1 = NULL, $option2 = NULL) Intersect multiple sorted sets and store the resulting sorted set in a new key
 * @method zRang(string $key, string $member) Determine the index of a member in a sorted set
 * @method zRevRang(string $key, string $member) Determine the index of a member in a sorted set, with scores ordered from high to low
 * @method zUnionStore(string $destination, string $numkeys, string $key1, string $key2 = NULL, $option1 = NULL, $option2 = NULL) Add multiple sorted sets and store the resulting sorted set in a new key</ul>
 * @method isConnected() Returns bool if redis client is connected
 */
class PhpRedisDriver extends \Redis
{

}
