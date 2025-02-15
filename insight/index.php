<?php
require_once '../config.php';

function getDeviceInfo() {
    return php_uname();
}

$server = $servers[array_rand($servers)];

// Check authentication
$token = $_COOKIE['auth'] ?? null;
$userId = null;
$teamNumber = null;

try {
    $db = new PDO(
        "mysql:host={$mysql['host']};dbname={$mysql['database']};port={$mysql['port']}",
        $mysql['username'],
        $mysql['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    if ($token) {
        // Validate token using same logic as view endpoint
        $stmt = $db->prepare("
            SELECT u.id, u.team_number 
            FROM auth_tokens at
            JOIN users u ON at.user_id = u.id
            WHERE at.token = ?
            AND at.created_at <= CURRENT_TIMESTAMP
            AND at.expires_at >= CURRENT_TIMESTAMP
            AND at.is_revoked = 0
        ");
        
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $userId = $user['id'];
            $teamNumber = $user['team_number'];
        }
    }

    // Get headers from request
    $headers = getallheaders();

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
                'Authorization: Bearer ' . $apikey
            ],
            'content' => json_encode($insightData)
        ]
    ];

    try {
        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        $responseData = json_decode($response, true);
        
        // Get request ID from API response and ensure it's set in response headers
        if (!empty($responseData['request_id'])) {
            header('X-Request-ID: ' . $responseData['request_id'], true);
        }
        echo json_encode(['request_id' => $responseData['request_id'] ?? null]);
        http_response_code(200);
    } catch (Exception $e) {
        http_response_code(500);
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    exit;
}
exit;
?>