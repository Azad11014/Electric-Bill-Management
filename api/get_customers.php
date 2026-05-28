<?php
/**
 * get_customers.php — API Endpoint
 * ----------------------------------
 * METHOD : GET
 * PURPOSE: Fetch all customers from the database and return as JSON.
 *          The frontend uses this to populate dropdown menus.
 *
 * RETURNS (JSON):
 *   {
 *     "success": true,
 *     "customers": [
 *       { "id": 1, "name": "Ravi Kumar", "meter_no": "MTR-1001", "address": "..." },
 *       ...
 *     ]
 *   }
 */

// ── 1. Headers ────────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// ── 2. Only allow GET ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only GET method allowed']);
    exit();
}

// ── 3. Connect to DB ──────────────────────────────────────────
require_once 'db.php';

// ── 4. Query all customers, newest first ──────────────────────
// No user input here, so no prepared statement needed (no injection risk)
$result = $conn->query("SELECT id, name, meter_no, address, created_at FROM customers ORDER BY created_at DESC");

if (!$result) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Query failed: ' . $conn->error]);
    exit();
}

// ── 5. Collect all rows into a PHP array ─────────────────────
$customers = [];

// fetch_assoc() returns one row at a time as an associative array
// The while loop keeps going until there are no more rows (returns null)
while ($row = $result->fetch_assoc()) {
    $customers[] = $row; // Append each row to our array
}

// ── 6. Send the response ──────────────────────────────────────
// json_encode() converts the PHP array into a JSON string
echo json_encode([
    'success'   => true,
    'customers' => $customers
]);

// ── 7. Clean up ───────────────────────────────────────────────
$result->free();
$conn->close();