<?php
require_once __DIR__ . '/portal-bootstrap.php';
$user = portal_require_role(['Admin']);
$pdo = portal_db();
$message = '';
$error = '';

function billing_money($value): string { return '৳' . number_format((float)$value, 2); }
function billing_date_valid(string $value): bool {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
    [$y,$m,$d] = array_map('intval', explode('-', $value));
    return checkdate($m,$d,$y);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_invoice'])) {
    portal_verify_csrf();
    try {
        $patientId = filter_var($_POST['patient_id'] ?? null, FILTER_VALIDATE_INT);
        $appointmentRaw = trim((string)($_POST['appointment_id'] ?? ''));
        $appointmentId = $appointmentRaw === '' ? null : filter_var($appointmentRaw, FILTER_VALIDATE_INT);
        $invoiceDate = trim((string)($_POST['invoice_date'] ?? date('Y-m-d')));
        $dueDate = trim((string)($_POST['due_date'] ?? ''));
        $discount = filter_var($_POST['discount_amount'] ?? '0', FILTER_VALIDATE_FLOAT);
        $taxPercent = filter_var($_POST['tax_percent'] ?? '0', FILTER_VALIDATE_FLOAT);
        $notes = trim((string)($_POST['notes'] ?? ''));
        if (!$patientId || !billing_date_valid($invoiceDate)) throw new RuntimeException('Select a patient and enter a valid invoice date.');
        if ($dueDate !== '' && !billing_date_valid($dueDate)) throw new RuntimeException('Enter a valid due date.');
        if ($appointmentRaw !== '' && !$appointmentId) throw new RuntimeException('Invalid appointment selection.');
        if ($discount === false || $discount < 0 || $taxPercent === false || $taxPercent < 0 || $taxPercent > 100) throw new RuntimeException('Discount must be zero or more and tax must be between 0 and 100%.');
        $checkPatient = $pdo->prepare('SELECT patient_id FROM patient WHERE patient_id=?');
        $checkPatient->execute([$patientId]);
        if (!$checkPatient->fetch()) throw new RuntimeException('Selected patient was not found.');
        if ($appointmentId) {
            $checkAppt = $pdo->prepare('SELECT appointment_id FROM appointment WHERE appointment_id=? AND patient_id=?');
            $checkAppt->execute([$appointmentId,$patientId]);
            if (!$checkAppt->fetch()) throw new RuntimeException('The selected appointment does not belong to this patient.');
        }
        $descriptions = (array)($_POST['item_description'] ?? []);
        $quantities = (array)($_POST['item_quantity'] ?? []);
        $prices = (array)($_POST['item_price'] ?? []);
        $items = [];
        $subtotal = 0.0;
        for ($i=0; $i<min(10,count($descriptions)); $i++) {
            $description = trim((string)($descriptions[$i] ?? ''));
            if ($description === '') continue;
            $qty = filter_var($quantities[$i] ?? '1', FILTER_VALIDATE_FLOAT);
            $price = filter_var($prices[$i] ?? '0', FILTER_VALIDATE_FLOAT);
            if ($qty === false || $qty <= 0 || $price === false || $price < 0) throw new RuntimeException('Each bill item needs a positive quantity and a non-negative unit price.');
            $line = round($qty * $price, 2);
            $subtotal += $line;
            $items[] = [$description, $qty, $price, $line];
        }
        if (!$items) throw new RuntimeException('Add at least one bill item.');
        $subtotal = round($subtotal, 2);
        if ($discount > $subtotal) throw new RuntimeException('Discount cannot be greater than the subtotal.');
        $taxable = max(0, $subtotal - (float)$discount);
        $taxAmount = round($taxable * ((float)$taxPercent / 100), 2);
        $total = round($taxable + $taxAmount, 2);
        $invoiceNumber = 'CCH-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        $pdo->beginTransaction();
        $ins = $pdo->prepare("INSERT INTO billing_invoices(invoice_number,patient_id,appointment_id,invoice_date,due_date,subtotal,discount_amount,tax_percent,tax_amount,total_amount,amount_paid,payment_status,notes,created_by_account_id) VALUES(?,?,?,?,?,?,?,?,?,?,0,'Unpaid',?,?)");
        $ins->execute([$invoiceNumber,$patientId,$appointmentId,$invoiceDate,$dueDate ?: null,$subtotal,$discount,$taxPercent,$taxAmount,$total,$notes ?: null,(int)$user['account_id']]);
        $invoiceId = (int)$pdo->lastInsertId();
        $itemIns = $pdo->prepare('INSERT INTO billing_invoice_items(invoice_id,description,quantity,unit_price,line_total) VALUES(?,?,?,?,?)');
        foreach ($items as $item) $itemIns->execute(array_merge([$invoiceId],$item));
        $pdo->commit();
        portal_audit((int)$user['account_id'],'billing_invoice_created',$invoiceId);
        $message = 'Invoice ' . $invoiceNumber . ' created successfully.';
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $ex instanceof PDOException ? 'Could not create the invoice. Check that billing-migration.sql has been run successfully.' : $ex->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    portal_verify_csrf();
    try {
        $invoiceId = filter_var($_POST['invoice_id'] ?? null, FILTER_VALIDATE_INT);
        $amount = filter_var($_POST['payment_amount'] ?? null, FILTER_VALIDATE_FLOAT);
        $method = (string)($_POST['payment_method'] ?? '');
        $allowedMethods = ['Cash','bKash','Nagad','Card','Bank Transfer','Other'];
        $reference = trim((string)($_POST['transaction_reference'] ?? ''));
        $notes = trim((string)($_POST['payment_notes'] ?? ''));
        if (!$invoiceId || $amount === false || $amount <= 0 || !in_array($method,$allowedMethods,true)) throw new RuntimeException('Enter a valid invoice, payment amount and payment method.');
        $pdo->beginTransaction();
        $q = $pdo->prepare("SELECT invoice_id,total_amount,amount_paid,payment_status FROM billing_invoices WHERE invoice_id=? FOR UPDATE");
        $q->execute([$invoiceId]);
        $invoice = $q->fetch();
        if (!$invoice || $invoice['payment_status'] === 'Cancelled') throw new RuntimeException('Invoice not found or cancelled.');
        $balance = round((float)$invoice['total_amount'] - (float)$invoice['amount_paid'],2);
        if ($amount > $balance + 0.001) throw new RuntimeException('Payment exceeds the outstanding balance of ' . billing_money($balance) . '.');
        $newPaid = round((float)$invoice['amount_paid'] + (float)$amount,2);
        $status = $newPaid >= (float)$invoice['total_amount'] - 0.001 ? 'Paid' : 'Partially Paid';
        $p = $pdo->prepare('INSERT INTO billing_payments(invoice_id,amount,payment_method,transaction_reference,received_by_account_id,notes) VALUES(?,?,?,?,?,?)');
        $p->execute([$invoiceId,$amount,$method,$reference ?: null,(int)$user['account_id'],$notes ?: null]);
        $u = $pdo->prepare('UPDATE billing_invoices SET amount_paid=?,payment_status=? WHERE invoice_id=?');
        $u->execute([$newPaid,$status,$invoiceId]);
        $pdo->commit();
        portal_audit((int)$user['account_id'],'billing_payment_recorded',$invoiceId);
        $message = 'Payment recorded. Invoice status: ' . $status . '.';
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = $ex instanceof PDOException ? 'Could not record payment. Check the billing tables and database connection.' : $ex->getMessage();
    }
}

$patients = $pdo->query('SELECT patient_id,patient_name,phone FROM patient ORDER BY patient_name')->fetchAll();
$appointments = $pdo->query("SELECT a.appointment_id,a.patient_id,a.appointment_date,a.appointment_time,p.patient_name,d.doctor_name FROM appointment a JOIN patient p ON p.patient_id=a.patient_id JOIN doctor d ON d.doctor_id=a.doctor_id ORDER BY a.appointment_date DESC,a.appointment_time DESC LIMIT 250")->fetchAll();
$invoices = $pdo->query("SELECT i.invoice_id,i.invoice_number,i.invoice_date,i.due_date,i.total_amount,i.amount_paid,i.payment_status,p.patient_name,p.patient_id FROM billing_invoices i JOIN patient p ON p.patient_id=i.patient_id ORDER BY i.created_at DESC LIMIT 200")->fetchAll();
$stats = $pdo->query("SELECT COUNT(*) AS invoice_count,COALESCE(SUM(total_amount),0) AS billed,COALESCE(SUM(amount_paid),0) AS collected,COALESCE(SUM(total_amount-amount_paid),0) AS outstanding FROM billing_invoices WHERE payment_status<>'Cancelled'")->fetch();
$daily = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM billing_payments WHERE DATE(paid_at)=CURDATE()")->fetchColumn();
portal_page_start('Billing Management');
?>
<h1>Billing &amp; Payment Management</h1>
<p class="rp-muted">Create patient invoices, record payments and monitor outstanding balances.</p>
<div class="rp-nav"><a class="rp-btn secondary" href="staff-dashboard.php">Staff Dashboard</a><a class="rp-btn secondary" href="portal-logout.php">Sign out</a></div>
<?php if($message): ?><div class="rp-alert rp-success"><?= portal_e($message) ?></div><?php endif; ?>
<?php if($error): ?><div class="rp-alert"><?= portal_e($error) ?></div><?php endif; ?>
<div class="rp-grid">
 <div class="rp-stat"><strong><?= (int)$stats['invoice_count'] ?></strong>Total invoices</div>
 <div class="rp-stat"><strong><?= billing_money($stats['billed']) ?></strong>Total billed</div>
 <div class="rp-stat"><strong><?= billing_money($stats['collected']) ?></strong>Total collected</div>
 <div class="rp-stat"><strong><?= billing_money($stats['outstanding']) ?></strong>Outstanding balance</div>
 <div class="rp-stat"><strong><?= billing_money($daily) ?></strong>Collected today</div>
</div>
<h2 id="cp-invoice">Create New Invoice</h2>
<form method="post" class="rp-form">
<input type="hidden" name="csrf_token" value="<?= portal_e(portal_csrf_token()) ?>">
<label>Patient<select name="patient_id" required><option value="">Select patient</option><?php foreach($patients as $p): ?><option value="<?= (int)$p['patient_id'] ?>">#<?= (int)$p['patient_id'] ?> · <?= portal_e($p['patient_name']) ?> · <?= portal_e($p['phone']) ?></option><?php endforeach; ?></select></label>
<label>Related appointment (optional)<select name="appointment_id"><option value="">No linked appointment</option><?php foreach($appointments as $a): ?><option value="<?= (int)$a['appointment_id'] ?>">#<?= (int)$a['appointment_id'] ?> · <?= portal_e($a['patient_name']) ?> · <?= portal_e($a['appointment_date']) ?> · <?= portal_e($a['doctor_name']) ?></option><?php endforeach; ?></select></label>
<label>Invoice date<input type="date" name="invoice_date" value="<?= date('Y-m-d') ?>" required></label>
<label>Due date (optional)<input type="date" name="due_date"></label>
<div style="grid-column:1/-1"><h3>Bill items</h3><div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>Description</th><th>Quantity</th><th>Unit price (৳)</th></tr></thead><tbody>
<?php for($i=0;$i<6;$i++): ?><tr><td><input name="item_description[]" maxlength="200" placeholder="e.g. Consultation fee" <?= $i===0?'required':'' ?>></td><td><input type="number" name="item_quantity[]" min="0.01" step="0.01" value="<?= $i===0?'1':'' ?>"></td><td><input type="number" name="item_price[]" min="0" step="0.01" placeholder="0.00"></td></tr><?php endfor; ?>
</tbody></table></div></div>
<label>Discount (৳)<input type="number" name="discount_amount" min="0" step="0.01" value="0"></label>
<label>Tax (%)<input type="number" name="tax_percent" min="0" max="100" step="0.01" value="0"></label>
<label style="grid-column:1/-1">Notes<textarea name="notes" placeholder="Optional billing notes"></textarea></label>
<div><button class="rp-btn" type="submit" name="create_invoice">Create Invoice</button></div>
</form>
<h2 id="cp-payment">Record a Payment</h2>
<form method="post" class="rp-form">
<input type="hidden" name="csrf_token" value="<?= portal_e(portal_csrf_token()) ?>">
<label>Invoice<select name="invoice_id" required><option value="">Select outstanding invoice</option><?php foreach($invoices as $i): if(in_array($i['payment_status'],['Paid','Cancelled'],true)) continue; ?><option value="<?= (int)$i['invoice_id'] ?>"><?= portal_e($i['invoice_number']) ?> · <?= portal_e($i['patient_name']) ?> · Balance <?= billing_money((float)$i['total_amount']-(float)$i['amount_paid']) ?></option><?php endforeach; ?></select></label>
<label>Payment amount (৳)<input type="number" name="payment_amount" min="0.01" step="0.01" required></label>
<label>Payment method<select name="payment_method" required><option>Cash</option><option>bKash</option><option>Nagad</option><option>Card</option><option>Bank Transfer</option><option>Other</option></select></label>
<label>Transaction reference (optional)<input name="transaction_reference" maxlength="120" placeholder="Transaction ID / receipt reference"></label>
<label style="grid-column:1/-1">Payment notes<textarea name="payment_notes"></textarea></label>
<div><button class="rp-btn" type="submit" name="record_payment">Record Payment</button></div>
</form>
<h2 id="cp-register">Invoice Register</h2>
<div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>Invoice</th><th>Patient</th><th>Date</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Invoice</th></tr></thead><tbody>
<?php foreach($invoices as $i): ?><tr><td><?= portal_e($i['invoice_number']) ?></td><td><?= portal_e($i['patient_name']) ?></td><td><?= portal_e($i['invoice_date']) ?></td><td><?= billing_money($i['total_amount']) ?></td><td><?= billing_money($i['amount_paid']) ?></td><td><?= billing_money((float)$i['total_amount']-(float)$i['amount_paid']) ?></td><td><?= portal_e($i['payment_status']) ?></td><td><a href="invoice-print.php?id=<?= (int)$i['invoice_id'] ?>">View / Print</a></td></tr><?php endforeach; ?>
<?php if(!$invoices): ?><tr><td colspan="8">No invoices created yet.</td></tr><?php endif; ?>
</tbody></table></div>
<div class="rp-alert">Payment options record payments in this local system; they do not automatically collect money from bKash, Nagad, cards or banks. Live gateway integration requires merchant accounts, provider API credentials and security testing.</div>
<?php portal_page_end(); ?>
