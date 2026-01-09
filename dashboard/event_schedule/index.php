<?php
require_once '../../config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

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

  // Get event code from query parameters
  $eventCode = $_GET['event'] ?? null;
  if (!$eventCode) {
    http_response_code(400);
    logError('Event code is required', 400, __FILE__ . ':' . __LINE__, $user['id'], 'medium');
    echo json_encode(['error' => 'Event code is required']);
    exit;
  }

  // Determine season year based on current month
  $currentMonth = (int)date('n');
  $currentYear = (int)date('Y');
  $seasonYear = ($currentMonth >= 9) ? $currentYear : $currentYear - 1;

  // Setup FIRST API request for schedule
  $scheduleUrl = "https://ftc-api.firstinspires.org/v2.0/$seasonYear/schedule/$eventCode/qual/hybrid";
  $auth = base64_encode($username . ':' . $password);
  $headers = [
    'Accept: application/json',
    "Authorization: Basic $auth"
  ];

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $scheduleUrl);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

  $response = curl_exec($ch);
  $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($httpCode !== 200) {
    http_response_code($httpCode);
    logError('Failed to fetch schedule', $httpCode, __FILE__ . ':' . __LINE__, $user['id'], 'high');
    echo json_encode(['error' => 'Failed to fetch schedule']);
    exit;
  }

  $data = json_decode($response, true);
  echo json_encode($data);
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Database error', 'details' => $e->getMessage()]);
  error_log('PDOException in schedule API: ' . $e->getMessage());
  exit;
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
  error_log('Exception in schedule API: ' . $e->getMessage());
  exit;
}

function logError($message, $code, $trace, $userId, $severity)
{
  global $server, $apikey, $webhook;

  $data = [
    'message' => $message,
    'code' => $code,
    'trace' => $trace,
    'user_id' => $userId,
    'ip' => $_SERVER['REMOTE_ADDR'],
    'agent' => $_SERVER['HTTP_USER_AGENT'],
    'device_info' => php_uname(),
    'server' => $_SERVER['SERVER_NAME'],
    'request_url' => $_SERVER['REQUEST_URI'],
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'request_headers' => json_encode(getallheaders()),
    'request_parameters' => json_encode($_GET),
    'request_body' => file_get_contents('php://input'),
    'metadata' => json_encode(['event_code' => $_GET['event'] ?? null]),
    'severity' => $severity
  ];

  $ch = curl_init("https://$server/v2/data/error/");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $apikey"]);
  curl_exec($ch);
  curl_close($ch);
}
