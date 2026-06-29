<?php
// Usage: php bin/create-admin.php "Name" email@org password
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
$root = dirname(__DIR__);
Dotenv\Dotenv::createImmutable($root)->safeLoad();

[$name, $email, $password] = [$argv[1] ?? '', $argv[2] ?? '', $argv[3] ?? ''];
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
    fwrite(STDERR, "Usage: php bin/create-admin.php \"Name\" email password(>=6)\n");
    exit(1);
}
$id = App\Models\User::create(['name'=>$name,'email'=>$email,'password'=>$password,'role'=>'admin']);
echo "Created admin user #{$id} ({$email})\n";
