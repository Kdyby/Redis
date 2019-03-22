<?php

declare(strict_types = 1);

return [
	\Kdyby\Redis\ConnectionException::class         => \Kdyby\Redis\Exception\ConnectionException::class,
	\Kdyby\Redis\Exception::class                   => \Kdyby\Redis\Exception\IException::class,
	\Kdyby\Redis\InvalidArgumentException::class    => \Kdyby\Redis\Exception\InvalidArgumentException::class,
	\Kdyby\Redis\LockException::class               => \Kdyby\Redis\Exception\LockException::class,
	\Kdyby\Redis\MissingExtensionException::class   => \Kdyby\Redis\Exception\MissingExtensionException::class,
	\Kdyby\Redis\RedisClientException::class        => \Kdyby\Redis\Exception\RedisClientException::class,
	\Kdyby\Redis\SessionHandlerException::class     => \Kdyby\Redis\Exception\SessionHandlerException::class,
	\Kdyby\Redis\TransactionException::class        => \Kdyby\Redis\Exception\TransactionException::class,
];
