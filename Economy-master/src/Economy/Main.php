<?php

/* Economy: @link github.com/ShaidDev */

namespace Economy;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

//libs
use Economy\libs\SimpleForm;
use Economy\libs\CustomForm;

//commands
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

class Main extends PluginBase implements Listener
{
	public $database;

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$this->database = new \SQLite3($this->getDataFolder() ."players.db");
		$this->database->query("CREATE TABLE IF NOT EXISTS players(username TEXT NOT NULL, money INTEGER NOT NULL);");

		$this->getServer()->getCommandMap()->register("economy", new EconomyCommand($this));
	}

	public function createData(DataPacketReceiveEvent $event){
		if($event->getPacket() instanceof LoginPacket){
			$username = strtolower($event->getPacket()->username);
			if(!$this->database->query("SELECT * FROM players WHERE username = '$username'")->fetchArray(SQLITE3_ASSOC)){
				$this->database->query("INSERT INTO players(username, money) VALUES('$username', 0);");
			}
		}
	}

	public function getMoney(Player $player){
		$username = strtolower($player->getName());
		$result = $this->database->query("SELECT money FROM players WHERE username = '$username'")->fetchArray(SQLITE3_ASSOC);

		return $result['money'];
	}

	public function addMoney(Player $player, int $amount){
		$username = strtolower($player->getName());
		$this->database->query("UPDATE `players` SET `money` = `money` +'$amount' WHERE `username` = '$username'");
	}

	public function remMoney(Player $player, int $amount){
		$username = strtolower($player->getName());
		$this->database->query("UPDATE `players` SET `money` = `money` -'$amount' WHERE `username` = '$username'");
	}

	public function createForm(Player $player, string $category = "main"){
		switch($category):
			case "main":
			$form = new SimpleForm(function($player, $data){
				switch($data):
					case 0:
					$player->sendMessage(TextFormat::GRAY ."Your balance: ". TextFormat::GREEN . $this->getMoney($player));
					break;
					case 1:
					$this->createForm($player, "pay");
					break;
					case 2:
					if($player->isOp()){
						$this->createForm($player, "system");
					}else{
						$player->sendMessage(TextFormat::RED ."No enough permissions!");
					}
					break;
				endswitch;
			});

			$form->setTitle("Economy");
			$form->setContent(TextFormat::GRAY ."Welcome ". TextFormat::GREEN . $player->getName() . TextFormat::GRAY ."!");
			$form->addButton(TextFormat::GREEN ."See your balance");
			$form->addButton(TextFormat::YELLOW ."Transfer money");
			if($player->isOp()){
				$form->addButton(TextFormat::GOLD ."Control economy");
			}
			$form->sendToPlayer($player);
			break;
			case "pay":
			$form = new CustomForm(function(Player $player, $data){
				if($data == 0){ 
					return;
				}
				if(is_null($data[0])){
					$player->sendMessage(TextFormat::RED ."You did not ender a nickname!");
					return true;
				}
				$target = $this->getServer()->getPlayer($data[0]);
				if(!is_null($target)){
				if($target->getName() == $player->getName()){
					$player->sendMessage(TextFormat::RED ."You cannot transfer money to yourself!");
				}else{
					if($this->getMoney($player) >= $data[1]){
						$this->remMoney($player, $data[1]);
						$this->addMoney($player, $data[1]);
						$player->sendMessage(TextFormat::GRAY ."You successful passed ". TextFormat::GREEN . $data[1] ."$". TextFormat::GRAY ." in player ". TextFormat::AQUA . $target->getName());
						$target->sendMessage(TextFormat::GRAY ."Player ". TextFormat::AQUA . $player->getName() . TextFormat::GRAY ." gave you ". TextFormat::GREEN . $data[1] ."$");
					}else{
						$player->sendMessage(TextFormat::RED ."No enough money!");
					}
				}
				}else{
					$player->sendMessage(TextFormat::RED ."Selected player already offline!");
				}
			});

			$form->setTitle("Economy");
			$form->addInput("Enter is name player:");
			$form->addSlider("Select amount", 1, $this->getMoney($player), 1);
			$form->sendToPlayer($player);
			break;
			case "system":
			$form = new SimpleForm(function($player, $data){
				switch($data):
					case 0:
					if($player->isOp()){
						$this->createForm($player, "addmoney");
					}else{
						$player->sendMessage(TextFormat::RED ."No enough permissions!");
					}
					break;
					case 1:
					if($player->isOp()){
						$this->createForm($player, "remmoney");
					}else{
						$player->sendMessage(TextFormat::RED ."No enough permissions!");
					}
					break;
				endswitch;
			});

			$form->setTitle(TextFormat::GOLD ."Economy System");
			$form->setContent(TextFormat::GRAY ."Welcome to ". TextFormat::AQUA ."Economy System, ". TextFormat::GREEN . $player->getName() . TextFormat::GRAY ."!");
			$form->addButton(TextFormat::GREEN ."Give money");
			$form->addButton(TextFormat::RED ."Reduce money");
			$form->sendToPlayer($player);
			break;
			case "addmoney":
			$form = new CustomForm(function(Player $player, $data){
				if($data == 0){ 
					return;
				}
				if(is_null($data[0])){
					$player->sendMessage(TextFormat::RED ."You did not ender a nickname!");
					return true;
				}
				$target = $this->getServer()->getPlayer($data[0]);
				if(!is_null($target)){
					$this->addMoney($player, $data[1]);
					$player->sendMessage(TextFormat::GRAY ."You successful given ". TextFormat::GREEN . $data[1] ."$". TextFormat::GRAY ." in player ". TextFormat::AQUA . $target->getName());
					$target->sendMessage(TextFormat::GRAY ."Player ". TextFormat::AQUA . $player->getName() . TextFormat::GRAY ." gave you ". TextFormat::GREEN . $data[1] ."$");
				}else{
					$player->sendMessage(TextFormat::RED ."Selected player already offline!");
				}
			});

			$form->setTitle(TextFormat::GOLD ."Economy System");
			$form->addInput("Enter is name player:");
			$form->addSlider("Select amount", 1, 99999, 1);
			$form->sendToPlayer($player);
			break;
			case "remmoney":
			$form = new CustomForm(function(Player $player, $data){
				if($data == 0){ 
					return;
				}
				if(is_null($data[0])){
					$player->sendMessage(TextFormat::RED ."You did not ender a nickname!");
					return true;
				}
				$target = $this->getServer()->getPlayer($data[0]);
				if(!is_null($target)){
				if($this->getMoney($target) >= $data[1]){
					$this->remMoney($player, $data[1]);
					$player->sendMessage(TextFormat::GRAY ."You successful reduced ". TextFormat::GREEN . $data[1] ."$". TextFormat::GRAY ." in player ". TextFormat::AQUA . $target->getName());
					$target->sendMessage(TextFormat::GRAY ."Player ". TextFormat::AQUA . $player->getName() . TextFormat::GRAY ." reduced you money is ". TextFormat::GREEN . $data[1] ."$");
				}else{
					$player->sendMessage(TextFormat::RED ."Player does not have how much money!");
				}
				}else{
					$player->sendMessage(TextFormat::RED ."Selected player already offline!");
				}
			});

			$form->setTitle(TextFormat::GOLD ."System Economy");
			$form->addInput("Enter is name player:");
			$form->addInput("Enter amount");
			$form->sendToPlayer($player);
			break;
		endswitch;
	}
}

class EconomyCommand extends Command
{
	public $plugin;

	public function __construct(Main $plugin){
		$this->plugin = $plugin;
		parent::__construct("economy", "system economy");
	}

	public function execute(CommandSender $sender, $aliases, array $args): bool{
		if($sender instanceof Player){
			$this->plugin->createForm($sender, "main");
			return true;
		}else{
			$sender->sendMessage(TextFormat::RED ."Only for players!");
			return true;
		}
	}
}
