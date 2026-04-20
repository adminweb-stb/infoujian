<?php
/**
 * SECURITY SHIELD - HONEYPOT 
 * Captures unauthorized access to sensitive/admin paths
 */

define('INTERNAL_LOG', true);
$log_action = 'security_alert';
$log_exam_type = 'security_threat';

// Log the attempt
include 'logger.php';

// Disguise: Show a 404 page
http_response_code(404);
include '../404.html';
exit;
