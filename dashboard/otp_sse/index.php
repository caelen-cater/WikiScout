<?php
require_once '../../config.php';
// this uses SSE (server-sent events) to only need one request to update otps!

header("Content-Type: text/event-stream");
header("Cache-Control: no-cache");

$token = $_COOKIE['auth'] ?? null;
if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = new PDO(
  "mysql:host={$mysql['host']};dbname={$mysql['database']};port={$mysql['port']}",
  $mysql['username'],
  $mysql['password'],
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Validate token and get user info
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

$prevCode = "none";
$secondsPassed = 0;

while (true) {
  // Get user's active OTP
  $stmt = $db->prepare("
                SELECT code FROM otp_codes 
                WHERE user_id = ? 
                AND expires_at > CURRENT_TIMESTAMP 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
  $stmt->execute([$userId]);
  $code = $stmt->fetchColumn();

  if ($prevCode == $code) continue;
  $prevCode = $code;

  echo "event: otp_update\n";
  echo 'data: ' . $code;

  echo "\n\n";

  if (ob_get_contents()) {
    ob_end_flush();
  }
  flush();

  if (connection_aborted()) break;
  if ($secondsPassed > 60) break;

  $secondsPassed++;
  sleep(1);
}
