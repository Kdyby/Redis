<?php declare(strict_types = 1);

namespace Kdyby\Redis\DI\Config;

class ClientSchema implements \Nette\Schema\Schema
{

	private \Nette\DI\ContainerBuilder $builder;


	public function __construct(\Nette\DI\ContainerBuilder $builder)
	{
		$this->builder = $builder;
	}

	public function normalize($value, \Nette\Schema\Context $context)
	{
		if (\is_bool($value)) {
			return $value;
		}

		$value = $this->getSchema()->normalize($value, $context);

		if (\array_key_exists('host', $value) && $value['host'][0] === '/') {
			$value['port'] = NULL; // sockets have no ports

		} elseif ( ! \array_key_exists('port', $value)) {
			$value['port'] = \Kdyby\Redis\RedisClient::DEFAULT_PORT;
		}

		return $value;
	}


	public function merge($value, $base)
	{
		return \Nette\Schema\Helpers::merge($value, $base);
	}


	public function complete($value, \Nette\Schema\Context $context)
	{
		if ( ! \is_bool($value)) {
			$value = $this->expandParameters($value);
		}

		$value = $this->getSchema()->complete($value, $context);

		return $value;
	}


	public function completeDefault(\Nette\Schema\Context $context)
	{

	}

	private function expandParameters(array $config): array
	{
		$params = $this->builder->parameters;
		if (isset($config['parameters'])) {
			foreach ((array) $config['parameters'] as $k => $v) {
				$v = \explode(' ', \is_int($k) ? $v : $k);
				$params[\end($v)] = $this->builder::literal('$' . \end($v));
			}
		}
		return \Nette\DI\Helpers::expand($config, $params);
	}

	private function getSchema(): \Nette\Schema\Schema
	{
		return \Nette\Schema\Expect::structure([
			'host' => \Nette\Schema\Expect::string('127.0.0.1'),
			'port' => \Nette\Schema\Expect::int()->nullable(),
			'timeout' => \Nette\Schema\Expect::int(10),
			'database' => \Nette\Schema\Expect::int(0),
			'auth' => \Nette\Schema\Expect::string()->nullable(),
			'persistent' => \Nette\Schema\Expect::bool(FALSE),
			'connectionAttempts' => \Nette\Schema\Expect::int(1),
			'lockDuration' => \Nette\Schema\Expect::int(15),
			'lockAcquireTimeout' => \Nette\Schema\Expect::bool(FALSE),
			'debugger' => \Nette\Schema\Expect::bool($this->builder->parameters['debugMode']),
			'versionCheck' => \Nette\Schema\Expect::bool(TRUE),
		])->castTo('array');
	}

}
