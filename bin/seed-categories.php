<?php
// Usage: php bin/seed-categories.php  — seeds starter categories if none exist.
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

use App\Database;
use App\Models\Category;

$count = (int) Database::pdo()->query('SELECT COUNT(*) FROM categories')->fetchColumn();
if ($count > 0) {
    echo "Categories already present ({$count}); nothing to do.\n";
    exit(0);
}

$income = ['Grants (Restricted)','Grants (Unrestricted)','Individual Donations','Corporate Donations',
    'Membership & Contributions','Bank/Interest Income','Other Income'];
$expense = ['Salaries & Wages','Staff Benefits','Office Rent','Utilities','Travel & Transport',
    'Programme/Project Costs','Training & Workshops','Office Supplies','Equipment',
    'Professional Fees (Audit/Legal)','Bank Charges','Communication','Repairs & Maintenance',
    'Fundraising Costs','Miscellaneous'];

$n = 0;
foreach ($income as $i => $name)  { Category::create(['type'=>'income','name'=>$name,'sort_order'=>$i+1]);  $n++; }
foreach ($expense as $i => $name) { Category::create(['type'=>'expense','name'=>$name,'sort_order'=>$i+1]); $n++; }
echo "Seeded {$n} categories.\n";
