USE foosball;

CREATE TABLE IF NOT EXISTS matches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  played_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  mode ENUM('1v1','2v2') NOT NULL DEFAULT '1v1',
  team_a_id INT NOT NULL,
  team_b_id INT NOT NULL,
  score_a TINYINT UNSIGNED NOT NULL,
  score_b TINYINT UNSIGNED NOT NULL,
  notes VARCHAR(255),
  CONSTRAINT fk_match_team_a FOREIGN KEY (team_a_id) REFERENCES teams(id),
  CONSTRAINT fk_match_team_b FOREIGN KEY (team_b_id) REFERENCES teams(id),
  INDEX idx_played_at (played_at)
) ENGINE=InnoDB;
