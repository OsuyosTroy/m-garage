-- Run once against an existing M-Garage database to associate each used part
-- with the mechanic who used it. Existing entries remain valid with NULL.
ALTER TABLE repair_parts_used
  ADD COLUMN mechanic_id INT UNSIGNED NULL AFTER item_id,
  ADD KEY idx_parts_mechanic (mechanic_id),
  ADD CONSTRAINT fk_parts_mechanic
    FOREIGN KEY (mechanic_id) REFERENCES mechanics(id)
    ON UPDATE CASCADE ON DELETE SET NULL;
