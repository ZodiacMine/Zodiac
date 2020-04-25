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

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandOverload;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\lang\TranslationContainer;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use function array_filter;
use function array_values;
use function count;
use function round;

class TeleportCommand extends VanillaCommand{

	public function __construct(string $name){
		parent::__construct(
			$name,
			"%pocketmine.command.tp.description",
			"%commands.tp.usage",
			["teleport"],
			[
				(new CommandOverload())
					->position("destination")
					->addListParameter("checkForBlocks", "Boolean", ["true", "false"], 0, true),
				(new CommandOverload())
					->position("destination")
					->value("yRot", 0, true)
					->value("xRot", 0, true)
					->addListParameter("checkForBlocks", "Boolean", ["true", "false"], 0, true),
				(new CommandOverload())
					->position("destination") //TODO: This is ARG_TYPE_DESTIONAL(0x26), not 0x25 and also all the POSTION in this class are destional
					->addListParameter("facing", "TeleportFacing", ["facing"])
					->position("lookAtPosition") //TODO: Also destional
					->addListParameter("checkForBlocks", "Boolean", ["true", "false"], 0, true),
				(new CommandOverload())
					->position("destination")
					->addListParameter("facing", "TeleportFacing", ["facing"])
					->target("lookAtEntity")
					->addListParameter("checkForBlocks", "Boolean", ["true", "false"], 0, true),
				(new CommandOverload())
					->target("victim")
					->position("destination")
					->value("yRot", 0, true)
					->value("xRot", 0, true)
					->addListParameter("checkForBlocks", "Boolean", ["true", "false"], 0, true),
				(new CommandOverload())
					->target("victim")
					->position("destination")
					->addListParameter("checkForBlocks", "Boolean", ["true", "false"], 0, true),
				(new CommandOverload())
					->target("victim")
					->position("destination")
					->addListParameter("facing", "TeleportFacing", ["facing"])
					->position("lookAtPosition")
					->addListParameter("checkForBlocks", "Boolean", ["true", "false"], 0, true),
				(new CommandOverload())
					->target("victim")
					->position("destination")
					->addListParameter("facing", "TeleportFacing", ["facing"])
					->target("lookAtEntity")
					->addListParameter("checkForBlocks", "Boolean", ["true", "false"], 0, true),
				(new CommandOverload())
					->target("destination")
					->addListParameter("checkForBlocks", "Boolean", ["true", "false"], 0, true),
				(new CommandOverload())
					->target("victim")
					->target("destination")
					->addListParameter("checkForBlocks", "Boolean", ["true", "false"], 0, true),
			]
		);
		$this->setPermission("pocketmine.command.teleport");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(!$this->testPermission($sender)){
			return true;
		}

		$args = array_values(array_filter($args, function(string $arg) : bool{
			return $arg !== "";
		}));

		if(count($args) < 1){
			throw new InvalidCommandSyntaxException();
		}

		$target = null;
		$origin = $sender;

		if($sender instanceof Player){
			$target = $sender;
			TELEPORT_AS_PLAYER:
			if(is_numeric($args[0]) or $args[0][0] === "~"){
				$targetLocation = $target->getLocation();
				goto TELEPORT_POSITION;
			}else{
				$playerName = $this->readPlayerName($args);
				$target = $sender->getServer()->getPlayer($playerName);
				if($target === null){
					$sender->sendMessage(TextFormat::RED . "Can't find player " . $playerName);

					return true;
				}
			}
		}else{
			$playerName = $this->readPlayerName($args);
			$origin = $sender->getServer()->getPlayer($playerName);
			if($origin === null){
				$sender->sendMessage(TextFormat::RED . "Can't find player " . $playerName);

				return true;
			}

			if(count($args) === 0){
				throw new InvalidCommandSyntaxException();
			}

			goto TELEPORT_AS_PLAYER;
		}

		$targetLocation = $target->getLocation();

		if(count($args) === 0){
			$origin->teleport($targetLocation);
			Command::broadcastCommandMessage($sender, new TranslationContainer("commands.tp.success", [$origin->getName(), $target->getName()]));

			return true;
		}else{
			TELEPORT_POSITION:

			if(count($args) < 3 or count($args) > 5){
				throw new InvalidCommandSyntaxException();
			}

			$x = $this->getRelativeDouble($targetLocation->x, $sender, $args[0]);
			$y = $this->getRelativeDouble($targetLocation->y, $sender, $args[1], 0, 256);
			$z = $this->getRelativeDouble($targetLocation->z, $sender, $args[2]);
			$yaw = $targetLocation->getYaw();
			$pitch = $targetLocation->getPitch();

			if(count($args) > 3){
				$yaw = (float) $args[3];
				if(count($args) > 4){
					$pitch = (float) $args[4];
				}
			}

			$target->teleport(new Vector3($x, $y, $z), $yaw, $pitch);
			Command::broadcastCommandMessage($sender, new TranslationContainer("commands.tp.success.coordinates", [$target->getName(), round($x, 2), round($y, 2), round($z, 2)]));

			return true;
		}
	}
}
