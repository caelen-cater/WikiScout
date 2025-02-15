<?php
require_once '../../../config.php';

if (!isset($_COOKIE['auth'])) {
    http_response_code(401);
    exit;
}

try {
    $db = new PDO(
        "mysql:host={$mysql['host']};dbname={$mysql['database']};port={$mysql['port']}",
        $mysql['username'],
        $mysql['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Check token validity
    $stmt = $db->prepare("
        SELECT u.id, u.team_number
        FROM auth_tokens at
        JOIN users u ON at.user_id = u.id
        WHERE at.token = ?
        AND at.expires_at > CURRENT_TIMESTAMP
        AND at.is_revoked = 0
    ");
    
    $stmt->execute([$_COOKIE['auth']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Invalid or expired token
    if (!$result) {
        http_response_code(401);
        exit;
    }

    // Valid token but no team number assigned
    if ($result['team_number'] === null) {
        http_response_code(501);
        exit;
    }

    // Token is valid and has team number
    header('Content-Type: application/json');
    echo json_encode([
        'valid' => true,
        'team_number' => $result['team_number']
    ]);

} catch (PDOException $e) {
    error_log("Token validation error: " . $e->getMessage());
    http_response_code(500);
    exit;
}
?>