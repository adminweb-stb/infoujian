<?php
// --- SIMPLE .ENV LOADER ---
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
        putenv(trim($name) . "=" . trim($value));
    }
}

// --- DATABASE CONNECTION ---
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: ''; // Default Laragon biasanya kosong
$db   = getenv('DB_NAME') ?: 'ujian_db';

mysqli_report(MYSQLI_REPORT_OFF); // Disable fatal exception to handle manually
$conn = @new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    // Fallback jika password di .env salah (misal di lokal ingin konek tanpa password)
    $conn = new mysqli($host, $user, '');
    if ($conn->connect_error) {
        die('Koneksi Database Gagal: ' . $conn->connect_error . " (Host: $host, User: $user)");
    }
}

$conn->query("CREATE DATABASE IF NOT EXISTS $db");
$conn->select_db($db);

if ($conn->error) {
    die('Gagal memilih database: ' . $conn->error);
}
?>
