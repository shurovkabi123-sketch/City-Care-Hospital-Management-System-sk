<?php
require_once __DIR__ . '/portal-bootstrap.php';
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 portal_verify_csrf();
 $doctorId=trim((string)($_POST['doctor_id']??'')); $phone=trim((string)($_POST['phone']??'')); $password=(string)($_POST['password']??''); $confirm=(string)($_POST['confirm_password']??'');
 try{
  if(!ctype_digit($doctorId)||$phone===''||strlen($password)<10||$password!==$confirm) throw new RuntimeException('Enter a valid doctor ID and registered phone. Password must be at least 10 characters and match.');
  $pdo=portal_db(); $q=$pdo->prepare('SELECT doctor_id,doctor_name,phone FROM doctor WHERE doctor_id=? AND phone=? LIMIT 1'); $q->execute([(int)$doctorId,$phone]); $doctor=$q->fetch();
  if(!$doctor) throw new RuntimeException('Doctor ID and registered phone did not match.');
  $ex=$pdo->prepare('SELECT account_id FROM portal_accounts WHERE doctor_id=? OR username=? LIMIT 1'); $ex->execute([(int)$doctorId,$phone]); if($ex->fetch()) throw new RuntimeException('A doctor portal account already exists.');
  $ins=$pdo->prepare("INSERT INTO portal_accounts(username,password_hash,role,doctor_id,full_name) VALUES(?,?,'Doctor',?,?)");
  $ins->execute([$phone,password_hash($password,PASSWORD_DEFAULT),(int)$doctorId,$doctor['doctor_name']]);
  $q=$pdo->prepare('SELECT * FROM portal_accounts WHERE account_id=?');$q->execute([(int)$pdo->lastInsertId()]);portal_login($q->fetch());
  header('Location: doctor-dashboard.php');exit;
 }catch(Throwable $ex){$error=$ex instanceof PDOException?'Could not create account. This doctor may already be registered.':$ex->getMessage();}
}
portal_page_start('Doctor Account Activation');
?>
<h1>Activate Doctor Portal</h1><p class="rp-muted">Create a password for an existing doctor record.</p>
<?php if($error): ?><div class="rp-alert"><?= portal_e($error) ?></div><?php endif; ?>
<form method="post" class="rp-form">
<input type="hidden" name="csrf_token" value="<?= portal_e(portal_csrf_token()) ?>">
<label>Doctor ID<input type="number" name="doctor_id" min="1" required></label>
<label>Registered doctor phone<input type="tel" name="phone" required></label>
<label>Password (minimum 10 characters)<input type="password" name="password" minlength="10" required></label>
<label>Confirm password<input type="password" name="confirm_password" minlength="10" required></label>
<div><button class="rp-btn" type="submit">Activate Account</button></div>
</form>
<div class="rp-alert">Doctor accounts should be activated only after identity and employment have been verified by hospital administration.</div>
<?php portal_page_end(); ?>
