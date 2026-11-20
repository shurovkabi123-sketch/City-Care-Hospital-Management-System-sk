<?php
require_once __DIR__ . '/portal-bootstrap.php';
$user=portal_user();
if(!$user){header('Location: patient-login.php');exit;}
$type=(string)($_GET['type']??'');$id=filter_var($_GET['id']??null,FILTER_VALIDATE_INT);
if(!$id||$type!=='lab'){http_response_code(400);exit('Invalid document request.');}
$pdo=portal_db();
$q=$pdo->prepare('SELECT * FROM lab_reports WHERE report_id=? LIMIT 1');$q->execute([$id]);$report=$q->fetch();
if(!$report){http_response_code(404);exit('Document not found.');}
$allowed=false;
if($user['role']==='Patient') $allowed=(int)$report['patient_id']===(int)$user['patient_id'];
elseif($user['role']==='Doctor'){
 $check=$pdo->prepare('SELECT 1 FROM appointment WHERE patient_id=? AND doctor_id=? LIMIT 1');$check->execute([(int)$report['patient_id'],(int)$user['doctor_id']]);$allowed=(bool)$check->fetchColumn();
}elseif(in_array($user['role'],['Admin','LabStaff'],true)) $allowed=true;
if(!$allowed){http_response_code(403);exit('You are not authorized to access this report.');}
$path=__DIR__.'/private_uploads/lab_reports/'.$report['stored_filename'];
if(!is_file($path)){http_response_code(404);exit('Stored report file is missing.');}
portal_audit((int)$user['account_id'],'lab_report_downloaded',(int)$report['report_id']);
$mime=in_array($report['mime_type'],['application/pdf','image/jpeg','image/png'],true)?$report['mime_type']:'application/octet-stream';
header('Content-Type: '.$mime);header('Content-Length: '.filesize($path));header('X-Content-Type-Options: nosniff');
$filename=preg_replace('/[^A-Za-z0-9._-]/','_',basename($report['original_filename']));
header('Content-Disposition: attachment; filename="'.$filename.'"');
readfile($path);exit;
