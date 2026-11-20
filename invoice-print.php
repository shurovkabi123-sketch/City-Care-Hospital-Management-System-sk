<?php
require_once __DIR__ . '/portal-bootstrap.php';
$user = portal_require_role(['Admin','Patient']);
$pdo = portal_db();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$id) { http_response_code(400); exit('Invalid invoice ID.'); }
$sql = 'SELECT i.*,p.patient_name,p.phone,p.address FROM billing_invoices i JOIN patient p ON p.patient_id=i.patient_id WHERE i.invoice_id=?';
$args = [$id];
if ($user['role'] === 'Patient') { $sql .= ' AND i.patient_id=?'; $args[] = (int)$user['patient_id']; }
$q = $pdo->prepare($sql); $q->execute($args); $invoice = $q->fetch();
if (!$invoice) { http_response_code(404); exit('Invoice not found.'); }
$itemsQ = $pdo->prepare('SELECT description,quantity,unit_price,line_total FROM billing_invoice_items WHERE invoice_id=? ORDER BY item_id');
$itemsQ->execute([$id]); $items = $itemsQ->fetchAll();
$paymentsQ = $pdo->prepare('SELECT amount,payment_method,transaction_reference,paid_at,notes FROM billing_payments WHERE invoice_id=? ORDER BY paid_at');
$paymentsQ->execute([$id]); $payments = $paymentsQ->fetchAll();
function inv_e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function inv_money($v): string { return '৳' . number_format((float)$v,2); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= inv_e($invoice['invoice_number']) ?> | City Care Hospital</title><link rel="stylesheet" href="style.css"><style>
body{background:#f2f7fc;color:#183653;font-family:Arial,sans-serif}.invoice{max-width:900px;margin:35px auto;background:white;padding:42px;border:1px solid #d8e5f1;border-radius:18px;box-shadow:0 12px 35px #173f6812}.top{display:flex;justify-content:space-between;gap:20px;align-items:flex-start}.brand{font-size:27px;font-weight:800;color:#075f91}.muted{color:#60758b}.meta{background:#f1f6fc;padding:18px;border-radius:12px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin:25px 0}.tbl{width:100%;border-collapse:collapse;margin:18px 0}.tbl th,.tbl td{padding:12px;border-bottom:1px solid #dce6ef;text-align:left}.tbl th{background:#eff6fd}.right{text-align:right!important}.totals{margin-left:auto;max-width:360px}.totals td{padding:8px}.actions{max-width:900px;margin:15px auto;display:flex;gap:10px}.actions button,.actions a{border:0;border-radius:9px;padding:12px 18px;background:#0872aa;color:white;text-decoration:none;cursor:pointer}@media print{body{background:white}.invoice{margin:0;box-shadow:none;border:0;max-width:none}.actions{display:none}}@media(max-width:650px){.invoice{padding:20px;margin:10px}.top,.grid{display:block}.meta{margin-top:15px}}
</style></head><body>
<div class="actions"><button onclick="window.print()">Print / Save as PDF</button><?php if($user['role']==='Admin'): ?><a href="billing.php">Back to Billing</a><?php else: ?><a href="patient-dashboard.php">Back to Patient Portal</a><?php endif; ?></div>
<main class="invoice"><div class="top"><div><div class="brand">CITY CARE HOSPITAL</div><div class="muted">Billing &amp; Accounts</div></div><div class="meta"><strong>INVOICE</strong><br><?= inv_e($invoice['invoice_number']) ?><br><span class="muted">Date: <?= inv_e($invoice['invoice_date']) ?></span><br><span class="muted">Status: <?= inv_e($invoice['payment_status']) ?></span></div></div>
<div class="grid"><div><h3>Bill To</h3><strong><?= inv_e($invoice['patient_name']) ?></strong><br><?= inv_e($invoice['phone']) ?><br><?= inv_e($invoice['address']) ?></div><div><h3>Invoice Details</h3>Due date: <?= inv_e($invoice['due_date'] ?: 'Not specified') ?><br>Appointment ID: <?= inv_e($invoice['appointment_id'] ?: 'Not linked') ?></div></div>
<table class="tbl"><thead><tr><th>Description</th><th>Qty</th><th>Unit price</th><th class="right">Amount</th></tr></thead><tbody><?php foreach($items as $it): ?><tr><td><?= inv_e($it['description']) ?></td><td><?= inv_e($it['quantity']) ?></td><td><?= inv_money($it['unit_price']) ?></td><td class="right"><?= inv_money($it['line_total']) ?></td></tr><?php endforeach; ?></tbody></table>
<table class="totals"><tr><td>Subtotal</td><td class="right"><?= inv_money($invoice['subtotal']) ?></td></tr><tr><td>Discount</td><td class="right">− <?= inv_money($invoice['discount_amount']) ?></td></tr><tr><td>Tax (<?= inv_e($invoice['tax_percent']) ?>%)</td><td class="right"><?= inv_money($invoice['tax_amount']) ?></td></tr><tr><th>Total</th><th class="right"><?= inv_money($invoice['total_amount']) ?></th></tr><tr><td>Amount paid</td><td class="right"><?= inv_money($invoice['amount_paid']) ?></td></tr><tr><th>Balance due</th><th class="right"><?= inv_money((float)$invoice['total_amount']-(float)$invoice['amount_paid']) ?></th></tr></table>
<?php if($payments): ?><h3>Payment History</h3><table class="tbl"><thead><tr><th>Date</th><th>Method</th><th>Reference</th><th class="right">Amount</th></tr></thead><tbody><?php foreach($payments as $pay): ?><tr><td><?= inv_e($pay['paid_at']) ?></td><td><?= inv_e($pay['payment_method']) ?></td><td><?= inv_e($pay['transaction_reference'] ?: '—') ?></td><td class="right"><?= inv_money($pay['amount']) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
<?php if($invoice['notes']): ?><p><strong>Notes:</strong> <?= nl2br(inv_e($invoice['notes'])) ?></p><?php endif; ?><p class="muted" style="text-align:center;margin-top:45px">Thank you for choosing City Care Hospital.</p></main></body></html>
