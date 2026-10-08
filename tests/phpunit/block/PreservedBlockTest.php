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
use pocketmine\block\tile\PreservedTile;
use pocketmine\block\tile\TileFactory;
use pocketmine\data\bedrock\BedrockDataFiles;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\data\bedrock\block\BlockStateDeserializeException;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\network\mcpe\convert\BlockStateDictionary;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\utils\Filesystem;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use pocketmine\world\World;

final class PreservedBlockTest extends TestCase{
	public function testLevelDbPalettePreservesUnsupportedCanonicalStates() : void{
		$provider = new class extends \pocketmine\world\format\io\leveldb\LevelDB{
			// Only exercise palette decoding here; opening a database is covered by the disk round-trip smoke test.
			public function __construct(){ // @phpstan-ignore constructor.missingParentCall
				$this->blockStateDeserializer = GlobalBlockStateHandlers::getDeserializer();
				$this->blockDataUpgrader = GlobalBlockStateHandlers::getUpgrader();
			}
			public function decode(\pocketmine\utils\BinaryStream $stream, \Logger $logger) : int{
				return $this->deserializeBlockPalette($stream, $logger)->get(0, 0, 0);
			}
		};
		$logger = $this->createMock(\Logger::class);
		$logger->expects(self::never())->method('error');
		$nbt = new \pocketmine\nbt\LittleEndianNbtSerializer();
		foreach(RuntimeBlockStateRegistry::getInstance()->getAllKnownStates() as $block){
			if(!$block instanceof PreservedBlock){ continue; }
			$state = $block->getPreservedState();
			$stream = new \pocketmine\utils\BinaryStream("\x00" . $nbt->write(new \pocketmine\nbt\TreeRoot($state->toNbt())));
			$decoded = GlobalBlockStateHandlers::getSerializer()->serialize($provider->decode($stream, $logger));
			self::assertTrue($state->toNbt()->equals($decoded->toNbt()), $state->getName());
		}
	}

	public function testTileFactoryPreservesContainerDataOnUnsupportedBlock() : void{
		$block = GlobalBlockStateHandlers::getDeserializer()->deserializeBlock(BlockStateData::current('minecraft:beehive', [
			'direction' => new IntTag(0), 'honey_level' => new IntTag(0)
		]));
		self::assertInstanceOf(PreservedBlock::class, $block);
		$world = $this->createMock(World::class);
		$world->method('isLoaded')->willReturn(true);
		$world->method('getBlock')->willReturn($block);
		$data = CompoundTag::create()->setString('id', 'Beehive')->setInt('x', 0)->setInt('y', 64)->setInt('z', 0)
			->setTag('FuturePayload', CompoundTag::create()->setString('value', 'keep me'));
		$tile = TileFactory::getInstance()->createFromData($world, $data);
		self::assertInstanceOf(PreservedTile::class, $tile);
		$saved = $tile->saveNBT();
		self::assertSame('Beehive', $saved->getString('id'));
		self::assertSame('keep me', $saved->getCompoundTag('FuturePayload')?->getString('value'));
		$tile->closed = true;
	}
	public function testEntirePaletteLoadsAndMissingStatesAreLossless() : void{
		$decoder = GlobalBlockStateHandlers::getDeserializer();
		$encoder = GlobalBlockStateHandlers::getSerializer();
		$translator = TypeConverter::getInstance()->getBlockTranslator();
		$preserved = 0;
		foreach(BlockStateDictionary::loadPaletteFromString(Filesystem::fileGetContents(BedrockDataFiles::CANONICAL_BLOCK_STATES_NBT)) as $state){
			$id = $decoder->deserialize($state);
			$block = RuntimeBlockStateRegistry::getInstance()->fromStateId($id);
			self::assertNotNull($translator->getBlockStateDictionary()->generateDataFromStateId($translator->internalIdToNetworkId($id)));
			if($block instanceof PreservedBlock){
				++$preserved;
				self::assertTrue($state->toNbt()->equals($encoder->serialize($id)->toNbt()), $state->getName());
				self::assertFalse($block->canBePlaced());
				self::assertFalse($block->onBreak(VanillaItems::AIR()));
				self::assertSame([], $block->getDrops(VanillaItems::AIR()));
			}
		}
		self::assertGreaterThan(0, $preserved);
	}

	public function testMalformedKnownStateStillRejected() : void{
		$this->expectException(BlockStateDeserializeException::class);
		GlobalBlockStateHandlers::getDeserializer()->deserialize(BlockStateData::current('minecraft:observer', ['facing_direction' => new IntTag(900)]));
	}

	public function testPreservedTileDoesNotDiscardUnknownTags() : void{
		//The NBT container is independent of world access; this avoids creating a live server in this test.
		$tile = (new \ReflectionClass(PreservedTile::class))->newInstanceWithoutConstructor();
		$tile->closed = true;
		$original = CompoundTag::create()->setString('id', 'CommandBlock')->setString('Command', 'say preserved')
			->setTag('FutureData', CompoundTag::create()->setInt('value', 123));
		$tile->readSaveData($original);
		$original->setString('Command', 'mutated');
		$saved = $tile->getCleanedNBT();
		self::assertNotNull($saved);
		self::assertSame('say preserved', $saved->getString('Command'));
		self::assertSame(123, $saved->getCompoundTag('FutureData')?->getInt('value'));
		$spawn = CompoundTag::create();
		(new \ReflectionMethod(PreservedTile::class, 'addAdditionalSpawnData'))->invoke($tile, $spawn);
		self::assertSame('CommandBlock', $spawn->getString('id'));
		self::assertNull($spawn->getTag('Command'));
		self::assertNull($spawn->getTag('FutureData'));
	}
}
