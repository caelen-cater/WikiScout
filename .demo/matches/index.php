<?php
require_once '../../config.php';

$server = $servers[array_rand($servers)];

function sendErrorTracking($message, $code, $trace, $userId, $ip, $agent, $deviceInfo, $requestUrl, $requestMethod, $requestHeaders, $requestParameters, $requestBody, $metadata, $severity) {
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
        "Content-Type: application/json"
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
    http_response_code(401);
    sendErrorTracking('Unauthorized', 401, __FILE__ . ':' . __LINE__, null, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], null, $_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD'], getallheaders(), $_GET, null, null, 'high');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get event code from query parameters
$eventCode = $_GET['event'] ?? null;
if (!$eventCode) {
    http_response_code(400);
    sendErrorTracking('Event code is required', 400, __FILE__ . ':' . __LINE__, null, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], null, $_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD'], getallheaders(), $_GET, null, null, 'medium');
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
    http_response_code($authHttpCode);
    sendErrorTracking('Authentication failed', $authHttpCode, __FILE__ . ':' . __LINE__, null, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], null, $_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD'], getallheaders(), $_GET, null, null, 'high');
    echo json_encode(['error' => 'Authentication failed']);
    exit;
}

$userData = json_decode($authResponse, true);
$userId = $userData['user']['id'];

// Replace FIRST API auth and requests with complete demo data

// Helper function to get next team number
function getNextTeamNumber($currentNumber) {
    return ($currentNumber % 50) + 1;
}

$demoMatches = [
    [
        'description' => 'Qualification 1', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 1,
        'scoreRedFinal' => 38, 'scoreRedAuto' => 0, 'scoreRedFoul' => 20,
        'scoreBlueFinal' => 42, 'scoreBlueAuto' => 0, 'scoreBlueFoul' => 5,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '1'],
            ['station' => 'Red2', 'teamNumber' => '2'],
            ['station' => 'Blue1', 'teamNumber' => '3'],
            ['station' => 'Blue2', 'teamNumber' => '4']
        ]
    ],
    [
        'description' => 'Qualification 2', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 2,
        'scoreRedFinal' => 105, 'scoreRedAuto' => 8, 'scoreRedFoul' => 20,
        'scoreBlueFinal' => 43, 'scoreBlueAuto' => 0, 'scoreBlueFoul' => 30,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '5'],
            ['station' => 'Red2', 'teamNumber' => '6'],
            ['station' => 'Blue1', 'teamNumber' => '7'],
            ['station' => 'Blue2', 'teamNumber' => '8']
        ]
    ],
    [
        'description' => 'Qualification 3', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 3,
        'scoreRedFinal' => 48, 'scoreRedAuto' => 4, 'scoreRedFoul' => 20,
        'scoreBlueFinal' => 160, 'scoreBlueAuto' => 10, 'scoreBlueFoul' => 0,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '9'],
            ['station' => 'Red2', 'teamNumber' => '10'],
            ['station' => 'Blue1', 'teamNumber' => '11'],
            ['station' => 'Blue2', 'teamNumber' => '12']
        ]
    ],
    [
        'description' => 'Qualification 4', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 4,
        'scoreRedFinal' => 74, 'scoreRedAuto' => 0, 'scoreRedFoul' => 0,
        'scoreBlueFinal' => 30, 'scoreBlueAuto' => 0, 'scoreBlueFoul' => 10,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '13'],
            ['station' => 'Red2', 'teamNumber' => '14'],
            ['station' => 'Blue1', 'teamNumber' => '15'],
            ['station' => 'Blue2', 'teamNumber' => '16']
        ]
    ],
    [
        'description' => 'Qualification 5', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 5,
        'scoreRedFinal' => 20, 'scoreRedAuto' => 0, 'scoreRedFoul' => 5,
        'scoreBlueFinal' => 87, 'scoreBlueAuto' => 10, 'scoreBlueFoul' => 0,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '17'],
            ['station' => 'Red2', 'teamNumber' => '18'],
            ['station' => 'Blue1', 'teamNumber' => '19'],
            ['station' => 'Blue2', 'teamNumber' => '20']
        ]
    ],
    [
        'description' => 'Qualification 6', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 6,
        'scoreRedFinal' => 48, 'scoreRedAuto' => 4, 'scoreRedFoul' => 0,
        'scoreBlueFinal' => 78, 'scoreBlueAuto' => 10, 'scoreBlueFoul' => 10,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '21'],
            ['station' => 'Red2', 'teamNumber' => '22'],
            ['station' => 'Blue1', 'teamNumber' => '23'],
            ['station' => 'Blue2', 'teamNumber' => '24']
        ]
    ],
    [
        'description' => 'Qualification 7', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 7,
        'scoreRedFinal' => 98, 'scoreRedAuto' => 0, 'scoreRedFoul' => 30,
        'scoreBlueFinal' => 78, 'scoreBlueAuto' => 0, 'scoreBlueFoul' => 35,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '25'],
            ['station' => 'Red2', 'teamNumber' => '26'],
            ['station' => 'Blue1', 'teamNumber' => '27'],
            ['station' => 'Blue2', 'teamNumber' => '28']
        ]
    ],
    [
        'description' => 'Qualification 8', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 8,
        'scoreRedFinal' => 33, 'scoreRedAuto' => 0, 'scoreRedFoul' => 0,
        'scoreBlueFinal' => 43, 'scoreBlueAuto' => 3, 'scoreBlueFoul' => 20,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '29'],
            ['station' => 'Red2', 'teamNumber' => '30'],
            ['station' => 'Blue1', 'teamNumber' => '31'],
            ['station' => 'Blue2', 'teamNumber' => '32']
        ]
    ],
    [
        'description' => 'Qualification 9', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 9,
        'scoreRedFinal' => 128, 'scoreRedAuto' => 4, 'scoreRedFoul' => 5,
        'scoreBlueFinal' => 39, 'scoreBlueAuto' => 0, 'scoreBlueFoul' => 15,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '33'],
            ['station' => 'Red2', 'teamNumber' => '34'],
            ['station' => 'Blue1', 'teamNumber' => '35'],
            ['station' => 'Blue2', 'teamNumber' => '36']
        ]
    ],
    [
        'description' => 'Qualification 10', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 10,
        'scoreRedFinal' => 67, 'scoreRedAuto' => 0, 'scoreRedFoul' => 15,
        'scoreBlueFinal' => 80, 'scoreBlueAuto' => 10, 'scoreBlueFoul' => 35,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '37'],
            ['station' => 'Red2', 'teamNumber' => '38'],
            ['station' => 'Blue1', 'teamNumber' => '39'],
            ['station' => 'Blue2', 'teamNumber' => '40']
        ]
    ],
    [
        'description' => 'Qualification 11', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 11,
        'scoreRedFinal' => 22, 'scoreRedAuto' => 0, 'scoreRedFoul' => 0,
        'scoreBlueFinal' => 53, 'scoreBlueAuto' => 5, 'scoreBlueFoul' => 0,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '41'],
            ['station' => 'Red2', 'teamNumber' => '42'],
            ['station' => 'Blue1', 'teamNumber' => '43'],
            ['station' => 'Blue2', 'teamNumber' => '44']
        ]
    ],
    [
        'description' => 'Qualification 12', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 12,
        'scoreRedFinal' => 97, 'scoreRedAuto' => 3, 'scoreRedFoul' => 15,
        'scoreBlueFinal' => 117, 'scoreBlueAuto' => 4, 'scoreBlueFoul' => 15,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '45'],
            ['station' => 'Red2', 'teamNumber' => '46'],
            ['station' => 'Blue1', 'teamNumber' => '47'],
            ['station' => 'Blue2', 'teamNumber' => '48']
        ]
    ],
    [
        'description' => 'Qualification 13', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 13,
        'scoreRedFinal' => 75, 'scoreRedAuto' => 6, 'scoreRedFoul' => 15,
        'scoreBlueFinal' => 101, 'scoreBlueAuto' => 18, 'scoreBlueFoul' => 0,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '49'],
            ['station' => 'Red2', 'teamNumber' => '50'],
            ['station' => 'Blue1', 'teamNumber' => '1'],
            ['station' => 'Blue2', 'teamNumber' => '2']
        ]
    ],
    [
        'description' => 'Qualification 14', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 14,
        'scoreRedFinal' => 34, 'scoreRedAuto' => 0, 'scoreRedFoul' => 5,
        'scoreBlueFinal' => 55, 'scoreBlueAuto' => 4, 'scoreBlueFoul' => 0,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '3'],
            ['station' => 'Red2', 'teamNumber' => '4'],
            ['station' => 'Blue1', 'teamNumber' => '5'],
            ['station' => 'Blue2', 'teamNumber' => '6']
        ]
    ],
    [
        'description' => 'Qualification 15', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 15,
        'scoreRedFinal' => 45, 'scoreRedAuto' => 0, 'scoreRedFoul' => 0,
        'scoreBlueFinal' => 72, 'scoreBlueAuto' => 0, 'scoreBlueFoul' => 20,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '7'],
            ['station' => 'Red2', 'teamNumber' => '8'],
            ['station' => 'Blue1', 'teamNumber' => '9'],
            ['station' => 'Blue2', 'teamNumber' => '10']
        ]
    ],
    [
        'description' => 'Qualification 16', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 16,
        'scoreRedFinal' => 148, 'scoreRedAuto' => 34, 'scoreRedFoul' => 0,
        'scoreBlueFinal' => 55, 'scoreBlueAuto' => 0, 'scoreBlueFoul' => 15,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '11'],
            ['station' => 'Red2', 'teamNumber' => '12'],
            ['station' => 'Blue1', 'teamNumber' => '13'],
            ['station' => 'Blue2', 'teamNumber' => '14']
        ]
    ],
    [
        'description' => 'Qualification 17', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 17,
        'scoreRedFinal' => 91, 'scoreRedAuto' => 8, 'scoreRedFoul' => 15,
        'scoreBlueFinal' => 84, 'scoreBlueAuto' => 5, 'scoreBlueFoul' => 0,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '15'],
            ['station' => 'Red2', 'teamNumber' => '16'],
            ['station' => 'Blue1', 'teamNumber' => '17'],
            ['station' => 'Blue2', 'teamNumber' => '18']
        ]
    ],
    [
        'description' => 'Qualification 18', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 18,
        'scoreRedFinal' => 71, 'scoreRedAuto' => 3, 'scoreRedFoul' => 15,
        'scoreBlueFinal' => 31, 'scoreBlueAuto' => 0, 'scoreBlueFoul' => 30,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '19'],
            ['station' => 'Red2', 'teamNumber' => '20'],
            ['station' => 'Blue1', 'teamNumber' => '21'],
            ['station' => 'Blue2', 'teamNumber' => '22']
        ]
    ],
    [
        'description' => 'Qualification 19', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 19,
        'scoreRedFinal' => 74, 'scoreRedAuto' => 13, 'scoreRedFoul' => 0,
        'scoreBlueFinal' => 61, 'scoreBlueAuto' => 2, 'scoreBlueFoul' => 5,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '23'],
            ['station' => 'Red2', 'teamNumber' => '24'],
            ['station' => 'Blue1', 'teamNumber' => '25'],
            ['station' => 'Blue2', 'teamNumber' => '26']
        ]
    ],
    [
        'description' => 'Qualification 20', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 20,
        'scoreRedFinal' => 83, 'scoreRedAuto' => 3, 'scoreRedFoul' => 5,
        'scoreBlueFinal' => 115, 'scoreBlueAuto' => 19, 'scoreBlueFoul' => 15,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '27'],
            ['station' => 'Red2', 'teamNumber' => '28'],
            ['station' => 'Blue1', 'teamNumber' => '29'],
            ['station' => 'Blue2', 'teamNumber' => '30']
        ]
    ],
    [
        'description' => 'Qualification 21', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 21,
        'scoreRedFinal' => 47, 'scoreRedAuto' => 7, 'scoreRedFoul' => 5,
        'scoreBlueFinal' => 90, 'scoreBlueAuto' => 7, 'scoreBlueFoul' => 15,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '31'],
            ['station' => 'Red2', 'teamNumber' => '32'],
            ['station' => 'Blue1', 'teamNumber' => '33'],
            ['station' => 'Blue2', 'teamNumber' => '34']
        ]
    ],
    [
        'description' => 'Qualification 22', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 22,
        'scoreRedFinal' => 66, 'scoreRedAuto' => 10, 'scoreRedFoul' => 20,
        'scoreBlueFinal' => 60, 'scoreBlueAuto' => 8, 'scoreBlueFoul' => 15,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '35'],
            ['station' => 'Red2', 'teamNumber' => '36'],
            ['station' => 'Blue1', 'teamNumber' => '37'],
            ['station' => 'Blue2', 'teamNumber' => '38']
        ]
    ],
    [
        'description' => 'Qualification 23', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 23,
        'scoreRedFinal' => 74, 'scoreRedAuto' => 0, 'scoreRedFoul' => 15,
        'scoreBlueFinal' => 61, 'scoreBlueAuto' => 0, 'scoreBlueFoul' => 0,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '39'],
            ['station' => 'Red2', 'teamNumber' => '40'],
            ['station' => 'Blue1', 'teamNumber' => '41'],
            ['station' => 'Blue2', 'teamNumber' => '42']
        ]
    ],
    [
        'description' => 'Qualification 24', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 24,
        'scoreRedFinal' => 40, 'scoreRedAuto' => 3, 'scoreRedFoul' => 15,
        'scoreBlueFinal' => 41, 'scoreBlueAuto' => 3, 'scoreBlueFoul' => 5,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '43'],
            ['station' => 'Red2', 'teamNumber' => '44'],
            ['station' => 'Blue1', 'teamNumber' => '45'],
            ['station' => 'Blue2', 'teamNumber' => '46']
        ]
    ],
    [
        'description' => 'Qualification 25', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 25,
        'scoreRedFinal' => 31, 'scoreRedAuto' => 3, 'scoreRedFoul' => 15,
        'scoreBlueFinal' => 107, 'scoreBlueAuto' => 0, 'scoreBlueFoul' => 0,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '47'],
            ['station' => 'Red2', 'teamNumber' => '48'],
            ['station' => 'Blue1', 'teamNumber' => '49'],
            ['station' => 'Blue2', 'teamNumber' => '50']
        ]
    ],
    [
        'description' => 'Qualification 26', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 26,
        'scoreRedFinal' => 38, 'scoreRedAuto' => 0, 'scoreRedFoul' => 0,
        'scoreBlueFinal' => 119, 'scoreBlueAuto' => 11, 'scoreBlueFoul' => 15,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '1'],
            ['station' => 'Red2', 'teamNumber' => '2'],
            ['station' => 'Blue1', 'teamNumber' => '3'],
            ['station' => 'Blue2', 'teamNumber' => '4']
        ]
    ],
    [
        'description' => 'Qualification 27', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 27,
        'scoreRedFinal' => 88, 'scoreRedAuto' => 20, 'scoreRedFoul' => 5,
        'scoreBlueFinal' => 48, 'scoreBlueAuto' => 3, 'scoreBlueFoul' => 5,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '5'],
            ['station' => 'Red2', 'teamNumber' => '6'],
            ['station' => 'Blue1', 'teamNumber' => '7'],
            ['station' => 'Blue2', 'teamNumber' => '8']
        ]
    ],
    [
        'description' => 'Qualification 28', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 28,
        'scoreRedFinal' => 38, 'scoreRedAuto' => 0, 'scoreRedFoul' => 5,
        'scoreBlueFinal' => 46, 'scoreBlueAuto' => 3, 'scoreBlueFoul' => 0,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '9'],
            ['station' => 'Red2', 'teamNumber' => '10'],
            ['station' => 'Blue1', 'teamNumber' => '11'],
            ['station' => 'Blue2', 'teamNumber' => '12']
        ]
    ],
    [
        'description' => 'Qualification 29', 'tournamentLevel' => 'QUALIFICATION', 'matchNumber' => 29,
        'scoreRedFinal' => 96, 'scoreRedAuto' => 16, 'scoreRedFoul' => 0,
        'scoreBlueFinal' => 42, 'scoreBlueAuto' => 3, 'scoreBlueFoul' => 0,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '13'],
            ['station' => 'Red2', 'teamNumber' => '14'],
            ['station' => 'Blue1', 'teamNumber' => '15'],
            ['station' => 'Blue2', 'teamNumber' => '16']
        ]
    ],
    [
        'description' => 'Upper Bracket  Round 1 Match 1', 'tournamentLevel' => 'PLAYOFF', 'matchNumber' => 1,
        'scoreRedFinal' => 79, 'scoreRedAuto' => 16, 'scoreRedFoul' => 30,
        'scoreBlueFinal' => 83, 'scoreBlueAuto' => 3, 'scoreBlueFoul' => 0,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '17'],
            ['station' => 'Red2', 'teamNumber' => '18'],
            ['station' => 'Blue1', 'teamNumber' => '19'],
            ['station' => 'Blue2', 'teamNumber' => '20']
        ]
    ],
    [
        'description' => 'Upper Bracket  Round 1 Match 2', 'tournamentLevel' => 'PLAYOFF', 'matchNumber' => 1,
        'scoreRedFinal' => 121, 'scoreRedAuto' => 13, 'scoreRedFoul' => 15,
        'scoreBlueFinal' => 199, 'scoreBlueAuto' => 18, 'scoreBlueFoul' => 15,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '21'],
            ['station' => 'Red2', 'teamNumber' => '22'],
            ['station' => 'Blue1', 'teamNumber' => '23'],
            ['station' => 'Blue2', 'teamNumber' => '24']
        ]
    ],
    [
        'description' => 'Lower Bracket  Round 2 Match 3', 'tournamentLevel' => 'PLAYOFF', 'matchNumber' => 1,
        'scoreRedFinal' => 169, 'scoreRedAuto' => 24, 'scoreRedFoul' => 0,
        'scoreBlueFinal' => 44, 'scoreBlueAuto' => 3, 'scoreBlueFoul' => 15,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '25'],
            ['station' => 'Red2', 'teamNumber' => '26'],
            ['station' => 'Blue1', 'teamNumber' => '27'],
            ['station' => 'Blue2', 'teamNumber' => '28']
        ]
    ],
    [
        'description' => 'Upper Bracket  Round 2 Match 4', 'tournamentLevel' => 'PLAYOFF', 'matchNumber' => 1,
        'scoreRedFinal' => 74, 'scoreRedAuto' => 3, 'scoreRedFoul' => 0,
        'scoreBlueFinal' => 154, 'scoreBlueAuto' => 10, 'scoreBlueFoul' => 30,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '29'],
            ['station' => 'Red2', 'teamNumber' => '30'],
            ['station' => 'Blue1', 'teamNumber' => '31'],
            ['station' => 'Blue2', 'teamNumber' => '32']
        ]
    ],
    [
        'description' => 'Lower Bracket  Round 3 Match 5', 'tournamentLevel' => 'PLAYOFF', 'matchNumber' => 1,
        'scoreRedFinal' => 32, 'scoreRedAuto' => 3, 'scoreRedFoul' => 0,
        'scoreBlueFinal' => 151, 'scoreBlueAuto' => 24, 'scoreBlueFoul' => 0,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '33'],
            ['station' => 'Red2', 'teamNumber' => '34'],
            ['station' => 'Blue1', 'teamNumber' => '35'],
            ['station' => 'Blue2', 'teamNumber' => '36']
        ]
    ],
    [
        'description' => 'Final Bracket  Round 4 Match 6', 'tournamentLevel' => 'PLAYOFF', 'matchNumber' => 1,
        'scoreRedFinal' => 148, 'scoreRedAuto' => 8, 'scoreRedFoul' => 0,
        'scoreBlueFinal' => 154, 'scoreBlueAuto' => 16, 'scoreBlueFoul' => 0,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '37'],
            ['station' => 'Red2', 'teamNumber' => '38'],
            ['station' => 'Blue1', 'teamNumber' => '39'],
            ['station' => 'Blue2', 'teamNumber' => '40']
        ]
    ],
    [
        'description' => 'Final Bracket  Round 5 Match 7', 'tournamentLevel' => 'PLAYOFF', 'matchNumber' => 1,
        'scoreRedFinal' => 186, 'scoreRedAuto' => 26, 'scoreRedFoul' => 0,
        'scoreBlueFinal' => 120, 'scoreBlueAuto' => 8, 'scoreBlueFoul' => 30,
        'teams' => [
            ['station' => 'Red1', 'teamNumber' => '41'],
            ['station' => 'Red2', 'teamNumber' => '42'],
            ['station' => 'Blue1', 'teamNumber' => '43'],
            ['station' => 'Blue2', 'teamNumber' => '44']
        ]
    ]
];

// Transform the matches data
$simplifiedMatches = array_map(function($match) {
    // Sort teams into alliances
    $redTeams = array_filter($match['teams'], function($team) {
        return strpos($team['station'], 'Red') !== false;
    });
    $blueTeams = array_filter($match['teams'], function($team) {
        return strpos($team['station'], 'Blue') !== false;
    });

    // Extract just the team numbers for each alliance
    $redTeamNumbers = array_map(function($team) {
        return $team['teamNumber'];
    }, array_values($redTeams));

    $blueTeamNumbers = array_map(function($team) {
        return $team['teamNumber'];
    }, array_values($blueTeams));

    return [
        'description' => $match['description'],
        'tournamentLevel' => $match['tournamentLevel'],
        'matchNumber' => $match['matchNumber'],
        'red' => [
            'total' => $match['scoreRedFinal'],
            'auto' => $match['scoreRedAuto'],
            'foul' => $match['scoreRedFoul'],
            'teams' => $redTeamNumbers
        ],
        'blue' => [
            'total' => $match['scoreBlueFinal'],
            'auto' => $match['scoreBlueAuto'],
            'foul' => $match['scoreBlueFoul'],
            'teams' => $blueTeamNumbers
        ]
    ];
}, $demoMatches);

echo json_encode([
    'matches' => $simplifiedMatches
]);
?>