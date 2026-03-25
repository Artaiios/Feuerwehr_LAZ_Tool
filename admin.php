<?php
/**
 * LAZ Übungs-Tracker – Server-Admin
 */

require_once __DIR__ . '/db.php';

$token = $_GET['token'] ?? '';
if (!verify_server_admin_token($token)) {
    http_response_code(403);
    $errorMessage = 'Zugriff verweigert. Ungültiger Server-Admin-Token.';
    require __DIR__ . '/views/partials/error.php';
    exit;
}

// Dummy-Event für Header-Partial (Server-Admin hat kein Event)
$event = [
    'name' => 'Server-Administration',
    'public_token' => '',
    'admin_token' => '',
];
$isAdmin = false;
$isServerAdmin = true;
$serverAdminToken = $token;

require __DIR__ . '/views/server_admin.php';
