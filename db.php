<?php
// Shared database connection. Every page that needs the database does:
//   require 'db.php';
// and then has a ready-to-use $pdo object.
//
// Using 127.0.0.1 instead of 'localhost' on purpose: 'localhost' can
// make PHP connect via a Unix socket file, which may silently hit a
// different MySQL install than the one phpMyAdmin is actually using.
// 127.0.0.1 forces a real TCP connection to the exact address/port
// shown in phpMyAdmin.

$host    = '127.0.0.1';
$port    = '3307';      // matches the port shown in your phpMyAdmin
$db      = 'clearframe_studio_v2';
$user    = 'root';
$pass    = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // In production you'd log this instead of showing it to visitors.
    die('Database connection failed: ' . $e->getMessage() .
        '<br>Check that MySQL is running, the port above is correct, ' .
        'and that your username/password match what phpMyAdmin uses.');
}
