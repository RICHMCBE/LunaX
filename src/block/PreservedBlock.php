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

use pocketmine\block\tile\PreservedTile;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\player\Player;
use function count;

/**
 * Lossless display-only block. Gameplay is deliberately disabled until implemented.
 */
final class PreservedBlock extends Opaque{
	private int $stateIndex = 0;

	/** @param non-empty-list<BlockStateData> $states */
	public function __construct(int $typeId, private array $states){
		parent::__construct(new BlockIdentifier($typeId, PreservedTile::class), $states[0]->getName() . " (preserved)", new BlockTypeInfo(BlockBreakInfo::indestructible()));
	}

	public function describeBlockItemState(RuntimeDataDescriber $w) : void{
		$w->boundedIntAuto(0, count($this->states) - 1, $this->stateIndex);
	}

	public function withStateIndex(int $index) : self{
		if(!isset($this->states[$index])){ throw new \InvalidArgumentException("Invalid preserved state index"); }
		$result = clone $this;
		$result->stateIndex = $index;
		return $result;
	}

	public function getPreservedState() : BlockStateData{ return $this->states[$this->stateIndex]; }
	public function canBePlaced() : bool{ return false; }
	public function getDrops(Item $item) : array{ return []; }
	public function onBreak(Item $item, ?Player $player = null, array &$returnedItems = []) : bool{ return false; }
}
