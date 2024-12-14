<?php
require_once '../../config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Check authentication
$token = $_COOKIE['auth'] ?? null;
if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Validate user and team number
$authUrl = "https://$serveer/v2/auth/user/";
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
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($authHttpCode !== 200) {
    http_response_code($authHttpCode);
    echo json_encode(['error' => 'Authentication failed']);
    exit;
}

$authData = json_decode($authResponse, true);
$teamNumber = $authData['details']['address'] ?? null;

if ($teamNumber === null) {
    http_response_code(501);
    echo json_encode(['error' => 'Team number not set']);
    exit;
}

// Create authorization string
$auth = base64_encode($username . ':' . $password);
$currentYear = date('Y');

// Setup FIRST API request
$firstApiUrl = "https://ftc-api.firstinspires.org/v2.0/$currentYear/events/";
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