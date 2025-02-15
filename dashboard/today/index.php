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
    foreach ($data['events'] as $event) {
        $startTime = strtotime($event['dateStart']);
        $endTime = strtotime($event['dateEnd']);
        
        if ($currentTime >= $startTime && $currentTime <= $endTime) {
            $currentEvents[] = [
                'code' => $event['code'],
                'name' => $event['name']
            ];
        }
    }

    echo json_encode([
        'events' => $currentEvents,
        'count' => count($currentEvents)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    error_log($e->getMessage());
    exit;
}
?>