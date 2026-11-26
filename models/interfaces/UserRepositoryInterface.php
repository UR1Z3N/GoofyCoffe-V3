<?php
interface UserRepositoryInterface {
    public function findByUsername(string $username): ?array;
}
