<?php
require_once __DIR__ . '/interfaces/OrderRepositoryInterface.php';

class Order implements OrderRepositoryInterface {
    private $conn;
    private $table_name = "orders";

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    public function createOrder($total_amount, $payment_method_id, $items) {
        try {
            $this->conn->beginTransaction();

            $order_no = "ORD-" . date('YmdHis') . "-" . rand(100, 999);

            // Validasi payment method dan petakan ke kolom ENUM lama selama masa transisi.
            $stmtPayment = $this->conn->prepare(
                "SELECT id, code, name FROM payment_methods WHERE id = :id AND is_active = 1 LIMIT 1"
            );
            $stmtPayment->bindValue(":id", (int) $payment_method_id, PDO::PARAM_INT);
            $stmtPayment->execute();
            $paymentMethod = $stmtPayment->fetch(PDO::FETCH_ASSOC);

            if (!$paymentMethod) {
                throw new Exception("Metode pembayaran tidak valid atau sedang nonaktif.");
            }

            // Kolom payment_method lama hanya mengenal Tunai/Digital.
            // payment_method_id menjadi sumber data utama untuk metode pembayaran baru.
            $legacyPaymentMethod = ($paymentMethod['code'] === 'CASH') ? 'Tunai' : 'Digital';

            $query = "INSERT INTO " . $this->table_name . "
                      (order_no, total_amount, payment_method, payment_method_id)
                      VALUES (:order_no, :total_amount, :legacy_payment_method, :payment_method_id)";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":order_no", $order_no);
            $stmt->bindParam(":total_amount", $total_amount);
            $stmt->bindParam(":legacy_payment_method", $legacyPaymentMethod);
            $stmt->bindValue(":payment_method_id", (int) $payment_method_id, PDO::PARAM_INT);
            $stmt->execute();

            $order_id = $this->conn->lastInsertId();

            // Tugas memasukkan order (pesanan) ke order_item
            $queryItem = "INSERT INTO order_items
                          (order_id, menu_id, quantity, price, notes)
                          VALUES (:order_id, :menu_id, :quantity, :price, :notes)";
            $stmtItem = $this->conn->prepare($queryItem);

            foreach ($items as $item) {
                $stmtItem->bindParam(":order_id", $order_id);
                $stmtItem->bindParam(":menu_id", $item['menu_id']);
                $stmtItem->bindParam(":quantity", $item['quantity']);
                $stmtItem->bindParam(":price", $item['price']);
                $stmtItem->bindParam(":notes", $item['notes']);
                $stmtItem->execute();
            }

            $this->conn->commit();
            return ["success" => true, "order_no" => $order_no];

        } catch (Exception $e) {
            $this->conn->rollBack();
            return ["success" => false, "message" => $e->getMessage()];
        }
    }

    public function getOrdersHistory($start_date = null, $end_date = null) {
        $query = "SELECT o.*, pm.name as payment_method_name
                  FROM " . $this->table_name . " o
                  LEFT JOIN payment_methods pm ON o.payment_method_id = pm.id
                  WHERE o.status = 'Selesai'";

        if ($start_date && $end_date) {
            $query .= " AND DATE(o.created_at) BETWEEN :start_date AND :end_date";
        }

        $query .= " ORDER BY o.created_at DESC";

        $stmt = $this->conn->prepare($query);

        if ($start_date && $end_date) {
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
        }

        $stmt->execute();
        return $stmt;
    }

    public function getOrderDetails($order_id) {
        $query = "SELECT oi.*, m.name as menu_name
                  FROM order_items oi
                  JOIN menus m ON oi.menu_id = m.id
                  WHERE oi.order_id = :order_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->execute();

        return $stmt;
    }
}
?>
