<?php
/**
 * API LOGGER - Asynchronous Visitor Tracker
 * Designed to be fast and non-blocking.
 */

require_once 'db.php';

// Disable error reporting for cleaner silent response
error_reporting(0);
header('Content-Type: application/json');

// 1. Ensure Table Exists
$conn->query("CREATE TABLE IF NOT EXISTS visitor_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45),
    user_agent TEXT,
    device_type VARCHAR(50),
    exam_type VARCHAR(50),
    semester INT,
    action VARCHAR(50), -- 'page_load', 'tab_click'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// 2. Capture Data
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

// Simple Device Detection
$device = "Desktop";
if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i', $ua)) {
    $device = "Tablet";
} else if (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile)/i', $ua)) {
    $device = "Mobile";
}

$exam_type = $_POST['exam_type'] ?? 'unknown';
$semester = (int)($_POST['semester'] ?? 0);
$action = $_POST['action'] ?? 'page_load';

// 3. Log to DB
$stmt = $conn->prepare("INSERT INTO visitor_logs (ip_address, user_agent, device_type, exam_type, semester, action) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssis", $ip, $ua, $device, $exam_type, $semester, $action);
$stmt->execute();

echo json_encode(['status' => 'logged']);
exit;
