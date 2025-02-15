<?php
require_once '../../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_COOKIE['auth'])) {
        try {
            $db = new PDO(
                "mysql:host={$mysql['host']};dbname={$mysql['database']};port={$mysql['port']}",
                $mysql['username'],
                $mysql['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Update token to be revoked instead of deleting
            $stmt = $db->prepare("
                UPDATE auth_tokens 
                SET is_revoked = 1 
                WHERE token = ?
            ");
            $stmt->execute([$_COOKIE['auth']]);
            
            // Clear the cookie
            setcookie('auth', '', time() - 3600, '/');
            
        } catch (PDOException $e) {
            error_log("Logout error: " . $e->getMessage());
        }
    }
}

header('Location: ../');
exit();
?>
