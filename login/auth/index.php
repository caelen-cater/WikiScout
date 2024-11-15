<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET' || empty($_POST)) {
    header('Location: ../../');
    exit();
}

$apikey = 'API_KEY';
$api_url = 'https://api.cirrus.center/v2/auth/user/';

$username = $_POST['username'];
$password = $_POST['password'];
$first = isset($_POST['first']) ? $_POST['first'] : null;
$last = isset($_POST['last']) ? $_POST['last'] : null;

$headers = [
    "Authorization: Bearer $apikey",
    "Content-Type: application/json"
];

function handle_response($http_code, $response, $is_registration = false) {
    if ($http_code == 200) {
        $response_data = json_decode($response, true);
        if (isset($response_data['token'])) {
            $token = $response_data['token'];
            setcookie('auth', $token, time() + (30 * 24 * 60 * 60), "/");
            header('Location: callback');
            exit();
        }
    } else if ($http_code == 401) {
        header('Location: ../?auth=failed');
        exit();
    }
}

if ($first && $last) {
    $registration_headers = [
        "Authorization: Bearer $apikey",
        "username: $username",
        "password: $password"
    ];

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $registration_headers);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $data = json_encode([
            'username' => $username,
            'password' => $password,
            'first' => $first,
            'last' => $last,
            'email' => $username,
            'generate' => false
        ]);

        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        handle_response($http_code, $response, true);
    } else {
        handle_response($http_code, $response);
    }
} else {
    $data = json_encode([
        'username' => $username,
        'password' => $password
    ]);

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    handle_response($http_code, $response);
}

header('Location: ../');
exit();
?>
