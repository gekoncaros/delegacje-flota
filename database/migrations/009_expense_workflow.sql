-- Koszty delegacji oraz ich bezpieczne załączniki.
-- Wykonać wyłącznie po weryfikacji na środowisku testowym.

CREATE TABLE IF NOT EXISTS app_expenses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  delegation_id BIGINT UNSIGNED NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  category VARCHAR(40) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'PLN',
  expense_date DATE NOT NULL,
  description TEXT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'submitted',
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  decision_note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_app_expenses_user (user_id, created_at),
  INDEX idx_app_expenses_delegation (delegation_id, created_at),
  INDEX idx_app_expenses_accounting (status, expense_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
