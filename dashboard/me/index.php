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

    // Create users and auth_tokens tables if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        team_number VARCHAR(50),
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_team_number (team_number)
    )");

    $db->exec("CREATE TABLE IF NOT EXISTS auth_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(255) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NULL,
        is_revoked BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_token (token)
    )");

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

    if ($user['team_number'] === null || $user['team_number'] === '') {
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
        if (function_exists('logError')) {
            logError('Failed to fetch events', $eventsResponse['http_code'], __FILE__ . ':' . __LINE__, $user['id']);
        }
        http_response_code($eventsResponse['http_code']);
        echo json_encode(['error' => 'Failed to fetch events', 'details' => $eventsResponse['response']]);
        exit;
    }

    $eventsData = json_decode($eventsResponse['response'], true);
    
    if (json_last_error() !== JSON_ERROR_NONE || !isset($eventsData['events']) || !is_array($eventsData['events'])) {
        http_response_code(500);
        echo json_encode(['error' => 'Invalid response from events API']);
        error_log('Invalid JSON response from events API: ' . $eventsResponse['response']);
        exit;
    }

    $currentDate = date('Y-m-d');
    $currentTime = time();
    $found = false;

    // Check if any of the team's events are currently active
    foreach ($eventsData['events'] as $event) {
        if (!isset($event['dateStart']) || !isset($event['dateEnd'])) {
            continue;
        }

        // Extract date portion (YYYY-MM-DD) from the timestamp
        $startDate = substr($event['dateStart'], 0, 10);
        $endDate = substr($event['dateEnd'], 0, 10);
        
        // Parse full timestamps for time-based comparison
        $startTime = strtotime($event['dateStart']);
        $endTime = strtotime($event['dateEnd']);
        
        // For single-day events (same start and end date), check if current date matches
        // For multi-day events, check if current time is within the range
        if ($startDate === $endDate) {
            // Single-day event: check if today's date matches
            if ($currentDate === $startDate) {
                $found = true;
                echo json_encode([
                    'found' => true,
                    'event' => [
                        'code' => $event['code'] ?? '',
                        'name' => $event['name'] ?? '',
                        'startDate' => $event['dateStart'],
                        'endDate' => $event['dateEnd']
                    ],
                    'teamNumber' => $teamNumber
                ]);
                exit;
            }
        } else {
            // Multi-day event: check if current time is within range
            // Add 24 hours to end time to include the entire end date
            $endTimeWithBuffer = $endTime + (24 * 60 * 60);
            if ($currentTime >= $startTime && $currentTime <= $endTimeWithBuffer) {
                $found = true;
                echo json_encode([
                    'found' => true,
                    'event' => [
                        'code' => $event['code'] ?? '',
                        'name' => $event['name'] ?? '',
                        'startDate' => $event['dateStart'],
                        'endDate' => $event['dateEnd']
                    ],
                    'teamNumber' => $teamNumber
                ]);
                exit;
            }
        }
    }

    // If we get here, team isn't at any current event
    echo json_encode([
        'found' => false,
        'message' => 'Team not found at any current event',
        'teamNumber' => $teamNumber,
        'currentDate' => $currentDate,
        'seasonYear' => $seasonYear
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'details' => $e->getMessage()]);
    error_log('PDOException in me API: ' . $e->getMessage());
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
    error_log('Exception in me API: ' . $e->getMessage());
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