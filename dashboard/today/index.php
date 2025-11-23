<?php
require_once '../../config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

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

    // Validate token
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

    // Continue with existing FIRST API call code

    // Determine season year based on current month
    $currentMonth = (int)date('n'); // 1-12
    $currentYear = (int)date('Y');
    $seasonYear = ($currentMonth >= 9) ? $currentYear : $currentYear - 1;

    // Create authorization string
    $auth = base64_encode($username . ':' . $password);

    // Setup FIRST API request
    $firstApiUrl = "https://ftc-api.firstinspires.org/v2.0/$seasonYear/events/";
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
        logError('Failed to fetch events', $httpCode, __FILE__ . ':' . __LINE__, $user['id'], 'high');
        http_response_code($httpCode);
        echo json_encode(['error' => 'Failed to fetch events']);
        exit;
    }

    $data = json_decode($response, true);
    $currentTime = time();
    $currentEvents = [];

    // Filter for current events
    $currentDate = date('Y-m-d');
    foreach ($data['events'] ?? [] as $event) {
        if (!isset($event['dateStart']) || !isset($event['dateEnd'])) {
            continue;
        }
        
        $startDate = substr($event['dateStart'], 0, 10);
        $endDate = substr($event['dateEnd'], 0, 10);
        $startTime = strtotime($event['dateStart']);
        $endTime = strtotime($event['dateEnd']);
        
        // For single-day events, check if current date matches
        // For multi-day events, check if current time is within range
        if ($startDate === $endDate) {
            if ($currentDate === $startDate) {
                $currentEvents[] = [
                    'code' => $event['code'] ?? '',
                    'name' => $event['name'] ?? ''
                ];
            }
        } else {
            $endTimeWithBuffer = $endTime + (24 * 60 * 60);
            if ($currentTime >= $startTime && $currentTime <= $endTimeWithBuffer) {
                $currentEvents[] = [
                    'code' => $event['code'] ?? '',
                    'name' => $event['name'] ?? ''
                ];
            }
        }
    }

    echo json_encode([
        'events' => $currentEvents,
        'count' => count($currentEvents)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'details' => $e->getMessage()]);
    error_log('PDOException in today API: ' . $e->getMessage());
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
    error_log('Exception in today API: ' . $e->getMessage());
    exit;
}
?>