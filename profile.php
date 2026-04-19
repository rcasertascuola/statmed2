<?php
require_once 'auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = trim($_POST['name']);
        $sex = $_POST['sex'];

        $stmt = $db->prepare("UPDATE users SET name = ?, sex = ? WHERE id = ?");
        $stmt->execute([$name, $sex, $user_id]);
        $_SESSION['name'] = $name;
        $_SESSION['sex'] = $sex;
        $message = "Profilo aggiornato con successo.";
    }
    elseif ($action === 'change_password') {
        $old_pass = $_POST['old_password'];
        $new_pass = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];

        if ($new_pass !== $confirm_pass) {
            $error = "Le nuove password non corrispondono.";
        } else {
            // Verify old password
            $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $stored_hash = $stmt->fetchColumn();

            if (password_verify($old_pass, $stored_hash)) {
                // If user is a leader, we must re-encrypt team keys
                $stmt = $db->prepare("SELECT id, encrypted_team_key FROM teams WHERE leader_id = ?");
                $stmt->execute([$user_id]);
                $managed_teams = $stmt->fetchAll();

                try {
                    $db->beginTransaction();

                    foreach ($managed_teams as $team) {
                        if ($team['encrypted_team_key']) {
                            $plain_key = decryptWithPassword($team['encrypted_team_key'], $old_pass);
                            if ($plain_key) {
                                $new_encrypted_key = encryptWithPassword($plain_key, $new_pass);
                                $upd = $db->prepare("UPDATE teams SET encrypted_team_key = ? WHERE id = ?");
                                $upd->execute([$new_encrypted_key, $team['id']]);

                                // Also update the managed_teams session key if this team is the active one
                                if (isset($_SESSION['managed_teams'][$team['id']])) {
                                    $_SESSION['managed_teams'][$team['id']] = $plain_key;
                                }
                            }
                        }
                    }

                    // Update password hash
                    $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$new_hash, $user_id]);

                    $db->commit();
                    $message = "Password aggiornata con successo. Le chiavi delle tue equipe sono state ri-cifrate.";
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = "Errore durante l'aggiornamento delle chiavi: " . $e->getMessage();
                }
            } else {
                $error = "La vecchia password non è corretta.";
            }
        }
    }
}

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profilo - StatMed2</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-blue-600 text-white p-3 shadow-lg relative z-50">
        <div class="container mx-auto">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <a href="index.php" class="flex items-center gap-2 hover:text-blue-200 transition" title="Dashboard">
                        <i class="ph ph-gauge text-2xl"></i>
                        <img src="assets/logo_small.png" alt="Logo" class="h-8 w-auto">
                    </a>
                </div>

                <div class="flex items-center gap-2 font-bold uppercase tracking-wider text-sm md:text-base">
                    <i class="ph ph-user"></i>
                    <span>Profilo</span>
                </div>

                <div class="flex items-center">
                    <div class="hidden md:flex items-center gap-2">
                        <a href="pazienti.php" class="p-2 hover:bg-blue-700 rounded-full transition" title="Pazienti">
                            <i class="ph ph-users text-xl"></i>
                        </a>
                        <a href="stats.php" class="p-2 hover:bg-blue-700 rounded-full transition" title="Statistiche">
                            <i class="ph ph-chart-line-up text-xl"></i>
                        </a>
                        <?php if (isAdmin() || isLeader()): ?>
                        <a href="settings.php" class="p-2 hover:bg-blue-700 rounded-full transition" title="Impostazioni">
                            <i class="ph ph-gear text-xl"></i>
                        </a>
                        <?php endif; ?>
                        <a href="login.php?action=logout" class="p-2 hover:bg-red-600 rounded-full transition" title="Esci">
                            <i class="ph ph-sign-out text-xl"></i>
                        </a>
                    </div>
                    <button onclick="toggleMobileMenu()" class="md:hidden p-2 hover:bg-blue-700 rounded-full transition">
                        <i class="ph ph-list text-2xl"></i>
                    </button>
                </div>
            </div>
        </div>
        <div id="mobileMenu" class="hidden absolute top-full left-0 w-full bg-blue-700 shadow-xl md:hidden">
            <div class="flex flex-col p-2">
                <a href="pazienti.php" class="flex items-center gap-3 p-3 hover:bg-blue-800 rounded-lg transition">
                    <i class="ph ph-users text-xl"></i>
                    <span>Pazienti</span>
                </a>
                <a href="stats.php" class="flex items-center gap-3 p-3 hover:bg-blue-800 rounded-lg transition">
                    <i class="ph ph-chart-line-up text-xl"></i>
                    <span>Statistiche</span>
                </a>
                <?php if (isAdmin() || isLeader()): ?>
                <a href="settings.php" class="flex items-center gap-3 p-3 hover:bg-blue-800 rounded-lg transition">
                    <i class="ph ph-gear text-xl"></i>
                    <span>Impostazioni</span>
                </a>
                <?php endif; ?>
                <a href="login.php?action=logout" class="flex items-center gap-3 p-3 hover:bg-red-600 rounded-lg transition">
                    <i class="ph ph-sign-out text-xl"></i>
                    <span>Esci</span>
                </a>
            </div>
        </div>
    </nav>
    <script>
    function toggleMobileMenu() {
        const menu = document.getElementById('mobileMenu');
        menu.classList.toggle('hidden');
    }
    </script>

    <main class="container mx-auto p-4 md:p-8 max-w-2xl">
        <?php if ($message): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="space-y-8">
            <!-- Edit Profile -->
            <section class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-lg font-bold mb-4 flex items-center gap-2"><i class="ph ph-user-circle"></i> Informazioni Personali</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_profile">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nome e Cognome</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" class="w-full p-2 border rounded" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Sesso</label>
                        <select name="sex" class="w-full p-2 border rounded">
                            <option value="M" <?php echo $user['sex'] === 'M' ? 'selected' : ''; ?>>Uomo (M)</option>
                            <option value="F" <?php echo $user['sex'] === 'F' ? 'selected' : ''; ?>>Donna (F)</option>
                        </select>
                    </div>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition">Salva Modifiche</button>
                </form>
            </section>

            <!-- Change Password -->
            <section class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-lg font-bold mb-4 flex items-center gap-2"><i class="ph ph-lock-key"></i> Sicurezza</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="change_password">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Vecchia Password</label>
                        <input type="password" name="old_password" class="w-full p-2 border rounded" required>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nuova Password</label>
                            <input type="password" name="new_password" class="w-full p-2 border rounded" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Conferma Nuova Password</label>
                            <input type="password" name="confirm_password" class="w-full p-2 border rounded" required>
                        </div>
                    </div>
                    <button type="submit" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 transition">Cambia Password</button>
                </form>
            </section>
        </div>
    </main>
</body>
</html>
