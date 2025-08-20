USE foosball;

CREATE TABLE IF NOT EXISTS teams (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  rating INT NOT NULL DEFAULT 1500,
  wins INT NOT NULL DEFAULT 0,
  games_played INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO teams (name, rating, wins, games_played) VALUES
('Alex', 1500, 0, 0),
('Ben', 1500, 0, 0),
('Chris', 1500, 0, 0),
('Dana', 1500, 0, 0);
