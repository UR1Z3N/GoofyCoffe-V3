<?php
require_once __DIR__ . '/auth.php';
if (isset($_SESSION['user']['id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/UserRepository.php';
require_once __DIR__ . '/models/NativePasswordVerifier.php';
require_once __DIR__ . '/models/UserAuthenticator.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $database = new Database();
    $users = new UserRepository($database->getConnection());
    $authenticator = new UserAuthenticator($users, new NativePasswordVerifier());
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $user = is_string($username) && is_string($password)
        ? $authenticator->authenticate($username, $password)
        : null;

    if ($user === null) {
        $error = 'Username atau password salah.';
    } else {
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        unset($_SESSION['csrf_token']);
        header('Location: index.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Goofy Coffee</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    <main class="w-full max-w-md bg-white rounded-2xl shadow-lg border border-gray-100 p-8">
        <div class="text-center mb-7">
            <img src="assets/images/brand_logo.png" alt="Goofy Coffee" class="h-16 mx-auto mb-3">
            <h1 class="text-2xl font-bold text-gray-900">Login Goofy Coffee</h1>
            <p class="text-sm text-gray-500 mt-1">Masuk dengan akun pengguna Anda.</p>
        </div>
        <?php if ($error !== ''): ?>
            <p role="alert" class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <form method="post" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <label class="block text-sm font-semibold text-gray-700">Username
                <input required name="username" autocomplete="username" class="mt-1 w-full border rounded-lg px-3 py-2 font-normal focus:outline-none focus:ring-2 focus:ring-green-600">
            </label>
            <label class="block text-sm font-semibold text-gray-700">Password
                <input required type="password" name="password" autocomplete="current-password" class="mt-1 w-full border rounded-lg px-3 py-2 font-normal focus:outline-none focus:ring-2 focus:ring-green-600">
            </label>
            <button class="w-full rounded-lg bg-green-700 hover:bg-green-800 text-white font-bold py-3">Masuk</button>
        </form>
        <p class="text-center text-sm text-gray-600 mt-6">Belum punya akun? <a href="register.php" class="font-semibold text-green-700 hover:underline">Daftar sebagai pengguna</a></p>
    </main>
</body>
</html>
