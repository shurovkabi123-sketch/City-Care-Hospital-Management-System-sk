<?php
require_once __DIR__ . '/portal-bootstrap.php';
$user = portal_require_role(['Patient']);
$pdo = portal_db(); $patientId = (int)$user['patient_id'];
$q = $pdo->prepare("SELECT a.appointment_id,a.appointment_date,a.appointment_time,a.status,d.doctor_name,dep.department_name
 FROM appointment a JOIN doctor d ON d.doctor_id=a.doctor_id JOIN department dep ON dep.department_id=d.department_id
 WHERE a.patient_id=? ORDER BY a.appointment_date DESC,a.appointment_time DESC");
$q->execute([$patientId]); $appointments = $q->fetchAll();
$q = $pdo->prepare("SELECT p.prescription_id,p.diagnosis,p.clinical_notes,p.created_at,d.doctor_name
 FROM prescriptions p JOIN doctor d ON d.doctor_id=p.doctor_id WHERE p.patient_id=? ORDER BY p.created_at DESC");
$q->execute([$patientId]); $prescriptions = $q->fetchAll();
$q = $pdo->prepare("SELECT report_id,test_name,report_date,notes,original_filename,file_size FROM lab_reports WHERE patient_id=? ORDER BY report_date DESC,report_id DESC");
$q->execute([$patientId]); $reports = $q->fetchAll();
$q = $pdo->prepare("SELECT invoice_id,invoice_number,invoice_date,due_date,total_amount,amount_paid,payment_status FROM billing_invoices WHERE patient_id=? ORDER BY invoice_date DESC,invoice_id DESC");
$q->execute([$patientId]); $invoices = $q->fetchAll();
portal_page_start('Patient Dashboard');
?>
<h1>Welcome, <?= portal_e($user['full_name']) ?></h1><p class="rp-muted">Your private patient dashboard.</p>
<div class="rp-nav"><a class="rp-btn secondary" href="patient-portal.php">Appointment lookup</a><a class="rp-btn secondary" href="portal-logout.php">Sign out</a></div>
<div class="rp-grid">
<div class="rp-stat"><span class="cp-stat-icon"><?= citycare_visual_icon('calendar') ?></span><strong><?= count($appointments) ?></strong>Appointments</div>
<div class="rp-stat"><span class="cp-stat-icon"><?= citycare_visual_icon('document') ?></span><strong><?= count($prescriptions) ?></strong>Prescriptions</div>
<div class="rp-stat"><span class="cp-stat-icon"><?= citycare_visual_icon('lab') ?></span><strong><?= count($reports) ?></strong>Laboratory reports</div>
</div>
<h2 id="cp-appointments">My Appointments</h2>
<div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>ID</th><th>Date</th><th>Time</th><th>Doctor</th><th>Department</th><th>Status</th></tr></thead><tbody>
<?php foreach ($appointments as $a): ?><tr><td><?= (int)$a['appointment_id'] ?></td><td><?= portal_e($a['appointment_date']) ?></td><td><?= portal_e($a['appointment_time']) ?></td><td><?= portal_e($a['doctor_name']) ?></td><td><?= portal_e($a['department_name']) ?></td><td><?= portal_e($a['status']) ?></td></tr><?php endforeach; ?>
<?php if (!$appointments): ?><tr><td colspan="6">No appointments found.</td></tr><?php endif; ?>
</tbody></table></div>
<h2 id="cp-prescriptions">My Prescriptions</h2>
<?php foreach ($prescriptions as $p): ?>
<div class="rp-panel"><strong>Prescription #<?= (int)$p['prescription_id'] ?></strong> · <a href="prescription-print.php?id=<?= (int)$p['prescription_id'] ?>">View / Print / Save PDF</a> · <?= portal_e($p['created_at']) ?><p><b>Doctor:</b> <?= portal_e($p['doctor_name']) ?></p><p><b>Diagnosis:</b> <?= portal_e($p['diagnosis']) ?></p><?php if ($p['clinical_notes']): ?><p><?= nl2br(portal_e($p['clinical_notes'])) ?></p><?php endif; ?>
<?php $itemsQ=$pdo->prepare('SELECT medicine_name,dose,frequency,duration,instructions FROM prescription_items WHERE prescription_id=?'); $itemsQ->execute([(int)$p['prescription_id']]); $items=$itemsQ->fetchAll(); ?>
<div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>Medicine</th><th>Dose</th><th>Frequency</th><th>Duration</th><th>Instructions</th></tr></thead><tbody><?php foreach($items as $it): ?><tr><td><?= portal_e($it['medicine_name']) ?></td><td><?= portal_e($it['dose']) ?></td><td><?= portal_e($it['frequency']) ?></td><td><?= portal_e($it['duration']) ?></td><td><?= portal_e($it['instructions']) ?></td></tr><?php endforeach; ?></tbody></table></div>
</div>
<?php endforeach; ?>
<?php if (!$prescriptions): ?><p class="rp-muted">No prescriptions have been published to your account yet.</p><?php endif; ?>
<h2 id="cp-reports">My Laboratory Reports</h2>
<div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>Test</th><th>Report date</th><th>Notes</th><th>File</th></tr></thead><tbody>
<?php foreach ($reports as $r): ?><tr><td><?= portal_e($r['test_name']) ?></td><td><?= portal_e($r['report_date']) ?></td><td><?= portal_e($r['notes']) ?></td><td><a href="document-download.php?type=lab&id=<?= (int)$r['report_id'] ?>">View / Download PDF</a></td></tr><?php endforeach; ?>
<?php if (!$reports): ?><tr><td colspan="4">No laboratory reports have been uploaded yet.</td></tr><?php endif; ?>
</tbody></table></div>
<h2 id="cp-bills">My Bills &amp; Payments</h2>
<div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>Invoice</th><th>Date</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Invoice</th></tr></thead><tbody>
<?php foreach ($invoices as $inv): ?><tr><td><?= portal_e($inv['invoice_number']) ?></td><td><?= portal_e($inv['invoice_date']) ?></td><td>৳<?= number_format((float)$inv['total_amount'],2) ?></td><td>৳<?= number_format((float)$inv['amount_paid'],2) ?></td><td>৳<?= number_format((float)$inv['total_amount']-(float)$inv['amount_paid'],2) ?></td><td><?= portal_e($inv['payment_status']) ?></td><td><a href="invoice-print.php?id=<?= (int)$inv['invoice_id'] ?>">View / Print</a></td></tr><?php endforeach; ?>
<?php if (!$invoices): ?><tr><td colspan="7">No bills are available for your account.</td></tr><?php endif; ?>
</tbody></table></div>
<?php portal_page_end(); ?>
