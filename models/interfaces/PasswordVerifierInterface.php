<?php
interface PasswordVerifierInterface {
    public function verify(string $password, string $passwordHash): bool;
}
