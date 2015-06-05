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
class PhpRedisSharedDriver extends PhpRedisDriver
{

	/**
	 * @var integer
	 */
	private $database = 0;

	/**
	 * {@inheritdoc}
	 */
	public function select($database)
	{
		$args = func_get_args();
		$result = call_user_func_array('parent::select', $args);

		if ($result === TRUE) {
			$this->database = (int) $database;
		}

		return $result;
	}



	/**
	 * Get actual selected database
	 * @return int
	 */
	public function getDatabase()
	{
		return $this->database;
	}

}
