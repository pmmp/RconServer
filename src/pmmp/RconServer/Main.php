<?php

declare(strict_types=1);

namespace pmmp\RconServer;

use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginException;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Rules\AllOf;
use Respect\Validation\Rules\Between;
use Respect\Validation\Rules\GreaterThan;
use Respect\Validation\Rules\IntType;
use Respect\Validation\Rules\Ip;
use Respect\Validation\Rules\Key;
use Respect\Validation\Rules\StringType;
use Respect\Validation\Validator;
use function base64_encode;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function random_bytes;
use function yaml_emit;
use function yaml_parse;

class Main extends PluginBase{

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
			$this->getServer()->getNetwork()->registerInterface(new Rcon(
				$config,
				function(string $commandLine) : string{
					$response = new RconCommandSender($this->getServer(), $this->getServer()->getLanguage());
					$response->recalculatePermissions();
					$this->getServer()->dispatchCommand($response, $commandLine);
					return $response->getMessage();
				},
				$this->getServer()->getLogger(),
				$this->getServer()->getTickSleeper()
			));
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
			file_put_contents($fileLocation, yaml_emit($config));
			$this->getLogger()->notice('RCON config file generated at ' . $fileLocation . '. Please customize it.');
		}else{
			try{
				$config = yaml_parse(file_get_contents($fileLocation));
			}catch(\ErrorException $e){
				throw new PluginException($e->getMessage());
			}
		}

		$validator = new Validator(
			new Key('ip', new Ip(), true),
			new Key('port', new AllOf(new IntType(), new Between(0, 65535))),
			new Key('max-connections', new AllOf(new IntType(), new GreaterThan(0))),
			new Key('password', new StringType())
		);
		$validator->setName('rcon.yml');
		try{
			$validator->assert($config);
		}catch(NestedValidationException $e){
			throw new PluginException($e->getFullMessage(), 0, $e);
		}

		return new RconConfig((string) $config['ip'], (int) $config['port'], (int) $config['max-connections'], (string) $config['password']);
	}
}
