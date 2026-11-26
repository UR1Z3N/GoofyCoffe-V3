<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/interfaces/UserRepositoryInterface.php';
require_once __DIR__ . '/../models/interfaces/PasswordVerifierInterface.php';
require_once __DIR__ . '/../models/UserAuthenticator.php';

final class FakeUserRepository implements UserRepositoryInterface {
    public array $users;
    public array $lookedUpUsernames = [];

    public function __construct(array $users = []) {
        $this->users = $users;
    }

    public function findByUsername(string $username): ?array {
        $this->lookedUpUsernames[] = $username;
        return $this->users[$username] ?? null;
    }

    public function createUser(string $username, string $passwordHash): void {
        $this->users[$username] = ['id' => count($this->users) + 1, 'username' => $username, 'password_hash' => $passwordHash];
    }
}

final class SpyPasswordVerifier implements PasswordVerifierInterface {
    public int $verifyCalls = 0;
    public ?string $receivedPassword = null;
    public ?string $receivedHash = null;

    public function __construct(private bool $verificationResult = true) {}

    public function hash(string $password): string {
        return 'hashed:' . $password;
    }

    public function verify(string $password, string $passwordHash): bool {
        $this->verifyCalls++;
        $this->receivedPassword = $password;
        $this->receivedHash = $passwordHash;
        return $this->verificationResult;
    }
}

final class UserAuthenticatorTest extends TestCase {
    public function testAuthenticatesKnownUserWithValidPassword(): void {
        $users = new FakeUserRepository([
            'barista' => ['id' => 7, 'username' => 'barista', 'password_hash' => 'stored-hash'],
        ]);
        $passwords = new SpyPasswordVerifier();
        $authenticator = new UserAuthenticator($users, $passwords);

        $result = $authenticator->authenticate(' barista ', 'correct-password');

        self::assertSame(['id' => 7, 'username' => 'barista'], $result);
        self::assertSame(['barista'], $users->lookedUpUsernames);
        self::assertSame(1, $passwords->verifyCalls);
        self::assertSame('stored-hash', $passwords->receivedHash);
    }

    public function testRejectsEmptyCredentialsWithoutLookingUpUser(): void {
        $users = new FakeUserRepository();
        $passwords = new SpyPasswordVerifier();
        $authenticator = new UserAuthenticator($users, $passwords);

        self::assertNull($authenticator->authenticate('', 'password'));
        self::assertSame([], $users->lookedUpUsernames);
        self::assertSame(0, $passwords->verifyCalls);
    }

    public function testRejectsEmptyPasswordWithoutLookingUpUser(): void {
        $users = new FakeUserRepository();
        $passwords = new SpyPasswordVerifier();
        $authenticator = new UserAuthenticator($users, $passwords);

        self::assertNull($authenticator->authenticate('barista', ''));
        self::assertSame([], $users->lookedUpUsernames);
        self::assertSame(0, $passwords->verifyCalls);
    }

    public function testRejectsUnknownUserWithoutCheckingPassword(): void {
        $users = new FakeUserRepository();
        $passwords = new SpyPasswordVerifier();
        $authenticator = new UserAuthenticator($users, $passwords);

        self::assertNull($authenticator->authenticate('missing', 'password'));
        self::assertSame(['missing'], $users->lookedUpUsernames);
        self::assertSame(0, $passwords->verifyCalls);
    }

    public function testRejectsWrongPassword(): void {
        $users = new FakeUserRepository([
            'barista' => ['id' => 7, 'username' => 'barista', 'password_hash' => 'stored-hash'],
        ]);
        $passwords = new SpyPasswordVerifier(false);
        $authenticator = new UserAuthenticator($users, $passwords);

        self::assertNull($authenticator->authenticate('barista', 'wrong-password'));
        self::assertSame(1, $passwords->verifyCalls);
    }
}
