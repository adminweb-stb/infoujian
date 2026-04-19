<?php
/**
 * SYNC DATA - DB TO JSON EXPORTER
 * Use this script to update the static JSON files used by the public exam schedule.
 * This decoupled approach ensures the server can handle thousands of concurrent users
 * without crashing the database.
 */

header('Content-Type: text/plain');
require_once 'db.php';

// Configuration: Mapping semesters to table names
$table_map = [
    '1' => 'semester_1',
    '2' => 'semester_2',
    '3' => 'semester_3',
    '4' => 'semester_4',
    '5' => 'semester_5',
    '6' => 'semester_6',
    '7' => 'semester_7',
    '8' => 'semester_8'
];

$output_dir = __DIR__ . '/data/';
if (!is_dir($output_dir)) {
    mkdir($output_dir, 0755, true);
}

echo "Starting Sync Process...\n";
echo "--------------------------\n";

foreach ($table_map as $semester => $table_name) {
    echo "Processing Semester $semester ($table_name)... ";
    
    // Check if table exists
    $check = $conn->query("SHOW TABLES LIKE '$table_name'");
    if ($check->num_rows == 0) {
        echo "Table not found. Skipping.\n";
        continue;
    }

    $sql = "SELECT * FROM $table_name";
    $result = $conn->query($sql);

    if ($result) {
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        $json_file = $output_dir . "semester-$semester.json";
        if (file_put_contents($json_file, json_encode($data, JSON_PRETTY_PRINT))) {
            echo "Success! Saved to data/semester-$semester.json (" . count($data) . " rows)\n";
        } else {
            echo "Error writing file.\n";
        }
    } else {
        echo "Query Error: " . $conn->error . "\n";
    }
}

echo "--------------------------\n";
echo "Sync Complete! Students can now access updated data.\n";
?>
