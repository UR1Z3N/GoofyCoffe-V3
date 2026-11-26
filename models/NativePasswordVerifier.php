<?php
require_once __DIR__ . '/interfaces/PasswordVerifierInterface.php';

class NativePasswordVerifier implements PasswordVerifierInterface {
    public function hash(string $password): string {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public function verify(string $password, string $passwordHash): bool {
        return password_verify($password, $passwordHash);
    }
}
