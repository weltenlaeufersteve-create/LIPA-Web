-- LIPA Web schema. Portable across MySQL 8.4 and MariaDB.
SET sql_mode = 'STRICT_ALL_TABLES';

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','editor','viewer') NOT NULL DEFAULT 'viewer',
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS contacts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('donor','vendor') NOT NULL,
  name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NULL,
  phone VARCHAR(60) NULL,
  address TEXT NULL,
  notes TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS projects (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  code VARCHAR(40) NULL,
  description TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('income','expense') NOT NULL,
  name VARCHAR(120) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  type ENUM('bank','cash','other') NOT NULL DEFAULT 'bank',
  opening_balance DECIMAL(15,2) NOT NULL DEFAULT 0,
  opening_balance_date DATE NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  project_id INT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_activity_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
  CONSTRAINT fk_activity_creator FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity_photos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  activity_id INT NOT NULL,
  filename VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_photo_activity FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS income (
  id INT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  contact_id INT NULL,
  project_id INT NULL,
  category_id INT NULL,
  account_id INT NULL,
  description VARCHAR(255) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'TZS',
  amount_original DECIMAL(15,2) NOT NULL DEFAULT 0,
  exchange_rate DECIMAL(15,6) NOT NULL DEFAULT 1,
  amount_tzs DECIMAL(15,2) NOT NULL DEFAULT 0,
  reference VARCHAR(120) NULL,
  receipt_path VARCHAR(255) NULL,
  notes TEXT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_income_contact  FOREIGN KEY (contact_id)  REFERENCES contacts(id)  ON DELETE SET NULL,
  CONSTRAINT fk_income_project  FOREIGN KEY (project_id)  REFERENCES projects(id)  ON DELETE SET NULL,
  CONSTRAINT fk_income_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_income_account  FOREIGN KEY (account_id)  REFERENCES accounts(id)   ON DELETE SET NULL,
  CONSTRAINT fk_income_user     FOREIGN KEY (created_by)  REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS expenses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  contact_id INT NULL,
  project_id INT NULL,
  category_id INT NULL,
  account_id INT NULL,
  activity_id INT NULL,
  description VARCHAR(255) NULL,
  amount_tzs DECIMAL(15,2) NOT NULL DEFAULT 0,
  reference VARCHAR(120) NULL,
  receipt_path VARCHAR(255) NULL,
  notes TEXT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_expense_contact  FOREIGN KEY (contact_id)  REFERENCES contacts(id)  ON DELETE SET NULL,
  CONSTRAINT fk_expense_project  FOREIGN KEY (project_id)  REFERENCES projects(id)  ON DELETE SET NULL,
  CONSTRAINT fk_expense_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_expense_account  FOREIGN KEY (account_id)  REFERENCES accounts(id)   ON DELETE SET NULL,
  CONSTRAINT fk_expense_activity FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE SET NULL,
  CONSTRAINT fk_expense_user     FOREIGN KEY (created_by)  REFERENCES users(id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transfers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  from_account_id INT NULL,
  to_account_id INT NULL,
  amount_tzs DECIMAL(15,2) NOT NULL DEFAULT 0,
  description VARCHAR(255) NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_transfer_from FOREIGN KEY (from_account_id) REFERENCES accounts(id) ON DELETE SET NULL,
  CONSTRAINT fk_transfer_to   FOREIGN KEY (to_account_id)   REFERENCES accounts(id) ON DELETE SET NULL,
  CONSTRAINT fk_transfer_user FOREIGN KEY (created_by)      REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(60) PRIMARY KEY,
  setting_value TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(40) NOT NULL,
  entity_type VARCHAR(40) NULL,
  entity_id INT NULL,
  description VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
