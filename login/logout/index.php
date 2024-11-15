<?php
$apikey = 'API_KEY';

function sendDeleteRequest($url, $apikey, $token) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Authorization: Bearer ' . $apikey,
        'Token: ' . $token
    ));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $httpCode;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_COOKIE['auth'])) {
        $token = $_COOKIE['auth'];
        $url = 'https://api.cirrus.center/v2/auth/user/';

        $responseCode = sendDeleteRequest($url, $apikey, $token);

        if ($responseCode == 200) {
            setcookie('auth', '', time() - 3600, '/');
        }
    }
}

header('Location: ../');
exit();
?>
