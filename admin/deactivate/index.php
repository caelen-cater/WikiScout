<?php
require_once '../../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = new PDO(
            "mysql:host={$mysql['host']};dbname={$mysql['database']};port={$mysql['port']}",
            $mysql['username'],
            $mysql['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $input = json_decode(file_get_contents('php://input'), true);
        $teamNumber = htmlspecialchars(strip_tags($input['teamNumber']));

        // Simply set team_number to NULL
        $stmt = $db->prepare("UPDATE users SET team_number = NULL WHERE team_number = ?");
        $stmt->execute([$teamNumber]);

        if ($stmt->rowCount() > 0) {
            http_response_code(200);
            echo json_encode(['success' => true]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Team not found']);
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
        error_log($e->getMessage());
    }
}
?>