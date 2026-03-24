<?php
/**
 * LAZ Übungs-Tracker – Hauptrouter
 */

require_once __DIR__ . '/db.php';

// ── Routing ─────────────────────────────────────────────────

$eventToken = $_GET['event'] ?? '';
$adminToken = $_GET['admin'] ?? '';
$memberId   = isset($_GET['member']) ? (int)$_GET['member'] : 0;

// Kein Event-Token → Fehlerseite
if (empty($eventToken)) {
    http_response_code(404);
    require __DIR__ . '/views/partials/error.php';
    exit;
}

// Event laden
$event = get_event_by_public_token($eventToken);
if (!$event) {
    http_response_code(404);
    $errorMessage = 'Event nicht gefunden oder nicht mehr aktiv.';
    require __DIR__ . '/views/partials/error.php';
    exit;
}

// Admin-Modus prüfen
$isAdmin = false;
if (!empty($adminToken)) {
    $adminEvent = get_event_by_admin_token($eventToken, $adminToken);
    if ($adminEvent) {
        $isAdmin = true;
        $event = $adminEvent; // Admin kann auch archivierte Events sehen
    }
}

// ── View auswählen ──────────────────────────────────────────

if ($isAdmin) {
    require __DIR__ . '/views/admin.php';
} elseif ($memberId > 0) {
    $member = get_member($memberId);
    if (!$member || $member['event_id'] != $event['id']) {
        http_response_code(404);
        $errorMessage = 'Teilnehmer nicht gefunden.';
        require __DIR__ . '/views/partials/error.php';
        exit;
    }
    require __DIR__ . '/views/member.php';
} else {
    require __DIR__ . '/views/dashboard.php';
}
