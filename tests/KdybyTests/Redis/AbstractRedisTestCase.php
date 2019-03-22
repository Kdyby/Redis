<?php

declare(strict_types = 1);

namespace KdybyTests\Redis;

use Closure;
use Kdyby\Redis\RedisClient;
use Nette\Reflection\ClassType;
use Nette\Utils\FileSystem;
use ReflectionClass;
use ReflectionFunctionAbstract;
use Tester;
use Tester\Environment;
use Tracy;

abstract class AbstractRedisTestCase extends \Tester\TestCase
{

	/**
	 * @var \Kdyby\Redis\RedisClient|NULL
	 */
	protected $client;

	/**
	 * @var resource|null
	 */
	private static $lock;

	protected function getClient(): RedisClient
	{
		if ($this->client) {
			return $this->client;
		}

		$client = new RedisClient();
		try {
			$client->connect();

		} catch (\Kdyby\Redis\Exception\RedisClientException $e) {
			Tester\Environment::skip($e->getMessage());
		}

		try {
			$client->assertVersion();

		} catch (\Nette\Utils\AssertionException $e) {
			Tester\Environment::skip($e->getMessage());
		}

		try {
			$client->flushDb();

		} catch (\Kdyby\Redis\Exception\RedisClientException $e) {
			Tester\Assert::fail($e->getMessage());
		}

		return $this->client = $client;
	}

	protected function setUp(): void
	{
		\flock(self::$lock = \fopen(\dirname(TEMP_DIR) . '/lock-redis', 'w'), LOCK_EX);

		$this->getClient(); // make sure it's created
	}

	protected function tearDown(): void
	{
		if (self::$lock) {
			@\flock(self::$lock, LOCK_UN);
			@\fclose(self::$lock);
			self::$lock = NULL;
		}

		$this->client = NULL;
	}

	/**
	 * @param \Closure $closure
	 * @param int $repeat
	 * @param int $threads
	 * @return array<mixed>
	 * @throws \ReflectionException
	 */
	protected function threadStress(Closure $closure, int $repeat = 100, int $threads = 30): array
	{
		$runTest = Tracy\Helpers::findTrace(\debug_backtrace(), 'Tester\TestCase::runTest') ?: ['args' => [0 => 'test']];
		$testName = $runTest['args'][0] instanceof ReflectionFunctionAbstract ? $runTest['args'][0]->getName() : (string) $runTest['args'][0];
		$scriptFile = TEMP_DIR . '/scripts/' . \str_replace('%5C', '_', \urlencode(static::class)) . '.' . \urlencode($testName) . '.php';
		FileSystem::createDir($dir = \dirname($scriptFile));

		$extractor = new ClosureExtractor($closure);
		\file_put_contents($scriptFile, $extractor->buildScript(ClassType::from($this), $repeat));
		@\chmod($scriptFile, 0755);

		$testRefl = new ReflectionClass($this);
		$collector = new ResultsCollector(\dirname($testRefl->getFileName()) . '/output', $runTest['args'][0]);

		// todo: fix for hhvm
		$runner = new Tester\Runner\Runner(new Tester\Runner\ZendPhpInterpreter('php-cgi', ' -c ' . Tester\Helpers::escapeArg(__DIR__ . '/../../php.ini-unix')));
		$runner->outputHandlers[] = $collector;
		$runner->threadCount = $threads;
		$runner->paths = [$scriptFile];

		\putenv(Environment::COVERAGE); // unset coverage fur subprocesses
		$runner->run();

		return $runner->getResults();
	}

}
