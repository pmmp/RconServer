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

namespace pmmp\RconServer;

use pocketmine\command\ConsoleCommandSender;
use pocketmine\lang\TranslationContainer;
use function trim;

class RconCommandSender extends ConsoleCommandSender{

	/** @var string */
	private $messages = "";

	public function sendMessage($message) : void{
		if($message instanceof TranslationContainer){
			$message = $this->getServer()->getLanguage()->translate($message);
		}else{
			$message = $this->getServer()->getLanguage()->translateString($message);
		}

		$this->messages .= trim($message, "\r\n") . "\n";
	}

	public function getMessage() : string{
		return $this->messages;
	}

	public function getName() : string{
		return "Rcon";
	}
}