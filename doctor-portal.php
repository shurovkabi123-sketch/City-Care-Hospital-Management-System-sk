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

$error=''; $appointments=[];
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $id=trim((string)($_POST['doctor_id']??''));
  $phone=trim((string)($_POST['phone']??''));
  if (!ctype_digit($id) || $phone==='') {
    $error='Enter your doctor ID and registered phone number.';
  } else {
    try {
      $pdo=db();
      $verify=$pdo->prepare('SELECT doctor_id,doctor_name,specialization FROM doctor WHERE doctor_id=? AND phone=? LIMIT 1');
      $verify->execute([(int)$id,$phone]);
      $doctorRow=$verify->fetch();
      if (!$doctorRow) {
        $error='Doctor ID and registered phone number did not match.';
      } else {
        $q=$pdo->prepare("SELECT a.appointment_id,a.appointment_date,a.appointment_time,a.status,a.notes,p.patient_name
                          FROM appointment a JOIN patient p ON p.patient_id=a.patient_id
                          WHERE a.doctor_id=? ORDER BY a.appointment_date,a.appointment_time");
        $q->execute([(int)$id]); $appointments=$q->fetchAll();
      }
    } catch (Throwable $ex) {
      $error='Could not load the doctor schedule. Check the XAMPP database connection.';
    }
  }
}
?>
<?php require_once __DIR__ . '/portal-layout.php'; citycare_portal_start('Doctor Schedule Lookup'); ?>
<h1>Doctor Portal</h1>
<div class="rp-nav"><a class="rp-btn secondary" href="doctor-login.php">Doctor Account Login</a><a class="rp-btn secondary" href="doctor-register.php">Activate Account</a></div><p>View the appointment schedule linked to a doctor record.</p>
<div class="cc-demo-warning"><strong>Account security:</strong> Schedule lookup uses the registered doctor ID and phone number. Hospital-controlled identity verification and individual secure accounts are required before production use.</div>
<form class="cc-portal-form" method="post">
<label>Doctor ID<input type="number" name="doctor_id" min="1" required value="<?= e($_POST['doctor_id']??'') ?>"></label>
<label>Registered doctor phone<input type="tel" name="phone" required value="<?= e($_POST['phone']??'') ?>"></label>
<button type="submit">View My Schedule</button>
</form>
<?php if ($error): ?><p class="cc-message cc-error"><?= e($error) ?></p><?php endif; ?>
<?php if ($appointments): ?>
<h2>Appointment Schedule</h2>
<div style="overflow-x:auto"><table class="cc-portal-table"><thead><tr><th>ID</th><th>Date</th><th>Time</th><th>Patient</th><th>Status</th><th>Notes</th></tr></thead><tbody>
<?php foreach ($appointments as $a): ?><tr>
<td><?= (int)$a['appointment_id'] ?></td><td><?= e($a['appointment_date']) ?></td><td><?= e($a['appointment_time']) ?></td><td><?= e($a['patient_name']) ?></td><td><?= e($a['status']) ?></td><td><?= e($a['notes']??'') ?></td>
</tr><?php endforeach; ?>
</tbody></table></div>
<?php elseif ($_SERVER['REQUEST_METHOD']==='POST' && !$error): ?><p>No appointments found for this doctor.</p><?php endif; ?>
<?php citycare_portal_end(); ?>
