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

namespace pocketmine\inventory;

use pocketmine\entity\Human;
use pocketmine\item\Item;

class PlayerOffHandInventory extends BaseInventory{
	/** @var Human */
	protected $holder;

	public function __construct(Human $holder){
		$this->holder = $holder;
		parent::__construct(1);
	}

	public function getHolder() : Human{
		return $this->holder;
	}

	public function setItemInHand(Item $item) : void{
		$this->setItem(0, $item);
	}

	protected function onSlotChange(int $index, Item $before) : void{
		foreach($this->listeners as $listener){
			$listener->onSlotChange($this, $index, $before);
		}
		foreach($this->viewers as $viewer){
			$viewer->getNetworkSession()->getInvManager()->syncContents($this); // Sync contents of this inventory instead of slot... #blamemojang?
		}
		foreach($this->holder->getViewers() as $viewer){
			$viewer->getNetworkSession()->onMobEquipmentChange($this->holder);
		}
	}

	public function getItemInHand() : Item{
		return $this->getItem(0);
	}

	public function getHeldItemIndex() : int{
		return 0;
	}

	public function setSize(int $size) : void{
		throw new \BadMethodCallException("OffHand can only carry one item at a time");
	}
}
