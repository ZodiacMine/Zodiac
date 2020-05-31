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

namespace pocketmine\block\tile;

use pocketmine\block\inventory\ShulkerBoxInventory;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\world\World;
use function abs;

class ShulkerBox extends Spawnable implements Container, Nameable{
	use NameableTrait {
		addAdditionalSpawnData as addNameSpawnData;
	}
	use ContainerTrait {
		onBlockDestroyedHook as private;
	}

	public const TAG_FACING = "facing";

	/** @var ShulkerBoxInventory */
	protected $inventory;

	/** @var int */
	private $facing;

	public function __construct(World $world, Vector3 $pos){
		parent::__construct($world, $pos);
		$this->inventory = new ShulkerBoxInventory($this->pos);
	}

	public function readSaveData(CompoundTag $nbt) : void{
		$this->facing = $nbt->getByte(self::TAG_FACING);

		$this->loadName($nbt);
		$this->loadItems($nbt);
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		$nbt->setByte(self::TAG_FACING, $this->facing);

		$this->saveName($nbt);
		$this->saveItems($nbt);
	}

	public function close() : void{
		if(!$this->closed){
			$this->inventory->removeAllViewers();
			$this->inventory = null;

			parent::close();
		}
	}

	protected function onBlockDestroyedHook() : void{
		$this->getRealInventory()->clearAll();
	}

	public function copyDataFromItem(Item $item) : void{
		$this->loadItems($item->getNamedTag());
	}

	public function copyDataToItem(Item $item) : void{
		$nbt = $item->getNamedTag();

		$this->saveItems($nbt);
		$item->setNamedTag($nbt);
	}

	/**
	 * @return ShulkerBoxInventory
	 */
	public function getInventory(){
		return $this->inventory;
	}

	/**
	 * @return ShulkerBoxInventory
	 */
	public function getRealInventory(){
		return $this->inventory;
	}

	public function getDefaultName() : string{
		return "Shulker Box";
	}

	public function getFacing() : int{
		return $this->facing;
	}

	public function setFacing(int $facing) : void{
		$this->facing = $facing;
	}

	protected function addAdditionalSpawnData(CompoundTag $nbt) : void{
		$nbt->setByte(self::TAG_FACING, $this->facing);

		$this->addNameSpawnData($nbt);
	}
}
