<?php
require_once __DIR__ . '/portal-bootstrap.php';
$count=(int)portal_db()->query("SELECT COUNT(*) FROM portal_accounts WHERE role='Admin'")->fetchColumn();
$error='';
if($count>0){portal_page_start('Staff Setup');echo '<h1>Staff setup already completed</h1><p>An administrator account already exists. <a href="staff-login.php">Go to staff sign in</a>.</p>';portal_page_end();exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){
 portal_verify_csrf();$username=trim((string)($_POST['username']??''));$name=trim((string)($_POST['full_name']??''));$password=(string)($_POST['password']??'');$confirm=(string)($_POST['confirm_password']??'');
 try{if(!preg_match('/^[A-Za-z0-9._-]{4,40}$/',$username)||$name===''||strlen($password)<12||$password!==$confirm)throw new RuntimeException('Use a 4–40 character username, full name, and matching password of at least 12 characters.');
 $q=portal_db()->prepare("INSERT INTO portal_accounts(username,password_hash,role,full_name) VALUES(?,?,'Admin',?)");$q->execute([$username,password_hash($password,PASSWORD_DEFAULT),$name]);
 $count=1;
 }catch(Throwable $ex){$error=$ex instanceof PDOException?'Could not create the admin account. The username may already exist.':$ex->getMessage();}
}
portal_page_start('Initial Staff Setup');
?>
<h1>Create the first Admin account</h1>
<div class="rp-alert">One-time setup. Run this only on your local XAMPP computer. Once an Admin exists, this page will no longer create another Admin account. Delete or disable staff-setup.php after setup.</div>
<?php if($error): ?><div class="rp-alert"><?= portal_e($error) ?></div><?php endif; ?>
<?php if($count===0): ?><form method="post" class="rp-form"><input type="hidden" name="csrf_token" value="<?= portal_e(portal_csrf_token()) ?>">
<label>Admin username<input name="username" required></label><label>Full name<input name="full_name" required></label><label>Password (12+ characters)<input type="password" name="password" minlength="12" required></label><label>Confirm password<input type="password" name="confirm_password" minlength="12" required></label><div><button class="rp-btn">Create Admin</button></div></form>
<?php else: ?><div class="rp-alert rp-success">Admin account created. <a href="staff-login.php">Sign in to Staff Portal</a>.</div><?php endif; ?>
<?php portal_page_end(); ?>
