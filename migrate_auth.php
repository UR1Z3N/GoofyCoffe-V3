<?php
require_once __DIR__ . '/config/database.php';

$database = new Database();
$db = $database->getConnection();
$sql = file_get_contents(__DIR__ . '/database_auth_migration.sql');

try {
    $db->exec($sql);
    echo 'Auth migration successful.';
} catch (PDOException $exception) {
    error_log('Auth migration failed: ' . $exception->getMessage());
    http_response_code(500);
    echo 'Auth migration failed. Check the PHP error log for details.';
}
