-- Warstwa zdarzeń mobilnych i załączników.
-- Brak FK jest celowy do czasu poznania istniejącego schematu produkcyjnego.

CREATE TABLE IF NOT EXISTS delegation_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  delegation_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(40) NOT NULL,
  occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  odometer_km INT UNSIGNED NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  ip_address VARCHAR(45) NULL,
  metadata_json JSON NULL,
  INDEX idx_delegation_events_delegation (delegation_id, occurred_at),
  INDEX idx_delegation_events_user (user_id, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS expense_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  expense_id BIGINT UNSIGNED NULL,
  delegation_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NULL,
  stored_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  size_bytes BIGINT UNSIGNED NOT NULL,
  sha256 CHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_expense_attachments_delegation (delegation_id),
  INDEX idx_expense_attachments_expense (expense_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
