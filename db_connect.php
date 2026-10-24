<?php
$host = getenv('RENTEASE_DB_HOST') ?: '127.0.0.1';
$port = (int) (getenv('RENTEASE_DB_PORT') ?: 3307);
$user = getenv('RENTEASE_DB_USER') ?: 'root';
$password = getenv('RENTEASE_DB_PASSWORD') ?: '';
$dbname = getenv('RENTEASE_DB_NAME') ?: 'rentease_db';

$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
