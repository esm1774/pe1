<?php
/**
 * PE Smart School - Professional Certificate Export API
 * Uses mPDF for server-side PDF generation
 */

function exportCertificate() {
    requireRole(['admin', 'teacher']);
    
    $studentId   = (int)getParam('student_id');
    $type        = getParam('type', 'appreciation');
    $orientation = getParam('orientation', 'L'); // 'L' for Landscape, 'P' for Portrait
    $action      = getParam('export_action', 'download'); // 'download' or 'email'

    if (!$studentId) jsonError('يجب تحديد الطالب');

    $db = getDB();
    
    // 1. Fetch Student Data
    $stmt = $db->prepare("
        SELECT s.*, g.name as grade_name, c.name as class_name 
        FROM students s 
        JOIN classes c ON s.class_id = c.id 
        JOIN grades g ON c.grade_id = g.id 
        WHERE s.id = ?
    ");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch();
    if (!$student) jsonError('الطالب غير موجود');

    // 2. Fetch School Data
    $schoolId = schoolId();
    $stmt = $db->prepare("SELECT * FROM schools WHERE id = ?");
    $stmt->execute([$schoolId]);
    $school = $stmt->fetch();
    $settings = json_decode($school['settings'] ?? '{}', true);

    // 3. Prepare Certificate Content based on Type
    $templates = [
        'excellence' => [
            'title' => 'شهادة تفوق رياضي',
            'titleEn' => 'Certificate of Sports Excellence',
            'body' => 'تشهد إدارة التربية البدنية بأن الطالب قد أظهر تفوقاً باهراً ومهارات استثنائية في الأنشطة الرياضية المدرسية، مما يجعله نموذجاً يحتذى به لزملائه.',
            'accent' => '#059669',
            'emoji' => '🏆'
        ],
        'appreciation' => [
            'title' => 'شهادة شكر وتقدير',
            'titleEn' => 'Certificate of Appreciation',
            'body' => 'تتقدم إدارة المدرسة بخالص الشكر والتقدير للطالب لمشاركته الفعالة وأدائه المتميز وروح التعاون التي أبداها خلال الفصل الدراسي.',
            'accent' => '#2563eb',
            'emoji' => '🌟'
        ],
        'sports_star' => [
            'title' => 'شهادة نجم الرياضة',
            'titleEn' => 'Sports Star Award',
            'body' => 'يُمنح هذا اللقب للطالب تقديراً لمواهبه الرياضية الفذة وروحه التنافسية العالية التي أثبتها في الميدان الرياضي.',
            'accent' => '#d97706',
            'emoji' => '⭐'
        ],
        'attendance' => [
            'title' => 'شهادة انضباط رياضي',
            'titleEn' => 'Sports Attendance Award',
            'body' => 'تُمنح هذه الشهادة للطالب تقديراً لانضباطه التام وحرصه المستمر على حضور حصص التربية البدنية والمشاركة الجادة فيها.',
            'accent' => '#7c3aed',
            'emoji' => '📅'
        ]
    ];

    $cert = $templates[$type] ?? $templates['appreciation'];

    // 4. Fetch Stats
    $stats = [];
    
    // Attendance
    $attPct = 0;
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status='present' THEN 1 ELSE 0 END) as present FROM attendance WHERE student_id = ?");
        $stmt->execute([$studentId]);
        $att = $stmt->fetch();
        if ($att['total'] > 0) {
            $attPct = round(($att['present'] / $att['total']) * 100);
            if ($attPct > 0) $stats[] = ['label' => 'نسبة الحضور', 'val' => $attPct . '%'];
        }
    } catch (Exception $e) {}

    // Fitness
    if (!empty($student['fitness_score']) && $student['fitness_score'] > 0) {
        $stats[] = ['label' => 'مؤشر اللياقة', 'val' => $student['fitness_score']];
    }

    // Badges
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM awarded_badges WHERE student_id = ?");
        $stmt->execute([$studentId]);
        $bc = $stmt->fetch();
        if ($bc['total'] > 0) {
            $stats[] = ['label' => 'الأوسمة', 'val' => $bc['total']];
        }
    } catch (Exception $e) {}

    // 5. Generate HTML
    $isL = $orientation === 'L';
    $logoRelUrl = $school['logo_url'] ?: '';
    $logoUrl = '';
    
    if ($logoRelUrl) {
        if (strpos($logoRelUrl, 'http') === 0) {
            $logoUrl = $logoRelUrl;
        } else {
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $base = defined('BASE_URL') ? trim(BASE_URL, '/') : '';
            $path = trim($logoRelUrl, '/');
            $logoUrl = $protocol . "://" . $host . ($base ? '/' . $base : '') . '/' . $path;
        }
    }

    $logoHtml = $logoUrl ? '<img src="' . $logoUrl . '" style="height: 60px; width: auto; max-width: 150px; object-fit: contain;">' : '<div style="font-size: 35px; color: ' . $cert['accent'] . ';">🎖️</div>';
    
    $statsHtml = '';
    if (!empty($stats)) {
        $statsHtml = '<div class="stats-section"><table align="center" style="margin: 0 auto;"><tr>';
        foreach ($stats as $s) {
            $statsHtml .= '
                <td style="padding: 0 5px;">
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px; width: 80px; text-align: center;">
                        <div style="font-size: 16px; font-weight: 900; color: ' . $cert['accent'] . ';">' . $s['val'] . '</div>
                        <div style="font-size: 8px; font-weight: bold; color: #94a3b8; text-transform: uppercase; margin-top: 2px;">' . $s['label'] . '</div>
                    </div>
                </td>';
        }
        $statsHtml .= '</tr></table></div>';
    }

    $html = '
    <html dir="rtl">
    <head>
    <style>
        @page { margin: 8mm; }
        body { font-family: "DejaVu Sans", sans-serif; color: #333; margin: 0; padding: 0; line-height: 1.2; background: #fff; }
        .cert-container { 
            border: 10px solid ' . $cert['accent'] . '; 
            padding: ' . ($isL ? '20px' : '40px 30px') . '; 
            background: #fff;
            position: relative;
            min-height: ' . ($isL ? '165mm' : '255mm') . ';
            box-sizing: border-box;
        }
        .header { width: 100%; margin-bottom: ' . ($isL ? '10px' : '30px') . '; }
        .header-table { width: 100%; }
        .header-right { text-align: right; vertical-align: top; width: 60%; }
        .header-left { text-align: left; vertical-align: top; width: 40%; }
        
        .header-text { font-weight: bold; font-size: 12px; color: #000; margin-bottom: 2px; }
        
        .title-section { text-align: center; margin-top: ' . ($isL ? '0' : '20px') . '; margin-bottom: ' . ($isL ? '10px' : '30px') . '; }
        .main-emoji { font-size: 40px; margin-bottom: 5px; color: ' . $cert['accent'] . '; }
        .title-ar { font-size: ' . ($isL ? '34px' : '42px') . '; font-weight: 900; color: #1e293b; margin: 0; }
        .title-en { font-size: 11px; font-weight: bold; color: ' . $cert['accent'] . '; text-transform: uppercase; letter-spacing: 2px; }
        
        .content-section { text-align: center; margin-bottom: ' . ($isL ? '10px' : '30px') . '; }
        .intro-text { font-size: 15px; color: #64748b; margin-bottom: 5px; }
        .student-name { font-size: ' . ($isL ? '30px' : '38px') . '; font-weight: 900; color: #0f172a; border-bottom: 2px solid ' . $cert['accent'] . '; display: inline-block; padding: 0 15px; margin-bottom: 8px; }
        .body-text { font-size: 13px; color: #475569; line-height: 1.4; max-width: 85%; margin: 0 auto; }
        
        .stats-section { margin-top: ' . ($isL ? '10px' : '30px') . '; text-align: center; }
        .stat-box { display: inline-block; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 8px 5px; width: 85px; text-align: center; }
        .stat-val { font-size: 16px; font-weight: 900; color: ' . $cert['accent'] . '; }
        .stat-lbl { font-size: 8px; font-weight: bold; color: #94a3b8; text-transform: uppercase; margin-top: 2px; }
        
        .footer { margin-top: ' . ($isL ? '15px' : '50px') . '; width: 100%; border-top: 1px solid #f1f5f9; padding-top: 15px; }
        .signature-block { margin-bottom: 5px; }
        .sig-label { font-size: 9px; color: #94a3b8; font-weight: bold; text-transform: uppercase; margin-bottom: 2px; }
        .sig-name { font-size: 13px; font-weight: 900; color: #334155; }
        
        .date-section { text-align: center; margin-top: ' . ($isL ? '10px' : '25px') . '; }
        .date-str { font-size: 9px; color: #94a3b8; font-weight: bold; border: 1px solid #f8fafc; display: inline-block; padding: 3px 8px; border-radius: 4px; }
    </style>
    </head>
    <body>
        <div class="cert-container">
            <div class="header">
                <table class="header-table">
                    <tr>
                        <td class="header-right">
                            <div class="header-text">وزارة التعليم</div>
                            <div class="header-text">' . sanitize($settings['education_dept'] ?? 'إدارة التعليم') . '</div>
                            <div class="header-text">' . sanitize($school['name']) . '</div>
                        </td>
                        <td class="header-left">
                            ' . $logoHtml . '
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="title-section">
                <div class="main-emoji">' . $cert['emoji'] . '</div>
                <h1 class="title-ar">' . $cert['title'] . '</h1>
                <p class="title-en">' . $cert['titleEn'] . '</p>
            </div>
            
            <div class="content-section">
                <p class="intro-text">تعتز إدارة التربية البدنية بمنح هذه الشهادة المتميزة للطالب:</p>
                <div class="student-name">' . sanitize($student['name']) . '</div>
                <p class="body-text">' . $cert['body'] . '</p>
            </div>
            
            ' . $statsHtml . '
            
            <div class="footer">
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td width="50%" align="right" valign="top">
                            <div class="signature-block">
                                <div class="sig-label">معلم التربية البدنية</div>
                                <div class="sig-name">' . sanitize($settings['teacher_name'] ?? 'معلم التربية البدنية') . '</div>
                            </div>
                        </td>
                        <td width="50%" align="left" valign="top">
                            <div class="signature-block">
                                <div class="sig-label">مدير المدرسة</div>
                                <div class="sig-name">' . sanitize($settings['principal_name'] ?? 'مدير المدرسة') . '</div>
                            </div>
                        </td>
                    </tr>
                </table>
                <div class="date-section">
                    <div class="date-str">' . date('Y-m-d') . '</div>
                </div>
            </div>
        </div>
    </body>
    </html>';

    // 6. Initialize mPDF
    try {
        require_once __DIR__ . '/../vendor/autoload.php';
        
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => $orientation === 'L' ? 'A4-L' : 'A4-P',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'autoArabic' => true,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'tempDir' => __DIR__ . '/../uploads/tmp_mpdf'
        ]);

        $mpdf->SetDirectionality('rtl');
        $mpdf->WriteHTML($html);

        $filename = 'Certificate_' . $student['id'] . '_' . date('Ymd') . '.pdf';

        if ($action === 'email') {
            // Fetch Parent Email
            $stmt = $db->prepare("
                SELECT p.email, p.name as parent_name 
                FROM parents p 
                JOIN parent_students ps ON p.id = ps.parent_id 
                WHERE ps.student_id = ? 
                LIMIT 1
            ");
            $stmt->execute([$studentId]);
            $parent = $stmt->fetch();
            
            if (!$parent || !$parent['email']) {
                jsonError('عذراً، لا يوجد بريد إلكتروني مسجل لولي الأمر');
            }

            $pdfContent = $mpdf->Output('', 'S');
            
            $subject = "شهادة تميز رياضي: " . $student['name'];
            $emailBody = "
                <div dir='rtl' style='font-family: sans-serif;'>
                    <h2>تحية طيبة،</h2>
                    <p>يسرنا أن نرسل لكم شهادة التميز الرياضي الخاصة بالطالب <b>{$student['name']}</b>.</p>
                    <p>مرفق مع هذه الرسالة نسخة PDF من الشهادة.</p>
                    <br>
                    <p>نظام التربية البدنية الذكي</p>
                    <p>{$school['name']}</p>
                </div>
            ";

            $sent = sendEmail($parent['email'], $subject, $emailBody, $parent['parent_name'], [
                'data' => $pdfContent,
                'filename' => $filename
            ]);

            if ($sent) {
                jsonSuccess(null, 'تم إرسال الشهادة بنجاح إلى ' . $parent['email']);
            } else {
                jsonError('فشل إرسال البريد الإلكتروني');
            }
        } else {
            // Direct Download
            $mpdf->Output($filename, 'D');
            exit;
        }

    } catch (Exception $e) {
        jsonError('خطأ أثناء إنشاء ملف PDF: ' . $e->getMessage());
    }
}
