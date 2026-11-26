<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // guna menambah code column pada metode payment jika belum ada
    $stmt = $db->query("SHOW COLUMNS FROM payment_methods LIKE 'code'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE payment_methods ADD COLUMN code VARCHAR(50) NULL AFTER id");
        $db->exec("ALTER TABLE payment_methods ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");

        // Update
        $db->exec("UPDATE payment_methods SET code = 'CASH' WHERE name = 'Tunai'");
        $db->exec("UPDATE payment_methods SET code = 'QRIS' WHERE name = 'QRIS'");
        $db->exec("UPDATE payment_methods SET code = 'BANK_TRANSFER' WHERE name = 'Transfer Bank'");
        $db->exec("UPDATE payment_methods SET code = 'GOJEK' WHERE name = 'Gojek'");
        $db->exec("UPDATE payment_methods SET code = 'LEGACY_DIGITAL' WHERE name = 'Digital (Legacy)'");

        //
        $db->exec("ALTER TABLE payment_methods MODIFY COLUMN code VARCHAR(50) NOT NULL UNIQUE");
    }

    echo "Migration V2 successful.";
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage();
}
