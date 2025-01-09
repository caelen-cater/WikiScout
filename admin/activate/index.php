<?php
require_once '../../config.php';

$server = $servers[array_rand($servers)];

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
    $input = json_decode(file_get_contents('php://input'), true);
    $username = $input['username'];
    $password = $input['password'];
    $teamNumber = $input['teamNumber'];
    $apikey = $apikey;

    // Step 1: Check if the team number already has an associated user
    $checkUserUrl = "https://" . $server . "/v2/data/database/?db=WikiScout&log=Users&entry=" . urlencode($teamNumber);
    $checkUserOptions = [
        "http" => [
            "header" => "Authorization: Bearer " . $apikey
        ]
    ];
    $checkUserContext = stream_context_create($checkUserOptions);
    $checkUserResponse = file_get_contents($checkUserUrl, false, $checkUserContext);
    $checkUserData = json_decode($checkUserResponse, true);

    if (isset($checkUserData['data']) && !empty($checkUserData['data'])) {
        // Existing user found, deactivate using app password
        $existingAppPassword = $checkUserData['data'];

        if ($existingAppPassword !== null) {
            // Deactivate the existing user
            $deactivateUrl = "https://" . $server . "/v2/auth/user/";
            $deactivateOptions = [
                "http" => [
                    "header" => "Authorization: Bearer " . $apikey . "\r\n" .
                                "App: " . $existingAppPassword . "\r\n" .
                                "Content-Type: application/json",
                    "method" => "POST",
                    "content" => json_encode([
                        'address' => "null"
                    ])
                ]
            ];
            $deactivateContext = stream_context_create($deactivateOptions);
            file_get_contents($deactivateUrl, false, $deactivateContext);
        }
    }

    // Step 2: Activate the new user
    $activateUrl = "https://" . $server . "/v2/auth/user/";
    $activateOptions = [
        "http" => [
            "header" => "Authorization: Bearer " . $apikey . "\r\n" .
                        "Content-Type: application/json",
            "method" => "POST",
            "content" => json_encode([
                'username' => $username,
                'password' => $password,
                'address' => $teamNumber
            ])
        ]
    ];
    $activateContext = stream_context_create($activateOptions);
    $activateResponse = file_get_contents($activateUrl, false, $activateContext);
    $activateStatus = $http_response_header[0];

    if (strpos($activateStatus, '200') !== false) {
        // Get app password
        $appPasswordUrl = "https://" . $server . "/v2/auth/user/";
        $appPasswordOptions = [
            "http" => [
                "header" => "Authorization: Bearer " . $apikey . "\r\n" .
                            "Username: " . $username . "\r\n" .
                            "Password: " . $password . "\r\n" .
                            "Scope: address*\r\n" .
                            "Content-Type: application/json",
                "method" => "PATCH"
            ]
        ];
        $appPasswordContext = stream_context_create($appPasswordOptions);
        $appPasswordResponse = file_get_contents($appPasswordUrl, false, $appPasswordContext);
        $appPasswordData = json_decode($appPasswordResponse, true);
        
        // Store the app password
        $logUserUrl = "https://" . $server . "/v2/data/database/";
        $logUserOptions = [
            "http" => [
                "header" => "Authorization: Bearer " . $apikey,
                "method" => "POST",
                "content" => http_build_query([
                    'db' => 'WikiScout',
                    'log' => 'Users',
                    'entry' => $teamNumber,
                    'value' => $appPasswordData['app_password']
                ])
            ]
        ];
        $logUserContext = stream_context_create($logUserOptions);
        file_get_contents($logUserUrl, false, $logUserContext);
        
        http_response_code(200);
    } else {
        http_response_code(401);
    }
}
?>