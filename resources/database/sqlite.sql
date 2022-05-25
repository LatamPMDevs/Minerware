-- #!sqlite

-- #{ table
	-- #{ players
		CREATE TABLE IF NOT EXISTS MinerwarePlayers
		(
			name             VARCHAR(32) PRIMARY KEY NOT NULL,
			gamesPlayed      NUMERIC     DEFAULT 0,
			gamesWon         NUMERIC     DEFAULT 0,
			lostGames        NUMERIC     DEFAULT 0,
			microgamesPlayed NUMERIC     DEFAULT 0,
			microgamesWon    NUMERIC     DEFAULT 0,
			lostMicrogames   NUMERIC     DEFAULT 0
		);
	-- #}
-- #}

-- #{ data
	-- #{ players
		-- #{ add
			-- # :name string
			-- # :gamesPlayed int 0
			-- # :gamesWon int 0
			-- # :lostGames int 0
			-- # :microgamesPlayed int 0
			-- # :microgamesWon int 0
			-- # :lostMicrogames int 0
			INSERT OR IGNORE INTO
			MinerwarePlayers(name, gamesPlayed, gamesWon, lostGames, microgamesPlayed, microgamesWon, lostMicrogames)
			VALUES (:name, :gamesPlayed, :gamesWon, :lostGames, :microgamesPlayed, :microgamesWon, :lostMicrogames);
		-- #}
		-- #{ get
			-- # :name string
			SELECT * FROM MinerwarePlayers WHERE name = :name;
		-- #}
		-- #{ set
			-- # :name string
			-- # :gamesPlayed int 0
			-- # :gamesWon int 0
			-- # :lostGames int 0
			-- # :microgamesPlayed int 0
			-- # :microgamesWon int 0
			-- # :lostMicrogames int 0
			INSERT OR REPLACE INTO
			MinerwarePlayers(name, gamesPlayed, gamesWon, lostGames, microgamesPlayed, microgamesWon, lostMicrogames)
			VALUES (:name, :gamesPlayed, :gamesWon, :lostGames, :microgamesPlayed, :microgamesWon, :lostMicrogames);
		-- #}
		-- #{ getAll
			SELECT * FROM MinerwarePlayers;
		-- #}
		-- #{ addGamesPlayed
			-- # :name string
			-- # :count int
			INSERT INTO MinerwarePlayers(name, gamesPlayed)
			VALUES(:name, :count)
			ON CONFLICT(name) DO UPDATE SET gamesPlayed = gamesPlayed + :count;
		-- #}
		-- #{ addGamesWon
			-- # :name string
			-- # :count int
			INSERT INTO MinerwarePlayers(name, gamesWon)
			VALUES(:name, :count)
			ON CONFLICT(name) DO UPDATE SET gamesWon = gamesWon + :count;
		-- #}
		-- #{ addLostGames
			-- # :name string
			-- # :count int
			INSERT INTO MinerwarePlayers(name, lostGames)
			VALUES(:name, :count)
			ON CONFLICT(name) DO UPDATE SET lostGames = lostGames + :count;
		-- #}
		-- #{ addMicrogamesPlayed
			-- # :name string
			-- # :count int
			INSERT INTO MinerwarePlayers(name, microgamesPlayed)
			VALUES(:name, :count)
			ON CONFLICT(name) DO UPDATE SET microgamesPlayed = microgamesPlayed + :count;
		-- #}
		-- #{ addMicrogamesWon
			-- # :name string
			-- # :count int
			INSERT INTO MinerwarePlayers(name, microgamesWon)
			VALUES(:name, :count)
			ON CONFLICT(name) DO UPDATE SET microgamesWon = microgamesWon + :count;
		-- #}
		-- #{ addLostMicrogames
			-- # :name string
			-- # :count int
			INSERT INTO MinerwarePlayers(name, lostMicrogames)
			VALUES(:name, :count)
			ON CONFLICT(name) DO UPDATE SET lostMicrogames = lostMicrogames + :count;
		-- #}
		-- #{ delete
			-- # :name string
			DELETE FROM MinerwarePlayers WHERE name = :name;
		-- #}
	-- #}
-- #}