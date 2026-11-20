<?php
declare(strict_types=1);

// Local development diagnostic only. Do not expose this page on a public server.
$remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
if (!in_array($remote, ['127.0.0.1', '::1'], true)) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/portal-bootstrap.php';

$checks = [];
$checks[] = ['PHP version', PHP_VERSION, version_compare(PHP_VERSION, '8.0.0', '>=')];
$checks[] = ['Portal bootstrap file', is_file(__DIR__ . '/portal-bootstrap.php') ? 'Found' : 'Missing', is_file(__DIR__ . '/portal-bootstrap.php')];
$requiredFiles = [
    'portal.php', 'patient-login.php', 'patient-dashboard.php',
    'patient-register.php', 'doctor-login.php', 'doctor-dashboard.php',
    'doctor-register.php', 'staff-login.php', 'staff-dashboard.php',
    'billing.php', 'portal-logout.php'
];
foreach ($requiredFiles as $file) {
    $exists = is_file(__DIR__ . '/' . $file);
    $checks[] = [$file, $exists ? 'Found' : 'Missing', $exists];
}

$dbMessage = 'Not checked';
$dbOk = false;
$tableNames = [
    'patient', 'doctor', 'department', 'appointment', 'portal_accounts',
    'portal_audit_log', 'prescriptions', 'prescription_items',
    'lab_reports', 'billing_invoices', 'billing_invoice_items', 'billing_payments'
];
try {
    $pdo = portal_db();
    $dbOk = true;
    $dbMessage = 'Connected to citycare_hospital';
    $checks[] = ['Database connection', $dbMessage, true];
    foreach ($tableNames as $table) {
        $q = $pdo->prepare('SHOW TABLES LIKE ?');
        $q->execute([$table]);
        $exists = (bool)$q->fetchColumn();
        $checks[] = ['Table: ' . $table, $exists ? 'Found' : 'Missing', $exists];
    }
    if ($dbOk) {
        foreach (['Patient', 'Doctor', 'Admin', 'LabStaff'] as $role) {
            $q = $pdo->prepare('SELECT COUNT(*) FROM portal_accounts WHERE role = ? AND active = 1');
            $q->execute([$role]);
            $checks[] = ['Active ' . $role . ' accounts', (string)$q->fetchColumn(), true];
        }
    }
} catch (Throwable $e) {
    $checks[] = ['Database connection', 'Failed. Check that XAMPP MySQL is running and DB settings match.', false];
}

portal_page_start('Local Portal Diagnostics');
?>
<h1>City Care Portal Diagnostics</h1>
<p class="rp-muted">Read-only local checks. This page does not modify your database or patient records.</p>
<div class="rp-table-wrap">
<table class="rp-table">
<thead><tr><th>Check</th><th>Result</th><th>Status</th></tr></thead>
<tbody>
<?php foreach ($checks as [$name, $result, $ok]): ?>
<tr>
  <td><?= portal_e($name) ?></td>
  <td><?= portal_e($result) ?></td>
  <td><?= $ok ? 'OK' : 'Needs attention' ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<p><a class="rp-btn" href="portal.php">Open City Care Portals</a>
<a class="rp-btn secondary" href="index.php">Main Website</a></p>
<div class="rp-alert">Security: this diagnostic page is restricted to requests from this computer. Delete it before any public deployment.</div>
<?php portal_page_end(); ?>
