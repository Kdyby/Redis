<?php

/**
 * This file is part of the Kdyby (http://www.kdyby.org)
 *
 * Copyright (c) 2008 Filip Procházka (filip@prochazka.su)
 *
 * For the full copyright and license information, please view the file license.md that was distributed with this source code.
 */

namespace Kdyby\Redis;

use Nette;



interface IMultiReadStorage extends Nette\Caching\IStorage
{

	/**
	 * Read multiple entries from cache
	 *
	 * @param array $keys
	 * @return array
	 */
	function multiRead(array $keys);

}
