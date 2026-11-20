<?php
require_once __DIR__ . '/portal-bootstrap.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    portal_verify_csrf();
    $patientId = trim((string)($_POST['patient_id'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    try {
        if (!ctype_digit($patientId) || $phone === '' || strlen($password) < 10 || $password !== $confirm) {
            throw new RuntimeException('Enter a valid patient ID and phone. Password must be at least 10 characters and both password fields must match.');
        }
        $pdo = portal_db();
        $q = $pdo->prepare('SELECT patient_id, patient_name, phone FROM patient WHERE patient_id = ? AND phone = ? LIMIT 1');
        $q->execute([(int)$patientId, $phone]);
        $patient = $q->fetch();
        if (!$patient) throw new RuntimeException('Patient ID and booking phone did not match an existing patient record.');
        $exists = $pdo->prepare('SELECT account_id FROM portal_accounts WHERE patient_id = ? OR username = ? LIMIT 1');
        $exists->execute([(int)$patientId, $phone]);
        if ($exists->fetch()) throw new RuntimeException('A portal account already exists for this patient or phone number. Please sign in.');
        $ins = $pdo->prepare("INSERT INTO portal_accounts (username,password_hash,role,patient_id,full_name) VALUES (?,?,'Patient',?,?)");
        $ins->execute([$phone, password_hash($password, PASSWORD_DEFAULT), (int)$patientId, $patient['patient_name']]);
        portal_login($pdo->query('SELECT * FROM portal_accounts WHERE account_id=' . (int)$pdo->lastInsertId())->fetch());
        header('Location: patient-dashboard.php'); exit;
    } catch (Throwable $ex) { $error = $ex instanceof PDOException ? 'Could not create account. Check whether this patient already has an account.' : $ex->getMessage(); }
}
portal_page_start('Patient Registration');
?>
<h1>Create Patient Account</h1>
<p class="rp-muted">Use the Patient ID and phone number already recorded during appointment booking.</p>
<div class="rp-alert">Account verification: this form matches existing patient details. Hospital-verified identity checks are required before production use.</div>
<?php if ($error): ?><div class="rp-alert"><?= portal_e($error) ?></div><?php endif; ?>
<form method="post" class="rp-form">
<input type="hidden" name="csrf_token" value="<?= portal_e(portal_csrf_token()) ?>">
<label>Patient ID<input type="number" name="patient_id" min="1" required></label>
<label>Booking phone number<input type="tel" name="phone" required></label>
<label>Password (minimum 10 characters)<input type="password" name="password" minlength="10" required autocomplete="new-password"></label>
<label>Confirm password<input type="password" name="confirm_password" minlength="10" required autocomplete="new-password"></label>
<div><button class="rp-btn" type="submit">Create Account</button></div>
</form>
<p>Already registered? <a href="patient-login.php">Patient sign in</a></p>
<?php portal_page_end(); ?>
