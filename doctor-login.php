<?php
require_once __DIR__ . '/portal-bootstrap.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    portal_verify_csrf();
    $username=trim((string)($_POST['phone']??'')); $password=(string)($_POST['password']??'');
    try {
        $q=portal_db()->prepare("SELECT * FROM portal_accounts WHERE username=? AND role='Doctor' AND active=1 LIMIT 1");
        $q->execute([$username]); $account=$q->fetch();
        if (!$account || !password_verify($password,$account['password_hash'])) throw new RuntimeException('Phone number or password is incorrect.');
        portal_login($account); portal_audit((int)$account['account_id'],'doctor_login');
        header('Location: doctor-dashboard.php'); exit;
    } catch(Throwable $ex) { $error=$ex instanceof PDOException?'Database connection failed. Run portal-migration.sql first.':$ex->getMessage(); }
}
portal_page_start('Doctor Login');
?>
<h1>Doctor Sign In</h1><p class="rp-muted">Access your schedule and issue patient prescriptions.</p>
<?php if($error): ?><div class="rp-alert"><?= portal_e($error) ?></div><?php endif; ?>
<form method="post" class="rp-form">
<input type="hidden" name="csrf_token" value="<?= portal_e(portal_csrf_token()) ?>">
<label>Registered doctor phone<input type="tel" name="phone" required autocomplete="username"></label>
<label>Password<input type="password" name="password" required autocomplete="current-password"></label>
<div><button class="rp-btn" type="submit">Sign In</button></div>
</form>
<p>First time? <a href="doctor-register.php">Activate doctor portal account</a></p>
<div class="rp-alert">Doctor account activation uses registered doctor details. Hospital-controlled identity verification is required before production use.</div>
<?php portal_page_end(); ?>
