<?php
// ============================================================
// Database connection — InfinityFree deployment settings
// ============================================================

$host   = "sql207.infinityfree.com";      // MySQL hostname from control panel
$user   = "if0_42776628";                 // MySQL username from control panel
$pass   = "Chanuka2903";                  // MySQL password from control panel
$dbname = "if0_42776628_hamadema";        // ⚠️ CONFIRM this matches your actual database name below

$conn = new mysqli($host, $user, $pass, $dbname);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}