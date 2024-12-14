<?php
require_once '../../config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Check authentication
$token = $_COOKIE['auth'] ?? null;
if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Validate user and team number
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

if ($authHttpCode === 401) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($authHttpCode !== 200) {
    http_response_code($authHttpCode);
    echo json_encode(['error' => 'Authentication failed']);
    exit;
}

$eventId = $_GET['event'] ?? null;

if (!$eventId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing event ID']);
    exit;
}

// Remove any unwanted characters
$eventId = preg_replace('/[^a-zA-Z0-9]/', '', $eventId);

$season = date("Y");
$url = "https://ftc-api.firstinspires.org/v2.0/$season/teams?null=null&eventCode=$eventId";

// Create authorization string from config values
$auth = base64_encode($username . ':' . $password);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    "Authorization: Basic $auth"
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo json_encode(['error' => 'Failed to fetch teams']);
    exit;
}

$data = json_decode($response, true);
$teams = array_map(function($team) {
    return $team['teamNumber'];
}, $data['teams'] ?? []);

echo json_encode(['teams' => $teams]);
?>