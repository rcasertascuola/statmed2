<?php
require_once 'auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$user_id = $_SESSION['user_id'];

// Get user teams
$teams = getUserTeams($user_id);

// Admin doesn't need teams or keys
if (isAdmin()) {
    $current_team_id = null;
    $team_key = null;
} else {
    // If user belongs to only one team, select it automatically
    $current_team_id = $_SESSION['active_team_id'] ?? (count($teams) === 1 ? $teams[0]['id'] : null);

    if ($current_team_id) {
        $_SESSION['active_team_id'] = $current_team_id;

        // Fetch OU name for display
        $stmt_ou = $db->prepare("SELECT ou.name FROM operative_units ou JOIN team_operative_units tou ON ou.id = tou.operative_unit_id WHERE tou.team_id = ? LIMIT 1");
        $stmt_ou->execute([$current_team_id]);
        $current_ou_name = $stmt_ou->fetchColumn();

        // Check if we have the key for this team
        if (isset($_SESSION['managed_teams'][$current_team_id])) {
            $team_key = $_SESSION['managed_teams'][$current_team_id];
        } else {
            $team_key = $_SESSION['team_keys'][$current_team_id] ?? null;
        }
    } else {
        $team_key = null;
    }
}

// Handle team selection and key entry
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['team_id'])) {
        $_SESSION['active_team_id'] = $_POST['team_id'];
        if (isset($_POST['team_key'])) {
            $_SESSION['team_keys'][$_POST['team_id']] = $_POST['team_key'];
        }
        header('Location: index.php');
        exit;
    }
}

// Handle change team action
if (isset($_GET['change_team'])) {
    unset($_SESSION['active_team_id']);
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - StatMed2</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script>
        // Pass team key to sessionStorage for client-side encryption
        <?php if ($team_key): ?>
        sessionStorage.setItem('encryption_key', '<?php echo addslashes($team_key); ?>');
        <?php endif; ?>
    </script>
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
                    <i class="ph ph-gauge"></i>
                    <span>Dashboard</span>
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
                        <a href="profile.php" class="p-2 hover:bg-blue-700 rounded-full transition" title="Profilo">
                            <i class="ph ph-user text-xl"></i>
                        </a>
                        <a href="login.php?action=logout" class="p-2 hover:bg-red-600 rounded-full transition" title="Esci">
                            <i class="ph ph-sign-out text-xl"></i>
                        </a>
                    </div>
                    <button onclick="toggleMobileMenu()" class="md:hidden p-2 hover:bg-blue-700 rounded-full transition">
                        <i class="ph ph-list text-2xl"></i>
                    </button>
                </div>
            </div>

            <div class="mt-2 pt-2 border-t border-blue-500 flex flex-wrap items-center gap-2 text-sm">
                <span>Benvenut<?php echo $_SESSION['sex'] === 'F' ? 'a' : 'o'; ?>,</span>
                <strong class="font-bold hidden md:inline"><?php echo htmlspecialchars($_SESSION['name']); ?></strong>
                <strong class="font-bold md:hidden"><?php echo htmlspecialchars(explode(' ', trim($_SESSION['name']))[0]); ?></strong>
                <span class="text-blue-100 italic">
                    (<?php
                        if (isAdmin()) {
                            echo "Amministratore";
                        } else {
                            echo isLeader() ? "Capo Equipe" : "Staff";
                            if (isset($current_ou_name) && $current_ou_name) {
                                echo " - " . htmlspecialchars($current_ou_name);
                            }
                        }
                    ?>)
                </span>
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
                <a href="profile.php" class="flex items-center gap-3 p-3 hover:bg-blue-800 rounded-lg transition">
                    <i class="ph ph-user text-xl"></i>
                    <span>Profilo</span>
                </a>
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

    <main class="container mx-auto p-4 md:p-8">

        <?php if (isAdmin()): ?>
            <div class="bg-white p-8 rounded-xl shadow-lg border border-gray-200">
                <h2 class="text-2xl font-bold mb-6 text-gray-800 flex items-center gap-2">
                    <i class="ph ph-shield-check text-blue-600"></i> Pannello Amministrativo
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <a href="settings.php" class="bg-gray-800 text-white p-6 rounded-xl shadow-md hover:bg-gray-900 transition flex items-center gap-4">
                        <i class="ph ph-gear-six text-3xl"></i>
                        <div>
                            <div class="font-bold text-lg">Configurazione</div>
                            <div class="text-xs opacity-70">Utenti, Ospedali ed Equipe</div>
                        </div>
                    </a>
                    <!-- Add more admin only links if needed -->
                </div>
            </div>
        <?php elseif (empty($teams)): ?>
            <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4">
                <p>Non sei ancora associato a nessuna equipe. Contatta l'amministratore.</p>
            </div>
        <?php elseif (!$current_team_id): ?>
            <div class="bg-white p-6 rounded-lg shadow-md max-w-md mx-auto">
                <h2 class="text-xl font-bold mb-4">Seleziona Equipe</h2>
                <form method="POST">
                    <select name="team_id" class="w-full p-2 border rounded mb-4" required>
                        <option value="">Scegli...</option>
                        <?php foreach ($teams as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="w-full bg-blue-500 text-white p-2 rounded hover:bg-blue-600">Seleziona</button>
                </form>
            </div>
        <?php elseif (!$team_key): ?>
            <div class="bg-white p-6 rounded-lg shadow-md max-w-md mx-auto">
                <h2 class="text-xl font-bold mb-4">Inserisci Chiave Equipe</h2>
                <p class="text-sm text-gray-600 mb-4">L'equipe selezionata richiede una chiave di cifratura per accedere ai dati sensibili.</p>
                <form method="POST">
                    <input type="hidden" name="team_id" value="<?php echo $current_team_id; ?>">
                    <input type="password" name="team_key" class="w-full p-2 border rounded mb-4" placeholder="Chiave segreta" required>
                    <button type="submit" class="w-full bg-blue-500 text-white p-2 rounded hover:bg-blue-600">Accedi ai Dati</button>
                </form>
                <div class="mt-4 text-center">
                    <a href="?change_team=1" class="text-sm text-blue-500 hover:underline">Cambia Equipe</a>
                </div>
            </div>
        <?php else: ?>
            <div class="mb-8 bg-white p-6 rounded-xl shadow-md border border-gray-100">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold flex items-center gap-2">
                        <i class="ph ph-users-four text-blue-600"></i> Le Tue Equipe
                    </h3>
                    <?php if ($current_team_id): ?>
                    <a href="?change_team=1" class="text-xs text-blue-500 hover:underline">Cambia selezione</a>
                    <?php endif; ?>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($teams as $t): ?>
                        <div class="p-4 rounded-lg border <?php echo $t['id'] == $current_team_id ? 'bg-blue-50 border-blue-200' : 'bg-gray-50 border-gray-200'; ?> flex justify-between items-center">
                            <div>
                                <div class="font-bold text-gray-800"><?php echo htmlspecialchars($t['name']); ?></div>
                                <div class="text-[10px] uppercase font-bold text-gray-400">
                                    <?php echo $t['leader_id'] == $user_id ? 'Capo Equipe' : 'Membro'; ?>
                                </div>
                            </div>
                            <?php if ($t['id'] == $current_team_id): ?>
                                <span class="bg-blue-500 text-white text-[10px] px-2 py-0.5 rounded-full font-bold">ATTIVA</span>
                            <?php else: ?>
                                <form method="POST">
                                    <input type="hidden" name="team_id" value="<?php echo $t['id']; ?>">
                                    <button type="submit" class="text-blue-500 text-xs font-bold hover:underline">Seleziona</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex flex-wrap gap-4 mb-8">
                <a href="pazienti.php" class="flex-1 min-w-[200px] bg-blue-600 text-white p-6 rounded-xl shadow-lg hover:bg-blue-700 transition flex items-center justify-between">
                    <div>
                        <h4 class="text-xl font-bold">Pazienti</h4>
                        <p class="text-sm opacity-80">Gestione e inserimento dati</p>
                    </div>
                    <i class="ph ph-users-three text-4xl"></i>
                </a>
                <a href="stats.php" class="flex-1 min-w-[200px] bg-purple-600 text-white p-6 rounded-xl shadow-lg hover:bg-purple-700 transition flex items-center justify-between">
                    <div>
                        <h4 class="text-xl font-bold">Statistiche</h4>
                        <p class="text-sm opacity-80">Analisi e trend</p>
                    </div>
                    <i class="ph ph-chart-bar text-4xl"></i>
                </a>
                <?php if (isLeader()): ?>
                <a href="settings.php" class="flex-1 min-w-[200px] bg-gray-800 text-white p-6 rounded-xl shadow-lg hover:bg-gray-900 transition flex items-center justify-between">
                    <div>
                        <h4 class="text-xl font-bold">Equipe</h4>
                        <p class="text-sm opacity-80">Gestione team e membri</p>
                    </div>
                    <i class="ph ph-users-four text-4xl"></i>
                </a>
                <a href="clinical_config.php" class="flex-1 min-w-[200px] bg-emerald-600 text-white p-6 rounded-xl shadow-lg hover:bg-emerald-700 transition flex items-center justify-between">
                    <div>
                        <h4 class="text-xl font-bold">Config</h4>
                        <p class="text-sm opacity-80">Range e Tag clinici</p>
                    </div>
                    <i class="ph ph-stethoscope text-4xl"></i>
                </a>
                <?php endif; ?>
            </div>


        <?php endif; ?>
    </main>
</body>
</html>
