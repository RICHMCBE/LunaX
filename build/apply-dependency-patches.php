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

// Keep clean Composer installs consistent with the tested preview codecs.
$root = dirname(__DIR__);
foreach(["bedrock-protocol", "bedrock-data"] as $package){
	$patch = __DIR__ . "/dependency-patches/$package.patch";
	$directory = "vendor/teamselenyx/$package";
	$run = static function(string ...$options) use ($root, $directory, $patch) : int{
		$command = array_merge(["git", "-C", $root, "apply", "--whitespace=nowarn", "--directory=$directory"], $options, [$patch]);
		$process = proc_open(array_values($command), [0 => ["pipe", "r"], 1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes);
		if(!is_resource($process)){
			throw new RuntimeException("Unable to start git to apply dependency patches");
		}
		fclose($pipes[0]);
		$output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$result = proc_close($process);
		if($result !== 0 && !in_array("--check", $options, true)){
			fwrite(STDERR, $output);
		}
		return $result;
	};
	if($run("--reverse", "--check") === 0){
		echo "$package preview patch already applied\n";
		continue;
	}
	if($run("--check") !== 0 || $run() !== 0){
		fwrite(STDERR, "Cannot apply $package preview patch; install the versions pinned in composer.lock.\n");
		exit(1);
	}
	echo "Applied $package preview patch\n";
}
