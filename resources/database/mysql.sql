-- #!mysql

-- #{ table
	-- #{ players
		CREATE TABLE IF NOT EXISTS players
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
			players(name, gamesPlayed, gamesWon, lostGames, microgamesPlayed, microgamesWon, lostMicrogames)
			VALUES (:name, :gamesPlayed, :gamesWon, :lostGames, :microgamesPlayed, :microgamesWon, :lostMicrogames);
		-- #}
		-- #{ get
			-- # :name string
			SELECT * FROM players WHERE name = :name;
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
			players(name, gamesPlayed, gamesWon, lostGames, microgamesPlayed, microgamesWon, lostMicrogames)
			VALUES (:name, :gamesPlayed, :gamesWon, :lostGames, :microgamesPlayed, :microgamesWon, :lostMicrogames);
		-- #}
		-- #{ getAll
			SELECT * FROM players;
		-- #}
		-- #{ addGamesPlayed
			-- # :name string
			-- # :count int
				INSERT INTO players(name, gamesPlayed)
				VALUES(:name, :count)
				ON DUPLICATE KEY UPDATE
					gamesPlayed = gamesPlayed + :count;
		-- #}
		-- #{ addGamesWon
			-- # :name string
			-- # :count int
			INSERT INTO players(name, gamesWon)
				VALUES(:name, :count)
				ON DUPLICATE KEY UPDATE
					gamesWon = gamesWon + :count;
		-- #}
		-- #{ addLostGames
			-- # :name string
			-- # :count int
			INSERT INTO players(name, lostGames)
				VALUES(:name, :count)
				ON DUPLICATE KEY UPDATE
					lostGames = lostGames + :count;
		-- #}
		-- #{ addMicrogamesPlayed
			-- # :name string
			-- # :count int
			INSERT INTO players(name, microgamesPlayed)
				VALUES(:name, :count)
				ON DUPLICATE KEY UPDATE
					microgamesPlayed = microgamesPlayed + :count;
		-- #}
		-- #{ addMicrogamesWon
			-- # :name string
			-- # :count int
			INSERT INTO players(name, microgamesWon)
				VALUES(:name, :count)
				ON DUPLICATE KEY UPDATE
					microgamesWon = microgamesWon + :count;
		-- #}
		-- #{ addLostMicrogames
			-- # :name string
			-- # :count int
			INSERT INTO players(name, lostMicrogames)
				VALUES(:name, :count)
				ON DUPLICATE KEY UPDATE
					lostMicrogames = lostMicrogames + :count;
		-- #}
		-- #{ delete
			-- # :name string
			DELETE FROM players WHERE name = :name;
		-- #}
	-- #}
-- #}