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

try {
    $db = new PDO(
        "mysql:host={$mysql['host']};dbname={$mysql['database']};port={$mysql['port']}",
        $mysql['username'],
        $mysql['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Validate token directly
    $stmt = $db->prepare("
        SELECT u.id, u.team_number
        FROM auth_tokens at
        JOIN users u ON at.user_id = u.id
        WHERE at.token = ?
        AND at.expires_at > CURRENT_TIMESTAMP
        AND at.is_revoked = 0
    ");
    
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid or expired token']);
        exit;
    }

    if ($user['team_number'] === null) {
        http_response_code(501);
        echo json_encode(['error' => 'No team number assigned']);
        exit;
    }

    $teamNumber = $user['team_number'];

    // Determine season year based on current month
    $currentMonth = (int)date('n');
    $currentYear = (int)date('Y');
    $seasonYear = ($currentMonth >= 9) ? $currentYear : $currentYear - 1;

    // Create FIRST API auth
    $auth = base64_encode($username . ':' . $password);
    $firstHeaders = [
        'Accept: application/json',
        "Authorization: Basic $auth"
    ];

    // Get team's events directly
    $eventsUrl = "https://ftc-api.firstinspires.org/v2.0/$seasonYear/events?teamNumber=$teamNumber";
    $eventsResponse = makeApiRequest($eventsUrl, $firstHeaders);

    if ($eventsResponse['http_code'] !== 200) {
        logError('Failed to fetch events', $eventsResponse['http_code'], __FILE__, $user['id']);
        http_response_code($eventsResponse['http_code']);
        echo json_encode(['error' => 'Failed to fetch events']);
        exit;
    }

    $eventsData = json_decode($eventsResponse['response'], true);
    $currentTime = time();
    $found = false;

    // Check if any of the team's events are currently active
    foreach ($eventsData['events'] as $event) {
        $startTime = strtotime($event['dateStart']);
        $endTime = strtotime($event['dateEnd']);
        
        if ($currentTime >= $startTime && $currentTime <= $endTime) {
            echo json_encode([
                'found' => true,
                'event' => [
                    'code' => $event['code'],
                    'name' => $event['name'],
                    'startDate' => $event['dateStart'],
                    'endDate' => $event['dateEnd']
                ]
            ]);
            exit;
        }
    }

    // If we get here, team isn't at any current event
    echo json_encode([
        'found' => false,
        'message' => 'Team not found at any current event'
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    error_log($e->getMessage());
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

function logError($message, $code, $trace, $userId) {
    global $server, $apikey, $webhook;

    $errorData = [
        'message' => $message,
        'code' => $code,
        'trace' => $trace,
        'user_id' => $userId,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'device_info' => php_uname(),
        'server' => $_SERVER['SERVER_NAME'] ?? 'unknown',
        'request_url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'request_headers' => json_encode(getallheaders()),
        'request_parameters' => json_encode($_GET),
        'request_body' => file_get_contents('php://input'),
        'metadata' => [
            'team_number' => $GLOBALS['teamNumber'] ?? null,
            'season_year' => $GLOBALS['seasonYear'] ?? null
        ],
        'severity' => determineSeverity($code),
        'webhook_content' => "An error (:error_id) occurred with :trace by user :user_id with error ':message' and code :code at :timestamp"
    ];

    $errorUrl = "https://$server/v2/data/error/";
    $errorHeaders = [
        "Authorization: Bearer $apikey",
        "Content-Type: application/json"
    ];

    makeApiRequest($errorUrl, $errorHeaders, json_encode($errorData));

    // Send webhook notification
    makeApiRequest($webhook, $errorHeaders, json_encode(['content' => $errorData['webhook_content']]));
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