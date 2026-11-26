<?php
require_once __DIR__ . '/config/database.php';

$database = new Database();
$db = $database->getConnection();
$sql = file_get_contents(__DIR__ . '/database_auth_migration.sql');

try {
    $db->exec($sql);
    echo 'Auth migration successful.';
} catch (PDOException $exception) {
    http_response_code(500);
    echo 'Auth migration failed: ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
}
