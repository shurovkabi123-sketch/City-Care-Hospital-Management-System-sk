<?php
require_once __DIR__ . '/portal-bootstrap.php';
$user=portal_require_role(['Doctor']); $pdo=portal_db(); $doctorId=(int)$user['doctor_id']; $message=''; $error='';
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_prescription'])){
 portal_verify_csrf();
 try{
  $appointmentId=filter_var($_POST['appointment_id']??null,FILTER_VALIDATE_INT);
  $diagnosis=trim((string)($_POST['diagnosis']??'')); $notes=trim((string)($_POST['clinical_notes']??''));
  if(!$appointmentId||$diagnosis==='') throw new RuntimeException('Select an appointment and enter a diagnosis.');
  $q=$pdo->prepare("SELECT appointment_id,patient_id FROM appointment WHERE appointment_id=? AND doctor_id=? AND status='Booked' LIMIT 1");
  $q->execute([$appointmentId,$doctorId]); $appt=$q->fetch(); if(!$appt) throw new RuntimeException('This appointment is not an active booked appointment for your doctor account. It may already be completed or cancelled.');
  $names=$_POST['medicine_name']??[]; $doses=$_POST['dose']??[]; $freqs=$_POST['frequency']??[]; $durations=$_POST['duration']??[]; $instructions=$_POST['instructions']??[];
  $items=[];
  for($i=0;$i<min(5,count((array)$names));$i++){ $n=trim((string)($names[$i]??'')); if($n!=='') $items[]=[ $n,trim((string)($doses[$i]??'')),trim((string)($freqs[$i]??'')),trim((string)($durations[$i]??'')),trim((string)($instructions[$i]??'')) ]; }
  if(!$items) throw new RuntimeException('Enter at least one medicine.');
  $pdo->beginTransaction();
  $ins=$pdo->prepare('INSERT INTO prescriptions(patient_id,doctor_id,appointment_id,created_by_account_id,diagnosis,clinical_notes) VALUES(?,?,?,?,?,?)');
  $ins->execute([(int)$appt['patient_id'],$doctorId,(int)$appointmentId,(int)$user['account_id'],$diagnosis,$notes?:null]);
  $prescriptionId=(int)$pdo->lastInsertId();
  $itemIns=$pdo->prepare('INSERT INTO prescription_items(prescription_id,medicine_name,dose,frequency,duration,instructions) VALUES(?,?,?,?,?,?)');
  foreach($items as $it)$itemIns->execute(array_merge([$prescriptionId],$it));
  // Complete the appointment only after the prescription and all medicine rows are saved.
  $complete=$pdo->prepare("UPDATE appointment SET status='Completed' WHERE appointment_id=? AND doctor_id=? AND status='Booked'");
  $complete->execute([(int)$appointmentId,$doctorId]);
  if($complete->rowCount() !== 1) throw new RuntimeException('Prescription was not finalized because the appointment status changed. Please refresh and check the appointment.');
  $pdo->commit(); portal_audit((int)$user['account_id'],'prescription_created',$prescriptionId); $message='Prescription saved. The appointment is now marked Completed and moved to appointment history.';
 }catch(Throwable $ex){if($pdo->inTransaction())$pdo->rollBack();$error=$ex instanceof PDOException?'Could not save prescription. Check the portal database migration.':$ex->getMessage();}
}
// Only active booked appointments remain in the working appointment list.
$q=$pdo->prepare("SELECT a.appointment_id,a.patient_id,a.appointment_date,a.appointment_time,a.status,a.notes,p.patient_name,p.phone
 FROM appointment a JOIN patient p ON p.patient_id=a.patient_id
 WHERE a.doctor_id=? AND a.status='Booked'
 ORDER BY a.appointment_date ASC,a.appointment_time ASC");
$q->execute([$doctorId]);$appointments=$q->fetchAll();

// Completed appointments remain available as history; they are not deleted.
$historyQ=$pdo->prepare("SELECT a.appointment_id,a.patient_id,a.appointment_date,a.appointment_time,a.status,p.patient_name,
 CASE WHEN EXISTS (SELECT 1 FROM prescriptions pr WHERE pr.appointment_id=a.appointment_id) THEN 1 ELSE 0 END AS has_prescription
 FROM appointment a JOIN patient p ON p.patient_id=a.patient_id
 WHERE a.doctor_id=? AND a.status='Completed'
 ORDER BY a.appointment_date DESC,a.appointment_time DESC LIMIT 100");
$historyQ->execute([$doctorId]);$completedAppointments=$historyQ->fetchAll();
portal_page_start('Doctor Dashboard');
?>
<h1>Welcome, <?= portal_e($user['full_name']) ?></h1><p class="rp-muted">Your appointment schedule and prescription workspace.</p>
<div class="rp-nav"><a class="rp-btn secondary" href="doctor-portal.php">Schedule lookup</a><a class="rp-btn secondary" href="portal-logout.php">Sign out</a></div>
<?php if($message): ?><div class="rp-alert rp-success"><?= portal_e($message) ?></div><?php endif; ?>
<?php if($error): ?><div class="rp-alert"><?= portal_e($error) ?></div><?php endif; ?>
<div class="rp-grid cp-doctor-metrics">
<div class="rp-stat"><span class="cp-stat-icon"><?= citycare_visual_icon('calendar') ?></span><strong><?= count($appointments) ?></strong>Booked appointments</div>
<div class="rp-stat"><span class="cp-stat-icon"><?= citycare_visual_icon('document') ?></span><strong><?= count($completedAppointments) ?></strong>Recent completed visits</div>
<div class="rp-stat"><span class="cp-stat-icon"><?= citycare_visual_icon('heart') ?></span><strong class="cp-role-value">Doctor</strong>Your clinical workspace</div>
</div>
<h2 id="cp-appointments">My Appointment Schedule</h2>
<div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>ID</th><th>Date</th><th>Time</th><th>Patient</th><th>Phone</th><th>Status</th></tr></thead><tbody>
<?php foreach($appointments as $a): ?><tr><td><?= (int)$a['appointment_id'] ?></td><td><?= portal_e($a['appointment_date']) ?></td><td><?= portal_e($a['appointment_time']) ?></td><td><?= portal_e($a['patient_name']) ?></td><td><?= portal_e($a['phone']) ?></td><td><?= portal_e($a['status']) ?></td></tr><?php endforeach; ?>
<?php if(!$appointments): ?><tr><td colspan="6">No pending booked appointments. Completed appointments remain in history below.</td></tr><?php endif; ?>
</tbody></table></div>
<h2 id="cp-history">Completed Appointment History</h2>
<p class="rp-muted">Appointments move here automatically after the doctor saves a prescription. Records are retained and are not deleted.</p>
<div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>ID</th><th>Date</th><th>Time</th><th>Patient</th><th>Status</th><th>Prescription</th></tr></thead><tbody>
<?php foreach($completedAppointments as $h): ?><tr><td><?= (int)$h['appointment_id'] ?></td><td><?= portal_e($h['appointment_date']) ?></td><td><?= portal_e($h['appointment_time']) ?></td><td><?= portal_e($h['patient_name']) ?></td><td><?= portal_e($h['status']) ?></td><td><?= (int)$h['has_prescription'] ? 'Saved' : 'No prescription linked' ?></td></tr><?php endforeach; ?>
<?php if(!$completedAppointments): ?><tr><td colspan="6">No completed appointments yet.</td></tr><?php endif; ?>
</tbody></table></div>
<h2 id="cp-prescribe">Create Patient Prescription</h2>
<p class="rp-muted">Only appointments assigned to your doctor account can be prescribed.</p>
<form method="post" class="rp-form">
<input type="hidden" name="csrf_token" value="<?= portal_e(portal_csrf_token()) ?>">
<label>Appointment<select name="appointment_id" required><option value="">Choose appointment</option><?php foreach($appointments as $a): ?><option value="<?= (int)$a['appointment_id'] ?>">#<?= (int)$a['appointment_id'] ?> · <?= portal_e($a['patient_name']) ?> · <?= portal_e($a['appointment_date']) ?></option><?php endforeach; ?></select></label>
<label>Diagnosis<input name="diagnosis" maxlength="255" required></label>
<label style="grid-column:1/-1">Clinical notes<textarea name="clinical_notes"></textarea></label>
<?php for($i=0;$i<5;$i++): ?>
<div class="rp-panel" style="grid-column:1/-1"><strong>Medicine <?= $i+1 ?> <?= $i===0?'(required)':'(optional)' ?></strong><div class="rp-form">
<label>Medicine name<input name="medicine_name[]" <?= $i===0?'required':'' ?>></label><label>Dose<input name="dose[]" placeholder="e.g. 500 mg"></label><label>Frequency<input name="frequency[]" placeholder="e.g. twice daily"></label><label>Duration<input name="duration[]" placeholder="e.g. 5 days"></label><label style="grid-column:1/-1">Instructions<input name="instructions[]" placeholder="e.g. after meals"></label>
</div></div>
<?php endfor; ?>
<div><button class="rp-btn" type="submit" name="create_prescription">Save & Publish Prescription</button></div>
</form>
<div class="rp-alert">Clinical safety: this system does not validate prescribing appropriateness, drug interactions, allergies or dosing. Verify prescriptions independently before clinical use.</div>
<?php portal_page_end(); ?>
