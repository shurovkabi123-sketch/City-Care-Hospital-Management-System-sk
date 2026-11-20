<?php
require_once __DIR__ . '/portal-bootstrap.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    portal_verify_csrf();
    $username = trim((string)($_POST['phone'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    try {
        $q = portal_db()->prepare("SELECT * FROM portal_accounts WHERE username=? AND role='Patient' AND active=1 LIMIT 1");
        $q->execute([$username]); $account = $q->fetch();
        if (!$account || !password_verify($password, $account['password_hash'])) throw new RuntimeException('Phone number or password is incorrect.');
        portal_login($account); portal_audit((int)$account['account_id'], 'patient_login');
        header('Location: patient-dashboard.php'); exit;
    } catch (Throwable $ex) { $error = $ex instanceof PDOException ? 'Database connection failed. Check XAMPP and run portal-migration.sql.' : $ex->getMessage(); }
}
portal_page_start('Patient Login');
?>
<h1>Patient Sign In</h1><p class="rp-muted">Access your appointments, prescriptions and laboratory reports.</p>
<?php if ($error): ?><div class="rp-alert"><?= portal_e($error) ?></div><?php endif; ?>
<form method="post" class="rp-form">
<input type="hidden" name="csrf_token" value="<?= portal_e(portal_csrf_token()) ?>">
<label>Registered phone number<input type="tel" name="phone" required autocomplete="username"></label>
<label>Password<input type="password" name="password" required autocomplete="current-password"></label>
<div><button class="rp-btn" type="submit">Sign In</button></div>
</form>
<p>First time here? <a href="patient-register.php">Create a patient account</a></p>
<div class="rp-alert">Patient account setup matches the Patient ID and booking phone already on record. Verified identity checks and secure account recovery should be enabled before production use.</div>
<?php portal_page_end(); ?>
