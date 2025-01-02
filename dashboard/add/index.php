<?php
require_once '../../config.php';

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

// Remove new lines from input data
$teamNumber = str_replace(["\r", "\n"], '', $teamNumber);
$eventId = str_replace(["\r", "\n"], '', $eventId);
$data = str_replace(["\r", "\n"], '', $data);

$token = $_COOKIE['auth'] ?? null;
if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

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

// Read form configuration
$formConfig = file_get_contents('../../form.dat');
$formFields = explode("\n", $formConfig);
$privateFieldIndexes = [];

// Identify private fields
$fieldIndex = 0;
foreach ($formFields as $field) {
    if (strpos($field, 'private') !== false) {
        $privateFieldIndexes[] = $fieldIndex;
    }
    $fieldIndex++;
}

// Process the data - split and remove event code if present
$dataFields = explode('|', $data);
if (strpos($dataFields[0], $eventId) !== false) {
    array_shift($dataFields);
}
$data = implode('|', $dataFields);
$publicData = $dataFields;

// Replace private fields with placeholder
foreach ($privateFieldIndexes as $index) {
    if (isset($publicData[$index])) {
        $publicData[$index] = "Redacted Field";
    }
}

$currentMonth = (int)date('n'); // 1-12
$currentYear = (int)date('Y');
$seasonYear = ($currentMonth >= 9) ? $currentYear : $currentYear - 1;

$eventCode = $eventId;

// Save public data
$dbUrl = "https://$server/v2/data/database/";
$dbHeaders = [
    "Authorization: Bearer $apikey"
];
$publicDbData = [
    'db' => "WikiScout-$seasonYear-$eventCode",
    'log' => $teamNumber . "-public",
    'entry' => $scoutingTeamNumber,
    'value' => implode('|', $publicData)
];

$dbCh = curl_init();
curl_setopt($dbCh, CURLOPT_URL, $dbUrl);
curl_setopt($dbCh, CURLOPT_RETURNTRANSFER, true);
curl_setopt($dbCh, CURLOPT_HTTPHEADER, $dbHeaders);
curl_setopt($dbCh, CURLOPT_POST, true);
curl_setopt($dbCh, CURLOPT_POSTFIELDS, http_build_query($publicDbData));

$publicDbResponse = curl_exec($dbCh);
$publicDbHttpCode = curl_getinfo($dbCh, CURLINFO_HTTP_CODE);
curl_close($dbCh);

// Save private data
$privateDbData = [
    'db' => "WikiScout-$seasonYear-$eventCode",
    'log' => $scoutingTeamNumber . "-private",
    'entry' => $teamNumber,
    'value' => $data
];

$dbCh = curl_init();
curl_setopt($dbCh, CURLOPT_URL, $dbUrl);
curl_setopt($dbCh, CURLOPT_RETURNTRANSFER, true);
curl_setopt($dbCh, CURLOPT_HTTPHEADER, $dbHeaders);
curl_setopt($dbCh, CURLOPT_POST, true);
curl_setopt($dbCh, CURLOPT_POSTFIELDS, http_build_query($privateDbData));

$privateDbResponse = curl_exec($dbCh);
$privateDbHttpCode = curl_getinfo($dbCh, CURLINFO_HTTP_CODE);
curl_close($dbCh);

// Return response based on both operations
if ($publicDbHttpCode === 200 && $privateDbHttpCode === 200) {
    http_response_code(200);
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database operation failed',
        'public_status' => $publicDbHttpCode,
        'private_status' => $privateDbHttpCode
    ]);
}
?>