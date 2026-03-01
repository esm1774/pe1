<?php
/**
 * PE Smart School System - Sports Calendar API
 * ==============================================
 * API للتقويم الرياضي المدرسي: أحداث، جدول سنوي، تصدير Google Calendar
 */

// ── Get Calendar Events ─────────────────────────────────────
function getCalendarEvents() {
    requireLogin();
    $db = getDB();
    $sid = schoolId();

    $month = getParam('month', null);
    $year  = getParam('year', null);
    $type  = getParam('type', null);

    $where = $sid ? "WHERE e.school_id = ?" : "WHERE 1=1";
    $params = $sid ? [$sid] : [];

    if ($month && $year) {
        $where .= " AND MONTH(e.event_date) = ? AND YEAR(e.event_date) = ?";
        $params[] = (int)$month;
        $params[] = (int)$year;
    } elseif ($year) {
        $where .= " AND YEAR(e.event_date) = ?";
        $params[] = (int)$year;
    }

    if ($type) {
        $where .= " AND e.event_type = ?";
        $params[] = $type;
    }

    $stmt = $db->prepare("
        SELECT e.*, u.name as created_by_name
        FROM sports_calendar e
        LEFT JOIN users u ON e.created_by = u.id
        $where
        ORDER BY e.event_date ASC, e.start_time ASC
    ");
    $stmt->execute($params);
    jsonSuccess($stmt->fetchAll());
}

// ── Save Calendar Event ──────────────────────────────────────
function saveCalendarEvent() {
    requireLogin();
    requireRole(['admin', 'teacher']);
    $db = getDB();
    $sid = schoolId();

    $data = getPostData();
    validateRequired($data, ['title', 'event_date', 'event_type']);

    $id          = $data['id'] ?? null;
    $title       = sanitize($data['title']);
    $description = sanitize($data['description'] ?? '');
    $eventDate   = sanitize($data['event_date']);
    $endDate     = !empty($data['end_date']) ? sanitize($data['end_date']) : null;
    $startTime   = !empty($data['start_time']) ? sanitize($data['start_time']) : null;
    $endTime     = !empty($data['end_time']) ? sanitize($data['end_time']) : null;
    $eventType   = sanitize($data['event_type']);
    $location    = sanitize($data['location'] ?? '');
    $color       = sanitize($data['color'] ?? '#10b981');
    $icon        = $data['icon'] ?? '📅';
    $isRecurring = (int)($data['is_recurring'] ?? 0);
    $recurPattern= sanitize($data['recurrence_pattern'] ?? '');
    $targetGrades= sanitize($data['target_grades'] ?? '');
    $isPublic    = (int)($data['is_public'] ?? 1);

    if ($id) {
        $stmt = $db->prepare("
            UPDATE sports_calendar 
            SET title=?, description=?, event_date=?, end_date=?, start_time=?, end_time=?,
                event_type=?, location=?, color=?, icon=?, is_recurring=?, 
                recurrence_pattern=?, target_grades=?, is_public=?
            WHERE id=? AND school_id=?
        ");
        $stmt->execute([$title, $description, $eventDate, $endDate, $startTime, $endTime,
                        $eventType, $location, $color, $icon, $isRecurring,
                        $recurPattern, $targetGrades, $isPublic, $id, $sid]);
    } else {
        $stmt = $db->prepare("
            INSERT INTO sports_calendar 
            (school_id, title, description, event_date, end_date, start_time, end_time,
             event_type, location, color, icon, is_recurring, recurrence_pattern,
             target_grades, is_public, created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([$sid, $title, $description, $eventDate, $endDate, $startTime, $endTime,
                        $eventType, $location, $color, $icon, $isRecurring,
                        $recurPattern, $targetGrades, $isPublic, $_SESSION['user_id']]);
        $id = $db->lastInsertId();
    }

    logActivity('calendar_save', 'calendar', $id, $title);
    jsonSuccess(['id' => (int)$id], 'تم حفظ الحدث بنجاح');
}

// ── Delete Calendar Event ────────────────────────────────────
function deleteCalendarEvent() {
    requireLogin();
    requireRole(['admin', 'teacher']);
    $db = getDB();
    $sid = schoolId();

    $data = getPostData();
    $id = (int)($data['id'] ?? 0);
    if (!$id) jsonError('معرف غير صالح');

    $db->prepare("DELETE FROM sports_calendar WHERE id = ? AND school_id = ?")->execute([$id, $sid]);
    logActivity('calendar_delete', 'calendar', $id);
    jsonSuccess(null, 'تم حذف الحدث');
}

// ── Calendar Summary (for dashboard) ─────────────────────────
function getCalendarSummary() {
    requireLogin();
    $db = getDB();
    $sid = schoolId();

    $today = date('Y-m-d');
    $where = $sid ? "WHERE school_id = ?" : "WHERE 1=1";
    $params = $sid ? [$sid] : [];

    // Upcoming events (next 30 days)
    $upcomingParams = array_merge($params, [$today]);
    $stmt = $db->prepare("
        SELECT * FROM sports_calendar 
        $where AND event_date >= ? 
        ORDER BY event_date ASC 
        LIMIT 5
    ");
    $stmt->execute($upcomingParams);
    $upcoming = $stmt->fetchAll();

    // Count by type this year
    $yearParams = array_merge($params, [date('Y')]);
    $stmt2 = $db->prepare("
        SELECT event_type, COUNT(*) as cnt 
        FROM sports_calendar 
        $where AND YEAR(event_date) = ?
        GROUP BY event_type
    ");
    $stmt2->execute($yearParams);
    $byType = $stmt2->fetchAll();

    // Total events this year
    $stmt3 = $db->prepare("
        SELECT COUNT(*) FROM sports_calendar 
        $where AND YEAR(event_date) = ?
    ");
    $stmt3->execute($yearParams);
    $total = (int)$stmt3->fetchColumn();

    jsonSuccess([
        'upcoming' => $upcoming,
        'by_type'  => $byType,
        'total'    => $total
    ]);
}

// ── Export to Google Calendar (ICS format) ────────────────────
function exportCalendarICS() {
    requireLogin();
    $db = getDB();
    $sid = schoolId();

    $year  = getParam('year', date('Y'));
    $where = $sid ? "WHERE school_id = ?" : "WHERE 1=1";
    $params = $sid ? [$sid, $year] : [$year];

    $stmt = $db->prepare("
        SELECT * FROM sports_calendar 
        $where AND YEAR(event_date) = ?
        ORDER BY event_date ASC
    ");
    $stmt->execute($params);
    $events = $stmt->fetchAll();

    $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//PE Smart School//Sports Calendar//AR\r\nCALSCALE:GREGORIAN\r\nMETHOD:PUBLISH\r\nX-WR-CALNAME:التقويم الرياضي المدرسي\r\n";

    foreach ($events as $ev) {
        $uid = 'pe-cal-' . $ev['id'] . '@pesmart.school';
        $dtstart = str_replace('-', '', $ev['event_date']);
        if ($ev['start_time']) {
            $dtstart .= 'T' . str_replace(':', '', $ev['start_time']) . '00';
        }
        $dtend = $dtstart;
        if ($ev['end_date']) {
            $dtend = str_replace('-', '', $ev['end_date']);
        }
        if ($ev['end_time']) {
            $dtend = str_replace('-', '', $ev['end_date'] ?: $ev['event_date']);
            $dtend .= 'T' . str_replace(':', '', $ev['end_time']) . '00';
        }

        $summary = str_replace(["\r", "\n", ",", ";"], [' ', ' ', '\\,', '\\;'], $ev['title']);
        $desc    = str_replace(["\r", "\n", ",", ";"], [' ', ' ', '\\,', '\\;'], $ev['description'] ?? '');
        $loc     = str_replace(["\r", "\n", ",", ";"], [' ', ' ', '\\,', '\\;'], $ev['location'] ?? '');

        $ics .= "BEGIN:VEVENT\r\n";
        $ics .= "UID:$uid\r\n";
        $ics .= "DTSTART:$dtstart\r\n";
        $ics .= "DTEND:$dtend\r\n";
        $ics .= "SUMMARY:$summary\r\n";
        if ($desc) $ics .= "DESCRIPTION:$desc\r\n";
        if ($loc)  $ics .= "LOCATION:$loc\r\n";
        $ics .= "END:VEVENT\r\n";
    }

    $ics .= "END:VCALENDAR\r\n";

    // Return as JSON with the ICS content
    jsonSuccess([
        'ics_content' => $ics,
        'filename'    => 'sports_calendar_' . $year . '.ics',
        'event_count' => count($events)
    ]);
}
