<?php

declare(strict_types = 1);

namespace Kdyby\Redis\Exception;

class RedisClientException extends \RuntimeException implements \Kdyby\Redis\Exception\IException
{

	/**
	 * @var string
	 */
	public $request;

	/**
	 * @var string
	 */
	public $response;

}
