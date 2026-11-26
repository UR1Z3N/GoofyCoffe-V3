<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../auth.php';
require_login(true);
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$type = $_GET['type'] ?? 'report';

// ─── REPORT: daily charts ────────────────────────────────────────────────────
if ($type === 'report') {
    $date = $_GET['date'] ?? date('Y-m-d');

    // Hourly revenue breakdown
    $stmtHourly = $db->prepare(
        "SELECT HOUR(created_at) AS hour, SUM(total_amount) AS revenue, COUNT(id) AS orders
         FROM orders
         WHERE DATE(created_at) = :date AND status = 'Selesai'
         GROUP BY HOUR(created_at)
         ORDER BY hour"
    );
    $stmtHourly->execute([':date' => $date]);
    $hourlyRows = $stmtHourly->fetchAll(PDO::FETCH_ASSOC);

    $hourlyLabels = [];
    $hourlyRevenue = [];
    $hourlyOrders  = [];
    foreach ($hourlyRows as $r) {
        $h = (int)$r['hour'];
        $hourlyLabels[]  = sprintf('%02d:00', $h);
        $hourlyRevenue[] = (float)$r['revenue'];
        $hourlyOrders[]  = (int)$r['orders'];
    }

    // Top 8 selling menu items
    $stmtTop = $db->prepare(
        "SELECT m.name, SUM(oi.quantity) AS qty, SUM(oi.quantity * oi.price) AS revenue
         FROM order_items oi
         JOIN menus m ON oi.menu_id = m.id
         JOIN orders o ON oi.order_id = o.id
         WHERE DATE(o.created_at) = :date AND o.status = 'Selesai'
         GROUP BY m.id, m.name
         ORDER BY qty DESC
         LIMIT 8"
    );
    $stmtTop->execute([':date' => $date]);
    $topRows = $stmtTop->fetchAll(PDO::FETCH_ASSOC);

    $topLabels  = array_column($topRows, 'name');
    $topQty     = array_map('intval', array_column($topRows, 'qty'));
    $topRevenue = array_map('floatval', array_column($topRows, 'revenue'));

    // Payment breakdown
    $stmtPay = $db->prepare(
        "SELECT pm.name AS method, COUNT(o.id) AS cnt, SUM(o.total_amount) AS total
         FROM orders o
         LEFT JOIN payment_methods pm ON o.payment_method_id = pm.id
         WHERE DATE(o.created_at) = :date AND o.status = 'Selesai'
         GROUP BY pm.id, pm.name"
    );
    $stmtPay->execute([':date' => $date]);
    $payRows = $stmtPay->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'       => true,
        'hourlyLabels'  => $hourlyLabels,
        'hourlyRevenue' => $hourlyRevenue,
        'hourlyOrders'  => $hourlyOrders,
        'topLabels'     => $topLabels,
        'topQty'        => $topQty,
        'topRevenue'    => $topRevenue,
        'paymentRows'   => $payRows,
    ]);
}

?>
