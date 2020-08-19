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

namespace pocketmine\world\generator\object;

use pocketmine\block\Block;

class OreType{
	/** @var Block */
	public $material;
	/** @var Block */
	public $replaces;
	/** @var int */
	public $clusterCount;
	/** @var int */
	public $clusterSize;
	/** @var int */
	public $maxHeight;
	/** @var int */
	public $minHeight;

	public function __construct(Block $material, Block $replaces, int $clusterCount, int $clusterSize, int $minHeight, int $maxHeight){
		$this->material = $material;
		$this->replaces = $replaces;
		$this->clusterCount = $clusterCount;
		$this->clusterSize = $clusterSize;
		$this->maxHeight = $maxHeight;
		$this->minHeight = $minHeight;
	}
}
