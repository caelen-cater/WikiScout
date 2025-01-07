<?php
require_once '../../config.php';

function logError($message, $code, $trace, $userId, $severity) {
    global $server, $apikey, $webhook;

    $errorData = [
        'message' => $message,
        'code' => $code,
        'trace' => $trace,
        'user_id' => $userId,
        'ip' => $_SERVER['REMOTE_ADDR'],
        'agent' => $_SERVER['HTTP_USER_AGENT'],
        'device_info' => php_uname(),
        'server' => $_SERVER['SERVER_NAME'],
        'request_url' => $_SERVER['REQUEST_URI'],
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'request_headers' => getallheaders(),
        'request_parameters' => $_GET,
        'request_body' => file_get_contents('php://input'),
        'metadata' => [
            'team_number' => $GLOBALS['teamNumber'] ?? null,
            'season_year' => $GLOBALS['seasonYear'] ?? null
        ],
        'severity' => $severity
    ];

    $ch = curl_init("https://$server/v2/data/error/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $apikey",
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($errorData));
    curl_exec($ch);
    curl_close($ch);

    // Send webhook notification
    $webhookContent = "An error ({$errorData['code']}) occurred with {$errorData['trace']} by user {$errorData['user_id']} with error '{$errorData['message']}' and code {$errorData['code']} at " . date('Y-m-d H:i:s');
    $ch = curl_init($webhook);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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
    logError('Unauthorized', 401, __FILE__ . ':' . __LINE__, null, 'high');
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
    logError('Unauthorized', 401, __FILE__ . ':' . __LINE__, null, 'high');
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($authHttpCode !== 200) {
    logError('Authentication failed', $authHttpCode, __FILE__ . ':' . __LINE__, null, 'high');
    http_response_code($authHttpCode);
    echo json_encode(['error' => 'Authentication failed']);
    exit;
}

$authData = json_decode($authResponse, true);
$userId = $authData['user']['id'] ?? null;
$teamNumber = $authData['details']['address'] ?? null;

if ($teamNumber === null) {
    logError('Team number not set', 501, __FILE__ . ':' . __LINE__, $userId, 'medium');
    http_response_code(501);
    echo json_encode(['error' => 'Team number not set']);
    exit;
}

// Determine season year based on current month
$currentMonth = (int)date('n'); // 1-12
$currentYear = (int)date('Y');
$seasonYear = ($currentMonth >= 9) ? $currentYear : $currentYear - 1;

// Create authorization string
$auth = base64_encode($username . ':' . $password);

// Setup FIRST API request
$firstApiUrl = "https://ftc-api.firstinspires.org/v2.0/$seasonYear/events/";
$headers = [
    'Accept: application/json',
    "Authorization: Basic $auth"
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $firstApiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    logError('Failed to fetch events', $httpCode, __FILE__ . ':' . __LINE__, $userId, 'high');
    http_response_code($httpCode);
    echo json_encode(['error' => 'Failed to fetch events']);
    exit;
}

$data = json_decode($response, true);
$currentTime = time();
$currentEvents = [];

// Filter for current events
foreach ($data['events'] as $event) {
    $startTime = strtotime($event['dateStart']);
    $endTime = strtotime($event['dateEnd']);
    
    if ($currentTime >= $startTime && $currentTime <= $endTime) {
        $currentEvents[] = [
            'code' => $event['code'],
            'name' => $event['name']
        ];
    }
}

echo json_encode([
    'events' => $currentEvents,
    'count' => count($currentEvents)
]);
?>