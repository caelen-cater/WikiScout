<?php
require_once '../../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$otp = $_POST['otp'] ?? null;
if (!$otp) {
    http_response_code(400);
    exit;
}

try {
    $db = new PDO(
        "mysql:host={$mysql['host']};dbname={$mysql['database']};port={$mysql['port']}",
        $mysql['username'],
        $mysql['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Start transaction
    $db->beginTransaction();

    // Find valid OTP and associated user
    $stmt = $db->prepare("
        SELECT oc.user_id, u.team_number
        FROM otp_codes oc
        JOIN users u ON u.id = oc.user_id
        WHERE oc.code = ?
        AND oc.expires_at > CURRENT_TIMESTAMP
        LIMIT 1
    ");
    $stmt->execute([$otp]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        $db->rollBack();
        http_response_code(401);
        exit;
    }

    // Invalidate used OTP
    $stmt = $db->prepare("
        UPDATE otp_codes 
        SET expires_at = CURRENT_TIMESTAMP 
        WHERE code = ?
    ");
    $stmt->execute([$otp]);

    // Generate new auth token
    $newToken = bin2hex(random_bytes(32));
    $stmt = $db->prepare("
        INSERT INTO auth_tokens (user_id, token, expires_at) 
        VALUES (?, ?, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 30 DAY))
    ");
    $stmt->execute([$result['user_id'], $newToken]);

    // After successful authentication and token generation, generate new OTP
    $newOtp = sprintf('%08d', mt_rand(0, 99999999));
    $stmt = $db->prepare("
        INSERT INTO otp_codes (user_id, code, expires_at) 
        VALUES (?, ?, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 1 HOUR))
    ");
    $stmt->execute([$result['user_id'], $newOtp]);

    $db->commit();

    // Set cookie and return success
    setcookie('auth', $newToken, time() + (86400 * 30), '/'); // 30 days
    http_response_code(200);
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    http_response_code(500);
    error_log($e->getMessage());
    exit;
}
?>