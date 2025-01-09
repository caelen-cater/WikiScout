<?php
require_once '../../config.php';

$server = $servers[array_rand($servers)];

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Check authentication
$token = $_COOKIE['auth'] ?? null;
if (!$token) {
    logError('Unauthorized', 401, __FILE__, null);
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Validate user and get team number
$authUrl = "https://$server/v2/auth/user/";
$authHeaders = [
    "Authorization: Bearer $apikey",
    "Token: $token"
];

$authResponse = makeApiRequest($authUrl, $authHeaders);
if ($authResponse['http_code'] === 401) {
    logError('Unauthorized', 401, __FILE__, null);
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($authResponse['http_code'] !== 200) {
    logError('Authentication failed', $authResponse['http_code'], __FILE__, null);
    http_response_code($authResponse['http_code']);
    echo json_encode(['error' => 'Authentication failed']);
    exit;
}

$userData = json_decode($authResponse['response'], true);
$userId = $userData['user']['id'] ?? null;
$teamNumber = $userData['details']['address'] ?? null;

if ($teamNumber === null || !is_numeric($teamNumber)) {
    logError('Valid team number not set', 501, __FILE__, $userId);
    http_response_code(501);
    echo json_encode(['error' => 'Valid team number not set']);
    exit;
}

// Determine season year based on current month
$currentMonth = (int)date('n');
$currentYear = (int)date('Y');
$seasonYear = ($currentMonth >= 9) ? $currentYear : $currentYear - 1;

// Create FIRST API auth
$auth = base64_encode($username . ':' . $password);
$firstHeaders = [
    'Accept: application/json',
    "Authorization: Basic $auth"
];

// Get today's events
$eventsUrl = "https://ftc-api.firstinspires.org/v2.0/$seasonYear/events/";
$eventsResponse = makeApiRequest($eventsUrl, $firstHeaders);

if ($eventsResponse['http_code'] !== 200) {
    logError('Failed to fetch events', $eventsResponse['http_code'], __FILE__, $userId);
    http_response_code($eventsResponse['http_code']);
    echo json_encode(['error' => 'Failed to fetch events']);
    exit;
}

$eventsData = json_decode($eventsResponse['response'], true);
$currentTime = time();
$currentEvents = [];

// Filter for current events
foreach ($eventsData['events'] as $event) {
    $startTime = strtotime($event['dateStart']);
    $endTime = strtotime($event['dateEnd']);
    
    if ($currentTime >= $startTime && $currentTime <= $endTime) {
        $currentEvents[] = $event;
    }
}

// Check each current event for the team
foreach ($currentEvents as $event) {
    $teamsUrl = "https://ftc-api.firstinspires.org/v2.0/$seasonYear/teams?eventCode=" . $event['code'];
    $teamsResponse = makeApiRequest($teamsUrl, $firstHeaders);
    
    if ($teamsResponse['http_code'] === 200) {
        $teamsData = json_decode($teamsResponse['response'], true);
        foreach ($teamsData['teams'] as $team) {
            if ($team['teamNumber'] == $teamNumber) {
                // Found the event!
                echo json_encode([
                    'found' => true,
                    'event' => [
                        'code' => $event['code'],
                        'name' => $event['name'],
                        'startDate' => $event['dateStart'],
                        'endDate' => $event['dateEnd']
                    ]
                ]);
                exit;
            }
        }
    }
}

// If we get here, team wasn't found at any current event
echo json_encode([
    'found' => false,
    'message' => 'Team not found at any current event'
]);

function makeApiRequest($url, $headers, $body = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($body) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['response' => $response, 'http_code' => $httpCode];
}

function logError($message, $code, $trace, $userId) {
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
        'severity' => determineSeverity($code)
    ];

    $errorUrl = "https://$server/v2/data/error/";
    $errorHeaders = [
        "Authorization: Bearer $apikey",
        "Content-Type: application/json"
    ];

    makeApiRequest($errorUrl, $errorHeaders, json_encode($errorData));

    // Send webhook notification
    $webhookContent = "An error ({$code}) occurred with {$trace} by user {$userId} with error '{$message}' and code {$code} at " . date('Y-m-d H:i:s');
    makeApiRequest($webhook, $errorHeaders, json_encode(['content' => $webhookContent]));
}

function determineSeverity($code) {
    if ($code >= 500) {
        return 'urgent';
    } elseif ($code >= 400) {
        return 'high';
    } elseif ($code >= 300) {
        return 'medium';
    } else {
        return 'low';
    }
}
?>