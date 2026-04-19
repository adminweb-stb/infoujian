<?php
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    die("Akses ditolak.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    require_once 'db.php';
    $semester = (int)$_POST['semester'];
    $table = "semester_$semester";
    
    $file = $_FILES['csv_file']['tmp_name'];
    $handle = fopen($file, "r");
    
    if ($handle !== FALSE) {
        // Read headers
        $headers = fgetcsv($handle, 1000, ",");
        
        // --- PREPARE DATABASE TABLE ---
        // Ensure table exists with correct columns (Standardizing for our new system)
        $conn->query("DROP TABLE IF EXISTS $table");
        $conn->query("CREATE TABLE $table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            hari VARCHAR(50),
            tanggal VARCHAR(50),
            sesi VARCHAR(10),
            matkul VARCHAR(255),
            jam VARCHAR(50),
            kelas VARCHAR(100),
            dosen VARCHAR(100),
            link_server VARCHAR(255)
        )");

        $count = 0;
        $error_count = 0;

        // --- IMPORT DATA ---
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Mapping: HARI, TANGGAL, SESI, MATAKULIAH, JAM, KELAS, DOSEN, LINK_SERVER
            $hari       = $conn->real_escape_string($data[0] ?? '');
            $tanggal    = $conn->real_escape_string($data[1] ?? '');
            $sesi       = $conn->real_escape_string($data[2] ?? '');
            $matkul     = $conn->real_escape_string($data[3] ?? '');
            $jam        = $conn->real_escape_string($data[4] ?? '');
            $kelas      = $conn->real_escape_string($data[5] ?? '');
            $dosen      = $conn->real_escape_string($data[6] ?? '');
            $link       = $conn->real_escape_string($data[7] ?? '');

            if (empty($hari) && empty($matkul)) continue; // Skip empty rows

            $sql = "INSERT INTO $table (hari, tanggal, sesi, matkul, jam, kelas, dosen, link_server) 
                    VALUES ('$hari', '$tanggal', '$sesi', '$matkul', '$jam', '$kelas', '$dosen', '$link')";
            
            if ($conn->query($sql)) {
                $count++;
            } else {
                $error_count++;
            }
        }
        fclose($handle);

        // --- TRIGGER SYNC ---
        ob_start();
        define('INTERNAL_SYNC', true);
        include 'sync_data.php';
        ob_end_clean();

        header("Location: admin.php?success=" . urlencode("Berhasil mengimpor $count data ke Semester $semester. Otomatis sinkronisasi JSON selesai."));
    } else {
        header("Location: admin.php?error=Gagal membaca file CSV.");
    }
} else {
    header("Location: admin.php");
}
?>
