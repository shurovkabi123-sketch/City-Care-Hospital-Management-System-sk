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

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT .
           ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$flashMessage = '';
$flashType = 'success';
$old = [];
$doctors = [];
$doctorsError = '';

try {
    $pdo = db();
    $doctors = $pdo->query(
        'SELECT d.doctor_id, d.doctor_name, d.specialization, dep.department_name
         FROM doctor d
         JOIN department dep ON dep.department_id = d.department_id
         ORDER BY dep.department_name, d.doctor_name'
    )->fetchAll();
} catch (Throwable $ex) {
    $doctorsError = 'Cannot connect to citycare_hospital. Check that XAMPP MySQL is running and config.php settings match your local setup.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    foreach (['patient_name','phone','gender','date_of_birth','doctor_id','appointment_date','appointment_time','address','notes'] as $field) {
        $old[$field] = trim((string)($_POST[$field] ?? ''));
    }

    try {
        if (!hash_equals($_SESSION['csrf_token'], (string)($_POST['csrf_token'] ?? ''))) {
            throw new RuntimeException('Security token check failed. Refresh the page and try again.');
        }
        if ($old['patient_name'] === '' || $old['phone'] === '' || $old['gender'] === '' ||
            $old['doctor_id'] === '' || $old['appointment_date'] === '' || $old['appointment_time'] === '') {
            throw new RuntimeException('Please complete all required fields.');
        }
        if (!in_array($old['gender'], ['Male','Female','Other'], true)) {
            throw new RuntimeException('Please select a valid gender.');
        }
        if (!ctype_digit($old['doctor_id'])) {
            throw new RuntimeException('Please select a valid doctor.');
        }
        if (!preg_match('/^[0-9+() -]{7,20}$/', $old['phone'])) {
            throw new RuntimeException('Please enter a valid phone number.');
        }

        $today = date('Y-m-d');
        $date = DateTime::createFromFormat('!Y-m-d', $old['appointment_date']);
        if (!$date || $date->format('Y-m-d') !== $old['appointment_date'] || $old['appointment_date'] < $today) {
            throw new RuntimeException('Choose today or a future date for the appointment.');
        }
        $time = DateTime::createFromFormat('!H:i', $old['appointment_time']);
        if (!$time || $time->format('H:i') !== $old['appointment_time']) {
            throw new RuntimeException('Please choose a valid appointment time.');
        }
        if ($old['date_of_birth'] !== '') {
            $dob = DateTime::createFromFormat('!Y-m-d', $old['date_of_birth']);
            if (!$dob || $dob->format('Y-m-d') !== $old['date_of_birth'] || $old['date_of_birth'] > $today) {
                throw new RuntimeException('Please enter a valid date of birth.');
            }
        }

        $pdo = db();
        $pdo->beginTransaction();

       
        $find = $pdo->prepare('SELECT patient_id FROM patient WHERE phone = ? ORDER BY patient_id LIMIT 1');
        $find->execute([$old['phone']]);
        $patientId = $find->fetchColumn();

        if (!$patientId) {
            $insertPatient = $pdo->prepare(
                'INSERT INTO patient (patient_name, gender, date_of_birth, phone, address)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $insertPatient->execute([
                $old['patient_name'],
                $old['gender'],
                $old['date_of_birth'] !== '' ? $old['date_of_birth'] : null,
                $old['phone'],
                $old['address'] !== '' ? $old['address'] : null,
            ]);
            $patientId = (int)$pdo->lastInsertId();
        }

        $checkDoctor = $pdo->prepare('SELECT doctor_id FROM doctor WHERE doctor_id = ?');
        $checkDoctor->execute([(int)$old['doctor_id']]);
        if (!$checkDoctor->fetchColumn()) {
            throw new RuntimeException('The selected doctor does not exist.');
        }

        $insertAppointment = $pdo->prepare(
            "INSERT INTO appointment
             (patient_id, doctor_id, appointment_date, appointment_time, status, notes)
             VALUES (?, ?, ?, ?, 'Booked', ?)"
        );
        $insertAppointment->execute([
            (int)$patientId,
            (int)$old['doctor_id'],
            $old['appointment_date'],
            $old['appointment_time'] . ':00',
            $old['notes'] !== '' ? $old['notes'] : null,
        ]);

        $pdo->commit();
        $flashMessage = 'Appointment booked successfully. Your request has been saved.';
        $flashType = 'success';
        $old = [];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Throwable $ex) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = $ex->getMessage();
        if (stripos($message, 'Doctor already has an appointment') !== false) {
            $message = 'This doctor already has an appointment at that date and time. Please choose another slot.';
        } elseif ($ex instanceof PDOException) {
            // Keep raw SQL details out of the public page.
            $message = 'Could not save the appointment. Check the database connection, selected doctor, and available time.';
        }
        $flashMessage = $message;
        $flashType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>City Care Hospital</title>

    <link rel="stylesheet" href="style.css?v=userphotos20261107">

</head>


<body>


    <!-- Top Information Bar -->

    <div class="top-bar">

        <div>
            📍 Cumilla, Bangladesh
        </div>

    </div>


    <header>

        <div class="logo">

            <div class="logo-icon">
                <svg width="48" height="48" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <!-- Modern Ruby Red Gradient -->
                        <linearGradient id="redBadgeGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" stop-color="#f43f5e" />
                            <stop offset="35%" stop-color="#e11d48" />
                            <stop offset="100%" stop-color="#9f1239" />
                        </linearGradient>
                        <!-- Specular Glass Highlight -->
                        <linearGradient id="glassSheen" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" stop-color="#ffffff" stop-opacity="0.3" />
                            <stop offset="100%" stop-color="#ffffff" stop-opacity="0" />
                        </linearGradient>
                        <!-- Drop Shadow Filter for the White Cross -->
                        <filter id="crossShadow" x="-10%" y="-10%" width="120%" height="120%" filterUnits="userSpaceOnUse">
                            <feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#4c0519" flood-opacity="0.35" />
                        </filter>
                    </defs>
                    <!-- Modern Squircle Badge with Red Ambient Depth -->
                    <rect width="48" height="48" rx="14" fill="url(#redBadgeGrad)" />
                    <!-- Beveled Glass Top Highlight -->
                    <rect x="1.5" y="1.5" width="45" height="45" rx="12.5" stroke="rgba(255, 255, 255, 0.35)" stroke-width="1.2" fill="url(#glassSheen)" />
                    
                    <!-- Modern White Medical Cross with Soft Curves -->
                    <g filter="url(#crossShadow)">
                        <!-- Vertical Cross Bar -->
                        <rect x="19" y="10" width="10" height="28" rx="4" fill="#ffffff" />
                        <!-- Horizontal Cross Bar -->
                        <rect x="10" y="19" width="28" height="10" rx="4" fill="#ffffff" />
                    </g>
                    
                    <!-- Modern Dynamic Red ECG Heartbeat Pulse Line -->
                    <path d="M11 24H17L19.5 17L23.5 31L26.5 19L28.5 24H37" stroke="#e11d48" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" />
                    
                    <!-- Stylized Healing Spark -->
                    <circle cx="36.5" cy="11.5" r="2.2" fill="#ffe4e6" />
                    <circle cx="36.5" cy="11.5" r="1" fill="#ffffff" />
                </svg>
            </div>

            <div>

                <h1>City Care <span>Hospital</span></h1>

                <p>Quality Healthcare With Compassion</p>

            </div>

        </div>


        <div class="header-right">

            <div class="cc-header-login-links" aria-label="Portal sign in">
                <a href="Patient-login.php">Patient Login</a>
                <a href="doctor-login.php">Doctor Login</a>
                <a href="staff-login.php">Staff Login</a>
            </div>

            <div class="cc-emergency-contact">
                📞
                <span>
                    Emergency<br>
                    <strong>999</strong>
                </span>
            </div>

            <a class="header-book-btn" href="#appointment">Book Appointment</a>

        </div>

    </header>



    <!-- Navigation Bar -->

    <nav>

        <a href="#home" class="active">
            Home
        </a>

        <a href="#about">
            About Us
        </a>

        <a href="#services">
            Services
        </a>

        <a href="#doctors">
            Doctors
        </a>

        <a href="#services">
            Departments
        </a>

        <a href="#about">
            Facilities
        </a>

        </a>
         <a href="#contact">
            Helpline
        
        </a>


    </nav>
<!-- ================= HERO SECTION ================= -->

<section class="hero" id="home">

    <div class="hero-content">

        <p class="hero-small">
            WELCOME TO CITY CARE HOSPITAL
        </p>

        <h2>
            Your Health,<br>
            Our <span>Priority.</span>
        </h2>

        <p class="hero-text">
            Providing trusted healthcare with experienced doctors,
            modern technology and compassionate care.
        </p>

        <div class="hero-buttons">

            <a href="#appointment" class="btn-primary">
                Book an Appointment
            </a>

            <a href="#doctors" class="btn-secondary">
                Find a Doctor
            </a>

        </div>

    </div>

</section>
<!-- ================= SERVICES SECTION ================= -->

<section class="services-section" id="services">

    <div class="section-heading">

        <p>WHAT WE OFFER</p>

        <h2>Our Medical Services</h2>

        <span></span>

        <h3>
            Comprehensive healthcare services for you and your family
        </h3>

    </div>


    <div class="services-container">


        <!-- Service 1 -->

        <div class="service-card">

            <div class="service-icon">
                🫀
            </div>

            <h3>Cardiology</h3>

            <p>
                Complete heart care from experienced
                cardiology specialists.
            </p>

            <a href="service.php?service=cardiology">
                Learn More →
            </a>

        </div>


        <!-- Service 2 -->

        <div class="service-card">

            <div class="service-icon">
                🧠
            </div>

            <h3>Neurology</h3>

            <p>
                Advanced diagnosis and treatment
                for neurological conditions.
            </p>

            <a href="service.php?service=neurology">
                Learn More →
            </a>

        </div>


        <!-- Service 3 -->

        <div class="service-card">

            <div class="service-icon">
                🦴
            </div>

            <h3>Orthopedics</h3>

            <p>
                Expert treatment for bones,
                joints and muscles.
            </p>

            <a href="service.php?service=orthopedics">
                Learn More →
            </a>

        </div>


        <!-- Service 4 -->

        <div class="service-card">

            <div class="service-icon">
                👶
            </div>

            <h3>Pediatrics</h3>

            <p>
                Specialized healthcare for
                infants, children and teenagers.
            </p>

            <a href="service.php?service=pediatrics">
                Learn More →
            </a>

        </div>


        <!-- Service 5 -->

        <div class="service-card">

            <div class="service-icon">
                🚑
            </div>

            <h3>Emergency Care</h3>

            <p>
                24/7 emergency medical services
                for urgent healthcare needs.
            </p>

            <a href="service.php?service=emergency-care">
                Learn More →
            </a>

        </div>


        <!-- Service 6 -->

        <div class="service-card">

            <div class="service-icon">
                🧪
            </div>

            <h3>Laboratory</h3>

            <p>
                Modern laboratory facilities
                for accurate medical testing.
            </p>

            <a href="service.php?service=laboratory">
                Learn More →
            </a>

        </div>


    </div>


    <div class="services-button">

        <a href="#services">
            View All Services →
        </a>

    </div>

</section>
<!-- ================= ABOUT SECTION ================= -->

<section class="about-section" id="about">

    <div class="about-container">


        <!-- Hospital Image -->

        <div class="about-image">

            <img src="hospital-about.jpg"
                 alt="City Care Hospital">

            <div class="experience-box">

                <strong>15+</strong>

                <span>Years of Excellence</span>

            </div>

        </div>



        <!-- About Content -->

        <div class="about-content">

            <p class="about-small">
                ABOUT OUR HOSPITAL
            </p>

            <h2>
                Caring for You Like Family
            </h2>

            <div class="about-line"></div>

            <p>
                City Care Hospital is committed to providing
                high-quality healthcare services to our patients.
                Our experienced doctors, nurses and healthcare
                professionals work together to provide safe,
                reliable and compassionate care.
            </p>

            <p>
                We combine modern medical technology with
                personalized patient care to ensure the best
                possible healthcare experience for every patient.
            </p>


            <!-- Features -->

            <div class="about-features">

                <div>

                    <span>✓</span>

                    <p>
                        Experienced Doctors
                    </p>

                </div>


                <div>

                    <span>✓</span>

                    <p>
                        Modern Facilities
                    </p>

                </div>


                <div>

                    <span>✓</span>

                    <p>
                        24/7 Emergency Care
                    </p>

                </div>


                <div>

                    <span>✓</span>

                    <p>
                        Patient-Centered Care
                    </p>

                </div>

            </div>


            <a href="#" class="about-button">
                Learn More About Us →
            </a>

        </div>

    </div>

</section>



<!-- ================= PORTAL QUICK ACCESS ================= -->
<section class="cc-portal-access" id="portals">
  <div class="cc-live-heading">
    <p>SECURE PORTAL ACCESS</p>
    <h2>Choose Your Login</h2>
    <span>Sign in to the relevant City Care Hospital workspace.</span>
  </div>
  <div class="cc-portal-access-grid">
    <article class="cc-portal-access-card patient">
      <div class="cc-portal-access-icon">👤</div>
      <h3>Patient Portal</h3>
      <p>View your appointments, prescriptions and laboratory reports.</p>
      <a href="patient-login.php">Patient Login →</a>
    </article>
    <article class="cc-portal-access-card doctor">
      <div class="cc-portal-access-icon">🩺</div>
      <h3>Doctor Portal</h3>
      <p>View your appointment schedule and prepare patient prescriptions.</p>
      <a href="doctor-login.php">Doctor Login →</a>
    </article>
    <article class="cc-portal-access-card staff">
      <div class="cc-portal-access-icon">🧪</div>
      <h3>Staff Portal</h3>
      <p>Manage laboratory report uploads and authorized report records.</p>
      <a href="staff-login.php">Staff Login →</a>
    </article>
  </div>
  <p class="cc-portal-access-note">Access the Patient, Doctor and Staff portals using the account options above.</p>
</section>

<!-- ================= LIVE DOCTORS FROM DATABASE ================= -->
<section class="cc-live-section" id="doctors">
  <div class="cc-live-heading">
    <p>OUR MEDICAL TEAM</p>
    <h2>Find a Doctor</h2>
    <span>Doctors are loaded from the City Care Hospital database.</span>
  </div>
  <div class="cc-doctor-grid">
    <?php if ($doctorsError): ?>
      <p class="cc-message cc-error"><?= e($doctorsError) ?></p>
    <?php elseif (!$doctors): ?>
      <p class="cc-message">No doctor records are available yet.</p>
    <?php else: ?>
      <?php foreach ($doctors as $doctor): ?>
        <article class="cc-doctor-card">
          <div class="cc-doctor-icon">⚕️</div>
          <h3><?= e($doctor['doctor_name']) ?></h3>
          <p><?= e($doctor['specialization']) ?></p>
          <span><?= e($doctor['department_name']) ?></span>
          <a href="#appointment">Book with this doctor →</a>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<!-- ================= DATABASE APPOINTMENT FORM ================= -->
<section class="cc-appointment-section" id="appointment">
  <div class="cc-appointment-wrap">
    <div class="cc-appointment-intro">
      <p class="cc-kicker">ONLINE APPOINTMENT</p>
      <h2>Book Your Appointment</h2>
      <p>Submit the form to save an appointment in the City Care Hospital MySQL database.</p>
      <div class="cc-note">For medical emergencies, call 999 or visit the nearest emergency department.</div>
    </div>

    <?php if ($flashMessage): ?>
      <div class="cc-message <?= $flashType === 'success' ? 'cc-success' : 'cc-error' ?>" role="status"><?= e($flashMessage) ?></div>
    <?php endif; ?>

    <form class="cc-appointment-form" method="post" action="#appointment">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <div class="cc-form-grid">
        <label>Patient full name *
          <input type="text" name="patient_name" maxlength="100" required value="<?= e($old['patient_name'] ?? '') ?>">
        </label>
        <label>Phone number *
          <input type="tel" name="phone" maxlength="20" required pattern="[0-9+() -]{7,20}" value="<?= e($old['phone'] ?? '') ?>">
        </label>
        <label>Gender *
          <select name="gender" required>
            <option value="">Select gender</option>
            <?php foreach (['Male','Female','Other'] as $g): ?>
              <option value="<?= e($g) ?>" <?= (($old['gender'] ?? '') === $g) ? 'selected' : '' ?>><?= e($g) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Date of birth
          <input type="date" name="date_of_birth" max="<?= date('Y-m-d') ?>" value="<?= e($old['date_of_birth'] ?? '') ?>">
        </label>
        <label>Doctor *
          <select name="doctor_id" required>
            <option value="">Choose a doctor</option>
            <?php foreach ($doctors as $doctor): ?>
              <option value="<?= (int)$doctor['doctor_id'] ?>" <?= ((string)($old['doctor_id'] ?? '') === (string)$doctor['doctor_id']) ? 'selected' : '' ?>>
                <?= e($doctor['doctor_name']) ?> — <?= e($doctor['department_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Appointment date *
          <input type="date" name="appointment_date" min="<?= date('Y-m-d') ?>" required value="<?= e($old['appointment_date'] ?? '') ?>">
        </label>
        <label>Appointment time *
          <input type="time" name="appointment_time" required value="<?= e($old['appointment_time'] ?? '') ?>">
        </label>
        <label>Address
          <input type="text" name="address" maxlength="255" value="<?= e($old['address'] ?? '') ?>">
        </label>
        <label class="cc-full">Reason / notes
          <textarea name="notes" maxlength="255" rows="3"><?= e($old['notes'] ?? '') ?></textarea>
        </label>
      </div>
      <button class="cc-submit-btn" type="submit" name="book_appointment" value="1">Confirm Appointment</button>
      <p class="cc-form-footnote">Fields marked * are required. The selected time must be available for that doctor.</p>
    </form>
  </div>
</section>

<footer class="cc-footer" id="contact">
  <strong>City Care Hospital</strong>
  <span>Dhaka, Bangladesh · Emergency: 999</span>
</footer>

</body>

</html>
