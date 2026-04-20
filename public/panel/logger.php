<?php
/**
 * API LOGGER v2.5 - Advanced Visitor Forensics & Geo-Analytics
 * Now supports internal calls and modular execution.
 */

// Global configuration
date_default_timezone_set('Asia/Jakarta');

function run_visitor_logger($custom_action = null, $custom_exam = null, $custom_sem = 0) {
    global $conn; // Access the DB connection from db.php
    
    // 1. Ensure Table Schema is modern
    $conn->query("CREATE TABLE IF NOT EXISTS visitor_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45),
        user_agent TEXT,
        os VARCHAR(100),
        brand VARCHAR(100),
        country VARCHAR(100),
        city VARCHAR(100),
        isp VARCHAR(255),
        device_type VARCHAR(50),
        exam_type VARCHAR(50),
        semester INT,
        action VARCHAR(50),
        is_bot TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Capture Identity
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip_list = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ip_list[0]);
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    if ($ip === '::1') $ip = '127.0.0.1';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    $os = "Unknown OS"; $brand = "Generic Device"; $is_bot = 0;
    $os_array = [
        '/windows nt 10/i'      =>  'Windows 10/11',
        '/windows nt 6.3/i'     =>  'Windows 8.1',
        '/windows nt 6.2/i'     =>  'Windows 8',
        '/windows nt 6.1/i'     =>  'Windows 7',
        '/macintosh|mac os x/i' =>  'MacOS',
        '/android/i'            =>  'Android',
        '/iphone|ipad|ipod/i'   =>  'iOS',
        '/linux/i'              =>  'Linux',
        '/bot|crawl|slurp|spider|mediapartners/i' => 'Bot/Spider'
    ];
    foreach ($os_array as $regex => $value) {
        if (preg_match($regex, $ua)) {
            $os = $value;
            if ($value === 'Bot/Spider') $is_bot = 1;
            break;
        }
    }

    if (preg_match('/iphone|ipad/i', $ua)) $brand = "Apple";
    else if (preg_match('/samsung|sm-|gt-/i', $ua)) $brand = "Samsung";
    else if (preg_match('/xiaomi|mi |redmi/i', $ua)) $brand = "Xiaomi";
    else if (preg_match('/oppo|cph|paj|pks|pkm|pb/i', $ua)) $brand = "Oppo";
    else if (preg_match('/vivo/i', $ua)) $brand = "Vivo";
    else if (preg_match('/huawei|honor/i', $ua)) $brand = "Huawei";
    else if (preg_match('/realme/i', $ua)) $brand = "Realme";
    else if (preg_match('/windows/i', $ua)) $brand = "PC / Laptop";

    // Client Hints Fallback
    $input_raw = file_get_contents('php://input');
    $input = json_decode($input_raw, true) ?: [];

    if ($brand === "Generic Device" && $os === "Android") {
        $client_model = $input['client_model'] ?? '';
        $client_brand = $input['client_brand'] ?? '';
        if ($client_model && $client_model !== 'Generic') {
            $brand = (str_contains($client_brand, 'Generic') ? 'Android' : $client_brand) . " (" . $client_model . ")";
        } else { $brand = "Android Device"; }
    }

    $device = "Desktop";
    if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i', $ua)) $device = "Tablet";
    else if (preg_match('/(up.browser|up.link|mmp|symbian|smartphone|midp|wap|phone|android|iemobile)/i', $ua)) $device = "Mobile";

    // Geo-IP
    $country = "Local/Private"; $city = ""; $isp = "";
    if ($ip !== '127.0.0.1' && !str_starts_with($ip, '192.168.') && !str_starts_with($ip, '10.')) {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (isset($_SESSION['geo_data'])) {
            $geo = $_SESSION['geo_data'];
        } else {
            $ctx = stream_context_create(['http' => ['timeout' => 1.5]]);
            $api_call = @file_get_contents("http://ip-api.com/json/$ip?fields=status,country,city,isp", false, $ctx);
            $geo = json_decode($api_call, true);
            if ($geo && $geo['status'] === 'success') $_SESSION['geo_data'] = $geo;
        }
        if ($geo && $geo['status'] === 'success') {
            $country = $geo['country']; $city = $geo['city']; $isp = $geo['isp'];
        }
    }

    // Determine Action & Exam Type
    $exam_type = $input['exam_type'] ?? $_POST['exam_type'] ?? $custom_exam ?? 'unknown';
    $semester = (int)($input['semester'] ?? $_POST['semester'] ?? $custom_sem ?? 0);
    $action = $input['action'] ?? $_POST['action'] ?? $custom_action ?? 'page_load';

    // Log to DB
    $now = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("INSERT INTO visitor_logs (ip_address, user_agent, os, brand, country, city, isp, device_type, exam_type, semester, action, is_bot, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssisis", $ip, $ua, $os, $brand, $country, $city, $isp, $device, $exam_type, $semester, $action, $is_bot, $now);
    return $stmt->execute();
}

// Ensure DB is available for both internal and external calls
require_once __DIR__ . '/../../core/db.php';

// If called directly via Web (POST), run immediately
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !defined('INTERNAL_LOG')) {
    header('Content-Type: application/json');
    run_visitor_logger();
    echo json_encode(['status' => 'logged']);
    exit;
}

// If called directly via GET (malicious or testing), show 404
if (!defined('INTERNAL_LOG') && $_SERVER['REQUEST_METHOD'] === 'GET') {
    http_response_code(404);
    include '../404.html';
    exit;
}
