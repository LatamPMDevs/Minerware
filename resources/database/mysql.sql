-- #!mysql

-- #{ table
	-- #{ players
		CREATE TABLE IF NOT EXISTS MinerwarePlayers
		(
			name             VARCHAR(32) PRIMARY KEY NOT NULL,
			wins             NUMERIC     DEFAULT 0,
			bossgamesWon     NUMERIC     DEFAULT 0,
			microgamesWon    NUMERIC     DEFAULT 0,
			gamesPlayed      NUMERIC     DEFAULT 0,
			microgamesPlayed NUMERIC     DEFAULT 0,
			timePlayed       NUMERIC     DEFAULT 0
		);
	-- #}
-- #}

-- #{ data
	-- #{ players
		-- #{ add
			-- # :name string
			-- # :wins int 0
			-- # :bossgamesWon int 0
			-- # :microgamesWon int 0
			-- # :gamesPlayed int 0
			-- # :microgamesPlayed int 0
			-- # :timePlayed int 0
			INSERT OR IGNORE INTO
			MinerwarePlayers(name, wins, bossgamesWon, microgamesWon, gamesPlayed, microgamesPlayed, timePlayed)
			VALUES (:name, :wins, :bossgamesWon, :microgamesWon, :gamesPlayed, :microgamesPlayed, :timePlayed);
		-- #}
		-- #{ get
			-- # :name string
			SELECT * FROM MinerwarePlayers WHERE name = :name;
		-- #}
		-- #{ set
			-- # :name string
			-- # :wins int 0
			-- # :bossgamesWon int 0
			-- # :microgamesWon int 0
			-- # :gamesPlayed int 0
			-- # :microgamesPlayed int 0
			-- # :timePlayed int 0
			INSERT OR REPLACE INTO
			MinerwarePlayers(name, wins, bossgamesWon, microgamesWon, gamesPlayed, microgamesPlayed, timePlayed)
			VALUES (:name, :wins, :bossgamesWon, :microgamesWon, :gamesPlayed, :microgamesPlayed, :timePlayed);
		-- #}
		-- #{ getAll
			SELECT * FROM MinerwarePlayers;
		-- #}
		-- #{ addWins
			-- # :name string
			-- # :count int
				INSERT INTO MinerwarePlayers(name, wins)
				VALUES(:name, :count)
				ON DUPLICATE KEY UPDATE
					wins = wins + :count;
		-- #}
		-- #{ addBossgamesWon
			-- # :name string
			-- # :count int
			INSERT INTO MinerwarePlayers(name, bossgamesWon)
				VALUES(:name, :count)
				ON DUPLICATE KEY UPDATE
					bossgamesWon = bossgamesWon + :count;
		-- #}
		-- #{ addMicrogamesWon
			-- # :name string
			-- # :count int
			INSERT INTO MinerwarePlayers(name, microgamesWon)
				VALUES(:name, :count)
				ON DUPLICATE KEY UPDATE
					microgamesWon = microgamesWon + :count;
		-- #}
		-- #{ addGamesPlayed
			-- # :name string
			-- # :count int
			INSERT INTO MinerwarePlayers(name, gamesPlayed)
				VALUES(:name, :count)
				ON DUPLICATE KEY UPDATE
					gamesPlayed = gamesPlayed + :count;
		-- #}
		-- #{ addMicrogamesPlayed
			-- # :name string
			-- # :count int
			INSERT INTO MinerwarePlayers(name, microgamesPlayed)
				VALUES(:name, :count)
				ON DUPLICATE KEY UPDATE
					microgamesPlayed = microgamesPlayed + :count;
		-- #}
		-- #{ addTimePlayed
			-- # :name string
			-- # :seconds int
			INSERT INTO MinerwarePlayers(name, timePlayed)
				VALUES(:name, :seconds)
				ON DUPLICATE KEY UPDATE
					timePlayed = timePlayed + :seconds;
		-- #}
		-- #{ delete
			-- # :name string
			DELETE FROM MinerwarePlayers WHERE name = :name;
		-- #}
	-- #}
-- #}