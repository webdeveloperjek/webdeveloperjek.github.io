
<?php
// contact.php
// REQUIREMENTS: PHPMailer via Composer: composer require phpmailer/phpmailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

// ---------- CONFIG ----------
$toEmail       = 'jericyambot134@gmail.com';   // where the form sends to (you)
$siteName      = 'Same Day Printing';          // used in email subjects/senders
$fromEmail     = 'jericyambot134@gmail.com'; // use your domain to pass SPF/DMARC
$smtpHost      = 'smtp.gmail.com';             // or your host's SMTP
$smtpUsername  = 'jericyambot134@gmail.com';   // SMTP username (Gmail address or SMTP user)
$smtpPassword  = 'YOUR_APP_PASSWORD_HERE';     // if Gmail, use an App Password (2FA required)
$smtpPort      = 587;                          // 587 (TLS) or 465 (SSL)
$smtpSecure    = PHPMailer::ENCRYPTION_STARTTLS; // or PHPMailer::ENCRYPTION_SMTPS

// ---------- HELPERS ----------
function clean($v) {
  return trim(filter_var($v, FILTER_SANITIZE_FULL_SPECIAL_CHARS));
}
function bad_request($msg, $code = 400) {
  http_response_code($code);
  echo $msg;
  exit;
}

// ---------- VALIDATION ----------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  bad_request('Invalid request method.', 405);
}

// Honeypot trap
if (!empty($_POST['website'])) {
  bad_request('Spam detected.', 400);
}

$name    = isset($_POST['name'])    ? clean($_POST['name'])    : '';
$email   = isset($_POST['email'])   ? filter_var($_POST['email'], FILTER_SANITIZE_EMAIL) : '';
$subject = isset($_POST['subject']) ? clean($_POST['subject']) : '';
$message = isset($_POST['message']) ? trim($_POST['message'])  : '';

if ($name === '' || $subject === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  bad_request('Please complete all fields with a valid email.');
}

// ---------- BUILD EMAILS ----------
$ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ua   = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
$time = date('Y-m-d H:i:s');

$adminBody = <<<HTML
<h2>New Contact Form Submission</h2>
<p><strong>Name:</strong> {$name}</p>
<p><strong>Email:</strong> {$email}</p>
<p><strong>Subject:</strong> {$subject}</p>
<p><strong>Message:</strong><br>
<pre style="white-space:pre-wrap;font-family:inherit;">{$message}</pre></p>
<hr>
<p style="font-size:12px;color:#666;">IP: {$ip}<br>User Agent: {$ua}<br>Time: {$time}</p>
HTML;

$userBody = <<<HTML
<p>Hi {$name},</p>
<p>Thanks for contacting {$siteName}. We’ve received your message and will get back to you soon.</p>
<hr>
<p><strong>Copy of your message:</strong></p>
<p><em>Subject:</em> {$subject}</p>
<p><pre style="white-space:pre-wrap;font-family:inherit;">{$message}</pre></p>
<p>Regards,<br>{$siteName} Team</p>
HTML;

// ---------- SEND (SMTP) ----------
try {
  // 1) Send to admin
  $mail = new PHPMailer(true);
  $mail->isSMTP();
  $mail->Host       = $smtpHost;
  $mail->SMTPAuth   = true;
  $mail->Username   = $smtpUsername;
  $mail->Password   = $smtpPassword;
  $mail->SMTPSecure = $smtpSecure;
  $mail->Port       = $smtpPort;

  // Use your domain as From for better deliverability; Reply-To is the user
  $mail->setFrom($fromEmail, $siteName);
  $mail->addAddress($toEmail);
  $mail->addReplyTo($email, $name);

  $mail->isHTML(true);
  $mail->Subject = "New message via Contact Form: {$subject}";
  $mail->Body    = $adminBody;
  $mail->AltBody = strip_tags("Name: {$name}\nEmail: {$email}\nSubject: {$subject}\n\n{$message}");

  $mail->send();

  // 2) Confirmation to user
  $confirm = new PHPMailer(true);
  $confirm->isSMTP();
  $confirm->Host       = $smtpHost;
  $confirm->SMTPAuth   = true;
  $confirm->Username   = $smtpUsername;
  $confirm->Password   = $smtpPassword;
  $confirm->SMTPSecure = $smtpSecure;
  $confirm->Port       = $smtpPort;

  $confirm->setFrom($fromEmail, $siteName);
  $confirm->addAddress($email, $name);
  $confirm->isHTML(true);
  $confirm->Subject = "{$siteName}: we received your message";
  $confirm->Body    = $userBody;
  $confirm->AltBody = strip_tags("Hi {$name},\n\nThanks for contacting {$siteName}. We’ve received your message.\n\nSubject: {$subject}\n\n{$message}\n\nRegards,\n{$siteName} Team");

  $confirm->send();

  // If your front-end expects a simple message:
  echo 'OK';
} catch (Exception $e) {
  http_response_code(500);
  echo 'Mailer Error: ' . htmlspecialchars($e->getMessage());
}
