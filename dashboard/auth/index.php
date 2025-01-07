<?php
require_once '../../config.php';

// Set no-cache headers
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

function logError($message, $code, $trace, $userId, $ip, $agent, $deviceInfo, $requestUrl, $requestMethod, $requestHeaders, $requestParameters, $requestBody, $metadata, $severity) {
    global $server, $apikey, $webhook;

    $errorUrl = "https://$server/v2/data/error/";
    $errorHeaders = [
        "Authorization: Bearer $apikey",
        "Content-Type: application/json"
    ];
    $errorBody = json_encode([
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
        'severity' => $severity
    ]);

    makeApiRequest($errorUrl, $errorHeaders, $errorBody);

    // Send webhook notification
    $webhookContent = "An error ($code) occurred with $trace by user $userId with error '$message' and code $code at " . date('Y-m-d H:i:s');
    $webhookBody = json_encode(['content' => $webhookContent]);
    makeApiRequest($webhook, ['Content-Type: application/json'], $webhookBody);
}

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
        logError('Unauthorized access', 401, __FILE__ . ':' . __LINE__, $userData['user']['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], '', $_SERVER['REQUEST_URI'], 'GET', getallheaders(), $_GET, '', [], 'high');
        http_response_code(401);
        exit;
    }

    if ($userResponse['http_code'] !== 200) {
        logError('Failed to fetch user info', $userResponse['http_code'], __FILE__ . ':' . __LINE__, $userData['user']['id'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], '', $_SERVER['REQUEST_URI'], 'GET', getallheaders(), $_GET, '', [], 'medium');
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
        logError('Failed to authenticate user', $authResponse['http_code'], __FILE__ . ':' . __LINE__, '', $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], '', $_SERVER['REQUEST_URI'], 'POST', getallheaders(), $_POST, '', [], 'high');
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
        logError('Failed to update user info', $updateResponse['http_code'], __FILE__ . ':' . __LINE__, '', $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], '', $_SERVER['REQUEST_URI'], 'POST', getallheaders(), $_POST, json_encode($updateBody), [], 'medium');
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
        logError('Failed to clear OTP', $updateResponse['http_code'], __FILE__ . ':' . __LINE__, '', $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], '', $_SERVER['REQUEST_URI'], 'DELETE', getallheaders(), $_GET, json_encode($updateBody), [], 'medium');
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
        logError('Failed to remove OTP from database', $deleteResponse['http_code'], __FILE__ . ':' . __LINE__, '', $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], '', $_SERVER['REQUEST_URI'], 'DELETE', getallheaders(), $_GET, '', [], 'medium');
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