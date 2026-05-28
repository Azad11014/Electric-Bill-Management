<?php
/**
 * add_bill.php — API Endpoint
 * ----------------------------
 * METHOD : POST
 * PURPOSE: Receive customer_id + units_consumed from the frontend,
 *          run all the billing calculations (same logic as before),
 *          and save the final bill to the database.
 *
 * EXPECTS (JSON body):
 *   { "customer_id": 1, "units_consumed": 250 }
 *
 * RETURNS (JSON):
 *   {
 *     "success": true,
 *     "message": "Bill generated",
 *     "bill": { ...all bill details... }
 *   }
 */

// ── 1. Headers ────────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST method allowed']);
    exit();
}

// ── 2. Billing rate constants (same as before) ────────────────
define('RATE_SLAB1',            2.00);
define('RATE_SLAB2',            3.50);
define('RATE_SLAB3',            5.00);
define('RATE_SLAB4',            7.00);
define('FIXED_CHARGE',         50.00);
define('TAX_RATE',              0.08);
define('SURCHARGE_THRESHOLD',   300);
define('SURCHARGE_RATE',        0.05);

// ── 3. Billing calculation functions ─────────────────────────

/**
 * Calculate energy charge using tiered slab rates.
 */
function calculateEnergyCharge(float $units): float
{
    if ($units <= 100) {
        return $units * RATE_SLAB1;
    } elseif ($units <= 200) {
        return (100 * RATE_SLAB1) + (($units - 100) * RATE_SLAB2);
    } elseif ($units <= 300) {
        return (100 * RATE_SLAB1) + (100 * RATE_SLAB2) + (($units - 200) * RATE_SLAB3);
    } else {
        return (100 * RATE_SLAB1) + (100 * RATE_SLAB2) + (100 * RATE_SLAB3) + (($units - 300) * RATE_SLAB4);
    }
}

/**
 * Determine consumer category label.
 */
function getCategory(float $units): string
{
    if ($units <= 100)       return 'Low Consumer';
    elseif ($units <= 200)   return 'Normal Consumer';
    elseif ($units <= 300)   return 'Moderate Consumer';
    else                     return 'Heavy Consumer';
}

// ── 4. Parse and validate the request body ───────────────────
$data = json_decode(file_get_contents('php://input'));

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit();
}

$customer_id    = intval($data->customer_id    ?? 0);
$units_consumed = floatval($data->units_consumed ?? 0);

// intval() safely converts to integer; floatval() to a decimal number
if ($customer_id <= 0 || $units_consumed <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid customer_id and units_consumed are required']);
    exit();
}

// ── 5. Connect to DB ──────────────────────────────────────────
require_once 'db.php';

// ── 6. Verify the customer actually exists ────────────────────
$check = $conn->prepare("SELECT id FROM customers WHERE id = ?");
$check->bind_param('i', $customer_id); // 'i' = integer
$check->execute();
$check->store_result(); // Needed to use num_rows

if ($check->num_rows === 0) {
    http_response_code(404); // 404 = Not Found
    echo json_encode(['success' => false, 'message' => 'Customer not found']);
    $check->close();
    $conn->close();
    exit();
}
$check->close();

// ── 7. Run all billing calculations ───────────────────────────
$energy_charge  = calculateEnergyCharge($units_consumed);
$tax            = round($energy_charge * TAX_RATE, 2);
$fixed_charge   = FIXED_CHARGE;
$subtotal       = $energy_charge + $tax + $fixed_charge;

// Surcharge only applies above the threshold
$surcharge = ($units_consumed > SURCHARGE_THRESHOLD)
    ? round($subtotal * SURCHARGE_RATE, 2)
    : 0.00;

$total_bill = round($subtotal + $surcharge, 2);
$category   = getCategory($units_consumed);

// Dates
$bill_date = date('Y-m-d');                               // Today
$due_date  = date('Y-m-d', strtotime('+30 days'));        // 30 days from now

// ── 8. Save the bill to the database ─────────────────────────
$sql = "INSERT INTO bills
            (customer_id, units_consumed, energy_charge, tax, fixed_charge, surcharge, total_bill, bill_date, due_date)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?)";

// Type string: i=integer, d=double(decimal), s=string
$stmt = $conn->prepare($sql);
$stmt->bind_param(
    'iddddddss',
    $customer_id,
    $units_consumed,
    $energy_charge,
    $tax,
    $fixed_charge,
    $surcharge,
    $total_bill,
    $bill_date,
    $due_date
);

if ($stmt->execute()) {
    $bill_id = $conn->insert_id;

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Bill generated successfully',
        'bill'    => [
            'bill_id'        => $bill_id,
            'customer_id'    => $customer_id,
            'category'       => $category,
            'units_consumed' => $units_consumed,
            'energy_charge'  => $energy_charge,
            'tax'            => $tax,
            'fixed_charge'   => $fixed_charge,
            'surcharge'      => $surcharge,
            'total_bill'     => $total_bill,
            'bill_date'      => $bill_date,
            'due_date'       => $due_date,
            'status'         => 'Unpaid'
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save bill: ' . $stmt->error]);
}

$stmt->close();
$conn->close();