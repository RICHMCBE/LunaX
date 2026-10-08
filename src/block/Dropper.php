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

use pocketmine\block\tile\Container;
use pocketmine\block\tile\Dropper as TileDropper;
use pocketmine\block\utils\AnyFacing;
use pocketmine\block\utils\AnyFacingTrait;
use pocketmine\block\utils\PoweredByRedstone;
use pocketmine\block\utils\PoweredByRedstoneTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\BlockTransaction;
use function abs;

final class Dropper extends Opaque implements AnyFacing, PoweredByRedstone{
	use AnyFacingTrait;
	use PoweredByRedstoneTrait;
	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->facing($this->facing);
		$w->bool($this->powered);
	}
	public function place(BlockTransaction $tx, Item $item, Block $blockReplace, Block $blockClicked, int $face, Vector3 $clickVector, ?Player $player = null) : bool{
		$this->powered = false;
		if($player !== null){
			$this->facing = Facing::opposite($player->getHorizontalFacing());
			if(abs($player->getPosition()->x - $blockReplace->position->x) < 2 && abs($player->getPosition()->z - $blockReplace->position->z) < 2){
				$height = $player->getEyePos()->y - $blockReplace->position->y;
				if($height > 2){ $this->facing = Facing::UP; }
				elseif($height < 0){ $this->facing = Facing::DOWN; }
			}
		}
		return parent::place($tx, $item, $blockReplace, $blockClicked, $face, $clickVector, $player);
	}
	public function onPostPlace() : void{ $this->onNearbyBlockChange(); }
	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		$tile = $this->position->getWorld()->getTile($this->position);
		if($player !== null && $tile instanceof TileDropper && $tile->canOpenWith($item->getCustomName())){
			$player->setCurrentWindow($tile->getInventory());
		}
		return true;
	}
	public function onNearbyBlockChange() : void{
		// The server has no general redstone propagation engine yet. Accept direct power sources.
		$powered = false;
		foreach(Facing::ALL as $face){
			$source = $this->getSide($face);
			if($source instanceof Redstone || ($source instanceof Lever && $source->isActivated()) || ($source instanceof Button && $source->isPressed())){
				$powered = true;
				break;
			}
		}
		if($powered !== $this->powered){
			$this->powered = $powered;
			$world = $this->position->getWorld();
			$world->setBlock($this->position, $this);
			if($powered){ $world->scheduleDelayedBlockUpdate($this->position, 4); }
		}
	}
	public function onScheduledUpdate() : void{
		$success = $this->dispense();
		$this->position->getWorld()->addSound($this->position->add(0.5, 0.5, 0.5), new \pocketmine\world\sound\ClickSound($success ? 1.0 : 1.2));
	}

	/** Emits one item, or transfers it to the container in front. A full container never spills items. */
	public function dispense() : bool{
		$world = $this->position->getWorld();
		$tile = $world->getTile($this->position);
		if(!$tile instanceof TileDropper){ return false; }
		$inventory = $tile->getInventory();
		$slot = $inventory->selectDispenseSlot();
		if($slot === null){ return false; }
		$stack = $inventory->getItem($slot);
		$item = (clone $stack)->setCount(1);
		$front = $this->position->getSide($this->facing);
		// Do not load another chunk as a side effect of dispensing.
		if(!$world->isChunkLoaded($front->getFloorX() >> 4, $front->getFloorZ() >> 4)){ return false; }
		$target = $world->getTile($front);
		if($target instanceof Container){
			// Workstations need sided slot rules; do not insert into their output or recipe slots.
			if(!$target instanceof \pocketmine\block\tile\Chest && !$target instanceof \pocketmine\block\tile\Barrel &&
				!$target instanceof \pocketmine\block\tile\Hopper && !$target instanceof TileDropper &&
				!$target instanceof \pocketmine\block\tile\ShulkerBox){
				return false;
			}
			$destination = $target->getInventory();
			if(!$destination->canAddItem($item) || $destination->addItem($item) !== []){ return false; }
		}else{
			$direction = (new Vector3(0, 0, 0))->getSide($this->facing);
			$spawn = $this->position->add(0.5, 0.5, 0.5)->addVector($direction->multiply(0.7));
			if($world->dropItem($spawn, $item, $direction->multiply(0.2)->add(0, 0.2, 0)) === null){ return false; }
		}
		$stack->pop();
		$inventory->setItem($slot, $stack);
		return true;
	}
}
