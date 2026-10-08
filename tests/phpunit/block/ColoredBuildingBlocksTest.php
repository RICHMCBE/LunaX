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
use pocketmine\block\utils\Colored;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\utils\SlabType;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use function preg_match;

final class ColoredBuildingBlocksTest extends TestCase{
	public function testEveryPaletteStateLoads() : void{
		$dictionary = TypeConverter::getInstance()->getBlockTranslator()->getBlockStateDictionary();
		$decoder = GlobalBlockStateHandlers::getDeserializer();
		$encoder = GlobalBlockStateHandlers::getSerializer();
		$names = [];
		foreach($dictionary->getStates() as $entry){
			if(preg_match('/_(?:concrete|wool)_(?:double_slab|slab|stairs)$/D', $entry->getStateName()) !== 1){
				continue;
			}
			$state = $entry->generateStateData();
			$id = $decoder->deserialize($state);
			$roundTrip = $encoder->serialize($id);
			self::assertSame($state->getName(), $roundTrip->getName());
			self::assertSame($id, $decoder->deserialize($roundTrip));
			self::assertNotNull($dictionary->lookupStateIdFromData($roundTrip));
			$names[$state->getName()] = true;
		}
		self::assertCount(96, $names);
	}

	public function testSlabsKeepColourWhenMergedAndDropped() : void{
		foreach([VanillaBlocks::WOOL_SLAB(), VanillaBlocks::CONCRETE_SLAB()] as $prototype){
			foreach(DyeColor::cases() as $color){
				$slab = (clone $prototype)->setColor($color);
				$other = (clone $prototype)->setColor($color === DyeColor::WHITE ? DyeColor::BLACK : DyeColor::WHITE);
				self::assertTrue($slab->canBePlacedAt(clone $slab, new Vector3(0.5, 0.75, 0.5), Facing::UP, true));
				self::assertFalse($slab->canBePlacedAt($other, new Vector3(0.5, 0.75, 0.5), Facing::UP, true));
				$slab->setSlabType(SlabType::DOUBLE);
				$drops = $slab->getDropsForCompatibleTool(VanillaItems::DIAMOND_PICKAXE());
				self::assertSame(2, $drops[0]->getCount());
				$droppedBlock = $drops[0]->getBlock();
				self::assertInstanceOf(Colored::class, $droppedBlock);
				self::assertSame($color, $droppedBlock->getColor());
			}
		}
	}

	public function testInventoryRoundTrip() : void{
		$converter = TypeConverter::getInstance();
		foreach(['red_wool_slab', 'blue_wool_stairs', 'green_concrete_slab', 'black_concrete_stairs'] as $name){
			$item = StringToItemParser::getInstance()->parse($name);
			self::assertNotNull($item);
			self::assertTrue($item->equals($converter->netItemStackToCore($converter->coreItemStackToNet($item))));
		}
	}
}
