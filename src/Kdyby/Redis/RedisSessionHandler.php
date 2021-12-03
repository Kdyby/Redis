<?php

declare(strict_types = 1);

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Redis;

/**
 * Redis session handler allows to store session in redis using Nette\Http\Session.
 *
 * <code>
 * $session->setStorage(new Kdyby\Redis\RedisSessionHandler($redisClient));
 * </code>
 */
class RedisSessionHandler implements \SessionHandlerInterface
{

	use \Nette\SmartObject;

	/** @internal cache structure */
	public const NS_NETTE = 'Nette.Session:';

	/**
	 * @var array
	 */
	private $ssIds = [];

	/**
	 * @var \Kdyby\Redis\RedisClient
	 */
	private $client;

	/**
	 * @var \Nette\Http\Session
	 */
	private $session;

	/**
	 * @var int
	 */
	private $ttl;

	public function __construct(\Kdyby\Redis\RedisClient $redisClient)
	{
		$this->client = $redisClient;
	}

	/**
	 * @internal
	 * @param \Nette\Http\Session $session
	 */
	public function bind(\Nette\Http\Session $session): \Kdyby\Redis\RedisSessionHandler
	{
		$this->session = $session;
		$session->setHandler($this);
		return $this;
	}

	protected function getTtl(): int
	{
		if ($this->ttl === NULL) {
			if ($this->session !== NULL) {
				$options = $this->session->getOptions();
				$ttl = \min(\array_filter([$options['cookie_lifetime'], $options['gc_maxlifetime']], static function ($v) {
					return $v > 0;
				})) ?: 0;

			} else {
				$ttl = (int) \ini_get('session.gc_maxlifetime');
			}

			if ($ttl <= 0) {
				throw new \InvalidArgumentException('PHP settings "cookie_lifetime" or "gc_maxlifetime" must be greater than 0');
			}

			$this->ttl = $ttl;
		}

		return $this->ttl;
	}

	public function setTtl(int $ttl): void
	{
		$this->ttl = \max($ttl, 0);
	}

	// phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingReturnTypeHint,SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	public function open($savePath, $sessionName): bool
	{
		return TRUE;
	}

	/**
	 * @param string $id
	 * @throws \Kdyby\Redis\Exception\SessionHandlerException
	 * @return string
	 */
	#[\ReturnTypeWillChange]
	public function read($id): string
	{
		return (string) $this->client->get($this->lock($id));
	}

	// phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingReturnTypeHint,SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	public function write($id, $data): bool
	{
		if (!isset($this->ssIds[$id])) {
			return FALSE;
		}

		return $this->client->setEX($this->formatKey($id), $this->getTtl(), $data);
	}

	/**
	 * @param string $id
	 * @throws \Exception
	 */
	// phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingReturnTypeHint,SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	public function destroy($id): bool
	{
		if (!isset($this->ssIds[$id])) {
			return FALSE;
		}

		$key = $this->formatKey($id);
		$this->client->multi(static function (\Kdyby\Redis\RedisClient $client) use ($key): void {
			$client->del($key);
			$client->unlock($key);
		});

		return TRUE;
	}

	public function close(): bool
	{
		foreach ($this->ssIds as $key) {
			$this->client->unlock($key);
		}
		$this->ssIds = [];

		return TRUE;
	}

	// phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingReturnTypeHint,SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
	public function gc($maxLifeTime): int
	{
		return 1;
	}

	protected function lock(string $id): string
	{
		try {
			$key = $this->formatKey($id);
			$this->client->lock($key);
			$this->ssIds[$id] = $key;

			return $key;

		} catch (\Kdyby\Redis\Exception\LockException $e) {
			throw new \Kdyby\Redis\Exception\SessionHandlerException(\sprintf('Cannot work with non-locked session id %s: %s', $id, $e->getMessage()), 0, $e);
		}
	}

	private function formatKey(string $id): string
	{
		return self::NS_NETTE . $id;
	}

}
