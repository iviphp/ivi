-- -----------------------------------------------------------------------------
-- Migration: Add initial indexes for performance
-- -----------------------------------------------------------------------------
-- Purpose:
--   - Add common lookup indexes on categories and shops
-- -----------------------------------------------------------------------------

ALTER TABLE market_categories
  ADD INDEX idx_market_categories_active (is_active),
  ADD INDEX idx_market_categories_slug   (slug);

ALTER TABLE market_shops
  ADD INDEX idx_market_shops_active (is_active),
  ADD INDEX idx_market_shops_owner  (owner_id);
