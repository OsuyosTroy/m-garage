-- Run once on existing databases to calculate repair labor from the parts used.
ALTER TABLE inventory_items
  ADD COLUMN labor_rate DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER selling_price;

ALTER TABLE repair_parts_used
  ADD COLUMN labor_unit_rate DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER unit_price;

ALTER TABLE labor_entries
  ADD COLUMN source_part_id INT UNSIGNED NULL AFTER request_token,
  ADD UNIQUE KEY uq_labor_source_part (source_part_id);

-- Starter rates are estimates and can be edited on each inventory item.
UPDATE inventory_items SET labor_rate = CASE sku
  WHEN 'ENG-SP-001' THEN 50.00
  WHEN 'BRK-PD-001' THEN 150.00
  WHEN 'BRK-SH-001' THEN 120.00
  WHEN 'ELE-BL-001' THEN 50.00
  WHEN 'ELE-BT-001' THEN 50.00
  WHEN 'OIL-EO-001' THEN 100.00
  WHEN 'OIL-GO-001' THEN 50.00
  WHEN 'CHN-CH-001' THEN 200.00
  WHEN 'CHN-SP-001' THEN 250.00
  WHEN 'TIR-TR-001' THEN 250.00
  WHEN 'ACC-MR-001' THEN 80.00
  WHEN 'MNT-CL-001' THEN 0.00
  ELSE labor_rate
END
WHERE sku IN ('ENG-SP-001','BRK-PD-001','BRK-SH-001','ELE-BL-001','ELE-BT-001','OIL-EO-001','OIL-GO-001','CHN-CH-001','CHN-SP-001','TIR-TR-001','ACC-MR-001','MNT-CL-001');

DELETE FROM settings WHERE setting_key = 'default_labor_fee';
