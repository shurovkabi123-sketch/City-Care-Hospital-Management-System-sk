<?php
require_once __DIR__ . '/portal-bootstrap.php';
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){portal_verify_csrf();$username=trim((string)($_POST['username']??''));$password=(string)($_POST['password']??'');
 try{$q=portal_db()->prepare("SELECT * FROM portal_accounts WHERE username=? AND role IN ('Admin','LabStaff') AND active=1 LIMIT 1");$q->execute([$username]);$account=$q->fetch();if(!$account||!password_verify($password,$account['password_hash']))throw new RuntimeException('Username or password is incorrect.');portal_login($account);portal_audit((int)$account['account_id'],'staff_login');header('Location: staff-dashboard.php');exit;}
 catch(Throwable $ex){$error=$ex instanceof PDOException?'Database unavailable. Run portal-migration.sql first.':$ex->getMessage();}}
portal_page_start('Staff Login');
?>
<h1>Admin / Laboratory Staff Sign In</h1><?php if($error): ?><div class="rp-alert"><?= portal_e($error) ?></div><?php endif; ?>
<form method="post" class="rp-form"><input type="hidden" name="csrf_token" value="<?= portal_e(portal_csrf_token()) ?>"><label>Username<input name="username" required autocomplete="username"></label><label>Password<input type="password" name="password" required autocomplete="current-password"></label><div><button class="rp-btn">Sign In</button></div></form>
<p>First setup? <a href="staff-setup.php">Create the first Admin account</a></p>
<?php portal_page_end(); ?>
