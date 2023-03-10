<?php

declare(strict_types = 1);

namespace KdybyTests\Redis;

class ClosureExtractor
{

	use \Nette\SmartObject;

	/**
	 * @var \ReflectionFunction
	 */
	private $closure;

	public function __construct(\Closure $closure)
	{
		$this->closure = new \ReflectionFunction($closure);
	}

	public function buildScript(
		\ReflectionClass $class,
		int $repeat
	): string
	{
		$uses = new \KdybyTests\Redis\NamespaceUses($class);
		$codeParser = new \KdybyTests\Redis\FunctionCode($this->closure);

		$code = '<?php' . "\n\n";

		$code .= <<<DOC
/**
 * @multiple $repeat
 */
DOC;

		$code .= "\n\nnamespace " . $class->getNamespaceName() . ";\n\n";
		$code .= 'use ' . \implode(";\n" . 'use ', $uses->parse()) . ";\n\n";

		$dumper = new \Nette\PhpGenerator\Dumper();
		// bootstrap
		$code .= $dumper->format('require_once ?;', __DIR__ . '/../bootstrap.php') . "\n";
		$code .= '\Tester\Environment::$checkAssertions = FALSE;' . "\n";
		$code .= $dumper->format('\Tracy\Debugger::$logDirectory = ?;', TEMP_DIR) . "\n\n\n";

		// script
		$code .= $dumper->format('extract(?);', $this->closure->getStaticVariables()) . "\n\n";
		$code .= $codeParser->parse() . "\n\n\n";

		return $code;
	}

}
