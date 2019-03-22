<?php

declare(strict_types = 1);

/**
 * Test: Kdyby\Redis\RedisStorage.
 *
 * @testCase Kdyby\Redis\RedisStorageTest
 */

namespace KdybyTests\Redis;

use Kdyby\Redis\RedisLuaJournal;
use Kdyby\Redis\RedisStorage;
use Nette\Caching\Cache;
use Nette\Caching\Storages\IJournal;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



class RedisStorageTest extends \KdybyTests\Redis\AbstractRedisTestCase
{

	/**
	 * @var \Kdyby\Redis\RedisStorage
	 */
	private $storage;

	public function setUp(): void
	{
		parent::setUp();
		$this->storage = new RedisStorage($this->client);
	}

	/**
	 * key and data with special chars
	 *
	 * @return array<mixed>
	 */
	public function basicData(): array
	{
		return [
			[1, TRUE],
			\range("\x00", "\xFF"),
		];
	}

	public function testBasics(): void
	{
		[$key, $value] = $this->basicData();

		$cache = new Cache($this->storage);
		Assert::null($cache->load($key), 'Cache content');

		// Writing cache...
		$cache->save($key, $value);
		Assert::same($value, $cache->load($key), 'Is cache ok?');

		// Removing from cache using unset()...
		$cache->remove($key);
		Assert::false($cache->load($key) !== NULL, 'Is cached?');

		// Removing from cache using set NULL...
		$cache->save($key, $value);
		$cache->save($key, NULL);
		Assert::false($cache->load($key) !== NULL, 'Is cached?');
	}

	/**
	 * @param mixed $val
	 * @return mixed
	 */
	public static function dependency($val)
	{
		return $val;
	}

	public function testCallbacks(): void
	{
		$key = 'nette';
		$value = 'rulez';

		$cache = new Cache($this->storage);
		$cb = static::class . '::dependency';

		// Writing cache...
		$cache->save($key, $value, [
			Cache::CALLBACKS => [[$cb, 1]],
		]);

		Assert::true($cache->load($key) !== NULL, 'Is cached?');

		// Writing cache...
		$cache->save($key, $value, [
			Cache::CALLBACKS => [[$cb, 0]],
		]);

		Assert::false($cache->load($key) !== NULL, 'Is cached?');
	}

	public function testCleanAll(): void
	{
		$cacheA = new Cache($this->storage);
		$cacheB = new Cache($this->storage, 'B');

		$cacheA->save('test1', 'David');
		$cacheA->save('test2', 'Grudl');
		$cacheB->save('test1', 'divaD');
		$cacheB->save('test2', 'ldurG');

		Assert::same('David Grudl divaD ldurG', \implode(' ', [
			$cacheA->load('test1'),
			$cacheA->load('test2'),
			$cacheB->load('test1'),
			$cacheB->load('test2'),
		]));

		$this->storage->clean([Cache::ALL => TRUE]);

		Assert::null($cacheA->load('test1'));
		Assert::null($cacheA->load('test2'));
		Assert::null($cacheB->load('test1'));
		Assert::null($cacheB->load('test2'));
	}

	public function testExpiration(): void
	{
		$key = 'nette';
		$value = 'rulez';

		$cache = new Cache($this->storage);

		// Writing cache...
		$cache->save($key, $value, [
			Cache::EXPIRATION => \time() + 3,
		]);

		// Sleeping 1 second
		\sleep(1);
		Assert::true($cache->load($key) !== NULL, 'Is cached?');

		// Sleeping 3 seconds
		\sleep(3);
		Assert::false($cache->load($key) !== NULL, 'Is cached?');
	}

	public function testIntKeys(): void
	{
		// key and data with special chars
		$key = 0;
		$value = \range("\x00", "\xFF");

		$cache = new Cache($this->storage);
		Assert::false($cache->load($key) !== NULL, 'Is cached?');
		Assert::null($cache->load($key), 'Cache content');

		// Writing cache...
		$cache->save($key, $value);
		Assert::true($cache->load($key) !== NULL, 'Is cached?');
		Assert::same($value, $cache->load($key), 'Is cache ok?');

		// Removing from cache using unset()...
		$cache->remove($key);
		Assert::false($cache->load($key) !== NULL, 'Is cached?');

		// Removing from cache using set NULL...
		$cache->save($key, $value);
		$cache->save($key, NULL);
		Assert::false($cache->load($key) !== NULL, 'Is cached?');
	}

	public function testDependentItems(): void
	{
		$key = 'nette';
		$value = 'rulez';

		$cache = new Cache($this->storage);

		// Writing cache...
		$cache->save($key, $value, [
			Cache::ITEMS => ['dependent'],
		]);
		Assert::true($cache->load($key) !== NULL, 'Is cached?');

		// Modifing dependent cached item
		$cache->save('dependent', 'hello world');
		Assert::false($cache->load($key) !== NULL, 'Is cached?');

		// Writing cache...
		$cache->save($key, $value, [
			Cache::ITEMS => 'dependent',
		]);
		Assert::true($cache->load($key) !== NULL, 'Is cached?');

		// Modifing dependent cached item
		\sleep(2);
		$cache->save('dependent', 'hello europe');
		Assert::false($cache->load($key) !== NULL, 'Is cached?');

		// Writing cache...
		$cache->save($key, $value, [
			Cache::ITEMS => 'dependent',
		]);
		Assert::true($cache->load($key) !== NULL, 'Is cached?');

		// Deleting dependent cached item
		$cache->save('dependent', NULL);
		Assert::false($cache->load($key) !== NULL, 'Is cached?');
	}

	public function testLoadOrSave(): void
	{
		// key and data with special chars
		$key = '../' . \implode('', \range("\x00", "\x1F"));
		$value = \range("\x00", "\xFF");

		$cache = new Cache($this->storage);
		Assert::false($cache->load($key) !== NULL, 'Is cached?');

		// Writing cache using Closure...
		$res = $cache->load($key, static function (& $dp) use ($value) {
			$dp = [
				Cache::EXPIRATION => \time() + 2,
			];

			return $value;
		});

		Assert::same($value, $res, 'Is result ok?');
		Assert::same($value, $cache->load($key), 'Is cache ok?');

		// Sleeping 3 seconds
		\sleep(3);
		Assert::false($cache->load($key) !== NULL, 'Is cached?');
	}

	public function testNamespace(): void
	{
		$cacheA = new Cache($this->storage, 'a');
		$cacheB = new Cache($this->storage, 'b');

		// Writing cache...
		$cacheA->save('key', 'hello');
		$cacheB->save('key', 'world');

		Assert::true($cacheA->load('key') !== NULL, 'Is cached #1?');
		Assert::true($cacheB->load('key') !== NULL, 'Is cached #2?');
		Assert::same('hello', $cacheA->load('key'), 'Is cache ok #1?');
		Assert::same('world', $cacheB->load('key'), 'Is cache ok #2?');

		// Removing from cache #2 using unset()...
		$cacheB->remove('key');
		Assert::true($cacheA->load('key') !== NULL, 'Is cached #1?');
		Assert::false($cacheB->load('key') !== NULL, 'Is cached #2?');
	}

	public function testPriority(): void
	{
		$storage = new RedisStorage($this->client, $this->createDefaultJournal());
		$cache = new Cache($storage);

		// Writing cache...
		$cache->save('key1', 'value1', [
			Cache::PRIORITY => 100,
		]);
		$cache->save('key2', 'value2', [
			Cache::PRIORITY => 200,
		]);
		$cache->save('key3', 'value3', [
			Cache::PRIORITY => 300,
		]);
		$cache->save('key4', 'value4');

		// Cleaning by priority...
		$cache->clean([
			Cache::PRIORITY => '200',
		]);

		Assert::false($cache->load('key1') !== NULL, 'Is cached key1?');
		Assert::false($cache->load('key2') !== NULL, 'Is cached key2?');
		Assert::true($cache->load('key3') !== NULL, 'Is cached key3?');
		Assert::true($cache->load('key4') !== NULL, 'Is cached key4?');
	}

	public function testPriorityOptimized(): void
	{
		$storage = new RedisStorage($this->client, new RedisLuaJournal($this->client));
		$cache = new Cache($storage);

		// Writing cache...
		$cache->save('key1', 'value1', [
			Cache::PRIORITY => 100,
		]);
		$cache->save('key2', 'value2', [
			Cache::PRIORITY => 200,
		]);
		$cache->save('key3', 'value3', [
			Cache::PRIORITY => 300,
		]);
		$cache->save('key4', 'value4');

		// Cleaning by priority...
		$cache->clean([
			Cache::PRIORITY => '200',
		]);

		Assert::false($cache->load('key1') !== NULL, 'Is cached key1?');
		Assert::false($cache->load('key2') !== NULL, 'Is cached key2?');
		Assert::true($cache->load('key3') !== NULL, 'Is cached key3?');
		Assert::true($cache->load('key4') !== NULL, 'Is cached key4?');
	}

	public function testTags(): void
	{
		$storage = new RedisStorage($this->client, $this->createDefaultJournal());
		$cache = new Cache($storage);

		// Writing cache...
		$cache->save('key1', 'value1', [
			Cache::TAGS => ['one', 'two'],
		]);
		$cache->save('key2', 'value2', [
			Cache::TAGS => ['one', 'three'],
		]);
		$cache->save('key3', 'value3', [
			Cache::TAGS => ['two', 'three'],
		]);
		$cache->save('key4', 'value4');

		// Cleaning by tags...
		$cache->clean([
			Cache::TAGS => 'one',
		]);

		Assert::false($cache->load('key1') !== NULL, 'Is cached key1?');
		Assert::false($cache->load('key2') !== NULL, 'Is cached key2?');
		Assert::true($cache->load('key3') !== NULL, 'Is cached key3?');
		Assert::true($cache->load('key4') !== NULL, 'Is cached key4?');
	}

	public function testTagsOptimized(): void
	{
		$storage = new RedisStorage($this->client, new RedisLuaJournal($this->client));
		$cache = new Cache($storage);

		// Writing cache...
		$cache->save('key1', 'value1', [
			Cache::TAGS => ['one', 'two'],
		]);
		$cache->save('key2', 'value2', [
			Cache::TAGS => ['one', 'three'],
		]);
		$cache->save('key3', 'value3', [
			Cache::TAGS => ['two', 'three'],
		]);
		$cache->save('key4', 'value4');

		// Cleaning by tags...
		$cache->clean([
			Cache::TAGS => 'one',
		]);

		Assert::false($cache->load('key1') !== NULL, 'Is cached key1?');
		Assert::false($cache->load('key2') !== NULL, 'Is cached key2?');
		Assert::true($cache->load('key3') !== NULL, 'Is cached key3?');
		Assert::true($cache->load('key4') !== NULL, 'Is cached key4?');
	}

	public function testMultiRead(): void
	{
		$storage = $this->storage;

		$storage->write('A', 1, []);
		$storage->write('B', 2, []);
		$storage->write('C', FALSE, []);
		$storage->write('E', NULL, []);

		Assert::equal([
			'A' => 1,
			'B' => 2,
			'C' => FALSE,
			'D' => NULL,
			'E' => NULL,
		], $storage->multiRead(['A', 'B', 'C', 'D', 'E']));
	}

	/**
	 * @throws \Exception
	 */
	private function createDefaultJournal(): IJournal
	{
		if (\class_exists(\Nette\Caching\Storages\SQLiteJournal::class)) {
			return new \Nette\Caching\Storages\SQLiteJournal(':memory:');
		}

		throw new \Exception('no journal available');
	}

}

(new RedisStorageTest())->run();
