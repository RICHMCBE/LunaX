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
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\world\format\io\GlobalBlockStateHandlers;

final class ImportedWorldBlocksTest extends TestCase{
	public function testImportedStatesRoundTrip() : void{
		$decoder = GlobalBlockStateHandlers::getDeserializer();
		$encoder = GlobalBlockStateHandlers::getSerializer();
		$states = [];
		foreach([0, 1] as $drag){
			$states[] = BlockStateData::current('minecraft:bubble_column', ['drag_down' => new ByteTag($drag)]);
		}
		foreach(['north', 'south', 'east', 'west'] as $direction){
			for($growth = 0; $growth < 4; ++$growth){
				$states[] = BlockStateData::current('minecraft:leaf_litter', [
					'growth' => new IntTag($growth),
					'minecraft:cardinal_direction' => new StringTag($direction)
				]);
			}
		}
		foreach($states as $state){
			$decoded = $decoder->deserialize($state);
			self::assertTrue($state->toNbt()->equals($encoder->serialize($decoded)->toNbt()));
			self::assertNotSame(VanillaBlocks::INFO_UPDATE()->getStateId(), $decoded);
		}
	}

	public function testBubbleSupportAndWaterForms() : void{
		self::assertFalse(BubbleColumn::getDragFromSupport(VanillaBlocks::SOUL_SAND()));
		self::assertTrue(BubbleColumn::getDragFromSupport(VanillaBlocks::MAGMA()));
		self::assertNull(BubbleColumn::getDragFromSupport(VanillaBlocks::STONE()));
		$column = VanillaBlocks::BUBBLE_COLUMN()->setDraggingDown(true);
		self::assertTrue(BubbleColumn::getDragFromSupport($column));
		self::assertSame(BlockTypeIds::WATER, $column->getFlowingForm()->getTypeId());
		self::assertSame(BlockTypeIds::WATER, $column->getStillForm()->getTypeId());
		self::assertSame([], $column->getCollisionBoxes());
	}
	public function testLeafLitterDrops() : void{
		$leaves = VanillaBlocks::LEAF_LITTER()->setCount(4);
		$drops = $leaves->getDropsForCompatibleTool(\pocketmine\item\VanillaItems::AIR());
		self::assertSame(4, $drops[0]->getCount());
		self::assertSame([], $leaves->getCollisionBoxes());
	}
}
