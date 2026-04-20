<?php
/**
 * SYNC DATA - DB TO JSON EXPORTER
 * Use this script to update the static JSON files used by the public exam schedule.
 * This decoupled approach ensures the server can handle thousands of concurrent users
 * without crashing the database.
 */

// Security: Only allow execution from internal include (admin operations) or CLI
if (!defined('INTERNAL_SYNC')) {
    http_response_code(404);
    include '../404.html';
    exit;
}

header('Content-Type: text/plain');
require_once '../../core/db.php';

// --- MASTER CONFIGURATION ---
$config_path = __DIR__ . '/../data/config.json';
if (file_exists($config_path)) {
    $config = json_decode(file_get_contents($config_path), true);
} else {
    $config = ['active_exam' => 'uts', 'active_period' => 'ganjil'];
}
$active_exam = $config['active_exam'] ?? 'uts';

// Configuration: Mapping semesters to table names dynamically
$table_map = [];
foreach(range(1, 8) as $sem) {
    $table_map[$sem] = "{$active_exam}_semester_{$sem}";
}

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

        $json_file = $output_dir . "{$active_exam}_semester-$semester.json";
        // Final Path Fix: Point to root's data folder from /panel/
        $json_file_abs = __DIR__ . "/../data/{$active_exam}_semester-$semester.json";
        if (file_put_contents($json_file_abs, json_encode($data, JSON_PRETTY_PRINT))) {
            echo "Success! Saved to data/{$active_exam}_semester-$semester.json (" . count($data) . " rows)\n";
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
