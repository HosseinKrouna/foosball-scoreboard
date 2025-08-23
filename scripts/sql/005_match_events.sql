USE foosball;

CREATE TABLE IF NOT EXISTS match_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  match_id INT NOT NULL,
  team ENUM('A','B') NOT NULL,
  delta TINYINT NOT NULL,     -- gewünschte Änderung (+1 / -1)
  applied TINYINT NOT NULL,   -- tatsächlich angewendet (+1 / 0 / -1)
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (match_id),
  CONSTRAINT fk_events_match FOREIGN KEY (match_id)
    REFERENCES matches(id)
    ON DELETE CASCADE
) ENGINE=InnoDB;
