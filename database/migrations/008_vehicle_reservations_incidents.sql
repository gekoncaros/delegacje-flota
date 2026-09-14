-- Rezerwacje pojazdów + pełny lifecycle usterek.

CREATE TABLE IF NOT EXISTS vehicle_reservations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vehicle_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  delegation_id BIGINT UNSIGNED NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  purpose VARCHAR(255) NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'active',
  created_by BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  cancelled_at DATETIME NULL,
  cancelled_by BIGINT UNSIGNED NULL,
  INDEX idx_vehicle_reservation_window (vehicle_id, starts_at, ends_at, status),
  INDEX idx_vehicle_reservation_user (user_id, status),
  INDEX idx_vehicle_reservation_delegation (delegation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE vehicle_incidents
  ADD COLUMN assigned_to_user_id BIGINT UNSIGNED NULL AFTER status,
  ADD COLUMN resolution_note TEXT NULL AFTER assigned_to_user_id,
  ADD COLUMN resolved_by BIGINT UNSIGNED NULL AFTER resolution_note,
  ADD COLUMN updated_at DATETIME NULL AFTER resolved_at;

CREATE INDEX idx_vehicle_incidents_assigned_status
  ON vehicle_incidents (assigned_to_user_id, status);
