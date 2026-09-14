-- Authentication and RBAC foundation.
-- Review before production migration and map existing users if present.

CREATE TABLE IF NOT EXISTS auth_users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  display_name VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  manager_user_id BIGINT UNSIGNED NULL,
  failed_login_count INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_login_at DATETIME NULL,
  password_changed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_auth_users_email (email),
  INDEX idx_auth_users_manager (manager_user_id),
  INDEX idx_auth_users_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS auth_login_audit (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  email_attempted VARCHAR(190) NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_auth_login_user (user_id, created_at),
  INDEX idx_auth_login_email (email_attempted, created_at),
  INDEX idx_auth_login_success (success, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Existing table user_role_assignments from 004_system_configuration.sql
-- is reused for role membership.
