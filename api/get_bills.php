<?php
/**
 * get_bills.php — API Endpoint (Fixed)
 * --------------------------------------
 * METHOD : GET
 * PURPOSE: Fetch all bills, optionally filtered by customer_id.
 *
 * FIX: Replaced $stmt->get_result() with bind_result() + fetch()
 *      so it works on ALL XAMPP setups without needing MySQLnd.
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

// ── 4. Check for optional customer_id filter in the URL ───────
// e.g. ?customer_id=2  →  $_GET['customer_id'] = "2"
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : null;

// ── 5. Run the correct query ──────────────────────────────────

$bills = []; // We'll fill this array with rows

if ($customer_id !== null && $customer_id > 0) {

    // ── FILTERED: prepared statement (user input → must be safe) ──
    $sql = "
        SELECT
            b.id            AS bill_id,
            c.name          AS customer_name,
            c.meter_no,
            b.units_consumed,
            b.energy_charge,
            b.tax,
            b.fixed_charge,
            b.surcharge,
            b.total_bill,
            b.bill_date,
            b.due_date,
            b.status
        FROM bills b
        JOIN customers c ON b.customer_id = c.id
        WHERE b.customer_id = ?
        ORDER BY b.bill_date DESC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
        exit();
    }

    // Bind the customer_id placeholder
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();

    // bind_result() maps each column to a PHP variable.
    // Order must exactly match the SELECT column order above.
    $stmt->bind_result(
        $bill_id, $customer_name, $meter_no,
        $units_consumed, $energy_charge, $tax,
        $fixed_charge, $surcharge, $total_bill,
        $bill_date, $due_date, $status
    );

    // fetch() reads one row at a time into the bound variables
    while ($stmt->fetch()) {
        // Each iteration: copy the variables into a new array entry
        $bills[] = [
            'bill_id'        => $bill_id,
            'customer_name'  => $customer_name,
            'meter_no'       => $meter_no,
            'units_consumed' => $units_consumed,
            'energy_charge'  => $energy_charge,
            'tax'            => $tax,
            'fixed_charge'   => $fixed_charge,
            'surcharge'      => $surcharge,
            'total_bill'     => $total_bill,
            'bill_date'      => $bill_date,
            'due_date'       => $due_date,
            'status'         => $status,
        ];
    }

    $stmt->close();

} else {

    // ── ALL BILLS: no user input → direct query is safe ───────
    $sql = "
        SELECT
            b.id            AS bill_id,
            c.name          AS customer_name,
            c.meter_no,
            b.units_consumed,
            b.energy_charge,
            b.tax,
            b.fixed_charge,
            b.surcharge,
            b.total_bill,
            b.bill_date,
            b.due_date,
            b.status
        FROM bills b
        JOIN customers c ON b.customer_id = c.id
        ORDER BY b.bill_date DESC
    ";

    $result = $conn->query($sql);

    if (!$result) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Query failed: ' . $conn->error]);
        exit();
    }

    // fetch_assoc() returns one row as an associative array
    while ($row = $result->fetch_assoc()) {
        $bills[] = $row;
    }

    $result->free();
}

// ── 6. Send the JSON response ─────────────────────────────────
echo json_encode([
    'success' => true,
    'count'   => count($bills),
    'bills'   => $bills
]);

$conn->close();