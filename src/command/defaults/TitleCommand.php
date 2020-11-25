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

use pocketmine\command\CommandOverload;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\lang\TranslationContainer;
use function count;
use function implode;

class TitleCommand extends VanillaCommand{

	public function __construct(string $name){
		parent::__construct(
			$name,
			"%pocketmine.command.title.description",
			"%commands.title.usage",
			[],
			[
				(new CommandOverload())
					->target("player")
					->addListParameter("clear", "TitleClear", ["clear"]),
				(new CommandOverload())
					->target("player")
					->addListParameter("reset", "TitleReset", ["reset"]),
				(new CommandOverload())
					->target("player")
					->addListParameter("titleLocation", "TitleSet", ["title", "subtitle", "actionbar"], 1) //TODO: Use constant?
					->message("titleText"),
				(new CommandOverload())
					->target("player")
					->addListParameter("times", "TitleTimes", ["times"])
					->int("fadeIn")
					->int("stay")
					->int("fadeOut")
			]
		);
		$this->setPermission("pocketmine.command.title");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(!$this->testPermission($sender)){
			return true;
		}

		if(count($args) < 2){
			throw new InvalidCommandSyntaxException();
		}

		$player = $sender->getServer()->getPlayerByPrefix($args[0]);
		if($player === null){
			$sender->sendMessage(new TranslationContainer("commands.generic.player.notFound"));
			return true;
		}

		switch(array_shift($args)){
			case "clear":
				$player->removeTitles();
				break;
			case "reset":
				$player->resetTitles();
				break;
			case "title":
				if(count($args) < 3){
					throw new InvalidCommandSyntaxException();
				}

				$player->sendTitle(implode(" ", $args));
				break;
			case "subtitle":
				if(count($args) < 3){
					throw new InvalidCommandSyntaxException();
				}

				$player->sendSubTitle(implode(" ", $args));
				break;
			case "actionbar":
				if(count($args) < 3){
					throw new InvalidCommandSyntaxException();
				}

				$player->sendActionBarMessage(implode(" ", $args));
				break;
			case "times":
				if(count($args) < 5){
					throw new InvalidCommandSyntaxException();
				}

				$fadeIn = array_shift($args);
				$stay = array_shift($args);
				$fadeOut = array_shift($args);

				$player->setTitleDuration($this->getInteger($sender, $fadeIn), $this->getInteger($sender, $stay), $this->getInteger($sender, $fadeOut));
				break;
			default:
				throw new InvalidCommandSyntaxException();
		}

		$sender->sendMessage(new TranslationContainer("commands.title.success"));

		return true;
	}
}
