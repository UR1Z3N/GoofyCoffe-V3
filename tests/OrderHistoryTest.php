<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/Order.php';

final class FakeDatabaseConnection extends PDO {
    public string $preparedQuery = '';
    public SpyPdoStatement $statement;

    public function __construct() {
        $this->statement = new SpyPdoStatement();
    }

    public function prepare(string $query, array $options = []): PDOStatement|false {
        $this->preparedQuery = $query;
        return $this->statement;
    }
}

final class SpyPdoStatement extends PDOStatement {
    public array $boundParameters = [];
    public int $executeCalls = 0;

    public function __construct() {}

    public function bindParam(string|int $parameter, mixed &$variable, int $dataType = PDO::PARAM_STR, int $length = 0, mixed $driverOptions = null): bool {
        $this->boundParameters[$parameter] = $variable;
        return true;
    }

    public function execute(?array $parameters = null): bool {
        $this->executeCalls++;
        return true;
    }
}

final class OrderHistoryTest extends TestCase {
    public function testAppliesDateRangeAndBindsBothDates(): void {
        $connection = new FakeDatabaseConnection();
        $order = new Order($connection);

        $statement = $order->getOrdersHistory('2026-01-01', '2026-01-31');

        self::assertSame(
            "SELECT o.*, pm.name as payment_method_name FROM orders o LEFT JOIN payment_methods pm ON o.payment_method_id = pm.id WHERE o.status = 'Selesai' AND DATE(o.created_at) BETWEEN :start_date AND :end_date ORDER BY o.created_at DESC",
            preg_replace('/\s+/', ' ', trim($connection->preparedQuery))
        );
        self::assertSame([
            ':start_date' => '2026-01-01',
            ':end_date' => '2026-01-31',
        ], $connection->statement->boundParameters);
        self::assertSame(1, $connection->statement->executeCalls);
        self::assertSame($connection->statement, $statement);
    }

    public function testOmitsDateFilterWhenBothDatesAreMissing(): void {
        $connection = new FakeDatabaseConnection();
        $order = new Order($connection);

        $statement = $order->getOrdersHistory();

        self::assertSame(
            "SELECT o.*, pm.name as payment_method_name FROM orders o LEFT JOIN payment_methods pm ON o.payment_method_id = pm.id WHERE o.status = 'Selesai' ORDER BY o.created_at DESC",
            preg_replace('/\s+/', ' ', trim($connection->preparedQuery))
        );
        self::assertSame([], $connection->statement->boundParameters);
        self::assertSame(1, $connection->statement->executeCalls);
        self::assertSame($connection->statement, $statement);
    }

    public function testOmitsDateFilterWhenOnlyOneDateIsProvided(): void {
        $connection = new FakeDatabaseConnection();
        $order = new Order($connection);

        $statement = $order->getOrdersHistory('2026-01-01', null);

        self::assertStringNotContainsString('BETWEEN :start_date AND :end_date', $connection->preparedQuery);
        self::assertSame([], $connection->statement->boundParameters);
        self::assertSame(1, $connection->statement->executeCalls);
        self::assertSame($connection->statement, $statement);
    }

    public function testOmitsDateFilterWhenOnlyEndDateIsProvided(): void {
        $connection = new FakeDatabaseConnection();
        $order = new Order($connection);

        $statement = $order->getOrdersHistory(null, '2026-01-31');

        self::assertStringNotContainsString('BETWEEN :start_date AND :end_date', $connection->preparedQuery);
        self::assertSame([], $connection->statement->boundParameters);
        self::assertSame(1, $connection->statement->executeCalls);
        self::assertSame($connection->statement, $statement);
    }
}
