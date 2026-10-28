<?php
const JOB_STATUSES = ['Pending', 'In Progress', 'Waiting for Parts', 'Completed', 'Released', 'Cancelled'];
function load_job(?int $id): array {
    $j = $id ? fetch_row('SELECT j.*, c.full_name AS cust, c.contact_number, m.brand, m.model, m.plate_number FROM repair_jobs j JOIN customers c ON c.id=j.customer_id JOIN motorcycles m ON m.id=j.motorcycle_id WHERE j.id=?', [$id]) : null;
    if (!$j) { flash('error', 'Repair job not found.'); redirect('repairs/index.php'); }
    return $j;
}
/** Released and Cancelled jobs cannot be edited directly. */
function job_locked(array $j): bool { return in_array($j['status'], ['Released', 'Cancelled'], true); }
function job_tabs(int $id, string $current): string {
    $t = ['view' => 'Summary', 'edit' => 'Edit', 'assign_mechanics' => 'Mechanics', 'labor' => 'Labor', 'parts_used' => 'Parts used'];
    $h = '<div class="toolbar tabs no-print">';
    foreach ($t as $f => $l) $h .= '<a class="btn btn-sm ' . ($f === $current ? 'btn-primary' : 'btn-outline') . '" href="' . $f . '.php?' . ($f === 'view' || $f === 'edit' ? 'id' : 'job_id') . '=' . $id . '">' . $l . '</a>';
    return $h . '<a class="btn btn-sm btn-outline" href="' . e(url('payments/record.php?job_id=' . $id)) . '">Payment</a><a class="btn btn-sm btn-outline" target="_blank" href="' . e(url('sales/receipt.php?job_id=' . $id)) . '"><i class="fa-solid fa-print"></i> Invoice</a></div>';
}
