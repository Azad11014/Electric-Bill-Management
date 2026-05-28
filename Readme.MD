Setting Up the Electricity Billing System

Step 1 — Install and Start XAMPP
Download XAMPP from apachefriends.org and install it. Once installed, open the XAMPP Control Panel and click Start next to both Apache and MySQL. Both rows should turn green. Apache is the web server that runs your PHP files. MySQL is the database that stores your data.

Step 2 — Copy the Project Files
Clone or download the project from GitHub, then move the folder into XAMPP's web root so Apache can serve it.
C:\xampp\htdocs\Electric-Bill-Management\

Step 3 — Open phpMyAdmin
Open your browser and go to:
http://localhost/phpmyadmin
This is a visual tool for managing your MySQL database. No password is needed on a default XAMPP install.

Step 4 — Create the Database
In phpMyAdmin, look at the left sidebar and click New. A form will appear asking for a database name. Type exactly:
electricity_billing
Leave the collation as default and click Create. The database will appear in the left sidebar.

Step 5 — Run the SQL Commands
Click on your new electricity_billing database in the sidebar, then click the SQL tab at the top. You will see a text box. Paste the following SQL into it and click Go.
sql-- Create the customers table
-- This stores every customer's name, meter number, and address
CREATE TABLE customers (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    meter_no   VARCHAR(50)  NOT NULL UNIQUE,
    address    VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create the bills table
-- This stores every generated bill linked to a customer
CREATE TABLE bills (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    customer_id    INT NOT NULL,
    units_consumed DECIMAL(10,2) NOT NULL,
    energy_charge  DECIMAL(10,2) NOT NULL,
    tax            DECIMAL(10,2) NOT NULL,
    fixed_charge   DECIMAL(10,2) NOT NULL,
    surcharge      DECIMAL(10,2) NOT NULL,
    total_bill     DECIMAL(10,2) NOT NULL,
    bill_date      DATE NOT NULL,
    due_date       DATE NOT NULL,
    status         ENUM('Unpaid','Paid') DEFAULT 'Unpaid',
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

-- Insert one sample customer so you can test immediately
INSERT INTO customers (name, meter_no, address)
VALUES ('Ravi Kumar', 'MTR-1001', '12 MG Road, Kolkata');
You should see a green success bar. Both tables are now created.

Step 6 — Verify the Tables Were Created
In the left sidebar, click the arrow next to electricity_billing. You should see two tables listed: customers and bills. Click on customers and then click the Browse tab — you should see the sample customer row that was inserted.

Step 7 — Open the Application
Open your browser and go to:
http://localhost/Electric-Bill-Management/
The app will load. You can now add customers, generate bills, and view all bills from the three tabs.

If Something Goes Wrong
Blank page or "connection failed" — make sure both Apache and MySQL are green in XAMPP Control Panel.
"Table already exists" error — the table was already created from a previous run. Click Browse on each table to confirm the data is there and skip to Step 7.
Wrong URL — the folder name in the URL must match exactly what is in your htdocs folder. If your folder is named electricity-billing (with a lowercase dash) then the URL must be http://localhost/electricity-billing/.