<?php
header('Content-Type: application/json');
// Restrict CORS to the official domain only
$allowed_origins = ['https://ujian.satyaterrabhinneka.ac.id', 'http://localhost'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
}

require_once 'db.php';

$semester = isset($_GET['semester']) ? $_GET['semester'] : '';
$response = [];

// Mapping semester ke nama tabel database
// Sesuaikan nama tabel dengan database Anda jika berbeda
$table_map = [
    '1' => 'semester_1',
    '2' => 'semester_2', // Asumsi jika ada
    '3' => 'semester_3',
    '4' => 'semester_4', // Asumsi jika ada
    '5' => 'semester_5',
    '6' => 'semester_6', // Asumsi jika ada
    '7' => 'semester_7',
    '8' => 'semester_8'
];

if (isset($table_map[$semester])) {
    $table_name = $table_map[$semester];

    // Query data jadwal
    // Menggunakan ORDER BY jika diperlukan, misalnya ORDER BY hari DESC atau no ASC
    $sql = "SELECT * FROM $table_name";

    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $response[] = $row;
        }
    } else {
        // Jika query gagal (misal tabel tidak ada), kirim log error tapi tetap return empty array json
        error_log("Query Error: " . $conn->error);
    }
}

echo json_encode($response);
?>