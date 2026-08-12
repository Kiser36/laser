<?php
/**
 * Owere & Associates — booking form processor.
 * 1. Validates + sanitises the POST payload (CSRF protected)
 * 2. Inserts the inquiry into the `inquiries` table
 * 3. Sends a routed notification email via SMTP (Zoho/Google, configurable in /admin)
 * 4. Redirects back with a flash message
 *
 * The inquiry is ALWAYS saved to the database — email delivery is best-effort
 * and never blocks the user.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../index.php');
}

require_csrf();

$fullName = trim((string)($_POST['full_name'] ?? ''));
$email    = trim((string)($_POST['email'] ?? ''));
$phone    = trim((string)($_POST['phone'] ?? ''));
$service  = trim((string)($_POST['service'] ?? ''));
$company  = trim((string)($_POST['company'] ?? ''));
$message  = trim((string)($_POST['message'] ?? ''));
$dateRaw  = trim((string)($_POST['preferred_date'] ?? ''));
$redirect = (string)($_POST['redirect'] ?? 'index.php');

// Only allow relative redirects back to the site (prevents open redirects).
if ($redirect === '' || str_starts_with($redirect, 'http')) {
    $redirect = 'index.php';
}
$redirect = ltrim($redirect, '/');

$errors = [];
if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 120) {
    $errors[] = 'Please provide your full name (2–120 characters).';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
    $errors[] = 'Please provide a valid email address.';
}
if (mb_strlen($phone) < 6 || mb_strlen($phone) > 40) {
    $errors[] = 'Please provide a valid phone number.';
}
if (mb_strlen($service) < 2 || mb_strlen($service) > 100) {
    $errors[] = 'Please select the service you are enquiring about.';
}
if (mb_strlen($company) > 120) {
    $errors[] = 'Company name is too long.';
}
$preferredDate = null;
if ($dateRaw !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $dateRaw);
    if (!$dt || $dt->format('Y-m-d') !== $dateRaw) {
        $errors[] = 'The preferred date you entered is not a valid date.';
    } else {
        $preferredDate = $dateRaw;
    }
}
if (mb_strlen($message) < 10 || mb_strlen($message) > 3000) {
    $errors[] = 'Please describe your requirements (10–3000 characters).';
}

if ($errors) {
    flash('error', implode(' ', $errors));
    redirect('../' . $redirect);
}

try {
    $stmt = db()->prepare(
        'INSERT INTO inquiries (client_name, company_name, email, phone, service_requested, preferred_date, message, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $fullName,
        $company !== '' ? $company : null,
        $email,
        $phone,
        $service,
        $preferredDate,
        $message,
        'new',
    ]);

    $inquiryId = (int)db()->lastInsertId();
} catch (PDOException $e) {
    error_log('[owere] DB insert failed: ' . $e->getMessage());
    flash('error', 'We could not save your enquiry right now. Please try again or call us directly.');
    redirect('../' . $redirect);
}

/* ---------- Best-effort email notification ---------- */
$to = smtp_route_recipient($service);

$html = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;color:#1E2A3B;max-width:600px;margin:0 auto;">'
    . '<div style="background:#0B192C;color:#D4AF37;padding:24px 28px;font-size:20px;font-weight:bold;">Owere &amp; Associates</div>'
    . '<div style="padding:28px;border:1px solid #E5E0D4;border-top:0;">'
    . '<h2 style="margin-top:0;">New consultation enquiry #' . $inquiryId . '</h2>'
    . '<table cellpadding="6" style="width:100%;font-size:14px;">'
    . '<tr><td style="color:#5B6B81;width:140px;">Name</td><td><strong>' . esc($fullName) . '</strong></td></tr>'
    . '<tr><td style="color:#5B6B81;">Company</td><td>' . esc($company !== '' ? $company : '—') . '</td></tr>'
    . '<tr><td style="color:#5B6B81;">Email</td><td><a href="mailto:' . esc($email) . '">' . esc($email) . '</a></td></tr>'
    . '<tr><td style="color:#5B6B81;">Phone</td><td>' . esc($phone) . '</td></tr>'
    . '<tr><td style="color:#5B6B81;">Service</td><td>' . esc($service) . '</td></tr>'
    . ($preferredDate ? '<tr><td style="color:#5B6B81;">Preferred date</td><td>' . esc($preferredDate) . '</td></tr>' : '')
    . '</table>'
    . '<p style="margin:18px 0 6px;color:#5B6B81;font-size:12px;text-transform:uppercase;letter-spacing:1px;">Message</p>'
    . '<div style="background:#F6F4EF;padding:16px;border-radius:6px;font-size:14px;white-space:pre-wrap;">' . esc($message) . '</div>'
    . '<p style="margin-top:24px;font-size:13px;color:#5B6B81;">Manage this lead in the admin dashboard: '
    . '<a href="dashboard.php">' . esc($_SERVER['HTTP_HOST'] ?? '') . '/admin/dashboard.php</a></p>'
    . '</div></body></html>';

$mailSent = send_mail($to, "New enquiry #{$inquiryId} — {$service}", $html);

flash(
    'success',
    'Thank you, ' . esc(explode(' ', $fullName)[0]) . '. Your enquiry has been received — a partner will respond within one business day.'
);

redirect('../' . $redirect);
