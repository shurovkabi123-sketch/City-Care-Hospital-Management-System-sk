<?php
declare(strict_types=1);
require_once __DIR__ . '/portal-bootstrap.php';

portal_page_start('Patient, Doctor & Staff Portals');
?>
<h1>City Care Hospital Portals</h1>
<p class="rp-muted">Choose the correct secure sign-in area. Use the appointment lookup only when you do not have a portal account.</p>

<div class="rp-grid">
  <div class="rp-panel">
    <h2>Patient Portal</h2>
    <p>View appointments, prescriptions, laboratory reports and bills.</p>
    <p><a class="rp-btn" href="patient-login.php">Patient Sign In</a></p>
    <p><a href="patient-register.php">Create patient account</a></p>
    <p><a href="patient-portal.php">Appointment lookup</a></p>
  </div>
  <div class="rp-panel">
    <h2>Doctor Portal</h2>
    <p>View assigned appointments and create prescriptions.</p>
    <p><a class="rp-btn" href="doctor-login.php">Doctor Sign In</a></p>
    <p><a href="doctor-register.php">Activate doctor account</a></p>
    <p><a href="doctor-portal.php">Doctor schedule lookup</a></p>
  </div>
  <div class="rp-panel">
    <h2>Staff Portal</h2>
    <p>Manage laboratory report uploads and billing (Admin access).</p>
    <p><a class="rp-btn" href="staff-login.php">Staff Sign In</a></p>
  </div>
</div>
<p class="rp-muted">For the main hospital website, return to <a href="index.php">City Care Hospital Home</a>.</p>
<?php portal_page_end(); ?>
