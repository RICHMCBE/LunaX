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

use PHPUnit\Framework\TestCase;
use pocketmine\block\tile\Dropper as TileDropper;
use pocketmine\entity\object\ItemEntity;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\World;

final class DropperTest extends TestCase{
	public function testAllStatesAndInventoryWireRoundTrip() : void{
		$count = 0;
		$converter = TypeConverter::getInstance();
		foreach($converter->getBlockTranslator()->getBlockStateDictionary()->getStates() as $entry){
			if($entry->getStateName() !== 'minecraft:dropper'){ continue; }
			$state = $entry->generateStateData();
			$block = GlobalBlockStateHandlers::getDeserializer()->deserializeBlock($state);
			self::assertInstanceOf(Dropper::class, $block);
			self::assertTrue($state->toNbt()->equals(GlobalBlockStateHandlers::getSerializer()->serializeBlock($block)->toNbt()));
			$item = $block->asItem();
			self::assertTrue($item->equals($converter->netItemStackToCore($converter->coreItemStackToNet($item))));
			++$count;
		}
		self::assertSame(12, $count);
	}
	public function testTransfersExactlyOneAndFullTargetDoesNotSpill() : void{
		$world = $this->createMock(World::class);
		$world->method('isLoaded')->willReturn(true);
		$world->method('isChunkLoaded')->willReturn(true);
		$source = new TileDropper($world, new Vector3(0, 64, 0));
		$target = new TileDropper($world, new Vector3(1, 64, 0));
		$source->closed = $target->closed = true;
		$world->method('getTile')->willReturnCallback(fn(Vector3 $pos) => $pos->getFloorX() === 0 ? $source : $target);
		$world->expects(self::never())->method('dropItem');
		$block = VanillaBlocks::DROPPER()->setFacing(Facing::EAST);
		$block->position($world, 0, 64, 0);
		$source->getInventory()->setItem(8, VanillaItems::DIAMOND()->setCount(2)->setCustomName('retained'));
		self::assertTrue($block->dispense());
		self::assertSame(1, $source->getInventory()->getItem(8)->getCount());
		self::assertSame('retained', $target->getInventory()->getItem(0)->getCustomName());
		self::assertSame(1, $target->getInventory()->getItem(0)->getCount());
		for($i = 0; $i < 9; ++$i){ $target->getInventory()->setItem($i, VanillaItems::DIAMOND()->setCount(64)); }
		self::assertFalse($block->dispense());
		self::assertSame(1, $source->getInventory()->getItem(8)->getCount());
	}
	public function testEjectionAndEmptyInventory() : void{
		$world = $this->createMock(World::class);
		$world->method('isLoaded')->willReturn(true);
		$world->method('isChunkLoaded')->willReturn(true);
		$source = new TileDropper($world, new Vector3(0, 64, 0));
		$source->closed = true;
		$world->method('getTile')->willReturnCallback(fn(Vector3 $pos) => $pos->getFloorX() === 0 ? $source : null);
		$entity = $this->createMock(ItemEntity::class);
		(new \ReflectionProperty(\pocketmine\entity\Entity::class, "closed"))->setValue($entity, true);
		$world->expects(self::once())->method('dropItem')->with(
			self::callback(fn(Vector3 $pos) => $pos->x > 1.0),
			self::callback(fn(\pocketmine\item\Item $item) => $item->getCount() === 1 && $item->getTypeId() === VanillaItems::DIAMOND()->getTypeId()),
			self::isInstanceOf(Vector3::class)
		)->willReturn($entity);
		$block = VanillaBlocks::DROPPER()->setFacing(Facing::EAST);
		$block->position($world, 0, 64, 0);
		self::assertFalse($block->dispense());
		$source->getInventory()->setItem(4, VanillaItems::DIAMOND());
		self::assertTrue($block->dispense());
		self::assertTrue($source->getInventory()->isSlotEmpty(4));
	}
	public function testRisingEdgeSchedulesOnlyOnceUntilPowerRemoved() : void{
		$world = $this->createMock(World::class);
		$world->method('isLoaded')->willReturn(true);
		$power = new class{ public bool $active = true; };
		$world->method('getBlockAt')->willReturnCallback(function() use ($power){ return $power->active ? VanillaBlocks::LEVER()->setActivated(true) : VanillaBlocks::AIR(); });
		$world->expects(self::exactly(2))->method('scheduleDelayedBlockUpdate')->with(self::isInstanceOf(Vector3::class), 4);
		$block = VanillaBlocks::DROPPER();
		$block->position($world, 0, 64, 0);
		$block->onNearbyBlockChange();
		$block->onNearbyBlockChange();
		self::assertTrue($block->isPowered());
		$power->active = false;
		$block->onNearbyBlockChange();
		self::assertFalse($block->isPowered());
		$power->active = true;
		$block->onNearbyBlockChange();
	}
	public function testContainerSaveReloadKeepsSlotsNameAndLock() : void{
		$world = $this->createMock(World::class);
		$world->method('isLoaded')->willReturn(true);
		$source = new TileDropper($world, new Vector3(0, 64, 0));
		$target = new TileDropper($world, new Vector3(0, 64, 0));
		$source->closed = $target->closed = true;
		$source->readSaveData(CompoundTag::create()->setString('CustomName', 'stored')->setString('Lock', 'key')->setInt('FutureData', 42));
		$source->getInventory()->setItem(8, VanillaItems::DIAMOND()->setCount(17));
		$target->readSaveData($source->saveNBT());
		self::assertSame(9, $target->getInventory()->getSize());
		self::assertSame(17, $target->getInventory()->getItem(8)->getCount());
		self::assertSame('stored', $target->getName());
		self::assertSame(42, $target->saveNBT()->getInt('FutureData'));
		self::assertFalse($target->canOpenWith(''));
		self::assertTrue($target->canOpenWith('key'));
	}
}
