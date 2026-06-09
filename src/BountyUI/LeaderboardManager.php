<?php

declare(strict_types=1);

namespace BountyUI;

use pocketmine\utils\Config;
use pocketmine\world\Position;

class LeaderboardManager{

    private Main $plugin;

    /** @var Leaderboard[] */
    private array $leaderboards = [];

    private Config $config;

    public function __construct(Main $plugin){

        $this->plugin = $plugin;

        $this->config = new Config(
            $plugin->getDataFolder() . "leaderboards.yml",
            Config::YAML,
            [
                "leaderboards" => []
            ]
        );
    }

    public function load() : void{

        foreach(
            $this->config->get("leaderboards", [])
            as $data
        ){

            $world = $this->plugin
                ->getServer()
                ->getWorldManager()
                ->getWorldByName(
                    $data["world"]
                );

            if($world === null){
                continue;
            }

            $lb = new Leaderboard(
                $this->plugin,
                new Position(
                    (float)$data["x"],
                    (float)$data["y"],
                    (float)$data["z"],
                    $world
                )
            );

            $this->leaderboards[] = $lb;
        }

        $this->updateAll();
    }

    public function save() : void{

        $data = [];

        foreach($this->leaderboards as $lb){
            $data[] = $lb->toArray();
        }

        $this->config->set(
            "leaderboards",
            $data
        );

        $this->config->save();
    }

    public function create(
        Position $position
    ) : void{

        $lb = new Leaderboard(
            $this->plugin,
            $position
        );

        $this->leaderboards[] = $lb;

        $lb->update();

        $this->save();
    }

    public function removeNearest(
        Position $position,
        float $radius = 5
    ) : bool{

        foreach(
            $this->leaderboards
            as $key => $lb
        ){

            if(
                $lb->getPosition()->distance(
                    $position
                ) <= $radius
            ){

                $lb->remove();

                unset(
                    $this->leaderboards[$key]
                );

                $this->save();

                return true;
            }
        }

        return false;
    }

    public function updateAll() : void{

        foreach(
            $this->leaderboards
            as $lb
        ){
            $lb->update();
        }
    }
}
