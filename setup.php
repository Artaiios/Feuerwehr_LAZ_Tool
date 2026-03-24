<?php
/**
 * LAZ Übungs-Tracker – Ersteinrichtung
 * Dieses Script nur einmal ausführen!
 */

require_once __DIR__ . '/config.php';

// Prüfe ob Setup bereits durchgeführt wurde
if (SETUP_COMPLETE) {
    die('<!DOCTYPE html><html><head><meta charset="utf-8"><title>Setup gesperrt</title></head><body style="font-family:sans-serif;max-width:600px;margin:50px auto;text-align:center;"><h1>⚠️ Setup bereits durchgeführt</h1><p>Die Ersteinrichtung wurde bereits abgeschlossen. Bitte setze <code>SETUP_COMPLETE</code> in <code>config.php</code> auf <code>false</code>, um das Setup erneut auszuführen.</p></body></html>');
}

$errors = [];
$success = false;
$urls = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Formulardaten
    $eventName = trim($_POST['event_name'] ?? '');
    $d1Date = $_POST['deadline_1_date'] ?? '';
    $d1Count = max(1, (int)($_POST['deadline_1_count'] ?? 11));
    $d2Date = $_POST['deadline_2_date'] ?? '';
    $d2Count = max(1, (int)($_POST['deadline_2_count'] ?? 20));

    if (empty($eventName)) $errors[] = 'Bitte einen Jahrgangs-Namen eingeben.';
    if (empty($d1Date) || empty($d2Date)) $errors[] = 'Bitte beide Frist-Daten angeben.';

    if (empty($errors)) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // Datenbank erstellen falls nötig
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `" . DB_NAME . "`");

            // ── Tabellen erstellen ──────────────────────────────

            $pdo->exec("DROP TABLE IF EXISTS audit_log");
            $pdo->exec("DROP TABLE IF EXISTS penalties");
            $pdo->exec("DROP TABLE IF EXISTS penalty_types");
            $pdo->exec("DROP TABLE IF EXISTS attendance");
            $pdo->exec("DROP TABLE IF EXISTS sessions");
            $pdo->exec("DROP TABLE IF EXISTS members");
            $pdo->exec("DROP TABLE IF EXISTS events");

            // Events
            $pdo->exec("CREATE TABLE events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                public_token VARCHAR(64) NOT NULL UNIQUE,
                admin_token VARCHAR(64) NOT NULL UNIQUE,
                deadline_1_date DATE NOT NULL,
                deadline_1_count INT NOT NULL DEFAULT 11,
                deadline_1_name VARCHAR(100) DEFAULT 'Frist 1',
                deadline_2_date DATE NOT NULL,
                deadline_2_count INT NOT NULL DEFAULT 20,
                deadline_2_name VARCHAR(100) DEFAULT 'Frist 2',
                session_duration_hours INT NOT NULL DEFAULT 3,
                weather_location VARCHAR(255) DEFAULT '',
                weather_lat DECIMAL(8,5) DEFAULT 0,
                weather_lng DECIMAL(8,5) DEFAULT 0,
                status ENUM('active','archived') NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Members
            $pdo->exec("CREATE TABLE members (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                role VARCHAR(100) DEFAULT '',
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
                INDEX idx_event_active (event_id, active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Sessions
            $pdo->exec("CREATE TABLE sessions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                session_date DATE NOT NULL,
                session_time TIME NOT NULL,
                comment VARCHAR(255) DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
                INDEX idx_event_date (event_id, session_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Attendance
            $pdo->exec("CREATE TABLE attendance (
                id INT AUTO_INCREMENT PRIMARY KEY,
                session_id INT NOT NULL,
                member_id INT NOT NULL,
                status ENUM('present','excused','absent') NOT NULL DEFAULT 'absent',
                excused_at DATETIME NULL,
                excused_by ENUM('member','admin') NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (session_id) REFERENCES sessions(id) ON DELETE CASCADE,
                FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
                UNIQUE KEY uk_session_member (session_id, member_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Penalty Types
            $pdo->exec("CREATE TABLE penalty_types (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                description VARCHAR(255) NOT NULL,
                amount DECIMAL(5,2) NOT NULL,
                active_from DATE NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Penalties
            $pdo->exec("CREATE TABLE penalties (
                id INT AUTO_INCREMENT PRIMARY KEY,
                member_id INT NOT NULL,
                penalty_type_id INT NOT NULL,
                penalty_date DATE NOT NULL,
                comment VARCHAR(500) DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL,
                FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
                FOREIGN KEY (penalty_type_id) REFERENCES penalty_types(id) ON DELETE CASCADE,
                INDEX idx_member_active (member_id, deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Audit Log
            $pdo->exec("CREATE TABLE audit_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                member_id INT NULL,
                action_type VARCHAR(50) NOT NULL,
                action_description TEXT NOT NULL,
                ip_address VARCHAR(45),
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
                INDEX idx_event_action (event_id, action_type),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // ── Event anlegen ───────────────────────────────────

            $publicToken = bin2hex(random_bytes(16));
            $adminToken = bin2hex(random_bytes(24));

            $stmt = $pdo->prepare("INSERT INTO events (name, public_token, admin_token, deadline_1_date, deadline_1_count, deadline_2_date, deadline_2_count) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$eventName, $publicToken, $adminToken, $d1Date, $d1Count, $d2Date, $d2Count]);
            $eventId = (int)$pdo->lastInsertId();

            // ── Strafenkatalog (Standard-Straftypen) ────────────

            $strafen = [
                ['Zu spät kommen', 5.00, null, 1],
                ['Unentschuldigtes Fehlen', 10.00, null, 2],
                ['Versagen von Sprüchen', 1.00, null, 3],
                ['Rauchen während der Übungsdurchführung', 5.00, null, 4],
                ['Handynutzung während der Übungsdurchführung (Ausnahme nach Abstimmung)', 5.00, null, 5],
                ['PSA unvollständig', 2.00, null, 6],
                ['Kurzfristige Absage (< 1h vor Übungsbeginn)', 2.00, null, 7],
            ];

            $stmtP = $pdo->prepare("INSERT INTO penalty_types (event_id, description, amount, active_from, sort_order) VALUES (?, ?, ?, ?, ?)");
            foreach ($strafen as $s) {
                $stmtP->execute([$eventId, $s[0], $s[1], $s[2], $s[3]]);
            }

            // Audit-Log
            $pdo->prepare("INSERT INTO audit_log (event_id, action_type, action_description, ip_address) VALUES (?, 'setup', 'Ersteinrichtung durchgeführt', ?)")
                ->execute([$eventId, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);

            $success = true;
            $baseUrl = get_base_url();
            $urls = [
                'public' => $baseUrl . '/index.php?event=' . $publicToken,
                'admin' => $baseUrl . '/index.php?event=' . $publicToken . '&admin=' . $adminToken,
            ];

        } catch (PDOException $e) {
            $errors[] = 'Datenbankfehler: ' . $e->getMessage();
        } catch (Exception $e) {
            $errors[] = 'Fehler: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔧 LAZ Tracker – Ersteinrichtung</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-lg w-full">
        <div class="bg-white rounded-2xl shadow-xl p-8">
            <div class="text-center mb-8">
                <div class="text-5xl mb-4">🔧</div>
                <h1 class="text-2xl font-bold text-gray-900">LAZ Übungs-Tracker</h1>
                <p class="text-gray-500 mt-1">Ersteinrichtung</p>
            </div>

            <?php if ($success): ?>
                <div class="bg-green-50 border border-green-200 rounded-xl p-6 mb-6">
                    <h2 class="font-bold text-green-800 text-lg mb-2">✅ Einrichtung erfolgreich!</h2>
                    <p class="text-green-700 text-sm mb-4">Die Datenbank wurde erstellt und der Jahrgang angelegt.</p>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-green-800 mb-1">🌐 Öffentliche URL (für Teilnehmer):</label>
                            <input type="text" readonly value="<?= e($urls['public']) ?>"
                                   class="w-full text-xs p-2 bg-white border border-green-300 rounded font-mono"
                                   onclick="this.select()">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-green-800 mb-1">🔑 Admin-URL (nur für dich!):</label>
                            <input type="text" readonly value="<?= e($urls['admin']) ?>"
                                   class="w-full text-xs p-2 bg-white border border-green-300 rounded font-mono"
                                   onclick="this.select()">
                        </div>
                    </div>
                </div>

                <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-6">
                    <h3 class="font-bold text-yellow-800 text-sm mb-2">⚠️ Wichtig – Nächste Schritte:</h3>
                    <ol class="text-yellow-700 text-sm space-y-1 list-decimal list-inside">
                        <li>Speichere beide URLs sicher ab</li>
                        <li>Setze <code class="bg-yellow-100 px-1 rounded">SETUP_COMPLETE</code> in <code class="bg-yellow-100 px-1 rounded">config.php</code> auf <code class="bg-yellow-100 px-1 rounded">true</code></li>
                        <li>Füge Teilnehmer und Termine über den Admin-Bereich hinzu</li>
                    </ol>
                </div>

                <a href="<?= e($urls['admin']) ?>" class="block w-full bg-red-600 text-white text-center font-semibold py-3 rounded-xl hover:bg-red-700 transition">
                    Zum Admin-Bereich →
                </a>
            <?php else: ?>
                <?php if (!empty($errors)): ?>
                    <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                        <?php foreach ($errors as $err): ?>
                            <p class="text-red-700 text-sm">❌ <?= e($err) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6">
                    <h3 class="font-bold text-blue-800 text-sm mb-2">ℹ️ Was wird eingerichtet?</h3>
                    <ul class="text-blue-700 text-sm space-y-1">
                        <li>• Datenbanktabellen werden erstellt</li>
                        <li>• Erster Jahrgang wird angelegt</li>
                        <li>• Strafenkatalog wird vorkonfiguriert</li>
                    </ul>
                </div>

                <form method="POST">
                    <div class="space-y-4 mb-6">
                        <div>
                            <label class="text-xs font-semibold text-gray-600">Jahrgangs-Name:</label>
                            <input type="text" name="event_name" required placeholder="z.B. LAZ Bronze 2026"
                                   value="<?= e($_POST['event_name'] ?? '') ?>"
                                   class="w-full border rounded-lg p-2 text-sm mt-1 focus:ring-2 focus:ring-red-500 focus:border-red-500">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Frist 1 – Datum:</label>
                                <input type="date" name="deadline_1_date" required
                                       value="<?= e($_POST['deadline_1_date'] ?? '') ?>"
                                       class="w-full border rounded-lg p-2 text-sm mt-1">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Frist 1 – Mindest-Teilnahmen:</label>
                                <input type="number" name="deadline_1_count" min="1" value="<?= (int)($_POST['deadline_1_count'] ?? 11) ?>"
                                       class="w-full border rounded-lg p-2 text-sm mt-1">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Frist 2 – Datum:</label>
                                <input type="date" name="deadline_2_date" required
                                       value="<?= e($_POST['deadline_2_date'] ?? '') ?>"
                                       class="w-full border rounded-lg p-2 text-sm mt-1">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-600">Frist 2 – Mindest-Teilnahmen:</label>
                                <input type="number" name="deadline_2_count" min="1" value="<?= (int)($_POST['deadline_2_count'] ?? 20) ?>"
                                       class="w-full border rounded-lg p-2 text-sm mt-1">
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 border rounded-xl p-4 mb-6">
                        <h3 class="font-bold text-gray-700 text-sm mb-2">📋 Voraussetzungen:</h3>
                        <ul class="text-gray-600 text-sm space-y-1">
                            <li>✓ MySQL/MariaDB-Datenbank verfügbar</li>
                            <li>✓ Zugangsdaten in <code class="bg-gray-200 px-1 rounded">config.php</code> eingetragen</li>
                            <li>✓ PHP 8.0+ mit PDO-MySQL-Erweiterung</li>
                        </ul>
                    </div>

                    <button type="submit"
                            class="w-full bg-red-600 text-white font-semibold py-3 rounded-xl hover:bg-red-700 transition"
                            onclick="return confirm('Bist du sicher? Bestehende Tabellen werden gelöscht und neu erstellt!')">
                        🚀 Einrichtung starten
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <p class="text-center text-gray-400 text-xs mt-4">LAZ Übungs-Tracker v<?= APP_VERSION ?></p>
    </div>
</body>
</html>
