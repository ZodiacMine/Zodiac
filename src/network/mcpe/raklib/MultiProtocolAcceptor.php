<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\network\mcpe\raklib;

use raklib\server\ProtocolAcceptor;
use function array_values;
use function in_array;

final class MultiProtocolAcceptor implements ProtocolAcceptor{

	/** @var int[] */
	private $protocolList;

	public function __construct(int ...$protocolList){
		if(count($protocolList) === 0){
			throw new \InvalidArgumentException("Expected 1 or more protocols, got 0");
		}

		$this->protocolList = array_values($protocolList);
	}

	public function accepts(int $protocolVersion) : bool{
		return in_array($protocolVersion, $this->protocolList, true);
	}

	public function getPrimaryVersion() : int{
		return $this->protocolList[0];
	}
}
