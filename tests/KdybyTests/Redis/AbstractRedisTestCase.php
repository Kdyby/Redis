<?php

declare(strict_types = 1);

namespace KdybyTests\Redis;

use Closure;
use Kdyby\Redis\RedisClient;
use Nette\Utils\FileSystem;
use ReflectionClass;
use ReflectionFunctionAbstract;
use Tester;
use Tester\Environment;
use Tester\Runner\PhpInterpreter;
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
	 * @throws \ReflectionException
	 */
	protected function threadStress(Closure $closure, int $repeat = 100, int $threads = 30): ResultsCollector
	{
		$runTest = Tracy\Helpers::findTrace(\debug_backtrace(), 'Tester\TestCase::runTest') ?: ['args' => [0 => 'test']];
		$testName = $runTest['args'][0] instanceof ReflectionFunctionAbstract ? $runTest['args'][0]->getName() : (string) $runTest['args'][0];
		$scriptFile = TEMP_DIR . '/scripts/' . \str_replace('%5C', '_', \urlencode(static::class)) . '.' . \urlencode($testName) . '.php';
		FileSystem::createDir($dir = \dirname($scriptFile));

		$extractor = new ClosureExtractor($closure);
		$testRefl = new ReflectionClass($this);
		\file_put_contents($scriptFile, $extractor->buildScript($testRefl, $repeat));
		@\chmod($scriptFile, 0755);

		$collector = new ResultsCollector(\dirname($testRefl->getFileName()) . '/output', $runTest['args'][0]);

		$interpreter = $this->createInterpreter();
		$runner = new Tester\Runner\Runner($interpreter);
		$runner->outputHandlers[] = $collector;
		$runner->threadCount = $threads;
		$runner->paths = [$scriptFile];

		\putenv(Environment::COVERAGE); // unset coverage fur subprocesses
		$runner->run();

		return $collector;
	}

	private function createInterpreter(): PhpInterpreter
	{
		$args = strlen((string) php_ini_scanned_files())
			? []
			: ['-n'];
		if (php_ini_loaded_file()) {
			array_push($args, '-c', php_ini_loaded_file());
		}
		return new Tester\Runner\PhpInterpreter(PHP_BINARY, $args);
	}

}
