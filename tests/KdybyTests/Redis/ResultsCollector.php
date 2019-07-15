<?php

declare(strict_types = 1);

namespace KdybyTests\Redis;

class ResultsCollector implements \Tester\Runner\OutputHandler
{

	/**
	 * @var array
	 */
	public $results;

	/**
	 * @var string
	 */
	private $dir;

	/**
	 * @var string
	 */
	private $testName;

	/**
	 * @param string $dir
	 * @param mixed $testName
	 */
	public function __construct(
		string $dir,
		$testName = NULL
	)
	{
		$this->dir = $dir;

		if ( ! $testName) {
			$runTest = \Tracy\Helpers::findTrace(
				\debug_backtrace(),
				'Tester\TestCase::runTest'
			) ?: ['args' => [0 => 'test']];
			$testName = $runTest['args'][0];
		}
		$this->testName = $testName instanceof \ReflectionFunctionAbstract ? $testName->getName() : (string) $testName;
	}

	public function begin(): void
	{
		$this->results = [];

		if (\is_dir($this->dir)) {
			foreach (\glob(\sprintf('%s/%s.*.actual', $this->dir, \urlencode($this->testName))) as $file) {
				@\unlink($file);
			}
		}
	}

	/**
	 * @param string $testName
	 * @param int $result
	 * @param mixed $message
	 */
	// phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingReturnTypeHint,SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	public function result(
		$testName,
		$result,
		$message
	): void
	{
		$message = \Tester\Dumper::removeColors(\trim((string) $message));

		if ($result !== \Tester\Runner\Runner::PASSED) {
			$this->results[] = [$testName, $message];
		}
	}

	public function end(): void
	{
		if ( ! $this->results) {
			return;
		}

		\Nette\Utils\FileSystem::createDir($this->dir);

		// write new
		foreach ($this->results as $process) {
			$args = ! \preg_match('~\\[(.+)\\]$~', \trim($process[0]), $m) ? \md5(
				\basename($process[0])
			) : \str_replace('=', '_', $m[1]);
			$filename = \urlencode($this->testName) . '.' . \urlencode($args) . '.actual';
			\file_put_contents($this->dir . '/' . $filename, $process[1]);
		}
	}

}
