-- Canonical delegation workflow used by the mobile/web application.
-- Uses auth_users as the identity source introduced in migration 005.

CREATE TABLE IF NOT EXISTS app_delegations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  number VARCHAR(40) NOT NULL UNIQUE,
  user_id BIGINT UNSIGNED NOT NULL,
  manager_id BIGINT UNSIGNED NULL,
  destination VARCHAR(255) NOT NULL,
  purpose TEXT NOT NULL,
  date_from DATE NOT NULL,
  date_to DATE NOT NULL,
  transport VARCHAR(50) NOT NULL DEFAULT 'company_car',
  status VARCHAR(40) NOT NULL DEFAULT 'pending',
  decision_note TEXT NULL,
  submitted_at DATETIME NULL,
  approved_at DATETIME NULL,
  rejected_at DATETIME NULL,
  started_at DATETIME NULL,
  finished_at DATETIME NULL,
  odometer_start INT UNSIGNED NULL,
  odometer_end INT UNSIGNED NULL,
  start_lat DECIMAL(10,7) NULL,
  start_lng DECIMAL(10,7) NULL,
  finish_lat DECIMAL(10,7) NULL,
  finish_lng DECIMAL(10,7) NULL,
  accounting_status VARCHAR(40) NOT NULL DEFAULT 'not_ready',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_app_delegations_user (user_id, created_at),
  INDEX idx_app_delegations_manager (manager_id, status, created_at),
  INDEX idx_app_delegations_accounting (accounting_status, finished_at),
  INDEX idx_app_delegations_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS app_delegation_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  delegation_id BIGINT UNSIGNED NOT NULL,
  actor_user_id BIGINT UNSIGNED NOT NULL,
  action_code VARCHAR(80) NOT NULL,
  from_status VARCHAR(40) NULL,
  to_status VARCHAR(40) NULL,
  note TEXT NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_delegation_history_delegation (delegation_id, created_at),
  INDEX idx_delegation_history_actor (actor_user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
