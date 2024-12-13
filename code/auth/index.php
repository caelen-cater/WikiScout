<?php
require_once '../../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = $_POST['otp'];
    $apikey = $apikey;

    // Step 1: Get token
    $otpUrl = "https://" . $server . "/v2/data/database/?db=WikiScout&log=OTP&entry=" . urlencode($otp);
    $otpOptions = [
        "http" => [
            "header" => "Authorization: Bearer " . $apikey
        ]
    ];
    $otpContext = stream_context_create($otpOptions);
    $otpResponse = file_get_contents($otpUrl, false, $otpContext);
    $otpData = json_decode($otpResponse, true);

    if (isset($otpData['data'])) {
        $token = $otpData['data'];

        // Step 2: Authenticate user with token
        $authUrl = $server . "/v2/auth/user/";
        $authOptions = [
            "http" => [
                "header" => "Authorization: Bearer " . $apikey . "\r\n" .
                            "Token: " . $token
            ]
        ];
        $authContext = stream_context_create($authOptions);
        $authResponse = file_get_contents($authUrl, false, $authContext);
        $authStatus = $http_response_header[0];

        if (strpos($authStatus, '200') !== false) {
            // Authentication successful
            setcookie('auth', $token, time() + (86400 * 30), "/"); // 30 days expiration
            http_response_code(200);
        } else {
            // Authentication failed
            http_response_code(401);
        }
    } else {
        // OTP verification failed
        http_response_code(401);
    }
}
?>
