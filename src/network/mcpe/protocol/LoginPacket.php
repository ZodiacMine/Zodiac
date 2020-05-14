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

namespace pocketmine\network\mcpe\protocol;

#include <rules/DataPacket.h>

use Particle\Validator\Validator;
use pocketmine\network\mcpe\JwtException;
use pocketmine\network\mcpe\JwtUtils;
use pocketmine\network\mcpe\protocol\serializer\NetworkBinaryStream;
use pocketmine\network\mcpe\protocol\types\PersonaSkinPiece;
use pocketmine\network\mcpe\protocol\types\SkinData;
use pocketmine\utils\BinaryDataException;
use pocketmine\utils\BinaryStream;
use function is_array;
use function is_object;
use function json_decode;
use function json_last_error_msg;

class LoginPacket extends DataPacket implements ServerboundPacket{
	public const NETWORK_ID = ProtocolInfo::LOGIN_PACKET;

	public const EDITION_POCKET = 0;

	public const I_USERNAME = 'displayName';
	public const I_UUID = 'identity';
	public const I_XUID = 'XUID';

	public const I_CLIENT_RANDOM_ID = 'ClientRandomId';
	public const I_SERVER_ADDRESS = 'ServerAddress';
	public const I_LANGUAGE_CODE = 'LanguageCode';

	public const I_SKIN_RESOURCE_PATCH = 'SkinResourcePatch';

	public const I_SKIN_ID = 'SkinId';
	public const I_SKIN_HEIGHT = 'SkinImageHeight';
	public const I_SKIN_WIDTH = 'SkinImageWidth';
	public const I_SKIN_DATA = 'SkinData';

	public const I_CAPE_ID = 'CapeId';
	public const I_CAPE_HEIGHT = 'CapeImageHeight';
	public const I_CAPE_WIDTH = 'CapeImageWidth';
	public const I_CAPE_DATA = 'CapeData';

	public const I_GEOMETRY_DATA = 'SkinGeometryData';

	public const I_ANIMATION_DATA = 'SkinAnimationData';
	public const I_ANIMATION_FRAMES = 'AnimatedImageData';

	public const I_ANIMATION_IMAGE_HEIGHT = 'ImageHeight';
	public const I_ANIMATION_IMAGE_WIDTH = 'ImageWidth';
	public const I_ANIMATION_IMAGE_FRAMES = 'Frames';
	public const I_ANIMATION_IMAGE_TYPE = 'Type';
	public const I_ANIMATION_IMAGE_DATA = 'Image';

	public const I_PREMIUM_SKIN = 'PremiumSkin';
	public const I_PERSONA_SKIN = 'PersonaSkin';
	public const I_PERSONA_CAPE_ON_CLASSIC_SKIN = 'CapeOnClassicSkin';

	public const I_SKIN_ARM_SIZE = 'ArmSize';
	public const I_SKIN_COLOR = 'SkinColor';

	public const I_PERSONA_PIECES = 'PersonaPieces';
	public const I_PIECE_TINT_COLORS = 'PieceTintColors';

	public const I_PERSONA_PIECE_ID = 'PieceId';
	public const I_PERSONA_PIECE_TYPE = 'PieceType';
	public const I_PERSONA_PIECE_PACK_ID = 'PackId';
	public const I_PERSONA_PIECE_IS_DEFAULT = 'IsDefault';
	public const I_PERSONA_PIECE_PRODUCT_ID = 'ProductId';

	public const I_PIECE_TINT_COLOR_TYPE = 'PieceType';
	public const I_PIECE_TINT_COLOR_COLORS = 'Colors';

	/** @var int */
	public $protocol;

	/** @var string[] array of encoded JWT */
	public $chainDataJwt = [];
	/**
	 * @var mixed[]|null extraData index of whichever JWT has it
	 * @phpstan-var array<string, mixed>
	 */
	public $extraData = null;
	/** @var string */
	public $clientDataJwt;
	/**
	 * @var mixed[] decoded payload of the clientData JWT
	 * @phpstan-var array<string, mixed>
	 */
	public $clientData;

	public function canBeSentBeforeLogin() : bool{
		return true;
	}

	protected function decodePayload(NetworkBinaryStream $in) : void{
		$this->protocol = $in->getInt();
		$this->decodeConnectionRequest($in);
	}

	/**
	 * @param mixed $data
	 *
	 * @throws PacketDecodeException
	 */
	private static function validate(Validator $v, string $name, $data) : void{
		$result = $v->validate($data);
		if($result->isNotValid()){
			$messages = [];
			foreach($result->getFailures() as $f){
				$messages[] = $f->format();
			}
			throw new PacketDecodeException("Failed to validate '$name': " . implode(", ", $messages));
		}
	}

	/**
	 * @throws PacketDecodeException
	 * @throws BinaryDataException
	 */
	protected function decodeConnectionRequest(NetworkBinaryStream $in) : void{
		$buffer = new BinaryStream($in->getString());

		$chainData = json_decode($buffer->get($buffer->getLInt()), true);
		if(!is_array($chainData)){
			throw new PacketDecodeException("Failed to decode chainData JSON: " . json_last_error_msg());
		}

		$vd = new Validator();
		$vd->required('chain')->isArray()->callback(function(array $data) : bool{
			return count($data) <= 3 and count(array_filter($data, '\is_string')) === count($data);
		});
		self::validate($vd, "chainData", $chainData);

		$this->chainDataJwt = $chainData['chain'];
		foreach($this->chainDataJwt as $k => $chain){
			//validate every chain element
			try{
				[, $claims, ] = JwtUtils::parse($chain);
			}catch(JwtException $e){
				throw new PacketDecodeException($e->getMessage(), 0, $e);
			}
			if(isset($claims["extraData"])){
				if(!is_array($claims["extraData"])){
					throw new PacketDecodeException("'extraData' key should be an array");
				}
				if($this->extraData !== null){
					throw new PacketDecodeException("Found 'extraData' more than once in chainData");
				}

				$extraV = new Validator();
				$extraV->required(self::I_USERNAME)->string();
				$extraV->required(self::I_UUID)->uuid();
				$extraV->required(self::I_XUID)->string()->digits()->allowEmpty(true);
				self::validate($extraV, "chain.$k.extraData", $claims['extraData']);

				$this->extraData = $claims['extraData'];
			}
		}
		if($this->extraData === null){
			throw new PacketDecodeException("'extraData' not found in chain data");
		}

		$this->clientDataJwt = $buffer->get($buffer->getLInt());
		try{
			[, $clientData, ] = JwtUtils::parse($this->clientDataJwt);
		}catch(JwtException $e){
			throw new PacketDecodeException($e->getMessage(), 0, $e);
		}

		$v = new Validator();
		$v->required(self::I_CLIENT_RANDOM_ID)->integer();
		$v->required(self::I_SERVER_ADDRESS)->string();
		$v->required(self::I_LANGUAGE_CODE)->string();

		$v->required(self::I_SKIN_RESOURCE_PATCH)->string();

		$v->required(self::I_SKIN_ID)->string();
		$v->required(self::I_SKIN_DATA)->string();
		$v->required(self::I_SKIN_HEIGHT)->integer(true);
		$v->required(self::I_SKIN_WIDTH)->integer(true);

		$v->required(self::I_CAPE_ID, null, true)->string();
		$v->required(self::I_CAPE_DATA, null, true)->string();
		$v->required(self::I_CAPE_HEIGHT)->integer(true);
		$v->required(self::I_CAPE_WIDTH)->integer(true);

		$v->required(self::I_GEOMETRY_DATA, null, true)->string();

		$v->required(self::I_ANIMATION_DATA, null, true)->string();
		$v->required(self::I_ANIMATION_FRAMES, null, true)->isArray()->each(function(Validator $vSub) : void{
			$vSub->required(self::I_ANIMATION_IMAGE_HEIGHT)->integer(true);
			$vSub->required(self::I_ANIMATION_IMAGE_WIDTH)->integer(true);
			$vSub->required(self::I_ANIMATION_IMAGE_FRAMES)->numeric(); //float() doesn't accept ints ???
			$vSub->required(self::I_ANIMATION_IMAGE_TYPE)->integer(true);
			$vSub->required(self::I_ANIMATION_IMAGE_DATA)->string();
		});
		$v->required(self::I_PREMIUM_SKIN)->bool();
		$v->required(self::I_PERSONA_SKIN)->bool();
		$v->required(self::I_PERSONA_CAPE_ON_CLASSIC_SKIN)->bool();
		$v->required(self::I_SKIN_ARM_SIZE, null, true)->string()->inArray([
			SkinData::ARM_SIZE_WIDE,
			SkinData::ARM_SIZE_SLIM
		]);
		$v->required(self::I_SKIN_COLOR)->string()->callback(function(string $data) : bool{
			return preg_match("~^\\#[0-9a-f]*$~i", $data) > 0;
		});
		$v->required(self::I_PERSONA_PIECES, null, true)->isArray()->each(function(Validator $vSub) : void{
			$vSub->required(self::I_PERSONA_PIECE_IS_DEFAULT)->bool();
			$vSub->required(self::I_PERSONA_PIECE_PACK_ID)->uuid();
			$vSub->required(self::I_PERSONA_PIECE_ID)->uuid();
			$vSub->required(self::I_PERSONA_PIECE_TYPE)->string()->inArray(PersonaSkinPiece::PIECE_TYPE_ALL);
			$vSub->required(self::I_PERSONA_PIECE_PRODUCT_ID, null, true)->string();
		});
		$v->required(self::I_PIECE_TINT_COLORS, null, true)->isArray()->each(function(Validator $vSub) : void{
			$vSub->required(self::I_PIECE_TINT_COLOR_COLORS)->isArray()->callback(function(array $data) : bool{
				return count(array_filter($data, '\is_string')) === count($data);
			});
			$vSub->required(self::I_PIECE_TINT_COLOR_TYPE)->string()->inArray(PersonaSkinPiece::PIECE_TYPE_ALL);
		});

		self::validate($v, 'clientData', $clientData);

		$this->clientData = $clientData;
	}

	protected function encodePayload(NetworkBinaryStream $out) : void{
		//TODO
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleLogin($this);
	}
}
