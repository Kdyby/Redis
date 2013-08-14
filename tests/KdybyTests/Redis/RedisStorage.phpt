<?php

/**
 * Test: Kdyby\Redis\RedisStorage.
 *
 * @testCase Kdyby\Redis\RedisStorageTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Redis
 */

namespace KdybyTests\Redis;

use Kdyby\Redis\RedisLuaJournal;
use Kdyby\Redis\RedisStorage;
use Nette;
use Nette\Caching\Cache;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class RedisStorageTest extends AbstractRedisTestCase
{

	/**
	 * @var \Kdyby\Redis\RedisStorage
	 */
	private $storage;



	public function setUp()
	{
		parent::setUp();
		$this->storage = new RedisStorage($this->client);
	}



	/**
	 * key and data with special chars
	 *
	 * @return array
	 */
	public function basicData()
	{
		return array(
			$key = array(1, TRUE),
			$value = range("\x00", "\xFF"),
		);
	}



	public function testBasics()
	{
		list($key, $value) = $this->basicData();

		$cache = new Cache($this->storage);
		Assert::false(isset($cache[$key]), "Is cached?");
		Assert::null($cache[$key], "Cache content");

		// Writing cache...
		$cache[$key] = $value;
		Assert::true(isset($cache[$key]), "Is cached?");
		Assert::same($value, $cache[$key], "Is cache ok?");

		// Removing from cache using unset()...
		unset($cache[$key]);
		Assert::false(isset($cache[$key]), "Is cached?");

		// Removing from cache using set NULL...
		$cache[$key] = $value;
		$cache[$key] = NULL;
		Assert::false(isset($cache[$key]), "Is cached?");

		// Writing cache...
		$cache->save($key, $value);
		Assert::same($value, $cache->load($key), "Is cache ok?");
	}



	/**
	 * @param mixed $val
	 * @return mixed
	 */
	public static function dependency($val)
	{
		return $val;
	}



	public function testCallbacks()
	{
		$key = 'nette';
		$value = 'rulez';

		$cache = new Cache($this->storage);
		$cb = get_called_class() . '::dependency';

		// Writing cache...
		$cache->save($key, $value, array(
			Cache::CALLBACKS => array(array($cb, 1)),
		));

		Assert::true(isset($cache[$key]), 'Is cached?');

		// Writing cache...
		$cache->save($key, $value, array(
			Cache::CALLBACKS => array(array($cb, 0)),
		));

		Assert::false(isset($cache[$key]), 'Is cached?');
	}



	public function testCleanAll()
	{
		$cacheA = new Cache($this->storage);
		$cacheB = new Cache($this->storage, 'B');

		$cacheA['test1'] = 'David';
		$cacheA['test2'] = 'Grudl';
		$cacheB['test1'] = 'divaD';
		$cacheB['test2'] = 'ldurG';

		Assert::same('David Grudl divaD ldurG', implode(' ', array(
			$cacheA['test1'],
			$cacheA['test2'],
			$cacheB['test1'],
			$cacheB['test2'],
		)));

		$this->storage->clean(array(Cache::ALL => TRUE));

		Assert::null($cacheA['test1']);
		Assert::null($cacheA['test2']);
		Assert::null($cacheB['test1']);
		Assert::null($cacheB['test2']);
	}



	public function testExpiration()
	{
		$key = 'nette';
		$value = 'rulez';

		$cache = new Cache($this->storage);

		// Writing cache...
		$cache->save($key, $value, array(
			Cache::EXPIRATION => time() + 3,
		));

		// Sleeping 1 second
		sleep(1);
		Assert::true(isset($cache[$key]), 'Is cached?');

		// Sleeping 3 seconds
		sleep(3);
		Assert::false(isset($cache[$key]), 'Is cached?');
	}



	public function testIntKeys()
	{
		// key and data with special chars
		$key = 0;
		$value = range("\x00", "\xFF");

		$cache = new Cache($this->storage);
		Assert::false(isset($cache[$key]), 'Is cached?');
		Assert::null($cache[$key], 'Cache content');

		// Writing cache...
		$cache[$key] = $value;
		Assert::true(isset($cache[$key]), 'Is cached?');
		Assert::same($value, $cache[$key], 'Is cache ok?');

		// Removing from cache using unset()...
		unset($cache[$key]);
		Assert::false(isset($cache[$key]), 'Is cached?');

		// Removing from cache using set NULL...
		$cache[$key] = $value;
		$cache[$key] = NULL;
		Assert::false(isset($cache[$key]), 'Is cached?');
	}



	public function testDependentItems()
	{
		$key = 'nette';
		$value = 'rulez';

		$cache = new Cache($this->storage);

		// Writing cache...
		$cache->save($key, $value, array(
			Cache::ITEMS => array('dependent'),
		));
		Assert::true(isset($cache[$key]), 'Is cached?');

		// Modifing dependent cached item
		$cache['dependent'] = 'hello world';
		Assert::false(isset($cache[$key]), 'Is cached?');

		// Writing cache...
		$cache->save($key, $value, array(
			Cache::ITEMS => 'dependent',
		));
		Assert::true(isset($cache[$key]), 'Is cached?');

		// Modifing dependent cached item
		sleep(2);
		$cache['dependent'] = 'hello europe';
		Assert::false(isset($cache[$key]), 'Is cached?');

		// Writing cache...
		$cache->save($key, $value, array(
			Cache::ITEMS => 'dependent',
		));
		Assert::true(isset($cache[$key]), 'Is cached?');

		// Deleting dependent cached item
		$cache['dependent'] = NULL;
		Assert::false(isset($cache[$key]), 'Is cached?');
	}



	/**
	 */
	public function testLoadOrSave()
	{
		// key and data with special chars
		$key = '../' . implode('', range("\x00", "\x1F"));
		$value = range("\x00", "\xFF");

		$cache = new Cache($this->storage);
		Assert::false(isset($cache[$key]), 'Is cached?');

		// Writing cache using Closure...
		$res = $cache->load($key, function (& $dp) use ($value) {
			$dp = array(
				Cache::EXPIRATION => time() + 2,
			);

			return $value;
		});

		Assert::same($value, $res, 'Is result ok?');
		Assert::same($value, $cache->load($key), 'Is cache ok?');

		// Sleeping 3 seconds
		sleep(3);
		Assert::false(isset($cache[$key]), 'Is cached?');
	}



	public function testNamespace()
	{
		$cacheA = new Cache($this->storage, 'a');
		$cacheB = new Cache($this->storage, 'b');

		// Writing cache...
		$cacheA['key'] = 'hello';
		$cacheB['key'] = 'world';

		Assert::true(isset($cacheA['key']), 'Is cached #1?');
		Assert::true(isset($cacheB['key']), 'Is cached #2?');
		Assert::same('hello', $cacheA['key'], 'Is cache ok #1?');
		Assert::same('world', $cacheB['key'], 'Is cache ok #2?');

		// Removing from cache #2 using unset()...
		unset($cacheB['key']);
		Assert::true(isset($cacheA['key']), 'Is cached #1?');
		Assert::false(isset($cacheB['key']), 'Is cached #2?');
	}



	public function testPriority()
	{
		$storage = new RedisStorage($this->client, new Nette\Caching\Storages\FileJournal(TEMP_DIR));
		$cache = new Cache($storage);

		// Writing cache...
		$cache->save('key1', 'value1', array(
			Cache::PRIORITY => 100,
		));
		$cache->save('key2', 'value2', array(
			Cache::PRIORITY => 200,
		));
		$cache->save('key3', 'value3', array(
			Cache::PRIORITY => 300,
		));
		$cache['key4'] = 'value4';

		// Cleaning by priority...
		$cache->clean(array(
			Cache::PRIORITY => '200',
		));

		Assert::false(isset($cache['key1']), 'Is cached key1?');
		Assert::false(isset($cache['key2']), 'Is cached key2?');
		Assert::true(isset($cache['key3']), 'Is cached key3?');
		Assert::true(isset($cache['key4']), 'Is cached key4?');
	}



	public function testPriority_Optimized()
	{
		$storage = new RedisStorage($this->client, new RedisLuaJournal($this->client));
		$cache = new Cache($storage);

		// Writing cache...
		$cache->save('key1', 'value1', array(
			Cache::PRIORITY => 100,
		));
		$cache->save('key2', 'value2', array(
			Cache::PRIORITY => 200,
		));
		$cache->save('key3', 'value3', array(
			Cache::PRIORITY => 300,
		));
		$cache['key4'] = 'value4';

		// Cleaning by priority...
		$cache->clean(array(
			Cache::PRIORITY => '200',
		));

		Assert::false(isset($cache['key1']), 'Is cached key1?');
		Assert::false(isset($cache['key2']), 'Is cached key2?');
		Assert::true(isset($cache['key3']), 'Is cached key3?');
		Assert::true(isset($cache['key4']), 'Is cached key4?');
	}



	public function testTags()
	{
		$storage = new RedisStorage($this->client, new Nette\Caching\Storages\FileJournal(TEMP_DIR));
		$cache = new Cache($storage);

		// Writing cache...
		$cache->save('key1', 'value1', array(
			Cache::TAGS => array('one', 'two'),
		));
		$cache->save('key2', 'value2', array(
			Cache::TAGS => array('one', 'three'),
		));
		$cache->save('key3', 'value3', array(
			Cache::TAGS => array('two', 'three'),
		));
		$cache['key4'] = 'value4';

		// Cleaning by tags...
		$cache->clean(array(
			Cache::TAGS => 'one',
		));

		Assert::false(isset($cache['key1']), 'Is cached key1?');
		Assert::false(isset($cache['key2']), 'Is cached key2?');
		Assert::true(isset($cache['key3']), 'Is cached key3?');
		Assert::true(isset($cache['key4']), 'Is cached key4?');
	}



	public function testTags_Optimized()
	{
		$storage = new RedisStorage($this->client, new RedisLuaJournal($this->client));
		$cache = new Cache($storage);

		// Writing cache...
		$cache->save('key1', 'value1', array(
			Cache::TAGS => array('one', 'two'),
		));
		$cache->save('key2', 'value2', array(
			Cache::TAGS => array('one', 'three'),
		));
		$cache->save('key3', 'value3', array(
			Cache::TAGS => array('two', 'three'),
		));
		$cache['key4'] = 'value4';

		// Cleaning by tags...
		$cache->clean(array(
			Cache::TAGS => 'one',
		));

		Assert::false(isset($cache['key1']), 'Is cached key1?');
		Assert::false(isset($cache['key2']), 'Is cached key2?');
		Assert::true(isset($cache['key3']), 'Is cached key3?');
		Assert::true(isset($cache['key4']), 'Is cached key4?');
	}

}

\run(new RedisStorageTest());
