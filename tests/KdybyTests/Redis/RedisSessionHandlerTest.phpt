<?php

declare(strict_types = 1);

/**
 * Test: Kdyby\Redis\SessionHandler.
 *
 * @testCase Kdyby\Redis\RedisSessionHandlerTest
 */

namespace KdybyTests\Redis;

use Kdyby\Redis\RedisClient;
use Kdyby\Redis\RedisSessionHandler;
use Nette;
use Tester\Assert;
use Tracy\Debugger;

require_once __DIR__ . '/../bootstrap.php';

class RedisSessionHandlerTest extends \KdybyTests\Redis\AbstractRedisTestCase
{

	/**
	 * @dataProvider dataRepeatMe
	 * @group concurrency
	 */
	public function testConsistency(): void
	{
		$sessionId = \md5('1');

		$result = $this->threadStress(static function () use ($sessionId): void {
			Debugger::log(\getmypid());

			$handler = new RedisSessionHandler(new RedisClient());

			// read
			$_COOKIE[\session_name()] = $sessionId;
			Assert::true($handler->open('path', \session_name()));

			$session = ['counter' => 0];
			$data = $handler->read($sessionId);
			if ($data) {
				$session = \unserialize($data);
			}

			// modify
			$session['counter'] += 1;
			\usleep(100000);

			// write
			$handler->write($sessionId, \serialize($session));
			$handler->close();
		});
		Assert::same(100, $result->getPassingResults());

		$handler = new RedisSessionHandler($this->client);

		$_COOKIE[\session_name()] = $sessionId;
		$handler->open('path', 'session_id');

		$data = $handler->read($sessionId);
		Assert::false(empty($data));

		$session = \unserialize($data);
		Assert::true(\is_array($session));
		Assert::true(\array_key_exists('counter', $session));
		Assert::same(100, $session['counter']);

		$handler->close(); // unlock

		Assert::count(1, $this->client->keys('Nette.Session:*'));
	}

	/**
	 * @group concurrency
	 */
	public function testIntegrationExistingSession(): void
	{
		$sessionId = \md5('1');
		$session = self::createSession([\session_name() => $sessionId]);

		$session->setHandler($handler = new SessionHandlerDecorator(new RedisSessionHandler($this->client)));
		$this->client->setupLockDuration(60, 20);

		// fake session
		$time = \time() - 1000;
		$this->client->set('Nette.Session:' . $sessionId, '__NF|' . \serialize(['Time' => $time]));

		$counter = $session->getSection('counter');
		$counter->visits += 1;
		Assert::same(1, $counter->visits);

		// close session
		$session->close();

		// reopen the session "on next request"
		$counter = $session->getSection('counter');
		$counter->visits += 1;
		Assert::same(2, $counter->visits);

		// close session
		$session->close();

		Assert::same(['open', '/var/lib/php/sessions', 'PHPSESSID'], $handler->series[0][0]);
		Assert::same(['read', $sessionId], $handler->series[0][1]);
		Assert::same('write', $handler->series[0][2][0]);
		$unserialize022 = \unserialize(\str_replace('__NF|', '', $handler->series[0][2][2]));
		Assert::same($time, $unserialize022['Time']);
		Assert::same([
			'counter' => [
				'visits' => 1,
			],
		], $unserialize022['DATA']);

		Assert::same(['open', '/var/lib/php/sessions', 'PHPSESSID'], $handler->series[1][0]);
		Assert::same(['read', $sessionId], $handler->series[1][1]);
		Assert::same('write', $handler->series[1][2][0]);
		$unserialize122 = \unserialize(\str_replace('__NF|', '', $handler->series[1][2][2]));
		Assert::same($time, $unserialize122['Time']);
		Assert::same([
			'counter' => [
				'visits' => 2,
			],
		], $unserialize122['DATA']);

		Assert::count(1, $this->client->keys('Nette.Session:*'));
	}

	/**
	 * @group concurrency
	 */
	public function testIntegrationEmptySessionRegenerate(): void
	{
		$sessionId = \md5('1');

		$session1 = self::createSession([\session_name() => $sessionId]); // no browser, empty session
		$session1->setHandler($handler1 = new SessionHandlerDecorator(new RedisSessionHandler($client = $this->client)));
		$client->setupLockDuration(60, 20);

		$counter = $session1->getSection('counter');
		$counter->visits += 1;
		Assert::same(1, $counter->visits);

		$session1->close();

		if (\count($handler1->series) === 2) {
			$secondVisit = $handler1->series[1][2];

		} else {
			$secondVisit = $handler1->series[2][2];
		}

		// open & destroy
		Assert::same(['open', '/var/lib/php/sessions', 'PHPSESSID'], $handler1->series[0][0]);
		Assert::same(['read', $sessionId], $handler1->series[0][1]);
		Assert::same(['destroy', $sessionId], $handler1->series[0][2]);

		// open regenerated
		Assert::same(['open', '/var/lib/php/sessions', 'PHPSESSID'], $handler1->series[1][0]);
		Assert::same('read', $handler1->series[1][1][0]);
		Assert::match('%S%', $regeneratedId = $handler1->series[1][1][1]);
		Assert::same('write', $secondVisit[0]);

		// close session
		Assert::same('write', $secondVisit[0]);
		$unserialize122 = \unserialize(\str_replace('__NF|', '', $secondVisit[2]));
		Assert::same([
			'counter' => [
				'visits' => 1,
			],
		], $unserialize122['DATA']);

		Assert::notSame($sessionId, $regeneratedId);

		$session2 = self::createSession([\session_name() => $regeneratedId]); // no browser, empty session
		$session2->setHandler($handler2 = new SessionHandlerDecorator(new RedisSessionHandler($client = new RedisClient())));
		$client->setupLockDuration(60, 20);

		$counter = $session2->getSection('counter');
		$counter->visits += 1;
		Assert::same(2, $counter->visits);

		// close session
		$session2->close();

		Assert::same(['open', '/var/lib/php/sessions', 'PHPSESSID'], $handler2->series[0][0]);
		Assert::same(['read', $regeneratedId], $handler2->series[0][1]);
		Assert::same('write', $handler2->series[0][2][0]);

		$unserialize022 = \unserialize(\str_replace('__NF|', '', $handler2->series[0][2][2]));
		Assert::same([
			'counter' => [
				'visits' => 2,
			],
		], $unserialize022['DATA']);

		Assert::count(1, $this->client->keys('Nette.Session:*'));
	}

	/**
	 * @group concurrency
	 */
	public function testIntegrationTimeout(): void
	{
		$sessionId = \md5('1');

		$client = $this->client;
		$client->setupLockDuration(50, 20);
		$client->lock('Nette.Session:' . $sessionId);

		\sleep(3); // working for a looong time :)

		$session = self::createSession([\session_name() => $sessionId]);
		$session->setHandler(new SessionHandlerDecorator(new RedisSessionHandler($client = new RedisClient())));
		$client->setupLockDuration(5, 2);

		Assert::exception(static function () use ($session): void {
			$counter = $session->getSection('counter');
			$counter->visits += 1;
			Assert::same(1, $counter->visits);
		}, 'Kdyby\Redis\Exception\SessionHandlerException', \sprintf('Cannot work with non-locked session id %s: Lock couldn\'t be acquired. The locking mechanism is giving up. You should kill the request.', $sessionId));

		Assert::count(1, $this->client->keys('Nette.Session:*'));
	}

	/**
	 * @dataProvider dataRepeatMe
	 * @group concurrency
	 */
	public function testConsistencyIntegration(): void
	{
		$sessionId = \md5('1');

		$session = self::createSession([\session_name() => $sessionId]);
		$session->setHandler($handler = new SessionHandlerDecorator(new RedisSessionHandler($client = new RedisClient())));

		// fake session
		$time = \time() - 1000;
		$client->set('Nette.Session:' . $sessionId, '__NF|' . \serialize(['Time' => $time]));

		// open session
		$counter = $session->getSection('counter');
		$counter->visits = 0;

		// explicitly close
		$session->close();

		Assert::same(['open', '/var/lib/php/sessions', 'PHPSESSID'], $handler->series[0][0]);
		Assert::same(['read', $sessionId], $handler->series[0][1]);
		Assert::same('write', $handler->series[0][2][0]);

		$unserialize022 = \unserialize(\str_replace('__NF|', '', $handler->series[0][2][2]));
		Assert::same($time, $unserialize022['Time']);
		Assert::same([
			'counter' => [
				'visits' => 0,
			],
		], $unserialize022['DATA']);

		// only testing the behaviour of high concurency for one request, without regenerating the session id
		// 30 processes will be started, but every one of them will work for at least 1 second
		// a fuckload (~66%) of the processes should actually fail, because the timeout for lock acquire is 10 sec
		$result = $this->threadStress(static function () use ($sessionId): void {
			$_COOKIE[\session_name()] = $sessionId;

			$arguments = [
				new Nette\Http\UrlScript('http://www.kdyby.org'),
				NULL,
				[],
				[],
				[\session_name() => $sessionId],
				[],
				'GET',
			];
			if (\class_exists(\Nette\Http\UrlImmutable::class)) {
				unset($arguments[1]);
			}

			$session = new Nette\Http\Session(
				new Nette\Http\Request(
					...$arguments
				),
				new Nette\Http\Response()
			);

			$session->setHandler($handler = new SessionHandlerDecorator(new RedisSessionHandler($client = new RedisClient())));
			$client->setupLockDuration(60, 10);
			$handler->log = TRUE;

			$counter = $session->getSection('counter');
			$counter->visits += 1;
			\sleep(1); // hard work after session is opened ~ 1s

			$session->close(); // explicit close with unlock
		}, 100, 30); // silence, I kill you!

		self::assertRange(30, 40, $result->getPassingResults());

			// hard unlock
		$client->del('Nette.Session:' . $sessionId . ':lock');

		// open session for visits verify, for second time
		$counter = $session->getSection('counter');
		self::assertRange(30, 40, $counter->visits);

		$session->close(); // unlocking drops the key

		Assert::count(1, $this->client->keys('Nette.Session:*'));
	}

	/**
	 * @return array<mixed>
	 */
	public function dataRepeatMe(): array
	{
		return \array_fill(0, 10, []);
	}

	/**
	 * @param array<mixed> $cookies
	 * @return \Nette\Http\Session
	 */
	private static function createSession(array $cookies = []): Nette\Http\Session
	{
		foreach ($cookies as $key => $val) {
			$_COOKIE[$key] = $val;
		}

		$arguments = [
			new Nette\Http\UrlScript('http://www.kdyby.org'),
			NULL,
			[],
			[],
			$cookies,
			[],
			'GET',
		];
		if (\class_exists(\Nette\Http\UrlImmutable::class)) {
			unset($arguments[1]);
		}

		return new Nette\Http\Session(
			new Nette\Http\Request(
				...$arguments
			),
			new Nette\Http\Response()
		);
	}

	protected static function assertRange(int $expectedLower, int $expectedHigher, int $actual): void
	{
		Assert::$counter++;
		if ($actual > $expectedHigher) {
			Assert::fail('%1 should be lower than %2', $actual, $expectedHigher);
		}
		if ($actual < $expectedLower) {
			Assert::fail('%1 should be higher than %2', $actual, $expectedLower);
		}
	}

}

(new RedisSessionHandlerTest())->run();
