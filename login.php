<?php
require_once 'auth.php';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    if (login($_POST['username'], $_POST['password'])) {
        header('Location: index.php');
        exit;
    } else {
        $error = "Credenziali errate.";
    }
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logout();
    ?>
    <!DOCTYPE html>
    <html>
    <body>
        <script>
            sessionStorage.clear();
            window.location.href = 'index.php';
        </script>
    </body>
    </html>
    <?php
    exit;
}

if (!isLoggedIn()): ?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - StatMed2</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded shadow-md w-96">
        <div class="flex flex-col items-center mb-6">
            <img src="assets/logo_large.png" alt="StatMed2 Logo" class="h-20 w-auto mb-2">
            <h1 class="text-xl font-bold text-gray-700">Login</h1>
        </div>
        <?php if (isset($error)): ?>
            <p class="text-red-500 mb-4"><?php echo $error; ?></p>
        <?php endif; ?>
        <form method="POST" onsubmit="sessionStorage.clear();">
            <input type="hidden" name="action" value="login">
            <div class="mb-4">
                <label class="block text-gray-700">Username</label>
                <input type="text" name="username" class="w-full p-2 border rounded" required>
            </div>
            <div class="mb-6">
                <label class="block text-gray-700">Password</label>
                <input type="password" name="password" class="w-full p-2 border rounded" required>
            </div>
            <button type="submit" class="w-full bg-blue-500 text-white p-2 rounded hover:bg-blue-600 transition">Accedi</button>
        </form>
    </div>
</body>
</html>
<?php exit; endif; ?>
<?php
// If logged in, redirect to Dashboard (index.php)
header('Location: index.php');
exit;
?>
