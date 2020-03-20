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

use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\types\command\CommandEnum;
use pocketmine\network\mcpe\protocol\types\command\CommandParameter;
use function array_keys;
use function array_map;

class CommandOverload{
	/**
	 * @param mixed[] $array
	 */
	private static function array_require(array $array, string ...$requiredFields) : void{
		foreach($requiredFields as $field){
			if(!isset($array[$field])){
				throw new \InvalidArgumentException("Parameter \"$field\" field required, but not specified");
			}
		}
	}

	/**
	 * @param mixed[][] $parameters
	 */
	public static function fromArray(array $parameters) : CommandOverload{
		$parameters = array_map(function(string $parameterName, array $parameterData) : CommandParameter{
			self::array_require($parameterData, "type");

			$parameter = new CommandParameter();
			$parameter->paramName = $parameterName;

			$parameter->flags = $parameterData["flags"] ?? 0;
			$parameter->isOptional = $parameterData["optional"] ?? false;

			switch($parameterData["type"]){
				case "list":
					self::array_require($parameterData, "enum", "values");
					$parameter->paramType = AvailableCommandsPacket::ARG_FLAG_ENUM | AvailableCommandsPacket::ARG_FLAG_VALID;
					$parameter->enum = new CommandEnum($parameterData["enum"], $parameterData["values"]);
					break;
				case "postixed":
					self::array_require($parameterData, "postfix");
					$parameter->paramType = AvailableCommandsPacket::ARG_FLAG_POSTFIX;
					$parameter->postfix = $parameterData["postfix"];
					break;
				case "network":
					self::array_require($parameterData, "networkType");
					$parameter->paramType = AvailableCommandsPacket::ARG_FLAG_VALID | $parameterData["networkType"];
					break;
				case "int":
					$parameter->paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_INT;
					break;
				case "float":
					$parameter->paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_FLOAT;
					break;
				case "value":
					$parameter->paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_VALUE;
					break;
				case "wildcardint":
				case "wildcard_int":
					$parameter->paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_WILDCARD_INT;
					break;
				case "operator":
					$parameter->paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_OPERATOR;
					break;
				case "target":
				case "player":
					$parameter->paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_TARGET;
					break;
				case "filepath":
					$parameter->paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_FILEPATH;
					break;
				case "string":
					$parameter->paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_STRING;
					break;
				case "position":
					$parameter->paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_POSITION;
					break;
				case "message":
					$parameter->paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_MESSAGE;
					break;
				case "rawtext":
					$parameter->paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_RAWTEXT;
					break;
				case "json":
					$parameter->paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_JSON;
					break;
				case "command":
					$parameter->paramType = AvailableCommandsPacket::ARG_FLAG_VALID | AvailableCommandsPacket::ARG_TYPE_COMMAND;
					break;
			}

			return $parameter;
		}, array_keys($parameters), $parameters);

		return new CommandOverload($parameters);
	}

	/** @var CommandParameter[] */
	protected $parameters = [];

	/**
	 * @param CommandParameter[] $parameters
	 */
	public function __construct(array $parameters = []){
		$this->setParameters($parameters);
	}

	/**
	 * @param CommandParameter[] $parameters
	 */
	public function setParameters(array $parameters) : void{
		//type checks
		(static function(CommandParameter ...$parameters) : void{})(...$parameters);

		$this->parameters = $parameters;
	}

	/**
	 * @return CommandParameter[]
	 */
	public function getParameters() : array{
		return $this->parameters;
	}


	public function addStandardParameter(string $name, int $networkType, int $flags = 0, bool $optional = false) : self{
		$this->parameters[] = CommandParameter::standard($name, $networkType, $flags, $optional);
		return $this;
	}

	public function addPostfixedParameter(string $name, string $postfix, int $flags = 0, bool $optional = false) : self{
		$this->parameters[] = CommandParameter::postfixed($name, $postfix, $flags, $optional);
		return $this;
	}

	/**
	 * @param string[] $enumValues
	 */
	public function addListParameter(string $name, string $enumName, array $enumValues, int $flags = 0, bool $optional = false) : self{
		$this->parameters[] = CommandParameter::enum($name, new CommandEnum($enumName, $enumValues), $flags, $optional);
		return $this;
	}


	public function int(string $name, int $flags = 0, bool $optional = false) : self{
		$this->parameters[] = CommandParameter::standard($name, AvailableCommandsPacket::ARG_TYPE_INT, $flags, $optional);
		return $this;
	}

	public function float(string $name, int $flags = 0, bool $optional = false) : self{
		$this->parameters[] = CommandParameter::standard($name, AvailableCommandsPacket::ARG_TYPE_FLOAT, $flags, $optional);
		return $this;
	}

	public function value(string $name, int $flags = 0, bool $optional = false) : self{
		$this->parameters[] = CommandParameter::standard($name, AvailableCommandsPacket::ARG_TYPE_VALUE, $flags, $optional);
		return $this;
	}

	public function wildcardInt(string $name, int $flags = 0, bool $optional = false) : self{
		$this->parameters[] = CommandParameter::standard($name, AvailableCommandsPacket::ARG_TYPE_WILDCARD_INT, $flags, $optional);
		return $this;
	}

	public function operator(string $name, int $flags = 0, bool $optional = false) : self{
		$this->parameters[] = CommandParameter::standard($name, AvailableCommandsPacket::ARG_TYPE_OPERATOR, $flags, $optional);
		return $this;
	}

	public function target(string $name, int $flags = 0, bool $optional = false) : self{
		$this->parameters[] = CommandParameter::standard($name, AvailableCommandsPacket::ARG_TYPE_TARGET, $flags, $optional);
		return $this;
	}

	public function filepath(string $name, int $flags = 0, bool $optional = false) : self{
		$this->parameters[] = CommandParameter::standard($name, AvailableCommandsPacket::ARG_TYPE_FILEPATH, $flags, $optional);
		return $this;
	}

	public function string(string $name, int $flags = 0, bool $optional = false) : self{
		$this->parameters[] = CommandParameter::standard($name, AvailableCommandsPacket::ARG_TYPE_STRING, $flags, $optional);
		return $this;
	}

	public function position(string $name, int $flags = 0, bool $optional = false) : self{
		$this->parameters[] = CommandParameter::standard($name, AvailableCommandsPacket::ARG_TYPE_POSITION, $flags, $optional);
		return $this;
	}

	public function message(string $name, int $flags = 0, bool $optional = false) : self{
		$this->parameters[] = CommandParameter::standard($name, AvailableCommandsPacket::ARG_TYPE_MESSAGE, $flags, $optional);
		return $this;
	}

	public function rawtext(string $name, int $flags = 0, bool $optional = false) : self{
		$this->parameters[] = CommandParameter::standard($name, AvailableCommandsPacket::ARG_TYPE_RAWTEXT, $flags, $optional);
		return $this;
	}

	public function json(string $name, int $flags = 0, bool $optional = false) : self{
		$this->parameters[] = CommandParameter::standard($name, AvailableCommandsPacket::ARG_TYPE_JSON, $flags, $optional);
		return $this;
	}

	public function command(string $name, int $flags = 0, bool $optional = false) : self{
		$this->parameters[] = CommandParameter::standard($name, AvailableCommandsPacket::ARG_TYPE_COMMAND, $flags, $optional);
		return $this;
	}
}
