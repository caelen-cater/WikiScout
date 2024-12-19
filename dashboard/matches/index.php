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

// Get event code from query parameters
$eventCode = $_GET['event'] ?? null;
if (!$eventCode) {
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
    http_response_code($authHttpCode);
    echo json_encode(['error' => 'Authentication failed']);
    exit;
}

// Create authorization string for FIRST API
$auth = base64_encode($username . ':' . $password);
$currentYear = date('Y');

// Setup FIRST API request for matches
$matchesUrl = "https://ftc-api.firstinspires.org/v2.0/$currentYear/matches/$eventCode";
$headers = [
    'Accept: application/json',
    "Authorization: Basic $auth"
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $matchesUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo json_encode(['error' => 'Failed to fetch matches']);
    exit;
}

$data = json_decode($response, true);

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
}, $data['matches']);

echo json_encode([
    'matches' => $simplifiedMatches
]);
?>