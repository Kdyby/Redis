<?php

declare(strict_types = 1);

/**
 * Test: Kdyby\Redis\RedisJournal.
 *
 * @testCase Kdyby\Redis\RedisJournalTest
 */

namespace KdybyTests\Redis;

use Kdyby\Redis\RedisLuaJournal;
use Nette\Caching\Cache;
use Tester;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



class RedisJournalTest extends \KdybyTests\Redis\AbstractRedisTestCase
{

	/**
	 * @var \Kdyby\Redis\RedisLuaJournal|\Nette\Caching\Storages\IJournal
	 */
	private $journal;

	protected function setUp(): void
	{
		parent::setUp();

		$this->journal = new RedisLuaJournal($this->getClient());
	}

	public function testRemoveByTag(): void
	{
		// Assert::same(0, count($this->getClient()->keys('*')));
		$this->assertKeysInDatabase(0);

		$this->journal->write('ok_test1', [
			Cache::TAGS => ['test:homepage'],
		]);

		// Assert::same(2, count($this->getClient()->keys('*')));
		$this->assertKeysInDatabase(2);

		$result = $this->journal->clean([Cache::TAGS => ['test:homepage']]);
		Assert::same(1, \count($result));
		Assert::same('ok_test1', $result[0]);
	}

	public function testRemovingByMultipleTagsOneIsNotDefined(): void
	{
		$this->journal->write('ok_test2', [
			Cache::TAGS => ['test:homepage', 'test:homepage2'],
		]);

		$result = $this->journal->clean([Cache::TAGS => ['test:homepage2']]);
		Assert::same(1, \count($result));
		Assert::same('ok_test2', $result[0]);
	}

	public function testRemovingByMultipleTagsBothAreOnOneEntry(): void
	{
		$this->journal->write('ok_test2b', [
			Cache::TAGS => ['test:homepage', 'test:homepage2'],
		]);

		$result = $this->journal->clean([Cache::TAGS => ['test:homepage', 'test:homepage2']]);
		Assert::same(1, \count($result));
		Assert::same('ok_test2b', $result[0]);
	}

	public function testRemoveByMultipleTagsTwoSameTags(): void
	{
		$this->journal->write('ok_test2c', [
			Cache::TAGS => ['test:homepage', 'test:homepage'],
		]);

		$result = $this->journal->clean([Cache::TAGS => ['test:homepage', 'test:homepage']]);
		Assert::same(1, \count($result));
		Assert::same('ok_test2c', $result[0]);
	}

	public function testRemoveByTagAndPriority(): void
	{
		$this->journal->write('ok_test2d', [
			Cache::TAGS => ['test:homepage'],
			Cache::PRIORITY => 15,
		]);

		$result = $this->journal->clean([Cache::TAGS => ['test:homepage'], Cache::PRIORITY => 20]);
		Assert::same(1, \count($result));
		Assert::same('ok_test2d', $result[0]);
	}

	public function testRemoveByPriority(): void
	{
		$this->journal->write('ok_test3', [
			Cache::PRIORITY => 10,
		]);

		$result = $this->journal->clean([Cache::PRIORITY => 10]);
		Assert::same(1, \count($result));
		Assert::same('ok_test3', $result[0]);
	}

	public function testPriorityAndTagCleanByTag(): void
	{
		$this->journal->write('ok_test4', [
			Cache::TAGS => ['test:homepage'],
			Cache::PRIORITY => 10,
		]);

		$result = $this->journal->clean([Cache::TAGS => ['test:homepage']]);
		Assert::same(1, \count($result));
		Assert::same('ok_test4', $result[0]);
	}

	public function testPriorityAndTagCleanByPriority(): void
	{
		$this->journal->write('ok_test5', [
			Cache::TAGS => ['test:homepage'],
			Cache::PRIORITY => 10,
		]);

		$result = $this->journal->clean([Cache::PRIORITY => 10]);
		Assert::same(1, \count($result));
		Assert::same('ok_test5', $result[0]);
	}

	public function testMultipleWritesAndMultipleClean(): void
	{
		for ($i = 1; $i <= 10; $i++) {
			$this->journal->write('ok_test6_' . $i, [
				Cache::TAGS => ['test:homepage', 'test:homepage/' . $i],
				Cache::PRIORITY => $i,
			]);
		}

		$result = $this->journal->clean([Cache::PRIORITY => 5]);
		Assert::same(5, \count($result), 'clean priority lower then 5');
		Assert::same('ok_test6_1', $result[0], 'clean priority lower then 5');

		$result = $this->journal->clean([Cache::TAGS => ['test:homepage/7']]);
		Assert::same(1, \count($result), 'clean tag homepage/7');
		Assert::same('ok_test6_7', $result[0], 'clean tag homepage/7');

		$result = $this->journal->clean([Cache::TAGS => ['test:homepage/4']]);
		Assert::same(0, \count($result), 'clean non exists tag');

		$result = $this->journal->clean([Cache::PRIORITY => 4]);
		Assert::same(0, \count($result), 'clean non exists priority');

		$result = $this->journal->clean([Cache::TAGS => ['test:homepage']]);
		Assert::same(4, \count($result), 'clean other');
		\sort($result);
		Assert::same(['ok_test6_10', 'ok_test6_6', 'ok_test6_8', 'ok_test6_9'], $result, 'clean other');
	}

	public function testSpecialChars(): void
	{
		$this->journal->write('ok_test7ščřžýáíé', [
			Cache::TAGS => ['čšřýýá', 'ýřžčýž/10'],
		]);

		$result = $this->journal->clean([Cache::TAGS => ['čšřýýá']]);
		Assert::same(1, \count($result));
		Assert::same('ok_test7ščřžýáíé', $result[0]);
	}

	public function testDuplicatesSameTag(): void
	{
		$this->journal->write('ok_test_a', [
			Cache::TAGS => ['homepage'],
		]);

		$this->journal->write('ok_test_a', [
			Cache::TAGS => ['homepage'],
		]);

		$result = $this->journal->clean([Cache::TAGS => ['homepage']]);
		Assert::same(1, \count($result));
		Assert::same('ok_test_a', $result[0]);
	}

	public function testDuplicatesSamePriority(): void
	{
		$this->journal->write('ok_test_b', [
			Cache::PRIORITY => 12,
		]);

		$this->journal->write('ok_test_b', [
			Cache::PRIORITY => 12,
		]);

		$result = $this->journal->clean([Cache::PRIORITY => 12]);
		Assert::same(1, \count($result));
		Assert::same('ok_test_b', $result[0]);
	}

	public function testDuplicatesDifferentTags(): void
	{
		$this->journal->write('ok_test_ba', [
			Cache::TAGS => ['homepage'],
		]);

		$this->journal->write('ok_test_ba', [
			Cache::TAGS => ['homepage2'],
		]);

		$result = $this->journal->clean([Cache::TAGS => ['homepage']]);
		Assert::same(0, \count($result));

		$result2 = $this->journal->clean([Cache::TAGS => ['homepage2']]);
		Assert::same(1, \count($result2));
		Assert::same('ok_test_ba', $result2[0]);
	}

	public function testDuplicatesDifferentPriorities(): void
	{
		$this->journal->write('ok_test_bb', [
			Cache::PRIORITY => 15,
		]);

		$this->journal->write('ok_test_bb', [
			Cache::PRIORITY => 20,
		]);

		$result = $this->journal->clean([Cache::PRIORITY => 30]);
		Assert::same(1, \count($result));
		Assert::same('ok_test_bb', $result[0]);
	}

	public function testCleanAll(): void
	{
		$this->journal->write('ok_test_all_tags', [
			Cache::TAGS => ['test:all', 'test:all'],
		]);

		$this->journal->write('ok_test_all_priority', [
			Cache::PRIORITY => 5,
		]);

		$result = $this->journal->clean([Cache::ALL => TRUE]);
		Assert::null($result);

		$result2 = $this->journal->clean([Cache::TAGS => 'test:all']);
		Assert::true(empty($result2));
	}

	public function testBigCache(): void
	{
		if (\strtoupper(\substr(PHP_OS, 0, 3)) === 'WIN') {
			Tester\Helpers::skip('Linux only');
		}

		$script = $this->cacheGeneratorScripts();
		$script .= <<<LUA
for i in range(1, 100) do
	local key = "test." .. i
	for l in range(1, 5000) do
		local tag = "test." .. l
		redis.call('sAdd', formatKey(tag, "keys") , key)
		redis.call('sAdd', formatKey(key, "tags") , tag)
	end
end

return redis.status_reply("Ok")
LUA;

		Assert::true($this->getClient()->evalScript($script));
		$this->assertKeysInDatabase(5100);

		$this->journal->clean([Cache::TAGS => 'test.4356']);
		$this->assertKeysInDatabase(0);
	}

	public function testBigCacheShitloadOfEntries(): void
	{
		if (\strtoupper(\substr(PHP_OS, 0, 3)) === 'WIN') {
			Tester\Helpers::skip('Linux only');
		}

		$script = $this->cacheGeneratorScripts();
		$script .= <<<LUA
for i in range(1, 200000) do
	local key = "test." .. i
	local tag = "kdyby"
	redis.call('sAdd', formatKey(tag, "keys") , key)
	redis.call('sAdd', formatKey(key, "tags") , tag)
end

return redis.status_reply("Ok")
LUA;

		Assert::true($this->getClient()->evalScript($script));
		$this->assertKeysInDatabase(200001);

		$this->journal->clean([Cache::TAGS => 'kdyby']);
		$this->assertKeysInDatabase(0);
	}

	protected function assertKeysInDatabase(int $number): void
	{
		$dbNum = $this->getClient()->getDriver()->getDBNum();
		$dbInfo = $this->getClient()->info('db' . $dbNum);

		if ($number > 0 && !$dbInfo) {
			Assert::fail("Number of keys in database couldn't be determined");
		}

		Assert::equal($number, $dbInfo ? (int) $dbInfo['keys'] : 0);
	}

	private function cacheGeneratorScripts(): string
	{
		$script = \file_get_contents(__DIR__ . '/../../../src/Kdyby/Redis/scripts/common.lua');
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

	public function testNullByte(): void
	{
		$key = "prefix\x00test:\\2";
		$this->journal->write($key, [
			Cache::TAGS => ['test:nullByte'],
		]);

		$result = $this->journal->clean([
			Cache::TAGS => ['test:nullByte'],
		]);
		Assert::same([$key], $result);
	}

}

(new RedisJournalTest())->run();
