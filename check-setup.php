<?php
declare(strict_types=1);
// Read-only checks for the local XAMPP computer. No database imports or permission changes.
$remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
if (!in_array($remote, ['127.0.0.1', '::1'], true)) { http_response_code(404); exit; }
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
function check_e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$checks = [];
$checks[] = ['PHP version', PHP_VERSION, version_compare(PHP_VERSION, '8.0.0', '>=')];
foreach (['pdo_mysql', 'fileinfo', 'session', 'ctype'] as $extension) {
 $ok = extension_loaded($extension); $checks[] = ['PHP extension: '.$extension, $ok ? 'Available' : 'Missing — enable in XAMPP PHP settings', $ok];
}
foreach (['index.php','style.css','portal-bootstrap.php','patient-login.php','doctor-login.php','staff-login.php'] as $file) {
 $ok = is_readable(__DIR__.'/'.$file); $checks[] = [$file, $ok ? 'Readable' : 'Missing or not readable by Apache', $ok];
}
try {
 require_once __DIR__.'/portal-bootstrap.php';
 $pdo = portal_db();
 $checks[] = ['Database connection', 'Connected to '.PORTAL_DB_NAME, true];
 $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
 foreach (['admission','appointment','bill','department','doctor','patient','room','users','portal_accounts','prescriptions','prescription_items','lab_reports','portal_audit_log','billing_invoices','billing_invoice_items','billing_payments'] as $table) {
  $ok = in_array($table, $tables, true); $checks[] = ['Table: '.$table, $ok ? 'Found' : 'Missing — see setup instructions', $ok];
 }
} catch (Throwable $e) {
 $code = $e instanceof PDOException && isset($e->errorInfo[1]) ? (int)$e->errorInfo[1] : 0;
 $message = $code === 1049 ? 'Database is missing. For a fresh installation import the main SQL through phpMyAdmin.' : ($code === 1045 ? 'Login rejected. Check the database username and password in the supplied PHP settings.' : ($code === 2002 ? 'Cannot reach MySQL. Start MySQL Database in XAMPP and check its port.' : 'Could not complete database checks. Check XAMPP PHP extensions, MySQL and the supplied connection settings.'));
 $checks[] = ['Database connection', $message, false];
}
$writable = is_writable(__DIR__.'/private_uploads/lab_reports');
$checks[] = ['Report upload folder', $writable ? 'Writable' : 'Not writable by Apache. This affects new report uploads; grant that Apache account write access through Finder or your Mac administrator.', $writable];
?><!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>City Care — Local Setup Check</title><style>body{font:16px/1.6 system-ui,sans-serif;color:#183653;background:#edf5fb;margin:0;padding:24px}main{max-width:960px;margin:auto;background:#fff;padding:24px;border-radius:16px}table{border-collapse:collapse;width:100%}td,th{text-align:left;border-bottom:1px solid #dce6ef;padding:12px;vertical-align:top}.ok{color:#17643a}.fail{color:#99400e}a{color:#0369a1}.scroll{overflow:auto}</style><main><h1>City Care — Local Setup Check</h1><p>This page checks your local setup without importing SQL or changing application files. If a check fails, send a screenshot of this page.</p><div class="scroll"><table><thead><tr><th>Check</th><th>Result</th><th>Status</th></tr></thead><tbody><?php foreach ($checks as [$label,$message,$ok]): ?><tr><td><?= check_e($label) ?></td><td><?= check_e($message) ?></td><td class="<?= $ok ? 'ok' : 'fail' ?>"><?= $ok ? 'OK' : 'Needs attention' ?></td></tr><?php endforeach; ?></tbody></table></div><p><a href="index.php">Open hospital website</a> · <a href="help.html">Setup instructions</a></p></main></html>
