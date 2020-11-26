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

namespace pocketmine\network\mcpe\handler;

use Mdanter\Ecc\Crypto\Key\PublicKeyInterface;
use Particle\Validator\Failure;
use Particle\Validator\Validator;
use pocketmine\entity\InvalidSkinException;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\network\BadPacketException;
use pocketmine\network\mcpe\auth\ProcessLoginTask;
use pocketmine\network\mcpe\convert\SkinAdapterSingleton;
use pocketmine\network\mcpe\JwtException;
use pocketmine\network\mcpe\JwtUtils;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\PlayStatusPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\types\login\AuthenticationData;
use pocketmine\network\mcpe\protocol\types\login\ClientData;
use pocketmine\network\mcpe\protocol\types\login\ClientDataToSkinDataHelper;
use pocketmine\network\mcpe\protocol\types\login\JwtChain;
use pocketmine\network\mcpe\protocol\types\skin\PersonaSkinPiece;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use pocketmine\player\Player;
use pocketmine\player\PlayerInfo;
use pocketmine\player\XboxLivePlayerInfo;
use pocketmine\Server;
use pocketmine\uuid\UUID;
use function array_map;
use function base64_decode;
use function in_array;
use function is_array;

/**
 * Handles the initial login phase of the session. This handler is used as the initial state.
 */
class LoginPacketHandler extends PacketHandler{

	//TODO: Move this constants to other place

	public const I_USERNAME = 'displayName';
	public const I_UUID = 'identity';
	public const I_TITLE_ID = 'titleId';
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

	public const I_ANIMATION_EXPRESSION = 'AnimationExpression';
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

	/** @var Server */
	private $server;
	/** @var NetworkSession */
	private $session;
	/**
	 * @var \Closure
	 * @phpstan-var \Closure(PlayerInfo) : void
	 */
	private $playerInfoConsumer;
	/**
	 * @var \Closure
	 * @phpstan-var \Closure(bool, bool, ?string, ?PublicKeyInterface) : void
	 */
	private $authCallback;

	/**
	 * @phpstan-param \Closure(PlayerInfo) : void $playerInfoConsumer
	 * @phpstan-param \Closure(bool $isAuthenticated, bool $authRequired, ?string $error, ?PublicKeyInterface $clientPubKey) : void $authCallback
	 */
	public function __construct(Server $server, NetworkSession $session, \Closure $playerInfoConsumer, \Closure $authCallback){
		$this->session = $session;
		$this->server = $server;
		$this->playerInfoConsumer = $playerInfoConsumer;
		$this->authCallback = $authCallback;
	}

	private static function dummy() : void{
		echo PublicKeyInterface::class; //this prevents the import getting removed by tools that don't understand phpstan
	}

	public function handleLogin(LoginPacket $packet) : bool{
		if(!$this->isCompatibleProtocol($packet->protocol)){
			$this->session->sendDataPacket(PlayStatusPacket::create($packet->protocol < ProtocolInfo::CURRENT_PROTOCOL ? PlayStatusPacket::LOGIN_FAILED_CLIENT : PlayStatusPacket::LOGIN_FAILED_SERVER), true);

			//This pocketmine disconnect message will only be seen by the console (PlayStatusPacket causes the messages to be shown for the client)
			$this->session->disconnect(
				$this->server->getLanguage()->translateString("pocketmine.disconnect.incompatibleProtocol", [$packet->protocol]),
				false
			);

			return true;
		}

		$extraData = $this->fetchAuthData($packet->chainDataJwt);

		if(!Player::isValidUserName($extraData[self::I_USERNAME])){
			$this->session->disconnect("disconnectionScreen.invalidName");

			return true;
		}

		$clientData = $this->parseClientData($packet->clientDataJwt);
		try{
			$skin = SkinAdapterSingleton::get()->fromSkinData(ClientDataToSkinDataHelper::getInstance()->fromClientData($clientData, $packet->protocol));
		}catch(\InvalidArgumentException | InvalidSkinException $e){
			$this->session->getLogger()->debug("Invalid skin: " . $e->getMessage());
			$this->session->disconnect("disconnectionScreen.invalidSkin");

			return true;
		}

		try{
			$uuid = UUID::fromString($extraData[self::I_UUID]);
		}catch(\InvalidArgumentException $e){
			throw BadPacketException::wrap($e, "Failed to parse login UUID");
		}

		if($extraData[self::I_XUID] !== ""){
			$playerInfo = new XboxLivePlayerInfo(
				$extraData[self::I_XUID],
				$extraData[self::I_USERNAME],
				$uuid,
				$skin,
				$clientData[self::I_LANGUAGE_CODE],
				$clientData
			);
		}else{
			$playerInfo = new PlayerInfo(
				$extraData[self::I_USERNAME],
				$uuid,
				$skin,
				$clientData[self::I_LANGUAGE_CODE],
				$clientData
			);
		}
		($this->playerInfoConsumer)($playerInfo);

		$ev = new PlayerPreLoginEvent(
			$playerInfo,
			$this->session->getIp(),
			$this->session->getPort(),
			$this->server->requiresAuthentication()
		);
		if($this->server->getNetwork()->getConnectionCount() > $this->server->getMaxPlayers()){
			$ev->setKickReason(PlayerPreLoginEvent::KICK_REASON_SERVER_FULL, "disconnectionScreen.serverFull");
		}
		if(!$this->server->isWhitelisted($playerInfo->getUsername())){
			$ev->setKickReason(PlayerPreLoginEvent::KICK_REASON_SERVER_WHITELISTED, "Server is whitelisted");
		}
		if($this->server->getNameBans()->isBanned($playerInfo->getUsername()) or $this->server->getIPBans()->isBanned($this->session->getIp())){
			$ev->setKickReason(PlayerPreLoginEvent::KICK_REASON_BANNED, "You are banned");
		}

		$ev->call();
		if(!$ev->isAllowed()){
			$this->session->disconnect($ev->getFinalKickMessage());
			return true;
		}

		$this->processLogin($packet, $ev->isAuthRequired());

		return true;
	}

	/**
	 * @return mixed[] extraData index of whichever JWT has it
	 * @phpstan-return array<string, mixed>
	 *
	 * @throws BadPacketException
	 */
	protected function fetchAuthData(JwtChain $chain) : array{
		$extraData = null;
		foreach($chain->chain as $k => $jwt){
			//validate every chain element
			try{
				[, $claims, ] = JwtUtils::parse($jwt);
			}catch(JwtException $e){
				throw BadPacketException::wrap($e);
			}
			if(isset($claims["extraData"])){
				if($extraData !== null){
					throw new BadPacketException("Found 'extraData' more than once in chainData");
				}

				if(!is_array($claims["extraData"])){
					throw new BadPacketException("'extraData' key should be an array");
				}

				$extraData = $claims['extraData'];

				$extraV = new Validator();
				$extraV->required(self::I_USERNAME)->string();
				$extraV->required(self::I_UUID)->uuid();
				$extraV->optional(self::I_TITLE_ID)->string(); //TODO: find out what this is for
				$extraV->required(self::I_XUID)->string()->digits()->allowEmpty(true);

				$result = $extraV->validate($extraData);
				if($result->isNotValid()){
					$messages = array_map(static function(Failure $f) : string{
						return $f->format();
					}, $result->getFailures());
					throw new BadPacketException("Failed to validate extraData of chain $k: " . implode(", ", $messages));
				}
			}
		}
		if($extraData === null){
			throw new BadPacketException("'extraData' not found in chain data");
		}
		return $extraData;
	}

	/**
	 * @return mixed[] decoded payload of the clientData JWT
	 * @phpstan-return array<string, mixed>
	 *
	 * @throws BadPacketException
	 */
	protected function parseClientData(string $clientDataJwt) : array{
		try{
			[, $clientDataClaims, ] = JwtUtils::parse($clientDataJwt);
		}catch(JwtException $e){
			throw BadPacketException::wrap($e);
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

		$result = $v->validate($clientDataClaims);
		if($result->isNotValid()){
			$messages = array_map(static function(Failure $f) : string{
				return $f->format();
			}, $result->getFailures());
			throw new BadPacketException("Failed to validate ClientData: " . implode(", ", $messages));
		}

		return $clientDataClaims;
	}

	/**
	 * TODO: This is separated for the purposes of allowing plugins (like Specter) to hack it and bypass authentication.
	 * In the future this won't be necessary.
	 *
	 * @throws \InvalidArgumentException
	 */
	protected function processLogin(LoginPacket $packet, bool $authRequired) : void{
		$this->server->getAsyncPool()->submitTask(new ProcessLoginTask($packet->chainDataJwt->chain, $packet->clientDataJwt, $authRequired, $this->authCallback));
		$this->session->setHandler(null); //drop packets received during login verification
	}

	protected function isCompatibleProtocol(int $protocolVersion) : bool{
		return in_array($protocolVersion, ProtocolInfo::ACCEPTED_PROTOCOLS, true);
	}
}
