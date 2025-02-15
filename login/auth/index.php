<?php
require_once '../../config.php';

// FIRST create the database tables
try {
    $db = new PDO(
        "mysql:host={$mysql['host']};dbname={$mysql['database']};port={$mysql['port']}",
        $mysql['username'],
        $mysql['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Create users table FIRST
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        team_number VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_login TIMESTAMP,
        INDEX idx_team_number (team_number)
    )");

    // THEN create auth_tokens table that depends on users
    $db->exec("CREATE TABLE IF NOT EXISTS auth_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(255) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NOT NULL,
        is_revoked BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_token (token)
    )");

    // Verify tables exist before proceeding
    $tables = $db->query("SHOW TABLES LIKE 'users'")->rowCount();
    if ($tables === 0) {
        error_log("Failed to create users table");
        header('Location: ../?auth=failed');
        exit();
    }

} catch (PDOException $e) {
    error_log("Database initialization error: " . $e->getMessage());
    header('Location: ../?auth=failed');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' || empty($_POST)) {
    header('Location: ../../');
    exit();
}

$email = $_POST['email'];
$password = $_POST['password'];
$firstName = isset($_POST['first']) ? $_POST['first'] : null;
$lastName = isset($_POST['last']) ? $_POST['last'] : null;

// Registration flow
if ($firstName && $lastName) {
    try {
        $db->beginTransaction();
        
        // Check if email exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $db->rollBack();
            header('Location: ../?auth=exists');
            exit();
        }

        // Create new user - team_number starts as NULL until admin verifies
        $stmt = $db->prepare("
            INSERT INTO users (email, password_hash, first_name, last_name)
            VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $firstName,
            $lastName
        ]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("Failed to create user");
        }
        
        $userId = $db->lastInsertId();
        
        // Create auth token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        $stmt = $db->prepare("
            INSERT INTO auth_tokens (user_id, token, expires_at)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $token, $expires]);
        
        $db->commit();
        
        setcookie('auth', $token, time() + (30 * 24 * 60 * 60), "/", "", true, true);
        header('Location: callback');
        exit();

    } catch (Exception $e) {
        $db->rollBack();
        error_log("Registration error: " . $e->getMessage());
        header('Location: ../?auth=failed');
        exit();
    }
}

// Login flow
else {
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("
            SELECT id, email, password_hash, team_number
            FROM users 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Revoke old tokens
            $db->prepare("
                UPDATE auth_tokens 
                SET is_revoked = 1 
                WHERE user_id = ?
            ")->execute([$user['id']]);
            
            // Generate new token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            $stmt = $db->prepare("
                INSERT INTO auth_tokens (user_id, token, expires_at)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$user['id'], $token, $expires]);
            
            $db->prepare("
                UPDATE users 
                SET last_login = CURRENT_TIMESTAMP 
                WHERE id = ?
            ")->execute([$user['id']]);
            
            $db->commit();
            
            setcookie('auth', $token, time() + (30 * 24 * 60 * 60), "/", "", true, true);
            header('Location: callback');
            exit();
        }

        $db->rollBack();
        header('Location: ../?auth=failed');
        exit();

    } catch (Exception $e) {
        $db->rollBack();
        error_log("Login error: " . $e->getMessage());
        header('Location: ../?auth=failed');
        exit();
    }
}
?>