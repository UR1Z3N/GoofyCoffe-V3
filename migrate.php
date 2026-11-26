<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$sql = file_get_contents('database_migration.sql');

try {
    $db->exec($sql);
    echo "Migration successful.";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage();
}
