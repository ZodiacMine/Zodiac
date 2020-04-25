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

namespace pocketmine\network\mcpe\convert;

use pocketmine\crafting\CraftingGrid;
use pocketmine\inventory\transaction\action\CreateItemAction;
use pocketmine\inventory\transaction\action\DestroyItemAction;
use pocketmine\inventory\transaction\action\DropItemAction;
use pocketmine\inventory\transaction\action\InventoryAction;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\NetworkInventoryAction;
use pocketmine\network\mcpe\protocol\types\recipe\RecipeIngredient;
use pocketmine\player\Player;

class TypeConverter{
	private const DAMAGE_TAG = "Damage"; //TAG_Int
	private const DAMAGE_TAG_CONFLICT_RESOLUTION = "___Damage_ProtocolCollisionResolution___";

	/** @var self|null */
	private static $instance;

	private function __construct(){
		//NOOP
	}

	public static function getInstance() : self{
		if(self::$instance === null){
			self::$instance = new self;
		}
		return self::$instance;
	}

	public static function setInstance(self $instance) : void{
		self::$instance = $instance;
	}

	public function coreItemStackToRecipeIngredient(Item $itemStack) : RecipeIngredient{
		$meta = $itemStack->getMeta();
		return new RecipeIngredient($itemStack->getId(), $meta === -1 ? 0x7fff : $meta, $itemStack->getCount());
	}

	public function recipeIngredientToCoreItemStack(RecipeIngredient $ingredient) : Item{
		$meta = $ingredient->getMeta();
		return ItemFactory::getInstance()->get($ingredient->getId(), $meta === 0x7fff ? -1 : $meta, $ingredient->getCount());
	}

	public function coreItemStackToNet(Item $itemStack) : ItemStack{
		$nbt = null;
		if($itemStack->hasNamedTag()){
			$nbt = clone $itemStack->getNamedTag();
		}
		if($itemStack instanceof Durable and $itemStack->getDamage() > 0){
			if($nbt !== null){
				if(($existing = $nbt->getTag(self::DAMAGE_TAG)) !== null){
					$nbt->removeTag(self::DAMAGE_TAG);
					$nbt->setTag(self::DAMAGE_TAG_CONFLICT_RESOLUTION, $existing);
				}
			}else{
				$nbt = new CompoundTag();
			}
			$nbt->setInt(self::DAMAGE_TAG, $itemStack->getDamage());
		}
		$id = $itemStack->getId();
		$meta = $itemStack->getMeta();

		return new ItemStack(
			$id,
			$meta === -1 ? 0x7fff : $meta,
			$itemStack->getCount(),
			$nbt,
			[],
			[],
			$id === ItemIds::SHIELD ? 0 : null
		);
	}

	public function netItemStackToCore(ItemStack $itemStack) : Item{
		$compound = $itemStack->getNbt();
		$meta = $itemStack->getMeta();

		if($compound !== null){
			$compound = clone $compound;
			if($compound->hasTag(self::DAMAGE_TAG, IntTag::class)){
				$meta = $compound->getInt(self::DAMAGE_TAG);
				$compound->removeTag(self::DAMAGE_TAG);
				if($compound->count() === 0){
					$compound = null;
					goto end;
				}
			}
			if(($conflicted = $compound->getTag(self::DAMAGE_TAG_CONFLICT_RESOLUTION)) !== null){
				$compound->removeTag(self::DAMAGE_TAG_CONFLICT_RESOLUTION);
				$compound->setTag(self::DAMAGE_TAG, $conflicted);
			}
		}

		end:
		return ItemFactory::getInstance()->get(
			$itemStack->getId(),
			$meta !== 0x7fff ? $meta : -1,
			$itemStack->getCount(),
			$compound
		);
	}

	/**
	 * @throws \UnexpectedValueException
	 */
	public function createInventoryAction(NetworkInventoryAction $action, Player $player) : ?InventoryAction{
		$old = TypeConverter::getInstance()->netItemStackToCore($action->oldItem);
		$new = TypeConverter::getInstance()->netItemStackToCore($action->newItem);
		if($old->equalsExact($new)){
			//filter out useless noise in 1.13
			return null;
		}
		switch($action->sourceType){
			case NetworkInventoryAction::SOURCE_CONTAINER:
				if($action->windowId === ContainerIds::UI and $action->inventorySlot > 0){
					if($action->inventorySlot === 50){
						return null; //useless noise
					}
					if($action->inventorySlot >= 28 and $action->inventorySlot <= 31){
						$window = $player->getCraftingGrid();
						if($window->getGridWidth() !== CraftingGrid::SIZE_SMALL){
							throw new \UnexpectedValueException("Expected small crafting grid");
						}
						$slot = $action->inventorySlot - 28;
					}elseif($action->inventorySlot >= 32 and $action->inventorySlot <= 40){
						$window = $player->getCraftingGrid();
						if($window->getGridWidth() !== CraftingGrid::SIZE_BIG){
							throw new \UnexpectedValueException("Expected big crafting grid");
						}
						$slot = $action->inventorySlot - 32;
					}else{
						throw new \UnexpectedValueException("Unhandled magic UI slot offset $action->inventorySlot");
					}
				}else{
					$window = $player->getNetworkSession()->getInvManager()->getWindow($action->windowId);
					$slot = $action->inventorySlot;
				}
				if($window !== null){
					return new SlotChangeAction($window, $slot, $old, $new);
				}

				throw new \UnexpectedValueException("No open container with window ID $action->windowId");
			case NetworkInventoryAction::SOURCE_WORLD:
				if($action->inventorySlot !== NetworkInventoryAction::ACTION_MAGIC_SLOT_DROP_ITEM){
					throw new \UnexpectedValueException("Only expecting drop-item world actions from the client!");
				}

				return new DropItemAction($new);
			case NetworkInventoryAction::SOURCE_CREATIVE:
				switch($action->inventorySlot){
					case NetworkInventoryAction::ACTION_MAGIC_SLOT_CREATIVE_DELETE_ITEM:
						return new DestroyItemAction($new);
					case NetworkInventoryAction::ACTION_MAGIC_SLOT_CREATIVE_CREATE_ITEM:
						return new CreateItemAction($new);
					default:
						throw new \UnexpectedValueException("Unexpected creative action type $action->inventorySlot");

				}
			case NetworkInventoryAction::SOURCE_TODO:
				//These types need special handling.
				switch($action->windowId){
					case NetworkInventoryAction::SOURCE_TYPE_CRAFTING_RESULT:
					case NetworkInventoryAction::SOURCE_TYPE_CRAFTING_USE_INGREDIENT:
						return null;
				}

				//TODO: more stuff
				throw new \UnexpectedValueException("No open container with window ID $action->windowId");
			default:
				throw new \UnexpectedValueException("Unknown inventory source type $action->sourceType");
		}
	}
}