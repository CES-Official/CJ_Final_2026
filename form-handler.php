<?php
/**
 * ═══════════════════════════════════════════════════════════════
 * FORM HANDLER — COMPUDON Junior
 * Verifies reCAPTCHA v3, then sends:
 *   1. Notification email  → support-team@compudonjunior.co.in
 *   2. Auto-acknowledgement → the person who submitted the form
 * ═══════════════════════════════════════════════════════════════
 *
 * DEVELOPER SETUP:
 * 1. Upload this file to the site (e.g. /form-handler.php)
 * 2. This file must run on a server with PHP + the mail() function
 *    enabled (standard on almost all shared hosting — GoDaddy,
 *    Hostinger, Bluehost etc. all support this by default)
 * 3. The SECRET_KEY below stays on the server ONLY — never expose it
 *    in any HTML/JS file. Ideally move it to an environment variable
 *    or a separate config file outside the public web root once live.
 * 4. Point every form's "action" attribute to this file's URL.
 * ═══════════════════════════════════════════════════════════════
 */

// ── Configuration ──────────────────────────────────────────────
define('RECAPTCHA_SECRET_KEY', '6LdXvlYtAAAAABfO3OtKzTQVRzQazyNx89L9OM8Y');
define('RECAPTCHA_SCORE_THRESHOLD', 0.5); // 0.0 = definitely bot, 1.0 = definitely human
define('NOTIFY_EMAIL', 'support-team@compudonjunior.co.in');
define('SITE_NAME', 'COMPUDON Junior');
define('FROM_EMAIL', 'no-reply@compudonjunior.com'); // must be a domain you control

// ── Only accept POST requests ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed');
}

// ── Step 1: Verify reCAPTCHA token with Google ─────────────────
$token = $_POST['recaptcha_token'] ?? '';

if (empty($token)) {
    http_response_code(400);
    die('Missing verification token. Please try submitting again.');
}

$verify_url = 'https://www.google.com/recaptcha/api/siteverify';
$verify_data = [
    'secret'   => RECAPTCHA_SECRET_KEY,
    'response' => $token,
    'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
];

$ch = curl_init($verify_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($verify_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

// ── Step 2: Reject if verification failed or score too low ─────
if (!$result || !$result['success'] || ($result['score'] ?? 0) < RECAPTCHA_SCORE_THRESHOLD) {
    http_response_code(403);
    die('We could not verify this submission. Please try again — if this keeps happening, contact us directly at support-team@compudonjunior.co.in.');
}

// ── Step 3: Basic honeypot check (optional extra layer) ────────
// Add a hidden field named "website" to your forms that real users
// never see or fill (via CSS display:none). Bots often fill every field.
if (!empty($_POST['website'])) {
    // Silently pretend success — don't tip off the bot
    http_response_code(200);
    die('Thank you for your submission.');
}

// ── Step 4: Basic rate limiting (simple file-based, per IP) ─────
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rate_limit_file = sys_get_temp_dir() . '/cj_ratelimit_' . md5($ip) . '.txt';
$rate_limit_window = 3600; // 1 hour
$rate_limit_max = 5;       // max 5 submissions per hour per IP

$submissions = [];
if (file_exists($rate_limit_file)) {
    $submissions = json_decode(file_get_contents($rate_limit_file), true) ?: [];
    $submissions = array_filter($submissions, function ($ts) use ($rate_limit_window) {
        return (time() - $ts) < $rate_limit_window;
    });
}

if (count($submissions) >= $rate_limit_max) {
    http_response_code(429);
    die('Too many submissions from this connection. Please try again later, or email us directly at support-team@compudonjunior.co.in.');
}

$submissions[] = time();
file_put_contents($rate_limit_file, json_encode($submissions));

// ── Step 5: Collect form fields (excluding internal fields) ────
$form_name = htmlspecialchars($_POST['form_name'] ?? 'Website Form');
$exclude_fields = ['recaptcha_token', 'website', 'form_name'];

$field_rows = '';
$submitter_email = '';
$submitter_name = '';

foreach ($_POST as $key => $value) {
    if (in_array($key, $exclude_fields)) continue;

    $clean_key = htmlspecialchars(ucwords(str_replace('_', ' ', $key)));
    $clean_value = htmlspecialchars(is_array($value) ? implode(', ', $value) : $value);
    $field_rows .= "<tr><td style='padding:8px 12px;font-weight:600;color:#1F3A5F;border-bottom:1px solid #eee;'>{$clean_key}</td><td style='padding:8px 12px;border-bottom:1px solid #eee;'>{$clean_value}</td></tr>";

    // Try to auto-detect an email field for the acknowledgement
    if (stripos($key, 'email') !== false && filter_var($value, FILTER_VALIDATE_EMAIL)) {
        $submitter_email = $value;
    }
    // Try to auto-detect a name field for personalising the acknowledgement
    if (stripos($key, 'name') !== false && empty($submitter_name)) {
        $submitter_name = $value;
    }
}

// ── Step 6: Send notification email to the support team ────────
$notify_subject = "New {$form_name} Submission — " . SITE_NAME;

$notify_body = "
<html><body style='font-family:Arial,sans-serif;color:#333;'>
<div style='max-width:600px;margin:0 auto;'>
  <div style='background:#1F3A5F;padding:20px;text-align:center;'>
    <h2 style='color:#fff;margin:0;'>New Form Submission</h2>
  </div>
  <div style='padding:20px;background:#F4F1EC;'>
    <p><strong>Form:</strong> {$form_name}</p>
    <p><strong>Submitted:</strong> " . date('d M Y, h:i A') . "</p>
    <p><strong>IP Address:</strong> {$ip}</p>
    <p><strong>Spam Score:</strong> " . htmlspecialchars($result['score'] ?? 'N/A') . " (1.0 = human, 0.0 = bot)</p>
  </div>
  <table style='width:100%;border-collapse:collapse;margin-top:10px;'>
    {$field_rows}
  </table>
</div>
</body></html>
";

$notify_headers = "MIME-Version: 1.0\r\n";
$notify_headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$notify_headers .= "From: " . SITE_NAME . " <" . FROM_EMAIL . ">\r\n";
if (!empty($submitter_email)) {
    $notify_headers .= "Reply-To: {$submitter_email}\r\n";
}

$notify_sent = mail(NOTIFY_EMAIL, $notify_subject, $notify_body, $notify_headers);

// ── Step 7: Send auto-acknowledgement to the submitter ──────────
$ack_sent = false;

if (!empty($submitter_email)) {
    $greeting = !empty($submitter_name) ? htmlspecialchars($submitter_name) : 'there';

    $ack_subject = "We've received your submission — " . SITE_NAME;

    $ack_body = "
    <html><body style='font-family:Arial,sans-serif;color:#333;'>
    <div style='max-width:600px;margin:0 auto;'>
      <div style='background:#1F3A5F;padding:24px;text-align:center;'>
        <h2 style='color:#fff;margin:0;'>" . SITE_NAME . "</h2>
      </div>
      <div style='padding:24px;'>
        <p>Hi {$greeting},</p>
        <p>Thank you for your submission to <strong>{$form_name}</strong>. We've received it and our team will review it shortly.</p>
        <p>If you have any urgent questions in the meantime, feel free to reach us directly at <a href='mailto:support-team@compudonjunior.co.in' style='color:#2C7A7B;'>support-team@compudonjunior.co.in</a>.</p>
        <p style='margin-top:24px;'>Warm regards,<br><strong>The COMPUDON Junior Team</strong></p>
      </div>
      <div style='background:#F4F1EC;padding:16px;text-align:center;font-size:12px;color:#777;'>
        CyberLearning Educational Society (Regd.) &middot; Powered by a Microsoft Global Training Partner
      </div>
    </div>
    </body></html>
    ";

    $ack_headers = "MIME-Version: 1.0\r\n";
    $ack_headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $ack_headers .= "From: " . SITE_NAME . " <" . FROM_EMAIL . ">\r\n";

    $ack_sent = mail($submitter_email, $ack_subject, $ack_body, $ack_headers);
}

// ── Step 8: Respond to the browser ───────────────────────────────
// Redirect to a thank-you page, or return a simple success message.
// Adjust this to redirect to a proper thank-you.html page if you have one.

if ($notify_sent) {
    // Option A: redirect to a thank-you page (recommended)
    // header('Location: /thank-you.html');
    // exit;

    // Option B: simple inline confirmation
    echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Thank You — " . SITE_NAME . "</title></head>";
    echo "<body style='font-family:Arial,sans-serif;text-align:center;padding:60px 20px;background:#F4F1EC;'>";
    echo "<h2 style='color:#1F3A5F;'>Thank you!</h2>";
    echo "<p style='color:#555;'>Your submission has been received. " . ($ack_sent ? "A confirmation email is on its way to you." : "") . "</p>";
    echo "<a href='/' style='color:#2C7A7B;font-weight:600;'>Return to homepage</a>";
    echo "</body></html>";
} else {
    http_response_code(500);
    echo "Something went wrong sending your submission. Please try again or email us directly at support-team@compudonjunior.co.in.";
}
