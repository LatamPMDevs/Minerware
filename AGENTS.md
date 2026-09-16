# AGENTS.md

PocketMine-MP 5.x plugin (virion): **Minerware**, a CubeCraft-inspired microgames minigame. Main class is `LatamPMDevs\minerware\Minerware` (`plugin.yml` → `src/LatamPMDevs/minerware/Minerware.php`). Requires PHP 8.1+, PM API 5.0.0.

This is a **game-engine plugin**: the whole point is rotating quick-fire "microgames" inside disposable instanced arenas. Read the [Game engine](#game-engine-how-minerware-runs) section before touching anything gameplay-related. The rest of this file covers setup, conventions, and contributing.

---

## Setup / dependencies

- All virions are **virion v3** and declared in `composer.json` (no `.poggit.yml`). Run `composer install` before working. `vendor/` is gitignored.
- Virion deps: Commando (`cortexpe/commando` `dev-ready` → LatamPMDevs fork), ConfigUpdater (`ifera-mc/config-updater`, an inline `repositories` package entry), FormAPI, languages, fakeblocks, libasynql (`^4.2.3`), ScoreFactory. When adding/removing a virion, edit `composer.json` and regenerate `composer.lock` via `composer update`.
- Commando's `dev-ready` branch conflicts with fakeblocks (different `muqsit/simple-packet-handler` refs); stay on the fork's `dev-ready` and do not bump the shared transitive ref.
- `phpstan.neon.dist` is a template; copy it to `phpstan.neon` to enable analysis (neon files are gitignored).

## Verification (no unit tests in this repo)

- Code style: `php-cs-fixer fix src/` using root `.php-cs-fixer.php`. The CI auto-commits style fixes, so match it locally. Style: tabs for indentation, `declare(strict_types=1)`, fully-imported native functions/classes (no `\` prefixes in code), ordered imports, no closing `?>`.
- Static analysis: copy `phpstan.neon.dist` → `phpstan.neon`, then `composer install` and `vendor/bin/phpstan.phar analyze --no-progress`.
- To ship a release build, compile virions into the plugin with `vendor/bin/pharynx` (scans virion deps from `composer.json`).
- CI PHP versions differ: `ci.yml` (php-cs-fixer) uses PHP 8.0; `phpstan.yml` uses the 8.2 PocketMine binaries.

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

A **map** is a fixed mid-air `/platform` (exactly 24×24) built by MapWorldGenerator from a zipped world backup; each arena extracts its own copy of the map world into `worlds/<name>-<id>`, so every game is fully isolated and disposable (deleted via `Arena::deleteMap()` when the arena ends).

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
- `getRecompensePoints(): int` — points awarded to winners on success (`DEFAULT_RECOMPENSE_POINTS = 1`, `BOSS_RECOMPENSE_POINTS = 3`).
- `tick(): void` — called ~every 3 ticks (~150ms) while running.

Lifecycle contract (from `Microgame.php`):

1. `start()` — base sets `startTime = microtime(true)`, flips `hasStarted`, fires `MicrogameStartEvent`. It also auto-registers the microgame as a `Listener` when the subclass `implements Listener` (so you **don't** register in the subclass — though some microgames call `registerEvents` redundantly anyway; follow the pattern in `StandOnDiamond`).
2. `tick()` — the subclass drives gameplay. The universal idiom is: read `getTimeLeft()`; on expiry (and/or a completion condition) decide `addWinner`/`addLoser` for every player, then call `$this->arena->endCurrentMicrogame()` and `return`. While running, keep the XP bar updated (use `updateTimeBar($timeLeft)` or set it manually for multi-phase games like TNT Run).
3. `end()` — base unregisters the listener, rolls the whole stage back asynchronously with one merged inverse `Selection` (see below), flips `hasEnded`, fires `MicrogameEndEvent`. Subclasses override this to print summary messages (top throws/hits/kills, win/lose text) and **must call `parent::end()`** last.
4. `isRunning()` = `hasStarted && !hasEnded`.

**Winner/loser bookkeeping:** use `addWinner($player)` / `addLoser($player)` — they fire `PlayerWinMicrogameEvent` / `PlayerLoseMicrogameEvent` (which the plugin-wide `EventListener` uses to persist stats) *before* recording state. Do **not** mutate `winners`/`losers` arrays directly. `Arena::endCurrentMicrogame()` awards `getRecompensePoints()` to winners via `PointHolder` and bounds winners/losers from further damage.

### World mutation & cleanup (important!)

There are two paths for world mutation, by volume (see `docs/async-block-system.md`):

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
2. Implement the 5 abstract methods + `start()` + `tick()` + `end()` (call `parent::end()` last). Register as a listener only via `implements Listener`; the base `Microgame::start()` does `registerEvents` — do **not** call `registerEvents` yourself unless matching an existing precedent.
3. Give `getGameDuration()` a sensible **fractional** second count, and `getRecompensePoints()` `DEFAULT_RECOMPENSE_POINTS` (normal) or `BOSS_RECOMPENSE_POINTS` (boss).
4. Register it in `MicrogameManager::__construct` — `register(FillTheTank::class, "fillthetank")` for normal, `registerBoss(BowSpleef::class, "bowspleef")` for boss. The lowercase `saveName` is the public id.
5. Use `addWinner`/`addLoser` (never raw arrays), record every gameplay-changed block via `recordOriginalBlock()` (or a stage `Selection`), call `Utils::initPlayer` + set gamemode for every player at `start()`, and always handle `onBlockBreak`/`onBlockPlace` (cancel for in-game players) and `onDamage` (void → lose).
6. Mark `[x]` in `Microgames.md` when done, and (matching repo convention) note the game in that file's catalog even if the implementation is complete.

### Maps (`src/LatamPMDevs/minerware/map/`)

- `Map` parses a map JSON (from `database/maps/*.json`) into: name, platform min/max (must be exactly 24×24 — `Utils::calculateSize` enforces during registration), computed center, spawns[], winners/losers cage positions. It lazily generates the 9 **mini-platforms** (a 3×3 grid of 2×2 elevated tiles with 3-tile margins) used by most microgames (`getMiniPlatforms()` returns relative [x, y, z] offsets).
- `MapManager` is the in-memory `name → Map` registry (`add`/`remove`/`getByName`/`getRandom`/`getAll`/`getCount`).
- `MapWorldGenerator::generate(Map, uniqueId)` extracts the map's world `.zip` (from `database/backups/*.zip`) into `worlds/<name>-<id>` and loads it as the arena's disposable `World`.
- `MapRegisterer` is the **interactive map editor**: while active it listens on chat (`help`, `setplatform`, `setcages`, `setspawn`, `done`) and block-break events (blaze rod = pick corners/blocks). The platform must resolve to `24x24` or it errors. `done` saves the JSON via `DataManager::saveMapData`, zips the world backup, and unregisters. It also implements `Listener` and registers itself — don't register it twice.

### Database & persistence (`src/LatamPMDevs/minerware/database/`)

- libasynql (`DataManager::createContext`) with schemas in `resources/database/sqlite.sql` and `mysql.sql`; config block `database` in `resources/config.yml`.
- `PlayerData` is a JSON-serializable value object (name, generationTime, wins, bossgamesWon, microgamesWon, gamesPlayed, microgamesPlayed, timePlayed). Stats persist via `EventListener` (`MONITOR`-priority listeners for ArenaEnd, MicrogameEnd, PlayerWinMicrogame, PlayerQuitArena) calling `DataManager::add*` methods — so most stat writing is automatic from events, not hand-wired into gameplay code.
- `DataHolder` is a typed read wrapper over a map JSON / config array.
- Tunables read from config via `DataManager`: `getServerIp`, `getMaxRuntimeArenas`, `getArenaStartingTime`, `getMinimumStartingPlayers`.

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
- Adding a microgame: extend abstract `Microgame` in `src/LatamPMDevs/minerware/arena/microgame/` (`normal/` or `boss/` subdir), implement `getName`, `getLevel`, `getGameDuration`, `getRecompensePoints`, `tick`. Register it in `MicrogameManager::__construct` with a lowercase `saveName` key. Mark `[x]` in `Microgames.md` when complete. (Full guide in [Adding a microgame](#adding-a-microgame-step-by-step) above.)
- `Microgame::addWinner`/`addLoser` fire events (`PlayerWinMicrogameEvent`/`PlayerLoseMicrogameEvent`); use those to attribute points rather than mutating winner/loser state directly.
- `@phpstan-param` / `@phpstan-return` docblocks annotate `class-string<T>` templates (PHPStan level 6, paths = `src`).
- Maps: the platform must be exactly `24x24` (`Utils::calculateSize` enforces this). Map JSON data is saved under the plugin data folder `database/maps/`; configured interactively via chat commands during `/minerware arenas create`.
- `Arena` and `MapRegisterer` implement `Listener` and register events themselves; don't duplicate registration. `Microgame` subclasses that `implements Listener` are auto-registered by `Microgame::start()` and auto-unregistered by `Microgame::end()`.
- World/`chunk` mutation helper: `Utils::fill`/`fillCircle`/`buildCage` return the replaced `Block[]` so callers can restore them; stage builds go through `Microgame::getStageSelection()` + `commitStage()`, and `Microgame::end()` is the single async rollback point.

## Runtime / resources

- Player-facing text is localized via `.ini` files in `resources/languages/` (saved into the plugin data folder on load). Access through `Translator` (from `Minerware::getInstance()->getTranslator()`); the config `default-language` selects the locale.
- Database via libasynql; schemas live in `resources/database/sqlite.sql` and `mysql.sql`.
- `resources/config.yml` holds all runtime tunables (`database`, `max-runtime-arenas`, `arena-starting-time`, `minimum-starting-players`, `server-ip`, `default-language`) and is auto-versioned by ConfigUpdater (`CONFIG_VERSION = 1`).
- `maps/*.zip` at the repo root are shipped world backups; `DataManager` copies and registers them. The `maps/` directory in the data folder holds the per-map JSON configs.
- Commands (`command/subcommands/`): `arenas` (create/start; op-only), `join`, `statistics`/`stats`, `language` (op), `help`, `credits`. Permissions are declared in `plugin.yml`.
- Forms (`form/`): `FormManager` sends a `StatisticsForm` (SimpleForm) for the `statistics` command.