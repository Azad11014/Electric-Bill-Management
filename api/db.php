<?php
/**
 * db.php — Database Connection
 * ----------------------------
 * This file creates ONE connection to MySQL and returns it.
 * Every other API file will include this file to get the connection.
 *
 * We use mysqli (MySQL Improved) — it comes built into PHP with XAMPP.
 */

// ── Your XAMPP database credentials ──────────────────────────
$host     = 'localhost';      // XAMPP MySQL always runs on localhost
$user     = 'root';           // Default XAMPP username (no password by default)
$password = '';               // Leave empty for XAMPP default setup
$database = 'electricity_billing'; // The database we created in phpMyAdmin

// ── Create the connection ─────────────────────────────────────
// mysqli() tries to open a connection. If it fails, we stop immediately.
$conn = new mysqli($host, $user, $password, $database);

// ── Check if connection worked ────────────────────────────────
if ($conn->connect_error) {
    // Send a JSON error response and stop execution
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit(); // Stop. Nothing else will run after this.
}

// ── Set character encoding to UTF-8 ──────────────────────────
// Prevents garbled characters with special symbols or languages
$conn->set_charset('utf8');

// At this point $conn is a valid, open database connection.
// Other files will use it after including this file.