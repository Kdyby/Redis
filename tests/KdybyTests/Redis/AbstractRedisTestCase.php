<?php

namespace KdybyTests\Redis;

use Kdyby;
use Kdyby\Redis\RedisClient;
use Kdyby\Redis\RedisClientException;
use Nette;
use Nette\PhpGenerator as Code;
use Nette\Reflection\ClassType;
use Nette\Reflection\GlobalFunction;
use Nette\Utils\AssertionException;
use Nette\Utils\Callback;
use Nette\Utils\FileSystem;
use Nette\Utils\Strings;
use Tester;
use Tracy;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
abstract class AbstractRedisTestCase extends Tester\TestCase
{

	/**
	 * @var \Kdyby\Redis\RedisClient|\stdClass
	 */
	protected $client;

	/**
	 * @var resource
	 */
	private static $lock;



	protected function getClient()
	{
		if ($this->client) {
			return $this->client;
		}

		$client = new RedisClient();
		try {
			$client->connect();

		} catch (RedisClientException $e) {
			Tester\Environment::skip($e->getMessage());
		}

		try {
			$client->assertVersion();

		} catch (AssertionException $e) {
			Tester\Environment::skip($e->getMessage());
		}

		try {
			$client->flushDb();

		} catch (RedisClientException $e) {
			Tester\Assert::fail($e->getMessage());
		}

		return $this->client = $client;
	}



	protected function setUp()
	{
		flock(self::$lock = fopen(dirname(TEMP_DIR) . '/lock-redis', 'w'), LOCK_EX);

		$this->getClient(); // make sure it's created
	}



	protected function tearDown()
	{
		if (self::$lock) {
			@flock(self::$lock, LOCK_UN);
			@fclose(self::$lock);
			self::$lock = NULL;
		}

		$this->client = NULL;
	}



	/**
	 * @param callable $closure
	 * @param int $repeat
	 * @param int $threads
	 * @return array
	 */
	protected function threadStress(\Closure $closure, $repeat = 100, $threads = 30)
	{
		$runTest = Tracy\Helpers::findTrace(debug_backtrace(), 'Tester\TestCase::runTest') ?: ['args' => [0 => 'test']];
		$testName = ($runTest['args'][0] instanceof \ReflectionFunctionAbstract) ? $runTest['args'][0]->getName() : (string) $runTest['args'][0];
		$scriptFile = TEMP_DIR . '/scripts/' . str_replace('%5C', '_', urlencode(get_class($this))) . '.' . urlencode($testName) . '.php';
		FileSystem::createDir($dir = dirname($scriptFile));

		$extractor = new ClosureExtractor($closure);
		file_put_contents($scriptFile, $extractor->buildScript(ClassType::from($this), $repeat));
		@chmod($scriptFile, 0755);

		$testRefl = new \ReflectionClass($this);
		$collector = new ResultsCollector(dirname($testRefl->getFileName()) . '/output', $runTest['args'][0]);

		// todo: fix for hhvm
		$runner = new Tester\Runner\Runner(new Tester\Runner\ZendPhpInterpreter('php-cgi', ' -c ' . Tester\Helpers::escapeArg(__DIR__ . '/../../php.ini-unix')));
		$runner->outputHandlers[] = $collector;
		$runner->threadCount = $threads;
		$runner->paths = [$scriptFile];

		putenv(\Tester\Environment::COVERAGE); // unset coverage fur subprocesses
		$runner->run();

		return $runner->getResults();
	}

}


class ResultsCollector implements Tester\Runner\OutputHandler
{

	public $results;

	/**
	 * @var string
	 */
	private $dir;

	/**
	 * @var string
	 */
	private $testName;



	public function __construct($dir, $testName = NULL)
	{
		$this->dir = $dir;

		if (!$testName) {
			$runTest = Tracy\Helpers::findTrace(debug_backtrace(), 'Tester\TestCase::runTest') ?: ['args' => [0 => 'test']];
			$testName = $runTest['args'][0];
		}
		$this->testName = $testName instanceof \ReflectionFunctionAbstract ? $testName->getName() : (string) $testName;
	}



	public function begin()
	{
		$this->results = [];

		if (is_dir($this->dir)) {
			foreach (glob(sprintf('%s/%s.*.actual', $this->dir, urlencode($this->testName))) as $file) {
				@unlink($file);
			}
		}
	}



	public function result($testName, $result, $message)
	{
		$message = Tester\Dumper::removeColors(trim($message));

		if ($result != Tester\Runner\Runner::PASSED) {
			$this->results[] = [$testName, $message];
		}
	}



	public function end()
	{
		if (!$this->results) {
			return;
		}

		FileSystem::createDir($this->dir);

		// write new
		foreach ($this->results as $process) {
			$args = !preg_match('~\\[(.+)\\]$~', trim($process[0]), $m) ? md5(basename($process[0])) : str_replace('=', '_', $m[1]);
			$filename = urlencode($this->testName) . '.' . urlencode($args) . '.actual';
			file_put_contents($this->dir . '/' . $filename, $process[1]);
		}
	}

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ClosureExtractor
{
	use Nette\SmartObject;

	/**
	 * @var GlobalFunction
	 */
	private $closure;



	/**
	 * @param \Closure $closure
	 */
	public function __construct(\Closure $closure)
	{
		$this->closure = new GlobalFunction($closure);
	}



	/**
	 * @param \ReflectionClass $class
	 * @param int $repeat
	 * @return string
	 */
	public function buildScript(\ReflectionClass $class, $repeat)
	{
		$uses = new NamespaceUses($class);
		$codeParser = new FunctionCode($this->closure);

		$code = '<?php' . "\n\n";

		$code .= <<<DOC
/**
 * @multiple $repeat
 */
DOC;

		$code .= "\n\nnamespace " . $class->getNamespaceName() . ";\n\n";
		$code .= 'use ' . implode(";\n" . 'use ', $uses->parse()) . ";\n\n";

		// bootstrap
		$code .= Code\Helpers::formatArgs('require_once ?;', [__DIR__ . '/../bootstrap.php']) . "\n";
		$code .= '\Tester\Environment::$checkAssertions = FALSE;' . "\n";
		$code .= Code\Helpers::formatArgs('\Tracy\Debugger::$logDirectory = ?;', [TEMP_DIR]) . "\n\n\n";

		// script
		$code .= Code\Helpers::formatArgs('extract(?);', [$this->closure->getStaticVariables()]) . "\n\n";
		$code .= $codeParser->parse() . "\n\n\n";

		return $code;
	}

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class NamespaceUses
{
	use Nette\SmartObject;

	/**
	 * @var \ReflectionClass
	 */
	private $refl;



	/**
	 * @param \ReflectionClass $refl
	 * @throws \Nette\InvalidArgumentException
	 */
	public function __construct(\ReflectionClass $refl)
	{
		if (!$refl->isUserDefined()) {
			throw new Nette\InvalidArgumentException("Native functions cannot be parsed.");
		}
		$this->refl = $refl;
	}



	/**
	 * @return string
	 */
	public function parse()
	{
		$uses = $this->parseFile();

		return $uses[$this->refl->getNamespaceName() . '\\'];
	}



	/**
	 * @return array
	 * @throws \Nette\InvalidStateException
	 */
	private function parseFile()
	{
		$code = file_get_contents($this->refl->getFileName());

		$T_TRAIT = PHP_VERSION_ID < 50400 ? -1 : T_TRAIT;

		$expected = FALSE;
		$class = $namespace = $name = '';
		$level = $minLevel = 0;
		$uses = [];

		foreach (@token_get_all($code) as $token) { // intentionally @
			if ($token === '}') {
				$level--;
				if ($class && $minLevel === $level) {
					$class = NULL;
				}
			}

			if (is_array($token)) {
				switch ($token[0]) {
					case T_COMMENT:
					case T_DOC_COMMENT:
					case T_WHITESPACE:
						continue 2;

					case T_NS_SEPARATOR:
					case T_STRING:
						if ($expected) {
							$name .= $token[1];
							continue 2;
						}
						break;

					case T_NAMESPACE:
					case T_CLASS:
					case T_INTERFACE:
					case T_USE:
					case $T_TRAIT:
						$expected = $token[0];
						$name = '';
						continue 2;

					case T_CURLY_OPEN:
					case T_DOLLAR_OPEN_CURLY_BRACES:
						$level++;
				}
			}

			if ($expected) {
				switch ($expected) {
					case T_CLASS:
					case T_INTERFACE:
					case $T_TRAIT:
						if ($level === $minLevel) {
							$class = $namespace . $name;
						}
						break;

					case T_USE:
						if ($token === ',') {
							$name .= $token;
							continue 2;
						} elseif ($token === ';') {
							$list = array_map(Callback::closure('trim'), explode(',', $name));
							$uses[$namespace] = isset($uses[$namespace]) ? array_merge($uses[$namespace], $list) : $list;
						}
						break;

					case T_NAMESPACE:
						$namespace = $name ? $name . '\\' : '';
						$minLevel = $token === '{' ? 1 : 0;
				}

				$expected = NULL;
			}

			if ($token === '{') {
				$level++;
			}
		}

		return $uses;
	}

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class FunctionCode
{
	use Nette\SmartObject;

	/**
	 * @var \ReflectionFunctionAbstract
	 */
	private $refl;

	/**
	 * @var string
	 */
	private $code;



	/**
	 * @param \ReflectionFunctionAbstract|\ReflectionFunction|\ReflectionMethod $refl
	 * @throws \Nette\InvalidArgumentException
	 */
	public function __construct(\ReflectionFunctionAbstract $refl)
	{
		if (!$refl->isUserDefined()) {
			throw new Nette\InvalidArgumentException("Native functions cannot be parsed.");
		}
		$this->refl = $refl;
	}



	/**
	 * @return string
	 */
	public function parse()
	{
		if ($this->code === NULL) {
			$functions = $this->parseFile();

			if ($this->refl instanceof \ReflectionMethod) {
				$this->code = $functions[substr((string) $this->refl, 0, -2)]; // strip the ()

			} elseif (!$this->refl->isClosure()) {
				$this->code = $functions[substr((string) $this->refl, 0, -2)]; // strip the ()

			} else {
				foreach ($functions as $name => $code) {
					if (Strings::match($name, '~' . preg_quote('{closure:' . $this->refl->getStartLine() . '}') . '~')) {
						$this->code = $code;
						break;
					}
				}
			}
		}

		return preg_replace('~^[\t ]+~m', '', $this->code);
	}



	/**
	 * @return array
	 * @throws \Nette\InvalidStateException
	 */
	private function parseFile()
	{
		$code = file_get_contents($this->refl->getFileName());

		$T_TRAIT = PHP_VERSION_ID < 50400 ? -1 : T_TRAIT;

		$expected = FALSE;
		$function = $class = $namespace = $name = '';
		$line = $level = $minLevel = 0;
		$functionLevels = $functions = $classes = [];

		foreach (@token_get_all($code) as $token) { // intentionally @
			if ($token === '}') {
				if ($function = array_search($level, $functionLevels, TRUE)) {
					unset($functionLevels[$function]);
				}
				if (end($functionLevels)) {
					$function = key($functionLevels);
				}
				$level--;
				if ($class && $minLevel === $level) {
					$class = NULL;
				}
			}

			foreach ($functionLevels as $function => $l) {
				if ($level >= $l) {
					$functions[$function] .= is_array($token) ? $token[1] : $token;
				}
			}

			if (is_array($token)) {
				$line = $token[2];

				switch ($token[0]) {
					case T_COMMENT:
					case T_DOC_COMMENT:
					case T_WHITESPACE:
						continue 2;

					case T_NS_SEPARATOR:
					case T_STRING:
						if ($expected) {
							$name .= $token[1];
							continue 2;
						}
						break;

					case T_NAMESPACE:
					case T_CLASS:
					case T_INTERFACE:
					case T_FUNCTION:
					case $T_TRAIT:
						$expected = $token[0];
						$name = '';
						continue 2;

					case T_CURLY_OPEN:
					case T_DOLLAR_OPEN_CURLY_BRACES:
						$level++;
				}
			}

			if ($expected) {
				switch ($expected) {
					case T_CLASS:
					case T_INTERFACE:
					case $T_TRAIT:
						if ($level === $minLevel) {
							$classes[] = $class = $namespace . $name;
						}
						break;

					case T_FUNCTION:
						if ($class && $name) { // method
							$function = $class . '::' . $name;
						} elseif ($name) { // function
							$function = $namespace . $name;
						} else { // closure
							if ($this->refl->isClosure() && $function
								&& $this->refl->getStartLine() === $line
								&& Strings::match($function, '~' . $line . '\\}$~')
							) {
								throw new Nette\InvalidStateException(
									"$this->refl cannot be parsed, because there are multiple closures defined on line $line."
								);
							}

							$function = $function . '\\{closure:' . $line . '}';
						}

						$functionLevels[$function] = $level + 1;
						$functions[$function] = NULL;
						break;

					case T_NAMESPACE:
						$namespace = $name ? $name . '\\' : '';
						$minLevel = $token === '{' ? 1 : 0;
				}

				$expected = NULL;
			}

			if ($token === '{') {
				$level++;
			}
		}

		return $functions;
	}

}


class SessionHandlerDecorator implements \SessionHandlerInterface
{

	/**
	 * @var array
	 */
	public $series = [];

	/**
	 * @var bool
	 */
	public $log = FALSE;

	/**
	 * @var array
	 */
	public $openedSessionCalls = [];

	/**
	 * @var \SessionHandlerInterface
	 */
	private $handler;



	public function __construct(\SessionHandlerInterface $handler)
	{
		$this->handler = $handler;
	}



	private function log($msg)
	{
		if (!$this->log) {
			return;
		}

		file_put_contents(dirname(TEMP_DIR) . '/session.log', sprintf('[%s] [%s]: %s', date('Y-m-d H:i:s'), str_pad(getmypid(), 6, '0', STR_PAD_LEFT), $msg) . "\n", FILE_APPEND);
	}



	public function open($save_path, $session_id)
	{
		$this->log(sprintf('%s: %s', __FUNCTION__, $session_id));
		$this->openedSessionCalls[] = array_merge([__FUNCTION__], func_get_args());
		return $this->handler->open($save_path, $session_id);
	}



	public function close()
	{
		$this->log(__FUNCTION__);
		$this->openedSessionCalls[] = array_merge([__FUNCTION__], func_get_args());
		$this->series[] = $this->openedSessionCalls;
		$this->openedSessionCalls = [];
		return $this->handler->close();
	}



	public function read($session_id)
	{
		$this->log(sprintf('%s: %s', __FUNCTION__, $session_id));
		$this->openedSessionCalls[] = array_merge([__FUNCTION__], func_get_args());
		try {
			return $this->handler->read($session_id);

		} catch (\Exception $e) {
			$this->log(sprintf('%s: %s', __FUNCTION__, $e->getMessage()));
			throw $e;
		}
	}



	public function destroy($session_id)
	{
		$this->log(sprintf('%s: %s', __FUNCTION__, $session_id));
		$this->openedSessionCalls[] = array_merge([__FUNCTION__], func_get_args());
		try {
			return $this->handler->destroy($session_id);

		} catch (\Exception $e) {
			$this->log(sprintf('%s: %s', __FUNCTION__, $e->getMessage()));
			throw $e;
		}
	}



	public function write($session_id, $session_data)
	{
		$this->log(sprintf('%s: %s', __FUNCTION__, $session_id));
		$this->openedSessionCalls[] = array_merge([__FUNCTION__], func_get_args());
		try {
			return $this->handler->write($session_id, $session_data);

		} catch (\Exception $e) {
			$this->log(sprintf('%s: %s', __FUNCTION__, $e->getMessage()));
			throw $e;
		}
	}



	public function gc($maxlifetime)
	{
		$this->log(__FUNCTION__);
		$this->openedSessionCalls[] = array_merge([__FUNCTION__], func_get_args());
		return $this->handler->gc($maxlifetime);
	}

}
