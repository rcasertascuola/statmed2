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
    <nav class="bg-blue-600 text-white p-4 shadow-lg flex justify-between items-center">
        <div class="flex items-center gap-2">
            <img src="assets/logo_small.png" alt="Logo" class="h-8 w-auto">
            <span class="text-xl font-bold">StatMed2</span>
        </div>
        <div class="flex items-center gap-4">
            <span class="text-sm">Benvenut<?php echo $_SESSION['sex'] === 'F' ? 'a' : 'o'; ?> <strong><?php echo $_SESSION['name']; ?></strong></span>
            <div class="flex items-center space-x-2">
                <a href="profile.php" class="bg-blue-700 hover:bg-blue-800 p-2 rounded-full text-white transition" title="Profilo">
                    <i class="ph ph-user text-xl"></i>
                </a>
                <a href="login.php?action=logout" class="bg-red-500 hover:bg-red-600 p-2 rounded-full text-white transition" title="Esci">
                    <i class="ph ph-sign-out text-xl"></i>
                </a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto p-4 md:p-8">
        <div class="flex justify-center mb-8">
            <img src="assets/logo_large.png" alt="StatMed2 Logo" class="h-32 w-auto">
        </div>

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
            <!-- ACTUAL DASHBOARD -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white p-6 rounded-lg shadow border-l-4 border-blue-500">
                    <h3 class="text-gray-500 text-sm font-bold uppercase">Pazienti Totali</h3>
                    <p class="text-3xl font-bold"><?php
                        $q = "SELECT COUNT(*) FROM patient_teams WHERE team_id = ?";
                        $stmt = $db->prepare($q);
                        $stmt->execute([$current_team_id]);
                        echo $stmt->fetchColumn();
                    ?></p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow border-l-4 border-green-500">
                    <h3 class="text-gray-500 text-sm font-bold uppercase">I tuoi Pazienti</h3>
                    <p class="text-3xl font-bold"><?php
                        $stmt = $db->prepare("SELECT COUNT(*) FROM pazienti WHERE created_by = ?");
                        $stmt->execute([$user_id]);
                        echo $stmt->fetchColumn();
                    ?></p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow border-l-4 border-purple-500">
                    <h3 class="text-gray-500 text-sm font-bold uppercase">Interventi Recenti</h3>
                    <p class="text-3xl font-bold"><?php
                        $q = "SELECT COUNT(*) FROM interventi i JOIN patient_teams pt ON i.paziente_id = pt.paziente_id WHERE pt.team_id = ?";
                        $stmt = $db->prepare($q);
                        $stmt->execute([$current_team_id]);
                        echo $stmt->fetchColumn();
                    ?></p>
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

            <?php if ($current_team_id):
                $stmt = $db->prepare("SELECT name FROM teams WHERE id = ?");
                $stmt->execute([$current_team_id]);
                $t_name = $stmt->fetchColumn();
            ?>
            <div class="text-right text-xs text-gray-500">
                Equipe attiva: <strong><?php echo htmlspecialchars($t_name); ?></strong>
                | <a href="?change_team=1" class="text-blue-500 hover:underline">Cambia</a>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </main>
</body>
</html>
