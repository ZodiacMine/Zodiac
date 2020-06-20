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

namespace pocketmine\network\mcpe\protocol\types\login;

use pocketmine\network\mcpe\handler\LoginPacketHandler;
use pocketmine\network\mcpe\protocol\types\PersonaPieceTintColor;
use pocketmine\network\mcpe\protocol\types\PersonaSkinPiece;
use pocketmine\network\mcpe\protocol\types\SkinAnimation;
use pocketmine\network\mcpe\protocol\types\SkinData;
use pocketmine\network\mcpe\protocol\types\SkinImage;
use pocketmine\utils\SingletonTrait;
use function array_map;
use function base64_decode;

final class ClientDataToSkinDataHelper{
	use SingletonTrait;

	/**
	 * @throws \InvalidArgumentException
	 */
	private static function safeB64Decode(string $base64, string $context) : string{
		$result = base64_decode($base64, true);
		if($result === false){
			throw new \InvalidArgumentException("$context: Malformed base64, cannot be decoded");
		}
		return $result;
	}

	/**
	 * @param mixed[] $clientData
	 *
	 * @throws \InvalidArgumentException
	 */
	public function fromClientData(array $clientData, int $protocol) : SkinData{
		/** @var SkinAnimation[] $animations */
		$animations = [];
		foreach($clientData[LoginPacketHandler::I_ANIMATION_FRAMES] as $k => $animation){
			$animations[] = new SkinAnimation(
				new SkinImage(
					$animation[LoginPacketHandler::I_ANIMATION_IMAGE_HEIGHT],
					$animation[LoginPacketHandler::I_ANIMATION_IMAGE_WIDTH],
					self::safeB64Decode($animation[LoginPacketHandler::I_ANIMATION_IMAGE_DATA], "AnimatedImageData.$k.Image")
				),
				$animation[LoginPacketHandler::I_ANIMATION_IMAGE_TYPE],
				$animation[LoginPacketHandler::I_ANIMATION_IMAGE_FRAMES]
			);
		}
		return new SkinData(
			$clientData[LoginPacketHandler::I_SKIN_ID],
			self::safeB64Decode($clientData[LoginPacketHandler::I_SKIN_RESOURCE_PATCH], "SkinResourcePatch"),
			new SkinImage(
				$clientData[LoginPacketHandler::I_SKIN_HEIGHT],
				$clientData[LoginPacketHandler::I_SKIN_WIDTH],
				self::safeB64Decode($clientData[LoginPacketHandler::I_SKIN_DATA], "SkinData")
			),
			$animations,
			new SkinImage(
				$clientData[LoginPacketHandler::I_CAPE_HEIGHT],
				$clientData[LoginPacketHandler::I_CAPE_WIDTH],
				self::safeB64Decode($clientData[LoginPacketHandler::I_CAPE_DATA], "CapeData")
			),
			self::safeB64Decode($clientData[LoginPacketHandler::I_GEOMETRY_DATA], "SkinGeometryData"),
			self::safeB64Decode($clientData[LoginPacketHandler::I_ANIMATION_DATA], "SkinAnimationData"),
			$clientData[LoginPacketHandler::I_PREMIUM_SKIN],
			$clientData[LoginPacketHandler::I_PERSONA_SKIN],
			$clientData[LoginPacketHandler::I_PERSONA_CAPE_ON_CLASSIC_SKIN],
			$clientData[LoginPacketHandler::I_CAPE_ID],
			null,
			$clientData[LoginPacketHandler::I_SKIN_ARM_SIZE],
			$clientData[LoginPacketHandler::I_SKIN_COLOR],
			array_map(static function(array $piece) : PersonaSkinPiece{
				return new PersonaSkinPiece(
					$piece[LoginPacketHandler::I_PERSONA_PIECE_ID],
					$piece[LoginPacketHandler::I_PERSONA_PIECE_TYPE],
					$piece[LoginPacketHandler::I_PERSONA_PIECE_PACK_ID],
					$piece[LoginPacketHandler::I_PERSONA_PIECE_IS_DEFAULT],
					$piece[LoginPacketHandler::I_PERSONA_PIECE_PRODUCT_ID]
				);
			}, $clientData[LoginPacketHandler::I_PERSONA_PIECES]),
			array_map(static function(array $tint) : PersonaPieceTintColor{
				return new PersonaPieceTintColor(
					$tint[LoginPacketHandler::I_PIECE_TINT_COLOR_TYPE],
					$tint[LoginPacketHandler::I_PIECE_TINT_COLOR_COLORS]
				);
			}, $clientData[LoginPacketHandler::I_PIECE_TINT_COLORS])
		);
	}
}
