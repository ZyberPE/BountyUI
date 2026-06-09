<?php

declare(strict_types=1);

namespace BountyUI;

use pocketmine\utils\TextFormat;
use pocketmine\world\particle\FloatingTextParticle;
use pocketmine\world\Position;

class Leaderboard{

    private Main $plugin;
    private Position $position;

    private FloatingTextParticle $particle;

    public function __construct(
        Main $plugin,
        Position $position
    ){
        $this->plugin = $plugin;
        $this->position = $position;

        $this->particle = new FloatingTextParticle(
            "",
            ""
        );
    }

    public function getPosition() : Position{
        return $this->position;
    }

    public function update() : void{

        $bounties = $this->plugin
            ->getBounties()
            ->get("bounties", []);

        $sorted = [];

        foreach($bounties as $player => $data){
            $sorted[$player] = (int)($data["amount"] ?? 0);
        }

        arsort($sorted);

        $players = array_keys($sorted);
        $values = array_values($sorted);

        $title = TextFormat::colorize(
            $this->plugin->getConfig()->getNested(
                "topbounties.title",
                "&6&lTop Bounties"
            )
        );

        $lines = $this->plugin->getConfig()->getNested(
            "topbounties.lines",
            []
        );

        $text = $title;

        foreach($lines as $index => $line){

            $rank = $index + 1;

            $line = str_replace(
                [
                    "{player{$rank}}",
                    "{value{$rank}}"
                ],
                [
                    $players[$index] ?? "N/A",
                    number_format(
                        (int)($values[$index] ?? 0)
                    )
                ],
                $line
            );

            $text .= "\n" .
                TextFormat::colorize($line);
        }

        $this->particle->setTitle($text);

        $this->position
            ->getWorld()
            ->addParticle(
                $this->position,
                $this->particle
            );
    }

    public function remove() : void{

        $this->particle->setInvisible(true);

        $this->position
            ->getWorld()
            ->addParticle(
                $this->position,
                $this->particle
            );
    }

    public function toArray() : array{
        return [
            "world" => $this->position
                ->getWorld()
                ->getFolderName(),
            "x" => $this->position->getX(),
            "y" => $this->position->getY(),
            "z" => $this->position->getZ()
        ];
    }
}
