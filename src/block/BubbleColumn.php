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

namespace pocketmine\block;

use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use function max;
use function min;

class BubbleColumn extends Water{
	private bool $draggingDown = false;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->bool($this->draggingDown);
	}

	public function isDraggingDown() : bool{ return $this->draggingDown; }

	/** @return $this */
	public function setDraggingDown(bool $value) : self{
		$this->draggingDown = $value;
		return $this;
	}

	public function getStillForm() : Block{ return VanillaBlocks::WATER()->setStill(); }
	public function getFlowingForm() : Block{ return VanillaBlocks::WATER(); }

	public function addVelocityToEntity(Entity $entity) : ?Vector3{
		return null; //The vertical current is applied in onEntityInside().
	}

	public function onEntityInside(Entity $entity) : bool{
		parent::onEntityInside($entity);
		if($entity instanceof Living){
			$entity->setAirSupplyTicks($entity->getMaxAirSupplyTicks());
		}
		$motion = $entity->getMotion();
		$entity->setMotion(new Vector3($motion->x, $this->draggingDown ? max(-0.3, $motion->y - 0.03) : min(0.7, $motion->y + 0.06), $motion->z));
		return true;
	}

	/** Returns the current direction supplied by the block below, or null if unsupported. */
	public static function getDragFromSupport(Block $below) : ?bool{
		return match(true){
			$below instanceof self => $below->isDraggingDown(),
			$below->getTypeId() === BlockTypeIds::SOUL_SAND => false,
			$below->getTypeId() === BlockTypeIds::MAGMA => true,
			default => null
		};
	}

	public function onScheduledUpdate() : void{
		$drag = self::getDragFromSupport($this->getSide(Facing::DOWN));
		$world = $this->position->getWorld();
		if($drag === null){
			$world->setBlock($this->position, VanillaBlocks::WATER()->setStill());
			return;
		}
		if($this->draggingDown !== $drag){
			$world->setBlock($this->position, (clone $this)->setDraggingDown($drag));
		}
		$above = $this->getSide(Facing::UP);
		if($above instanceof Water && !$above instanceof self && $above->isSource()){
			$world->setBlock($above->getPosition(), VanillaBlocks::BUBBLE_COLUMN()->setDraggingDown($drag));
		}
	}
}
