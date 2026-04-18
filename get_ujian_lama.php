<?php
header('Content-Type: application/json');
require_once 'db.php'; // file koneksi database

$response = ["status" => "error", "data" => []];

$query_dua = "SELECT * FROM jadwal_sem_dua ORDER BY hari DESC";
$query_empat = "SELECT * FROM jadwal_sem_empat ORDER BY hari DESC";

$result_dua = mysqli_query($conn, $query_dua);
$result_empat = mysqli_query($conn, $query_empat);

$data = [

];
if ($result_dua && $result_empat) {
    while ($row = mysqli_fetch_assoc($result_dua)) {
        $row["semester"] = "2"; // Tambahkan informasi semester  
$data[] = $row;
    }
    while ($row = mysqli_fetch_assoc($result_empat)) {
        $row["semester"] = "4"; // Tambahkan informasi semester
        $data[] = $row;
    }

    $response["status"] = "success";
    $response["data"] = $data;
}

echo json_encode($response);
