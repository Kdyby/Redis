<?php
declare(strict_types = 1);

namespace KdybyTests\Redis;

use Kdyby\Redis\IRedisDriver;



class NullDriver extends \Redis implements IRedisDriver
{

	/**
	 * {@inheritdoc}
	 */
	public function isConnected()
	{
		return TRUE;
	}

}
