<?php
require 'db.php';
$res = $conn->query("SHOW TABLES");
while($row = $res->fetch_array()) {
    echo "Table: " . $row[0] . "\n";
    $desc = $conn->query("DESCRIBE " . $row[0]);
    while($d = $desc->fetch_assoc()) {
        echo "  - " . $d['Field'] . " (" . $d['Type'] . ")\n";
    }
}
