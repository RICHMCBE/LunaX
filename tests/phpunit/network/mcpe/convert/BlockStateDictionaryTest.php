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

namespace pocketmine\network\mcpe\convert;

use PHPUnit\Framework\TestCase;
use pocketmine\nbt\tag\IntTag;

final class BlockStateDictionaryTest extends TestCase{
	public function testStableIdsAndUnsignedLookup() : void{
		$air = new BlockStateDictionaryEntry("minecraft:air", [], 0);
		$stone = new BlockStateDictionaryEntry("minecraft:stone", [], 0);
		//FNV-1a of LE NBT {name: "minecraft:air", states: {}} (no version tag).
		self::assertSame(-604749536, $air->getNetworkId());
		self::assertSame(-2144268767, $stone->getNetworkId());
		foreach([[$air, $stone], [$stone, $air]] as $entries){
			$dictionary = new BlockStateDictionary($entries);
			self::assertSame(-604749536, $dictionary->lookupStateIdFromData($air->generateStateData()));
			self::assertSame(-2144268767, $dictionary->lookupStateIdFromIdMeta("minecraft:stone", 0));
			self::assertSame("minecraft:air", $dictionary->generateDataFromStateId(3690217760)?->getName());
			self::assertSame(0, $dictionary->getMetaFromStateId(3690217760));
		}
	}

	public function testPropertyOrderingAndUnknown() : void{
		$a = new BlockStateDictionaryEntry("test:block", ["z" => new IntTag(1), "a" => new IntTag(2)], 0);
		$b = new BlockStateDictionaryEntry("test:block", ["a" => new IntTag(2), "z" => new IntTag(1)], 3);
		self::assertSame($a->getNetworkId(), $b->getNetworkId());
		self::assertSame(-2, (new BlockStateDictionaryEntry("minecraft:unknown", [], 0))->getNetworkId());
	}

	public function testDuplicateHashRejected() : void{
		$air = new BlockStateDictionaryEntry("minecraft:air", [], 0);
		$this->expectException(\InvalidArgumentException::class);
		new BlockStateDictionary([$air, $air]);
	}
}
