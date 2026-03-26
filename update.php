<?php
/**
 * LAZ Übungs-Tracker – Kumulative Migration
 * 
 * Bringt die Datenbank von jeder älteren Version auf den aktuellen Stand (v1.7.3).
 * Kann gefahrlos mehrfach ausgeführt werden – bereits vorhandene Änderungen werden übersprungen.
 * Bestehende Daten bleiben vollständig erhalten.
 */
require_once __DIR__ . '/config.php';

$results = [];
$errors = [];
$serverAdminUrl = '';

function columnExists(PDO $pdo, string $table, string $column): bool {
    $cols = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
    return in_array($column, $cols);
}

function tableExists(PDO $pdo, string $table): bool {
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    return in_array($table, $tables);
}

function configKeyExists(PDO $pdo, string $key): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM server_config WHERE config_key = ?");
    $stmt->execute([$key]);
    return (int)$stmt->fetchColumn() > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        // ────────────────────────────────────────────────────
        // v1.1: Frist-Anzeigenamen
        // ────────────────────────────────────────────────────
        if (!columnExists($pdo, 'events', 'deadline_1_name')) {
            $pdo->exec("ALTER TABLE events ADD COLUMN deadline_1_name VARCHAR(100) DEFAULT 'Frist 1' AFTER deadline_1_count");
            $results[] = 'v1.1 – deadline_1_name hinzugefügt';
        }
        if (!columnExists($pdo, 'events', 'deadline_2_name')) {
            $pdo->exec("ALTER TABLE events ADD COLUMN deadline_2_name VARCHAR(100) DEFAULT 'Frist 2' AFTER deadline_2_count");
            $results[] = 'v1.1 – deadline_2_name hinzugefügt';
        }

        // ────────────────────────────────────────────────────
        // v1.6.0: Übungsdauer
        // ────────────────────────────────────────────────────
        if (!columnExists($pdo, 'events', 'session_duration_hours')) {
            $pdo->exec("ALTER TABLE events ADD COLUMN session_duration_hours INT NOT NULL DEFAULT 3 AFTER deadline_2_name");
            $results[] = 'v1.6.0 – session_duration_hours hinzugefügt';
        }

        // ────────────────────────────────────────────────────
        // v1.6.1: Wetter-Standort
        // ────────────────────────────────────────────────────
        if (!columnExists($pdo, 'events', 'weather_location')) {
            $pdo->exec("ALTER TABLE events ADD COLUMN weather_location VARCHAR(255) DEFAULT '' AFTER session_duration_hours");
            $results[] = 'v1.6.1 – weather_location hinzugefügt';
        }
        if (!columnExists($pdo, 'events', 'weather_lat')) {
            $pdo->exec("ALTER TABLE events ADD COLUMN weather_lat DECIMAL(8,5) DEFAULT 0 AFTER weather_location");
            $results[] = 'v1.6.1 – weather_lat hinzugefügt';
        }
        if (!columnExists($pdo, 'events', 'weather_lng')) {
            $pdo->exec("ALTER TABLE events ADD COLUMN weather_lng DECIMAL(8,5) DEFAULT 0 AFTER weather_lat");
            $results[] = 'v1.6.1 – weather_lng hinzugefügt';
        }

        // ────────────────────────────────────────────────────
        // v1.7.0: Server-Admin + Org-Name + Frist 1 optional
        // ────────────────────────────────────────────────────
        if (!tableExists($pdo, 'server_config')) {
            $pdo->exec("CREATE TABLE server_config (
                id INT AUTO_INCREMENT PRIMARY KEY,
                config_key VARCHAR(100) NOT NULL UNIQUE,
                config_value TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $serverToken = bin2hex(random_bytes(24));
            $stmt = $pdo->prepare("INSERT INTO server_config (config_key, config_value) VALUES (?, ?)");
            $stmt->execute(['server_admin_token', $serverToken]);
            $stmt->execute(['organization_name', 'Freiwillige Feuerwehr']);
            $stmt->execute(['admin_email', '']);
            $stmt->execute(['show_public_overview', '0']);

            $baseUrl = rtrim(
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                . dirname($_SERVER['SCRIPT_NAME']), '/'
            );
            $serverAdminUrl = $baseUrl . '/admin.php?token=' . $serverToken;
            $results[] = 'v1.7.0 – server_config Tabelle erstellt + Server-Admin-Token generiert';
        } else {
            // Fehlende config-Einträge nachziehen
            if (!configKeyExists($pdo, 'admin_email')) {
                $pdo->prepare("INSERT INTO server_config (config_key, config_value) VALUES ('admin_email', '')")->execute();
                $results[] = 'v1.7.0 – admin_email Eintrag nachgetragen';
            }
            if (!configKeyExists($pdo, 'show_public_overview')) {
                $pdo->prepare("INSERT INTO server_config (config_key, config_value) VALUES ('show_public_overview', '0')")->execute();
                $results[] = 'v1.7.0 – show_public_overview Eintrag nachgetragen';
            }
            // URL für Anzeige
            $stmt = $pdo->prepare("SELECT config_value FROM server_config WHERE config_key = 'server_admin_token'");
            $stmt->execute();
            $existingToken = $stmt->fetchColumn();
            if ($existingToken) {
                $baseUrl = rtrim(
                    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                    . dirname($_SERVER['SCRIPT_NAME']), '/'
                );
                $serverAdminUrl = $baseUrl . '/admin.php?token=' . $existingToken;
            }
        }

        if (!columnExists($pdo, 'events', 'organization_name')) {
            $pdo->exec("ALTER TABLE events ADD COLUMN organization_name VARCHAR(255) DEFAULT NULL AFTER name");
            $results[] = 'v1.7.0 – organization_name hinzugefügt';
        }
        if (!columnExists($pdo, 'events', 'deadline_1_enabled')) {
            $pdo->exec("ALTER TABLE events ADD COLUMN deadline_1_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER deadline_1_name");
            $results[] = 'v1.7.0 – deadline_1_enabled hinzugefügt';
        }

        // ────────────────────────────────────────────────────
        // v1.7.2: Rollen
        // ────────────────────────────────────────────────────
        if (!columnExists($pdo, 'events', 'roles_enabled')) {
            $pdo->exec("ALTER TABLE events ADD COLUMN roles_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER weather_lng");
            $results[] = 'v1.7.2 – roles_enabled hinzugefügt';
        }
        if (!tableExists($pdo, 'roles')) {
            $pdo->exec("CREATE TABLE roles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
                INDEX idx_event_sort (event_id, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $results[] = 'v1.7.2 – roles Tabelle erstellt';
        }
        if (!tableExists($pdo, 'member_roles')) {
            $pdo->exec("CREATE TABLE member_roles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                member_id INT NOT NULL,
                role_id INT NOT NULL,
                FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
                FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
                UNIQUE KEY uk_member_role (member_id, role_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $results[] = 'v1.7.2 – member_roles Tabelle erstellt';
        }

        // ────────────────────────────────────────────────────
        // Fertig
        // ────────────────────────────────────────────────────
        if (empty($results)) {
            $results[] = 'Datenbank ist bereits auf dem neuesten Stand — keine Änderungen nötig.';
        }

    } catch (PDOException $e) {
        $errors[] = 'Datenbankfehler: ' . $e->getMessage();
    }
}
?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LAZ Tracker – Datenbank-Migration</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
<div class="max-w-lg w-full">
    <div class="bg-white rounded-2xl shadow-xl p-8">
        <div class="text-center mb-6">
            <div class="text-5xl mb-4">🔧</div>
            <h1 class="text-2xl font-bold">Datenbank-Migration</h1>
            <p class="text-gray-500 mt-1">Aktualisiert auf v1.7.3</p>
        </div>

        <?php if (!empty($errors)): ?>
            <?php foreach ($errors as $err): ?>
            <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
                <p class="text-red-700 text-sm">❌ <?= htmlspecialchars($err) ?></p>
            </div>
            <?php endforeach; ?>

        <?php elseif (!empty($results)): ?>
            <div class="bg-green-50 border border-green-200 rounded-xl p-6 mb-6">
                <p class="text-green-800 font-semibold mb-3">✅ Migration abgeschlossen</p>
                <ul class="text-green-700 text-sm space-y-1">
                    <?php foreach ($results as $r): ?>
                    <li>• <?= htmlspecialchars($r) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <?php if ($serverAdminUrl): ?>
            <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6">
                <h3 class="font-bold text-yellow-800 text-sm mb-2">🔑 Server-Admin URL:</h3>
                <input type="text" readonly value="<?= htmlspecialchars($serverAdminUrl) ?>"
                       class="w-full text-xs p-2 bg-white border border-yellow-300 rounded font-mono mb-2" onclick="this.select()">
                <p class="text-yellow-700 text-xs">Diese URL sicher abspeichern!</p>
            </div>
            <?php endif; ?>

            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
                <h3 class="font-bold text-blue-800 text-sm mb-2">Nächste Schritte:</h3>
                <ol class="text-blue-700 text-sm space-y-1 list-decimal list-inside">
                    <li>APP_VERSION in config.php auf '1.7.3' setzen</li>
                    <li>Diese Datei vom Server löschen</li>
                    <?php if ($serverAdminUrl): ?>
                    <li>Server-Admin URL sicher abspeichern</li>
                    <li>Im Server-Admin die E-Mail-Adresse eintragen</li>
                    <?php endif; ?>
                </ol>
            </div>

        <?php else: ?>
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
                <p class="text-blue-700 text-sm">Prüft die Datenbank und führt alle fehlenden Änderungen durch. Kann gefahrlos mehrfach ausgeführt werden.</p>
                <p class="text-blue-600 text-xs mt-2">Unterstützte Quellversionen: v1.0 bis v1.7.2</p>
            </div>
            <form method="POST">
                <button type="submit" class="w-full bg-red-600 text-white font-semibold py-3 rounded-xl hover:bg-red-700 transition">
                    🚀 Migration starten
                </button>
            </form>
        <?php endif; ?>
    </div>
    <p class="text-center text-gray-400 text-xs mt-4">LAZ Übungs-Tracker – Migration auf v1.7.3</p>
</div>
</body>
</html>
