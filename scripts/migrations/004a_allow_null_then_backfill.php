-- 004a_allow_null_then_backfill.sql
ALTER TABLE users
MODIFY COLUMN password VARCHAR(255) NULL DEFAULT NULL AFTER email;

-- Met un hash par défaut pour les lignes vides
UPDATE users
SET password = '$2y$10$HZXlqZkqG0b6Tt8y0vTzUe0m0bV3F3iQwEJ1rO1P8WZqv2kY8Qe5i'
WHERE password IS NULL OR password = '';

-- Repasser en NOT NULL sans default
ALTER TABLE users
MODIFY COLUMN password VARCHAR(255) NOT NULL AFTER email;