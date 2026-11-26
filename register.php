<?php
require_once __DIR__ . '/auth.php';
if (isset($_SESSION['user']['id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/UserRepository.php';

$error = '';
$usernameValue = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $usernameInput = $_POST['username'] ?? '';
    $passwordInput = $_POST['password'] ?? '';
    $confirmationInput = $_POST['password_confirmation'] ?? '';
    $username = is_string($usernameInput) ? trim($usernameInput) : '';
    $password = is_string($passwordInput) ? $passwordInput : '';
    $confirmation = is_string($confirmationInput) ? $confirmationInput : '';
    $usernameValue = is_string($usernameInput) ? $usernameInput : '';

    if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
        $error = 'Username harus 3-32 karakter dan hanya boleh berisi huruf, angka, atau garis bawah.';
    } elseif (strlen($password) < 8) {
        $error = 'Password harus terdiri dari minimal 8 karakter.';
    } elseif ($password !== $confirmation) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $database = new Database();
        $users = new UserRepository($database->getConnection());
        try {
            $users->createUser($username, password_hash($password, PASSWORD_DEFAULT));
            header('Location: login.php?registered=1');
            exit;
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $error = 'Username sudah digunakan.';
            } else {
                throw $exception;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pengguna | Goofy Coffee</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <main class="w-full max-w-md bg-white rounded-2xl shadow-lg border border-gray-100 p-8">
        <div class="text-center mb-7">
            <h1 class="text-2xl font-bold text-gray-900">Daftar Pengguna</h1>
            <p class="text-sm text-gray-500 mt-1">Akun yang dibuat hanya memiliki akses pengguna.</p>
        </div>
        <?php if ($error !== ''): ?>
            <p role="alert" class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <form method="post" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <label class="block text-sm font-semibold text-gray-700">Username
                <input required minlength="3" maxlength="32" pattern="[A-Za-z0-9_]+" name="username" autocomplete="username" value="<?= htmlspecialchars($usernameValue, ENT_QUOTES, 'UTF-8') ?>" class="mt-1 w-full border rounded-lg px-3 py-2 font-normal">
            </label>
            <label class="block text-sm font-semibold text-gray-700">Password (minimal 8 karakter)
                <input required minlength="8" type="password" name="password" autocomplete="new-password" class="mt-1 w-full border rounded-lg px-3 py-2 font-normal">
            </label>
            <label class="block text-sm font-semibold text-gray-700">Konfirmasi Password
                <input required minlength="8" type="password" name="password_confirmation" autocomplete="new-password" class="mt-1 w-full border rounded-lg px-3 py-2 font-normal">
            </label>
            <button class="w-full rounded-lg bg-green-700 hover:bg-green-800 text-white font-bold py-3">Buat Akun</button>
        </form>
        <p class="text-center text-sm text-gray-600 mt-6">Sudah punya akun? <a href="login.php" class="font-semibold text-green-700 hover:underline">Masuk</a></p>
    </main>
</body>
</html>
