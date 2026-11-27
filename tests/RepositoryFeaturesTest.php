<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Menu.php';
require_once __DIR__ . '/../models/Report.php';
require_once __DIR__ . '/../models/UserRepository.php';
require_once __DIR__ . '/../models/NativePasswordVerifier.php';

final class RepositoryTestPDO extends PDO {
    public array $queries = [];
    public array $statements = [];
    public array $fetchQueue = [];
    public array $fetchAllQueue = [];
    public array $transactions = [];
    public int $executionCount = 0;
    public ?int $throwOnExecution = null;
    public string $insertId = '41';

    public function __construct() {}

    public function prepare(string $query, array $options = []): PDOStatement|false {
        $statement = new RepositoryTestPDOStatement($this, $query);
        $this->queries[] = $query;
        $this->statements[] = $statement;

        if ($this->fetchQueue !== []) {
            $statement->fetchResult = array_shift($this->fetchQueue);
        }

        if ($this->fetchAllQueue !== []) {
            $statement->fetchAllResult = array_shift($this->fetchAllQueue);
        }

        return $statement;
    }

    public function beginTransaction(): bool {
        $this->transactions[] = 'begin';
        return true;
    }

    public function commit(): bool {
        $this->transactions[] = 'commit';
        return true;
    }

    public function rollBack(): bool {
        $this->transactions[] = 'rollback';
        return true;
    }

    public function lastInsertId(?string $name = null): string|false {
        return $this->insertId;
    }
}

final class RepositoryTestPDOStatement extends PDOStatement {
    public mixed $fetchResult = false;
    public array $fetchAllResult = [];
    public array $boundValues = [];
    public array $boundReferences = [];
    public array $executions = [];

    public function __construct(
        private RepositoryTestPDO $connection,
        public string $query
    ) {}

    public function bindParam(
        string|int $param,
        mixed &$var,
        int $type = PDO::PARAM_STR,
        int $maxLength = 0,
        mixed $driverOptions = null
    ): bool {
        $this->boundReferences[$param] = &$var;
        return true;
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool {
        $this->boundValues[$param] = $value;
        return true;
    }

    public function execute(?array $params = null): bool {
        $this->connection->executionCount++;
        if ($this->connection->throwOnExecution === $this->connection->executionCount) {
            throw new RuntimeException('Simulated database failure.');
        }

        $values = $this->boundValues;
        foreach ($this->boundReferences as $name => &$value) {
            $values[$name] = $value;
        }
        unset($value);

        $this->executions[] = array_replace($values, $params ?? []);
        return true;
    }

    public function fetch(
        int $mode = PDO::FETCH_DEFAULT,
        int $cursorOrientation = PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed {
        return $this->fetchResult;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array {
        return $this->fetchAllResult;
    }
}

function normalizeRepositoryTestSql(string $query): string {
    return preg_replace('/\s+/', ' ', trim($query));
}

final class RepositoryFeaturesTest extends TestCase {
    public function testCreatesDigitalOrderAndPersistsEveryItemInOneTransaction(): void {
        $connection = new RepositoryTestPDO();
        $connection->fetchQueue = [['id' => 2, 'code' => 'QRIS', 'name' => 'QRIS']];
        $order = new Order($connection);

        $result = $order->createOrder(35000, 2, [
            ['menu_id' => 4, 'quantity' => 2, 'price' => 15000, 'notes' => 'less sugar'],
            ['menu_id' => 7, 'quantity' => 1, 'price' => 5000, 'notes' => ''],
        ]);

        self::assertTrue($result['success']);
        self::assertMatchesRegularExpression('/^ORD-\d{14}-\d{3}$/', $result['order_no']);
        self::assertSame(['begin', 'commit'], $connection->transactions);
        self::assertSame(2, count($connection->statements[2]->executions));
        self::assertSame([
            ':order_id' => '41',
            ':menu_id' => 4,
            ':quantity' => 2,
            ':price' => 15000,
            ':notes' => 'less sugar',
        ], $connection->statements[2]->executions[0]);
        self::assertSame([
            ':order_id' => '41',
            ':menu_id' => 7,
            ':quantity' => 1,
            ':price' => 5000,
            ':notes' => '',
        ], $connection->statements[2]->executions[1]);
        self::assertSame('Digital', $connection->statements[1]->executions[0][':legacy_payment_method']);
        self::assertSame(2, $connection->statements[1]->executions[0][':payment_method_id']);
    }

    public function testMapsCashPaymentToLegacyPaymentValue(): void {
        $connection = new RepositoryTestPDO();
        $connection->fetchQueue = [['id' => 1, 'code' => 'CASH', 'name' => 'Tunai']];
        $order = new Order($connection);

        $result = $order->createOrder(10000, 1, [
            ['menu_id' => 1, 'quantity' => 1, 'price' => 10000, 'notes' => ''],
        ]);

        self::assertTrue($result['success']);
        self::assertSame('Tunai', $connection->statements[1]->executions[0][':legacy_payment_method']);
        self::assertSame(['begin', 'commit'], $connection->transactions);
    }

    public function testRejectsUnavailablePaymentMethodAndRollsBack(): void {
        $connection = new RepositoryTestPDO();
        $connection->fetchQueue = [false];
        $order = new Order($connection);

        $result = $order->createOrder(10000, 9, [
            ['menu_id' => 1, 'quantity' => 1, 'price' => 10000, 'notes' => ''],
        ]);

        self::assertFalse($result['success']);
        self::assertSame('Metode pembayaran tidak valid atau sedang nonaktif.', $result['message']);
        self::assertSame(['begin', 'rollback'], $connection->transactions);
        self::assertCount(1, $connection->queries);
    }

    public function testRollsBackWhenAnOrderItemInsertFails(): void {
        $connection = new RepositoryTestPDO();
        $connection->fetchQueue = [['id' => 2, 'code' => 'QRIS', 'name' => 'QRIS']];
        $connection->throwOnExecution = 3;
        $order = new Order($connection);

        $result = $order->createOrder(10000, 2, [
            ['menu_id' => 1, 'quantity' => 1, 'price' => 10000, 'notes' => ''],
        ]);

        self::assertFalse($result['success']);
        self::assertSame('Simulated database failure.', $result['message']);
        self::assertSame(['begin', 'rollback'], $connection->transactions);
    }

    public function testLoadsOrderDetailsForRequestedOrder(): void {
        $connection = new RepositoryTestPDO();
        $order = new Order($connection);

        $statement = $order->getOrderDetails(41);

        self::assertSame(
            'SELECT oi.*, m.name as menu_name FROM order_items oi JOIN menus m ON oi.menu_id = m.id WHERE oi.order_id = :order_id',
            normalizeRepositoryTestSql($connection->queries[0])
        );
        self::assertSame([':order_id' => 41], $connection->statements[0]->executions[0]);
        self::assertSame($connection->statements[0], $statement);
    }
    public function testLoadsMenusWithCategoryNames(): void {
        $connection = new RepositoryTestPDO();
        $connection->fetchAllQueue = [[['id' => 1, 'name' => 'Espresso', 'category_name' => 'Coffee']]];
        $menu = new Menu($connection);

        $statement = $menu->getAllMenus();

        self::assertSame(
            'SELECT m.id, m.name, m.price, m.image, c.name as category_name FROM menus m LEFT JOIN categories c ON m.category_id = c.id ORDER BY c.id, m.name',
            normalizeRepositoryTestSql($connection->queries[0])
        );
        self::assertCount(1, $connection->statements[0]->executions);
        self::assertSame(1, count($statement->fetchAll(PDO::FETCH_ASSOC)));
    }

    public function testLoadsCategoriesInTypeAndNameOrder(): void {
        $connection = new RepositoryTestPDO();
        $connection->fetchAllQueue = [[['id' => 3, 'name' => 'Food', 'type' => 'makanan']]];
        $menu = new Menu($connection);

        $statement = $menu->getCategories();

        self::assertSame(
            'SELECT * FROM categories ORDER BY type, name',
            normalizeRepositoryTestSql($connection->queries[0])
        );
        self::assertCount(1, $connection->statements[0]->executions);
        self::assertSame([['id' => 3, 'name' => 'Food', 'type' => 'makanan']], $statement->fetchAll(PDO::FETCH_ASSOC));
    }
    public function testGetsDailyRecapForRequestedDate(): void {
        $connection = new RepositoryTestPDO();
        $connection->fetchQueue = [['total_orders' => 2, 'total_revenue' => 25000]];
        $report = new Report($connection);

        $result = $report->getDailyRecap('2026-01-05');

        self::assertSame(
            "SELECT COUNT(id) as total_orders, SUM(total_amount) as total_revenue FROM orders WHERE DATE(created_at) = :date AND status = 'Selesai'",
            normalizeRepositoryTestSql($connection->queries[0])
        );
        self::assertSame([':date' => '2026-01-05'], $connection->statements[0]->executions[0]);
        self::assertSame(['total_orders' => 2, 'total_revenue' => 25000], $result);
    }

    public function testGetsDailyPaymentMethodTotals(): void {
        $connection = new RepositoryTestPDO();
        $connection->fetchAllQueue = [[['payment_method' => 'Tunai', 'count' => 2, 'total' => 25000]]];
        $report = new Report($connection);

        $result = $report->getPaymentMethodStats('2026-01-05');

        self::assertStringContainsString('GROUP BY payment_method', normalizeRepositoryTestSql($connection->queries[0]));
        self::assertSame([':date' => '2026-01-05'], $connection->statements[0]->executions[0]);
        self::assertSame([['payment_method' => 'Tunai', 'count' => 2, 'total' => 25000]], $result);
    }

    public function testGetsMonthlyRecapForRequestedMonth(): void {
        $connection = new RepositoryTestPDO();
        $connection->fetchQueue = [['total_orders' => 4, 'total_revenue' => 90000]];
        $report = new Report($connection);

        $result = $report->getMonthlyRecap('2026-01');

        self::assertStringContainsString("DATE_FORMAT(created_at, '%Y-%m') = :month", normalizeRepositoryTestSql($connection->queries[0]));
        self::assertSame([':month' => '2026-01'], $connection->statements[0]->executions[0]);
        self::assertSame(['total_orders' => 4, 'total_revenue' => 90000], $result);
    }

    public function testGetsMonthlyPaymentMethodTotals(): void {
        $connection = new RepositoryTestPDO();
        $connection->fetchAllQueue = [[['payment_method' => 'Digital', 'count' => 3, 'total' => 45000]]];
        $report = new Report($connection);

        $result = $report->getMonthlyPaymentMethodStats('2026-01');

        self::assertStringContainsString('GROUP BY payment_method', normalizeRepositoryTestSql($connection->queries[0]));
        self::assertSame([':month' => '2026-01'], $connection->statements[0]->executions[0]);
        self::assertSame([['payment_method' => 'Digital', 'count' => 3, 'total' => 45000]], $result);
    }
    public function testFindsUserByUsernameAndReturnsNullWhenMissing(): void {
        $connection = new RepositoryTestPDO();
        $connection->fetchQueue = [
            ['id' => 8, 'username' => 'barista', 'password_hash' => 'hash'],
            false,
        ];
        $users = new UserRepository($connection);

        self::assertSame(
            ['id' => 8, 'username' => 'barista', 'password_hash' => 'hash'],
            $users->findByUsername('barista')
        );
        self::assertNull($users->findByUsername('missing'));
        self::assertSame(
            ['SELECT id, username, password_hash FROM users WHERE username = :username LIMIT 1',
             'SELECT id, username, password_hash FROM users WHERE username = :username LIMIT 1'],
            $connection->queries
        );
        self::assertSame([':username' => 'barista'], $connection->statements[0]->executions[0]);
        self::assertSame([':username' => 'missing'], $connection->statements[1]->executions[0]);
    }

    public function testCreatesUserWithProvidedPasswordHash(): void {
        $connection = new RepositoryTestPDO();
        $users = new UserRepository($connection);

        $users->createUser('new_user', 'secure-hash');

        self::assertSame(
            'INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)',
            $connection->queries[0]
        );
        self::assertSame([
            ':username' => 'new_user',
            ':password_hash' => 'secure-hash',
        ], $connection->statements[0]->executions[0]);
    }
    public function testHashesPasswordsAndVerifiesOnlyMatchingPassword(): void {
        $passwords = new NativePasswordVerifier();

        $hash = $passwords->hash('correct-horse-battery');

        self::assertNotSame('correct-horse-battery', $hash);
        self::assertTrue($passwords->verify('correct-horse-battery', $hash));
        self::assertFalse($passwords->verify('incorrect-password', $hash));
    }
}
