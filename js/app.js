/**
 * app.js — Frontend Logic
 * ------------------------
 * This file handles everything the user sees and does:
 *   1. Tab switching
 *   2. Fetching customers from the API
 *   3. Adding new customers (POST to API)
 *   4. Generating bills (POST to API)
 *   5. Loading and displaying bills (GET from API)
 *
 * KEY CONCEPT — fetch():
 *   fetch() is the modern way to make HTTP requests from JavaScript.
 *   It is "asynchronous" — meaning it doesn't freeze the page while waiting.
 *   We use async/await to write it in a readable, step-by-step style.
 */

// ── Base URL for all API calls ────────────────────────────────
// Change this if your XAMPP folder is named differently
const API_BASE = 'http://localhost/electricity-billing/api';


// ════════════════════════════════════════════════════════════
// SECTION 1 — TAB NAVIGATION
// ════════════════════════════════════════════════════════════

/**
 * Sets up click listeners on all .tab-btn elements.
 * When clicked, the matching .tab-panel becomes visible.
 */
function initTabs() {
  const buttons = document.querySelectorAll('.tab-btn');
  const panels  = document.querySelectorAll('.tab-panel');

  buttons.forEach(btn => {
    btn.addEventListener('click', () => {
      // Remove 'active' from all buttons and panels
      buttons.forEach(b => b.classList.remove('active'));
      panels.forEach(p  => p.classList.remove('active'));

      // Add 'active' to the clicked button
      btn.classList.add('active');

      // Find the matching panel using data-tab attribute and show it
      const targetId = btn.dataset.tab; // e.g. "customers"
      document.getElementById(targetId).classList.add('active');

      // Reload data when switching to the bills tab
      if (targetId === 'bills') loadBills();
      if (targetId === 'generate') populateCustomerDropdowns();
    });
  });
}


// ════════════════════════════════════════════════════════════
// SECTION 2 — UTILITY FUNCTIONS
// ════════════════════════════════════════════════════════════

/**
 * Formats a number as Indian Rupees.
 * e.g.  1234.5  →  "₹1,234.50"
 */
function formatRupee(amount) {
  return '₹' + parseFloat(amount).toLocaleString('en-IN', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  });
}

/**
 * Shows an alert message inside a container.
 * @param {string} elementId  - The id of the .alert div
 * @param {string} message    - Text to display
 * @param {string} type       - 'success' or 'error'
 */
function showAlert(elementId, message, type) {
  const el = document.getElementById(elementId);
  el.textContent = message;
  el.className = `alert alert-${type} show`;

  // Auto-hide after 4 seconds
  setTimeout(() => { el.classList.remove('show'); }, 4000);
}

/**
 * Shows/hides a loading spinner button state.
 * @param {string} btnId   - The button element id
 * @param {boolean} loading - true = show spinner, false = restore
 * @param {string} label   - Original button text to restore
 */
function setLoading(btnId, loading, label = 'Submit') {
  const btn = document.getElementById(btnId);
  if (loading) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Loading...';
  } else {
    btn.disabled = false;
    btn.textContent = label;
  }
}


// ════════════════════════════════════════════════════════════
// SECTION 3 — CUSTOMERS TAB
// ════════════════════════════════════════════════════════════

/**
 * Fetches all customers from GET /api/get_customers.php
 * and renders them in the customers table.
 *
 * async/await explained:
 *   - async means this function can use await inside it
 *   - await pauses HERE (not the whole page) until the fetch is done
 *   - Then execution continues with the response
 */
async function loadCustomers() {
  const tbody = document.getElementById('customers-tbody');
  const statsEl = document.getElementById('total-customers');
  tbody.innerHTML = '<tr><td colspan="4" class="empty-state"><span class="spinner"></span> Loading...</td></tr>';

  try {
    // Step 1: Make the GET request to the API
    const response = await fetch(`${API_BASE}/get_customers.php`);

    // Step 2: Parse the JSON response body
    const data = await response.json();

    if (!data.success) throw new Error(data.message);

    const customers = data.customers;
    statsEl.textContent = customers.length;

    if (customers.length === 0) {
      tbody.innerHTML = '<tr><td colspan="4" class="empty-state">No customers yet. Add one!</td></tr>';
      return;
    }

    // Step 3: Build table rows from the data
    tbody.innerHTML = customers.map(c => `
      <tr>
        <td>${c.id}</td>
        <td>${c.name}</td>
        <td style="font-family:var(--mono)">${c.meter_no}</td>
        <td>${c.address || '—'}</td>
      </tr>
    `).join('');

  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="4" class="empty-state" style="color:var(--danger)">Error: ${err.message}</td></tr>`;
  }
}


/**
 * Handles the "Add Customer" form submission.
 * Reads the form, sends a POST request, shows feedback.
 */
async function addCustomer(event) {
  event.preventDefault(); // Stop the form from reloading the page

  setLoading('add-customer-btn', true);

  // Read values from the form fields
  const payload = {
    name:     document.getElementById('c-name').value.trim(),
    meter_no: document.getElementById('c-meter').value.trim(),
    address:  document.getElementById('c-address').value.trim()
  };

  try {
    // POST request — we send JSON in the body
    const response = await fetch(`${API_BASE}/add_customer.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' }, // Tell the API we're sending JSON
      body: JSON.stringify(payload) // Convert JS object → JSON string
    });

    const data = await response.json();

    if (!data.success) throw new Error(data.message);

    showAlert('customer-alert', `✅ ${data.message} (ID: ${data.customer_id})`, 'success');
    document.getElementById('add-customer-form').reset(); // Clear the form
    loadCustomers(); // Refresh the table

  } catch (err) {
    showAlert('customer-alert', `❌ ${err.message}`, 'error');
  } finally {
    // finally always runs, whether it succeeded or failed
    setLoading('add-customer-btn', false, 'Add Customer');
  }
}


// ════════════════════════════════════════════════════════════
// SECTION 4 — GENERATE BILL TAB
// ════════════════════════════════════════════════════════════

/**
 * Fetches all customers and populates the <select> dropdown
 * so the user can pick a customer when generating a bill.
 */
async function populateCustomerDropdowns() {
  const selects = document.querySelectorAll('.customer-select');
  selects.forEach(s => s.innerHTML = '<option value="">Loading...</option>');

  try {
    const response = await fetch(`${API_BASE}/get_customers.php`);
    const data     = await response.json();

    if (!data.success) throw new Error(data.message);

    const options = data.customers.map(c =>
      `<option value="${c.id}">${c.name} (${c.meter_no})</option>`
    ).join('');

    selects.forEach(s => {
      s.innerHTML = '<option value="">— Select Customer —</option>' + options;
    });

  } catch (err) {
    selects.forEach(s => s.innerHTML = '<option value="">Failed to load</option>');
  }
}


/**
 * Handles the "Generate Bill" form submission.
 * Sends customer_id + units_consumed to the API,
 * then renders the returned bill as a receipt.
 */
async function generateBill(event) {
  event.preventDefault();

  setLoading('generate-bill-btn', true);

  const payload = {
    customer_id:    parseInt(document.getElementById('g-customer').value),
    units_consumed: parseFloat(document.getElementById('g-units').value)
  };

  // Basic client-side check before even calling the API
  if (!payload.customer_id || isNaN(payload.units_consumed) || payload.units_consumed <= 0) {
    showAlert('bill-alert', '❌ Please select a customer and enter valid units.', 'error');
    setLoading('generate-bill-btn', false, 'Generate Bill');
    return;
  }

  try {
    const response = await fetch(`${API_BASE}/add_bill.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    const data = await response.json();
    if (!data.success) throw new Error(data.message);

    showAlert('bill-alert', '✅ Bill generated successfully!', 'success');

    // Show the receipt preview
    renderReceipt(data.bill);

  } catch (err) {
    showAlert('bill-alert', `❌ ${err.message}`, 'error');
  } finally {
    setLoading('generate-bill-btn', false, 'Generate Bill');
  }
}


/**
 * Takes the bill object returned by the API and renders
 * a receipt-style preview on the page.
 */
function renderReceipt(bill) {
  const preview = document.getElementById('bill-preview');
  preview.style.display = 'block';

  // Build the HTML for the receipt
  preview.innerHTML = `
    <h3>⚡ ELECTRICITY BILL RECEIPT</h3>
    <div class="receipt-row"><span>Bill ID</span><span>#${bill.bill_id}</span></div>
    <div class="receipt-row"><span>Bill Date</span><span>${bill.bill_date}</span></div>
    <div class="receipt-row"><span>Due Date</span><span>${bill.due_date}</span></div>
    <div class="receipt-row"><span>Category</span><span>${bill.category}</span></div>
    <div class="receipt-row"><span>Units Consumed</span><span>${bill.units_consumed} kWh</span></div>
    <br>
    <div class="receipt-row"><span>Energy Charge</span><span>${formatRupee(bill.energy_charge)}</span></div>
    <div class="receipt-row"><span>Tax (8%)</span><span>${formatRupee(bill.tax)}</span></div>
    <div class="receipt-row"><span>Fixed Charge</span><span>${formatRupee(bill.fixed_charge)}</span></div>
    ${bill.surcharge > 0
      ? `<div class="receipt-row"><span>Surcharge (5%)</span><span>${formatRupee(bill.surcharge)}</span></div>`
      : ''}
    <div class="receipt-row total"><span>TOTAL BILL</span><span>${formatRupee(bill.total_bill)}</span></div>
    <div style="text-align:center;margin-top:14px;color:var(--danger);font-weight:700">Status: ${bill.status}</div>
  `;
}


// ════════════════════════════════════════════════════════════
// SECTION 5 — ALL BILLS TAB
// ════════════════════════════════════════════════════════════

/**
 * Fetches all bills from the API and renders them in a table.
 * Optionally filters by customer_id if one is selected.
 */
async function loadBills() {
  const tbody   = document.getElementById('bills-tbody');
  const statsEl = document.getElementById('total-bills');
  const filter  = document.getElementById('filter-customer')?.value;

  tbody.innerHTML = '<tr><td colspan="8" class="empty-state"><span class="spinner"></span> Loading...</td></tr>';

  // Build the URL — add ?customer_id= only if a filter is selected
  let url = `${API_BASE}/get_bills.php`;
  if (filter) url += `?customer_id=${filter}`;

  try {
    const response = await fetch(url);
    const data     = await response.json();

    if (!data.success) throw new Error(data.message);

    statsEl.textContent = data.count;

    if (data.bills.length === 0) {
      tbody.innerHTML = '<tr><td colspan="8" class="empty-state">No bills found.</td></tr>';
      return;
    }

    tbody.innerHTML = data.bills.map(b => `
      <tr>
        <td>#${b.bill_id}</td>
        <td>${b.customer_name}</td>
        <td style="font-family:var(--mono)">${b.meter_no}</td>
        <td>${b.units_consumed} kWh</td>
        <td>${formatRupee(b.total_bill)}</td>
        <td>${b.bill_date}</td>
        <td>${b.due_date}</td>
        <td><span class="badge badge-${b.status.toLowerCase()}">${b.status}</span></td>
      </tr>
    `).join('');

  } catch (err) {
    tbody.innerHTML = `<tr><td colspan="8" class="empty-state" style="color:var(--danger)">Error: ${err.message}</td></tr>`;
  }
}


// ════════════════════════════════════════════════════════════
// SECTION 6 — BOOT (runs when the page finishes loading)
// ════════════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', () => {
  // Wire up tabs
  initTabs();

  // Wire up form submissions
  document.getElementById('add-customer-form').addEventListener('submit', addCustomer);
  document.getElementById('generate-bill-form').addEventListener('submit', generateBill);

  // Wire up the filter dropdown on the bills tab
  document.getElementById('filter-customer').addEventListener('change', loadBills);

  // Load initial data for the first visible tab
  loadCustomers();
  populateCustomerDropdowns();
});