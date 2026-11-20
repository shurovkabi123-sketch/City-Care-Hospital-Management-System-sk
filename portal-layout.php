<?php
// Presentation helpers only: shared branding and navigation for the existing portals.
function citycare_visual_e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function citycare_visual_icon(string $name): string {
    $paths = [
        'home' => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M16 3v4M8 3v4M3 11h18m-13 5h3"/>',
        'document' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h8m-8 4h5"/>',
        'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8z"/><path d="M4 11h4l2-4 3 8 2-4h5"/>',
        'lab' => '<path d="M9 3h6m-5 0v6l-6 10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2L14 9V3M7 15h10m-7 3h.01M14 12h.01"/>',
        'wallet' => '<rect x="3" y="5" width="18" height="15" rx="3"/><path d="M3 9h18m0 4h-6v4h6M7 5V3h11v2"/>',
        'users' => '<circle cx="9" cy="8" r="4"/><path d="M2 21v-2a7 7 0 0 1 14 0v2M17 4a4 4 0 0 1 0 8m3 9v-2a7 7 0 0 0-3-6"/>',
        'arrow' => '<path d="M5 12h14m-6-6 6 6-6 6"/>',
        'logout' => '<path d="M9 21H4V3h5m6 14 5-5-5-5m-7 5h12"/>',
        'shield' => '<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6zM8 12l3 3 5-6"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? $paths['grid']) . '</svg>';
}
function citycare_visual_links(array $links, string $page): string {
    $html = '';
    foreach ($links as [$href, $label, $icon]) {
        $current = $href === $page ? ' aria-current="page"' : '';
        $html .= '<a href="' . citycare_visual_e($href) . '"' . $current . '>' . citycare_visual_icon($icon) . '<span>' . citycare_visual_e($label) . '</span></a>';
    }
    return $html;
}
function citycare_portal_start(string $title): void {
    $page = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'portal.php'));
    $role = strpos($page, 'patient-') === 0 ? 'patient' : (strpos($page, 'doctor-') === 0 ? 'doctor' : ((strpos($page, 'staff-') === 0 || $page === 'billing.php') ? 'staff' : 'hub'));
    $dashboard = in_array($page, ['patient-dashboard.php', 'doctor-dashboard.php', 'staff-dashboard.php', 'billing.php'], true);
    $auth = in_array($page, ['patient-login.php', 'patient-register.php', 'doctor-login.php', 'doctor-register.php', 'staff-login.php', 'staff-setup.php', 'patient-portal.php', 'doctor-portal.php'], true);
    $account = function_exists('portal_user') ? portal_user() : null;
    $label = $role === 'hub' ? 'Portal hub' : ucfirst($role) . ' portal';
    $copy = [
        'patient' => ['Care that stays<br>connected to you.', 'Your appointments, prescriptions and laboratory reports, together in one place.', ['Appointments & schedules', 'Prescriptions & reports', 'Bills & payment history'], 'heart'],
        'doctor' => ['Your expertise.<br>A clearer workspace.', 'Manage your schedule and prepare patient prescriptions with everything close at hand.', ['Appointment schedules', 'Patient prescriptions', 'Completed visit history'], 'calendar'],
        'staff' => ['Behind every visit,<br>a team that cares.', 'A focused workspace for laboratory reports, staff accounts and hospital billing.', ['Laboratory report uploads', 'Invoices & payment records', 'Staff account management'], 'lab'],
    ];
    $links = [];
    if ($dashboard && $role === 'patient') $links = [['patient-dashboard.php','Overview','grid'],['patient-dashboard.php#cp-appointments','Appointments','calendar'],['patient-dashboard.php#cp-prescriptions','Prescriptions','document'],['patient-dashboard.php#cp-reports','Lab reports','lab'],['patient-dashboard.php#cp-bills','Bills & payments','wallet']];
    if ($dashboard && $role === 'doctor') $links = [['doctor-dashboard.php','Overview','grid'],['doctor-dashboard.php#cp-appointments','Appointments','calendar'],['doctor-dashboard.php#cp-history','Visit history','document'],['doctor-dashboard.php#cp-prescribe','Write prescription','heart'],['doctor-portal.php','Schedule lookup','calendar']];
    if ($dashboard && $role === 'staff') {
        $links = [['staff-dashboard.php','Overview','grid'],['staff-dashboard.php#cp-upload','Upload report','lab'],['staff-dashboard.php#cp-reports','Recent reports','document']];
        if (($account['role'] ?? '') === 'Admin') {
            $links[] = ['staff-dashboard.php#cp-accounts','Staff accounts','users'];
            $links[] = ['billing.php','Billing & payments','wallet'];
        }
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . citycare_visual_e($title) . ' | City Care Hospital</title><link rel="stylesheet" href="style.css?v=portals20261112"><link rel="stylesheet" href="portal-theme.css?v=20261112"></head>';
    echo '<body class="cp-body cp-' . $role . ($dashboard ? ' cp-dashboard' : '') . ($auth ? ' cp-auth' : '') . '"><a class="cp-skip" href="#cp-content">Skip to content</a>';
    echo '<header class="cp-topbar"><a class="cp-brand" href="index.php" aria-label="City Care Hospital home"><span class="cp-brand-mark" aria-hidden="true">+</span><span>City Care <b>Hospital</b><small>Quality healthcare with compassion</small></span></a><nav class="cp-topnav" aria-label="Portal navigation">';
    foreach (['patient'=>'Patient','doctor'=>'Doctor','staff'=>'Staff'] as $key=>$name) echo '<a href="' . $key . '-login.php"' . ($role === $key ? ' aria-current="page"' : '') . '>' . $name . '<span> portal</span></a>';
    echo '</nav><a class="cp-home-link" href="index.php">' . citycare_visual_icon('home') . '<span>Hospital website</span></a></header>';
    if ($dashboard) echo '<details class="cp-mobile-menu"><summary>Workspace navigation</summary><nav aria-label="Mobile workspace">' . citycare_visual_links($links, $page) . '<a href="portal-logout.php">' . citycare_visual_icon('logout') . 'Sign out</a></nav></details>';
    echo '<div class="cp-frame">';
    if ($dashboard) {
        echo '<aside class="cp-sidebar"><div class="cp-workspace-label">YOUR WORKSPACE</div><div class="cp-sidebar-role">' . citycare_visual_icon($copy[$role][3]) . citycare_visual_e(ucfirst($role)) . ' portal</div><nav aria-label="Workspace">' . citycare_visual_links($links, $page) . '</nav><div class="cp-sidebar-bottom"><div class="cp-support-mark">' . citycare_visual_icon('heart') . '</div><strong>Care, made connected.</strong><p>City Care Hospital<br>Dhaka, Bangladesh</p><a href="portal-logout.php">' . citycare_visual_icon('logout') . 'Sign out</a></div></aside>';
    } elseif ($auth) {
        [$heading,$description,$features,$icon] = $copy[$role];
        echo '<aside class="cp-auth-story"><div class="cp-story-top"><span class="cp-story-label">' . citycare_visual_icon($icon) . citycare_visual_e($label) . '</span><span class="cp-story-line"></span></div><h2>' . $heading . '</h2><p>' . $description . '</p><ul>';
        foreach ($features as $feature) echo '<li><span aria-hidden="true">✓</span>' . citycare_visual_e($feature) . '</li>';
        echo '</ul><div class="cp-story-photo"><div class="cp-photo-caption"><span class="cp-dot"></span><div><strong>City Care Hospital</strong><small>Quality healthcare. Compassionate care.</small></div></div></div></aside>';
    }
    echo '<main class="rp-wrap" id="cp-content"><div class="cp-page-context"><span class="cp-context-dot"></span>' . citycare_visual_e($label) . '<span class="cp-context-separator">/</span><span>' . citycare_visual_e($title) . '</span></div><section class="rp-card">';
    echo '<a class="cc-portal-back" href="index.php">← Back to City Care Hospital</a>';
}
function citycare_portal_end(): void {
    echo '</section><footer class="cp-footer"><span>© City Care Hospital</span><a href="portal.php">Patient, doctor & staff portals ' . citycare_visual_icon('arrow') . '</a></footer></main></div></body></html>';
}
