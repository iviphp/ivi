-- -----------------------------------------------------------------------------
-- Seeder: Market/Core base data (SQL)
-- -----------------------------------------------------------------------------
-- Inserts:
--   - Default settings
--   - Top-level categories
--   - Example shops
-- -----------------------------------------------------------------------------

INSERT INTO market_settings (`key`, `value`) VALUES
  ('market.title', JSON_OBJECT('text', 'Softadastra Market'))
ON DUPLICATE KEY UPDATE `value`=VALUES(`value`);

INSERT INTO market_categories (parent_id, slug, `name`, is_active)
VALUES
  (NULL, 'mobile-phones', 'Mobile Phones & Tablets', 1),
  (NULL, 'fashion',       'Fashion & Apparel',       1),
  (NULL, 'electronics',   'Electronics',             1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), is_active=VALUES(is_active);

INSERT INTO market_shops (owner_id, slug, `name`, country, city, is_active)
VALUES
  (1, 'alpha-traders',  'Alpha Traders',  'Uganda', 'Kampala', 1),
  (2, 'kivu-boutique',  'Kivu Boutique',  'DRC',    'Goma',    1)
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), is_active=VALUES(is_active);
