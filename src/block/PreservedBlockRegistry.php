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

use pocketmine\data\bedrock\BedrockDataFiles;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\data\bedrock\block\BlockStateDeserializeException;
use pocketmine\network\mcpe\convert\BlockStateDictionary;
use pocketmine\network\mcpe\convert\BlockStateDictionaryEntry;
use pocketmine\utils\Filesystem;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\Utils;
use pocketmine\world\format\io\GlobalBlockStateHandlers;
use function ksort;
use const SORT_STRING;

/**
 * Deterministic across the main thread and chunk worker threads using the same palette.
 */
final class PreservedBlockRegistry{
	use SingletonTrait;

	/** @var list<PreservedBlock> */
	private array $blocks = [];
	/** @var array<string, array<string, PreservedBlock>> */
	private array $lookup = [];

	public function __construct(){
		$decoder = GlobalBlockStateHandlers::getDeserializer();
		$missing = [];
		$allNames = [];
		foreach(BlockStateDictionary::loadPaletteFromString(Filesystem::fileGetContents(BedrockDataFiles::CANONICAL_BLOCK_STATES_NBT)) as $state){
			$allNames[$state->getName()] = true;
			try{
				$decoder->deserializeBlock($state, false);
			}catch(BlockStateDeserializeException){
				$missing[$state->getName()][] = $state;
			}
		}
		ksort($missing, SORT_STRING);
		ksort($allNames, SORT_STRING);
		$typeId = BlockTypeIds::PRESERVED_BLOCK_ID_START - 1;
		foreach(Utils::stringifyKeys($allNames) as $name => $_){
			++$typeId;
			if($typeId >= BlockTypeIds::PRESERVED_BLOCK_ID_END){ throw new \LogicException("Preserved block ID range exhausted"); }
			$states = $missing[$name] ?? null;
			if($states === null){ continue; }
			$block = new PreservedBlock($typeId, $states);
			$this->blocks[] = $block;
			foreach($states as $index => $state){
				$this->lookup[$state->getName()][BlockStateDictionaryEntry::encodeStateProperties($state->getStates())] = $block->withStateIndex($index);
			}
		}
	}

	/** @return list<PreservedBlock> */
	public function getBlocks() : array{ return $this->blocks; }

	public function lookup(BlockStateData $state) : ?PreservedBlock{
		$block = $this->lookup[$state->getName()][BlockStateDictionaryEntry::encodeStateProperties($state->getStates())] ?? null;
		return $block === null ? null : clone $block;
	}
}
