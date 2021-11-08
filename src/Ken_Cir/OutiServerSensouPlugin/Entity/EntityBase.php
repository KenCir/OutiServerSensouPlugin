<?php

namespace Ken_Cir\OutiServerSensouPlugin\Entity;

use Ken_Cir\OutiServerSensouPlugin\Main;
use pocketmine\entity\Monster;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\Level;
use pocketmine\Player;

/**
 * エンティティ系ベース
 */
abstract class EntityBase extends Monster
{

    public const NETWORK_ID = null;

    protected ?Player $target = null;
    protected bool $isNeutral = false;

    protected float $speed = 0.28;
    protected int $coolTime = 0;

    public $width = 0.6;
    public $height = 1.9;

    /**
     * @return string
     * エンティティ名を取得する
     */
    public function getName() : string{
        return "";
    }

    /**
     * @param int $distance
     * @return Player|null
     * プレイヤーとの距離計算
     */
    public function findClosestPlayer(int $distance) : ?Player {
        $result = null;
        foreach ($this->getLevel()->getPlayers() as $player) {
            //[$playerとこのエンティティの距離 < 前の$playerの距離]なら、$resultに$playerを代入
            if ($player->distance($this) < $distance) {
                $result = $player;//結果に代入
                $distance = $player->distance($this);//距離を更新
            }
        }

        return $result;
    }

    public function entityBaseTick(int $tickDiff = 1): bool
    {
        $level = $this->getLevel();
        $time = $level->getTimeOfDay();
        // ゾンビやスケルトンは昼間では焼死するのでkill
        if(0 <= $time && $time < Level::TIME_NIGHT){
            $this->kill();
        }
        $hasUpdate = parent::entityBaseTick($tickDiff);
        $this->attackTime -= $tickDiff;
        $this->coolTime -= $tickDiff;

        if($this->attackTime > 0)
            return false;
        else
            $this->attackTime = 0;

        if($this->getTarget() == null) {
            $preTarget = $this->findClosestPlayer(35);
            if ($preTarget === null) {
                return $hasUpdate;
            }
            else {
                Main::getInstance()->getPluginLogger()->info("プレイヤーロックオン");
                $this->target = $preTarget;
            }
        }

        $target = $this->getTarget();
        if(!($target instanceof Player))
            return $hasUpdate;

        $speed = $this->getSpeed();
        $this->lookAt($target);

        if($this->distance($target) <= 1){
            if($this->coolTime < 0){
                $ev = new EntityDamageByEntityEvent($this, $target, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 3);;
                $target->attack($ev);
                $this->coolTime = 23;
            }
            return $hasUpdate;
        }
        else if ($this->distance($target) >= 5) {//5ブロックより遠ければ
            $preTarget = $this->findClosestPlayer(10);//10ブロック以内の一番近いプレイヤーを取得
            if ($preTarget === null) {//プレイヤーが近くにいなければ
                $this->target = null;//ターゲットを空にして、処理をやめる。
                return $hasUpdate;
            } else {//プレイヤーが存在すれば
                $this->target = $preTarget;//ターゲットを設定
            }
        }


        $moveX = sin(-deg2rad($this->yaw)) * $speed;
        $moveZ = cos(-deg2rad($this->yaw)) * $speed;
        $this->checkFront();
        $this->motion->x = $moveX;
        $this->motion->z = $moveZ;

        return true;
    }


    public function attack(EntityDamageEvent $source): void
    {
        if($source instanceof EntityDamageByEntityEvent)
            $source->setKnockBack(0.5);
        parent::attack($source);
        $this->attackTime = 17;

    }


    public function jump(): void
    {
        if($this->onGround)
            $this->motion->y = 0.5;
    }


    public function checkFront(): void
    {
        $dv = $this->getDirectionVector()->multiply(1);
        $checkPos = $this->add($dv->x, 0, $dv->z)->floor();
        if($this->level->getBlockAt((int)$checkPos->x, (int)$this->y+1, (int)$checkPos->z)->isSolid())
        {
            return;
        }
        if($this->level->getBlockAt((int)$checkPos->x, (int)$this->y, (int)$checkPos->z)->isSolid())
        {
            $this->jump();
        }
    }


    public function setTarget(Player $player)
    {
        $this->isNeutral = false;
        $this->target = $player;
    }


    public function getTarget()
    {
        return $this->target;
    }


    public function getSpeed(): float
    {
        return $this->speed;
    }


    public function hasTarget(){
        return !is_null($this->getTarget());
    }

    public function getXpDropAmount() : int{
        return 0;
    }

}