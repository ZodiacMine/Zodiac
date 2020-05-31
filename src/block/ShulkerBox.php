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

namespace pocketmine\block;

use pocketmine\block\tile\ShulkerBox as TileShulkerBox;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;

class ShulkerBox extends Transparent{
	/** @var int */
	protected $facing = 0;

	public function __construct(BlockIdentifier $idInfo, string $name, ?BlockBreakInfo $breakInfo = null){
		parent::__construct($idInfo, $name, $breakInfo ?? new BlockBreakInfo(6.0, BlockToolType::PICKAXE));
	}

	public function readStateFromWorld() : void{
		parent::readStateFromWorld();
		$tile = $this->pos->getWorldNonNull()->getTile($this->pos);
		if($tile instanceof TileShulkerBox){
			$this->facing = $tile->getFacing();
		}
	}

	public function writeStateToWorld() : void{
		parent::writeStateToWorld();
		//extra block properties storage hack
		$tile = $this->pos->getWorldNonNull()->getTile($this->pos);
		assert($tile instanceof TileShulkerBox);
		$tile->setFacing($this->facing);
	}

	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		$this->facing = $face;

		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		if($player instanceof Player){
			$shulkerBox = $this->pos->getWorldNonNull()->getTile($this->pos);
			if($shulkerBox instanceof TileShulkerBox){
				$player->setCurrentWindow($shulkerBox->getInventory());
			}
		}

		return true;
	}

	public function asItem() : Item{
		$item = parent::asItem();

		$tile = $this->pos->getWorldNonNull()->getTile($this->pos);
		if($tile instanceof TileShulkerBox){
			$nbt = $item->getNamedTag();
			$tile->saveItems($nbt);

			$item->setNamedTag($nbt);
		}

		return $item;
	}
}
