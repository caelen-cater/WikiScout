<?php
require_once '../../config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$eventId = $_GET['event_id'] ?? 'USMNSAQ1';
$season = date("Y");

$dbUrl = "https://$server/v2/data/database/?db=WikiScout-$season-$eventId";
$dbHeaders = [
    "Authorization: Bearer $apikey"
];

$dbCh = curl_init();
curl_setopt($dbCh, CURLOPT_URL, $dbUrl);
curl_setopt($dbCh, CURLOPT_RETURNTRANSFER, true);
curl_setopt($dbCh, CURLOPT_HTTPHEADER, $dbHeaders);

$dbResponse = curl_exec($dbCh);
$dbHttpCode = curl_getinfo($dbCh, CURLINFO_HTTP_CODE);
curl_close($dbCh);

if ($dbHttpCode !== 200) {
    http_response_code($dbHttpCode);
    echo $dbResponse;
    exit;
}

$dbData = json_decode($dbResponse, true);
$entries = $dbData['data']["WikiScout-$season-$eventId"] ?? [];

$table = "Team Number | Mecanum Drive Train | Driver Practice | High Basket | High Chamber | Hang | Auto Points | Extra Data\n";
$table .= str_repeat("-", 100) . "\n";

foreach ($entries as $teamNumber => $scoutData) {
    foreach ($scoutData as $scoutingTeam => $data) {
        $values = explode('|', $data);
        if (count($values) === 7) { // Only include correctly formatted data
            $table .= sprintf(
                "%11s | %19s | %14s | %11s | %12s | %4s | %11s | %s\n",
                $teamNumber,
                $values[0],
                $values[1],
                $values[2],
                $values[3],
                $values[4],
                $values[5],
                $values[6]
            );
        }
    }
}

echo json_encode(['table' => $table]);
?>