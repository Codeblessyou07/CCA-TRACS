<?php
// =====================================================
// mailer.php
// Sends the temporary ID number to the user's email using PHPMailer + SMTP.
// Setup: download PHPMailer and put it in a folder named "PHPMailer"
// next to this file (so PHPMailer/src/PHPMailer.php exists).
// =====================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/src/Exception.php';
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';

// ---- EDIT THESE ----
const SMTP_HOST      = 'smtp.gmail.com';
const SMTP_PORT      = 587;
const SMTP_USER      = 'yourschoolsystem@gmail.com';   // the Gmail that sends the emails
const SMTP_PASS      = 'xxxx xxxx xxxx xxxx';          // Gmail APP PASSWORD (not your normal password)
const MAIL_FROM_NAME = 'CCA TRACS';
const MAIL_DEBUG     = true;   // shows the real email error on the signup page. Set to false when done testing.
// --------------------

/**
 * Emails the temporary ID number. Returns true if sent, false if it failed.
 */
function sendTempIdEmail(string $toEmail, string $fullName, string $idNumber): bool
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;

        $mail->setFrom(SMTP_USER, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $fullName);

        $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');

        $mail->isHTML(true);
        $mail->Subject = 'Your CCA TRACS ID Number';
        $mail->Body    = "
            <p>Hello <strong>{$safeName}</strong>,</p>
            <p>Your account has been created. Use this temporary ID number to log in:</p>
            <h2 style='letter-spacing:2px;'>{$idNumber}</h2>
            <p>Log in with this ID number and the password you created during signup.</p>
            <p>If you did not create this account, you can ignore this email.</p>
        ";
        $mail->AltBody = "Hello {$fullName}, your CCA TRACS ID number is {$idNumber}. "
                       . "Log in with this ID and the password you created during signup.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        $GLOBALS['mail_last_error'] = $mail->ErrorInfo;
        error_log('Mailer error: ' . $mail->ErrorInfo);
        return false;
    }
}