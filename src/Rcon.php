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

/**
 * Implementation of the Source RCON Protocol to allow remote console commands
 * Source: https://developer.valvesoftware.com/wiki/Source_RCON_Protocol
 */

namespace pmmp\RconServer;

use pocketmine\network\NetworkInterface;
use pocketmine\snooze\SleeperHandler;
use pocketmine\snooze\SleeperNotifier;
use pocketmine\utils\TextFormat;
use function socket_bind;
use function socket_close;
use function socket_create;
use function socket_create_pair;
use function socket_last_error;
use function socket_listen;
use function socket_set_block;
use function socket_set_option;
use function socket_strerror;
use function socket_write;
use function trim;
use const AF_INET;
use const AF_UNIX;
use const PTHREADS_INHERIT_NONE;
use const SO_REUSEADDR;
use const SOCK_STREAM;
use const SOCKET_ENOPROTOOPT;
use const SOCKET_EPROTONOSUPPORT;
use const SOL_SOCKET;
use const SOL_TCP;

class Rcon implements NetworkInterface{
	/** @var \Socket */
	private $socket;

	/** @var RconThread */
	private $thread;

	/** @var \Socket */
	private $ipcMainSocket;
	/** @var \Socket */
	private $ipcThreadSocket;

	/**
	 * @phpstan-param callable(string $command) : string $onCommandCallback
	 * @throws RconException
	 */
	public function __construct(RconConfig $config, callable $onCommandCallback, \ThreadedLogger $logger, SleeperHandler $sleeper){
		$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if($socket === false){
			throw new RconException("Failed to create socket: " . socket_strerror(socket_last_error()));
		}
		$this->socket = $socket;

		if(!socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1)){
			throw new RconException("Unable to set option on socket: " . trim(socket_strerror(socket_last_error())));
		}

		if(!@socket_bind($this->socket, $config->getIp(), $config->getPort()) or !@socket_listen($this->socket, 5)){
			throw new RconException('Failed to open main socket: ' . trim(socket_strerror(socket_last_error())));
		}

		socket_set_block($this->socket);

		$ret = @socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $ipc);
		if(!$ret){
			$err = socket_last_error();
			if(($err !== SOCKET_EPROTONOSUPPORT and $err !== SOCKET_ENOPROTOOPT) or !@socket_create_pair(AF_INET, SOCK_STREAM, 0, $ipc)){
				throw new RconException('Failed to open IPC socket: ' . trim(socket_strerror(socket_last_error())));
			}
		}

		[$this->ipcMainSocket, $this->ipcThreadSocket] = $ipc;

		$notifier = new SleeperNotifier();

		$sleeper->addNotifier($notifier, function() use ($onCommandCallback) : void{
			$response = $onCommandCallback($this->thread->cmd);

			$this->thread->response = TextFormat::clean($response);
			$this->thread->synchronized(function(RconThread $thread) : void{
				$thread->notify();
			}, $this->thread);
		});

		$this->thread = new RconThread($this->socket, $config->getPassword(), $config->getMaxConnections(), $logger, $this->ipcThreadSocket, $notifier);
	}

	public function start() : void{
		$this->thread->start(PTHREADS_INHERIT_NONE);
	}

	public function tick() : void{

	}

	public function setName(string $name) : void{

	}

	public function shutdown() : void{
		$this->thread->close();
		socket_write($this->ipcMainSocket, "\x00"); //make select() return
		$this->thread->quit();

		@socket_close($this->socket);
		@socket_close($this->ipcMainSocket);
		@socket_close($this->ipcThreadSocket);
	}
}
