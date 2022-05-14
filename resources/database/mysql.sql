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
            UPDATE players SET gamesPlayed = gamesPlayed + :count WHERE name = :name;
        -- #}
        -- #{ addGamesWon
            -- # :name string
            -- # :count int
            UPDATE players SET gamesWon = gamesWon + :count WHERE name = :name;
        -- #}
        -- #{ addLostGames
            -- # :name string
            -- # :count int
            UPDATE players SET lostGames = lostGames + :count WHERE name = :name;
        -- #}
        -- #{ addMicrogamesPlayed
            -- # :name string
            -- # :count int
            UPDATE players SET microgamesPlayed = microgamesPlayed + :count WHERE name = :name;
        -- #}
        -- #{ addMicrogamesWon
            -- # :name string
            -- # :count int
            UPDATE players SET microgamesWon = microgamesWon + :count WHERE name = :name;
        -- #}
        -- #{ addLostMicrogames
            -- # :name string
            -- # :count int
            UPDATE players SET lostMicrogames = lostMicrogames + :count WHERE name = :name;
        -- #}
        -- #{ delete
            -- # :name string
            DELETE FROM players WHERE name = :name;
        -- #}
    -- #}
-- #}