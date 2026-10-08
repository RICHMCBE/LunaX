# Bedrock 1.26.60 preview update preparation

## Latest checkpoint: 2026-10-08, preview 1.26.60.30

Publication branch: `bedrock-1.26.60.30`. This is a development checkpoint,
not a supported release. The sibling BedrockProtocol/BedrockData changes are
local dependencies and are not included in this LunaX branch; the published
Composer pins remain unchanged. See the local installation instructions below.

### Native dropper

Replaced the preserved dropper with a native block (12 facing/trigger states),
9-slot container, Dropper window type, placement, normal pickaxe drops and saved
inventory/name/lock. Unknown extra tile tags are retained during migration.
An activation selects a nonempty slot uniformly and moves one item into an
adjacent chest, barrel, hopper, shulker box or dropper, or ejects an item entity
when no container is present. Full containers retain the source item; unloaded
destination chunks are not loaded to dispense. Workstation containers currently
reject transfers until their sided slot rules are implemented.

Direct adjacent levers, buttons and redstone blocks trigger one action after
four game ticks on a rising edge. General wire propagation, indirect power,
comparators and full redstone parity remain unimplemented in this engine.
The native dropper does not implement dispenser projectile/bucket behavior.
Tests cover all 12 states, item round-trip, one-item transfer, full-container
retention, empty/ejection behavior, rising edges and container save/reload.
The full suite passed 210 tests; the final targeted suite passed 5 tests / 57
assertions. The remaining 1,464 preserved states still pass the LevelDB
save/close/reopen check. Client GUI and live activation playtests are pending.
PHPStan passed. Backed up the world to
`../deps-1.26.60/dropper-backup-20261008-094841` and restarted the source server.

### Education blocks: interaction and missing icons

Hardened glass and elements already had native block implementations and were
present in the creative catalog. Verified all 119 element blocks and 34 hardened
glass/pane variants through creative lookup, item conversion and drops.
Fixed the protocol item-wrapper reader to normalize unsigned 32-bit block hashes
to the signed representation used by other item packets and server comparisons.
Before the fix hardened glass changed from -774892487 to 3520074809 during a wire
round-trip, so equality failed; it now stays equal. This affects negative hash
IDs generally. Beryllium has a positive hash, so that bug alone does not explain
the user's reported cancellation for beryllium.

StartGame now enables Education features to match the educational blocks already
advertised in the creative catalog. Previously the flag was false; the user
reported blank icons with visible names, canceled placement and returning broken
blocks. Client confirmation of the flag's effect is still required. This is not
an implementation of chemistry workstations or all remaining preserved blocks.
LunaX PHPUnit passed 205 tests / 154,751 assertions and PHPStan passed; protocol
PHPUnit passed 491 tests / 902 assertions.

### World preservation first: remaining blocks

Added native poplar building blocks (17 network names), including logs, planks,
slabs, stairs, doors, fences and signs through the existing wood implementations.
For the remaining 112 names / 1,476 states in the installed canonical palette,
added inert preservation blocks: exact block states survive loading, network
translation and saving instead of becoming UPDATE blocks. Block-entity NBT is
retained without executing commands or container logic. Current-format canonical
preserved states bypass legacy LevelDB normalization, which otherwise rewrote
reserved mushroom face states.

The installed palette audit covers 1,477 names / 23,930 states with zero decode
failures. PHPUnit passed 204 tests / 151,893 assertions. A separate LevelDB
save, close and reopen test retained all 1,476 preserved states exactly; the
permanent regression test also exercises the disk palette decoder.
PHPStan passed. Backed up the live world to
`../deps-1.26.60/preservation-backup-20261008-093542` and restarted the source
server successfully; the existing client bridge remains on `127.0.0.1:29173`.

This implements the requested preservation/display priority, not full gameplay.
Preserved blocks cannot be placed or broken, use conservative full-cube collision
and opacity, and do not implement pistons, commands, bees or other special logic.
Only block-entity identity is sent to clients; special block-entity visuals may
still need dedicated support. Already-lost UPDATE states cannot be reconstructed
without original world data. Full target-client playtesting and extraction of a
fresh official .30 palette remain outstanding; coverage refers to the installed
supporting data, not an independently verified complete .30 catalog.

### Additional block implementation: coloured building blocks

Added 16 colours of wool and concrete slabs, double slabs and stairs (96 network
block names; four internal block types). Reuses slab merging, drops, collision
shapes and stair corner handling. Colour is part of item state and survives
inventory conversion; slabs with different colours cannot merge. Wool keeps its
shears speed and fire properties. Preview data gives these variants lower blast
resistance than full blocks (wool 0.8, concrete 1.8 in the server scale).
The property comparison test now rounds after converting blast resistance units,
avoiding a floating-point equality failure for 0.36 * 5 versus 1.8.

The full suite passed 199 tests; the final targeted colour tests passed 3 tests /
4,393 assertions, and PHPStan passed. All 96 names are checked for decoding,
stable reserialization and valid network IDs. Decoder gaps decreased from 225
to 129 names. The source server was restarted with these changes; client placement,
crafting and stair corner playtests for these new blocks remain pending.
This is the first additional implementation batch, not completion of all blocks.

### Imported world recovery

Implemented `LeafLitter` (count/direction, stacking, drops, support and fire
properties) and `BubbleColumn` (drag direction, source-water conversion,
support propagation/removal, vertical current and air replenishment). Registered
their block IDs, state mappings and generated interfaces. Leaf litter accepts
the palette's reserved growth values 4..7 by clamping them to four leaves, like
the existing pink petals decoder; valid growth 0..3 round-trips exactly.
Full vanilla parity of bubble movement, boats and surface behavior is not claimed.

Stopped the isolated server and backed up both world directories to
`../deps-1.26.60/recovery-backup-20261008-090738/` before recovery. Used
`tools/restore-update-blocks.php` to compare persistent palettes directly, without
running unrelated unsupported blocks through the lossy world decoder. Only
INFO_UPDATE cells with original leaf_litter/bubble_column data were eligible.
Recovered 138 leaf litter and 6 bubble columns in 5 subchunk records. A comparison
against the backup verified exactly 144 cell changes and no unrelated cell or
record changes; a second dry run found zero changes and zero unresolved cells.

PHPUnit passed 196 tests / 96,379 assertions and PHPStan passed. The updated palette decoder audit
accepts 32/32 leaf litter states and 2/2 bubble column states; 225 other block names
still have unsupported states. The user reconnected to 127.0.0.1:29173 and
confirmed that the UPDATE blocks disappeared. The new login produced no unknown
block errors at spawn. The source test server remains running. Other unsupported
block implementations and the wider preview release gates remain open.

### Block network ID correction and remaining unsupported blocks

Repeated client screenshots still showed incorrect terrain after reconnecting.
The dictionary used sequential indices from the inherited palette. It now uses
signed 32-bit FNV-1a hashes of little-endian NBT containing `name` and sorted
`states`, excluding `version`; `minecraft:unknown` uses -2. StartGame advertises
`blockNetworkIdsAreHashes=true`, as supported by the official preview.30 schema.
Algorithm reference: [Bedrock hash implementation by Alemiz112](https://gist.github.com/Alemiz112/504d0f79feac7ef57eda174b668dd345).
This removes dependence on the client's sequential palette ordering; the exact
old/new palette mismatch has not yet been established with a vanilla trace.

The same dictionary is used for chunks, block updates and inventory conversion.
Reverse lookup accepts unsigned 32-bit IDs received in inventory packets, rejects
hash collisions on loading, and retains recipe meta mappings. Block punch effects
use directional event IDs instead of overwriting the high bits of the hash.
PHPUnit passed 193 tests / 96,222 assertions and PHPStan passed. All 23,930 loaded
palette entries have distinct hashes. Missing `leaf_litter` and `bubble_column`
block implementations remain a separate limitation of imported vanilla worlds.

The source test server was restarted with this correction at 127.0.0.1:29173
(NetherNet endpoint; backend 29175, RakNet 29170). It remains running for client
retesting. Desktop control remains disconnected. The user reconnected and reported
that blocks still look wrong, then specifically reported some UPDATE blocks.
The hash change alone is therefore not a confirmed fix.

A full decoder audit of the installed inherited palette found 1,477 distinct
block names / 23,930 states; 227 names have at least one unsupported state.
`leaf_litter` has 0/32 supported states, `bubble_column` 0/2, `firefly_bush` 0/1,
`sulfur_spike` 0/10, `pale_oak_sapling` 0/2, and `pale_oak_shelf` 0/32.
Sulfur/cinnabar construction blocks and pale oak logs/planks do decode. This
audit tests decoding, not full mechanics or completeness against a fresh .30 dump.
Full results: `../deps-1.26.60/block-support-audit.json`.

The world loader substitutes `INFO_UPDATE` for unsupported block states. Direct
loading of chunks (0,-1) and (1,-1), at Y=48..95, yielded 63 and 75 INFO_UPDATE
blocks respectively. These are actual server-side replacement blocks, not only a
client texture issue. Mapping support and recovery from pristine world data still
need work; rendering and gameplay are not yet validated.

### Client playtest result (same day, after the automated checks)

The user launched Preview and connected to the isolated NetherNet endpoint on
127.0.0.1:29173. The capability request identified version 1.26.60.30 / protocol
2225. Authentication, login and `joined the game` completed successfully after
allowlisting the test account.

**World rendering failed.** The user's screenshot shows incorrect block textures
and geometry. Loading chunks around spawn in the copied BDS world produced
`Unknown block ID "minecraft:leaf_litter"` and
`Unknown block ID "minecraft:bubble_column"` errors (chunk 42 / subchunk 9).
These are confirmed missing block decoder mappings. They do not by themselves
explain the full rendering corruption; runtime block palette/hash mapping and
chunk serialization still need comparison with vanilla traffic and data.

Inventory, crafting and representative gameplay are not validated. The console
issued four oak logs, but no successful client inventory/crafting operation was
confirmed. The earlier server-only world-load check did not exercise these spawn
chunks and must not be interpreted as correct world compatibility.

At the user's request, desktop control was ended, the isolated LunaX server shut
down cleanly, and the NetherNet bridge was stopped. Evidence remains in
`../deps-1.26.60/lunax-smoke-30/server.log`. Its server timestamps use the host's
configured timezone; the test date in the user's Asia/Seoul timezone is 2026-10-08.

**Work in progress; not a supported release.** The older sections below describe
the September baseline. `origin` now points to `https://github.com/RICHMCBE/LunaX.git`;
the initial work used `bedrock-1.26.60`. This historical checkpoint preceded
publication on `bedrock-1.26.60.30`.

### Verified inputs

- [Mojang preview.30 metadata and developer notes](https://github.com/Mojang/bedrock-protocol-docs/releases/tag/v1.26.60-preview.30).
  `StartGamePacket.json` declares `x-protocol-version: 2225`, independently confirmed
  by `RequestNetworkSettingsPacketPayload.json` and the BDS-generated `level.dat`.
- [Official Windows BDS 1.26.60.30](https://www.minecraft.net/bedrockdedicatedserver/bin-win-preview/bedrock-server-1.26.60.30.zip).
  Downloaded under `../deps-1.26.60/` and extracted to `../BDS-1.26.60-preview.30/`.
  SHA-256: `3631505DE328C94080088DA1EC83D71E8A7DB3FC69204C463068E1911437CE3B`.
- Metadata ZIP SHA-256: `0E0C0F79333973791CDCDCEF6C4DBA5B62F677E088BE38D0140A7D424AC3FE5D`.
- Developer notes ZIP SHA-256: `886F4F41E8B8370F9F7B1CC8BCDCBB8079E1C5805DA4715B1EB82340D99F76A3`.

Compared with preview.25, the metadata contains 17 added schemas and 17 changed
schemas after excluding version annotations, descriptions and defaults. BDS
`definitions/` contents are identical; this does **not** prove runtime palettes or
recipes are identical. Behavior packs have 15 added and 5 changed files, including
new experimental Drop 4 archives; resource packs have 59 changed files.

### Implemented locally

The sibling `../TeamSelenyx-BedrockProtocol` repository contains the protocol changes:

- Generated protocol 2225 / version 1.26.60.30 metadata, packet pool and handlers.
- Optional passenger block state in AddActor/AddPlayer, editor migration version
  in LevelSettings, and optional matchmaking details plus the additional states.
- SetPassengerOfBlock (357), ServerboundCursorItemDrag (358),
  ClientboundPlayAudioContent (359), ServerboundRegisterAudioContent (360).
- Reserved crafting action at outer ID 16 / inner ID 18; shifted deprecated
  crafting action discriminators; reserved container IDs and ice-ball sound enum.
- Five independent byte-fixture tests for schema-derived packet/action encodings.
  These fixtures are **not vanilla packet captures**.

The sibling `../TeamSelenyx-BedrockData` repository contains updated protocol info
and sound IDs. Palettes, item/creative inventories and recipe data have not been
re-extracted from a .30 vanilla trace.

LunaX now records NETWORK 2225 and LAST_OPENED_IN `[1, 26, 60, 30, 1]`.
BDS world metadata confirms STORAGE 10, and generated chunks confirm CHUNK 42 and
subchunk format 9. LunaX continues writing its supported subchunk format 8; its
reader supports 9. BLOCK_STATES remains tied to the existing upgrade schema
1.21.60.33, not the client version. Neither upgrade-schema repository was changed.
The NetherNet sidecar advertises 2225 / 1.26.60.30.

The checked-in Composer pins still identify the older published preview revisions.
To use these **unpublished local changes**, run from LunaX:

```powershell
./tools/install-preview-dependencies.ps1
./bin/php/php.exe src/PocketMine.php --version
```

The helper validates both package names, writes ignored `composer-local-protocol.*`
files, refreshes mirrored dependencies (including uncommitted source), and runs
code generation. Keep both sibling repositories with this checkout. Running normal
`composer install` or `composer make-server` restores the published dependencies;
do not use those commands to build this preview checkpoint. Published dependency
pins must be updated together before distributing this version.

### Validation and remaining gates

- BedrockProtocol: PHPStan passes; PHPUnit 490 tests / 890 assertions pass.
- LunaX with local preview dependencies: PHPStan passes; PHPUnit 190 tests /
  96,209 assertions pass. Fixed the four existing CDN-related static-analysis errors.
- Fixed the PHP 8.1 platform/lock mismatch by resolving `brick/math` to 0.13.1.
- Ran protocol factory generation, targeted PHP CS Fixer, and LunaX code generation.
  Data-derived generated files had no differences with the currently available data.
- Isolated source-server smoke test generated all 224 spawn chunks and shut down
  cleanly (exit 0). Output: `../deps-1.26.60/lunax-smoke-30/startup-output.log`.
- Official BDS started and stopped cleanly; its isolated ticking area generated
  chunk data. Output: `../BDS-1.26.60-preview.30/validation-chunks.log`.
- Copied that BDS world into the isolated LunaX test directory; LunaX loaded it
  and shut down cleanly (exit 0). Output:
  `../deps-1.26.60/lunax-smoke-30/bds-import-output.log`. This is a server-side
  world loading test, not a client rendering or gameplay test.
- Installed Windows Preview reports `1.26.6030.0`, but the UI automation launch
  returned no targetable game window. No client login or gameplay test is claimed.

Remaining release blockers: implement/verify the inherited environment-attribute
payload changes and uncertain wire details using vanilla traffic; obtain .30
runtime palettes, item/creative/recipe data and any required upgrade mappings;
publish and pin the tested dependency revisions; playtest login, world loading,
inventory, crafting and representative gameplay with Preview 26.60.30. Automated
tests and server startup alone do not satisfy these gates.
Existing PHAR files were not rebuilt; this checkpoint applies to the local source
installation. All temporary BDS/LunaX test server processes were stopped.

## Earlier checkpoint (September)

Checked on 2026-09-21 against Mojang's `v1.26.60-preview.25` release (game build `1.26.60.25`, protocol `2211`). This is an analysis checkpoint, not a server compatibility claim.

## Downloaded inputs

- Windows preview BDS: `F:\minecraft\local\dev\deps-1.26.60\bedrock-server-1.26.60.25.zip`, extracted to `F:\minecraft\local\dev\BDS-1.26.60-preview.25`. SHA-256: `4C2C13B3D4EB9BCF204CDF3B306E98697A2D915BC0E0BE76B81E4ECC7661063B`.
- Mojang protocol metadata: `F:\minecraft\local\dev\deps-1.26.60\metadata-1.26.60-preview.25.zip`, extracted beside it (997 JSON schemas).
- Mojang developer notes: `F:\minecraft\local\dev\deps-1.26.60\developer_notes-1.26.60-preview.25.zip`, extracted beside it (212 files).

Sources: [official BDS preview ZIP](https://www.minecraft.net/bedrockdedicatedserver/bin-win-preview/bedrock-server-1.26.60.25.zip), [Mojang protocol metadata release](https://github.com/Mojang/bedrock-protocol-docs/releases/tag/v1.26.60-preview.25), [official preview changelog](https://feedback.minecraft.net/hc/en-us/articles/48940953884173-Minecraft-Beta-Preview-26-60-24).

## Current baseline and protocol changes

At the initial analysis checkpoint, `stable` targeted BedrockData and BedrockProtocol for 1.26.30 (protocol `1001`). The working branch now includes the existing 1.26.50 update (protocol `2193`). The downloaded preview declares protocol `2211`; the 1.26.50 final metadata used for comparison declares `2193`.

Compared with `F:\minecraft\local\dev\deps-1.26.50\metadata-1.26.50-final`, the preview metadata has 15 added schema files and no removed schema files. After ignoring version labels, descriptions, enum binary value annotations, and defaults, 52 existing schemas have structural changes.

Compared with the extracted 1.26.51 BDS, `definitions/` has the same 340 file paths. `behavior_packs/` grows from 2,482 to 2,821 files, with 340 new paths, mainly the new `vanilla_1.26.60` pack. `resource_packs/` keeps 196 files and replaces its versioned manifest path. These are file inventory differences; contents still need a data-level comparison.

Priority changes for the protocol fork:

1. Add packet IDs 353 `ClientboundMatchmakingState`, 354 `ServerboundStonecutterSetRecipe`, 355 `ClientboundStonecutterSetRecipe`, and 356 `ServerboundMatchmakingCancel`. The Stonecutter schemas appear in metadata even though `MinecraftPacketIds.json` only adds the matchmaking names; verify packet registration against the BDS binary or a live trace before implementation.
2. `LevelChunkPacketPayload` adds the boolean `Is Client Biome Update`. Check serialization order and chunk sender behavior.
3. `AnimatePacketPayload` adds a `Hand` field. Check encode and decode of hand animations.
4. `ClientboundUpdateSoundDataPacketPayload` has extensive structural changes. Inspect its action variants and related `Pause`, `Resume`, `SeekTo`, `SetPitch`, `SetVolume`, `Stop`, and `Fade` schemas together.
5. Inspect `ChangeDimension`, `ModalFormResponse`, `GraphicsParameterOverride`, `ServerboundLoadingScreen`, `UpdateClientOptions`, and the other changed packet payload schemas before updating the protocol library.
6. Review the added attribute and transition schemas (`ConstantAttributeData`, `NoiseTransitionAttributeData`, `NoiseTransitionSettingsData`, `TransitionAttributeData`, `TransitionSettingsData`) for world and biome data support.

## Suggested update sequence

1. Update the local BedrockProtocol fork against the downloaded metadata. Keep the preview version and protocol number explicit. Run its codec tests and capture a preview client login trace.
2. Compare the preview BDS definitions, behavior packs, and resource packs with the current BedrockData fork. Regenerate the required palettes, IDs, recipes, and mappings using the existing data generation tools.
3. Update `composer.json` and lock files to the tested local protocol and data revisions; regenerate `generated/` files with the repository's Composer scripts.
4. Run unit tests and a real preview client join/transfer test, including chunk display, inventory, forms, and dimension changes. Only then advertise 1.26.60 support.

Preview schemas can change before the final release. Preserve this input set so the final release can be diffed against it.

## Work in progress on 2026-09-21

The `bedrock-1.26.60` LunaX branch was created from `stable` and merged the existing `bedrock-1.26.50` branch as its starting point. Separate `bedrock-1.26.60` branches were created in the local TeamSelenyx BedrockProtocol and BedrockData repositories.

The protocol branch now contains the four new packet codecs, the protocol ID update, and the identified changes to chunk, animation, sound, inventory transaction, player list, skin, and dimension serialization. BedrockData's `protocol_info.json` now records preview build 1.26.60.25 and protocol 2211. These changes remain local and are not yet a supported release.

Validation so far: BedrockProtocol PHPStan passes; its PHPUnit suite passes (477 tests). LunaX PHPUnit passes (190 tests) when the in-progress protocol source is copied into the ignored local `vendor` directory. LunaX PHPStan reports four unrelated existing errors in `src/Server.php` and `src/resourcepacks/ResourcePackCdnServer.php`. A source bootstrap `--version` command reports `v26.60.25 beta` with that local protocol source. An isolated server smoke test using the local protocol source started successfully, generated a fresh `preview-smoke` world, and shut down cleanly; its data is under `F:\minecraft\local\dev\deps-1.26.60\lunax-smoke`.

Remaining work: implement the changed environment attribute payload and verify uncertain wire details with a vanilla trace; extract and validate the preview block palette, item/recipe data, and upgrade schemas; update the LunaX dependency manifest and lock file to published test revisions; regenerate BedrockData-derived code; update every applicable `WorldDataVersions` constant; and playtest the target client. The public BDS ZIP does not provide the debug symbols required for the guide's data extraction workflow. No preview client is available to this agent for login and gameplay validation.
