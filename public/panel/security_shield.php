<?php
/**
 * SECURITY SHIELD - HONEYPOT v2.0
 * Captures unauthorized access to sensitive/admin paths
 */

define('INTERNAL_LOG', true);
require_once 'logger.php';

// Log the attempt
run_visitor_logger('security_alert', 'security_threat');

// Disguise: Show a 404 page
header("HTTP/1.1 404 Not Found");
include '../404.html';
exit;
