<div align="center">
  <h1>⛏️ Minerware 🎮</h1>
  <p>⚡ Multiple classic microgames in a single chunk — inspired by CubeCraft, built for PocketMine-MP</p>

  <img src="https://www.cubecraft.net/attachments/1611402324248-png.184772/" alt="Minerware map banner" width="720">

  [![CI](https://img.shields.io/github/actions/workflow/status/LatamPMDevs/Minerware/phpstan.yml?label=CI&style=flat&logo=github)](https://github.com/LatamPMDevs/Minerware/actions/workflows/phpstan.yml)
  [![GitHub Downloads](https://img.shields.io/github/downloads/LatamPMDevs/Minerware/total?style=flat&label=github+downloads&logo=github&logoColor=white)](https://github.com/LatamPMDevs/Minerware/releases)
  [![License](https://img.shields.io/github/license/LatamPMDevs/Minerware?style=flat&logo=opensourceinitiative&logoColor=white)](LICENSE)
</div>

---

## 📃 Description

**Minerware** is a minigame plugin for [PocketMine-MP](https://github.com/pmmp/PocketMine-MP) that recreates the classic **CubeCraft** microgames. Players rotate through quick-fire rounds inside fully instanced arenas — each one a disposable copy of the map — finishing every run with an epic **boss microgame**.

---

## ✨ Features

* 🎮 **Microgames Galore:** A lot of microgames to play, with more on the way.
* 🏟️ **Instanced Arenas:** Every game runs in its own disposable world copy, so multiple arenas can run at the same time.
* 🏆 **Points & Podium:** Win microgames to earn points (boss rounds are worth more) and reach the top-3 podium.
* 🗺️ **Custom Maps:** Use any world as long as its platform is exactly `24x24`, with a built-in interactive map editor.
* 🌍 **Multi-Language:** Player-facing text is fully translatable via `.ini` language files.
* 💾 **Data Support:** Native support for **SQLite3** and **MySQL** providers.
* 📊 **Statistics:** Tracks wins, microgames and bossgames won, games played and time played, viewable in-game.
* 🧭 **Forms UI:** Statistics are shown through clean in-game forms — no command spam.

---

## 📥 Download

<div align="center">

[![Nightly Build](https://img.shields.io/badge/dynamic/yaml?url=https://raw.githubusercontent.com/LatamPMDevs/Minerware/master/plugin.yml&query=$.version&label=Nightly+Build&prefix=v&color=blueviolet&style=for-the-badge&logo=github&logoColor=white)](https://github.com/LatamPMDevs/Minerware/releases/download/nightly/Minerware.phar)

*Always up to date from the latest commit*

</div>

---

## 🗺️ Maps

You can find some maps [here](https://github.com/LatamPMDevs/Minerware/tree/master/maps). The platform of these maps has a measure of `24x24` — you can use custom maps as long as they comply with that measure.

#### Available maps:

| Map | Download |
| :--- | :--- |
| City | [City.zip](https://github.com/LatamPMDevs/Minerware/blob/master/maps/City.zip) |
| Gladiator | [Gladiator.zip](https://github.com/LatamPMDevs/Minerware/blob/master/maps/Gladiator.zip) |
| Jungle | [Jungle.zip](https://github.com/LatamPMDevs/Minerware/blob/master/maps/Jungle.zip) |
| Jurassic | [Jurassic.zip](https://github.com/LatamPMDevs/Minerware/blob/master/maps/Jurassic.zip) |

> [!NOTE]
> Download the world, place it in your server's `worlds` folder, and register it in game with [`/minerware arenas create <world>`](#-getting-started).

---

## 🎮 Microgames

### Normal games

| Microgame | Description |
| :--- | :--- |
| Fill the Tank | A random puddle will form on the platform — collect the water and click on the furnace to fill the tank up! |
| Ignite The TNT | Light at least one block of TNT with your flint & steel before the time runs out. |
| Last Knight Standing | Players will fight to be the last one standing. |
| Mine Ore | The platform turns into a giant cube of cobblestone and ores — mine the ore you are commanded to. |
| Nerd Pole | Grab blocks and snowballs from the corner chests, build up to the platform in the air and stand on it. Don't get hit off, though! |
| One In The Chamber | You're given a bow, 1 arrow and a sword. Shoot someone and they instantly die; kill someone and you get a new arrow. |
| Platform Plummet | Don't fall off as the platforms around and underneath you crumble away. |
| Sneaking | Players have to keep sneaking until the time is up. |
| Stack Blocks | You're given 20 blocks of wool of a random color — build a pillar 10 blocks high. |
| Stand on Color | The platform turns into an assortment of colored wool — stand on the instructed color. |
| Stand on Diamond | When the time runs out, every platform disappears except the diamond block ones. You'd better be standing on one! |

### Boss games

| Microgame | Description |
| :--- | :--- |
| Bow Spleef | The platform is transformed into TNT — shoot it with your flame bow to make other players fall. Survive as one of the last three standing to win! |
| Color Floor | Paint floor tiles with your Colorizer and spread your color. Been circled off? Throw a paint missile to reclaim ground. Top 3 players with the most colored tiles win! |
| TNT Run | You get teleported onto a TNT platform — don't fall as the TNT vanishes underneath you. |

> [!NOTE]
> Winning a normal microgame is worth **1 point**, while boss microgames are worth **3 points**. The player with the most points when the run ends wins!

---

## 🤖 Commands

| Command | Description | Permission |
| :--- | :--- | :--- |
| `/minerware arenas create <world>` | Start the interactive setup of a new arena map | `minerware.command.arenas` |
| `/minerware arenas start` | Force-start the arena you are currently in | `minerware.command.arenas` |
| `/minerware join` | Join a random arena | `minerware.command.join` |
| `/minerware statistics [user]` | View a player's statistics | `minerware.command.statistics` |
| `/minerware language [locale]` | Change (or list) the plugin languages | `minerware.command.language` |
| `/minerware help` | Show the list of available commands | `minerware.command.help` |
| `/minerware credits` | Show the plugin credits | `minerware.command.credits` |

---

## 🚀 Getting Started

### Installation

1. Download the plugin from the [Download](#-download) section
2. Drop `Minerware.phar` into your server's `plugins` folder
3. Start the server — `config.yml` and the language files are generated automatically

### Arena set up

Requirements:

* Operator or permission: `minerware.command.arenas`
* A map world loaded on the server, with a platform of exactly `24x24` blocks

Steps:

1. Run: `/minerware arenas create <world>`
2. Type `help` in the chat to display the actions list
3. Type each action in the chat to set it up (`setplatform`, `setcages`, `setspawn`, …)
4. At the end, type `done` in the chat — the map data and world backup are saved automatically

### Arena joining

Currently it is only possible to join the game using the command:

```
/minerware join
```

You will be routed into a shared arena; the game starts automatically once enough players are in.

---

## ⚙️ Configuration

All runtime tunables live in `config.yml` (auto-updated between versions):

| Option | Default | Description |
| :--- | :--- | :--- |
| `database.type` | `sqlite` | Storage backend: `sqlite` or `mysql` |
| `max-runtime-arenas` | `15` | Maximum number of simultaneous arenas (15 ~ 20 recommended) |
| `arena-starting-time` | `120` | Countdown in seconds once the minimum player count is met |
| `minimum-starting-players` | `2` | Players required to start the countdown (4 ~ 6 recommended) |
| `server-ip` | — | Server IP shown on the scoreboard |
| `default-language` | `en_US` | Default locale (see the `languages` folder) |

---

## 📋 FAQ

<details>
<summary><b>How do I win a game?</b></summary>
Each microgame awards points to its winners — <b>1 point</b> for normal games and <b>3 points</b> for boss games. When the run ends, the players with the most points make the podium (1st, 2nd and 3rd).
</details>

<details>
<summary><b>How many players can play in one arena?</b></summary>
An arena starts counting down once at least <code>minimum-starting-players</code> (2 by default, 4 ~ 6 recommended) join, and holds up to <b>12 players</b>. Since every arena is a fully isolated world copy, multiple games can run simultaneously — capped by <code>max-runtime-arenas</code>.
</details>

<details>
<summary><b>Can I add my own maps?</b></summary>
<b>Yes!</b> Any world works as long as its platform measures exactly <code>24x24</code> blocks. Load the world on your server and run <code>/minerware arenas create &lt;world&gt;</code> to open the interactive map editor.
</details>

<details>
<summary><b>Which software versions are supported?</b></summary>
Minerware requires <b>PocketMine-MP 5.0.0+</b> and <b>PHP 8.1+</b>. PocketMine-MP 4 is not supported.
</details>

<details>
<summary><b>I found a bug or my server crashed! What should I do?</b></summary>
Please <b>report the issue on <a href="https://github.com/LatamPMDevs/Minerware/issues">GitHub</a></b>. Make sure to include the crash dump and the steps to reproduce the error so we can fix it as soon as possible.
</details>

---

## 💖 Support the Project

Minerware is and will always be **free and open-source**. If you enjoy the plugin or it helps your server, consider supporting future development — it goes a long way!

<div align="center">

[![Donate](https://img.shields.io/badge/Donate-Support_Me-ff69b4?style=for-the-badge&logo=githubsponsors&logoColor=white)](https://donate.endergames.org/IvanCraft623)

</div>
