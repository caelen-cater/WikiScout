<?php
require_once '../../config.php';

$server = $servers[array_rand($servers)];

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

function logError($message, $code, $trace, $userId, $severity, $metadata) {
    global $server, $apikey, $webhook;

    // Remove backslashes from webhook URL
    $cleanWebhook = str_replace('\\', '', $webhook);

    $errorData = [
        'message' => $message,
        'code' => $code,
        'trace' => $trace,
        'user_id' => $userId,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'device_info' => php_uname(),
        'server' => $_SERVER['SERVER_NAME'] ?? 'unknown',
        'request_url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'request_headers' => json_encode(getallheaders()),
        'request_parameters' => json_encode($_GET),
        'request_body' => file_get_contents('php://input'),
        'metadata' => json_encode($metadata),
        'severity' => $severity,
        'webhook_url' => addslashes($cleanWebhook),
        'webhook_content' => "An error (:error_id) occurred with :trace by user :user_id with error ':message' and code :code at :timestamp"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://$server/v2/data/error/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $apikey"]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($errorData));

    curl_exec($ch);
    curl_close($ch);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    logError('Method Not Allowed', 405, __FILE__ . ':' . __LINE__, null, 'medium', []);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$teamNumber = $_POST['team_number'] ?? null;
$eventId = $_POST['event_id'] ?? null;
$data = $_POST['data'] ?? null;

if (!$teamNumber || !$eventId || !$data) {
    http_response_code(400);
    logError('Missing parameters', 400, __FILE__ . ':' . __LINE__, null, 'medium', compact('teamNumber', 'eventId', 'data'));
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
    logError('Unauthorized', 401, __FILE__ . ':' . __LINE__, null, 'high', []);
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
    logError('Authentication failed', $authHttpCode, __FILE__ . ':' . __LINE__, null, 'high', []);
    echo $authResponse;
    exit;
}

$authData = json_decode($authResponse, true);
$userId = $authData['user']['id'] ?? null;
$scoutingTeamNumber = $authData['details']['address'] ?? null;

if (!$userId || !$scoutingTeamNumber) {
    http_response_code(500);
    logError('Failed to retrieve user ID or scouting team number', 500, __FILE__ . ':' . __LINE__, $userId, 'urgent', []);
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
    logError('Database operation failed', 500, __FILE__ . ':' . __LINE__, $userId, 'urgent', [
        'public_status' => $publicDbHttpCode,
        'private_status' => $privateDbHttpCode
    ]);
    echo json_encode([
        'error' => 'Database operation failed',
        'public_status' => $publicDbHttpCode,
        'private_status' => $privateDbHttpCode
    ]);
}
?>