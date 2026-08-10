<?php
/**
 * POST /consent/verify-otp.php
 * Validates the OTP and logs the verified consent.
 * Expects JSON body: { "code": "123456" }
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

// --- Restore session ---
session_name(SESSION_NAME);
session_start();

if (empty($_SESSION['otp'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No verification in progress. Please request a new code.']);
    exit;
}

$otpData = &$_SESSION['otp'];

// --- Check if already verified ---
if ($otpData['verified']) {
    echo json_encode(['ok' => true, 'message' => 'Consent already verified.', 'already' => true]);
    exit;
}

// --- Check expiry ---
if ((time() - $otpData['created_at']) > OTP_EXPIRY_SEC) {
    unset($_SESSION['otp']);
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Code expired. Please request a new one.']);
    exit;
}

// --- Check attempt limit ---
if ($otpData['attempts'] >= OTP_MAX_ATTEMPTS) {
    unset($_SESSION['otp']);
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'Too many incorrect attempts. Please request a new code.']);
    exit;
}

// --- Validate code ---
$input = json_decode(file_get_contents('php://input'), true);
$code  = trim($input['code'] ?? '');

if ($code !== $otpData['code']) {
    $otpData['attempts']++;
    $remaining = OTP_MAX_ATTEMPTS - $otpData['attempts'];
    http_response_code(400);
    echo json_encode([
        'ok'    => false,
        'error' => "Incorrect code. $remaining attempt(s) remaining.",
    ]);
    exit;
}

// --- OTP matches — log consent ---
$otpData['verified'] = true;

$logEntry = [
    date('Y-m-d H:i:s'),                              // Timestamp (IST on server)
    $otpData['email'],                                 // Parent email
    $otpData['student'],                               // Student name
    $_SERVER['REMOTE_ADDR'] ?? 'unknown',              // IP address
    $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',          // User agent
    'EMAIL_OTP_VERIFIED',                              // Verification method
    $otpData['code'],                                  // OTP used (for audit)
];

// Write header if file doesn't exist
$logFile = CONSENT_LOG;
$writeHeader = !file_exists($logFile);
$fp = fopen($logFile, 'a');
if ($fp) {
    if ($writeHeader) {
        fputcsv($fp, ['timestamp', 'parent_email', 'student_name', 'ip_address', 'user_agent', 'method', 'otp_code']);
    }
    fputcsv($fp, $logEntry);
    fclose($fp);
}

echo json_encode([
    'ok'      => true,
    'message' => 'Parental consent verified successfully.',
    'ref'     => strtoupper(substr(md5($otpData['email'] . $otpData['created_at']), 0, 8)),
]);
