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

    // Fetch user info
    $userUrl = "https://$server/v2/auth/user/";
    $userHeaders = [
        "Authorization: Bearer $apikey",
        "Token: $token"
    ];

    $userResponse = makeApiRequest($userUrl, $userHeaders);
    $userData = json_decode($userResponse['response'], true);

    if ($userResponse['http_code'] === 401) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    if ($userResponse['http_code'] === 200) {
        if (is_null($userData['details']['address'])) {
            http_response_code(501);
            echo json_encode(['error' => 'Address is null']);
            exit;
        }

        if (is_numeric($userData['details']['address'])) {
            // Complete the script
            http_response_code(200);
            echo json_encode(['message' => 'Address is a number']);
            exit;
        }
    }

    http_response_code($userResponse['http_code']);
    echo $userResponse['response'];
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