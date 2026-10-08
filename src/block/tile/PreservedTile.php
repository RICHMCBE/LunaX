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

namespace pocketmine\block\tile;

use pocketmine\nbt\tag\CompoundTag;
use pocketmine\utils\Utils;

/**
 * Keeps unknown block-entity data without running commands or inventory logic.
 */
final class PreservedTile extends Spawnable{
	private ?CompoundTag $original = null;

	public function readSaveData(CompoundTag $nbt) : void{
		$this->original = clone $nbt;
		$this->clearSpawnCompoundCache();
	}

	protected function writeSaveData(CompoundTag $nbt) : void{
		if($this->original !== null){
			foreach(Utils::stringifyKeys($this->original->getValue()) as $name => $tag){
				$nbt->setTag($name, clone $tag);
			}
		}
	}

	protected function addAdditionalSpawnData(CompoundTag $nbt) : void{
		//Never broadcast stored inventories, command strings or other server-only data.
		if(($id = $this->original?->getTag(self::TAG_ID)) !== null){
			$nbt->setTag(self::TAG_ID, clone $id);
		}
	}
}
