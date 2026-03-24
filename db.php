<?php
/**
 * LAZ Übungs-Tracker – Datenbankverbindung & Hilfsfunktionen
 */

require_once __DIR__ . '/config.php';

function get_pdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        $pdo->exec("SET NAMES utf8mb4");
        $pdo->exec("SET time_zone = '+01:00'");
    }
    return $pdo;
}

// ── Event-Funktionen ────────────────────────────────────────

function get_event_by_public_token(string $token): ?array {
    $stmt = get_pdo()->prepare("SELECT * FROM events WHERE public_token = ? AND status = 'active'");
    $stmt->execute([$token]);
    return $stmt->fetch() ?: null;
}

function get_event_by_admin_token(string $eventToken, string $adminToken): ?array {
    $stmt = get_pdo()->prepare("SELECT * FROM events WHERE public_token = ? AND admin_token = ?");
    $stmt->execute([$eventToken, $adminToken]);
    return $stmt->fetch() ?: null;
}

function get_all_events(): array {
    return get_pdo()->query("SELECT * FROM events ORDER BY created_at DESC")->fetchAll();
}

function create_event(string $name, string $d1_date, int $d1_count, string $d2_date, int $d2_count): array {
    $publicToken = generate_token(16);
    $adminToken = generate_token(24);
    $stmt = get_pdo()->prepare("INSERT INTO events (name, public_token, admin_token, deadline_1_date, deadline_1_count, deadline_2_date, deadline_2_count, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
    $stmt->execute([$name, $publicToken, $adminToken, $d1_date, $d1_count, $d2_date, $d2_count]);
    return [
        'id' => get_pdo()->lastInsertId(),
        'public_token' => $publicToken,
        'admin_token' => $adminToken,
    ];
}

function update_event(int $id, string $name, string $status, string $d1_date, int $d1_count, string $d2_date, int $d2_count, string $d1_name = 'Frist 1', string $d2_name = 'Frist 2', int $sessionDuration = 3, string $weatherLocation = '', float $weatherLat = 0, float $weatherLng = 0): void {
    $sql = "UPDATE events SET name=?, status=?, deadline_1_date=?, deadline_1_count=?, deadline_2_date=?, deadline_2_count=?, deadline_1_name=?, deadline_2_name=?, session_duration_hours=?";
    $params = [$name, $status, $d1_date, $d1_count, $d2_date, $d2_count, $d1_name, $d2_name, $sessionDuration];
    if ($weatherLocation !== '' && $weatherLat != 0 && $weatherLng != 0) {
        $sql .= ", weather_location=?, weather_lat=?, weather_lng=?";
        $params[] = $weatherLocation;
        $params[] = $weatherLat;
        $params[] = $weatherLng;
    }
    $sql .= " WHERE id=?";
    $params[] = $id;
    $stmt = get_pdo()->prepare($sql);
    $stmt->execute($params);
}

// ── Session-Zeitlogik ───────────────────────────────────────

/**
 * Prüft ob der Termin-Startzeitpunkt in der Zukunft liegt.
 */
function is_session_in_future(array $session): bool {
    $start = new DateTime($session['session_date'] . ' ' . $session['session_time']);
    return $start > new DateTime();
}

/**
 * Prüft ob die Übung als beendet gilt (Startzeit + Dauer überschritten).
 */
function is_session_ended(array $session, int $durationHours = 3): bool {
    $start = new DateTime($session['session_date'] . ' ' . $session['session_time']);
    $end = clone $start;
    $end->modify('+' . $durationHours . ' hours');
    return new DateTime() >= $end;
}

/**
 * Ermittelt den nächsten Termin (noch nicht beendeter Termin).
 */
function get_next_session(array $sessions, int $durationHours = 3): ?array {
    foreach ($sessions as $s) {
        if (!is_session_ended($s, $durationHours)) {
            return $s;
        }
    }
    return null;
}

/**
 * Prüft ob ein Mitglied seinen Entschuldigungsstatus selbst ändern darf.
 * Erlaubt wenn: Termin noch nicht gestartet UND Admin hat nicht bereits present/absent gesetzt.
 */
function can_member_change_excuse(array $session, ?array $attendance): bool {
    if (!is_session_in_future($session)) return false;
    if ($attendance && in_array($attendance['status'], ['present', 'absent']) && $attendance['excused_by'] === 'admin') {
        return false;
    }
    return true;
}

// ── Teilnehmer-Funktionen ───────────────────────────────────

function get_members(int $eventId, bool $activeOnly = true): array {
    $sql = "SELECT * FROM members WHERE event_id = ?";
    if ($activeOnly) $sql .= " AND active = 1";
    $sql .= " ORDER BY name ASC";
    $stmt = get_pdo()->prepare($sql);
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}

function get_member(int $id): ?array {
    $stmt = get_pdo()->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function create_member(int $eventId, string $name, string $role = ''): int {
    $stmt = get_pdo()->prepare("INSERT INTO members (event_id, name, role, active, created_at) VALUES (?, ?, ?, 1, NOW())");
    $stmt->execute([$eventId, trim($name), trim($role)]);
    return (int)get_pdo()->lastInsertId();
}

function update_member(int $id, string $name, string $role, bool $active): void {
    $stmt = get_pdo()->prepare("UPDATE members SET name=?, role=?, active=? WHERE id=?");
    $stmt->execute([trim($name), trim($role), $active ? 1 : 0, $id]);
}

// ── Termin-Funktionen ───────────────────────────────────────

function get_sessions(int $eventId): array {
    $stmt = get_pdo()->prepare("SELECT * FROM sessions WHERE event_id = ? ORDER BY session_date ASC, session_time ASC");
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}

function get_session(int $id): ?array {
    $stmt = get_pdo()->prepare("SELECT * FROM sessions WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function create_session(int $eventId, string $date, string $time, string $comment = ''): int {
    $stmt = get_pdo()->prepare("INSERT INTO sessions (event_id, session_date, session_time, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$eventId, $date, $time, trim($comment)]);
    return (int)get_pdo()->lastInsertId();
}

function update_session(int $id, string $date, string $time, string $comment): void {
    $stmt = get_pdo()->prepare("UPDATE sessions SET session_date=?, session_time=?, comment=? WHERE id=?");
    $stmt->execute([$date, $time, trim($comment), $id]);
}

function delete_session(int $id): void {
    get_pdo()->prepare("DELETE FROM attendance WHERE session_id = ?")->execute([$id]);
    get_pdo()->prepare("DELETE FROM sessions WHERE id = ?")->execute([$id]);
}

// ── Anwesenheits-Funktionen ─────────────────────────────────

function get_attendance_for_session(int $sessionId): array {
    $stmt = get_pdo()->prepare("SELECT a.*, m.name as member_name FROM attendance a JOIN members m ON a.member_id = m.id WHERE a.session_id = ? ORDER BY m.name");
    $stmt->execute([$sessionId]);
    return $stmt->fetchAll();
}

function get_attendance_for_member(int $memberId): array {
    $stmt = get_pdo()->prepare("SELECT a.*, s.session_date, s.session_time, s.comment as session_comment FROM attendance a JOIN sessions s ON a.session_id = s.id WHERE a.member_id = ? ORDER BY s.session_date ASC");
    $stmt->execute([$memberId]);
    return $stmt->fetchAll();
}

function get_all_attendance(int $eventId): array {
    $stmt = get_pdo()->prepare("SELECT a.*, m.name as member_name, s.session_date, s.session_time FROM attendance a JOIN members m ON a.member_id = m.id JOIN sessions s ON a.session_id = s.id WHERE s.event_id = ?");
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}

function set_attendance(int $sessionId, int $memberId, string $status, string $excusedBy = 'admin'): void {
    $existing = get_pdo()->prepare("SELECT id, status FROM attendance WHERE session_id = ? AND member_id = ?");
    $existing->execute([$sessionId, $memberId]);
    $row = $existing->fetch();

    $excusedAt = ($status === 'excused') ? date('Y-m-d H:i:s') : null;

    if ($row) {
        // Preserve member's excused_at if already set by member and admin is also setting excused
        if ($status === 'excused' && $row['status'] === 'excused') {
            $stmt = get_pdo()->prepare("UPDATE attendance SET status=?, excused_by=?, updated_at=NOW() WHERE session_id=? AND member_id=?");
            $stmt->execute([$status, $excusedBy, $sessionId, $memberId]);
        } else {
            $stmt = get_pdo()->prepare("UPDATE attendance SET status=?, excused_at=?, excused_by=?, updated_at=NOW() WHERE session_id=? AND member_id=?");
            $stmt->execute([$status, $excusedAt, $excusedBy, $sessionId, $memberId]);
        }
    } else {
        $stmt = get_pdo()->prepare("INSERT INTO attendance (session_id, member_id, status, excused_at, excused_by, updated_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$sessionId, $memberId, $status, $excusedAt, $excusedBy]);
    }
}

function member_excuse(int $sessionId, int $memberId): array {
    $session = get_session($sessionId);
    if (!$session) return ['success' => false, 'message' => 'Termin nicht gefunden.'];

    // Prüfe ob der Termin-Startzeitpunkt in der Zukunft liegt
    if (!is_session_in_future($session)) {
        return ['success' => false, 'message' => 'Entschuldigung für begonnene oder vergangene Termine nicht möglich.'];
    }

    // Prüfe bestehenden Status
    $stmt = get_pdo()->prepare("SELECT * FROM attendance WHERE session_id = ? AND member_id = ?");
    $stmt->execute([$sessionId, $memberId]);
    $row = $stmt->fetch();

    // Admin hat bereits Anwesenheit/Abwesenheit festgestellt
    if ($row && in_array($row['status'], ['present', 'absent']) && $row['excused_by'] === 'admin') {
        return ['success' => false, 'message' => 'Der Admin hat deinen Status bereits festgelegt. Bitte wende dich an den Administrator.'];
    }

    if ($row && $row['status'] === 'excused') {
        return ['success' => false, 'message' => 'Du bist bereits für diesen Termin entschuldigt.'];
    }

    // Kurzfristig-Warnung prüfen (< 1 Stunde)
    $sessionDateTime = new DateTime($session['session_date'] . ' ' . $session['session_time']);
    $now = new DateTime();
    $diff = $now->diff($sessionDateTime);
    $totalMinutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
    $shortNotice = ($totalMinutes < 60 && !$diff->invert);

    // Entschuldigung eintragen
    $excusedAt = date('Y-m-d H:i:s');
    if ($row) {
        $stmt = get_pdo()->prepare("UPDATE attendance SET status='excused', excused_at=?, excused_by='member', updated_at=NOW() WHERE session_id=? AND member_id=?");
        $stmt->execute([$excusedAt, $sessionId, $memberId]);
    } else {
        $stmt = get_pdo()->prepare("INSERT INTO attendance (session_id, member_id, status, excused_at, excused_by, updated_at) VALUES (?, ?, 'excused', ?, 'member', NOW())");
        $stmt->execute([$sessionId, $memberId, $excusedAt]);
    }

    return [
        'success' => true,
        'short_notice' => $shortNotice,
        'excused_at' => $excusedAt,
        'message' => $shortNotice
            ? '⚠️ Achtung: Deine Absage erfolgt kurzfristig (weniger als 1 Stunde vor Übungsbeginn). Gemäß Strafenkatalog können hierfür 2€ anfallen.'
            : 'Entschuldigung erfolgreich eingetragen.'
    ];
}

function member_withdraw_excuse(int $sessionId, int $memberId): array {
    $session = get_session($sessionId);
    if (!$session) return ['success' => false, 'message' => 'Termin nicht gefunden.'];

    if (!is_session_in_future($session)) {
        return ['success' => false, 'message' => 'Entschuldigung für begonnene oder vergangene Termine kann nicht zurückgezogen werden.'];
    }

    $stmt = get_pdo()->prepare("SELECT * FROM attendance WHERE session_id = ? AND member_id = ?");
    $stmt->execute([$sessionId, $memberId]);
    $row = $stmt->fetch();

    if (!$row || $row['status'] !== 'excused') {
        return ['success' => false, 'message' => 'Es liegt keine Entschuldigung vor, die zurückgezogen werden kann.'];
    }

    if ($row['excused_by'] === 'admin') {
        return ['success' => false, 'message' => 'Vom Admin gesetzte Entschuldigungen können nur vom Admin geändert werden.'];
    }

    // Entschuldigung zurückziehen (Eintrag löschen)
    $stmt = get_pdo()->prepare("DELETE FROM attendance WHERE session_id = ? AND member_id = ?");
    $stmt->execute([$sessionId, $memberId]);

    return [
        'success' => true,
        'message' => 'Entschuldigung zurückgezogen. Du bist wieder als teilnehmend eingetragen.'
    ];
}

// ── Teilnahme-Statistiken ───────────────────────────────────

function get_member_stats(int $eventId): array {
    $members = get_members($eventId);
    $sessions = get_sessions($eventId);
    $pastSessions = array_filter($sessions, fn($s) => $s['session_date'] <= date('Y-m-d'));
    $totalPast = count($pastSessions);

    $stats = [];
    foreach ($members as $m) {
        $att = get_attendance_for_member($m['id']);
        $present = count(array_filter($att, fn($a) => $a['status'] === 'present'));
        $excused = count(array_filter($att, fn($a) => $a['status'] === 'excused'));
        $absent = count(array_filter($att, fn($a) => $a['status'] === 'absent'));
        $quote = $totalPast > 0 ? round(($present / $totalPast) * 100, 1) : 0;

        $stats[] = array_merge($m, [
            'present' => $present,
            'excused' => $excused,
            'absent' => $absent,
            'quote' => $quote,
            'total_past' => $totalPast,
        ]);
    }
    return $stats;
}

function calculate_deadline_status(int $present, int $required, string $deadlineDate, int $totalSessions, int $pastSessions, int $totalRemaining): array {
    $now = new DateTime();
    $deadline = new DateTime($deadlineDate);

    if ($present >= $required) {
        return ['status' => 'achieved', 'icon' => '✅', 'class' => 'text-green-600 bg-green-50'];
    }

    if ($deadline < $now) {
        // Frist abgelaufen
        return ['status' => 'failed', 'icon' => '❌', 'class' => 'text-red-600 bg-red-50'];
    }

    $needed = $required - $present;
    if ($needed <= $totalRemaining) {
        // Prüfe ob noch realistisch (80% der verbleibenden Termine nötig = gelb)
        $ratio = $totalRemaining > 0 ? $needed / $totalRemaining : 1;
        if ($ratio > 0.9) {
            return ['status' => 'critical', 'icon' => '⚠️', 'class' => 'text-yellow-600 bg-yellow-50'];
        }
        return ['status' => 'on_track', 'icon' => '✅', 'class' => 'text-green-600 bg-green-50'];
    }

    return ['status' => 'impossible', 'icon' => '❌', 'class' => 'text-red-600 bg-red-50'];
}

// ── Strafen-Funktionen ──────────────────────────────────────

function get_penalty_types(int $eventId, bool $activeOnly = false): array {
    $sql = "SELECT * FROM penalty_types WHERE event_id = ?";
    if ($activeOnly) $sql .= " AND active = 1";
    $sql .= " ORDER BY sort_order ASC, id ASC";
    $stmt = get_pdo()->prepare($sql);
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}

function create_penalty_type(int $eventId, string $description, float $amount, ?string $activeFrom = null, int $sortOrder = 0): int {
    $stmt = get_pdo()->prepare("INSERT INTO penalty_types (event_id, description, amount, active_from, active, sort_order) VALUES (?, ?, ?, ?, 1, ?)");
    $stmt->execute([$eventId, $description, $amount, $activeFrom, $sortOrder]);
    return (int)get_pdo()->lastInsertId();
}

function update_penalty_type(int $id, string $description, float $amount, ?string $activeFrom, bool $active, int $sortOrder): void {
    $stmt = get_pdo()->prepare("UPDATE penalty_types SET description=?, amount=?, active_from=?, active=?, sort_order=? WHERE id=?");
    $stmt->execute([$description, $amount, $activeFrom, $active ? 1 : 0, $sortOrder, $id]);
}

function delete_penalty_type(int $id): void {
    get_pdo()->prepare("DELETE FROM penalty_types WHERE id = ?")->execute([$id]);
}

function get_penalties_for_member(int $memberId): array {
    $stmt = get_pdo()->prepare("SELECT p.*, pt.description as type_description, pt.amount FROM penalties p JOIN penalty_types pt ON p.penalty_type_id = pt.id WHERE p.member_id = ? AND p.deleted_at IS NULL ORDER BY p.penalty_date DESC");
    $stmt->execute([$memberId]);
    return $stmt->fetchAll();
}

function get_penalties_for_event(int $eventId): array {
    $stmt = get_pdo()->prepare("SELECT p.*, pt.description as type_description, pt.amount, m.name as member_name FROM penalties p JOIN penalty_types pt ON p.penalty_type_id = pt.id JOIN members m ON p.member_id = m.id WHERE m.event_id = ? AND p.deleted_at IS NULL ORDER BY p.penalty_date DESC");
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}

function get_member_penalty_total(int $memberId): float {
    $stmt = get_pdo()->prepare("SELECT COALESCE(SUM(pt.amount), 0) as total FROM penalties p JOIN penalty_types pt ON p.penalty_type_id = pt.id WHERE p.member_id = ? AND p.deleted_at IS NULL");
    $stmt->execute([$memberId]);
    return (float)$stmt->fetchColumn();
}

function get_event_penalty_total(int $eventId): float {
    $stmt = get_pdo()->prepare("SELECT COALESCE(SUM(pt.amount), 0) as total FROM penalties p JOIN penalty_types pt ON p.penalty_type_id = pt.id JOIN members m ON p.member_id = m.id WHERE m.event_id = ? AND p.deleted_at IS NULL");
    $stmt->execute([$eventId]);
    return (float)$stmt->fetchColumn();
}

function create_penalty(int $memberId, int $penaltyTypeId, string $date, string $comment = ''): int {
    $stmt = get_pdo()->prepare("INSERT INTO penalties (member_id, penalty_type_id, penalty_date, comment, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$memberId, $penaltyTypeId, $date, $comment]);
    return (int)get_pdo()->lastInsertId();
}

function delete_penalty(int $id): void {
    $stmt = get_pdo()->prepare("UPDATE penalties SET deleted_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);
}

// ── Audit-Log ───────────────────────────────────────────────

function audit_log(int $eventId, ?int $memberId, string $actionType, string $description): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = get_pdo()->prepare("INSERT INTO audit_log (event_id, member_id, action_type, action_description, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$eventId, $memberId, $actionType, $description, $ip]);
}

function get_audit_log(int $eventId, ?string $actionType = null, ?int $memberId = null, int $limit = 100): array {
    $sql = "SELECT al.*, m.name as member_name FROM audit_log al LEFT JOIN members m ON al.member_id = m.id WHERE al.event_id = ?";
    $params = [$eventId];

    if ($actionType) {
        $sql .= " AND al.action_type = ?";
        $params[] = $actionType;
    }
    if ($memberId) {
        $sql .= " AND al.member_id = ?";
        $params[] = $memberId;
    }

    $sql .= " ORDER BY al.created_at DESC LIMIT ?";
    $params[] = $limit;

    $stmt = get_pdo()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// ── Penalty-Statistiken ─────────────────────────────────────

function get_penalty_stats_by_type(int $eventId): array {
    $stmt = get_pdo()->prepare("SELECT pt.description, pt.amount, COUNT(p.id) as count, COALESCE(SUM(CASE WHEN p.id IS NOT NULL THEN pt.amount ELSE 0 END), 0) as total FROM penalty_types pt LEFT JOIN penalties p ON pt.id = p.penalty_type_id AND p.deleted_at IS NULL WHERE pt.event_id = ? GROUP BY pt.id ORDER BY count DESC, pt.sort_order ASC");
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}

function get_penalty_stats_by_member(int $eventId): array {
    $stmt = get_pdo()->prepare("SELECT m.name, m.id, COALESCE(SUM(pt.amount), 0) as total, COUNT(p.id) as count FROM members m LEFT JOIN penalties p ON m.id = p.member_id AND p.deleted_at IS NULL LEFT JOIN penalty_types pt ON p.penalty_type_id = pt.id WHERE m.event_id = ? AND m.active = 1 GROUP BY m.id ORDER BY total DESC");
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}
