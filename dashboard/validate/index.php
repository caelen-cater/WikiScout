<?php
require_once '../../config.php';

$server = $servers[array_rand($servers)];

// Set no-cache headers
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_COOKIE['auth'])) {
        logError('No auth cookie found', 400, null);
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
    
    if ($userResponse['http_code'] === 401) {
        logError('Unauthorized', 401, null);
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    if ($userResponse['http_code'] !== 200) {
        logError('API service unavailable', 503, null);
        http_response_code(503);
        echo json_encode(['error' => 'Service temporarily unavailable']);
        exit;
    }

    $userData = json_decode($userResponse['response'], true);
    if ($userData === null || !isset($userData['details'])) {
        logError('Invalid response format', 502, null);
        http_response_code(502);
        echo json_encode(['error' => 'Invalid response from authentication service']);
        exit;
    }

    if (empty($userData['details']['address'])) {
        logError('Address not set', 400, $userData['user']['id'] ?? null);
        http_response_code(400);
        echo json_encode(['error' => 'Team number not set']);
        exit;
    }

    if (is_numeric($userData['details']['address'])) {
        http_response_code(200);
        echo json_encode(['message' => 'Address is a number']);
        exit;
    }

    // If address exists but isn't numeric
    http_response_code(400);
    echo json_encode(['error' => 'Invalid team number format']);
    exit;
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

function logError($message, $code, $userId) {
    global $server, $apikey, $webhook;

    $errorUrl = "https://$server/v2/data/error/";
    $errorHeaders = [
        "Authorization: Bearer $apikey",
        "Content-Type: application/json"
    ];

    $errorData = [
        'message' => $message,
        'code' => $code,
        'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0]['file'],
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
        'metadata' => [],
        'severity' => determineSeverity($code),
        'webhook_url' => $webhook,
        'webhook_content' => "An error (:error_id) occurred with :trace by user :user_id with error ':message' and code :code at :timestamp"
    ];

    makeApiRequest($errorUrl, $errorHeaders, json_encode($errorData));
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