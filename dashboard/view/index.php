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

// Get parameters
$teamNumber = $_GET['team'] ?? null;
$eventCode = $_GET['event'] ?? null;

if (!$teamNumber || !$eventCode) {
    http_response_code(400);
    echo json_encode(['error' => 'Team number and event code are required']);
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

$authData = json_decode($authResponse, true);
$scoutingTeam = $authData['details']['address'] ?? null;

$season = date("Y");
$dbHeaders = ["Authorization: Bearer $apikey"];

// Fetch private data
$privateDbUrl = "https://$server/v2/data/database/?db=WikiScout-$season-$eventCode&log=$scoutingTeam-private&entry=$teamNumber";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $privateDbUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $dbHeaders);
$privateResponse = curl_exec($ch);
$privateHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Fetch public data
$publicDbUrl = "https://$server/v2/data/database/?db=WikiScout-$season-$eventCode&log=$teamNumber-public";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $publicDbUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $dbHeaders);
$publicResponse = curl_exec($ch);
$publicHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Add error checking
if ($privateHttpCode !== 200 || $publicHttpCode !== 200) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database fetch failed',
        'private_status' => $privateHttpCode,
        'public_status' => $publicHttpCode,
        'private_url' => $privateDbUrl,
        'public_url' => $publicDbUrl
    ]);
    exit;
}

// Read form configuration
$formConfig = file_get_contents('../../form.dat');
$formFields = array_map(function($line) {
    $matches = [];
    preg_match('/"([^"]+)"/', $line, $matches);
    return $matches[1] ?? '';
}, explode("\n", $formConfig));

// Clean and parse responses
function cleanResponse($response) {
    $jsonStart = strpos($response, '{');
    if ($jsonStart !== false) {
        $response = substr($response, $jsonStart);
    }
    return json_decode($response, true);
}

$privateData = cleanResponse($privateResponse);
$publicData = cleanResponse($publicResponse);

echo json_encode([
    'fields' => $formFields,
    'private_data' => cleanResponse($privateResponse),
    'public_data' => cleanResponse($publicResponse)
]);
?>