-- System configuration for reusable company deployments.
-- Run only after reviewing the current production schema.

CREATE TABLE IF NOT EXISTS system_settings (
  setting_key VARCHAR(120) PRIMARY KEY,
  setting_group VARCHAR(80) NOT NULL,
  setting_value JSON NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_system_settings_group (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_role_assignments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  role_code VARCHAR(50) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_user_role (user_id, role_code),
  INDEX idx_user_roles_active (role_code, active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notification_routes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  route_code VARCHAR(80) NOT NULL,
  destination_type VARCHAR(40) NOT NULL DEFAULT 'email',
  destination_value VARCHAR(255) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_notification_route (route_code, destination_type, destination_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Suggested role codes:
-- employee, manager, accounting, fleet_admin, super_admin
