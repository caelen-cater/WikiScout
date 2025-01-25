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
        'severity' => $severity,
        'webhook_url' => $webhook,
        'webhook_content' => "An error (:error_id) occurred with :trace by user :user_id with error ':message' and code :code at :timestamp"
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

// Remove FIRST API auth and request code, replace with static data
$rankings = [
    ["rank" => 1, "teamNumber" => 33, "teamName" => "Team 33", "wins" => 0, "losses" => 5, "ties" => 1, "matchesPlayed" => 6],
    ["rank" => 2, "teamNumber" => 28, "teamName" => "Team 28", "wins" => 4, "losses" => 2, "ties" => 0, "matchesPlayed" => 6],
    ["rank" => 3, "teamNumber" => 42, "teamName" => "Team 42", "wins" => 1, "losses" => 0, "ties" => 5, "matchesPlayed" => 6],
    ["rank" => 4, "teamNumber" => 9, "teamName" => "Team 9", "wins" => 4, "losses" => 2, "ties" => 0, "matchesPlayed" => 6],
    ["rank" => 5, "teamNumber" => 50, "teamName" => "Team 50", "wins" => 5, "losses" => 0, "ties" => 1, "matchesPlayed" => 6],
    ["rank" => 6, "teamNumber" => 36, "teamName" => "Team 36", "wins" => 2, "losses" => 3, "ties" => 1, "matchesPlayed" => 6],
    ["rank" => 7, "teamNumber" => 24, "teamName" => "Team 24", "wins" => 3, "losses" => 1, "ties" => 2, "matchesPlayed" => 6],
    ["rank" => 8, "teamNumber" => 4, "teamName" => "Team 4", "wins" => 4, "losses" => 1, "ties" => 1, "matchesPlayed" => 6],
    ["rank" => 9, "teamNumber" => 2, "teamName" => "Team 2", "wins" => 1, "losses" => 5, "ties" => 0, "matchesPlayed" => 6],
    ["rank" => 10, "teamNumber" => 20, "teamName" => "Team 20", "wins" => 4, "losses" => 1, "ties" => 1, "matchesPlayed" => 6],
    ["rank" => 11, "teamNumber" => 40, "teamName" => "Team 40", "wins" => 6, "losses" => 0, "ties" => 0, "matchesPlayed" => 6],
    ["rank" => 12, "teamNumber" => 18, "teamName" => "Team 18", "wins" => 4, "losses" => 0, "ties" => 2, "matchesPlayed" => 6],
    ["rank" => 13, "teamNumber" => 37, "teamName" => "Team 37", "wins" => 5, "losses" => 0, "ties" => 1, "matchesPlayed" => 6],
    ["rank" => 14, "teamNumber" => 45, "teamName" => "Team 45", "wins" => 0, "losses" => 0, "ties" => 6, "matchesPlayed" => 6],
    ["rank" => 15, "teamNumber" => 30, "teamName" => "Team 30", "wins" => 0, "losses" => 2, "ties" => 4, "matchesPlayed" => 6],
    ["rank" => 16, "teamNumber" => 26, "teamName" => "Team 26", "wins" => 1, "losses" => 0, "ties" => 5, "matchesPlayed" => 6],
    ["rank" => 17, "teamNumber" => 41, "teamName" => "Team 41", "wins" => 0, "losses" => 3, "ties" => 3, "matchesPlayed" => 6],
    ["rank" => 18, "teamNumber" => 14, "teamName" => "Team 14", "wins" => 5, "losses" => 1, "ties" => 0, "matchesPlayed" => 6],
    ["rank" => 19, "teamNumber" => 31, "teamName" => "Team 31", "wins" => 0, "losses" => 3, "ties" => 3, "matchesPlayed" => 6],
    ["rank" => 20, "teamNumber" => 43, "teamName" => "Team 43", "wins" => 0, "losses" => 5, "ties" => 1, "matchesPlayed" => 6],
    ["rank" => 21, "teamNumber" => 22, "teamName" => "Team 22", "wins" => 1, "losses" => 1, "ties" => 4, "matchesPlayed" => 6],
    ["rank" => 22, "teamNumber" => 15, "teamName" => "Team 15", "wins" => 6, "losses" => 0, "ties" => 0, "matchesPlayed" => 6],
    ["rank" => 23, "teamNumber" => 8, "teamName" => "Team 8", "wins" => 1, "losses" => 2, "ties" => 3, "matchesPlayed" => 6],
    ["rank" => 24, "teamNumber" => 25, "teamName" => "Team 25", "wins" => 0, "losses" => 6, "ties" => 0, "matchesPlayed" => 6],
    ["rank" => 25, "teamNumber" => 38, "teamName" => "Team 38", "wins" => 1, "losses" => 2, "ties" => 3, "matchesPlayed" => 6],
    ["rank" => 26, "teamNumber" => 1, "teamName" => "Team 1", "wins" => 5, "losses" => 0, "ties" => 1, "matchesPlayed" => 6],
    ["rank" => 27, "teamNumber" => 10, "teamName" => "Team 10", "wins" => 4, "losses" => 0, "ties" => 2, "matchesPlayed" => 6],
    ["rank" => 28, "teamNumber" => 34, "teamName" => "Team 34", "wins" => 0, "losses" => 6, "ties" => 0, "matchesPlayed" => 6],
    ["rank" => 29, "teamNumber" => 21, "teamName" => "Team 21", "wins" => 4, "losses" => 2, "ties" => 0, "matchesPlayed" => 6],
    ["rank" => 30, "teamNumber" => 32, "teamName" => "Team 32", "wins" => 4, "losses" => 0, "ties" => 2, "matchesPlayed" => 6],
    ["rank" => 31, "teamNumber" => 44, "teamName" => "Team 44", "wins" => 2, "losses" => 3, "ties" => 1, "matchesPlayed" => 6],
    ["rank" => 32, "teamNumber" => 16, "teamName" => "Team 16", "wins" => 3, "losses" => 1, "ties" => 2, "matchesPlayed" => 6],
    ["rank" => 33, "teamNumber" => 27, "teamName" => "Team 27", "wins" => 2, "losses" => 4, "ties" => 0, "matchesPlayed" => 6],
    ["rank" => 34, "teamNumber" => 39, "teamName" => "Team 39", "wins" => 1, "losses" => 3, "ties" => 2, "matchesPlayed" => 6],
    ["rank" => 35, "teamNumber" => 13, "teamName" => "Team 13", "wins" => 5, "losses" => 0, "ties" => 1, "matchesPlayed" => 6],
    ["rank" => 36, "teamNumber" => 12, "teamName" => "Team 12", "wins" => 4, "losses" => 1, "ties" => 1, "matchesPlayed" => 6],
    ["rank" => 37, "teamNumber" => 19, "teamName" => "Team 19", "wins" => 1, "losses" => 5, "ties" => 0, "matchesPlayed" => 6],
    ["rank" => 38, "teamNumber" => 6, "teamName" => "Team 6", "wins" => 0, "losses" => 4, "ties" => 2, "matchesPlayed" => 6],
    ["rank" => 39, "teamNumber" => 11, "teamName" => "Team 11", "wins" => 3, "losses" => 3, "ties" => 0, "matchesPlayed" => 6],
    ["rank" => 40, "teamNumber" => 35, "teamName" => "Team 35", "wins" => 2, "losses" => 2, "ties" => 2, "matchesPlayed" => 6],
    ["rank" => 41, "teamNumber" => 23, "teamName" => "Team 23", "wins" => 1, "losses" => 4, "ties" => 1, "matchesPlayed" => 6],
    ["rank" => 42, "teamNumber" => 29, "teamName" => "Team 29", "wins" => 4, "losses" => 1, "ties" => 1, "matchesPlayed" => 6],
    ["rank" => 43, "teamNumber" => 3, "teamName" => "Team 3", "wins" => 2, "losses" => 3, "ties" => 1, "matchesPlayed" => 6],
    ["rank" => 44, "teamNumber" => 17, "teamName" => "Team 17", "wins" => 3, "losses" => 2, "ties" => 1, "matchesPlayed" => 6],
    ["rank" => 45, "teamNumber" => 7, "teamName" => "Team 7", "wins" => 0, "losses" => 3, "ties" => 3, "matchesPlayed" => 6],
    ["rank" => 46, "teamNumber" => 48, "teamName" => "Team 48", "wins" => 1, "losses" => 1, "ties" => 4, "matchesPlayed" => 6],
    ["rank" => 47, "teamNumber" => 46, "teamName" => "Team 46", "wins" => 2, "losses" => 4, "ties" => 0, "matchesPlayed" => 6],
    ["rank" => 48, "teamNumber" => 5, "teamName" => "Team 5", "wins" => 0, "losses" => 5, "ties" => 1, "matchesPlayed" => 6],
    ["rank" => 49, "teamNumber" => 49, "teamName" => "Team 49", "wins" => 0, "losses" => 5, "ties" => 1, "matchesPlayed" => 6],
    ["rank" => 50, "teamNumber" => 6, "teamName" => "Team 6", "wins" => 0, "losses" => 5, "ties" => 1, "matchesPlayed" => 6]
];

echo json_encode([
    'rankings' => $rankings,
    'count' => count($rankings)
]);
?>