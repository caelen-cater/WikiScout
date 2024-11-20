<?php
require_once '../../secrets.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_COOKIE['auth'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No auth cookie found']);
        exit;
    }

    $token = $_COOKIE['auth'];
    $apikey = $apikey;

    // Initial user auth API call
    $authUrl = 'https://api.cirrus.center/v2/auth/user/';
    $authHeaders = [
        "Authorization: Bearer $apikey",
        "Token: $token"
    ];

    $authResponse = makeApiRequest($authUrl, $authHeaders);

    if ($authResponse['http_code'] !== 200) {
        http_response_code($authResponse['http_code']);
        echo json_encode(['error' => 'Auth API error', 'details' => $authResponse['response']]);
        exit;
    }

    // Generate random 8 digit number
    $randomNumber = rand(10000000, 99999999);

    // OTP database API call
    $dataUrl = 'https://api.cirrus.center/v2/data/database/';
    $dataHeaders = [
        "Authorization: Bearer $apikey"
    ];
    $dataBody = http_build_query([
        'db' => 'WikiScout',
        'log' => 'OTP',
        'entry' => $randomNumber,
        'value' => $token
    ]);

    $dataResponse = makeApiRequest($dataUrl, $dataHeaders, $dataBody);
    $dataResponseBody = json_decode($dataResponse['response'], true);

    echo json_encode(['code' => $dataResponseBody['entry']]);
}

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
?>