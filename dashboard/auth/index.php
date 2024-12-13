<?php
require_once '../../config.php';

// Set no-cache headers
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_COOKIE['auth'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No auth cookie found']);
        exit;
    }

    $token = $_COOKIE['auth'];
    $apikey = $apikey;

    // Fetch user info to get the OTP stored in the phone number field
    $userUrl = "https://$server/v2/auth/user/";
    $userHeaders = [
        "Authorization: Bearer $apikey",
        "Token: $token"
    ];

    $userResponse = makeApiRequest($userUrl, $userHeaders);
    $userData = json_decode($userResponse['response'], true);

    if ($userResponse['http_code'] === 401) {
        http_response_code(401);
        exit;
    }

    if ($userResponse['http_code'] !== 200) {
        http_response_code($userResponse['http_code']);
        exit;
    }

    $otp = isset($userData['details']['phone']) ? $userData['details']['phone'] : '--------';

    echo json_encode(['code' => $otp]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_COOKIE['auth'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No auth cookie found']);
        exit;
    }

    $token = $_COOKIE['auth'];
    $apikey = $apikey;

    // Initial user auth API call
    $authUrl = "https://$server/v2/auth/user/";
    $authHeaders = [
        "Authorization: Bearer $apikey",
        "Token: $token"
    ];

    $authResponse = makeApiRequest($authUrl, $authHeaders);

    if ($authResponse['http_code'] !== 200) {
        http_response_code($authResponse['http_code']);
        exit;
    }

    // Generate random 8 digit number
    $randomNumber = rand(10000000, 99999999);

    // Store OTP in the user's phone number field
    $updateUrl = "https://$server/v2/auth/user/";
    $updateHeaders = [
        "Authorization: Bearer $apikey",
        "Token: $token"
    ];
    $updateBody = [
        'phone' => $randomNumber
    ];

    $updateResponse = makeApiRequest($updateUrl, $updateHeaders, json_encode($updateBody));

    if ($updateResponse['http_code'] !== 200) {
        http_response_code($updateResponse['http_code']);
        exit;
    }

    // Add OTP to the database
    $dataUrl = "https://$server/v2/data/database/";
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

    echo json_encode(['code' => $randomNumber]);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (!isset($_COOKIE['auth'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No auth cookie found']);
        exit;
    }

    $token = $_COOKIE['auth'];
    $apikey = $apikey;

    // Clear OTP from the user's phone number field
    $updateUrl = "https://$server/v2/auth/user/";
    $updateHeaders = [
        "Authorization: Bearer $apikey",
        "Token: $token"
    ];
    $updateBody = [
        'phone' => 'null'
    ];

    $updateResponse = makeApiRequest($updateUrl, $updateHeaders, json_encode($updateBody));

    if ($updateResponse['http_code'] !== 200) {
        http_response_code($updateResponse['http_code']);
        exit;
    }

    // Remove OTP from the database
    $otp = $_COOKIE['OTP'];
    $deleteUrl = "https://$server/v2/data/database/?db=WikiScout&log=OTP&entry=$otp";
    $deleteHeaders = [
        "Authorization: Bearer $apikey"
    ];

    $deleteResponse = makeApiRequest($deleteUrl, $deleteHeaders);
    if ($deleteResponse['http_code'] !== 200) {
        http_response_code($deleteResponse['http_code']);
        exit;
    }

    echo json_encode(['message' => 'OTP invalidated']);
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