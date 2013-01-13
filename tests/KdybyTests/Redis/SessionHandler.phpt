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
	 * @group concurrency
	 */
	public function testConsistency()
	{
		$userId = md5(1);

		$this->threadStress(function () use ($userId) {
			$handler = new RedisSessionHandler(new RedisClient());

			Nette\Diagnostics\Debugger::$logDirectory = __DIR__;
			Nette\Diagnostics\Debugger::log(getmypid());

			// read
			$handler->open('path', 'session_id');
			$session = array('counter' => 0);
			if ($data = $handler->read($userId)) {
				$session = unserialize($data);
			}

			// modify
			$session['counter'] += 1;

			// write
			$handler->write($userId, serialize($session));
			$handler->close();
		});

		$handler = new RedisSessionHandler($this->client);
		$handler->open('path', 'session_id');

		$data = $handler->read($userId);
		Assert::false(empty($data));

		$session = unserialize($data);
		Assert::true(is_array($session));
		Assert::true(array_key_exists('counter', $session));
		Assert::same(100, $session['counter']);
	}

}

\run(new SessionHandlerTest());
