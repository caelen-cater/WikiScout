<?php
require_once '../../config.php';

$server = $servers[array_rand($servers)];

try {
    $db = new PDO(
        "mysql:host={$mysql['host']};dbname={$mysql['database']};port={$mysql['port']}",
        $mysql['username'],
        $mysql['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Create scouting_data table if not exists
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
    echo json_encode(['error' => 'Database error']);
    exit;
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

function logError($message, $code, $trace, $userId, $severity, $metadata) {
    global $server, $apikey, $webhook;

    // Remove backslashes from webhook URL
    $cleanWebhook = str_replace('\\', '', $webhook);

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
        'metadata' => json_encode($metadata),
        'severity' => $severity,
        'webhook_url' => addslashes($cleanWebhook),
        'webhook_content' => "An error (:error_id) occurred with :trace by user :user_id with error ':message' and code :code at :timestamp"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://$server/v2/data/error/");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $apikey"]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($errorData));

    curl_exec($ch);
    curl_close($ch);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    logError('Method Not Allowed', 405, __FILE__ . ':' . __LINE__, null, 'medium', []);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$teamNumber = $_POST['team_number'] ?? null;
$eventId = $_POST['event_id'] ?? null;
$data = $_POST['data'] ?? null;

if (!$teamNumber || !$eventId || !$data) {
    http_response_code(400);
    logError('Missing parameters', 400, __FILE__ . ':' . __LINE__, null, 'medium', compact('teamNumber', 'eventId', 'data'));
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

// Remove new lines from input data
$teamNumber = str_replace(["\r", "\n"], '', $teamNumber);
$eventId = str_replace(["\r", "\n"], '', $eventId);
$data = str_replace(["\r", "\n"], '', $data);

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

    $userId = $user['id'];
    $scoutingTeamNumber = $user['team_number'];

    // Read form configuration
    $formConfig = file_get_contents('../../form.dat');
    $formFields = explode("\n", $formConfig);
    $privateFieldIndexes = [];

    // Identify private fields
    $fieldIndex = 0;
    foreach ($formFields as $field) {
        if (strpos($field, 'private') !== false) {
            $privateFieldIndexes[] = $fieldIndex;
        }
        $fieldIndex++;
    }

    // Process the data - split and remove event code if present
    $dataFields = explode('|', $data);
    if (strpos($dataFields[0], $eventId) !== false) {
        array_shift($dataFields);
    }
    $data = implode('|', $dataFields);
    $publicData = $dataFields;

    // Replace private fields with placeholder
    foreach ($privateFieldIndexes as $index) {
        if (isset($publicData[$index])) {
            $publicData[$index] = "Redacted Field";
        }
    }

    $currentMonth = (int)date('n'); // 1-12
    $currentYear = (int)date('Y');
    $seasonYear = ($currentMonth >= 9) ? $currentYear : $currentYear - 1;

    $eventCode = $eventId;

    try {
        // Save both public and private data
        $stmt = $db->prepare("
            INSERT INTO scouting_data 
            (team_number, event_code, season_year, scouting_team, data, is_private) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        // Save public data
        $stmt->execute([
            $teamNumber,
            $eventCode,
            $seasonYear,
            $scoutingTeamNumber,
            implode('|', $publicData),
            0  // is_private = false
        ]);

        // Save private data
        $stmt->execute([
            $teamNumber,
            $eventCode,
            $seasonYear,
            $scoutingTeamNumber,
            $data,
            1  // is_private = true
        ]);

        http_response_code(200);
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        http_response_code(500);
        logError('Database operation failed', 500, __FILE__ . ':' . __LINE__, $userId, 'urgent', [
            'error' => $e->getMessage()
        ]);
        echo json_encode(['error' => 'Database operation failed']);
    }

} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}
?>