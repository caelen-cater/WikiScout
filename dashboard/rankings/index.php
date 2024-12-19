<?php
require_once '../../config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Check authentication
$token = $_COOKIE['auth'] ?? null;
if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get event code from query parameters
$eventCode = $_GET['event'] ?? null;
if (!$eventCode) {
    http_response_code(400);
    echo json_encode(['error' => 'Event code is required']);
    exit;
}

// Validate user authentication
$authUrl = "https://$server/v2/auth/user/";
$authHeaders = [
    "Authorization: Bearer $apikey",
    "Token: $token"
];

$authCh = curl_init();
curl_setopt($authCh, CURLOPT_URL, $authUrl);
curl_setopt($authCh, CURLOPT_RETURNTRANSFER, true);
curl_setopt($authCh, CURLOPT_HTTPHEADER, $authHeaders);

$authResponse = curl_exec($authCh);
$authHttpCode = curl_getinfo($authCh, CURLINFO_HTTP_CODE);
curl_close($authCh);

if ($authHttpCode !== 200) {
    http_response_code($authHttpCode);
    echo json_encode(['error' => 'Authentication failed']);
    exit;
}

// Create authorization string for FIRST API
$auth = base64_encode($username . ':' . $password);
$currentYear = date('Y');

// Setup FIRST API request for rankings
$rankingsUrl = "https://ftc-api.firstinspires.org/v2.0/$currentYear/rankings/$eventCode?teamNumber=0&top=0";
$headers = [
    'Accept: application/json',
    "Authorization: Basic $auth"
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $rankingsUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo json_encode(['error' => 'Failed to fetch rankings']);
    exit;
}

$data = json_decode($response, true);
$rankings = array_map(function($team) {
    return [
        'rank' => $team['rank'],
        'teamNumber' => $team['teamNumber'],
        'teamName' => $team['teamName'],
        'wins' => $team['wins'],
        'losses' => $team['losses'],
        'ties' => $team['ties'],
        'matchesPlayed' => $team['matchesPlayed']
    ];
}, $data['rankings']);

echo json_encode([
    'rankings' => $rankings,
    'count' => count($rankings)
]);
?>