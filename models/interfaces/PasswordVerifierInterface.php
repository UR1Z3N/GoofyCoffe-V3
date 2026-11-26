<?php
interface PasswordVerifierInterface {
    public function hash(string $password): string;
    public function verify(string $password, string $passwordHash): bool;
}
