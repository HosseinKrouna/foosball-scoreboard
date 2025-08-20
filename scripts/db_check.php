<?php
require __DIR__ . '/../src/db.php';

try {
    db()->query('SELECT 1');
    echo "DB OK ✅\n";
} catch (Throwable $e) {
    echo "DB ERROR ❌: " . $e->getMessage() . "\n";
}