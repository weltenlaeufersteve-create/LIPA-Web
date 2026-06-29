<?php
require __DIR__ . '/../vendor/autoload.php';

// Load test env if present, else fall back to .env
$root = dirname(__DIR__);
$file = is_file($root . '/.env.testing') ? '.env.testing' : '.env';
$dotenv = Dotenv\Dotenv::createImmutable($root, $file);
$dotenv->safeLoad();
