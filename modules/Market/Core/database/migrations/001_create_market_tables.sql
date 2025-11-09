-- -----------------------------------------------------------------------------
-- Migration: Create Market/Core base tables
-- -----------------------------------------------------------------------------
-- Purpose:
--   - Create core tables for the marketplace domain:
--     settings, categories, shops
-- Notes:
--   - Idempotent (uses IF NOT EXISTS)
--   - UTF8MB4 default charset for MySQL
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS market_settings (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key`         VARCHAR(120) NOT NULL UNIQUE,
  `value`       JSON         NULL,
  created_at    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS market_categories (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id     BIGINT UNSIGNED NULL,
  slug          VARCHAR(160) NOT NULL UNIQUE,
  `name`        VARCHAR(160) NOT NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_market_categories_parent
    FOREIGN KEY (parent_id) REFERENCES market_categories(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS market_shops (
  id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_id      BIGINT UNSIGNED NOT NULL,
  slug          VARCHAR(160) NOT NULL UNIQUE,
  `name`        VARCHAR(160) NOT NULL,
  country       VARCHAR(80)  NULL,
  city          VARCHAR(120) NULL,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
