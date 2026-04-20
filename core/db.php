<?php
// Database configuration using Environment Variables
// On Production (Docker), these are set in docker-compose.yml
// On Local (Laragon), it uses the fallback values below
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$db   = getenv('DB_NAME') ?: 'ujian_db';

// 1. Initial connection to MySQL
// This will throw a Fatal Error if the 'mysqli' extension is missing!
// Fixed by installing mysqli in Dockerfile.
$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die('Koneksi Gagal: ' . $conn->connect_error);
}

// 2. Create Database if not exists (helpful for initial setup)
$conn->query("CREATE DATABASE IF NOT EXISTS $db");

// 3. Select database
$conn->select_db($db);

if ($conn->error) {
    die('Gagal masuk ke database: ' . $conn->error);
}

