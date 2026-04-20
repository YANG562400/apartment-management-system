
CREATE DATABASE IF NOT EXISTS arnaut;
USE arnaut;

-- Table: buildings
CREATE TABLE buildings (
  id int(11) NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL,
  address varchar(255) NOT NULL,
  is_archived tinyint(1) DEFAULT 0,
  created_at datetime DEFAULT current_timestamp(),
  updated_at datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: apartments
CREATE TABLE apartments (
  id int(11) NOT NULL AUTO_INCREMENT,
  building_id int(11) NOT NULL,
  unit_number varchar(50) NOT NULL,
  type varchar(50) NOT NULL,
  status enum('vacant','occupied') NOT NULL DEFAULT 'vacant',
  created_at datetime DEFAULT current_timestamp(),
  updated_at datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  is_archived tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY building_id (building_id),
  CONSTRAINT apartments_ibfk_1 FOREIGN KEY (building_id) REFERENCES buildings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: tenants
CREATE TABLE tenants (
  id varchar(50) NOT NULL,
  name varchar(100) NOT NULL,
  contact varchar(50) NOT NULL,
  apartment_id int(11) NOT NULL,
  move_in_date date NOT NULL,
  PRIMARY KEY (id),
  KEY apartment_id (apartment_id),
  CONSTRAINT tenants_ibfk_1 FOREIGN KEY (apartment_id) REFERENCES apartments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: maintenance
CREATE TABLE maintenance (
  id int(11) NOT NULL AUTO_INCREMENT,
  apartment_id int(11) NOT NULL,
  request_date date NOT NULL,
  description text NOT NULL,
  status enum('pending','in_progress','resolved') NOT NULL DEFAULT 'pending',
  PRIMARY KEY (id),
  KEY apartment_id (apartment_id),
  CONSTRAINT maintenance_ibfk_1 FOREIGN KEY (apartment_id) REFERENCES apartments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: payments
CREATE TABLE payments (
  id int(11) NOT NULL AUTO_INCREMENT,
  tenant_id varchar(50) NOT NULL,
  amount decimal(10,2) NOT NULL,
  payment_date date NOT NULL,
  remarks text DEFAULT NULL,
  PRIMARY KEY (id),
  KEY tenant_id (tenant_id),
  CONSTRAINT payments_ibfk_1 FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: users
CREATE TABLE users (
  id int(11) NOT NULL AUTO_INCREMENT,
  username varchar(50) NOT NULL,
  password varchar(255) NOT NULL,
  role enum('admin','staff') NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
