<?php
/**
 * POST /consent/send-otp.php
 * Generates a 6-digit OTP and emails it to the parent.
 * Expects JSON body: { "email": "parent@example.com", "student_name": "..." }
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../vendor/autoload.php'; // PHPMailer via Composer
// If PHPMailer is manually installed, adjust path:
// require_once __DIR__ . '/../PHPMailer/src/PHPMailer.php';
// require_once __DIR__ . '/../PHPMailer/src/SMTP.php';
// require_once __DIR__ . '/../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

// --- Validate input ---
$input = json_decode(file_get_contents('php://input'), true);
$email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$studentName = trim($input['student_name'] ?? '');

if (!$email) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Please enter a valid email address.']);
    exit;
}

// --- Generate OTP ---
$otp = str_pad(random_int(0, 999999), OTP_LENGTH, '0', STR_PAD_LEFT);

// --- Store in session ---
session_name(SESSION_NAME);
session_start();
$_SESSION['otp'] = [
    'code'       => $otp,
    'email'      => $email,
    'student'    => $studentName,
    'created_at' => time(),
    'attempts'   => 0,
    'verified'   => false,
];

// --- Send email ---
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port       = SMTP_PORT;

    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mail->addAddress($email);

    $mail->isHTML(true);
    $mail->Subject = 'COMPUDON Junior — Parental Consent Verification Code';
    $mail->Body = '
    <div style="font-family: IBM Plex Sans, Arial, sans-serif; max-width: 520px; margin: 0 auto; padding: 32px 24px;">
        <h2 style="color: #0B2545; margin-bottom: 8px;">COMPUDON Junior</h2>
        <p style="color: #1E88E5; font-size: 15px; margin-top: 0;">Student Ambassador Program — Consent Verification</p>
        <hr style="border: none; border-top: 1px solid #E2E8F0; margin: 20px 0;">
        <p style="color: #333;">Dear Parent / Guardian,</p>
        <p style="color: #333;">You are receiving this email to verify your parental consent for <strong>' . htmlspecialchars($studentName ?: 'your child') . '</strong>\'s appointment as a COMPUDON Junior Student Ambassador.</p>
        <p style="color: #333;">Your one-time verification code is:</p>
        <div style="text-align: center; margin: 24px 0;">
            <span style="font-size: 32px; font-weight: 700; letter-spacing: 8px; color: #0B2545; background: #F5F7FA; padding: 16px 32px; border-radius: 8px; border: 2px solid #E2E8F0;">' . $otp . '</span>
        </div>
        <p style="color: #666; font-size: 13px;">This code expires in 10 minutes. Do not share it with anyone.</p>
        <p style="color: #333;">If you did not request this, please ignore this email.</p>
        <hr style="border: none; border-top: 1px solid #E2E8F0; margin: 24px 0;">
        <p style="color: #999; font-size: 12px; text-align: center;">
            COMPUDON Junior • CyberLearning Educational Society (Regd.)<br>
            connect@compudonjunior.com • www.compudonjunior.com
        </p>
    </div>';
    $mail->AltBody = "COMPUDON Junior — Consent Verification\n\nYour one-time code: $otp\n\nThis code expires in 10 minutes.\n\nIf you did not request this, please ignore this email.";

    $mail->send();
    echo json_encode(['ok' => true, 'message' => 'Verification code sent to ' . substr($email, 0, 3) . '***']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not send email. Please try again shortly.']);
    error_log('OTP mail error: ' . $mail->ErrorInfo);
}
