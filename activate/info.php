<?php
require_once '../config.php';

header('Content-Type: application/json');

$response = [
    'teamName' => $teamName,
    'emailAddress' => $emailAddress,
    'supportUrl' => $supportUrl
];

echo json_encode($response);
?>