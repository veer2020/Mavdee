-- database/migrations/001_delhivery.sql
-- Run once to set up all Delhivery-related database objects and settings.
-- ── orders table columns ────────────────────────────────────────────────────
ALTER TABLE orders
ADD COLUMN IF NOT EXISTS return_waybill VARCHAR(64) DEFAULT NULL
AFTER tracking_number;
ALTER TABLE orders
ADD COLUMN IF NOT EXISTS courier_mode VARCHAR(32) DEFAULT NULL
AFTER courier;
-- ── Webhook event log ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS delhivery_webhook_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  waybill VARCHAR(64) NOT NULL,
  order_id INT UNSIGNED DEFAULT NULL,
  status_code VARCHAR(16) NOT NULL,
  status_text VARCHAR(255) NOT NULL DEFAULT '',
  location VARCHAR(255) NOT NULL DEFAULT '',
  raw_payload MEDIUMTEXT NOT NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_waybill (waybill),
  KEY idx_order_id (order_id),
  KEY idx_received (received_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ── Pre-fetched waybill pool ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS delhivery_waybill_pool (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  waybill VARCHAR(64) NOT NULL,
  is_used TINYINT(1) NOT NULL DEFAULT 0,
  order_id INT UNSIGNED DEFAULT NULL,
  fetched_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_waybill (waybill),
  KEY idx_is_used (is_used)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ── NDR (Non-Delivery Report) tracking ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS delhivery_ndr (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  waybill VARCHAR(64) NOT NULL,
  order_id INT UNSIGNED DEFAULT NULL,
  action VARCHAR(64) NOT NULL,
  remarks VARCHAR(512) NOT NULL DEFAULT '',
  acted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_waybill (waybill),
  KEY idx_order_id (order_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ── Pincode serviceability cache ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pincode_cache (
  pin CHAR(6) NOT NULL,
  serviceable TINYINT(1) NOT NULL DEFAULT 0,
  cod TINYINT(1) NOT NULL DEFAULT 0,
  prepaid TINYINT(1) NOT NULL DEFAULT 0,
  city VARCHAR(128) NOT NULL DEFAULT '',
  state VARCHAR(128) NOT NULL DEFAULT '',
  cached_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (pin),
  KEY idx_cached_at (cached_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;
-- ── Delhivery settings seed ──────────────────────────────────────────────────
INSERT IGNORE INTO settings (`key`, `value`)
VALUES ('delhivery_enabled', '0'),
  ('delhivery_token', ''),
  ('delhivery_client_name', 'Mavdee'),
  ('delhivery_warehouse_pin', ''),
  ('free_shipping_above', '999'),
  ('default_shipping_cost', '49'),
  ('cod_extra_fee', '30');