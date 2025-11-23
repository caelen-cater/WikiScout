<?php
require_once '../../config.php';

// Initialize database connection and create tables
try {
    $db = new PDO(
        "mysql:host={$mysql['host']};dbname={$mysql['database']};port={$mysql['port']}",
        $mysql['username'],
        $mysql['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Create all required tables
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
    
    $db->exec("CREATE TABLE IF NOT EXISTS scouting_data (
        id INT AUTO_INCREMENT PRIMARY KEY,
        team_number VARCHAR(50) NOT NULL,
        event_code VARCHAR(50) NOT NULL,
        season_year INT NOT NULL,
        scouting_team VARCHAR(50) NOT NULL,
        data TEXT NOT NULL,
        is_private BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_team_event (team_number, event_code)
    )");

} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'details' => $e->getMessage()]);
    exit;
} catch (Exception $e) {
    error_log("Initialization failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
    exit;
}

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
    // Reuse existing connection or create new one
    if (!isset($db)) {
        $db = new PDO(
            "mysql:host={$mysql['host']};dbname={$mysql['database']};port={$mysql['port']}",
            $mysql['username'],
            $mysql['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

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

    $requestingTeam = $user['team_number'];

    // Get parameters
    $teamNumber = $_GET['team'] ?? null;
    $eventCode = $_GET['event'] ?? null;

    if (!$teamNumber || !$eventCode) {
        http_response_code(400);
        echo json_encode(['error' => 'Team number and event code are required']);
        exit;
    }

    try {
        // Determine season year
        $currentMonth = (int)date('n');
        $currentYear = (int)date('Y');
        $seasonYear = ($currentMonth >= 9) ? $currentYear : $currentYear - 1;
        
        // Get private data (only the requesting team's private data)
        $privateStmt = $db->prepare("
            SELECT data, scouting_team
            FROM scouting_data 
            WHERE team_number = ? AND event_code = ? 
            AND season_year = ? AND is_private = 1
            AND scouting_team = ?
        ");
        $privateStmt->execute([$teamNumber, $eventCode, $seasonYear, $requestingTeam]);
        $privateData = $privateStmt->fetch(PDO::FETCH_ASSOC);

        // Get public data (excluding the requesting team's data)
        $publicStmt = $db->prepare("
            SELECT data, scouting_team
            FROM scouting_data 
            WHERE team_number = ? AND event_code = ? 
            AND season_year = ? AND is_private = 0
            AND scouting_team != ?
            ORDER BY created_at DESC
        ");
        $publicStmt->execute([$teamNumber, $eventCode, $seasonYear, $requestingTeam]);
        $publicData = $publicStmt->fetchAll(PDO::FETCH_ASSOC);

        // Read form configuration from JSON
        $formConfigPath = '../../form.json';
        $formFields = [];

        if (file_exists($formConfigPath)) {
            $formConfig = json_decode(file_get_contents($formConfigPath), true);
            if (is_array($formConfig)) {
                $formFields = array_map(function($field) {
                    // Skip separators and headers - they don't have labels for data display
                    if (isset($field['type']) && ($field['type'] === 'separator' || $field['type'] === 'header')) {
                        return null;
                    }
                    return $field['label'] ?? '';
                }, $formConfig);
                // Remove null entries (separators and headers)
                $formFields = array_filter($formFields, function($field) {
                    return $field !== null;
                });
            }
        } else {
            error_log('Form configuration file not found: ' . $formConfigPath);
        }

        // Parse data - handle both JSON and legacy pipe-separated format
        $privateDataArray = [];
        if (!empty($privateData['data'])) {
            $decoded = json_decode($privateData['data'], true);
            $privateDataArray = ($decoded !== null && json_last_error() === JSON_ERROR_NONE) 
                ? $decoded 
                : explode('|', $privateData['data']);
        }
        
        $publicDataArray = array_map(function($entry) {
            $decoded = json_decode($entry['data'], true);
            return [
                'data' => ($decoded !== null && json_last_error() === JSON_ERROR_NONE) 
                    ? $decoded 
                    : explode('|', $entry['data']),
                'scouting_team' => $entry['scouting_team']
            ];
        }, $publicData);

        echo json_encode([
            'fields' => $formFields,
            'private_data' => [
                'data' => $privateDataArray,
                'scouting_team' => $privateData['scouting_team'] ?? null
            ],
            'public_data' => $publicDataArray,
            'debug' => [
                'team' => $teamNumber,
                'event' => $eventCode,
                'season' => $seasonYear,
                'requesting_team' => $requestingTeam,
                'has_private' => !empty($privateData),
                'has_public' => !empty($publicData)
            ]
        ]);

    } catch (Exception $e) {
        error_log("Error retrieving data: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'error' => 'Database error',
            'debug' => $e->getMessage()
        ]);
    }

} catch (PDOException $e) {
    error_log("PDOException in view API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'details' => $e->getMessage()]);
    exit;
} catch (Exception $e) {
    error_log("Exception in view API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
    exit;
}
?>