# AGENTS.md

PocketMine-MP 5 plugin (PHP 8.1+, API 5.0.0): **Minerware**, a CubeCraft-style minigame that rotates quick "microgames" inside disposable arenas. Main class: `LatamPMDevs\minerware\Minerware`.

## Setup and checks

- Virions are declared in `composer.json` (v3, no `.poggit.yml`). Run `composer install`. Commando stays on the LatamPMDevs fork's `dev-ready`; do not bump the shared `simple-packet-handler` ref.
- Style: `php-cs-fixer fix` (version pinned in `ci.yml`). Tabs, `declare(strict_types=1)`, imported functions and classes, LGPL banner on every file.
- Types: `vendor/bin/phpstan.phar analyze --no-progress` (level 9).
- Tests: `composer test`. Language files must keep the keys of `en_US.ini`.
- Phar: `vendor/bin/pharynx -c -i . -p=<out>.phar`.

## Conventions

- No `empty()` or `in_array()`: check explicitly (`=== []`, `isset` on a flipped array).
- Units in names or trailing comments (`//ticks`, `//seconds`).
- Broken invariants throw `AssumptionFailedError`. Catch specific exceptions; the one broad `catch` is `Arena::runGuarded()`.
- Leave a `TODO` instead of speculative code; delete what a change orphans.
- Docs stay short.
- Singletons (`SingletonTrait`): `Minerware`, `DataManager`, `ArenaManager`, `MicrogameManager`, `MapManager`, `MapTemplates`.

## How it runs

```
ArenaManager ─ Arena ×N ─ ArenaPhase (shared, stateless)
                        ├ Countdown, Podium, ArenaWalls, StageGate
                        ├ MicrogameQueue ─ Microgame (current) ─ StageJournal
                        └ Cage ×2, PointHolder, ScoreboardRenderer
```

- Two plugin-wide tasks drive everything: `ArenaManager::tick()` (1 s) and `tickMicrogames()` (3 ticks). Each arena step runs in `Arena::runGuarded()`: a throwing arena is logged and closed, the others keep running.
- **Phases** (`arena/phase/`): `Waiting` → `Starting` → `Inbetween` ⇄ `Ingame` → `Ending`. A phase has a name, a duration, `isLobby()`, a `tick()` and a scoreboard. `Arena::setPhase()` fires `ArenaChangePhaseEvent`. The phase is the only state; there is no status enum.
- `Inbetween` ends the game when the queue is empty; the last queued game is always a boss. `Ending` requeues everyone into a new arena, then closes this one.
- **Worlds** (`map/world/`): a map is a 24×24 mid-air platform. Each map zip is opened once as a `MapTemplate`; every arena gets an in-memory copy through `MapWorldProvider` (the world folder holds only a marker file, saves do nothing). The editor keeps real worlds.
- **Async builds**: all bulk block changes are `Selection`s written by `AsyncBlockManager`. `StageGate::build()` counts them; while any is in flight the arena holds its tick (`Arena::isBuildingStage()`), and a lost callback is released after 30 s.

## Microgames (`arena/microgame/{normal,boss}/`)

Extend `Microgame`; implement `getName`, `getLevel` (`NORMAL`/`BOSS`, sets the points), `getGameDuration` (seconds), `tick`, and usually `start`/`end`. Register in `MicrogameManager::__construct`.

- `start()` builds the stage into `getStageSelection()`, calls `commitStage()`, then `parent::start()` last (it registers the listener when the class `implements Listener`; `end()` unregisters it).
- `StageJournal` rolls everything back in one merged async write at `end()`: stage builds, `pushStageOperation()` changes, and blocks recorded with `recordOriginalBlock()` before gameplay changes them.
- Decide players with `win()`, `lose()`, `eliminate($player, $key)` (lose plus message), or `endRound($remainingWin)`. They fire the win/lose events that persist stats; never touch the arrays.
- `sendOutcome()`, `updateTimeBar()`, `announceResults()` are shared helpers. Traits: `CancelsBuildEvents`, `LosesInVoid`.
- Reset players with `Utils::initPlayer()` and set the gamemode in `start()`. Guard every listener with `$this->arena->inGame($player)`.

## Join points (`join/`)

All entries go through `Joiner::join()`/`tryJoin()` (permission, already in arena, `join-cooldown`, map filter) and return a `JoinResult`. `JoinSigns` (`[Minerware]` sign), `LobbyNpc` (saved entity, registered in `onLoad()`), `/minerware join`. `/minerware npc` places and removes the NPCs and the leaderboard.

## Other

- `ArenaListener` is the only player listener for arenas (`ArenaManager::getArenaOf()` is a player-id map). `SpecialItems` builds the items with a custom behavior (leave bed, editor rod), tagged `minerware:<name>`; their handlers check the tag. `/minerware arena admin` opens `ArenaAdminForm`.
- `MapRegisterer`: per-player map editor (chat `setplatform`, `setcages`, `setspawn`, `done`, driven by the `SpecialItems` rod). Maps live in `database/maps/*.json` (`MapData` models, strict JsonMapper) with their zip in `database/backups/`. `resources/maps/<Name>.json` + `.zip` are installed on the first run, never over existing files.
- Leaderboard: `Leaderboard` caches the top wins from an async query (on start, every minute, and after each win); `LeaderboardText` and `TopPlayerNpc` (a `Human`, so its skin is saved with it) only read the cache. `LeaderboardListener` gives an NPC the skin of its player when they join or change it.
- Stats: libasynql (`resources/database/*.sql`), keyed by the player's UUID (their name is only stored for display and `/minerware stats <name>`), written from `EventListener` on arena and microgame events. There are no migrations: delete a database made by an older build.
- Text: `resources/languages/*.ini` through `Translator`. Locales Minecraft lacks (`vi_VN`) are skipped with a warning.
- Config: `resources/config.yml`, versioned by ConfigUpdater (`CONFIG_VERSION = 2`).
