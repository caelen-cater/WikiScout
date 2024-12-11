<?php
require_once '../../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $teamNumber = $input['teamNumber'];

    // Check if team exists and get app password
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
        $appPassword = $checkUserData['data'];

        // Deactivate the user
        $deactivateUrl = "https://" . $server . "/v2/auth/user/";
        $deactivateOptions = [
            "http" => [
                "header" => "Authorization: Bearer " . $apikey . "\r\n" .
                            "App: " . $appPassword . "\r\n" .
                            "Content-Type: application/json",
                "method" => "POST",
                "content" => json_encode([
                    'address' => "null"
                ])
            ]
        ];
        $deactivateContext = stream_context_create($deactivateOptions);
        $deactivateResponse = file_get_contents($deactivateUrl, false, $deactivateContext);
        $deactivateStatus = $http_response_header[0];

        if (strpos($deactivateStatus, '200') !== false) {
            // Remove the app password from database
            $deleteUserUrl = "https://" . $server . "/v2/data/database/";
            $deleteUserOptions = [
                "http" => [
                    "header" => "Authorization: Bearer " . $apikey,
                    "method" => "DELETE",
                    "content" => http_build_query([
                        'db' => 'WikiScout',
                        'log' => 'Users',
                        'entry' => $teamNumber
                    ])
                ]
            ];
            $deleteUserContext = stream_context_create($deleteUserOptions);
            file_get_contents($deleteUserUrl, false, $deleteUserContext);
            
            http_response_code(200);
        } else {
            http_response_code(400);
        }
    } else {
        http_response_code(404);
    }
}
?>