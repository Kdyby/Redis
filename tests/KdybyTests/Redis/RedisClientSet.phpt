<?php

declare(strict_types = 1);

namespace KdybyTests\Redis;

require_once __DIR__ . '/../bootstrap.php';


class RedisClientSet extends \KdybyTests\Redis\AbstractRedisTestCase
{

	/**
	 * @var string
	 */
	private $ns;

	public function setUp(): void
	{
		parent::setUp();
		$this->ns = \Nette\Utils\Random::generate();
	}


	public function testSaveString(): void
	{
		$secret = "PeckaWorkshop";
		$key = $this->ns . 'redis-test-secred';

		$this->client->set($key, $secret);
		$this->client->expire($key, 10);

		\Tester\Assert::same($secret, $this->client->get($key));
	}


	public function testSaveEmptyString(): void
	{
		$secret = "";
		$key = $this->ns . 'redis-test-secred';

		$this->client->set($key, $secret);
		$this->client->expire($key, 10);

		\Tester\Assert::same($secret, $this->client->get($key));
	}


	public function testSaveWrongString(): void
	{
		\Tester\Assert::exception(function() {
			$secret = NULL;
			$key = $this->ns . 'redis-test-secred';

			$this->client->set($key, $secret);
			$this->client->expire($key, 10);

			\Tester\Assert::same($secret, $this->client->get($key));
		}, \Exception::class);
	}

}

(new RedisClientSet())->run();
