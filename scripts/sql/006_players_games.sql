-- 006_players_games.sql
USE foosball;

CREATE TABLE IF NOT EXISTS spieler (
  SpielerID INT AUTO_INCREMENT PRIMARY KEY,
  Vorname   VARCHAR(80) NOT NULL,
  Nachname  VARCHAR(80) NOT NULL,
  Geschlecht ENUM('m','w','d') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS spiel (
  SpielID  INT AUTO_INCREMENT PRIMARY KEY,
  Spieler1 INT NOT NULL,
  Spieler2 INT NOT NULL,
  Tore1    INT NOT NULL DEFAULT 0,
  Tore2    INT NOT NULL DEFAULT 0,
  gespielt_am TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_spiel_sp1 FOREIGN KEY (Spieler1) REFERENCES spieler(SpielerID),
  CONSTRAINT fk_spiel_sp2 FOREIGN KEY (Spieler2) REFERENCES spieler(SpielerID)
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS spiel_spieler (
  Spiel_SpielerID INT AUTO_INCREMENT PRIMARY KEY,
  SpielerID INT NOT NULL,
  SpielID   INT NOT NULL,
  CONSTRAINT fk_ss_spieler FOREIGN KEY (SpielerID) REFERENCES spieler(SpielerID),
  CONSTRAINT fk_ss_spiel   FOREIGN KEY (SpielID)   REFERENCES spiel(SpielID)
) ENGINE=InnoDB;

-- kleine Beispieldaten
INSERT INTO spieler (Vorname, Nachname, Geschlecht) VALUES
('Alex', 'Beispiel', 'm'),
('Bella', 'Demo', 'w'),
('Chris', 'Test', 'd');
