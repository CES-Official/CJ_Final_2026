<?php
/**
 * COMPUDON Junior — Parental Consent OTP Configuration
 * Update these values for your GoDaddy / Zoho Mail setup.
 */

// SMTP Settings (Zoho Mail example)
define('SMTP_HOST',     'smtp.zoho.in');
define('SMTP_PORT',     465);
define('SMTP_SECURE',   'ssl');              // 'ssl' for port 465, 'tls' for 587
define('SMTP_USER',     'privacy@compudonjunior.com');
define('SMTP_PASS',     '********');         // App password — never commit to Git

// From address
define('MAIL_FROM',      'privacy@compudonjunior.com');
define('MAIL_FROM_NAME', 'COMPUDON Junior');

// OTP Settings
define('OTP_LENGTH',     6);                 // digits
define('OTP_EXPIRY_SEC', 600);               // 10 minutes
define('OTP_MAX_ATTEMPTS', 3);               // lockout after 3 wrong tries

// Consent audit log (CSV file — keep OUTSIDE web root in production)
define('CONSENT_LOG',    __DIR__ . '/../consent-audit-log.csv');

// Session name (avoids collisions with main site)
define('SESSION_NAME',   'cj_consent_otp');
