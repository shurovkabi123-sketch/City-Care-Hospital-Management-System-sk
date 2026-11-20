<?php
declare(strict_types=1);
session_start();

const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'citycare_hospital';
const DB_USER = 'root';
const DB_PASS = '';

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$services = [
    'cardiology' => [
        'title' => 'Cardiology',
        'icon' => '🫀',
        'subtitle' => 'Heart and cardiovascular care',
        'intro' => 'Our Cardiology service focuses on assessment and care for heart and blood-vessel concerns. The clinical team can help patients understand symptoms, risk factors and appropriate next steps.',
        'items' => [
            'Consultation for heart-related symptoms',
            'Blood pressure and cardiovascular risk assessment',
            'Review of relevant test reports',
            'Guidance on follow-up and preventive heart health'
        ],
        'notice' => 'Chest pain, severe breathing difficulty, fainting or suspected heart attack may be an emergency. Seek urgent medical care immediately.'
    ],
    'neurology' => [
        'title' => 'Neurology',
        'icon' => '🧠',
        'subtitle' => 'Brain, nerve and nervous-system care',
        'intro' => 'The Neurology service provides assessment for concerns involving the brain, spinal cord, nerves and muscles, with guidance on investigations and follow-up when clinically appropriate.',
        'items' => [
            'Consultation for headache and recurring neurological symptoms',
            'Assessment of numbness, tingling or weakness',
            'Review of neurological test reports',
            'Follow-up planning for ongoing neurological conditions'
        ],
        'notice' => 'Sudden facial drooping, new weakness on one side, difficulty speaking, seizure or a sudden severe headache needs emergency assessment.'
    ],
    'orthopedics' => [
        'title' => 'Orthopedics',
        'icon' => '🦴',
        'subtitle' => 'Bone, joint, muscle and movement care',
        'intro' => 'Orthopedics supports assessment and care planning for bone, joint, ligament and muscle problems, injuries and difficulties affecting movement.',
        'items' => [
            'Assessment of joint and musculoskeletal pain',
            'Consultation for sprains and minor injuries',
            'Review of X-ray and other imaging reports',
            'Guidance on recovery, mobility and follow-up'
        ],
        'notice' => 'Severe injury, uncontrolled bleeding, visible deformity or loss of sensation after an injury needs urgent medical assessment.'
    ],
    'pediatrics' => [
        'title' => 'Pediatrics',
        'icon' => '👶',
        'subtitle' => 'Healthcare for babies, children and teenagers',
        'intro' => 'Pediatrics provides age-appropriate assessment and care for infants, children and adolescents, supporting families with routine health concerns and follow-up.',
        'items' => [
            'Child health consultations',
            'Growth and development discussions',
            'Assessment of common childhood illnesses',
            'Advice on follow-up and preventive care'
        ],
        'notice' => 'A child with severe breathing difficulty, unresponsiveness, blue lips or a seizure needs emergency care.'
    ],
    'emergency-care' => [
        'title' => 'Emergency Care',
        'icon' => '🚑',
        'subtitle' => 'Urgent assessment for serious symptoms and injuries',
        'intro' => 'Emergency Care is intended for urgent health problems that need prompt assessment. Do not use the online appointment form for a medical emergency.',
        'items' => [
            'Initial assessment of urgent symptoms',
            'Emergency response and triage',
            'Assessment of acute illness or injury',
            'Referral or further care based on clinical need'
        ],
        'notice' => 'For a life-threatening emergency in Bangladesh, call 999 or go to the nearest emergency department. Do not wait for an online booking.'
    ],
    'laboratory' => [
        'title' => 'Laboratory',
        'icon' => '🧪',
        'subtitle' => 'Diagnostic testing and laboratory services',
        'intro' => 'The Laboratory service supports clinical care with diagnostic testing. Test availability, preparation requirements and result timing depend on the specific test ordered.',
        'items' => [
            'Routine blood and urine testing (subject to availability)',
            'Sample collection according to test requirements',
            'Diagnostic test processing and reporting',
            'Guidance on preparation and report collection'
        ],
        'notice' => 'Confirm the exact test, preparation requirements and opening hours with the hospital before visiting.'
    ]
];

$key = strtolower(trim((string)($_GET['service'] ?? '')));
if (!isset($services[$key])) {
    http_response_code(404);
    $service = null;
} else {
    $service = $services[$key];
}

$doctors = [];
$dbError = '';
if ($service) {
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
        $stmt = $pdo->prepare(
            'SELECT d.doctor_id, d.doctor_name, d.specialization, dep.department_name
             FROM doctor d
             JOIN department dep ON dep.department_id = d.department_id
             WHERE LOWER(dep.department_name) = LOWER(?)
             ORDER BY d.doctor_name'
        );
        $departmentName = $service['title'];
        $stmt->execute([$departmentName]);
        $doctors = $stmt->fetchAll();
    } catch (Throwable $ex) {
        $dbError = 'Doctor availability could not be loaded right now. Please try again later.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $service ? e($service['title']) . ' | City Care Hospital' : 'Service Not Found | City Care Hospital' ?></title>
<link rel="stylesheet" href="style.css">
<style>
.service-detail-hero{padding:5rem 7%;background:linear-gradient(135deg,#071a2d,#124c75);color:#fff}
.service-detail-inner{max-width:1100px;margin:auto}
.service-detail-kicker{font-weight:800;letter-spacing:.12em;color:#7dd3fc;text-transform:uppercase;font-size:.82rem}
.service-detail-hero h1{font-size:clamp(2.2rem,5vw,3.5rem);margin:.8rem 0}
.service-detail-hero p{max-width:780px;line-height:1.8;color:#dbeafe;font-size:1.05rem}
.service-detail-icon{font-size:3rem}
.service-detail-content{padding:4rem 7%;background:#f5f9fc}
.service-detail-grid{max-width:1100px;margin:auto;display:grid;grid-template-columns:1.3fr .7fr;gap:2rem}
.service-detail-card{background:white;padding:2rem;border-radius:18px;box-shadow:0 8px 28px rgba(15,23,42,.08)}
.service-detail-card h2{color:#10243b;margin-top:0}
.service-detail-card li{padding:.45rem 0;line-height:1.6}
.service-detail-notice{margin-top:1.5rem;padding:1rem 1.1rem;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;border-radius:12px;line-height:1.65}
.service-doctors{margin-top:2rem}
.service-doctor-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem}
.service-doctor{border:1px solid #e2e8f0;border-radius:12px;padding:1.1rem;background:#fff}
.service-doctor h3{margin:.2rem 0 .5rem}
.service-actions{display:flex;gap:1rem;flex-wrap:wrap;margin-top:1.5rem}
.service-actions a{display:inline-block;padding:.85rem 1.2rem;border-radius:10px;text-decoration:none;font-weight:800}
.service-primary{background:#0284c7;color:white}
.service-secondary{border:1px solid #bae6fd;color:#e0f2fe}
.service-back{display:inline-block;margin-bottom:1rem;color:#bae6fd}
@media(max-width:760px){.service-detail-grid{grid-template-columns:1fr}.service-detail-content,.service-detail-hero{padding:3rem 5%}}
</style>
</head>
<body>
<?php if (!$service): ?>
<section class="service-detail-hero"><div class="service-detail-inner">
<p class="service-detail-kicker">City Care Hospital</p><h1>Service not found</h1>
<p>Please choose a service from the homepage.</p><a class="service-primary" href="index.php#services">Back to Services</a>
</div></section>
<?php else: ?>
<section class="service-detail-hero">
  <div class="service-detail-inner">
    <a class="service-back" href="index.php#services">← Back to all services</a>
    <div class="service-detail-icon"><?= e($service['icon']) ?></div>
    <p class="service-detail-kicker">City Care Hospital · Medical Services</p>
    <h1><?= e($service['title']) ?></h1>
    <p><strong><?= e($service['subtitle']) ?></strong></p>
    <p><?= e($service['intro']) ?></p>
    <div class="service-actions">
      <a class="service-primary" href="index.php#appointment">Book an Appointment</a>
      <a class="service-secondary" href="#service-doctors">View Department Doctors</a>
    </div>
  </div>
</section>
<section class="service-detail-content">
  <div class="service-detail-grid">
    <article class="service-detail-card">
      <h2>What this service offers</h2>
      <ul><?php foreach ($service['items'] as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul>
      <div class="service-detail-notice"><strong>Important health information:</strong><br><?= e($service['notice']) ?></div>
    </article>
    <aside class="service-detail-card">
      <h2>How to get started</h2>
      <ol>
        <li>Choose the relevant department or doctor.</li>
        <li>Use the online appointment form for routine consultations.</li>
        <li>Bring previous prescriptions and relevant test reports, if available.</li>
      </ol>
      <p>Service availability and specific tests should be confirmed with the hospital.</p>
      <a class="service-primary" href="index.php#appointment">Continue to booking →</a>
    </aside>
  </div>
  <div class="service-detail-inner service-doctors" id="service-doctors">
    <h2>Doctors in <?= e($service['title']) ?></h2>
    <?php if ($dbError): ?>
      <p class="cc-message cc-error"><?= e($dbError) ?></p>
    <?php elseif (!$doctors): ?>
      <p class="cc-message">No doctors are currently listed under this department in the database. You can still return to the appointment form to review available doctors.</p>
    <?php else: ?>
      <div class="service-doctor-list">
      <?php foreach ($doctors as $doctor): ?>
        <article class="service-doctor">
          <h3><?= e($doctor['doctor_name']) ?></h3>
          <p><?= e($doctor['specialization']) ?></p>
          <p><?= e($doctor['department_name']) ?></p>
          <a href="index.php#appointment">Book an appointment →</a>
        </article>
      <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>
<footer class="cc-footer" id="contact">
  <strong>City Care Hospital</strong>
  <span>Dhaka, Bangladesh</span>
  <a href="index.php">Home</a>
</footer>
</body>
</html>
