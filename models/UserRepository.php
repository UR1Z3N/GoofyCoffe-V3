<?php
require_once __DIR__ . '/interfaces/UserRepositoryInterface.php';

class UserRepository implements UserRepositoryInterface {
    private PDO $connection;

    public function __construct(PDO $connection) {
        $this->connection = $connection;
    }

    public function findByUsername(string $username): ?array {
        $statement = $this->connection->prepare(
            'SELECT id, username, password_hash FROM users WHERE username = :username LIMIT 1'
        );
        $statement->execute([':username' => $username]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    public function createUser(string $username, string $passwordHash): void {
        $statement = $this->connection->prepare(
            'INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)'
        );
        $statement->execute([
            ':username' => $username,
            ':password_hash' => $passwordHash,
        ]);
    }
}
