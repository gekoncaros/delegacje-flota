CREATE TABLE IF NOT EXISTS activity_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  object_type VARCHAR(80) NULL,
  object_id BIGINT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  user_agent VARCHAR(500) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_activity_log_user (user_id, created_at),
  INDEX idx_activity_log_object (object_type, object_id, created_at),
  INDEX idx_activity_log_action (action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
