<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.txt that was distributed with this source code.
 */

namespace Kdyby\Redis\Driver;

use Kdyby;
use Nette;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class PhpRedisDriver extends \Redis implements Kdyby\Redis\IRedisDriver
{

	/**
	 * {@inheritdoc}
	 */
	public function connect($host, $port = NULL, $timeout = 0)
	{
		$args = func_get_args();
		return call_user_func_array('parent::connect', $args);
	}



	/**
	 * {@inheritdoc}
	 */
	public function select($database)
	{
		$args = func_get_args();
		return call_user_func_array('parent::select', $args);
	}



	/**
	 * {@inheritdoc}
	 */
	public function script($command, $script = NULL)
	{
		$args = func_get_args();
		return call_user_func_array('parent::script', $args);
	}



	/**
	 * {@inheritdoc}
	 */
	public function evalsha($scriptSha, $argsArray = array(), $numKeys = 0)
	{
		$args = func_get_args();
		return call_user_func_array('parent::evalsha', $args);
	}

}
