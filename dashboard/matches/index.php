<?php
require_once '../../config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

try {
    $db = new PDO(
        "mysql:host={$mysql['host']};dbname={$mysql['database']};port={$mysql['port']}",
        $mysql['username'],
        $mysql['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Check token exists
    $token = $_COOKIE['auth'] ?? null;
    if (!$token) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
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

    $userId = $user['id'];

    // Determine season year based on current month
    $currentMonth = (int)date('n'); // 1-12
    $currentYear = (int)date('Y');
    $seasonYear = ($currentMonth >= 9) ? $currentYear : $currentYear - 1;

    // Setup FIRST API request for matches
    $eventCode = $_GET['event'] ?? null;
    if (!$eventCode) {
        http_response_code(400);
        echo json_encode(['error' => 'Event code is required']);
        exit;
    }

    $auth = base64_encode($username . ':' . $password);
    $matchesUrl = "https://ftc-api.firstinspires.org/v2.0/$seasonYear/matches/$eventCode";
    $headers = [
        'Accept: application/json',
        "Authorization: Basic $auth"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $matchesUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        http_response_code($httpCode);
        echo json_encode(['error' => 'Failed to fetch matches']);
        exit;
    }

    $data = json_decode($response, true);

    // Transform the matches data
    $simplifiedMatches = array_map(function($match) {
        // Sort teams into alliances
        $redTeams = array_filter($match['teams'], function($team) {
            return strpos($team['station'], 'Red') !== false;
        });
        $blueTeams = array_filter($match['teams'], function($team) {
            return strpos($team['station'], 'Blue') !== false;
        });

        // Extract just the team numbers for each alliance
        $redTeamNumbers = array_map(function($team) {
            return $team['teamNumber'];
        }, array_values($redTeams));

        $blueTeamNumbers = array_map(function($team) {
            return $team['teamNumber'];
        }, array_values($blueTeams));

        return [
            'description' => $match['description'],
            'tournamentLevel' => $match['tournamentLevel'],
            'matchNumber' => $match['matchNumber'],
            'red' => [
                'total' => $match['scoreRedFinal'],
                'auto' => $match['scoreRedAuto'],
                'foul' => $match['scoreRedFoul'],
                'teams' => $redTeamNumbers
            ],
            'blue' => [
                'total' => $match['scoreBlueFinal'],
                'auto' => $match['scoreBlueAuto'],
                'foul' => $match['scoreBlueFoul'],
                'teams' => $blueTeamNumbers
            ]
        ];
    }, $data['matches']);

    echo json_encode([
        'matches' => $simplifiedMatches
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    error_log($e->getMessage());
    exit;
}
?>