<?php
/**
 * add_customer.php — API Endpoint
 * --------------------------------
 * METHOD : POST
 * PURPOSE: Receive customer details from the frontend,
 *          validate them, and save to the database.
 *
 * EXPECTS (JSON body):
 *   { "name": "Ravi Kumar", "meter_no": "MTR-1001", "address": "Kolkata" }
 *
 * RETURNS (JSON):
 *   { "success": true, "message": "Customer added", "customer_id": 3 }
 */

// ── 1. CORS & JSON headers ────────────────────────────────────
// These headers tell the browser:
//   - Any page can call this API (Access-Control-Allow-Origin)
//   - We will always respond with JSON
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// When a browser sends a "preflight" OPTIONS request, just say OK and stop.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ── 2. Only allow POST requests ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // 405 = Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Only POST method allowed']);
    exit();
}

// ── 3. Get the JSON body sent from the frontend ───────────────
// file_get_contents('php://input') reads the raw request body
$body = file_get_contents('php://input');

// json_decode turns the JSON string into a PHP object
$data = json_decode($body);

// Check that JSON was valid
if (!$data) {
    http_response_code(400); // 400 = Bad Request
    echo json_encode(['success' => false, 'message' => 'Invalid JSON body']);
    exit();
}

// ── 4. Validate required fields ───────────────────────────────
// trim() removes leading/trailing whitespace from a string
$name     = trim($data->name     ?? '');
$meter_no = trim($data->meter_no ?? '');
$address  = trim($data->address  ?? '');

// Check that name and meter number are not empty
if (empty($name) || empty($meter_no)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Name and Meter Number are required']);
    exit();
}

// ── 5. Connect to the database ────────────────────────────────
require_once 'db.php'; // This gives us the $conn variable

// ── 6. Prepare the SQL statement ──────────────────────────────
// We use a "prepared statement" for security.
// The ? placeholders prevent SQL Injection attacks.
// SQL Injection = a hacker sending malicious SQL inside form fields.
$sql = "INSERT INTO customers (name, meter_no, address) VALUES (?, ?, ?)";

// prepare() creates a prepared statement
$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Query preparation failed']);
    exit();
}

// ── 7. Bind the real values to the ? placeholders ─────────────
// "sss" means three Strings. Other types: i=integer, d=double, b=blob
$stmt->bind_param('sss', $name, $meter_no, $address);

// ── 8. Execute the query ──────────────────────────────────────
if ($stmt->execute()) {
    // insert_id gives us the auto-generated ID of the new row
    $new_id = $conn->insert_id;

    http_response_code(201); // 201 = Created
    echo json_encode([
        'success'     => true,
        'message'     => 'Customer added successfully',
        'customer_id' => $new_id
    ]);
} else {
    // Check for duplicate meter number (MySQL error 1062 = duplicate entry)
    if ($conn->errno === 1062) {
        http_response_code(409); // 409 = Conflict
        echo json_encode(['success' => false, 'message' => 'Meter number already exists']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to add customer: ' . $stmt->error]);
    }
}

// ── 9. Clean up ───────────────────────────────────────────────
$stmt->close();
$conn->close();