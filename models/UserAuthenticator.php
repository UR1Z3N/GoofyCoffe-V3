<?php
require_once __DIR__ . '/interfaces/UserRepositoryInterface.php';
require_once __DIR__ . '/interfaces/PasswordVerifierInterface.php';

class UserAuthenticator {
    private UserRepositoryInterface $users;
    private PasswordVerifierInterface $passwords;

    public function __construct(
        UserRepositoryInterface $users,
        PasswordVerifierInterface $passwords
    ) {
        $this->users = $users;
        $this->passwords = $passwords;
    }

    public function authenticate(string $username, string $password): ?array {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return null;
        }

        $user = $this->users->findByUsername($username);
        if ($user === null) {
            return null;
        }

        if (!$this->passwords->verify($password, $user['password_hash'])) {
            return null;
        }

        return [
            'id' => (int)$user['id'],
            'username' => $user['username'],
        ];
    }
}
