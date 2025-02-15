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

    $user_id = $user['id'];
    $team_number = $user['team_number'];

    // Continue with existing FIRST API call code
    $eventId = $_GET['event'] ?? null;

    if (!$eventId) {
        http_response_code(400);
        report_error('Missing event ID', 400, __FILE__ . ':' . __LINE__, $user_id, 'medium');
        echo json_encode(['error' => 'Missing event ID']);
        exit;
    }

    // Remove any unwanted characters
    $eventId = preg_replace('/[^a-zA-Z0-9]/', '', $eventId);

    // Determine season year based on current month
    $currentMonth = (int)date('n'); // 1-12
    $currentYear = (int)date('Y');
    $seasonYear = ($currentMonth >= 9) ? $currentYear : $currentYear - 1;

    $url = "https://ftc-api.firstinspires.org/v2.0/$seasonYear/teams?null=null&eventCode=$eventId";

    // Create authorization string from config values
    $auth = base64_encode($username . ':' . $password);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        "Authorization: Basic $auth"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200) {
        http_response_code($httpCode);
        report_error('Failed to fetch teams', $httpCode, __FILE__ . ':' . __LINE__, $user_id, 'medium');
        echo json_encode(['error' => 'Failed to fetch teams']);
        exit;
    }

    $data = json_decode($response, true);
    $teams = array_map(function($team) {
        return $team['teamNumber'];
    }, $data['teams'] ?? []);

    echo json_encode(['teams' => $teams]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    error_log($e->getMessage());
    exit;
}
?>