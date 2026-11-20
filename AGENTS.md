# AGENTS.md

PocketMine-MP 5.x plugin (virion): **Minerware**, a CubeCraft-inspired microgames minigame. Main class is `LatamPMDevs\minerware\Minerware` (`plugin.yml` → `src/LatamPMDevs/minerware/Minerware.php`). Requires PHP 8.1+, PM API 5.0.0.

This is a **game-engine plugin**: the whole point is rotating quick-fire "microgames" inside disposable instanced arenas. Read the [Game engine](#game-engine-how-minerware-runs) section before touching anything gameplay-related. The rest of this file covers setup, conventions, and contributing.

---

## Setup / dependencies

- All virions are **virion v3** and declared in `composer.json` (no `.poggit.yml`). Run `composer install` before working. `vendor/` is gitignored.
- Virion deps: Commando (`cortexpe/commando` `dev-ready` → LatamPMDevs fork), ConfigUpdater (`ifera-mc/config-updater`, an inline `repositories` package entry), FormAPI, languages, fakeblocks, libasynql (`^4.2.3`), ScoreFactory. When adding/removing a virion, edit `composer.json` and regenerate `composer.lock` via `composer update`.
- Commando's `dev-ready` branch conflicts with fakeblocks (different `muqsit/simple-packet-handler` refs); stay on the fork's `dev-ready` and do not bump the shared transitive ref.
- `phpstan.neon.dist` is a template; copy it to `phpstan.neon` to enable analysis (neon files are gitignored).

## Verification

- Code style: `php-cs-fixer fix` (root `.php-cs-fixer.php`, covers `src/` and `tests/`), with the version `ci.yml` pins. Style: tabs, `declare(strict_types=1)`, imported native functions/classes, ordered imports.
- Static analysis: `vendor/bin/phpstan.phar analyze --no-progress` (level 6; `phpstan.neon.dist` is picked up automatically).
- Tests: `composer test` (PHPUnit, `tests/phpunit/`). Language files must keep the same keys as `en_US.ini`.
- CI (`ci.yml`): code style, PHPStan, PHPUnit.
- Release build: `vendor/bin/pharynx` compiles the virions into the plugin.

---

## Game engine: how Minerware runs

### Big picture

```
Minerware (PluginBase, singleton)
 └─ DataManager (singleton)     – config, SQL database, languages, map JSON, arena tunables
 └─ MapManager (singleton)      – registry of loaded Map objects
 └─ MicrogameManager (singleton)– registry of microgame CLASSES (normal + boss)
 └─ ArenaManager (singleton)    – creates/tracks live Arena instances
      └─ Arena  ×N              – one per live game; drives the whole lobby→game→end FSM
           ├─ ArenaTask         – repeating scheduler task = the FSM tick (every 20 ticks / 1s)
           ├─ Microgame  ×1     – current microgame instance, ticked every 3 ticks
           ├─ Cage ×2           – winners / losers holding cages (stained glass)
           └─ PointHolder       – scoreboard points per player for this arena
```

Four singletons use `SingletonTrait` and are reached via `X::getInstance()`: `Minerware`, `DataManager`, `ArenaManager`, `MicrogameManager`. `MapManager` and `FormManager` are also singletons but you normally reach them through `DataManager`/commands.

A **map** is a fixed mid-air `/platform` (exactly 24×24) loaded once as a `MapTemplate` from its zipped world backup; each arena gets its own volatile in-memory world (`worlds/<name>-<id>` holds only a marker file), so every game is isolated and nothing is copied or saved (removed via `Arena::deleteMap()` when the arena ends).

### Lifecycle

1. **Plugin enable** (`Minerware::onEnable`) registers the packet hooker + fakeblock manager, loads maps from disk into `MapManager`, and registers the plugin-wide `EventListener` for arena/microgame stat events.
2. **Arena creation** — `ArenaManager::createArena(?Map)` picks a random map (throws if none), generates a 2-char + 2-digit id, and constructs an `Arena`. Any player can be routed into a shared arena via `getAvailable()` (reuses a `WAITING`/`STARTING` arena under 12 players, else makes a new one, capped by `max-runtime-arenas`).
3. **Arena constructor** builds/registers everything:
   - Generates the world (`MapWorldGenerator::generate`), freezes time at noon, builds the two `Cage`s at the map's cage positions.
   - Shuffles the **normal** microgame classes into `microgamesQueue`, then appends **one random boss** game at the end (so every run ends with a boss round).
   - Schedules two repeating tasks: `ArenaTask` every 20 ticks (the status FSM) and a 3-tick closure that calls the current `Microgame::tick()` while it's running.
   - Registers itself as a `Listener`, fires `ArenaCreationEvent`.
4. **Play** flows through `ArenaTask::onRun()` which switches on `Arena::getStatus()` (see below).
5. **Arena end** — `Arena::end()` ranks players in the scoreboard into P1/P2/P3 winners (ties chunked), sends the podium, fires `ArenaEndEvent`. The `ENDING` countdown then re-queues every player into a fresh arena (`ArenaManager::join`) and tears the arena + its world down.

### Arena status FSM (`src/LatamPMDevs/minerware/arena/Status.php`)

Driven entirely by `tasks/ArenaTask.php` on a 1-second repeating schedule (`ArenaTask`) plus `Arena::getCountdown()`:

| Status      | Entered when | What happens |
|-------------|--------------|--------------|
| `WAITING`   | arena starts | Ticks "need more players" tips until player count ≥ `minimum-starting-players` |
| `STARTING`  | min players met | 120s countdown (config `arena-starting-time`); cancels back to WAITING if count drops below min; force-skipped to 15s at max capacity; `forceStart()` via `/minerware arenas start` sets countdown to 5 |
| `INBETWEEN` | countdown hits 0 → `Arena::start()` | 5s inter-round pause showing the next microgame's name + "GO"; if the queue is empty at the end it calls `Arena::end()` instead |
| `INGAME`    | inter-round ends | `Arena::startNextMicrogame()` instantiates + `start()`s the next queued microgame; handled by the microgame's own `tick()`; when the microgame finishes it nulls `currentMicrogame` |
| `ENDING`    | `Arena::end()` | 10s podium, then requeue players, delete world, delete arena, cancel the task |

Key points:
- `Arena::setStatus()` fires a cancellable `ArenaChangeStatusEvent`, sets the countdown, and clears scoreboard lines on entry.
- The scoreboard is refreshed every task tick via `Arena::updateScoreboard()` (ScoreFactory side lines); it shows map/players in WAITING/STARTING, live sorted points + current/next microgame in INBETWEEN/INGAME, and is removed in ENDING.
- `INBETWEEN` checks `count($players) < ... == null` on `getNextMicrogame()` to decide between starting the next microgame or ending the arena — i.e. the boss game is last.

### Microgame lifecycle (the core abstraction)

`arena/microgame/Microgame.php` is the abstract base every normal *and* boss game extends. A microgame must implement:

- `getName(): string` — display name.
- `getLevel(): Level` — `Level::NORMAL` or `Level::BOSS` (drives scoreboard + stat attribution).
- `getGameDuration(): float` — **seconds** (fractional, e.g. `16.9`).
- Points awarded to winners come from `getLevel()` (`DEFAULT_RECOMPENSE_POINTS = 1`, `BOSS_RECOMPENSE_POINTS = 3`); override `getRecompensePoints()` only for something special.
- `tick(): void` — called ~every 3 ticks (~150ms) while running.

Lifecycle contract (from `Microgame.php`):

1. `start()` — base sets `startTime = microtime(true)`, flips `hasStarted`, fires `MicrogameStartEvent`. It also auto-registers the microgame as a `Listener` when the subclass `implements Listener` (so you **don't** register in the subclass — though some microgames call `registerEvents` redundantly anyway; follow the pattern in `StandOnDiamond`).
2. `tick()` — the subclass drives gameplay. The universal idiom is: read `getTimeLeft()`; on expiry (and/or a completion condition) decide `addWinner`/`addLoser` for every player, then call `$this->arena->endCurrentMicrogame()` and `return`. While running, keep the XP bar updated (use `updateTimeBar($timeLeft)` or set it manually for multi-phase games like TNT Run).
3. `end()` — base unregisters the listener, rolls the whole stage back asynchronously with one merged inverse `Selection` (see below), flips `hasEnded`, fires `MicrogameEndEvent`. Subclasses override this to print summary messages (top throws/hits/kills, win/lose text) and **must call `parent::end()`** last.
4. `isRunning()` = `hasStarted && !hasEnded`.

**Winner/loser bookkeeping:** use `addWinner($player)` / `addLoser($player)` — they fire `PlayerWinMicrogameEvent` / `PlayerLoseMicrogameEvent` (which the plugin-wide `EventListener` uses to persist stats) *before* recording state. Do **not** mutate `winners`/`losers` arrays directly. `Arena::endCurrentMicrogame()` awards `getRecompensePoints()` to winners via `PointHolder` and bounds winners/losers from further damage.

### World mutation & cleanup (important!)

There are two paths for world mutation, by volume:

- **Bulk stage builds → async.** A microgame builds its stage into a `Selection` (`getStageSelection()`; `addCell`/`addBlock`/`addFill`) and submits it with `commitStage()`. The build operation returns an **inverse `Selection`** (every applied cell's pre-change state, computed on the worker); `end()` submits it through the same async path to restore the exact pre-build terrain. The arena gates gameplay/countdown while a build is in flight (`Arena::isBuildingStage()`); never teleport players onto a stage before `commitStage()`'s completion callback runs.
- **Gameplay interaction writes (mined blocks, player placements).** Record the original block via `recordOriginalBlock($world->getBlockAt(...))` (BEFORE the write) or stage it into a `Selection`; `end()` restores everything asynchronously in one merged task.
- Helper `setMiniPlatformsAsync(Selection $selection, Block $block, array $keys = [])` stages every 2×2 mini-platform (or a subset by key) into the stage selection — small games call it with `AIR` to blank the stage.
- Sparse gameplay mechanics (TnTRun floor destruction, PlatformPlummet platform ticks) write synchronously per tick — they're gameplay, and their cells fall inside the stage selection's inverse, so no separate recording is needed.
- Don't set more blocks than you restore; a game that leaves the map dirty will leak stage state into the next microgame.

### Catches, cages, and player hygiene

- `Cage` (`arena/Cage.php`) builds a 7×7×5 stained-glass box (`Utils::buildCage`) with a y+2 offset spawn; `addPlayer` resets the player, sets ADVENTURE, and teleports them inside. `winners` cage = LIME glass, `losers` cage = RED. `Arena::resetCages()` relocates them to the map's cage positions after each round.
- `Utils::initPlayer($player)` is the canonical reset: extinguish, no flight, health/food 20, clear effects and every inventory (main/armor/cursor/off-hand). Call it at the start of every microgame for every player.
- The arena-level `onDamage` cancels damage while no microgame runs (void = respawn at random spawn), and winners/losers of the current microgame are immune to further damage. Microgames implement their own `onDamage` to handle voids (knock a player off → `addLoser` + `losersCage->addPlayer`) and PvP scoring (knockback hits / kills).
- `buildInvisibleBlocks()` / `unsetInvisibleBlocks()` (Arena) ring the platform with invisible-fake bedrock so players can't escape the arena bounds; games that need a sealed stage (e.g. StackBlocks, OneInTheChamber) call `$this->arena->buildInvisibleBlocks()` if not already set.

### Adding a microgame (step by step)

1. Create `src/LatamPMDevs/minerware/arena/microgame/<normal|boss>/<Name>.php`, starting from an existing sibling for the header/layout. `class <Name> extends Microgame implements Listener`.
2. Implement the 4 abstract methods + `start()` + `tick()` + `end()` (call `parent::end()` last). Register as a listener only via `implements Listener`; the base `Microgame::start()` does `registerEvents` — do **not** call `registerEvents` yourself unless matching an existing precedent.
3. Give `getGameDuration()` a sensible **fractional** second count, and `getLevel()` right (it sets the points). Helpers in `Microgame`: `endRound(bool $remainingWin)` to finish when time is up, `eliminate($player, $messageKey)` to send a loser to the cage, `sendOutcome($player, $wonKey, $loseKey)` for the end messages, `updateTimeBar($timeLeft)`. Traits: `CancelsBuildEvents` (cancel block break/place) and `LosesInVoid` (cancel damage, void makes a player lose; override `getFallMessageKey()` for another message).
4. Register it in `MicrogameManager::__construct` — `register(FillTheTank::class, "fillthetank")` for normal, `registerBoss(BowSpleef::class, "bowspleef")` for boss. The lowercase `saveName` is the public id.
5. Use `addWinner`/`addLoser` (never raw arrays), record every gameplay-changed block via `recordOriginalBlock()` (or a stage `Selection`), call `Utils::initPlayer` + set gamemode for every player at `start()`, and always handle `onBlockBreak`/`onBlockPlace` (cancel for in-game players) and `onDamage` (void → lose).
6. Mark `[x]` in `Microgames.md` when done, and (matching repo convention) note the game in that file's catalog even if the implementation is complete.

### Maps (`src/LatamPMDevs/minerware/map/`)

- `Map::fromArray` maps a map JSON (from `database/maps/*.json`) onto the models in `map/data/` with JsonMapper (strict, scalar types guarded), then `Map::fromData` checks the 24×24 platform and at least one spawn; bad maps are skipped with a warning into: name, platform min/max (must be exactly 24×24 — `Utils::calculateSize` enforces during registration), computed center, spawns[], winners/losers cage positions. It lazily generates the 9 **mini-platforms** (a 3×3 grid of 2×2 elevated tiles with 3-tile margins) used by most microgames (`getMiniPlatforms()` returns relative [x, y, z] offsets).
- `MapManager` is the in-memory `name → Map` registry (`add`/`remove`/`getByName`/`getRandom`/`getAll`/`getCount`).
- `map/world/`: `MapTemplates` extracts a backup once (again only if the zip changed) into `database/templates/<name>/` and opens it as a `MapTemplate`, which caches chunks lazily. `MapWorldProvider` serves clones of them to one arena world and ignores saves; `MapWorldProviderEntry` (registered as `minerware`) recognises the marker folders. `MapWorldGenerator::generate` creates an instance, `MapTemplates::removeInstance` deletes it, and stale instance folders are removed at enable. Backups may hold the world at the zip root (editor) or inside one folder.
- `MapRegisterer` is the **interactive map editor**: while active it listens on chat (`help`, `setplatform`, `setcages`, `setspawn`, `done`) and block-break events (blaze rod = pick corners/blocks). The platform must resolve to `24x24` or it errors. `done` saves the JSON via `DataManager::saveMapData`, zips the world backup, and unregisters. It also implements `Listener` and registers itself — don't register it twice.

### Database & persistence (`src/LatamPMDevs/minerware/database/`)

- libasynql (`DataManager::createContext`) with schemas in `resources/database/sqlite.sql` and `mysql.sql`; config block `database` in `resources/config.yml`.
- `PlayerData` is a JSON-serializable value object (name, generationTime, wins, bossgamesWon, microgamesWon, gamesPlayed, microgamesPlayed, timePlayed). Stats persist via `EventListener` (`MONITOR`-priority listeners for ArenaEnd, MicrogameEnd, PlayerWinMicrogame, PlayerQuitArena) calling `DataManager::add*` methods — so most stat writing is automatic from events, not hand-wired into gameplay code.
- Tunables read from config via `DataManager`: `getServerIp`, `getMaxRuntimeArenas`, `getArenaStartingTime`, `getMinimumStartingPlayers`.

### Join points (`join/`)

- Every way into a game goes through `Joiner::join()` / `tryJoin()` (permission `minerware.join`, already in an arena, `join-cooldown`, map filter) and returns a `JoinResult`; `ArenaManager::join()` is the unchecked low-level call (also used to requeue after a game).
- `JoinSigns`: a sign whose line 1 is `[Minerware]` (line 2 optional map) joins on click. The world is the only record; an in-memory index (filled on chunk load) lets lines 3-4 refresh every second, only when changed.
- `LobbyNpc` (`entity/object/`): saved with the world, so registered in `onLoad()` before worlds load. Hit or click joins. Its name tag uses the default language.
- `/mw joinpoint npc [map] | remove | list`. `remove` arms the next hit on an NPC for 10 seconds; signs are removed by breaking them (permission `minerware.joinpoint.remove`).

### Localization (`resources/languages/*.ini`)

- Player-facing text is localized via `.ini` files saved into the plugin data folder on load; access through `Translator` (`Minerware::getInstance()->getTranslator()`). `default-language` selects the locale.
- Translating with placeholders: `$this->plugin->getTranslator()->translate($player, "microgame.standondiamond.hitscount", ["{%player}" => ..., "{%hits_count}" => ...])`.
- Microgame item names are localized (`"microgame.item.pickaxe"`, `"microgame.item.powerstick"`, etc.), never hardcoded.
- `Utils::DyeColor2TextFormat` maps DyeColor → Minecraft text-format color so colored text renders correctly across languages.

### Custom entities (`src/LatamPMDevs/minerware/entity/`)

Simple optimizations/special-cases, all `NeverSavedWithChunkEntity` (never serialized to disk):
- `object/TextEntity` — a tiny invisible `Human` used to render per-player name tags over chests (NerdPole). Exists because a FloatingTextParticle can't show a different string per player (for translations).
- `object/FallingBlock` — a `FallingBlock` with a bounded lifetime (`getMaxTicksOfLife`/`setMaxTicksOfLife`) so crumbs don't persist.
- `projectile/ColorMissile` — a `SplashPotion` whose on-hit is computed by `ColorFloor` (overrides `onHit` to no-op; the boss game reads its hit in `onProjectileHit`).

---

## Architecture / conventions

- Every PHP source file opens with a large ASCII-art LGPL banner header — replicate it in any new file.
- Singletons via `SingletonTrait`: `Minerware`, `DataManager`, `ArenaManager`, `MicrogameManager`. Access with `X::getInstance()`.
- Adding a microgame: extend abstract `Microgame` in `src/LatamPMDevs/minerware/arena/microgame/` (`normal/` or `boss/` subdir), implement `getName`, `getLevel`, `getGameDuration`, `tick`. Register it in `MicrogameManager::__construct` with a lowercase `saveName` key. Mark `[x]` in `Microgames.md` when complete. (Full guide in [Adding a microgame](#adding-a-microgame-step-by-step) above.)
- `Microgame::addWinner`/`addLoser` fire events (`PlayerWinMicrogameEvent`/`PlayerLoseMicrogameEvent`); use those to attribute points rather than mutating winner/loser state directly.
- `@phpstan-param` / `@phpstan-return` docblocks annotate `class-string<T>` templates (PHPStan level 6, paths = `src`).
- Maps: the platform must be exactly `24x24` (`Utils::calculateSize` enforces this). Map JSON data is saved under the plugin data folder `database/maps/`; configured interactively via chat commands during `/minerware arenas create`.
- `Arena` and `MapRegisterer` implement `Listener` and register events themselves; don't duplicate registration. `Microgame` subclasses that `implements Listener` are auto-registered by `Microgame::start()` and auto-unregistered by `Microgame::end()`.
- World/`chunk` mutation helper: `Utils::fill`/`fillCircle`/`buildCage` return the replaced `Block[]` so callers can restore them; stage builds go through `Microgame::getStageSelection()` + `commitStage()`, and `Microgame::end()` is the single async rollback point.

- No `empty()` or `in_array()`; check explicitly (`count($x) !== 0`, `=== null`).
- State the unit of quantities in the name or a trailing comment (`//ticks`, `//seconds`).
- Broken invariants throw `AssumptionFailedError`; catch specific exceptions only.
- Games that only cancel block break/place `use CancelsBuildEvents`, and ones where the void is the only danger `use LosesInVoid`, instead of redeclaring the handlers.
- Leave a `TODO` instead of speculative code, and delete what a change orphans.

## Runtime / resources

- Player-facing text is localized via `.ini` files in `resources/languages/` (saved into the plugin data folder on load). Access through `Translator` (from `Minerware::getInstance()->getTranslator()`); the config `default-language` selects the locale.
- Database via libasynql; schemas live in `resources/database/sqlite.sql` and `mysql.sql`.
- `resources/config.yml` holds all runtime tunables (`database`, `max-runtime-arenas`, `arena-starting-time`, `minimum-starting-players`, `server-ip`, `default-language`) and is auto-versioned by ConfigUpdater (`CONFIG_VERSION = 1`).
- `maps/*.zip` at the repo root are world backups only. Nothing installs them: a map needs its JSON in `<data>/database/maps/` and its zip in `<data>/database/backups/`, both written by `MapRegisterer`; the zip may wrap the world in one folder.
- Commands (`command/subcommands/`): `arenas` (create/start; op-only), `join`, `joinpoint` (op), `statistics`/`stats`, `language` (op), `help`, `credits`. Permissions are declared in `plugin.yml`.
- Forms (`form/`): `FormManager` sends a `StatisticsForm` (SimpleForm) for the `statistics` command.