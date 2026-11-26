<?php
interface UserRepositoryInterface {
    public function findByUsername(string $username): ?array;
    public function createUser(string $username, string $passwordHash): void;
}
