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

// Offline, selective recovery: never overwrite non-placeholder blocks or unrelated DB records.
// Usage: php tools/restore-update-blocks.php SOURCE_WORLD TARGET_WORLD [--apply]
// Back up the stopped target world before using --apply. Default is a dry run.
require dirname(__DIR__) . '/vendor/autoload.php';

use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\utils\Binary;
use pocketmine\utils\Utils;
use pocketmine\world\format\PalettedBlockArray;

/**
 * @return array{string, list<array{PalettedBlockArray, list<CompoundTag>}>}
 */
function decodeRecoverySubChunk(string $raw) : array{
	$version = ord($raw[0]);
	if($version !== 8 && $version !== 9){
		throw new RuntimeException("Unsupported subchunk version $version");
	}
	$offset = $version === 9 ? 3 : 2;
	$header = substr($raw, 0, $offset);
	$layers = [];
	$nbt = new LittleEndianNbtSerializer();
	for($layer = 0; $layer < ord($raw[1]); ++$layer){
		$encoding = ord($raw[$offset++]);
		if(($encoding & 1) !== 0){ throw new RuntimeException('Expected persistent palette'); }
		$bits = $encoding >> 1;
		$size = PalettedBlockArray::getExpectedWordArraySize($bits);
		$words = substr($raw, $offset, $size);
		$offset += $size;
		$count = 1;
		if($bits !== 0){
			$count = Binary::readLInt(substr($raw, $offset, 4));
			$offset += 4;
		}
		if($count < 1 || $count > 4096){
			throw new RuntimeException('Invalid palette size');
		}
		$tags = [];
		for($i = 0; $i < $count; ++$i){ $tags[] = $nbt->read($raw, $offset)->mustGetCompoundTag(); }
		$layers[] = [PalettedBlockArray::fromData($bits, $words, range(0, $count - 1)), $tags];
	}
	if($offset !== strlen($raw)){ throw new RuntimeException('Unexpected trailing subchunk data'); }
	return [$header, $layers];
}

/**
 * @param list<array{PalettedBlockArray, list<CompoundTag>}> $layers
 */
function encodeRecoverySubChunk(string $header, array $layers) : string{
	$result = $header;
	$nbt = new LittleEndianNbtSerializer();
	foreach($layers as [$blocks, $tags]){
		$result .= chr(($blocks->getBitsPerBlock() << 1) & 0xff) . $blocks->getWordArray();
		$palette = $blocks->getPalette();
		if($blocks->getBitsPerBlock() !== 0){ $result .= pack('V', count($palette)); }
		foreach($palette as $index){ $result .= $nbt->write(new TreeRoot($tags[$index])); }
	}
	return $result;
}

if(!isset($argv) || count($argv) < 3){ throw new InvalidArgumentException('SOURCE_WORLD TARGET_WORLD [--apply] required'); }
$sourcePath = realpath($argv[1]);
$targetPath = realpath($argv[2]);
if($sourcePath === false || $targetPath === false || strcasecmp($sourcePath, $targetPath) === 0){
	throw new InvalidArgumentException('Two different existing worlds are required');
}
$source = new LevelDB($sourcePath . '/db', ['compression' => LEVELDB_ZLIB_RAW_COMPRESSION]);
$target = new LevelDB($targetPath . '/db', ['compression' => LEVELDB_ZLIB_RAW_COMPRESSION]);
$changes = [];
$counts = [];
$unresolved = 0;
foreach($target->getIterator() as $key => $raw){
	$len = strlen($key);
	if(($len !== 10 && $len !== 14) || $key[$len - 2] !== "\x2f"){ continue; }
	[$header, $layers] = decodeRecoverySubChunk($raw);
	$original = $source->get($key);
	$sourceLayers = $original === false ? [] : decodeRecoverySubChunk($original)[1];
	$changed = false;
	foreach($layers as $layer => [$blocks, $tags]){
		$append = [];
		for($x = 0; $x < 16; ++$x){ for($z = 0; $z < 16; ++$z){ for($y = 0; $y < 16; ++$y){
			if($tags[$blocks->get($x, $y, $z)]->getString('name') !== 'minecraft:info_update'){ continue; }
			$sourceLayer = $sourceLayers[$layer] ?? null;
			if($sourceLayer === null){
				++$unresolved;
				continue;
			}
			$old = $sourceLayer[1][$sourceLayer[0]->get($x, $y, $z)];
			$name = $old->getString('name');
			if($name !== 'minecraft:leaf_litter' && $name !== 'minecraft:bubble_column'){
				++$unresolved;
				continue;
			}
			$sourceIndex = $sourceLayer[0]->get($x, $y, $z);
			if(!isset($append[$sourceIndex])){
				$append[$sourceIndex] = count($tags);
				$tags[] = $old;
			}
			$blocks->set($x, $y, $z, $append[$sourceIndex]);
			$counts[$name] = ($counts[$name] ?? 0) + 1;
			$changed = true;
		}}}
		$layers[$layer] = [$blocks, $tags];
	}
	if($changed){
		$encoded = encodeRecoverySubChunk($header, $layers);
		$verified = decodeRecoverySubChunk($encoded)[1];
		foreach($layers as $layer => [$blocks, $tags]){
			[$checkBlocks, $checkTags] = $verified[$layer];
			for($x = 0; $x < 16; ++$x){ for($z = 0; $z < 16; ++$z){ for($y = 0; $y < 16; ++$y){
				if(!$tags[$blocks->get($x, $y, $z)]->equals($checkTags[$checkBlocks->get($x, $y, $z)])){
					throw new RuntimeException('Recovery serialization validation failed');
				}
			}}}
		}
		$changes[$key] = $encoded;
	}
}
// Finish decoding all records before mutating the database, so malformed input aborts safely.
$apply = ($argv[3] ?? '') === '--apply';
if($apply){ foreach(Utils::stringifyKeys($changes) as $key => $raw){ $target->put($key, $raw); } }
echo json_encode(['applied' => $apply, 'subchunks' => count($changes), 'restored' => $counts, 'unresolved' => $unresolved], JSON_PRETTY_PRINT), PHP_EOL;
