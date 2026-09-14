CREATE TABLE IF NOT EXISTS auth_invitations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  display_name VARCHAR(190) NOT NULL,
  role_code VARCHAR(50) NOT NULL DEFAULT 'employee',
  token_hash CHAR(64) NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_auth_invitation_token (token_hash),
  INDEX idx_auth_invitation_email (email, expires_at),
  INDEX idx_auth_invitation_active (used_at, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
