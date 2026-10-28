-- =====================================================================
-- M-GARAGE INVENTORY AND SERVICE MANAGEMENT SYSTEM  |  Database schema
-- Import with phpMyAdmin (Import tab) or: mysql -u root < m_garage.sql
-- =====================================================================
CREATE DATABASE IF NOT EXISTS m_garage CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE m_garage;
SET FOREIGN_KEY_CHECKS = 0;

-- users: application logins. Only two roles exist: admin and cashier.
CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(120) NOT NULL,
  role ENUM('admin','cashier') NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  last_login DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB;

-- categories: groups for inventory items.
CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB;

-- suppliers: businesses that supply parts.
CREATE TABLE suppliers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  contact_person VARCHAR(120) NULL,
  phone VARCHAR(30) NULL,
  address VARCHAR(255) NULL,
  notes TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_suppliers_name (name)
) ENGINE=InnoDB;

-- inventory_items: parts, accessories and maintenance products with live stock.
CREATE TABLE inventory_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(50) NOT NULL,
  name VARCHAR(150) NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  brand VARCHAR(80) NULL,
  compatibility VARCHAR(255) NULL,
  description TEXT NULL,
  supplier_id INT UNSIGNED NULL,
  purchase_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  selling_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  stock_qty INT NOT NULL DEFAULT 0,
  min_stock INT NOT NULL DEFAULT 5,
  unit VARCHAR(20) NOT NULL DEFAULT 'pc',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_items_sku (sku),
  KEY idx_items_name (name), KEY idx_items_brand (brand), KEY idx_items_stock (stock_qty),
  CONSTRAINT chk_items_stock CHECK (stock_qty >= 0),
  CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES categories(id) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_items_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- stock_receivings: header of an incoming-stock transaction from a supplier.
CREATE TABLE stock_receivings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reference_no VARCHAR(50) NOT NULL,
  supplier_id INT UNSIGNED NOT NULL,
  date_received DATE NOT NULL,
  total_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  notes TEXT NULL,
  received_by INT UNSIGNED NOT NULL,
  request_token CHAR(32) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_receiving_ref (reference_no),
  UNIQUE KEY uq_receiving_token (request_token),
  CONSTRAINT fk_rec_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON UPDATE CASCADE,
  CONSTRAINT fk_rec_user FOREIGN KEY (received_by) REFERENCES users(id) ON UPDATE CASCADE
) ENGINE=InnoDB;

-- stock_receiving_items: line items of a receiving transaction.
CREATE TABLE stock_receiving_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  receiving_id INT UNSIGNED NOT NULL,
  item_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_cost DECIMAL(12,2) NOT NULL,
  line_total DECIMAL(12,2) NOT NULL,
  KEY idx_ri_item (item_id),
  CONSTRAINT fk_ri_receiving FOREIGN KEY (receiving_id) REFERENCES stock_receivings(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_ri_item FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON UPDATE CASCADE
) ENGINE=InnoDB;

-- inventory_movements: audit trail of every stock change (never edited or deleted).
CREATE TABLE inventory_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id INT UNSIGNED NOT NULL,
  movement_type ENUM('receiving','sale','repair_use','adjustment','reversal') NOT NULL,
  quantity_change INT NOT NULL,
  qty_before INT NOT NULL,
  qty_after INT NOT NULL,
  reference_type VARCHAR(30) NULL,
  reference_id INT UNSIGNED NULL,
  reason VARCHAR(255) NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_mov_item (item_id), KEY idx_mov_ref (reference_type, reference_id), KEY idx_mov_date (created_at),
  CONSTRAINT fk_mov_item FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON UPDATE CASCADE,
  CONSTRAINT fk_mov_user FOREIGN KEY (created_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- customers: people who buy or bring motorcycles for repair.
CREATE TABLE customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  contact_number VARCHAR(30) NULL,
  address VARCHAR(255) NULL,
  notes TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  date_registered TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_cust_name (full_name), KEY idx_cust_contact (contact_number)
) ENGINE=InnoDB;

-- motorcycles: one customer can own many motorcycles.
CREATE TABLE motorcycles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  brand VARCHAR(80) NOT NULL,
  model VARCHAR(80) NOT NULL,
  plate_number VARCHAR(20) NULL,
  engine_chassis_no VARCHAR(60) NULL,
  year_model VARCHAR(10) NULL,
  notes TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_moto_plate (plate_number), KEY idx_moto_model (model),
  CONSTRAINT fk_moto_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- mechanics: personnel records only (they never log in).
CREATE TABLE mechanics (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(150) NOT NULL,
  contact_number VARCHAR(30) NULL,
  specialty VARCHAR(150) NULL,
  employment_status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  notes TEXT NULL,
  is_demo TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- repair_jobs: service orders with calculated totals (computed server-side).
CREATE TABLE repair_jobs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_no VARCHAR(30) NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  motorcycle_id INT UNSIGNED NOT NULL,
  date_received DATE NOT NULL,
  reported_problem TEXT NOT NULL,
  diagnosis TEXT NULL,
  requested_services TEXT NULL,
  status ENUM('Pending','In Progress','Waiting for Parts','Completed','Released','Cancelled') NOT NULL DEFAULT 'Pending',
  parts_subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  labor_subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_due DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  payment_status ENUM('Unpaid','Partially Paid','Paid') NOT NULL DEFAULT 'Unpaid',
  is_finalized TINYINT(1) NOT NULL DEFAULT 0,
  date_completed DATE NULL,
  notes TEXT NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_jobs_no (job_no),
  KEY idx_jobs_status (status), KEY idx_jobs_date (date_received),
  CONSTRAINT fk_jobs_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON UPDATE CASCADE,
  CONSTRAINT fk_jobs_moto FOREIGN KEY (motorcycle_id) REFERENCES motorcycles(id) ON UPDATE CASCADE,
  CONSTRAINT fk_jobs_user FOREIGN KEY (created_by) REFERENCES users(id) ON UPDATE CASCADE
) ENGINE=InnoDB;

-- repair_mechanics: mechanics assigned to a job (many-to-many).
CREATE TABLE repair_mechanics (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  repair_job_id INT UNSIGNED NOT NULL,
  mechanic_id INT UNSIGNED NOT NULL,
  assigned_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_job_mech (repair_job_id, mechanic_id),
  CONSTRAINT fk_rm_job FOREIGN KEY (repair_job_id) REFERENCES repair_jobs(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_rm_mech FOREIGN KEY (mechanic_id) REFERENCES mechanics(id) ON UPDATE CASCADE
) ENGINE=InnoDB;

-- labor_entries: labor charges per job (separate from wages/salary).
CREATE TABLE labor_entries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  repair_job_id INT UNSIGNED NOT NULL,
  mechanic_id INT UNSIGNED NOT NULL,
  service_type VARCHAR(120) NOT NULL,
  description TEXT NULL,
  service_date DATE NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  status ENUM('active','removed') NOT NULL DEFAULT 'active',
  request_token CHAR(32) NULL,
  created_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_labor_token (request_token),
  KEY idx_labor_mech (mechanic_id),
  CONSTRAINT fk_labor_job FOREIGN KEY (repair_job_id) REFERENCES repair_jobs(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_labor_mech FOREIGN KEY (mechanic_id) REFERENCES mechanics(id) ON UPDATE CASCADE,
  CONSTRAINT fk_labor_user FOREIGN KEY (created_by) REFERENCES users(id) ON UPDATE CASCADE
) ENGINE=InnoDB;

-- repair_parts_used: parts consumed by a repair; price frozen at time of use.
CREATE TABLE repair_parts_used (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  repair_job_id INT UNSIGNED NOT NULL,
  item_id INT UNSIGNED NOT NULL,
  mechanic_id INT UNSIGNED NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  line_subtotal DECIMAL(12,2) NOT NULL,
  status ENUM('active','removed','reversed') NOT NULL DEFAULT 'active',
  request_token CHAR(32) NULL,
  recorded_by INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_parts_token (request_token),
  KEY idx_parts_job (repair_job_id), KEY idx_parts_item (item_id), KEY idx_parts_mechanic (mechanic_id),
  CONSTRAINT fk_parts_job FOREIGN KEY (repair_job_id) REFERENCES repair_jobs(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_parts_item FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON UPDATE CASCADE,
  CONSTRAINT fk_parts_mechanic FOREIGN KEY (mechanic_id) REFERENCES mechanics(id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_parts_user FOREIGN KEY (recorded_by) REFERENCES users(id) ON UPDATE CASCADE
) ENGINE=InnoDB;

-- sales: direct point-of-sale transactions (parts sold over the counter).
CREATE TABLE sales (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  receipt_no VARCHAR(30) NOT NULL,
  customer_id INT UNSIGNED NULL,
  customer_name VARCHAR(150) NULL,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  status ENUM('completed','cancelled','refunded') NOT NULL DEFAULT 'completed',
  cashier_id INT UNSIGNED NOT NULL,
  cancel_reason VARCHAR(255) NULL,
  cancelled_by INT UNSIGNED NULL,
  cancelled_at DATETIME NULL,
  request_token CHAR(32) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sales_receipt (receipt_no),
  UNIQUE KEY uq_sales_token (request_token),
  KEY idx_sales_date (created_at),
  CONSTRAINT fk_sales_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_sales_cashier FOREIGN KEY (cashier_id) REFERENCES users(id) ON UPDATE CASCADE,
  CONSTRAINT fk_sales_cancel FOREIGN KEY (cancelled_by) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- sale_items: line items of a sale (price and cost frozen at sale time).
CREATE TABLE sale_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_id INT UNSIGNED NOT NULL,
  item_id INT UNSIGNED NOT NULL,
  quantity INT UNSIGNED NOT NULL,
  unit_price DECIMAL(12,2) NOT NULL,
  unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  line_subtotal DECIMAL(12,2) NOT NULL,
  KEY idx_si_item (item_id),
  CONSTRAINT fk_si_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_si_item FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON UPDATE CASCADE
) ENGINE=InnoDB;

-- payments: cash payments linked to either a sale or a repair job.
CREATE TABLE payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_no VARCHAR(30) NOT NULL,
  payable_type ENUM('sale','repair') NOT NULL,
  sale_id INT UNSIGNED NULL,
  repair_job_id INT UNSIGNED NULL,
  amount_due DECIMAL(12,2) NOT NULL,
  amount_tendered DECIMAL(12,2) NOT NULL,
  amount_applied DECIMAL(12,2) NOT NULL,
  change_given DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  method ENUM('cash') NOT NULL DEFAULT 'cash',
  status ENUM('completed','voided') NOT NULL DEFAULT 'completed',
  received_by INT UNSIGNED NOT NULL,
  notes VARCHAR(255) NULL,
  request_token CHAR(32) NULL,
  paid_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pay_no (payment_no),
  UNIQUE KEY uq_pay_token (request_token),
  KEY idx_pay_sale (sale_id), KEY idx_pay_job (repair_job_id), KEY idx_pay_date (paid_at),
  CONSTRAINT fk_pay_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON UPDATE CASCADE,
  CONSTRAINT fk_pay_job FOREIGN KEY (repair_job_id) REFERENCES repair_jobs(id) ON UPDATE CASCADE,
  CONSTRAINT fk_pay_user FOREIGN KEY (received_by) REFERENCES users(id) ON UPDATE CASCADE
) ENGINE=InnoDB;

-- activity_logs: audit trail of important user actions.
CREATE TABLE activity_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(60) NOT NULL,
  module VARCHAR(40) NOT NULL,
  description VARCHAR(500) NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_log_user (user_id), KEY idx_log_date (created_at),
  CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- settings: key/value system settings.
CREATE TABLE settings (
  setting_key VARCHAR(60) PRIMARY KEY,
  setting_value VARCHAR(500) NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- DEMONSTRATION DATA (is_demo = 1). Safe to delete before real use.
-- Demo accounts must change password on first login. Password hashes are
-- rotated separately for this installation; replace them with fresh hashes
-- before distributing a database dump.
-- =====================================================================
INSERT INTO users (username, password_hash, full_name, role, must_change_password, is_demo) VALUES
('admin',   '$2y$10$GAt9kQYkXEAoP2oVIovoeeq/0etsQ29jaQqm5Cp8Y1Fya1LRx2PYW', 'Demo Administrator', 'admin',   1, 1),
('cashier', '$2y$10$1Z69t/tj/9.66q6doo3HAeT7haW8LxbjmneTnH6JViDr.caXSi.qS', 'Demo Cashier',       'cashier', 1, 1);

INSERT INTO settings (setting_key, setting_value) VALUES
('shop_name','M-Garage'),
('shop_address','Poblacion, Inabanga, Bohol'),
('shop_contact',''),
('receipt_footer','Thank you for choosing M-Garage!'),
('currency_symbol','₱');

INSERT INTO categories (name, description) VALUES
('Engine Parts','Spark plugs, gaskets, pistons and related'),
('Brake System','Brake pads, shoes, discs and fluid'),
('Electrical Parts','Bulbs, batteries, switches and wiring'),
('Lubricants and Engine Oil','Engine oil, gear oil, grease'),
('Chains and Sprockets','Drive chains and sprocket sets'),
('Tires and Wheels','Tires, tubes and wheel parts'),
('Motorcycle Accessories','Mirrors, grips, covers'),
('Maintenance Products','Cleaners, chain lube, polish');

INSERT INTO suppliers (name, contact_person, phone, address, notes, is_demo) VALUES
('DEMO Bohol Moto Supply','Sample Contact','09000000001','Tagbilaran City, Bohol','Demonstration supplier',1),
('DEMO Visayas Parts Depot','Sample Contact','09000000002','Cebu City','Demonstration supplier',1);

INSERT INTO inventory_items (sku,name,category_id,brand,compatibility,supplier_id,purchase_cost,selling_price,stock_qty,min_stock,unit,is_demo) VALUES
('ENG-SP-001','Spark Plug',1,'NGK','Most 110-150cc',1,70.00,120.00,40,10,'pc',1),
('BRK-PD-001','Brake Pad (Front)',2,'Nissin','Honda Click, Beat',1,180.00,280.00,15,5,'set',1),
('BRK-SH-001','Brake Shoe (Rear)',2,'Generic','Honda XRM, Wave',1,120.00,200.00,12,5,'set',1),
('ELE-BL-001','Headlight Bulb',3,'Osram','Universal 12V',2,45.00,90.00,25,8,'pc',1),
('ELE-BT-001','Battery 12V 5Ah',3,'Motolite','Scooters',2,650.00,950.00,6,3,'pc',1),
('OIL-EO-001','Engine Oil 1L 10W-40',4,'Shell Advance','4-stroke',1,230.00,320.00,30,10,'bottle',1),
('OIL-GO-001','Gear Oil 120ml',4,'Motul','Scooters',1,55.00,95.00,20,8,'bottle',1),
('CHN-CH-001','Drive Chain 428H',5,'DID','125cc',2,420.00,650.00,8,3,'pc',1),
('CHN-SP-001','Sprocket Set',5,'Generic','Honda XRM',2,550.00,850.00,2,3,'set',1),
('TIR-TR-001','Tire 80/90-14',6,'Michelin','Scooters',2,1100.00,1500.00,4,2,'pc',1),
('ACC-MR-001','Side Mirror (Pair)',7,'Generic','Universal',1,150.00,250.00,0,4,'pair',1),
('MNT-CL-001','Chain Lube 150ml',8,'Motul','Universal',1,160.00,240.00,14,5,'can',1);

INSERT INTO inventory_movements (item_id, movement_type, quantity_change, qty_before, qty_after, reason, created_by)
SELECT id,'adjustment',stock_qty,0,stock_qty,'Demo opening stock',1 FROM inventory_items WHERE is_demo=1 AND stock_qty>0;

INSERT INTO customers (full_name, contact_number, address, notes, is_demo) VALUES
('DEMO Juan Dela Cruz','09000000011','Poblacion, Inabanga','Demonstration customer',1),
('DEMO Maria Santos','09000000012','Inabanga, Bohol','Demonstration customer',1);

INSERT INTO motorcycles (customer_id, brand, model, plate_number, year_model) VALUES
(1,'Honda','Click 125i','ABC 1234','2021'),
(1,'Honda','XRM 125','XYZ 5678','2018'),
(2,'Yamaha','Mio Sporty','DEF 9012','2019');

INSERT INTO mechanics (full_name, contact_number, specialty, is_demo) VALUES
('DEMO Pedro Reyes','09000000021','Engine overhaul, tune-up',1),
('DEMO Jose Ramos','09000000022','Electrical, brakes',1),
('DEMO Ana Lopez','09000000023','General maintenance',1);
-- Sample repair jobs and sales are added in a later stage, once the PHP modules that create them exist.

-- ---------------------------------------------------------------------
-- DEMO TRANSACTIONS (stock levels and movements kept consistent)
-- ---------------------------------------------------------------------
-- Demo sale: 2 spark plugs + 1 engine oil = 560.00, cash 600.00, change 40.00
INSERT INTO sales (receipt_no, customer_name, subtotal, discount, total, cashier_id) VALUES ('OR-DEMO-0001', NULL, 560.00, 0.00, 560.00, 2);
INSERT INTO sale_items (sale_id, item_id, quantity, unit_price, unit_cost, line_subtotal) VALUES (1,1,2,120.00,70.00,240.00),(1,6,1,320.00,230.00,320.00);
INSERT INTO payments (payment_no, payable_type, sale_id, amount_due, amount_tendered, amount_applied, change_given, received_by) VALUES ('PAY-DEMO-0001','sale',1,560.00,600.00,560.00,40.00,2);
INSERT INTO inventory_movements (item_id, movement_type, quantity_change, qty_before, qty_after, reference_type, reference_id, reason, created_by) VALUES
(1,'sale',-2,40,38,'sale',1,'Sale OR-DEMO-0001',2),(6,'sale',-1,30,29,'sale',1,'Sale OR-DEMO-0001',2);

-- Demo repair job 1: brake pad + spark plug (400.00) + labor (350.00) = 750.00, partially paid 500.00
INSERT INTO repair_jobs (job_no, customer_id, motorcycle_id, date_received, reported_problem, diagnosis, requested_services, status, parts_subtotal, labor_subtotal, discount, total_due, amount_paid, payment_status, created_by)
VALUES ('JOB-DEMO-0001',1,1,CURDATE(),'Weak brakes and rough idle','Worn front brake pad, fouled spark plug','Brake pad and spark plug replacement','Completed',400.00,350.00,0.00,750.00,500.00,'Partially Paid',2);
INSERT INTO repair_mechanics (repair_job_id, mechanic_id) VALUES (1,1),(1,2);
INSERT INTO labor_entries (repair_job_id, mechanic_id, service_type, description, service_date, amount, created_by) VALUES (1,2,'Brake service and tune-up','Replace pad, replace plug, adjust',CURDATE(),350.00,2);
INSERT INTO repair_parts_used (repair_job_id, item_id, quantity, unit_price, line_subtotal, recorded_by) VALUES (1,2,1,280.00,280.00,2),(1,1,1,120.00,120.00,2);
INSERT INTO inventory_movements (item_id, movement_type, quantity_change, qty_before, qty_after, reference_type, reference_id, reason, created_by) VALUES
(2,'repair_use',-1,15,14,'repair_job',1,'Used in JOB-DEMO-0001',2),(1,'repair_use',-1,38,37,'repair_job',1,'Used in JOB-DEMO-0001',2);
INSERT INTO payments (payment_no, payable_type, repair_job_id, amount_due, amount_tendered, amount_applied, change_given, received_by) VALUES ('PAY-DEMO-0002','repair',1,750.00,500.00,500.00,0.00,2);

-- Demo repair job 2: newly received, nothing recorded yet
INSERT INTO repair_jobs (job_no, customer_id, motorcycle_id, date_received, reported_problem, status, created_by)
VALUES ('JOB-DEMO-0002',2,3,CURDATE(),'Engine hard to start in the morning','Pending',2);
INSERT INTO repair_mechanics (repair_job_id, mechanic_id) VALUES (2,3);

-- Bring stock quantities in line with the demo movements above
UPDATE inventory_items SET stock_qty = 37 WHERE id = 1;
UPDATE inventory_items SET stock_qty = 29 WHERE id = 6;
UPDATE inventory_items SET stock_qty = 14 WHERE id = 2;
