<?php
$servers = [
    'us-east.cirrusapi.com',
];
// Learn about API servers here: https://cirrusapi.com/hc/articles/3/15/14/servers
$apikey = 'your-api-key-here';
// Learn about API keys here: https://cirrusapi.com/docs/hc/articles/3/14/12/api-keys

$adminUserIds = [1, 2, 3];
// Webhook for error tracking (if you want it to notify you, I use a Discord webhook myself, but you can use any webhook)
$webhook = 'https://your-webhook-url-here';

$version = '1.0.0';

$username = 'FIRST-username';
$password = 'FIRST-password';

$siteName = 'WikiScout';
$emailAddress = 'support@chanrobotics.org';
$teamName = 'WikiScout Team';
$supportUrl = 'https://wikiscout.chanrobotics.org';

$mysql = [
    'host' => 'localhost',
    'database' => 'wikiscout_db',
    'username' => 'your_mysql_user',
    'password' => 'your_mysql_password',
    'port' => 3306
];
?>