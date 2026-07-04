<?php
/**
 * مساعد إرسال البريد الإلكتروني عبر SMTP - معرض الصور 2026
 */

// إذا كنت تستخدم الـ Composer، يمكنك استدعاؤه هنا، أو تضمين ملفات المكتبة يدوياً
// require_once __DIR__ . '/../vendor/autoload.php';

function sendSMTPMail($toEmail, $subject, $messageBody) {
    // جلب الإعدادات ديناميكياً من قاعدة البيانات (باستخدام دالة getSetting المتوفرة في سكربتك)
    $smtpHost     = getSetting('smtp_host', '');
    $smtpPort     = getSetting('smtp_port', '587');
    $smtpUser     = getSetting('smtp_username', '');
    $smtpPass     = getSetting('smtp_password', '');
    $smtpEnc      = getSetting('smtp_encryption', 'tls');
    $fromEmail    = getSetting('smtp_from_email', 'noreply@gallery.com');
    $fromName     = getSetting('smtp_from_name', 'Photo Gallery');

    // إذا كانت الإعدادات فارغة، يمكننا الاعتماد على دالة mail الافتراضية كخيار احتياطي
    if (empty($smtpHost) || empty($smtpUser)) {
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: <" . $fromEmail . ">" . "\r\n";
        return mail($toEmail, $subject, $messageBody, $headers);
    }

    // هنا نقوم بإنشاء كائن الـ PHPMailer (تأكد من وجود المكتبة بسيرفرك أو تضمينها)
    // لتسهيل الكود، إليك الطريقة القياسية متى ما قمت برفع مجلد PHPMailer لسكربتك:
    
    /*
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = $smtpEnc; // tls أو ssl
        $mail->Port       = $smtpPort;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $messageBody;

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
    */
    
    // كود مدمج وسريع للإرسال كبديل مؤقت لحين تثبيت PHPMailer بالكامل
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: {$fromName} <{$fromEmail}>" . "\r\n";
    return mail($toEmail, $subject, $messageBody, $headers);
}