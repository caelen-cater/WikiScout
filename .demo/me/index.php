<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$debugEvent = [
    'code' => 'USMNBUQ2',
    'name' => 'MN FTC Burnsville Sun. Jan. 12'
];

echo json_encode([
    'found' => true,
    'event' => [
        'code' => $debugEvent['code'],
        'name' => $debugEvent['name']
    ]
]);
exit;
?>