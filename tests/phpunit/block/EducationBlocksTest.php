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
use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pocketmine\inventory\CreativeInventory;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use function preg_match;
use function str_starts_with;

final class EducationBlocksTest extends TestCase{
	public function testElementsAndHardenedGlassSurviveWireAndDropConversion() : void{
		$converter = TypeConverter::getInstance();
		$names = [];
		foreach($converter->getBlockTranslator()->getBlockStateDictionary()->getStates() as $entry){
			$name = $entry->getStateName();
			if(preg_match('/^minecraft:element_[0-9]+$/D', $name) !== 1 && !str_starts_with($name, 'minecraft:hard_')){ continue; }
			$block = GlobalBlockStateHandlers::getDeserializer()->deserializeBlock($entry->generateStateData());
			self::assertNotInstanceOf(PreservedBlock::class, $block);
			self::assertTrue($block->canBePlaced());
			$item = $block->asItem();
			self::assertTrue(CreativeInventory::getInstance()->contains($item), $name);
			$network = $converter->coreItemStackToNet($item);
			$writer = new ByteBufferWriter();
			CommonTypes::putItemStackWrapper($writer, new ItemStackWrapper(1, $network));
			$decoded = CommonTypes::getItemStackWrapper(new ByteBufferReader($writer->getData()))->getItemStack();
			self::assertTrue($network->equals($decoded), $name);
			self::assertTrue($item->equals($converter->netItemStackToCore($decoded)), $name);
			$drops = $block->getDrops(VanillaItems::AIR());
			self::assertCount(1, $drops, $name);
			self::assertTrue($item->equals($drops[0]), $name);
			$names[$name] = true;
		}
		self::assertCount(153, $names); //119 elements, 17 glass blocks and 17 panes.
		self::assertSame(VanillaBlocks::ELEMENT_BERYLLIUM()->getTypeId(), StringToItemParser::getInstance()->parse('element_4')?->getBlock()->getTypeId());
	}
}
