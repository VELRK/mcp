<?php
define('BASEPATH', true);
define('ENVIRONMENT', 'development');
require __DIR__ . '/../application/config/database.php';
$cfg = $db['default'];
$conn = new mysqli($cfg['hostname'], $cfg['username'], $cfg['password'], $cfg['database']);
$res = $conn->query("SELECT `key`, LEFT(`value`, 40) as v FROM settings WHERE `key` LIKE '%wa_%' OR `key` LIKE '%meta%'");
while ($r = $res->fetch_assoc()) {
    echo $r['key'] . " = " . $r['v'] . "\n";
}
