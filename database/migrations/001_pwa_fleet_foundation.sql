-- Fundament PWA/Flota.
-- Uruchamiać dopiero po weryfikacji schematu istniejącej bazy.

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(80) NOT NULL,
  title VARCHAR(255) NOT NULL,
  body TEXT NULL,
  object_type VARCHAR(80) NULL,
  object_id BIGINT UNSIGNED NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notifications_user_read (user_id, read_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vehicles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  make VARCHAR(100) NOT NULL,
  model VARCHAR(100) NOT NULL,
  registration_number VARCHAR(32) NOT NULL,
  vin VARCHAR(32) NULL,
  year SMALLINT UNSIGNED NULL,
  fuel_type VARCHAR(40) NULL,
  mileage_km INT UNSIGNED NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'available',
  assigned_user_id BIGINT UNSIGNED NULL,
  inspection_due DATE NULL,
  insurance_due DATE NULL,
  notes TEXT NULL,
  qr_token CHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  UNIQUE KEY uq_vehicle_registration (registration_number),
  UNIQUE KEY uq_vehicle_qr_token (qr_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vehicle_trips (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vehicle_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  delegation_id BIGINT UNSIGNED NULL,
  started_at DATETIME NOT NULL,
  ended_at DATETIME NULL,
  odometer_start INT UNSIGNED NULL,
  odometer_end INT UNSIGNED NULL,
  start_place VARCHAR(255) NULL,
  destination VARCHAR(255) NULL,
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_vehicle_trips_vehicle (vehicle_id),
  INDEX idx_vehicle_trips_user (user_id),
  INDEX idx_vehicle_trips_delegation (delegation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vehicle_fuelings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vehicle_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  fueled_at DATETIME NOT NULL,
  mileage_km INT UNSIGNED NULL,
  liters DECIMAL(10,3) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'PLN',
  fuel_type VARCHAR(40) NULL,
  station VARCHAR(160) NULL,
  document_number VARCHAR(100) NULL,
  attachment_path VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_vehicle_fuelings_vehicle (vehicle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vehicle_incidents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vehicle_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  category VARCHAR(50) NOT NULL,
  description TEXT NOT NULL,
  mileage_km INT UNSIGNED NULL,
  unsafe_to_drive TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(40) NOT NULL DEFAULT 'open',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  INDEX idx_vehicle_incidents_vehicle_status (vehicle_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
