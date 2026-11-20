<?php
require_once __DIR__ . '/portal-bootstrap.php';
$user=portal_require_role(['Admin','LabStaff']);$pdo=portal_db();$message='';$error='';
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['upload_report'])){
 portal_verify_csrf();
 $storedPath=null;
 try{
  $patientId=filter_var($_POST['patient_id']??null,FILTER_VALIDATE_INT);
  $test=trim((string)($_POST['test_name']??''));
  $date=trim((string)($_POST['report_date']??''));
  $notes=trim((string)($_POST['notes']??''));
  if(!$patientId||$test===''||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))
   throw new RuntimeException('Enter a valid patient, test name and report date.');
  [$year,$month,$day]=array_map('intval',explode('-',$date));
  if(!checkdate($month,$day,$year)) throw new RuntimeException('The report date is not a valid calendar date.');

  $p=$pdo->prepare('SELECT patient_id FROM patient WHERE patient_id=?');
  $p->execute([$patientId]);
  if(!$p->fetch()) throw new RuntimeException('Patient ID was not found.');

  $file=$_FILES['report_file']??null;
  if(!$file || !isset($file['error'])) throw new RuntimeException('Choose a PDF, JPG or PNG report file before uploading.');
  if((int)$file['error']!==UPLOAD_ERR_OK){
   $uploadErrors=[
    UPLOAD_ERR_INI_SIZE=>'The file exceeds the PHP upload_max_filesize setting.',
    UPLOAD_ERR_FORM_SIZE=>'The file exceeds the form upload size limit.',
    UPLOAD_ERR_PARTIAL=>'The file was only partially uploaded. Please try again.',
    UPLOAD_ERR_NO_FILE=>'No report file was selected.',
    UPLOAD_ERR_NO_TMP_DIR=>'PHP temporary upload folder is missing on this computer.',
    UPLOAD_ERR_CANT_WRITE=>'PHP could not write the temporary uploaded file. Check the XAMPP temporary folder permissions.',
    UPLOAD_ERR_EXTENSION=>'A PHP extension stopped the file upload.'
   ];
   throw new RuntimeException($uploadErrors[(int)$file['error']]??'The browser upload failed. Please try again.');
  }
  if(!is_uploaded_file($file['tmp_name'])) throw new RuntimeException('PHP did not receive a valid uploaded file. Refresh the page and try again.');
  if((int)$file['size']<=0) throw new RuntimeException('The selected file is empty.');
  if((int)$file['size']>10*1024*1024) throw new RuntimeException('File exceeds the 10 MB limit.');

  $finfo=new finfo(FILEINFO_MIME_TYPE);
  $mime=$finfo->file($file['tmp_name']);
  $allowed=['application/pdf'=>'pdf','image/jpeg'=>'jpg','image/png'=>'png'];
  if(!isset($allowed[$mime])) throw new RuntimeException('Only genuine PDF, JPG and PNG files are accepted. Detected file type: '.($mime?:'unknown').'.');

  $dir=__DIR__.'/private_uploads/lab_reports';
  if(!is_dir($dir) && !mkdir($dir,0770,true) && !is_dir($dir))
   throw new RuntimeException('Could not create the protected report folder. Check permissions on private_uploads.');
  if(!is_writable($dir)){
   throw new RuntimeException('The report folder exists but Apache cannot write to it. On Mac XAMPP, run the permission commands in UPLOAD_STORAGE_SETUP_MAC.txt, then retry the upload.');
  }

  $stored=bin2hex(random_bytes(24)).'.'.$allowed[$mime];
  $storedPath=$dir.'/'.$stored;
  if(!move_uploaded_file($file['tmp_name'],$storedPath)){
   $lastError=error_get_last();
   error_log('CityCare lab upload move failed. Destination='.$storedPath.'; PHP error='.($lastError['message']??'none'));
   throw new RuntimeException('PHP received the file but could not move it into protected storage. Check the folder permissions using UPLOAD_STORAGE_SETUP_MAC.txt.');
  }
  @chmod($storedPath,0640);

  try{
   $ins=$pdo->prepare('INSERT INTO lab_reports(patient_id,uploaded_by_account_id,test_name,report_date,notes,stored_filename,original_filename,mime_type,file_size) VALUES(?,?,?,?,?,?,?,?,?)');
   $ins->execute([$patientId,(int)$user['account_id'],$test,$date,$notes?:null,$stored,basename((string)$file['name']),$mime,(int)$file['size']]);
   $reportId=(int)$pdo->lastInsertId();
   portal_audit((int)$user['account_id'],'lab_report_uploaded',$reportId);
   $message='Laboratory report uploaded successfully and linked to the selected patient.';
   $storedPath=null; // keep the file after the database insert succeeds
  }catch(Throwable $ex){
   if($storedPath && is_file($storedPath)) @unlink($storedPath);
   error_log('CityCare lab report database insert failed: '.$ex->getMessage());
   throw new RuntimeException('The file was received, but the report record could not be saved in MySQL. Check the lab_reports table structure and database connection.');
  }
 }catch(Throwable $ex){
  if($storedPath && is_file($storedPath)) @unlink($storedPath);
  if($ex instanceof PDOException){error_log('CityCare lab upload database error: '.$ex->getMessage());$error='MySQL could not save the report record. Check the lab_reports table and database connection.';}
  else $error=$ex->getMessage();
 }
}
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_labstaff']) && $user['role']==='Admin'){
 portal_verify_csrf();
 try{$username=trim((string)($_POST['username']??''));$name=trim((string)($_POST['full_name']??''));$password=(string)($_POST['password']??'');
 if(!preg_match('/^[A-Za-z0-9._-]{4,40}$/',$username)||$name===''||strlen($password)<12)throw new RuntimeException('Use a valid username, full name and password of at least 12 characters.');
 $ins=$pdo->prepare("INSERT INTO portal_accounts(username,password_hash,role,full_name) VALUES(?,?,'LabStaff',?)");$ins->execute([$username,password_hash($password,PASSWORD_DEFAULT),$name]);$message='Laboratory staff account created.';
 }catch(Throwable $ex){$error=$ex instanceof PDOException?'Could not create account. Username may already exist.':$ex->getMessage();}
}
$patients=$pdo->query('SELECT patient_id,patient_name,phone FROM patient ORDER BY patient_name')->fetchAll();
$reports=$pdo->query('SELECT r.report_id,r.test_name,r.report_date,r.original_filename,p.patient_name,u.full_name AS uploaded_by FROM lab_reports r JOIN patient p ON p.patient_id=r.patient_id JOIN portal_accounts u ON u.account_id=r.uploaded_by_account_id ORDER BY r.created_at DESC LIMIT 100')->fetchAll();
portal_page_start('Staff Dashboard');
?>
<h1>Welcome, <?= portal_e($user['full_name']) ?></h1><p class="rp-muted"><?= portal_e($user['role']) ?> workspace for laboratory report management.</p>
<div class="rp-nav"><?php if($user['role']==='Admin'): ?><a class="rp-btn secondary" href="billing.php">Billing &amp; Payments</a><?php endif; ?><a class="rp-btn secondary" href="portal-logout.php">Sign out</a></div>
<?php if($message): ?><div class="rp-alert rp-success"><?= portal_e($message) ?></div><?php endif; ?><?php if($error): ?><div class="rp-alert"><?= portal_e($error) ?></div><?php endif; ?>
<div class="rp-grid cp-staff-metrics">
<div class="rp-stat"><span class="cp-stat-icon"><?= citycare_visual_icon('users') ?></span><strong><?= count($patients) ?></strong>Patient records</div>
<div class="rp-stat"><span class="cp-stat-icon"><?= citycare_visual_icon('lab') ?></span><strong><?= count($reports) ?></strong>Recent lab reports</div>
<div class="rp-stat"><span class="cp-stat-icon"><?= citycare_visual_icon('shield') ?></span><strong class="cp-role-value"><?= portal_e($user['role']) ?></strong>Your workspace access</div>
</div>
<h2 id="cp-upload">Upload Laboratory Report</h2>
<form method="post" enctype="multipart/form-data" class="rp-form"><input type="hidden" name="csrf_token" value="<?= portal_e(portal_csrf_token()) ?>">
<label>Patient<select name="patient_id" required><option value="">Select patient</option><?php foreach($patients as $p): ?><option value="<?= (int)$p['patient_id'] ?>">#<?= (int)$p['patient_id'] ?> · <?= portal_e($p['patient_name']) ?> (<?= portal_e($p['phone']) ?>)</option><?php endforeach; ?></select></label>
<label>Test name<input name="test_name" required maxlength="160" placeholder="e.g. Complete Blood Count"></label>
<label>Report date<input type="date" name="report_date" required value="<?= date('Y-m-d') ?>"></label>
<label>Report file (PDF/JPG/PNG, max 10 MB)<input type="file" name="report_file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required></label>
<label style="grid-column:1/-1">Notes<textarea name="notes"></textarea></label><div><button class="rp-btn" name="upload_report">Upload Report</button></div>
</form>
<?php if($user['role']==='Admin'): ?>
<h2 id="cp-accounts">Create Laboratory Staff Account</h2><form method="post" class="rp-form"><input type="hidden" name="csrf_token" value="<?= portal_e(portal_csrf_token()) ?>"><label>Username<input name="username" required></label><label>Full name<input name="full_name" required></label><label>Temporary password (12+ characters)<input type="password" name="password" minlength="12" required></label><div><button class="rp-btn" name="create_labstaff">Create Lab Staff</button></div></form>
<?php endif; ?>
<h2 id="cp-reports">Recent Uploaded Reports</h2><div class="rp-table-wrap"><table class="rp-table"><thead><tr><th>Patient</th><th>Test</th><th>Date</th><th>File</th><th>Uploaded by</th></tr></thead><tbody>
<?php foreach($reports as $r): ?><tr><td><?= portal_e($r['patient_name']) ?></td><td><?= portal_e($r['test_name']) ?></td><td><?= portal_e($r['report_date']) ?></td><td><a href="document-download.php?type=lab&id=<?= (int)$r['report_id'] ?>">Download</a></td><td><?= portal_e($r['uploaded_by']) ?></td></tr><?php endforeach; ?>
<?php if(!$reports): ?><tr><td colspan="5">No reports uploaded yet.</td></tr><?php endif; ?>
</tbody></table></div>
<div class="rp-alert">Upload only authorized test reports until the deployment security review is complete. Files are stored outside public web access and served through an authorization-checked download route.</div>
<?php portal_page_end(); ?>
