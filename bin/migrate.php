<?php
// Idempotent migration for the accounts feature. Safe to run repeatedly.
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

use App\Database;

$pdo = Database::pdo();

function columnExists(\PDO $pdo, string $table, string $col): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
    );
    $st->execute([':t' => $table, ':c' => $col]);
    return (bool) $st->fetchColumn();
}

$pdo->exec("CREATE TABLE IF NOT EXISTS accounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  type ENUM('bank','cash','other') NOT NULL DEFAULT 'bank',
  opening_balance DECIMAL(15,2) NOT NULL DEFAULT 0,
  opening_balance_date DATE NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "accounts table ok\n";

$pdo->exec("CREATE TABLE IF NOT EXISTS transfers (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "transfers table ok\n";

if (!columnExists($pdo, 'income', 'account_id')) {
    $pdo->exec('ALTER TABLE income ADD COLUMN account_id INT NULL,
        ADD CONSTRAINT fk_income_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL');
    echo "income.account_id added\n";
} else { echo "income.account_id exists\n"; }

if (!columnExists($pdo, 'expenses', 'account_id')) {
    $pdo->exec('ALTER TABLE expenses ADD COLUMN account_id INT NULL,
        ADD CONSTRAINT fk_expense_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE SET NULL');
    echo "expenses.account_id added\n";
} else { echo "expenses.account_id exists\n"; }

// Seed default accounts if none exist.
$count = (int) $pdo->query('SELECT COUNT(*) FROM accounts')->fetchColumn();
if ($count === 0) {
    $pdo->exec("INSERT INTO accounts (name, type, opening_balance, active) VALUES
        ('Bank — TZS main', 'bank', 0, 1),
        ('Petty cash', 'cash', 0, 1)");
    echo "seeded 2 accounts\n";
}

// Backfill existing rows to the Bank account.
$bankId = (int) $pdo->query("SELECT id FROM accounts ORDER BY id ASC LIMIT 1")->fetchColumn();
if ($bankId > 0) {
    $pdo->exec("UPDATE income   SET account_id = {$bankId} WHERE account_id IS NULL");
    $pdo->exec("UPDATE expenses SET account_id = {$bankId} WHERE account_id IS NULL");
    echo "backfilled income/expenses to account #{$bankId}\n";
}
$pdo->exec("CREATE TABLE IF NOT EXISTS activities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  date DATE NOT NULL,
  title VARCHAR(190) NOT NULL,
  description TEXT NULL,
  project_id INT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_activity_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE SET NULL,
  CONSTRAINT fk_activity_creator FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "activities table ok\n";

$pdo->exec("CREATE TABLE IF NOT EXISTS activity_photos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  activity_id INT NOT NULL,
  filename VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_photo_activity FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
echo "activity_photos table ok\n";

if (!columnExists($pdo, 'expenses', 'activity_id')) {
    $pdo->exec('ALTER TABLE expenses ADD COLUMN activity_id INT NULL,
        ADD CONSTRAINT fk_expense_activity FOREIGN KEY (activity_id) REFERENCES activities(id) ON DELETE SET NULL');
    echo "expenses.activity_id added\n";
} else { echo "expenses.activity_id exists\n"; }

echo "migration complete\n";
