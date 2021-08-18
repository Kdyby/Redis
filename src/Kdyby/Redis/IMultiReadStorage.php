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

interface IMultiReadStorage extends \Nette\Caching\Storage
{

	/**
	 * Read multiple entries from cache
	 *
	 * @param array<mixed> $keys
	 * @return array<mixed>
	 */
	public function multiRead(array $keys): array;

}
