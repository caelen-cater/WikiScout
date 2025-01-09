<?php
require_once '../../config.php';

$server = $servers[array_rand($servers)];

function logError($message, $code, $trace, $userId, $ip, $agent, $deviceInfo, $requestUrl, $requestMethod, $requestHeaders, $requestParameters, $requestBody, $metadata, $severity) {
    global $server, $apikey, $webhook;

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
        'request_headers' => $requestHeaders,
        'request_parameters' => $requestParameters,
        'request_body' => $requestBody,
        'metadata' => $metadata,
        'severity' => $severity
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://$server/v2/data/error/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $apikey",
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($errorData));
    curl_exec($ch);
    curl_close($ch);

    // Send webhook notification
    $webhookContent = str_replace(
        [':error_id', ':trace', ':user_id', ':message', ':code', ':timestamp'],
        [uniqid(), $trace, $userId, $message, $code, date('c')],
        "An error (:error_id) occurred with :trace by user :user_id with error ':message' and code :code at :timestamp"
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhook);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['content' => $webhookContent]));
    curl_exec($ch);
    curl_close($ch);
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Check authentication
$token = $_COOKIE['auth'] ?? null;
if (!$token) {
    logError('Unauthorized', 401, __FILE__ . ':' . __LINE__, null, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], '', $_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD'], getallheaders(), $_GET, file_get_contents('php://input'), [], 'high');
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get event code from query parameters
$eventCode = $_GET['event'] ?? null;
if (!$eventCode) {
    logError('Event code is required', 400, __FILE__ . ':' . __LINE__, null, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], '', $_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD'], getallheaders(), $_GET, file_get_contents('php://input'), [], 'medium');
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
    logError('Authentication failed', $authHttpCode, __FILE__ . ':' . __LINE__, null, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], '', $_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD'], getallheaders(), $_GET, file_get_contents('php://input'), [], 'high');
    http_response_code($authHttpCode);
    echo json_encode(['error' => 'Authentication failed']);
    exit;
}

$userData = json_decode($authResponse, true);
$userId = $userData['user']['id'];

// Create authorization string for FIRST API
$auth = base64_encode($username . ':' . $password);

// Determine season year based on current month
$currentMonth = (int)date('n'); // 1-12
$currentYear = (int)date('Y');
$seasonYear = ($currentMonth >= 9) ? $currentYear : $currentYear - 1;

// Setup FIRST API request for rankings
$rankingsUrl = "https://ftc-api.firstinspires.org/v2.0/$seasonYear/rankings/$eventCode?teamNumber=0&top=0";
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
    logError('Failed to fetch rankings', $httpCode, __FILE__ . ':' . __LINE__, $userId, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], '', $_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD'], getallheaders(), $_GET, file_get_contents('php://input'), ['eventCode' => $eventCode, 'seasonYear' => $seasonYear], 'medium');
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