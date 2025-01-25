<?php
require_once '../../config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$debugEvent = [
    'code' => 'USMNBUQ2',
    'name' => 'MN FTC Burnsville Sun. Jan. 12'
];

echo json_encode([
    'found' => true,
    'event' => [
        'code' => $debugEvent['code'],
        'name' => $debugEvent['name'],
        'startDate' => date('Y-m-d'),
        'endDate' => date('Y-m-d')
    ]
]);
exit;

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