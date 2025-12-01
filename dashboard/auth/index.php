<?php
require_once '../../config.php';

header('Content-Type: application/json');
header("Cache-Control: no-cache, no-store, must-revalidate");

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

    // Create otp_codes table if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS otp_codes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        code VARCHAR(8) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NULL DEFAULT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_code (code),
        INDEX idx_expires_at (expires_at)
    )");

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

    // Handle different request methods
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
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
            
            echo json_encode(['code' => $code ?: '--------']);
            break;

        case 'POST':
            // Generate new OTP
            $db->beginTransaction();
            try {
                // First invalidate any existing OTPs
                $stmt = $db->prepare("
                    UPDATE otp_codes 
                    SET expires_at = CURRENT_TIMESTAMP 
                    WHERE user_id = ? 
                    AND expires_at > CURRENT_TIMESTAMP
                ");
                $stmt->execute([$userId]);

                // Generate and store new OTP
                $code = sprintf('%08d', mt_rand(0, 99999999));
                $stmt = $db->prepare("
                    INSERT INTO otp_codes (user_id, code, expires_at) 
                    VALUES (?, ?, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 1 HOUR))
                ");
                $stmt->execute([$userId, $code]);
                
                $db->commit();
                echo json_encode(['code' => $code]);
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;

        case 'DELETE':
            // Invalidate all active OTPs
            $stmt = $db->prepare("
                UPDATE otp_codes 
                SET expires_at = CURRENT_TIMESTAMP 
                WHERE user_id = ? 
                AND expires_at > CURRENT_TIMESTAMP
            ");
            $stmt->execute([$userId]);
            echo json_encode(['message' => 'OTP invalidated']);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    error_log($e->getMessage());
}
?>