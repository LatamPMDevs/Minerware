-- #!mysql

-- #{ table
	-- #{ players
		CREATE TABLE IF NOT EXISTS MinerwarePlayers
		(
			uuid             VARCHAR(36) PRIMARY KEY NOT NULL,
			name             VARCHAR(32) NOT NULL,
			wins             NUMERIC     DEFAULT 0,
			bossgamesWon     NUMERIC     DEFAULT 0,
			microgamesWon    NUMERIC     DEFAULT 0,
			gamesPlayed      NUMERIC     DEFAULT 0,
			microgamesPlayed NUMERIC     DEFAULT 0,
			timePlayed       NUMERIC     DEFAULT 0
		);
	-- #}
	-- #{ languages
		CREATE TABLE IF NOT EXISTS MinerwareLanguages
		(
			uuid   VARCHAR(36) PRIMARY KEY NOT NULL,
			locale VARCHAR(8)  NOT NULL
		);
	-- #}
	-- #{ check
		SELECT uuid FROM MinerwarePlayers LIMIT 1;
	-- #}
-- #}

-- #{ data
	-- #{ players
		-- #{ get
			-- # :uuid string
			SELECT * FROM MinerwarePlayers WHERE uuid = :uuid;
		-- #}
		-- #{ getByName
			-- # :name string
			SELECT * FROM MinerwarePlayers WHERE LOWER(name) = LOWER(:name) ORDER BY gamesPlayed DESC LIMIT 1;
		-- #}
		-- #{ addWins
			-- # :uuid string
			-- # :name string
			-- # :count int
			INSERT INTO MinerwarePlayers(uuid, name, wins)
			VALUES(:uuid, :name, :count)
			ON DUPLICATE KEY UPDATE
				wins = wins + :count,
				name = :name;
		-- #}
		-- #{ addBossgamesWon
			-- # :uuid string
			-- # :name string
			-- # :count int
			INSERT INTO MinerwarePlayers(uuid, name, bossgamesWon)
			VALUES(:uuid, :name, :count)
			ON DUPLICATE KEY UPDATE
				bossgamesWon = bossgamesWon + :count,
				name = :name;
		-- #}
		-- #{ addMicrogamesWon
			-- # :uuid string
			-- # :name string
			-- # :count int
			INSERT INTO MinerwarePlayers(uuid, name, microgamesWon)
			VALUES(:uuid, :name, :count)
			ON DUPLICATE KEY UPDATE
				microgamesWon = microgamesWon + :count,
				name = :name;
		-- #}
		-- #{ addGamesPlayed
			-- # :uuid string
			-- # :name string
			-- # :count int
			INSERT INTO MinerwarePlayers(uuid, name, gamesPlayed)
			VALUES(:uuid, :name, :count)
			ON DUPLICATE KEY UPDATE
				gamesPlayed = gamesPlayed + :count,
				name = :name;
		-- #}
		-- #{ addMicrogamesPlayed
			-- # :uuid string
			-- # :name string
			-- # :count int
			INSERT INTO MinerwarePlayers(uuid, name, microgamesPlayed)
			VALUES(:uuid, :name, :count)
			ON DUPLICATE KEY UPDATE
				microgamesPlayed = microgamesPlayed + :count,
				name = :name;
		-- #}
		-- #{ addTimePlayed
			-- # :uuid string
			-- # :name string
			-- # :seconds int
			INSERT INTO MinerwarePlayers(uuid, name, timePlayed)
			VALUES(:uuid, :name, :seconds)
			ON DUPLICATE KEY UPDATE
				timePlayed = timePlayed + :seconds,
				name = :name;
		-- #}
	-- #}
	-- #{ languages
		-- #{ get
			-- # :uuid string
			SELECT locale FROM MinerwareLanguages WHERE uuid = :uuid;
		-- #}
		-- #{ set
			-- # :uuid string
			-- # :locale string
			REPLACE INTO MinerwareLanguages(uuid, locale)
			VALUES (:uuid, :locale);
		-- #}
	-- #}
-- #}