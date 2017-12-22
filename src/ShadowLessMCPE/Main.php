<?php

namespace ShadowLessMCPE;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\command\{Command, CommandSender};
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use onebone\economyapi\EconomyAPI;

class Main extends PluginBase implements Listener{
    public $db;
	public function onEnable(){
    $this->getLogger()->info("§b§lLoaded Bounty by ShadowLessMCPE");
		$files = array("config.yml");
		foreach($files as $file){
			if(!file_exists($this->getDataFolder() . $file)) {
				@mkdir($this->getDataFolder());
				file_put_contents($this->getDataFolder() . $file, $this->getResource($file));
			}
		}
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->cfg = new Config($this->getDataFolder() . "config.yml", Config::YAML);
		$this->db = new \SQLite3($this->getDataFolder() . "bounty.db");
		$this->db->exec("CREATE TABLE IF NOT EXISTS bounty (player TEXT PRIMARY KEY COLLATE NOCASE, money INT);");
	}
    	public function bountyExists($playe) {
		$result = $this->db->query("SELECT * FROM bounty WHERE player='$playe';");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return empty($array) == false;
	    }
		public function getBountyMoney($play){
        $result = $this->db->query("SELECT * FROM bounty WHERE player = '$play';");
        $resultArr = $result->fetchArray(SQLITE3_ASSOC);
        return (int) $resultArr["money"];
        }
		public function onEntityDamage(EntityDamageEvent $event){
		$entity = $event->getEntity();
		if($entity instanceof Player){
			$player = $entity->getPlayer();
			if($this->cfg->get("bounty_stats") == 1 or $this->cfg->get("health_stats") == 1){
		    $this->renderNametag($player);
		    }
		  }
	    }
	    public function onEntityRegainHealth(EntityRegainHealthEvent $event){
		$entity = $event->getEntity();
		if($entity instanceof Player){
			$player = $entity->getPlayer();
			if($this->cfg->get("bounty_stats") == 1 or $this->cfg->get("health_stats") == 1){
		    $this->renderNametag($player);
		    }
		  }
	    }
		public function getBountyMoney2($play){
		  if(!$this->bountyExists($play)){
			  $i = 0;
			  return $i;
		  }
        $result = $this->db->query("SELECT * FROM bounty WHERE player = '$play';");
        $resultArr = $result->fetchArray(SQLITE3_ASSOC);
        return (int) $resultArr["money"];
        }
	    public function deleteBounty($pla){
		$this->db->query("DELETE FROM bounty WHERE player = '$pla';");
	    }
		public function addBounty($player, $mon){
		if($this->bountyExists($player)){
		   $stmt = $this->db->prepare("INSERT OR REPLACE INTO bounty (player, money) VALUES (:player, :money);");  	
           $stmt->bindValue(":player", $player);
		   $stmt->bindValue(":money", $this->getBountyMoney($player) + $mon);
		   $result = $stmt->execute();	   
		 }
		 if(!$this->bountyExists($player)){
		   $stmt = $this->db->prepare("INSERT OR REPLACE INTO bounty (player, money) VALUES (:player, :money);");  	
           $stmt->bindValue(":player", $player);
		   $stmt->bindValue(":money", $mon);
		   $result = $stmt->execute();	   
	     }
		}
		public function onDeath(PlayerDeathEvent $event) {
        $cause = $event->getEntity()->getLastDamageCause();
        if($cause instanceof EntityDamageByEntityEvent) {
            $player = $event->getEntity();
			$name = $player->getName();
			$lowr = strtolower($name);
            $killer = $event->getEntity()->getLastDamageCause()->getDamager();
			$name2 = $killer->getName();
			if($player instanceof Player){
				if($this->bountyExists($lowr)){
					$money = $this->getBountyMoney($lowr);
					$killer->sendMessage("§8§l[§a+§8]§r§7 You got §6$$money §7for claming §c" . $name . "'s §7bounty!");
					EconomyAPI::getInstance()->addMoney($killer->getName(), $money);
					if($this->cfg->get("bounty_broadcast") == 1){
			          $this->getServer()->broadcastMessage("§8§l[§a+§8]§r§7 §r§c$name2 §7has claimed §c" . $name . "'s §6$$money §7bounty!");
		            }
				if($this->cfg->get("bounty_fine") == 1){
					$perc = $this->cfg->get("fine_percentage");
					$fine = ($money*$perc)/100;
					if(EconomyAPI::getInstance()->myMoney($player->getName()) > $fine){
					  	EconomyAPI::getInstance()->reduceMoney($player->getName(), $fine);
						$player->sendMessage("§8§l[§c+§8]§r§7 §6$fine"."$ §7was taken as bounty fine!");
					}
					if(EconomyAPI::getInstance()->myMoney($player->getName()) <= $fine){
					  	EconomyAPI::getInstance()->setMoney($player->getName(), 0);
						$player->sendMessage("§8§l[§c+§8]§r§7 §6$fine"."$ §7was taken as bounty fine!");
					}
				}
					$this->deleteBounty($lowr);
				}
		 }
    }
}
	    public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool {
		////////////////////// BOUNTY //////////////////////
		 if(strtolower($cmd->getName()) == "bounty"){	
		   if(!isset($args[0])){
		        $sender->sendMessage("§8§l[§6+§8]§r§7 Usage: /bounty (set/me/search/top)");
			    return false;
		   }	
		 switch(strtolower($args[0])){
		 case "set":
		   if(!(isset($args[1])) or !(isset($args[2]))){
			   $sender->sendMessage("§8§l[§6+§8]§r§7 Usage: /bounty set (player) (money)");
			   return false;
			   break;
		   }
		   $invited = $args[1];
		   $lower = strtolower($invited);
		   $name = strtolower($sender->getName());
		   if($lower == $name){
			   $sender->sendMessage("§8§l[§c+§8]§r§7 You can't place a bounty on yourself!");
			   return true;
			   break;
		   }
		    $playerid = $this->getServer()->getPlayerExact($lower);
			$money = $args[2];
		   if(!$playerid instanceof Player) {
			   $sender->sendMessage("§8§l[§c+§8]§r§7 Player $args[1] was not found!");
			   return false;
			   break;
		   }
		   if(!is_numeric($args[2])) {
			   $sender->sendMessage("§8§l[§6+§8]§r§7 Usage: /bounty set $args[1] (money)\n§8§l(§c!§8)§r§7 Please enter in a valid number!");
			   return false;
			   break;
		   }
		   $min = $this->cfg->get("min_bounty");
		   if($money < $min){
			  $sender->sendMessage("§8§l[§6+§8]§r§7 Money has to be greater than $$min!");
			  return false;
			  break;
		   }
		   if($fail = EconomyAPI::getInstance()->reduceMoney($sender, $money)) {
		   $player = $sender->getName();
		   $this->addBounty($lower, $money);
		   $sender->sendMessage("§8§l[§a+§8]§r§7 Successfully added §6$$money a §7bounty on §c$args[1]§7.");
		   $playerid->sendMessage("§8§l[§c+§8]§r§7 A bounty has been added on you for §6$$money §7by §c$name\n§8§l(§c!§8)§r§7 Check the total bounty on you by /bounty me");
		   if($this->cfg->get("bounty_broadcast") == 1){
			   $this->getServer()->broadcastMessage("§8§l[§a+§8]§r§c $player §7just added §6$$money §7bounty on §c$args[1]§7!");
		   }
		   return true;
		   break;
		   }else {
						switch($fail){
							case EconomyAPI::RET_INVALID:
								$sender->sendMessage("§8§l[§c+§8]§r§7 You don't have enough money to set that bounty!");
								return false;
								break;
							case EconomyAPI::RET_CANCELLED:
								$sender->sendMessage("§8§l[§c+§8]§r§7 Error!");
								break;
							case EconomyAPI::RET_NO_ACCOUNT:
								$sender->sendMessage("§8§l[§c+§8]§r§7 §6Error!");
								break;
						}
					}
		   break;
		   case "me":
			   $lower = strtolower($sender->getName());
			   if(isset($args[1])){
				   $sender->sendMessage("§8§l[§6+§8]§r§7 Usage: /bounty me");
				   return true;
				   break;
			   }
			   if(!$this->bountyExists($lower)){
				   $sender->sendMessage("\n\n§8§l[§d+§8]§r§7 §5Bounty §8§l[§d+§8]§r§7\n\n§7You don't have a bounty at the moment\n\n\n");
				   return false;
				   break;
			   }
			   if($this->bountyExists($lower)){
				   $bounty = $this->getBountyMoney($lower);
				   $sender->sendMessage("\n\n§8§l[§d+§8]§r§7 §5Bounty §8§l[§d+§8]§r§7\n\n§7Bounty§8: §6$$bounty\n\n\n");
				   return true;
				   break;
			   }
			   break;
		   
		   case "search":
			   if(!isset($args[1])){
				   $sender->sendMessage("§8§l[§6+§8]§r§7 Usage: /bounty search <player>");
				   return false;
				   break;
			   }
			   $lower = strtolower($args[1]);
			   if(!$this->bountyExists($lower)){
				   $sender->sendMessage("\n\n§8§l[§d+§8]§r§7 §5Bounty §8§l[§d+§8]§r§7\n\n§7$args[1] doesn't have a bounty at the moment\n\n\n");
				   return false;
				   break;
			   }
			   if($this->bountyExists($lower)){
				   $bounty = $this->getBountyMoney($lower);
				   $sender->sendMessage("\n\n§8§l[§d+§8]§r§7 §5Bounty §8§l[§d+§8]§r§7\n\n§c$args[1]\n§7Bounty§8: §6$$bounty\n\n\n");
				   return true;
				   break;
			   }
			       break;
		   case "top":
		       if(isset($args[1])){
				   $sender->sendMessage("§cUsage: /bounty top");
				   return true;
				   break;
			   }
			          $sender->sendMessage("§8§l[§d+§8]§r§7 §5Most Wanted §8§l[§d+§8]");
		              $result = $this->db->query("SELECT * FROM bounty ORDER BY money DESC LIMIT 10;"); 			
				      $i = 1; 
					  while($row = $result->fetchArray(SQLITE3_ASSOC)){
						    $play = $row["player"];
							$money = $row["money"];
							$sender->sendMessage("§f§l$i. §r§d$play §7| §6$$money");
						    $i++; 
				      }
			return true;
		    break; 
		   default:
		    $sender->sendMessage("§8§l[§6+§8]§r§7 Usage: /bounty (set/me/search/top)");
		    break;
			 }
	}
  }
}
