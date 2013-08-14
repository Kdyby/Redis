<?php

/**
 * Test: Kdyby\Redis\RedisJournal.
 *
 * @testCase Kdyby\Redis\RedisJournalTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Redis
 */

namespace KdybyTests\Redis;

use Kdyby\Redis\RedisJournal;
use Kdyby\Redis\RedisLuaJournal;
use Nette;
use Nette\Caching\Cache;
use Nette\Caching\Storages\IJournal;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class RedisJournalTest extends AbstractRedisTestCase
{

	public function dataJournals()
	{
		return array(
			// array(new RedisJournal($this->getClient())),
			array(new RedisLuaJournal($this->getClient())),
		);
	}



	/**
	 * @dataProvider dataJournals
	 */
	public function testRemoveByTag(IJournal $journal)
	{
		Assert::same(0, count($this->getClient()->keys('*')));

		$journal->write('ok_test1', array(
			Cache::TAGS => array('test:homepage'),
		));

		Assert::same(2, count($this->getClient()->keys('*')));

		$result = $journal->clean(array(Cache::TAGS => array('test:homepage')));
		Assert::same(1, count($result));
		Assert::same('ok_test1', $result[0]);
	}



	/**
	 * @dataProvider dataJournals
	 */
	public function testRemovingByMultipleTags_OneIsNotDefined(IJournal $journal)
	{
		$journal->write('ok_test2', array(
			Cache::TAGS => array('test:homepage', 'test:homepage2'),
		));

		$result = $journal->clean(array(Cache::TAGS => array('test:homepage2')));
		Assert::same(1, count($result));
		Assert::same('ok_test2', $result[0]);
	}



	/**
	 * @dataProvider dataJournals
	 */
	public function testRemovingByMultipleTags_BothAreOnOneEntry(IJournal $journal)
	{
		$journal->write('ok_test2b', array(
			Cache::TAGS => array('test:homepage', 'test:homepage2'),
		));

		$result = $journal->clean(array(Cache::TAGS => array('test:homepage', 'test:homepage2')));
		Assert::same(1, count($result));
		Assert::same('ok_test2b', $result[0]);
	}



	/**
	 * @dataProvider dataJournals
	 */
	public function testRemoveByMultipleTags_TwoSameTags(IJournal $journal)
	{
		$journal->write('ok_test2c', array(
			Cache::TAGS => array('test:homepage', 'test:homepage'),
		));

		$result = $journal->clean(array(Cache::TAGS => array('test:homepage', 'test:homepage')));
		Assert::same(1, count($result));
		Assert::same('ok_test2c', $result[0]);
	}



	/**
	 * @dataProvider dataJournals
	 */
	public function testRemoveByTagAndPriority(IJournal $journal)
	{
		$journal->write('ok_test2d', array(
			Cache::TAGS => array('test:homepage'),
			Cache::PRIORITY => 15,
		));

		$result = $journal->clean(array(Cache::TAGS => array('test:homepage'), Cache::PRIORITY => 20));
		Assert::same(1, count($result));
		Assert::same('ok_test2d', $result[0]);
	}



	/**
	 * @dataProvider dataJournals
	 */
	public function testRemoveByPriority(IJournal $journal)
	{
		$journal->write('ok_test3', array(
			Cache::PRIORITY => 10,
		));

		$result = $journal->clean(array(Cache::PRIORITY => 10));
		Assert::same(1, count($result));
		Assert::same('ok_test3', $result[0]);
	}



	/**
	 * @dataProvider dataJournals
	 */
	public function testPriorityAndTag_CleanByTag(IJournal $journal)
	{
		$journal->write('ok_test4', array(
			Cache::TAGS => array('test:homepage'),
			Cache::PRIORITY => 10,
		));

		$result = $journal->clean(array(Cache::TAGS => array('test:homepage')));
		Assert::same(1, count($result));
		Assert::same('ok_test4', $result[0]);
	}



	/**
	 * @dataProvider dataJournals
	 */
	public function testPriorityAndTag_CleanByPriority(IJournal $journal)
	{
		$journal->write('ok_test5', array(
			Cache::TAGS => array('test:homepage'),
			Cache::PRIORITY => 10,
		));

		$result = $journal->clean(array(Cache::PRIORITY => 10));
		Assert::same(1, count($result));
		Assert::same('ok_test5', $result[0]);
	}



	/**
	 * @dataProvider dataJournals
	 */
	public function testMultipleWritesAndMultipleClean(IJournal $journal)
	{
		for ($i = 1; $i <= 10; $i++) {
			$journal->write('ok_test6_' . $i, array(
				Cache::TAGS => array('test:homepage', 'test:homepage/' . $i),
				Cache::PRIORITY => $i,
			));
		}

		$result = $journal->clean(array(Cache::PRIORITY => 5));
		Assert::same(5, count($result), "clean priority lower then 5");
		Assert::same('ok_test6_1', $result[0], "clean priority lower then 5");

		$result = $journal->clean(array(Cache::TAGS => array('test:homepage/7')));
		Assert::same(1, count($result), "clean tag homepage/7");
		Assert::same('ok_test6_7', $result[0], "clean tag homepage/7");

		$result = $journal->clean(array(Cache::TAGS => array('test:homepage/4')));
		Assert::same(0, count($result), "clean non exists tag");

		$result = $journal->clean(array(Cache::PRIORITY => 4));
		Assert::same(0, count($result), "clean non exists priority");

		$result = $journal->clean(array(Cache::TAGS => array('test:homepage')));
		Assert::same(4, count($result), "clean other");
		Assert::same('ok_test6_6', $result[0], "clean other");
	}



	/**
	 * @dataProvider dataJournals
	 */
	public function testSpecialChars(IJournal $journal)
	{
		$journal->write('ok_test7ščřžýáíé', array(
			Cache::TAGS => array('čšřýýá', 'ýřžčýž/10')
		));

		$result = $journal->clean(array(Cache::TAGS => array('čšřýýá')));
		Assert::same(1, count($result));
		Assert::same('ok_test7ščřžýáíé', $result[0]);
	}



	/**
	 * @dataProvider dataJournals
	 */
	public function testDuplicates_SameTag(IJournal $journal)
	{
		$journal->write('ok_test_a', array(
			Cache::TAGS => array('homepage')
		));

		$journal->write('ok_test_a', array(
			Cache::TAGS => array('homepage')
		));

		$result = $journal->clean(array(Cache::TAGS => array('homepage')));
		Assert::same(1, count($result));
		Assert::same('ok_test_a', $result[0]);
	}



	/**
	 * @dataProvider dataJournals
	 */
	public function testDuplicates_SamePriority(IJournal $journal)
	{
		$journal->write('ok_test_b', array(
			Cache::PRIORITY => 12
		));

		$journal->write('ok_test_b', array(
			Cache::PRIORITY => 12
		));

		$result = $journal->clean(array(Cache::PRIORITY => 12));
		Assert::same(1, count($result));
		Assert::same('ok_test_b', $result[0]);
	}



	/**
	 * @dataProvider dataJournals
	 */
	public function testDuplicates_DifferentTags(IJournal $journal)
	{
		$journal->write('ok_test_ba', array(
			Cache::TAGS => array('homepage')
		));

		$journal->write('ok_test_ba', array(
			Cache::TAGS => array('homepage2')
		));

		$result = $journal->clean(array(Cache::TAGS => array('homepage')));
		Assert::same(0, count($result));

		$result2 = $journal->clean(array(Cache::TAGS => array('homepage2')));
		Assert::same(1, count($result2));
		Assert::same('ok_test_ba', $result2[0]);
	}



	/**
	 * @dataProvider dataJournals
	 */
	public function testDuplicates_DifferentPriorities(IJournal $journal)
	{
		$journal->write('ok_test_bb', array(
			Cache::PRIORITY => 15
		));

		$journal->write('ok_test_bb', array(
			Cache::PRIORITY => 20
		));

		$result = $journal->clean(array(Cache::PRIORITY => 30));
		Assert::same(1, count($result));
		Assert::same('ok_test_bb', $result[0]);
	}



	/**
	 * @dataProvider dataJournals
	 */
	public function testCleanAll(IJournal $journal)
	{
		$journal->write('ok_test_all_tags', array(
			Cache::TAGS => array('test:all', 'test:all')
		));

		$journal->write('ok_test_all_priority', array(
			Cache::PRIORITY => 5,
		));

		$result = $journal->clean(array(Cache::ALL => TRUE));
		Assert::null($result);

		$result2 = $journal->clean(array(Cache::TAGS => 'test:all'));
		Assert::true(empty($result2));
	}



	/**
	 * @dataProvider dataJournals
	 */
	public function testBigCache(IJournal $journal)
	{
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			Tester\Helpers::skip("Linux only");
		}

		$script = $this->cacheGeneratorScripts();
		$script .= <<<LUA
for i in range(1, 100) do
	local key = "test." .. i
	for i in range(1, 5000) do
		local tag = "test." .. i
		redis.call('rPush', formatKey(tag, "keys") , key)
		redis.call('rPush', formatKey(key, "tags") , tag)
	end
end

return redis.status_reply("Ok")
LUA;

		Assert::true($this->getClient()->evalScript($script));
		$this->assertKeysInDatabase(5100);

		$journal->clean(array(Cache::TAGS => 'test.4356'));
		$this->assertKeysInDatabase(0);
	}



	/**
	 * @dataProvider dataJournals
	 */
	public function testBigCache_ShitloadOfEntries(IJournal $journal)
	{
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			Tester\Helpers::skip("Linux only");
		}

		$script = $this->cacheGeneratorScripts();
		$script .= <<<LUA
for i in range(1, 200000) do
	local key = "test." .. i
	local tag = "kdyby"
	redis.call('rPush', formatKey(tag, "keys") , key)
	redis.call('rPush', formatKey(key, "tags") , tag)
end

return redis.status_reply("Ok")
LUA;

		Assert::true($this->getClient()->evalScript($script));
		$this->assertKeysInDatabase(200001);

		$journal->clean(array(Cache::TAGS => 'kdyby'));
		$this->assertKeysInDatabase(0);
	}



	protected function assertKeysInDatabase($number)
	{
		$dbInfo = $this->getClient()->info('db' . $this->getClient()->getDriver()->getDBNum());
		if ($dbInfo === NULL) {
			$m = array('keys' => 0);

		} elseif (!$m = Nette\Utils\Strings::match($dbInfo, '~keys\=(?P<keys>[^,]+),~')) {
			Assert::fail("Number of keys in database couldn't be determined");
		}

		Assert::equal($number, (int) $m['keys']);
	}



	private function cacheGeneratorScripts()
	{
		$script = file_get_contents(__DIR__ . '/../../../src/Kdyby/Redis/scripts/common.lua');
		$script .= <<<LUA
local range = function (from, to, step)
	step = step or 1
	local f =
		step > 0 and
			function(_, lastvalue)
				local nextvalue = lastvalue + step
				if nextvalue <= to then return nextvalue end
			end or
		step < 0 and
			function(_, lastvalue)
				local nextvalue = lastvalue + step
				if nextvalue >= to then return nextvalue end
			end or
			function(_, lastvalue) return lastvalue end
	return f, nil, from - step
end


LUA;
		return $script;
	}

}

\run(new RedisJournalTest());
