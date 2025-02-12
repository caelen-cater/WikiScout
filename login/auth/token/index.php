<?php
if (!isset($_COOKIE['auth'])) {
    http_response_code(401);
    exit;
}

$apikey = 'API_KEY';

$token = $_COOKIE['auth'];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://us-east.cirrusapi.com/v2/auth/user/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $apikey",
    "Token: $token"
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($http_code == 200) {
    http_response_code(200);
} else {
    http_response_code(401);
}
?>