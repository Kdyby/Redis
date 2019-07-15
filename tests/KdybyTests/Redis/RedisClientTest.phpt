<?php

declare(strict_types = 1);

/**
 * Test: Kdyby\Redis\RedisClient.
 *
 * @testCase Kdyby\Redis\RedisClientTest
 */

namespace KdybyTests\Redis;

use Kdyby\Redis\RedisClient;
use Nette;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';



class RedisClientTest extends \KdybyTests\Redis\AbstractRedisTestCase
{

	/**
	 * @var string
	 */
	private $ns;

	public function setUp(): void
	{
		parent::setUp();
		$this->ns = Nette\Utils\Random::generate();
	}

	public function testPrimitives(): void
	{
		$secret = "I'm batman";
		$key = $this->ns . 'redis-test-secred';

		$this->client->set($key, $secret);
		$this->client->expire($key, 10);

		Assert::same($secret, $this->client->get($key));
	}

	public function testLargeData(): void
	{
		$data = \str_repeat('Kdyby', (int) 1e6);
		$this->client->set('large', $data);
		Assert::same($data, $this->client->get('large'));
	}

	public function testNullReply(): void
	{
		Assert::false($this->client->get('nonexistingkey'));
	}

	public function testExec(): void
	{
		Assert::equal(1, $this->client->sadd('test:key', 'item1'));
		Assert::equal(1, $this->client->sadd('test:key', 'item2'));

		Assert::equal('OK', $this->client->multi());
		Assert::equal('QUEUED', $this->client->sMembers('test:key'));
		Assert::equal('QUEUED', $this->client->sMembers('test:key'));

		[$first, $second] = $this->client->exec();
		\sort($first);
		\sort($second);
		Assert::equal(['item1', 'item2'], $first);
		Assert::equal(['item1', 'item2'], $second);
	}

	public function testExecWithClosure(): void
	{
		Assert::equal(1, $this->client->sadd('test:key', 'item1'));
		Assert::equal(1, $this->client->sadd('test:key', 'item2'));

		[$first, $second] = $this->client->multi(static function (RedisClient $client): void {
			$client->sMembers('test:key');
			$client->sMembers('test:key');
		});

		\sort($first);
		\sort($second);
		Assert::equal(['item1', 'item2'], $first);
		Assert::equal(['item1', 'item2'], $second);
	}

	public function testExecException(): void
	{
		$other = new RedisClient();
		$client = $this->client;

		Assert::exception(static function () use ($other, $client): void {
			$client->set('foo', 1);
			$client->watch('foo');

			$client->multi();
			$other->del('foo');
			$client->incr('foo');
			$client->exec();
		}, 'Kdyby\Redis\Exception\TransactionException');
	}

	public function testMagicAccessors(): void
	{
		Assert::false(isset($this->client->nemam));
		$this->client->nemam = 'nemam';
		Assert::true(isset($this->client->nemam));
		Assert::same('nemam', $this->client->nemam);
		unset($this->client->nemam);
		Assert::false(isset($this->client->nemam));
	}

	public function testInfo(): void
	{
		Assert::true(\is_array($this->client->info()));
		Assert::same('master', $this->client->info('role'));

		$this->client->set('foo', 'bar');
		Assert::same(['keys' => '1', 'expires' => '0'], \array_diff_key($this->client->info('db0'), ['avg_ttl' => TRUE]));
	}

	public function testSelect(): void
	{
		Assert::same(0, $this->client->getDatabase());
		Assert::true($this->client->select(1));
		Assert::same(1, $this->client->getDatabase());
		Assert::true($this->client->select(0));
		Assert::same(0, $this->client->getDatabase());
	}

}

(new RedisClientTest())->run();
