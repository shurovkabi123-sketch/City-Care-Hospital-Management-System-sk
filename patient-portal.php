<?php
declare(strict_types=1);
session_start();
const DB_HOST='127.0.0.1';
const DB_PORT='3306';
const DB_NAME='citycare_hospital';
const DB_USER='root';
const DB_PASS='';
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function db(): PDO {
  static $pdo=null;
  if ($pdo instanceof PDO) return $pdo;
  $pdo=new PDO('mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false
  ]);
  return $pdo;
}

$error=''; $record=null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $id=trim((string)($_POST['appointment_id']??''));
  $phone=trim((string)($_POST['phone']??''));
  if (!ctype_digit($id) || $phone==='') {
    $error='Enter your appointment ID and the phone number used when booking.';
  } else {
    try {
      $q=db()->prepare("SELECT a.appointment_id,a.appointment_date,a.appointment_time,a.status,a.notes,
                               p.patient_name,p.phone,d.doctor_name,dep.department_name
                        FROM appointment a
                        JOIN patient p ON p.patient_id=a.patient_id
                        JOIN doctor d ON d.doctor_id=a.doctor_id
                        JOIN department dep ON dep.department_id=d.department_id
                        WHERE a.appointment_id=? AND p.phone=? LIMIT 1");
      $q->execute([(int)$id,$phone]);
      $record=$q->fetch();
      if (!$record) $error='No matching appointment was found. Check the appointment ID and phone number.';
    } catch (Throwable $ex) {
      $error='Could not load appointment details. Check the XAMPP database connection.';
    }
  }
}
?>
<?php require_once __DIR__ . '/portal-layout.php'; citycare_portal_start('Patient Appointment Lookup'); ?>
<h1>Patient Portal</h1>
<div class="rp-nav"><a class="rp-btn secondary" href="patient-login.php">Patient Account Login</a><a class="rp-btn secondary" href="patient-register.php">Create Account</a></div><p>Check an appointment using the appointment ID and the phone number entered during booking.</p>
<div class="cc-demo-warning"><strong>Privacy notice:</strong> This lookup uses the appointment ID and booking phone number and does not provide full identity verification. Secure patient authentication is required before production use.</div>
<form class="cc-portal-form" method="post">
<label>Appointment ID<input type="number" name="appointment_id" min="1" required value="<?= e($_POST['appointment_id']??'') ?>"></label>
<label>Booking phone number<input type="tel" name="phone" required value="<?= e($_POST['phone']??'') ?>"></label>
<button type="submit">Find Appointment</button>
</form>
<?php if ($error): ?><p class="cc-message cc-error"><?= e($error) ?></p><?php endif; ?>
<?php if ($record): ?>
<h2>Appointment Details</h2>
<table class="cc-portal-table"><tbody>
<tr><th>Appointment ID</th><td><?= (int)$record['appointment_id'] ?></td></tr>
<tr><th>Patient</th><td><?= e($record['patient_name']) ?></td></tr>
<tr><th>Doctor</th><td><?= e($record['doctor_name']) ?></td></tr>
<tr><th>Department</th><td><?= e($record['department_name']) ?></td></tr>
<tr><th>Date</th><td><?= e($record['appointment_date']) ?></td></tr>
<tr><th>Time</th><td><?= e($record['appointment_time']) ?></td></tr>
<tr><th>Status</th><td><?= e($record['status']) ?></td></tr>
</tbody></table>
<?php endif; ?>
<?php citycare_portal_end(); ?>
