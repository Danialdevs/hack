<?php

require 'rb.php';

// Load .env
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') !== false) {
            [$key, $val] = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($val);
        }
    }
}

$dbType = $_ENV['DB_TYPE'] ?? 'sqlite';

if ($dbType === 'mysql') {
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $name = $_ENV['DB_NAME'] ?? 'hack';
    $user = $_ENV['DB_USER'] ?? 'root';
    $pass = $_ENV['DB_PASS'] ?? '';
    R::setup("mysql:host=$host;dbname=$name", $user, $pass);
} else {
    $dbPath = __DIR__ . '/../' . ($_ENV['DB_PATH'] ?? 'hack.db');
    R::setup('sqlite:' . $dbPath);
}

R::freeze(false);

