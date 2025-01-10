<?php
require_once '../config.php';

$server = $servers[array_rand($servers)];

function getDeviceInfo() {
    return json_encode([
        'browser' => $_SERVER['HTTP_USER_AGENT'],
        'platform' => php_uname('s'),
        'hostname' => gethostname()
    ]);
}

// Get auth token and user ID
$token = $_COOKIE['auth'] ?? null;
$userId = null;

if ($token) {
    $authUrl = "https://" . $server . "/v2/auth/user/";
    $authOptions = [
        "http" => [
            "header" => "Authorization: Bearer " . $apikey . "\r\n" .
                        "Token: " . $token
        ]
    ];
    $authContext = stream_context_create($authOptions);
    $authResponse = file_get_contents($authUrl, false, $authContext);
    $authData = json_decode($authResponse, true);
    $userId = $authData['user_id'] ?? null;
}

// Get headers from request
$headers = getallheaders();

// Generate request ID without prefix
$requestId = uniqid(true);

// Set no-cache headers
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

$insightData = [
    'message' => $headers['X-Action-Message'] ?? 'API Request',
    'code' => http_response_code(),
    'trace' => __FILE__,
    'user_id' => $userId,
    'ip' => $_SERVER['REMOTE_ADDR'],
    'agent' => $_SERVER['HTTP_USER_AGENT'],
    'device_info' => getDeviceInfo(),
    'version' => $version,
    'server' => $_SERVER['SERVER_NAME'],
    'request_url' => $_SERVER['HTTP_REFERER'] ?? '',
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'request_headers' => json_encode($headers),
    'request_parameters' => json_encode($_GET),
    'request_body' => json_encode(json_decode(file_get_contents('php://input'), true) ?? []),
    'status_code' => http_response_code(),
    'metadata' => $headers['X-Metadata'] ?? '{}'
];

$url = "https://" . $server . "/v2/data/request/";
$options = [
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apikey,
            'X-Request-ID: ' . $requestId
        ],
        'content' => json_encode($insightData)
    ]
];

try {
    $context = stream_context_create($options);
    file_get_contents($url, false, $context);
    
    // Set response headers
    header('X-Request-ID: ' . $requestId);
    http_response_code(200);
} catch (Exception $e) {
    http_response_code(500);
}
exit;
?>