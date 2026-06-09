<?php

declare(strict_types=1);

namespace BountyUI;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\Server;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDeathEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;

use BountyUI\LeaderboardManager;
use BountyUI\task\UpdateTask;

use onebone\economyapi\EconomyAPI;
use jojoe77777\FormAPI\CustomForm;

class Main extends PluginBase implements Listener{

    private Config $bounties;

    private LeaderboardManager $leaderboardManager;

    public function onEnable() : void{

        @mkdir($this->getDataFolder());

        $this->saveResource("config.yml");
        $this->saveResource("bounties.yml");

        if(!file_exists($this->getDataFolder() . "leaderboards.yml")){
            $cfg = new Config(
                $this->getDataFolder() . "leaderboards.yml",
                Config::YAML,
                [
                    "leaderboards" => []
                ]
            );
            $cfg->save();
        }

        $this->bounties = new Config(
            $this->getDataFolder() . "bounties.yml",
            Config::YAML
        );

        $this->leaderboardManager = new LeaderboardManager($this);

        $this->leaderboardManager->load();

        $this->getScheduler()->scheduleRepeatingTask(
            new UpdateTask($this),
            20 * 60
        );

        $this->getServer()->getPluginManager()->registerEvents(
            $this,
            $this
        );
    }

    public function getBounties() : Config{
        return $this->bounties;
    }

    public function getLeaderboardManager() : LeaderboardManager{
        return $this->leaderboardManager;
    }

    private function msg(string $key, array $replace = []) : string{

        $message = $this->getConfig()->get("messages")[$key] ?? "Message not found";

        foreach($replace as $k => $v){
            $message = str_replace(
                "{" . $k . "}",
                (string)$v,
                $message
            );
        }

        return $message;
    }

    public function onCommand(
        CommandSender $sender,
        Command $command,
        string $label,
        array $args
    ) : bool{

        switch(strtolower($command->getName())){

            case "topbounties":

                if(!$sender instanceof Player){
                    return true;
                }

                if(!isset($args[0])){
                    $sender->sendMessage(
                        "§eUsage: /topbounties <create|remove>"
                    );
                    return true;
                }

                switch(strtolower($args[0])){

                    case "create":

                        $this->leaderboardManager->create(
                            $sender->getPosition()
                        );

                        $sender->sendMessage(
                            "§aTop bounty leaderboard created."
                        );
                        break;

                    case "remove":

                        if(
                            $this->leaderboardManager->removeNearest(
                                $sender->getPosition()
                            )
                        ){
                            $sender->sendMessage(
                                "§cLeaderboard removed."
                            );
                        }else{
                            $sender->sendMessage(
                                "§cNo leaderboard found nearby."
                            );
                        }

                        break;
                }

                return true;

            case "bounty":

                if(!$sender instanceof Player){
                    $sender->sendMessage("Run this command in game.");
                    return true;
                }

                $players = [];

                foreach(Server::getInstance()->getOnlinePlayers() as $p){

                    if($p->getName() !== $sender->getName()){
                        $players[] = $p->getName();
                    }
                }

                if(count($players) <= 0){
                    $sender->sendMessage(
                        $this->msg("no_players")
                    );
                    return true;
                }

                $form = new CustomForm(
                    function(Player $player, ?array $data) use ($players){

                        if($data === null){
                            return;
                        }

                        $target = $players[$data[0]] ?? null;

                        if($target === null){
                            return;
                        }

                        $amount = (int)$data[1];

                        if($target === $player->getName()){
                            $player->sendMessage(
                                $this->msg("self_bounty")
                            );
                            return;
                        }

                        if($amount <= 0){
                            $player->sendMessage(
                                $this->msg("invalid_amount")
                            );
                            return;
                        }

                        $econ = EconomyAPI::getInstance();

                        if($econ->myMoney($player) < $amount){
                            $player->sendMessage(
                                $this->msg("not_enough_money")
                            );
                            return;
                        }

                        $bounties = $this->bounties->get(
                            "bounties",
                            []
                        );

                        if(isset($bounties[$target])){
                            $player->sendMessage(
                                $this->msg("already_has_bounty")
                            );
                            return;
                        }

                        $econ->reduceMoney(
                            $player,
                            $amount
                        );

                        $bounties[$target] = [
                            "amount" => $amount,
                            "setter" => $player->getName()
                        ];

                        $this->bounties->set(
                            "bounties",
                            $bounties
                        );

                        $this->bounties->save();

                        Server::getInstance()->broadcastMessage(
                            $this->msg(
                                "bounty_set_broadcast",
                                [
                                    "player" => $player->getName(),
                                    "target" => $target,
                                    "amount" => $amount
                                ]
                            )
                        );
                    }
                );

                $form->setTitle("§6Bounty Menu");
                $form->addDropdown(
                    "Select Player",
                    $players
                );
                $form->addInput(
                    "Enter Bounty Amount"
                );

                $sender->sendForm($form);

                return true;
        }

        return true;
    }

    public function onDeath(
        EntityDeathEvent $event
    ) : void{

        $player = $event->getEntity();

        if(!$player instanceof Player){
            return;
        }

        $bounties = $this->bounties->get(
            "bounties",
            []
        );

        $name = $player->getName();

        if(!isset($bounties[$name])){
            return;
        }

        $cause = $player->getLastDamageCause();

        if(!$cause instanceof EntityDamageByEntityEvent){
            return;
        }

        $killer = $cause->getDamager();

        if(!$killer instanceof Player){
            return;
        }

        $amount = (int)$bounties[$name]["amount"];

        EconomyAPI::getInstance()->addMoney(
            $killer,
            $amount
        );

        unset($bounties[$name]);

        $this->bounties->set(
            "bounties",
            $bounties
        );

        $this->bounties->save();

        Server::getInstance()->broadcastMessage(
            $this->msg(
                "bounty_claimed_broadcast",
                [
                    "target" => $name,
                    "killer" => $killer->getName(),
                    "amount" => $amount
                ]
            )
        );

        $this->leaderboardManager->updateAll();
    }
}
