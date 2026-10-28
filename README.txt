M-GARAGE INVENTORY AND SERVICE MANAGEMENT SYSTEM  (HTML, CSS, PHP, JavaScript, MySQL)

SETUP (XAMPP on Windows)
1. Copy the m-garage folder to C:\xampp\htdocs\m-garage
2. Start Apache and MySQL in the XAMPP Control Panel.
3. Open http://localhost/phpmyadmin > Import > choose database\m_garage.sql > Go.
   (This creates the m_garage database, all 19 tables and the demonstration data.)
4. Open http://localhost/m-garage/
5. If MySQL has a root password or the folder name differs, edit config\config.php.

DEMO LOGINS
  Usernames: admin and cashier. Both accounts require a password change on first login.
  Temporary passwords for this database are in the setup handoff; change both after first login.
  Do not distribute this database dump or its credentials publicly.
Demo rows are flagged is_demo=1 (items, suppliers, customers, mechanics, accounts). Sample sale OR-DEMO-0001
and repair jobs JOB-DEMO-0001 / JOB-DEMO-0002 are demonstration data. Delete before real use.

TABLES (database\m_garage.sql explains each one in a comment)
users, categories, suppliers, inventory_items, stock_receivings, stock_receiving_items, inventory_movements,
customers, motorcycles, mechanics, repair_jobs, repair_mechanics, labor_entries, repair_parts_used,
sales, sale_items, payments, activity_logs, settings.

BUSINESS RULES IMPLEMENTED
- Stock never goes negative (PHP check + MySQL CHECK constraint); every change writes inventory_movements.
- Parts used in repairs and POS sales both deduct stock, each through its own records (repair_parts_used / sale_items).
- Totals (parts, labor, discount, amount due, change, payment status) are computed on the server; JavaScript only previews.
- Price is frozen at the time a part is used or sold.
- One-time form tokens + unique request_token columns stop duplicate submissions from double-deducting stock.
- All multi-table writes use database transactions and roll back on failure.
- Completed records are never deleted: use deactivate, cancel, refund, void or reverse (all logged in activity_logs).
- Released jobs can only change parts through a reversal with a reason; cancelling a job returns its parts to stock.

QUICK REPAIR ENTRY
- Cashiers can enter customer and motorcycle details, the mechanic responsible, parts used, labor, discount and any payment on one screen.
- Use Repairs > Quick repair entry. Existing customers can be selected, or a new customer and motorcycle can be registered inline.
- Choose the mechanic responsible once. Parts and labor lines default to that mechanic; choose another only when needed. Mechanics are assigned to the job, parts are listed under the mechanic who used them, and stock and totals update when saved.
- For an existing database, import database/migrations/20261028_add_mechanic_to_repair_parts.sql once before using the mechanic-on-parts field. This workspace database already has the update.

TEST CHECKLIST
 1. Log in as admin, change password, create a cashier account (Admin > User Management).
 2. Log in as cashier: Inventory > Add item (with initial stock).
 3. Stock Receiving: receive stock from a supplier; the quantity increases and a movement is logged.
 4. Point of Sale: sell items, enter cash, confirm change and stock decrease; print the receipt.
 5. Customers > Register customer, then add a motorcycle.
 6. Repair and Service Jobs > New repair job.
 7. Open the job: Mechanics tab (assign), Labor tab (add charges).
 8. Parts used tab: add several parts; stock drops by the exact quantities; try a quantity above stock (rejected).
 9. Job summary: parts + labor - discount = amount due.
10. Payment tab: pay partially (status Partially Paid), then the rest (Paid, change shown). Print the invoice.
11. Set status Released (only allowed when fully paid).
12. Lower an item's stock to its minimum: the low-stock alert shows on the dashboard.
13. Log in as cashier and open /m-garage/admin/users.php: access denied (403).
14. Refresh a POST result page / double-click Save: stock is deducted only once.
15. Reports: filter dates and print. Admin > Activity Logs shows the actions above.
