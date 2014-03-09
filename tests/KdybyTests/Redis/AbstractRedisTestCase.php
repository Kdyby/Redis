<?php

namespace KdybyTests\Redis;

use Kdyby;
use Kdyby\Redis\RedisClient;
use Kdyby\Redis\RedisClientException;
use Nette\PhpGenerator as Code;
use Nette\Reflection\ClassType;
use Nette\Reflection\GlobalFunction;
use Nette\Utils\AssertionException;
use Nette;
use Nette\Utils\Strings;
use Tester;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
abstract class AbstractRedisTestCase extends Tester\TestCase
{

	/**
	 * @var \Kdyby\Redis\RedisClient
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
	 */
	protected function threadStress(\Closure $closure, $repeat = 100, $threads = 30)
	{
		$scriptFile = TEMP_DIR . '/scripts/' . md5(get_class($this)) . '.php';
		if (!is_dir($dir = dirname($scriptFile))) {
			@umask(0);
			mkdir($dir, 0777);
		}

		$extractor = new ClosureExtractor($closure);
		file_put_contents($scriptFile, $extractor->buildScript(ClassType::from($this), $repeat));
		@chmod($scriptFile, 0755);

		$runner = new Tester\Runner\Runner(new Tester\Runner\PhpExecutable('php-cgi'));
		$runner->outputHandlers[] = $messages = new ResultsCollector();
		$runner->threadCount = $threads;
		$runner->paths = array($scriptFile);
		$runner->run();

		$result = $runner->getResults();

		foreach ($messages->results as $result) {
			echo 'FAILURE ' . $result[0] . "\n" . $result[1] . "\n";
		}

		Tester\Assert::equal($repeat, $result[Tester\Runner\Runner::PASSED]);
	}

}


class ResultsCollector implements Tester\Runner\OutputHandler
{

	public $results;



	public function begin()
	{
		$this->results = array();
	}



	public function result($testName, $result, $message)
	{
		$message = Tester\Dumper::removeColors(trim($message));

		if ($result != Tester\Runner\Runner::PASSED) {
			$this->results[] = array($testName, $message);
		}
	}



	public function end()
	{

	}

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ClosureExtractor extends Nette\Object
{

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
		$code .= Code\Helpers::formatArgs('require_once ?;', array(__DIR__ . '/../bootstrap.php')) . "\n\n\n";

		// script
		$code .= Code\Helpers::formatArgs('extract(?);', array($this->closure->getStaticVariables())) . "\n\n";
		$code .= $codeParser->parse() . "\n\n\n";

		return $code;
	}

}



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class NamespaceUses extends Nette\Object
{

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
		$uses = array();

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
							$list = array_map(callback('trim'), explode(',', $name));
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
class FunctionCode extends Nette\Object
{

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
		$functionLevels = $functions = $classes = array();

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
