USE foosball;

ALTER TABLE matches
  ADD COLUMN status ENUM('in_progress','finished') NOT NULL DEFAULT 'in_progress',
  ADD COLUMN finished_at DATETIME NULL;
