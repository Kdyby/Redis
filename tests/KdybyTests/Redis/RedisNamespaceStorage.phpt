<?php

/**
 * Test: Kdyby\Redis\RedisNamespaceStorage.
 *
 * @testCase Kdyby\Redis\RedisNamespaceStorageTest
 * @author Vladimir Bosiak <vladimir.bosiak@ulozenka.cz>
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
 * @author Vladimir Bosiak <vladimir.bosiak@ulozenka.cz>
 */
class RedisNamespaceStorageTest extends AbstractRedisTestCase
{

    /**
     * @var \Kdyby\Redis\RedisStorage
     */
    private $storage;

    public function setUp()
    {
        parent::setUp();
        $this->storage = new RedisStorage($this->client, NULL, 'foo');
    }

    public function testBasics()
    {
        list($key, $value) = $this->basicData();

        $cache = new Cache($this->storage);
        Assert::null($cache->load($key), "Cache content");

        // Writing cache...
        $cache->save($key, $value);
        Assert::same($value, $cache->load($key), "Is cache ok?");

        // Removing from cache using unset()...
        $cache->remove($key);
        Assert::false($cache->load($key) !== null, "Is cached?");

        // Removing from cache using set NULL...
        $cache->save($key, $value);
        $cache->save($key, null);
        Assert::false($cache->load($key) !== null, "Is cached?");
    }

    /**
     * key and data with special chars
     *
     * @return array
     */
    public function basicData()
    {
        return array(
            $key = array(1, true),
            $value = range("\x00", "\xFF"),
        );
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

        Assert::true($cache->load($key) !== null, 'Is cached?');

        // Writing cache...
        $cache->save($key, $value, array(
            Cache::CALLBACKS => array(array($cb, 0)),
        ));

        Assert::false($cache->load($key) !== null, 'Is cached?');
    }


    public function testCleanAll()
    {
        $cacheA = new Cache($this->storage);
        $cacheB = new Cache($this->storage, 'B');

        $cacheA->save('test1', 'David');
        $cacheA->save('test2', 'Grudl');
        $cacheB->save('test1', 'divaD');
        $cacheB->save('test2', 'ldurG');

        Assert::same('David Grudl divaD ldurG', implode(' ', array(
            $cacheA->load('test1'),
            $cacheA->load('test2'),
            $cacheB->load('test1'),
            $cacheB->load('test2'),
        )));

        $this->storage->clean(array(Cache::ALL => true));

        Assert::null($cacheA->load('test1'));
        Assert::null($cacheA->load('test2'));
        Assert::null($cacheB->load('test1'));
        Assert::null($cacheB->load('test2'));
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
        Assert::true($cache->load($key) !== null, 'Is cached?');

        // Sleeping 3 seconds
        sleep(3);
        Assert::false($cache->load($key) !== null, 'Is cached?');
    }


    public function testIntKeys()
    {
        // key and data with special chars
        $key = 0;
        $value = range("\x00", "\xFF");

        $cache = new Cache($this->storage);
        Assert::false($cache->load($key) !== null, 'Is cached?');
        Assert::null($cache->load($key), 'Cache content');

        // Writing cache...
        $cache->save($key, $value);
        Assert::true($cache->load($key) !== null, 'Is cached?');
        Assert::same($value, $cache->load($key), 'Is cache ok?');

        // Removing from cache using unset()...
        $cache->remove($key);
        Assert::false($cache->load($key) !== null, 'Is cached?');

        // Removing from cache using set NULL...
        $cache->save($key, $value);
        $cache->save($key, null);
        Assert::false($cache->load($key) !== null, 'Is cached?');
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
        Assert::true($cache->load($key) !== null, 'Is cached?');

        // Modifing dependent cached item
        $cache->save('dependent', 'hello world');
        Assert::false($cache->load($key) !== null, 'Is cached?');

        // Writing cache...
        $cache->save($key, $value, array(
            Cache::ITEMS => 'dependent',
        ));
        Assert::true($cache->load($key) !== null, 'Is cached?');

        // Modifing dependent cached item
        sleep(2);
        $cache->save('dependent', 'hello europe');
        Assert::false($cache->load($key) !== null, 'Is cached?');

        // Writing cache...
        $cache->save($key, $value, array(
            Cache::ITEMS => 'dependent',
        ));
        Assert::true($cache->load($key) !== null, 'Is cached?');

        // Deleting dependent cached item
        $cache->save('dependent', null);
        Assert::false($cache->load($key) !== null, 'Is cached?');
    }


    /**
     */
    public function testLoadOrSave()
    {
        // key and data with special chars
        $key = '../' . implode('', range("\x00", "\x1F"));
        $value = range("\x00", "\xFF");

        $cache = new Cache($this->storage);
        Assert::false($cache->load($key) !== null, 'Is cached?');

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
        Assert::false($cache->load($key) !== null, 'Is cached?');
    }


    public function testNamespace()
    {
        $cacheA = new Cache($this->storage, 'a');
        $cacheB = new Cache($this->storage, 'b');

        // Writing cache...
        $cacheA->save('key', 'hello');
        $cacheB->save('key', 'world');

        Assert::true($cacheA->load('key') !== null, 'Is cached #1?');
        Assert::true($cacheB->load('key') !== null, 'Is cached #2?');
        Assert::same('hello', $cacheA->load('key'), 'Is cache ok #1?');
        Assert::same('world', $cacheB->load('key'), 'Is cache ok #2?');

        // Removing from cache #2 using unset()...
        $cacheB->remove('key');
        Assert::true($cacheA->load('key') !== null, 'Is cached #1?');
        Assert::false($cacheB->load('key') !== null, 'Is cached #2?');
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
        $cache->save('key4', 'value4');

        // Cleaning by priority...
        $cache->clean(array(
            Cache::PRIORITY => '200',
        ));

        Assert::false($cache->load('key1') !== null, 'Is cached key1?');
        Assert::false($cache->load('key2') !== null, 'Is cached key2?');
        Assert::true($cache->load('key3') !== null, 'Is cached key3?');
        Assert::true($cache->load('key4') !== null, 'Is cached key4?');
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
        $cache->save('key4', 'value4');

        // Cleaning by priority...
        $cache->clean(array(
            Cache::PRIORITY => '200',
        ));

        Assert::false($cache->load('key1') !== null, 'Is cached key1?');
        Assert::false($cache->load('key2') !== null, 'Is cached key2?');
        Assert::true($cache->load('key3') !== null, 'Is cached key3?');
        Assert::true($cache->load('key4') !== null, 'Is cached key4?');
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
        $cache->save('key4', 'value4');

        // Cleaning by tags...
        $cache->clean(array(
            Cache::TAGS => 'one',
        ));

        Assert::false($cache->load('key1') !== null, 'Is cached key1?');
        Assert::false($cache->load('key2') !== null, 'Is cached key2?');
        Assert::true($cache->load('key3') !== null, 'Is cached key3?');
        Assert::true($cache->load('key4') !== null, 'Is cached key4?');
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
        $cache->save('key4', 'value4');

        // Cleaning by tags...
        $cache->clean(array(
            Cache::TAGS => 'one',
        ));

        Assert::false($cache->load('key1') !== null, 'Is cached key1?');
        Assert::false($cache->load('key2') !== null, 'Is cached key2?');
        Assert::true($cache->load('key3') !== null, 'Is cached key3?');
        Assert::true($cache->load('key4') !== null, 'Is cached key4?');
    }


    public function testMultiRead()
    {
        $storage = $this->storage;

        $storage->write('A', 1, array());
        $storage->write('B', 2, array());
        $storage->write('C', false, array());
        $storage->write('E', null, array());

        Assert::equal(array(
            'A' => 1,
            'B' => 2,
            'C' => false,
            'D' => null,
            'E' => null,
        ), $storage->multiRead(array('A', 'B', 'C', 'D', 'E')));
    }
}

\run(new RedisNamespaceStorageTest());
