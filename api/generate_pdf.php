<?php
require_once '../vendor/autoload.php';
require_once '../config.php';

// Debug Logging Function
function pdf_log($msg) {
    file_put_contents(__DIR__ . '/pdf_debug.log', date('[Y-m-d H:i:s] ') . $msg . PHP_EOL, FILE_APPEND);
}

// 1. Log Request
$json = file_get_contents('php://input');
pdf_log("--- NEW REQUEST ---");
pdf_log("Request URI: " . $_SERVER['REQUEST_URI']);
pdf_log("Remote IP: " . $_SERVER['REMOTE_ADDR']);
pdf_log("JSON Payload length: " . strlen($json));

// Security: Check Auth
$user = requireLogin();
pdf_log("Auth Success. User: " . $user['name']);

$data = json_decode($json, true);
if (!$data) {
    pdf_log("JSON Decode Failed! Raw content: " . substr($json, 0, 100));
    http_response_code(400);
    exit('Invalid JSON');
}

$type = $data['type'] ?? 'unknown';
pdf_log("Request Decoded. Type: " . $type);
$reportData = $data['data'] ?? [];
$filename = ($data['filename'] ?? 'Report') . '.pdf';

try {
    // Check Environment
    if (!extension_loaded('gd')) pdf_log("WARNING: GD extension NOT loaded");
    if (!extension_loaded('mbstring')) pdf_log("WARNING: mbstring extension NOT loaded");

    // Check School data
    $schoolId = $_SESSION['school_id'] ?? null;
    pdf_log("Session School ID: " . ($schoolId ?? 'NULL'));
    
    $school = null;
    if ($schoolId) {
        $stmt = getDB()->prepare("SELECT name, logo_url FROM schools WHERE id = ?");
        $stmt->execute([$schoolId]);
        $school = $stmt->fetch();
    }
    $schoolName = $school['name'] ?? 'مدرستنا الذكية';
    $logoUrl = $school['logo_url'] ?? '';

    // Initialize mPDF
    $config = [
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 10,
        'margin_bottom' => 10,
        'direction' => 'rtl',
        'autoArabic' => true,
        'default_font' => 'xbriyaz',
        'tempDir' => __DIR__ . '/tmp'
    ];
    
    if ($type === 'monitoring' || $type === 'grading') $config['format'] = 'A4-L';

    $mpdf = new \Mpdf\Mpdf($config);

    // Build HTML
    $html = _buildPdfHtmlByReportType($type, $reportData, $schoolName, $logoUrl);
    pdf_log("HTML Build Success. Length: " . strlen($html));

    $mpdf->WriteHTML($html);
    pdf_log("mPDF Write Success.");

    if (ob_get_length()) ob_clean();

    // 5. Handle Email or Browser Download
    $recipient = $data['recipient_email'] ?? null;
    if ($recipient) {
        pdf_log("Sending PDF via email to: " . $recipient);
        $pdfContent = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
        
        $subject = "تقرير مدرسي: " . str_replace('.pdf', '', $filename);
        $body = "<h3>تحية طيبة،</h3><p>مرفق لكم التقرير المطلوب من نظام PE Smart School.</p><p>خالص التحيات، <br>إدارة المدرسة</p>";
        
        $sent = sendEmail($recipient, $subject, $body, '', [
            'data' => $pdfContent,
            'filename' => $filename
        ]);
        
        if ($sent) {
            pdf_log("Email Sent Successfully.");
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'message' => 'تم إرسال التقرير بنجاح إلى ' . $recipient]);
        } else {
            pdf_log("Email Failed to send.");
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'فشل إرسال البريد الإلكتروني. تأكد من إعدادات SMTP في config.php']);
        }
        exit;
    }

    $mpdf->Output($filename, 'I');
    pdf_log("PDF Output Success.");

} catch (\Exception $e) {
    pdf_log("CRITICAL ERROR: " . $e->getMessage());
    pdf_log("Trace: " . $e->getTraceAsString());
    http_response_code(500);
    exit('Error: ' . $e->getMessage());
}

/** Router for builder functions */
function _buildPdfHtmlByReportType($type, $data, $schoolName, $logoUrl) {
    $content = '';
    switch ($type) {
        case 'student':
            $content = _buildStudentReport($data, $schoolName, $logoUrl);
            break;
        case 'class':
            $content = _buildClassReport($data, $schoolName, $logoUrl);
            break;
        case 'compare':
            $content = _buildCompareReport($data, $schoolName, $logoUrl);
            break;
        case 'monitoring':
            $content = _buildMonitoringReport($data, $schoolName, $logoUrl);
            break;
        case 'grading':
            $content = _buildGradingReport($data, $schoolName, $logoUrl);
            break;
        case 'progress_report':
            $content = _buildProgressReport($data, $schoolName, $logoUrl);
            break;
        default:
            $content = '<h1>نوع التقرير غير مدعوم حالياً</h1>';
    }
    return _wrapPdfPage($content, ($type === 'monitoring'));
}

/** Wraps content in base styles */
function _wrapPdfPage($content, $isLandscape = false) {
    ob_start();
    ?>
    <style>
        body { font-family: 'xbriyaz', sans-serif; direction: rtl; text-align: right; color: #111827; }
        .pdf-header { border-bottom: 3px double #d1d5db; padding-bottom: 15px; margin-bottom: 25px; width: 100%; }
        .pdf-header td { border: none; vertical-align: middle; padding: 0; }
        .ministry-text { font-size: 11px; font-weight: bold; line-height: 1.5; text-align: right; }
        .school-info { text-align: center; font-size: 18px; font-weight: bold; }
        .report-title { font-size: 22px; font-weight: 900; text-align: center; margin: 20px 0; color: #1e3a8a; }
        
        .info-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 15px; margin-bottom: 20px; }
        .info-grid { width: 100%; }
        .info-grid td { border: none; text-align: right; padding: 4px; font-size: 14px; }
        
        table.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; border: 1px solid #e2e8f0; }
        table.data-table th { background-color: #f1f5f9; color: #334155; font-weight: bold; padding: 8px; border: 1px solid #e2e8f0; font-size: 12px; }
        table.data-table td { padding: 8px; border: 1px solid #e2e8f0; text-align: center; font-size: 12px; }
        
        .progress-container { background: #e2e8f0; border-radius: 5px; width: 80px; height: 10px; display: inline-block; position: relative; }
        .progress-bar { height: 100%; border-radius: 5px; }
        
        .pdf-pill { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; }
        .pill-blue { background: #eff6ff; color: #1e40af; }
        .pill-green { background: #f0fdf4; color: #166534; }
        .pill-red { background: #fef2f2; color: #991b1b; }
        
        /* Landscape specific adjustments */
        <?php if ($isLandscape): ?>
        table.data-table th, table.data-table td { padding: 4px; font-size: 10px; }
        <?php endif; ?>

        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 10px; color: #94a3b8; padding: 10px 0; }
    </style>
    <div class="pdf-body">
        <?php echo $content; ?>
        <div class="footer">نظام PE Smart School - الخبير الرياضي @ <?php echo date('Y'); ?></div>
    </div>
    <?php
    return ob_get_clean();
}

/** Professional Header Builder */
function _pdfHeaderHtml($schoolName, $logoUrl) {
    $logoPath = '';
    if ($logoUrl) {
        $logoPath = dirname(__DIR__) . '/' . $logoUrl;
    }

    $html = '<table class="pdf-header"><tr>';
    $html .= '<td width="30%" class="ministry-text">المملكة العربية السعودية<br>وزارة التعليم<br>إدارة التربية والتعليم</td>';
    $html .= '<td width="40%" class="school-info">' . htmlspecialchars($schoolName) . '<br><span style="font-size:12px; font-weight:normal;">التاريخ: ' . date('Y/m/d') . '</span></td>';
    $html .= '<td width="30%" style="text-align:left;">' . ($logoPath && file_exists($logoPath) ? '<img src="'.$logoPath.'" height="60">' : '') . '</td>';
    $html .= '</tr></table>';
    return $html;
}

/** 1. Student Report */
function _buildStudentReport($data, $schoolName, $logoUrl) {
    $student = $data['student'] ?? [];
    $results = $data['fitness'] ?? []; // Use 'fitness' instead of 'results'
    
    $html = _pdfHeaderHtml($schoolName, $logoUrl);
    $html .= '<div class="report-title">تقرير مستوى الطالب الفردي</div>';
    
    $html .= '<div class="info-box">';
    $html .= '<table class="info-grid"><tr>';
    $html .= '<td><strong>اسم الطالب:</strong> ' . htmlspecialchars($student['name'] ?? '-') . '</td>';
    // Match 'full_class_name' from JS
    $html .= '<td><strong>الصف/الفصل:</strong> ' . htmlspecialchars($student['full_class_name'] ?? '-') . '</td>';
    $html .= '</tr><tr>';
    $html .= '<td><strong>الرقم الأكاديمي:</strong> ' . htmlspecialchars($student['student_number'] ?? '-') . '</td>';
    $html .= '<td><strong>العمر:</strong> ' . htmlspecialchars($student['age'] ?? '-') . ' سنة</td>';
    $html .= '</tr></table>';
    $html .= '</div>';
    
    $html .= '<table class="data-table">';
    $html .= '<thead><tr><th>المجال / الاختبار</th><th>النتيجة</th><th>المستوى</th><th>التقييم البياني</th></tr></thead>';
    $html .= '<tbody>';
    foreach ($results as $res) {
        $score = floatval($res['score'] ?? 0);
        $max = floatval($res['max_score'] ?? 10);
        $pct = ($max > 0) ? ($score / $max) * 100 : 0;
        
        $color = '#3b82f6';
        if ($pct >= 90) $color = '#10b981';
        elseif ($pct < 50) $color = '#ef4444';
        
        $html .= '<tr>';
        $html .= '<td style="text-align:right;">' . htmlspecialchars($res['test_name']) . '</td>';
        $html .= '<td>' . htmlspecialchars($res['value'] ?? '-') . ' ' . htmlspecialchars($res['unit'] ?? '') . '</td>';
        $html .= '<td><strong>' . $score . ' / ' . $max . '</strong></td>';
        $html .= '<td><div class="progress-container"><div class="progress-bar" style="width:'.$pct.'%; background-color:'.$color.';"></div></div></td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    
    // 3. Grading Summary (if exists)
    if (isset($data['grading_summary'])) {
        $gs = $data['grading_summary'];
        $html .= '<div style="margin-top:20px;">';
        $html .= '<h4 style="color:#4f46e5; border-bottom:1px solid #e2e8f0; padding-bottom:5px;">التقييم الشامل والتقدير النهائي: ' . ($gs['letter'] ?? '-') . '</h4>';
        $html .= '<table class="info-grid">';
        $html .= '<tr><td>الحضور: '.$gs['attendance_pct'].'%</td><td>الزي: '.$gs['uniform_pct'].'%</td><td>السلوك: '.$gs['behavior_skills_pct'].'%</td><td>اللياقة: '.$gs['fitness_pct'].'%</td></tr>';
        $html .= '<tr><td>المشاركة: '.$gs['participation_pct'].'%</td><td>اختبار: '.$gs['quiz_score'].'</td><td>مشروع: '.$gs['project_score'].'</td><td>نهائي: '.$gs['final_exam_score'].'</td></tr>';
        $html .= '</table></div>';
    }

    // 4. Attendance Stats
    if (isset($data['attendance'])) {
        $att = $data['attendance'];
        $html .= '<div style="margin-top:15px; background:#f0fdf4; padding:10px; border-radius:10px;">';
        $html .= '<span style="font-weight:bold; color:#166534;">إحصائيات الحضور: </span>';
        $html .= 'حضور: ' . ($att['present'] ?? 0) . ' | غياب: ' . ($att['absent'] ?? 0) . ' | تأخر: ' . ($att['late'] ?? 0);
        $html .= '</div>';
    }
    
    return $html;
}

/** 2. Class Report */
function _buildClassReport($data, $schoolName, $logoUrl) {
    $html = _pdfHeaderHtml($schoolName, $logoUrl);
    $html .= '<div class="report-title">مسرد نتائج الصف (' . htmlspecialchars($data['class']['full_name'] ?? '') . ')</div>';
    
    $html .= '<table class="data-table">';
    $html .= '<thead><tr><th>م</th><th>اسم الطالب</th><th>المعدل</th><th>التقدير</th><th>BMI</th><th>الحضور</th></tr></thead>';
    $html .= '<tbody>';
    foreach (($data['students'] ?? []) as $index => $s) {
        $html .= '<tr>';
        $html .= '<td>' . ($index + 1) . '</td>';
        $html .= '<td style="text-align:right;">' . htmlspecialchars($s['name']) . '</td>';
        $html .= '<td>' . htmlspecialchars($s['percentage']) . '%</td>';
        $html .= '<td>' . htmlspecialchars($s['letter'] ?? '-') . '</td>';
        $html .= '<td>' . htmlspecialchars($s['latest_bmi'] ?? '-') . '</td>';
        $html .= '<td>' . htmlspecialchars($s['present_count'] ?? '0') . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    return $html;
}

/** 3. Compare Report */
function _buildCompareReport($data, $schoolName, $logoUrl) {
    $classes = $data['classes'] ?? [];
    $html = _pdfHeaderHtml($schoolName, $logoUrl);
    $html .= '<div class="report-title">تحليل مقارنة أداء الفصول</div>';

    $html .= '<table class="data-table">';
    $html .= '<thead><tr><th>الترتيب</th><th>الفصل</th><th>عدد الطلاب</th><th>المتوسط العام</th><th>مستوى الأداء</th></tr></thead>';
    $html .= '<tbody>';
    foreach ($classes as $index => $c) {
        $html .= '<tr>';
        $html .= '<td>' . ($index + 1) . '</td>';
        $html .= '<td style="text-align:right;">' . htmlspecialchars($c['class_name']) . '</td>';
        $html .= '<td>' . htmlspecialchars($c['students_count']) . '</td>';
        $html .= '<td>' . htmlspecialchars($c['percentage']) . '%</td>';
        $html .= '<td>' . htmlspecialchars($c['rating'] ?? '-') . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    return $html;
}

/** 4. Monitoring Report (Landscape) */
function _buildMonitoringReport($data, $schoolName, $logoUrl) {
    $students = $data['students'] ?? [];
    $dates = $data['dates'] ?? [];
    $matrix = $data['matrix'] ?? [];
    
    $html = _pdfHeaderHtml($schoolName, $logoUrl);
    $html .= '<div class="report-title" style="margin:10px 0;">سجل المتابعة اليومي - ' . htmlspecialchars($data['class']['full_name'] ?? '') . '</div>';
    
    $html .= '<table class="data-table" style="font-size:8px;">';
    $html .= '<thead><tr><th rowspan="2">م</th><th rowspan="2">اسم الطالب</th>';
    foreach($dates as $d) {
        $html .= '<th colspan="3">' . $d . '</th>';
    }
    $html .= '</tr><tr>';
    foreach($dates as $d) {
        $html .= '<th>ح</th><th>ز</th><th>م</th>';
    }
    $html .= '</tr></thead><tbody>';
    
    foreach ($students as $index => $s) {
        $html .= '<tr>';
        $html .= '<td>' . ($index + 1) . '</td>';
        $html .= '<td style="text-align:right; font-weight:bold; white-space:nowrap;">' . htmlspecialchars($s['name']) . '</td>';
        foreach ($dates as $d) {
            $m = $matrix[$s['id']][$d] ?? null;
            $att = $m ? ($m['status'] == 'present' ? '✓' : 'غ') : '-';
            $uni = $m ? ($m['uniform'] == 'full' ? '✓' : 'X') : '-';
            $part = $m ? ($m['participation'] ?? '-') : '-';
            $html .= '<td>' . $att . '</td><td>' . $uni . '</td><td>' . $part . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    return $html;
}

/** 5. Grading Report */
function _buildGradingReport($data, $schoolName, $logoUrl) {
    $weights = $data['weights'] ?? [];
    $students = $data['students'] ?? [];
    
    $html = _pdfHeaderHtml($schoolName, $logoUrl);
    $html .= '<div class="report-title">كشف الدرجات النهائي للتربية البدنية</div>';
    $html .= '<div style="text-align:center; font-size:12px; margin-bottom:10px;">الفصل: ' . htmlspecialchars($data['className'] ?? '') . ' | الفترة: ' . $data['start'] . ' إلى ' . $data['end'] . '</div>';
    
    $html .= '<table class="data-table">';
    $html .= '<thead><tr>';
    $html .= '<th width="3%">م</th>';
    $html .= '<th width="22%">اسم الطالب</th>';
    $html .= '<th width="7.5%">حضور</th>';
    $html .= '<th width="7.5%">زي</th>';
    $html .= '<th width="7.5%">سلوك</th>';
    $html .= '<th width="7.5%">مشاركة</th>';
    $html .= '<th width="7.5%">لياقة</th>';
    $html .= '<th width="7.5%">اختبار قصير</th>';
    $html .= '<th width="7.5%">بحوث / مشاريع</th>';
    $html .= '<th width="7.5%">اختبار نهائي</th>';
    $html .= '<th width="7.5%">المجموع</th>';
    $html .= '<th width="7.5%">التقدير</th>';
    $html .= '</tr></thead>';
    $html .= '<tbody>';
    foreach ($students as $index => $s) {
        $html .= '<tr>';
        $html .= '<td>' . ($index + 1) . '</td>';
        $html .= '<td style="text-align:right; font-weight:bold;">' . htmlspecialchars($s['name']) . '</td>';
        $html .= '<td>' . ($s['attendance_score'] ?? '0') . '</td>';
        $html .= '<td>' . ($s['uniform_score'] ?? '0') . '</td>';
        $html .= '<td>' . ($s['behavior_skills_score'] ?? '0') . '</td>'; // Use behavior_skills as "Behavior"
        $html .= '<td>' . ($s['participation_score'] ?? '0') . '</td>';
        $html .= '<td>' . ($s['fitness_score'] ?? '0') . '</td>';
        $html .= '<td>' . ($s['quiz_score'] ?? '0') . '</td>';
        $html .= '<td>' . ($s['project_score'] ?? '0') . '</td>';
        $html .= '<td>' . ($s['final_exam_score'] ?? '0') . '</td>';
        $html .= '<td style="background:#fefce8; font-weight:bold;">' . ($s['final_grade'] ?? '0') . '</td>';
        $html .= '<td>' . ($s['letter'] ?? '-') . '</td>';
        $html .= '</tr>';
    }
    $html .= '</tbody></table>';
    return $html;
}

/** 6. Progress Report */
function _buildProgressReport($data, $schoolName, $logoUrl) {
    $student = $data['student'] ?? [];
    $hMeas = $data['measurements'] ?? [];
    $hFit = $data['fitnessHistory'] ?? [];
    
    $html = _pdfHeaderHtml($schoolName, $logoUrl);
    $html .= '<div class="report-title">التقرير التراكمي: مسار وتطور اللياقة والنمو</div>';
    
    $html .= '<div class="info-box">';
    $html .= '<table class="info-grid"><tr>';
    $html .= '<td><strong>اسم الطالب:</strong> ' . htmlspecialchars($student['name'] ?? '-') . '</td>';
    $html .= '<td><strong>الصف/الفصل:</strong> ' . htmlspecialchars($student['full_class_name'] ?? '-') . '</td>';
    $html .= '</tr><tr>';
    $html .= '<td><strong>الرقم الأكاديمي:</strong> ' . htmlspecialchars($student['student_number'] ?? '-') . '</td>';
    $html .= '<td><strong>العمر:</strong> ' . htmlspecialchars($student['age'] ?? '-') . ' سنة</td>';
    $html .= '</tr></table>';
    $html .= '</div>';
    
    $timeline = [];
    foreach ($hMeas as $m) {
        $timeline[] = [
            'date' => $m['measurement_date'],
            'type' => 'measurement',
            'title' => 'قياسات جسمية',
            'desc' => 'الوزن: ' . ($m['weight_kg'] ?? '-') . ' كجم | الطول: ' . ($m['height_cm'] ?? '-') . ' سم | BMI: ' . ($m['bmi'] ?? '-')
        ];
    }
    foreach ($hFit as $f) {
        if ($f['value'] !== null || $f['score'] !== null) {
            $timeline[] = [
                'date' => $f['test_date'],
                'type' => 'fitness',
                'title' => 'اختبار لياقة: ' . $f['test_name'],
                'desc' => 'النتيجة: ' . ($f['value'] ?? '-') . ' ' . ($f['unit'] ?? '') . ' | الدرجة: ' . ($f['score'] ?? 0) . ' من ' . ($f['max_score'] ?? 0)
            ];
        }
    }
    
    usort($timeline, function($a, $b) {
        return strtotime($a['date']) - strtotime($b['date']);
    });
    
    $html .= '<table class="data-table">';
    $html .= '<thead><tr><th width="15%">التاريخ</th><th width="25%">نوع الإجراء</th><th width="45%">النتائج والتفاصيل</th><th width="15%">المؤشر</th></tr></thead>';
    $html .= '<tbody>';
    
    if (empty($timeline)) {
        $html .= '<tr><td colspan="4" style="text-align:center;">لا توجد سجلات أداء مسجلة</td></tr>';
    } else {
        $lastMeasWeight = 0;
        $lastFitness = [];
        
        foreach ($timeline as $i => $item) {
            $changeHtml = '-';
            if ($item['type'] === 'measurement') {
                preg_match('/الوزن:\s([0-9.]+)/', $item['desc'], $matches);
                $currentWeight = floatval($matches[1] ?? 0);
                if ($lastMeasWeight > 0 && $currentWeight > 0) {
                    $diff = round($currentWeight - $lastMeasWeight, 1);
                    if ($diff > 0) $changeHtml = '<span style="color:#d97706; font-size:10px;" dir="ltr">⬆ +'.$diff.'</span>';
                    elseif ($diff < 0) $changeHtml = '<span style="color:#10b981; font-size:10px;" dir="ltr">⬇ '.$diff.'</span>';
                    else $changeHtml = '<span style="font-size:10px;">ثابت</span>';
                }
                if ($currentWeight > 0) $lastMeasWeight = $currentWeight;
            } else if ($item['type'] === 'fitness') {
                $testName = $item['title'];
                preg_match('/النتيجة:\s*([0-9.]+)/', $item['desc'], $matches);
                if (!empty($matches[1])) {
                    $currentVal = floatval($matches[1]);
                    if (isset($lastFitness[$testName])) {
                        $diff = round($currentVal - $lastFitness[$testName], 1);
                        if ($diff > 0) $changeHtml = '<span style="color:#2563eb; font-size:10px;" dir="ltr">⬆ +'.$diff.'</span>';
                        elseif ($diff < 0) $changeHtml = '<span style="color:#ea580c; font-size:10px;" dir="ltr">⬇ '.$diff.'</span>';
                        else $changeHtml = '<span style="font-size:10px;">ثابت</span>';
                    }
                    $lastFitness[$testName] = $currentVal;
                }
            }
            $timeline[$i]['changeHtml'] = $changeHtml;
        }
        
        $timeline = array_reverse($timeline);
        foreach ($timeline as $item) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($item['date']) . '</td>';
            $html .= '<td style="text-align:right;"><strong>' . htmlspecialchars($item['title']) . '</strong></td>';
            $html .= '<td style="text-align:right;">' . htmlspecialchars($item['desc']) . '</td>';
            $html .= '<td style="text-align:center;">' . $item['changeHtml'] . '</td>';
            $html .= '</tr>';
        }
    }
    
    $html .= '</tbody></table>';
    
    $html .= '<div style="margin-top:20px; font-size:12px; color:#4b5563; text-align:center; background:#f3f4f6; padding:10px; border-radius:10px; border: 1px dashed #d1d5db;">';
    $html .= '<strong>ملاحظة هامة:</strong> هذا التقرير التراكمي يعكس أداء الطالب منذ أول تسجيل له وحتى آخر قياس. المتابعة المستمرة تعزز لياقة وصحة الطالب وتحفزه نحو تحقيق إنجازات أفضل.';
    $html .= '</div>';
    
    return $html;
}
