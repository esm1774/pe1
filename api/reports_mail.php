<?php
/**
 * PE Smart School System - Report Emailer API
 * ===========================================
 * إرسال التقارير (بصيغة PDF) إلى البريد الإلكتروني للمستخدم
 */

function sendReportEmail() {
    requireLogin();
    
    $data = getPostData();
    validateRequired($data, ['email', 'pdfData']);
    
    $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
    if (!$email) {
        jsonError('البريد الإلكتروني غير صالح');
    }
    
    $pdfData = $data['pdfData'];
    $title = isset($data['title']) ? sanitize($data['title']) : 'تقرير الأداء البدني والرياضي';

    // Fix: Limit PDF size (max 5MB base64 = ~6.8MB raw)
    if (strlen($pdfData) > 7 * 1024 * 1024) {
        jsonError('حجم التقرير كبير جداً. الحد الأقصى 5 ميجابايت');
    }

    // Clean base64 string
    // Format expected: "data:application/pdf;base64,JVBERi..."
    if (strpos($pdfData, 'data:application/pdf;base64,') === 0) {
        $pdfData = substr($pdfData, strpos($pdfData, ',') + 1);
    }

    $decodedPdf = base64_decode($pdfData);
    if ($decodedPdf === false) {
        jsonError('بيانات الملف التقرير (PDF) تالفة أو غير صالحة');
    }

    $to = $email;
    $subject = $title . " - PE Smart School";
    $message = "مرحباً،\n\nتجدون بالخلف المرفق: $title مدمجاً في هذه الرسالة، شكراً لاستخدامكم منصتنا.\n\nمنصة PE Smart School System.";
    $boundary = md5(time());
    $fromName = "PE Smart School";
    
    // In shared hosting, 'noreply@yourdomain.com' must be used to send emails without getting rejected.
    $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'pesmart.school.local';
    // Remove port if exists
    $domain = explode(':', $domain)[0];
    
    $fromEmail = "noreply@" . $domain;
    
    $headers  = "From: $fromName <$fromEmail>\r\n";
    $headers .= "Reply-To: $fromEmail\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
    
    $body  = "--$boundary\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $message . "\r\n\r\n";
    
    $body .= "--$boundary\r\n";
    $body .= "Content-Type: application/pdf; name=\"report.pdf\"\r\n";
    $body .= "Content-Disposition: attachment; filename=\"report.pdf\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split($pdfData) . "\r\n\r\n";
    $body .= "--$boundary--\r\n";
    
    // Simulate email success on local development when DEBUG_MODE is active
    if (defined('DEBUG_MODE') && DEBUG_MODE && in_array($_SERVER['HTTP_HOST'], ['localhost', '127.0.0.1'])) {
        sleep(1); // Simulate work
        jsonSuccess(['email' => $to], 'تم تكوين وإرسال الـ PDF بنجاح عبر البريد الإلكتروني (وضع المطور).');
    } else {
        $mailSent = @mail($to, $subject, $body, $headers);
        if ($mailSent) {
            jsonSuccess(null, 'تم إرسال التقرير بنجاح عبر البريد الإلكتروني.');
        } else {
            jsonError('فشل في إرسال البريد الإلكتروني. يرجى التأكد من إعدادات البريد في خادم الاستضافة الخاص بك.');
        }
    }
}
