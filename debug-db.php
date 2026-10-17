<?php
// debug-db.php — run this in your browser to see EXACTLY which
// server/database your actual PHP code is connecting to, and what
// tables it can see there. DELETE THIS FILE once you're done
// debugging — it's not meant to stay on the site.

require 'db.php'; // uses the exact same connection your whole app uses

echo '<pre>';

echo "Connected via db.php to:\n";
$info = $pdo->query("SELECT @@hostname AS host, @@port AS port, DATABASE() AS db, VERSION() AS version")->fetch();
print_r($info);

echo "\nTables visible in this connection:\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);

echo '</pre>';
