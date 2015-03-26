<?php

/**
 * Test: Kdyby\Redis\SessionHandler.
 *
 * @testCase Kdyby\Redis\SessionHandlerTest
 * @author Filip Procházka <filip@prochazka.su>
 * @package Kdyby\Redis
 */

namespace KdybyTests\Redis;

use Kdyby\Redis\RedisClient;
use Kdyby\Redis\RedisSessionHandler;
use Nette;
use Tester;
use Tester\Assert;



require_once __DIR__ . '/../bootstrap.php';

/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class SessionHandlerTest extends AbstractRedisTestCase
{

	/**
	 * @dataProvider dataRepeatMe
	 * @group concurrency
	 */
	public function testConsistency()
	{
		$sessionId = md5(1);

		$result = $this->threadStress(function () use ($sessionId) {
			\Tracy\Debugger::log(getmypid());

			$handler = new RedisSessionHandler(new RedisClient());

			// read
			$_COOKIE[session_name()] = $sessionId;
			Assert::true($handler->open('path', session_name()));

			$session = array('counter' => 0);
			if ($data = $handler->read($sessionId)) {
				$session = unserialize($data);
			}

			// modify
			$session['counter'] += 1;
			usleep(100000);

			// write
			$handler->write($sessionId, serialize($session));
			$handler->close();
		});
		Assert::same(100, $result[Tester\Runner\Runner::PASSED]);

		$handler = new RedisSessionHandler($this->client);

		$_COOKIE[session_name()] = $sessionId;
		$handler->open('path', 'session_id');

		$data = $handler->read($sessionId);
		Assert::false(empty($data));

		$session = unserialize($data);
		Assert::true(is_array($session));
		Assert::true(array_key_exists('counter', $session));
		Assert::same(100, $session['counter']);

		$handler->close(); // unlock

		Assert::count(3, $this->client->keys('Nette.Session:*'));
	}



	/**
	 * @group concurrency
	 */
	public function testIntegration_existingSession()
	{
		$sessionId = md5(1);
		$session = self::createSession(array(session_name() => $sessionId, 'nette-browser' => $B = '1lm7e5iqsk'));

		$session->setHandler($handler = new SessionHandlerDecorator(new RedisSessionHandler($this->client)));
		$this->client->setupLockDuration(60, 20);

		// fake session
		$this->client->set('Nette.Session:' . $sessionId, '__NF|' . serialize(array('Time' => $T = time() - 1000, 'B' => $B)));

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

		Assert::same(array(
			array('open' => array('', 'PHPSESSID')),
			array('read' => array($sessionId)),
			array('write' => array($sessionId, '__NF|a:3:{s:4:"Time";i:' . $T . ';s:1:"B";s:10:"' . $B . '";s:4:"DATA";a:1:{s:7:"counter";a:1:{s:6:"visits";i:1;}}}')),
			array('close' => array()),
			array('open' => array('', 'PHPSESSID')),
			array('read' => array($sessionId)),
			array('write' => array($sessionId, '__NF|a:3:{s:4:"Time";i:' . $T . ';s:1:"B";s:10:"' . $B . '";s:4:"DATA";a:1:{s:7:"counter";a:1:{s:6:"visits";i:2;}}}')),
			array('close' => array()),
		), $handler->methods);

		Assert::count(3, $this->client->keys('Nette.Session:*'));
	}



	/**
	 * @group concurrency
	 */
	public function testIntegration_emptySession_regenerate()
	{
		$sessionId = md5(1);

		$session1 = self::createSession(array(session_name() => $sessionId)); // no browser, empty session
		$session1->setHandler($handler = new SessionHandlerDecorator(new RedisSessionHandler($client = $this->client)));
		$client->setupLockDuration(60, 20);

		$counter = $session1->getSection('counter');
		$counter->visits += 1;
		Assert::same(1, $counter->visits);

		// close session
		$session1->close();

		Assert::count(9, $handler->methods);

		// regenerate
		Assert::same(array('open' => array('', 'PHPSESSID')), $handler->methods[0]);
		Assert::same(array('read' => array($sessionId)), $handler->methods[1]);
		Assert::same(array('destroy' => array($sessionId)), $handler->methods[2]);
		Assert::match('%S%', $regeneratedId = $handler->methods[3]['write'][0]);
		Assert::match('__NF|a:2:{s:4:"Time";i:%S%;s:1:"B";s:10:"%S%";}', $handler->methods[3]['write'][1]);
		Assert::same(array('close' => array()), $handler->methods[4]);

		// open regenerated
		Assert::same(array('open' => array('', 'PHPSESSID')), $handler->methods[5]);
		Assert::same(array('read' => array($regeneratedId)), $handler->methods[6]);
		Assert::same($regeneratedId, $handler->methods[7]['write'][0]);
		Assert::match('__NF|a:3:{s:4:"Time";i:%S%;s:1:"B";s:10:"%S%";s:4:"DATA";a:1:{s:7:"counter";a:1:{s:6:"visits";i:1;}}}', $handler->methods[7]['write'][1]);
		Assert::same(array('close' => array()), $handler->methods[8]);

		Assert::notSame($sessionId, $regeneratedId);

		$session2 = self::createSession(array(session_name() => $regeneratedId, 'nette-browser' => $_SESSION['__NF']['B'])); // no browser, empty session
		$session2->setHandler($handler = new SessionHandlerDecorator(new RedisSessionHandler($client = new RedisClient())));
		$client->setupLockDuration(60, 20);

		$counter = $session2->getSection('counter');
		$counter->visits += 1;
		Assert::same(2, $counter->visits);

		// close session
		$session2->close();

		Assert::same(array(
			array('open' => array('', 'PHPSESSID')),
			array('read' => array($regeneratedId)),
			array('write' => array($regeneratedId, '__NF|a:3:{s:4:"Time";i:' . $_SESSION['__NF']['Time'] . ';s:1:"B";s:10:"' . $_SESSION['__NF']['B'] . '";s:4:"DATA";a:1:{s:7:"counter";a:1:{s:6:"visits";i:2;}}}')),
			array('close' => array()),
		), $handler->methods);

		Assert::count(5, $this->client->keys('Nette.Session:*'));
	}



	/**
	 * @group concurrency
	 */
	public function testIntegration_timeout()
	{
		$sessionId = md5(1);

		$client = $this->client;
		$client->setupLockDuration(50, 20);
		$client->lock('Nette.Session:' . $sessionId);

		sleep(3); // working for a looong time :)

		$session = self::createSession(array(session_name() => $sessionId));
		$session->setHandler($handler = new SessionHandlerDecorator(new RedisSessionHandler($client = new RedisClient())));
		$client->setupLockDuration(5, 2);

		Assert::exception(function () use ($session) {
			$counter = $session->getSection('counter');
			$counter->visits += 1;
			Assert::same(1, $counter->visits);
		}, 'Kdyby\Redis\SessionHandlerException', sprintf('Cannot work with non-locked session id %s: Lock couldn\'t be acquired. The locking mechanism is giving up. You should kill the request.', $sessionId));

		Assert::count(2, $this->client->keys('Nette.Session:*'));
	}



	/**
	 * @dataProvider dataRepeatMe
	 * @group concurrency
	 */
	public function testConsistency_Integration()
	{
		$sessionId = md5(1);

		$session = self::createSession(array(session_name() => $sessionId, 'nette-browser' => $B = '1lm7e5iqsk'));
		$session->setHandler($handler = new SessionHandlerDecorator(new RedisSessionHandler($client = new RedisClient())));

		// fake session
		$client->set('Nette.Session:' . $sessionId, '__NF|' . serialize(array('Time' => $T = time() - 1000, 'B' => $B)));

		// open session
		$counter = $session->getSection('counter');
		$counter->visits = 0;

		// explicitly close
		$session->close();

		Assert::same(array(
			array('open' => array('', 'PHPSESSID')),
			array('read' => array($sessionId)),
			array('write' => array($sessionId, '__NF|a:3:{s:4:"Time";i:' . $_SESSION['__NF']['Time'] . ';s:1:"B";s:10:"' . $_SESSION['__NF']['B'] . '";s:4:"DATA";a:1:{s:7:"counter";a:1:{s:6:"visits";i:0;}}}')),
			array('close' => array()),
		), $handler->methods);

		// only testing the behaviour of high concurency for one request, without regenerating the session id
		// 30 processes will be started, but every one of them will work for at least 1 second
		// a fuckload (~66%) of the processes should actually fail, because the timeout for lock acquire is 10 sec
		$result = $this->threadStress(function () use ($sessionId, $B) {
			$_COOKIE[session_name()] = $sessionId;
			$_COOKIE['nette-browser'] = $B;

			$session = new Nette\Http\Session(
				new Nette\Http\Request(
					new Nette\Http\UrlScript('http://www.kdyby.org'),
					NULL, array(), array(), array(session_name() => $sessionId, 'nette-browser' => $B), array(), 'GET'
				),
				new Nette\Http\Response()
			);

			$session->setHandler($handler = new SessionHandlerDecorator(new RedisSessionHandler($client = new RedisClient())));
			$client->setupLockDuration(60, 10);
			$handler->log = TRUE;

			$counter = $session->getSection('counter');
			$counter->visits += 1;
			sleep(1); // hard work after session is opened ~ 1s

			$session->close(); // explicit close with unlock
		}, 100, 30); // silence, I kill you!

		self::assertRange(30, 45, $result[Tester\Runner\Runner::PASSED]);

		// hard unlock
		$client->rPush('Nette.Session:' . $sessionId . ':lock', 1);

		// open session for visits verify, for second time
		$counter = $session->getSection('counter');
		self::assertRange(30, 45, $counter->visits);

		$session->close(); // unlocking drops the key

		Assert::count(3, $this->client->keys('Nette.Session:*'));
	}



	/**
	 * @return array
	 */
	public function dataRepeatMe()
	{
		return array_fill(0, 10, array());
	}



	/**
	 * @param array $cookies
	 * @return Nette\Http\Session
	 */
	private static function createSession($cookies = array())
	{
		foreach ($cookies as $key => $val) {
			$_COOKIE[$key] = $val;
		}

		return new Nette\Http\Session(
			new Nette\Http\Request(
				new Nette\Http\UrlScript('http://www.kdyby.org'),
				NULL,
				array(),
				array(),
				$cookies,
				array(),
				'GET'
			),
			new Nette\Http\Response()
		);
	}



	protected static function assertRange($expectedLower, $expectedHigher, $actual)
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

\run(new SessionHandlerTest());
