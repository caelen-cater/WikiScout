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
    
    // Simple users table
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        team_number VARCHAR(50),
        is_active BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE INDEX idx_team_number (team_number),
        UNIQUE INDEX idx_email (email)
    )");

} catch (PDOException $e) {
    error_log("Connection failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $authToken = $_COOKIE['auth'];
    $url = "https://" . $server . "/v2/auth/user/";
    $options = [
        "http" => [
            "header" => "Authorization: Bearer " . $apikey . "\r\n" .
                        "Token: " . $authToken,
            "method" => "GET"
        ]
    ];
    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);
    $status = $http_response_header[0];

    if (strpos($status, '401') !== false) {
        http_response_code(401);
        exit();
    } elseif (strpos($status, '200') !== false) {
        $data = json_decode($response, true);
        if (!in_array($data['user']['id'], $adminUserIds)) {
            header('Location: https://static.cirrus.center/http/404/');
            exit();
        } else {
            echo json_encode($data);
        }
    } else {
        http_response_code(401);
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = filter_var($input['email'], FILTER_VALIDATE_EMAIL);
        $teamNumber = htmlspecialchars(strip_tags($input['teamNumber']));

        if (!$email || !$teamNumber) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input']);
            exit;
        }

        // Start transaction
        $db->beginTransaction();

        // First, set team_number to NULL for any account that currently has this team number
        $deactivateStmt = $db->prepare("UPDATE users SET team_number = NULL WHERE team_number = ?");
        $deactivateStmt->execute([$teamNumber]);

        // Now handle the new activation
        $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->execute([$email]);
        $userId = $checkStmt->fetchColumn();

        if ($userId) {
            // Update existing user
            $updateStmt = $db->prepare("UPDATE users SET team_number = ? WHERE id = ?");
            $updateStmt->execute([$teamNumber, $userId]);
        } else {
            // Create new user
            $insertStmt = $db->prepare("INSERT INTO users (email, team_number) VALUES (?, ?)");
            $insertStmt->execute([$email, $teamNumber]);
        }

        $db->commit();
        http_response_code(200);
        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
        error_log($e->getMessage());
    }
}
?>