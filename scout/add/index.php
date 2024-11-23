<?php
require_once '../../secrets.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$teamNumber = $_POST['team_number'] ?? null;
$eventId = $_POST['event_id'] ?? null;
$data = $_POST['data'] ?? null;

if (!$teamNumber || !$eventId || !$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$token = $_COOKIE['auth'] ?? null;
if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$authUrl = 'https://api.cirrus.center/v2/auth/user/';
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
    echo $authResponse;
    exit;
}

$authData = json_decode($authResponse, true);
$userId = $authData['user']['id'] ?? null;
$scoutingTeamNumber = $authData['details']['address'] ?? null;

if (!$userId || !$scoutingTeamNumber) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to retrieve user ID or scouting team number']);
    exit;
}

$season = date("Y");
$eventCode = $eventId;

$dbUrl = 'https://api.cirrus.center/v2/data/database/';
$dbHeaders = [
    "Authorization: Bearer $apikey"
];
$dbData = [
    'db' => "WikiScout-$season-$eventCode",
    'log' => $teamNumber,
    'entry' => $scoutingTeamNumber,
    'value' => $data
];

$dbCh = curl_init();
curl_setopt($dbCh, CURLOPT_URL, $dbUrl);
curl_setopt($dbCh, CURLOPT_RETURNTRANSFER, true);
curl_setopt($dbCh, CURLOPT_HTTPHEADER, $dbHeaders);
curl_setopt($dbCh, CURLOPT_POST, true);
curl_setopt($dbCh, CURLOPT_POSTFIELDS, http_build_query($dbData));

$dbResponse = curl_exec($dbCh);
$dbHttpCode = curl_getinfo($dbCh, CURLINFO_HTTP_CODE);
curl_close($dbCh);

http_response_code($dbHttpCode);
echo $dbResponse;
?>
