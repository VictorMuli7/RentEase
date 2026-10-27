-- RentEase schema (Deliverable 2.6). MySQL 8, InnoDB, utf8mb4
CREATE DATABASE IF NOT EXISTS rentease_db CHARACTER SET utf8mb4;
USE rentease_db;

CREATE TABLE roles (
  role_id INT AUTO_INCREMENT PRIMARY KEY,
  role_name VARCHAR(30) NOT NULL UNIQUE,
  description VARCHAR(150)
) ENGINE=InnoDB;

CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  role_id INT NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  phone VARCHAR(15) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  is_verified BOOLEAN DEFAULT 0,
  status ENUM('active','suspended') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(role_id)
) ENGINE=InnoDB;

CREATE TABLE properties (
  property_id INT AUTO_INCREMENT PRIMARY KEY,
  owner_id INT NOT NULL,
  manager_id INT NULL,
  name VARCHAR(100) NOT NULL,
  location VARCHAR(150) NOT NULL,
  house_type ENUM('apartment','bungalow','maisonette') NOT NULL,
  landmarks VARCHAR(255),
  status ENUM('active','inactive') DEFAULT 'active',
  FOREIGN KEY (owner_id) REFERENCES users(user_id),
  FOREIGN KEY (manager_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE units (
  unit_id INT AUTO_INCREMENT PRIMARY KEY,
  property_id INT NOT NULL,
  unit_label VARCHAR(20) NOT NULL,
  bedrooms TINYINT NOT NULL,
  rent_amount DECIMAL(10,2) NOT NULL CHECK (rent_amount > 0),
  deposit_amount DECIMAL(10,2) NOT NULL,
  available_from DATE,
  floor_plan_url VARCHAR(255),
  status ENUM('vacant','occupied') DEFAULT 'vacant',
  UNIQUE (property_id, unit_label),
  FOREIGN KEY (property_id) REFERENCES properties(property_id)
) ENGINE=InnoDB;

CREATE TABLE unit_photos (
  photo_id INT AUTO_INCREMENT PRIMARY KEY,
  unit_id INT NOT NULL,
  photo_url VARCHAR(255) NOT NULL,
  FOREIGN KEY (unit_id) REFERENCES units(unit_id)
) ENGINE=InnoDB;

CREATE TABLE amenities (
  amenity_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE unit_amenities (
  unit_id INT NOT NULL,
  amenity_id INT NOT NULL,
  PRIMARY KEY (unit_id, amenity_id),
  FOREIGN KEY (unit_id) REFERENCES units(unit_id),
  FOREIGN KEY (amenity_id) REFERENCES amenities(amenity_id)
) ENGINE=InnoDB;

CREATE TABLE applications (
  application_id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  unit_id INT NOT NULL,
  employer VARCHAR(100),
  monthly_income DECIMAL(10,2),
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  reviewed_by INT NULL,
  review_comment VARCHAR(255),
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES users(user_id),
  FOREIGN KEY (unit_id) REFERENCES units(unit_id),
  FOREIGN KEY (reviewed_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE application_documents (
  document_id INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL,
  doc_type ENUM('national_id','proof_of_income','other') NOT NULL,
  file_url VARCHAR(255) NOT NULL,
  FOREIGN KEY (application_id) REFERENCES applications(application_id)
) ENGINE=InnoDB;

CREATE TABLE leases (
  lease_id INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL UNIQUE,
  unit_id INT NOT NULL,
  tenant_id INT NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  monthly_rent DECIMAL(10,2) NOT NULL,
  deposit_paid DECIMAL(10,2) DEFAULT 0,
  status ENUM('active','renewal_requested','termination_requested','expired','terminated') DEFAULT 'active',
  document_url VARCHAR(255),
  CHECK (end_date > start_date),
  FOREIGN KEY (application_id) REFERENCES applications(application_id),
  FOREIGN KEY (unit_id) REFERENCES units(unit_id),
  FOREIGN KEY (tenant_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE payments (
  payment_id INT AUTO_INCREMENT PRIMARY KEY,
  lease_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL CHECK (amount > 0),
  method ENUM('mpesa','card','bank','cash','cheque') NOT NULL,
  reference_no VARCHAR(40) NOT NULL UNIQUE,
  period_month DATE NOT NULL,
  status ENUM('pending','completed','failed') DEFAULT 'pending',
  recorded_by INT NULL,
  paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (lease_id) REFERENCES leases(lease_id),
  FOREIGN KEY (recorded_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE vendors (
  vendor_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  phone VARCHAR(15) NOT NULL,
  specialty VARCHAR(50),
  rating DECIMAL(2,1) CHECK (rating BETWEEN 0 AND 5)
) ENGINE=InnoDB;

CREATE TABLE maintenance_requests (
  request_id INT AUTO_INCREMENT PRIMARY KEY,
  unit_id INT NOT NULL,
  tenant_id INT NOT NULL,
  vendor_id INT NULL,
  category ENUM('plumbing','electrical','structural','other') NOT NULL,
  description TEXT NOT NULL,
  photo_url VARCHAR(255),
  status ENUM('open','assigned','in_progress','closed') DEFAULT 'open',
  cost DECIMAL(10,2) NULL,
  resolution_notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  closed_at TIMESTAMP NULL,
  FOREIGN KEY (unit_id) REFERENCES units(unit_id),
  FOREIGN KEY (tenant_id) REFERENCES users(user_id),
  FOREIGN KEY (vendor_id) REFERENCES vendors(vendor_id)
) ENGINE=InnoDB;

CREATE TABLE notifications (
  notification_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  channel ENUM('sms','email','in_app') NOT NULL,
  message VARCHAR(255) NOT NULL,
  is_read BOOLEAN DEFAULT 0,
  sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE reviews (
  review_id INT AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT NOT NULL,
  property_id INT NOT NULL,
  rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comment VARCHAR(500),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tenant_id) REFERENCES users(user_id),
  FOREIGN KEY (property_id) REFERENCES properties(property_id)
) ENGINE=InnoDB;

CREATE TABLE disputes (
  dispute_id INT AUTO_INCREMENT PRIMARY KEY,
  lease_id INT NOT NULL,
  raised_by INT NOT NULL,
  description TEXT NOT NULL,
  status ENUM('open','under_review','resolved') DEFAULT 'open',
  resolution TEXT,
  resolved_by INT NULL,
  FOREIGN KEY (lease_id) REFERENCES leases(lease_id),
  FOREIGN KEY (raised_by) REFERENCES users(user_id),
  FOREIGN KEY (resolved_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  log_id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(50) NOT NULL,
  table_name VARCHAR(50),
  record_id INT,
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB;

CREATE TABLE system_settings (
  setting_key VARCHAR(50) PRIMARY KEY,
  setting_value VARCHAR(100) NOT NULL,
  updated_by INT,
  FOREIGN KEY (updated_by) REFERENCES users(user_id)
) ENGINE=InnoDB;

INSERT INTO roles (role_name, description) VALUES
 ('tenant','Searches, applies, pays rent, reports issues'),
 ('manager','Runs listings, applications, leases, tickets'),
 ('landlord','Owns properties, views income and occupancy'),
 ('admin','Manages users, settings, disputes, audit logs');
INSERT INTO system_settings (setting_key, setting_value) VALUES
 ('late_fee_percent','5'), ('grace_period_days','5'), ('major_expense_threshold','20000');



CREATE TABLE IF NOT EXISTS simulated_payments (
    simulated_payment_id INT AUTO_INCREMENT PRIMARY KEY,
    lease_id INT NOT NULL,
    tenant_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    method ENUM('mpesa', 'card') NOT NULL,
    period_month DATE NOT NULL,
    reference_no VARCHAR(40) NOT NULL UNIQUE,
    status ENUM('pending', 'completed', 'failed')
        NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_simulated_lease (lease_id),
    INDEX idx_simulated_tenant (tenant_id)
);