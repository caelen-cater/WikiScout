<?php
require_once '../../config.php';

$server = $servers[array_rand($servers)];

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Function to send error tracking data
function sendErrorTracking($message, $code, $trace, $userId, $ip, $agent, $deviceInfo, $requestUrl, $requestMethod, $requestHeaders, $requestParameters, $requestBody, $metadata, $severity) {
    global $server, $apikey, $webhook;
    $errorUrl = "https://$server/v2/data/error/";
    $errorData = [
        'message' => $message,
        'code' => $code,
        'trace' => $trace,
        'user_id' => $userId,
        'ip' => $ip,
        'agent' => $agent,
        'device_info' => $deviceInfo,
        'server' => $server,
        'request_url' => $requestUrl,
        'request_method' => $requestMethod,
        'request_headers' => json_encode($requestHeaders),
        'request_parameters' => json_encode($requestParameters),
        'request_body' => $requestBody,
        'metadata' => json_encode($metadata),
        'severity' => $severity,
        'webhook_url' => $webhook,
        'webhook_content' => "An error (:error_id) occurred with :trace by user :user_id with error ':message' and code :code at :timestamp"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $errorUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $apikey", "Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($errorData));
    curl_exec($ch);
    curl_close($ch);
}

// Check authentication
$token = $_COOKIE['auth'] ?? null;
if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    sendErrorTracking(
        'Unauthorized access attempt',
        401,
        __FILE__ . ':' . __LINE__,
        null,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'],
        null,
        $_SERVER['REQUEST_URI'],
        $_SERVER['REQUEST_METHOD'],
        getallheaders(),
        $_GET,
        null,
        ['teamNumber' => $_GET['team'] ?? null, 'eventCode' => $_GET['event'] ?? null],
        'high'
    );
    exit;
}

// Get parameters
$teamNumber = $_GET['team'] ?? null;
$eventCode = $_GET['event'] ?? null;

if (!$teamNumber || !$eventCode) {
    http_response_code(400);
    echo json_encode(['error' => 'Team number and event code are required']);
    sendErrorTracking(
        'Missing team number or event code',
        400,
        __FILE__ . ':' . __LINE__,
        null,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'],
        null,
        $_SERVER['REQUEST_URI'],
        $_SERVER['REQUEST_METHOD'],
        getallheaders(),
        $_GET,
        null,
        ['teamNumber' => $teamNumber, 'eventCode' => $eventCode],
        'medium'
    );
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
    sendErrorTracking(
        'Authentication failed',
        $authHttpCode,
        __FILE__ . ':' . __LINE__,
        null,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'],
        null,
        $_SERVER['REQUEST_URI'],
        $_SERVER['REQUEST_METHOD'],
        getallheaders(),
        $_GET,
        null,
        ['teamNumber' => $teamNumber, 'eventCode' => $eventCode],
        'high'
    );
    exit;
}

$authData = json_decode($authResponse, true);
$userId = $authData['user']['id'] ?? null;
$scoutingTeam = $authData['details']['address'] ?? null;

// Determine season year based on current month
$currentMonth = (int)date('n'); // 1-12
$currentYear = (int)date('Y');
$seasonYear = ($currentMonth >= 9) ? $currentYear : $currentYear - 1;

$dbHeaders = ["Authorization: Bearer $apikey"];

// Fetch private data
$privateDbUrl = "https://$server/v2/data/database/?db=WikiScout-$seasonYear-$eventCode&log=$scoutingTeam-private&entry=$teamNumber";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $privateDbUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $dbHeaders);
$privateResponse = curl_exec($ch);
$privateHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Fetch public data
$publicDbUrl = "https://$server/v2/data/database/?db=WikiScout-$seasonYear-$eventCode&log=$teamNumber-public";
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
    sendErrorTracking(
        'Database fetch failed',
        500,
        __FILE__ . ':' . __LINE__,
        $userId,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT'],
        null,
        $_SERVER['REQUEST_URI'],
        $_SERVER['REQUEST_METHOD'],
        getallheaders(),
        $_GET,
        null,
        ['teamNumber' => $teamNumber, 'eventCode' => $eventCode],
        'urgent'
    );
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

// Filter out entries from the current scouting team from public data
if (is_array($publicData)) {
    $publicData = array_filter($publicData, function($entry) use ($scoutingTeam) {
        return !isset($entry['scout']) || $entry['scout'] !== $scoutingTeam;
    });
}

echo json_encode([
    'fields' => $formFields,
    'private_data' => $privateData,
    'public_data' => $publicData
]);
?>