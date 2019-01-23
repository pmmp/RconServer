<?php

declare(strict_types=1);

namespace pmofficial\RconServer;

use Particle\Validator\Validator;
use pocketmine\command\RemoteConsoleCommandSender;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginException;
use function base64_encode;
use function file_exists;
use function implode;
use function random_bytes;
use function yaml_emit_file;
use function yaml_parse_file;
use const PHP_INT_MAX;

class Main extends PluginBase{

	/** @var Rcon|null */
	private $rcon = null;

	public function onEnable() : void{
		try{
			$config = $this->loadConfig();
		}catch(PluginException $e){
			$this->getLogger()->alert('Invalid config file: ' . $e->getMessage());
			$this->getLogger()->alert('Please fix the errors and restart the server.');
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}

		$this->getLogger()->info('Starting RCON on ' . $config->getIp() . ':' . $config->getPort());
		try{
			$this->rcon = new Rcon(
				$config,
				function(string $commandLine) : string{
					$response = new RemoteConsoleCommandSender();
					$this->getServer()->dispatchCommand($response, $commandLine);
					return $response->getMessage();
				},
				$this->getServer()->getLogger(),
				$this->getServer()->getTickSleeper()
			);
		}catch(\RuntimeException $e){
			$this->getLogger()->alert('Failed to start RCON: ' . $e->getMessage());
			$this->getLogger()->logException($e);
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}
	}

	/**
	 * @return RconConfig
	 * @throws PluginException
	 */
	private function loadConfig() : RconConfig{
		$fileLocation = $this->getDataFolder() . 'rcon.yml';
		if(!file_exists($fileLocation)){
			$config = [
				'ip' => $this->getServer()->getIp(),
				'port' => $this->getServer()->getPort(),
				'max-connections' => 50,
				'password' => base64_encode(random_bytes(8))
			];
			yaml_emit_file($fileLocation, $config);
			$this->getLogger()->notice('RCON config file generated at ' . $fileLocation . '. Please customize it.');
		}else{
			try{
				$config = yaml_parse_file($fileLocation);
			}catch(\ErrorException $e){
				throw new PluginException($e->getMessage());
			}
		}

		$validator = new Validator();
		$validator->required('ip')->string();
		$validator->required('port')->between(0, 65535)->integer();
		$validator->required('max-connections')->between(1, PHP_INT_MAX)->integer(); //greaterThan is too dumb for this
		$validator->required('password')->string();

		$result = $validator->validate($config);
		if($result->isNotValid()){
			$messages = [];
			foreach($result->getFailures() as $failure){
				$messages[] = $failure->format();
			}
			throw new PluginException(implode('; ', $messages));
		}

		return new RconConfig((string) $config['ip'], (int) $config['port'], (int) $config['max-connections'], (string) $config['password']);
	}

	public function onDisable() : void{
		if($this->rcon !== null){
			$this->rcon->stop();
			$this->rcon = null;
		}
	}
}
