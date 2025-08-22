USE foosball;

ALTER TABLE matches
  ADD COLUMN target_score INT NOT NULL DEFAULT 10 AFTER score_b;
