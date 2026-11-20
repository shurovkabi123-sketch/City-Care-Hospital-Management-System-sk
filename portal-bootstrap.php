<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

const PORTAL_DB_HOST = '127.0.0.1';
const PORTAL_DB_PORT = '3306';
const PORTAL_DB_NAME = 'citycare_hospital';
const PORTAL_DB_USER = 'root';
const PORTAL_DB_PASS = '';

function portal_db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $pdo = new PDO(
        'mysql:host=' . PORTAL_DB_HOST . ';port=' . PORTAL_DB_PORT . ';dbname=' . PORTAL_DB_NAME . ';charset=utf8mb4',
        PORTAL_DB_USER,
        PORTAL_DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    return $pdo;
}

function portal_e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function portal_csrf_token(): string {
    if (empty($_SESSION['portal_csrf'])) {
        $_SESSION['portal_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['portal_csrf'];
}

function portal_verify_csrf(): void {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['portal_csrf']) || !hash_equals($_SESSION['portal_csrf'], $token)) {
        http_response_code(419);
        exit('Security token expired. Please go back, refresh the page, and try again.');
    }
}

function portal_user(): ?array {
    return isset($_SESSION['portal_user']) && is_array($_SESSION['portal_user'])
        ? $_SESSION['portal_user'] : null;
}

function portal_require_role(array $roles): array {
    $user = portal_user();
    if (!$user || !in_array($user['role'] ?? '', $roles, true)) {
        $target = in_array('Patient', $roles, true) ? 'patient-login.php'
            : (in_array('Doctor', $roles, true) ? 'doctor-login.php' : 'staff-login.php');
        header('Location: ' . $target);
        exit;
    }
    return $user;
}

function portal_login(array $account): void {
    session_regenerate_id(true);
    $_SESSION['portal_user'] = [
        'account_id' => (int)$account['account_id'],
        'role' => (string)$account['role'],
        'username' => (string)$account['username'],
        'patient_id' => $account['patient_id'] !== null ? (int)$account['patient_id'] : null,
        'doctor_id' => $account['doctor_id'] !== null ? (int)$account['doctor_id'] : null,
        'full_name' => (string)$account['full_name'],
    ];
    $_SESSION['portal_csrf'] = bin2hex(random_bytes(32));
}

function portal_page_start(string $title): void {
    require_once __DIR__ . '/portal-layout.php';
    citycare_portal_start($title);
}

function portal_page_end(): void {
    citycare_portal_end();
}

function portal_audit(int $accountId, string $action, ?int $recordId = null): void {
    try {
        $q = portal_db()->prepare('INSERT INTO portal_audit_log (account_id, action_name, record_id, ip_address) VALUES (?, ?, ?, ?)');
        $q->execute([$accountId, $action, $recordId, substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)]);
    } catch (Throwable $ignored) {
        // Audit logging must not break a local student demo workflow.
    }
}
