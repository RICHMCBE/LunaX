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

namespace pocketmine\data\runtime;

use PHPUnit\Framework\TestCase;

final class RuntimeDataTest extends TestCase{
	public function testSingleValueRangePreservesOneBitLayout() : void{
		$value = 7;
		$calculator = new RuntimeDataSizeCalculator();
		$calculator->boundedIntAuto(7, 7, $value);
		self::assertSame(1, $calculator->getBitsUsed());
		$writer = new RuntimeDataWriter(2);
		$writer->boundedIntAuto(7, 7, $value);
		$writer->writeInt(1, 1);
		self::assertSame(2, $writer->getValue());
		$reader = new RuntimeDataReader(2, $writer->getValue());
		$decoded = 0;
		$reader->boundedIntAuto(7, 7, $decoded);
		self::assertSame(7, $decoded);
		self::assertSame(1, $reader->readInt(1));
	}

	public function testSingleValueRangeRejectsInvalidBit() : void{
		$reader = new RuntimeDataReader(1, 1);
		$value = 0;
		$this->expectException(InvalidSerializedRuntimeDataException::class);
		$reader->boundedIntAuto(7, 7, $value);
	}
}
