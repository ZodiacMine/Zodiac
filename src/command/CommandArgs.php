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

namespace pocketmine\command;

use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use function array_slice;
use function count;
use function implode;
use function strlen;
use function substr;

class CommandArgs implements \Countable{
	public const TARGET_SELECTOR_ALL_PLAYERS = "@a";
	public const TARGET_SELECTOR_ALL_ENTITIES = "@e";
	public const TARGET_SELECTOR_CLOSEST_PLAYER = "@p";
	public const TARGET_SELECTOR_RANDOM_PLAYER = "@r";
	public const TARGET_SELECTOR_YOURSELF = "@s";

	/** @var string[] */
	protected $args;
	/** @var int */
	protected $current = 0;

	public function __construct(array $args){
		$this->args = $args;
	}

	public function count() : int{
		return count($this->args);
	}

	public function canRead() : bool{
		return isset($this->args[$this->current]);
	}

	public function readString() : string{
		if(!$this->canRead()){
			throw new InvalidCommandSyntaxException();
		}

		return $this->args[$this->current++];
	}

	public function readRawText() : string{
		return implode(" ", array_slice($this->args, $this->current));
	}

	public function readPlayerName() : string{
		$name = $this->readString();
		if(strlen($name) > 0 and $name[0] === "\""){
			do{
				$part = $this->readString();

				$name .= " " . $part;
				if(substr($part, -1) === "\""){
					break;
				}
			}while(true); // Ends when readString() throws exception

			return substr($name, 1, strlen($name) - 2);
		}

		return $name;
	}

	public function readInt() : int{
		$input = $this->readString();
		if(!is_numeric($input)){
			throw new InvalidCommandSyntaxException();
		}

		return (int) $input;
	}

	public function readDouble() : int{
		$input = $this->readString();
		//TODO: Validate?

		return (double) $input;
	}

	public function readBool() : bool{
		$input = $this->readString();
		switch($input){
			case "true":
				return true;
			case "false":
				return false;
		}

		throw new InvalidCommandSyntaxException();
	}

	public function readVector3(?Vector3 $currentPos) : Vector3{
		$position = [];
		do{
			$input = $this->readString();
			if($input[0] === "~"){
				if($currentPos === null){
					throw new InvalidCommandSyntaxException();
				}

				for($i = 0; $i < strlen($input); ++$i){
					switch(count($position) % 3){
						case 0:
							$coord = $currentPos->getX();
							break;
						case 1:
							$coord = $currentPos->getY();
							break;
						case 2:
							$coord = $currentPos->getZ();
							break;
						default:
							$coord = 0; // Make PHPStan happy
							break;
					}

					$position[] = $coord;
				}

				continue;
			}

			$position[] = (double) $input;
		}while(count($position) < 3);

		if(count($position) > 3){
			throw new InvalidCommandSyntaxException();
		}

		return new Vector3($position[0], $position[1], $position[2]);
	}

	/**
	 * @return Entity[]
	 */
	public function readTargets(CommandSender $sender) : array{
		$input = $this->readPlayerName();

		$expectedPlayer = static function() use ($sender) : void{
			if(!$sender instanceof Player){
				throw new InvalidCommandSyntaxException();
			}
		};

		switch($input){
			case self::TARGET_SELECTOR_ALL_PLAYERS:
				$targets = $player->getServer()->getOnlinePlayers();
				break;
			case self::TARGET_SELECTOR_ALL_ENTITIES:
				$expectedPlayer();
				$targets = $player->getWorld()->getEntities();
				break;
			case self::TARGET_SELECTOR_CLOSEST_PLAYER:
				$expectedPlayer();
				$targets = [$player->getWorld()->getNearestEntity($player->getPosition(), 100, Player::class)];
				break;
			case self::TARGET_SELECTOR_RANDOM_PLAYER:
				$players = array_values($sender->getServer()->getOnlinePlayers());
				$targets = (count($players) === 0 ? [] : [$players[array_rand($players)]]);
				break;
			case self::TARGET_SELECTOR_YOURSELF:
				$expectedPlayer();
				$targets = [$sender];
				break;
			default:
				$targets = [$sender->getServer()->getPlayerExact($input)];
				break;
		}

		return $targets;
	}

	public function readTarget(CommandSender $sender) : CommandSender{
		$input = $this->readPlayerName();

		if($input === self::TARGET_SELECTOR_YOURSELF){
			return $sender;
		}else{
			$player = $sender->getServer()->getPlayerExact($input);
			if(!$player instanceof Player){
				throw new InvalidCommandSyntaxException();
			}

			return $player;
		}
	}
}
