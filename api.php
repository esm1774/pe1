<?php
/**
 * PE Smart School System - API Router v2.1
 * ==========================================
 * هذا الملف يعمل كراوتر فقط
 * الوظائف موزعة في مجلد api/
 */

require_once 'config.php';

// Resolve current tenant (school) context
Tenant::resolve();

// SaaS Security: If school found but inactive, block access unless Platform Admin
if (Tenant::isSaasMode() && !Tenant::isPlatformAdmin()) {
    $school = Tenant::school();
    if ($school && $school['active'] == 0) {
        http_response_code(403);
        die(json_encode(['success' => false, 'error' => 'هذا الحساب معطل حالياً، يرجى مراجعة مدير المنصة']));
    }
}

// Set JSON headers ONLY for non-file-download actions
$action = getParam('action', '');
if ($action !== 'students_template') {
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
}

// ============================================================
// LOAD API MODULES
// ============================================================
require_once 'api/auth.php';
require_once 'api/dashboard.php';
require_once 'api/grades.php';
require_once 'api/students.php';
require_once 'api/profile.php';
require_once 'api/attendance.php';
require_once 'api/fitness.php';
require_once 'api/competition.php';
require_once 'api/users.php';
require_once 'api/parent.php';
require_once 'api/parents_admin.php';
require_once 'api/notifications.php';
require_once 'api/badges.php';
require_once 'api/calendar.php';
require_once 'api/analytics.php';
require_once 'api/reports_mail.php';
require_once 'api/audit_log.php';
require_once 'api/timetable.php';
require_once 'api/schools.php';
require_once 'api/reports_grading.php';

// ============================================================
// ROUTE REQUESTS
// ============================================================
try {
    // SaaS Middleware: Check subscription for protected actions
    $publicActions = ['login', 'student_login', 'check_auth', 'logout', 'exit_impersonation', 'schools_list', 'get_public_plans', 'register_school', 'forgot_password', 'reset_password', 'get_active_announcements'];
    if (!in_array($action, $publicActions)) {
        Subscription::requireActive();
    }

    // Feature-gated actions
    $featureActions = [
        'tournaments' => ['tournament_save', 'tournament_delete'],
        'badges' => ['badge_save', 'badge_delete', 'award_badge', 'run_auto_badges'],
        'sports_teams' => [],  // handled in module
    ];
    foreach ($featureActions as $feature => $actions) {
        if (in_array($action, $actions)) {
            Subscription::requireFeature($feature);
        }
    }

    switch ($action) {
        // AUTH
        case 'check_auth':      checkAuth(); break;
        case 'login':           login(); break;
        case 'student_login':   studentLogin(); break;
        case 'logout':          logout(); break;
        case 'exit_impersonation': exitImpersonation(); break;
        case 'schools_list':    getSchoolsList(); break;
        case 'forgot_password': forgotPassword(); break;
        case 'reset_password':  resetPassword(); break;
        case 'get_active_announcements': getActiveAnnouncements(); break;

        // DASHBOARD
        case 'dashboard':       getDashboard(); break;
        case 'student_dashboard_summary': getStudentDashboardSummary(); break;
        case 'analytics_dashboard': getAnalyticsDashboard(); break;
        case 'audit_logs':      getAuditLogs(); break;
        
        // TIMETABLE
        case 'timetable':           getTimetable(); break;
        case 'save_timetable':      saveTimetable(); break;
        case 'period_times':        getPeriodTimes(); break;
        case 'save_period_times':   savePeriodTimes(); break;

        // GRADES
        case 'grades':          getGrades(); break;
        case 'grade_save':      saveGrade(); break;
        case 'grade_delete':    deleteGrade(); break;

        // CLASSES
        case 'classes':         getClasses(); break;
        case 'class_save':      saveClass(); break;
        case 'class_delete':    deleteClass(); break;

        // SCHOOL SETTINGS
        case 'get_school_info': getSchoolInfo(); break;
        case 'save_school_info': saveSchoolInfo(); break;
        case 'upload_logo':     uploadSchoolLogo(); break;

        // STUDENTS
        case 'students':        getStudents(); break;
        case 'student_save':    saveStudent(); break;
        case 'student_delete':  deleteStudent(); break;
        case 'students_import': importStudents(); break;
        case 'students_template': exportStudentsTemplate(); break;

        // STUDENT PROFILE
        case 'student_profile': getStudentProfile(); break;

        // MEASUREMENTS
        case 'measurements':        getMeasurements(); break;
        case 'measurement_save':    saveMeasurement(); break;
        case 'measurement_delete':  deleteMeasurement(); break;

        // HEALTH
        case 'health_conditions':   getHealthConditions(); break;
        case 'health_save':         saveHealthCondition(); break;
        case 'health_delete':       deleteHealthCondition(); break;
        case 'class_health_alerts': getClassHealthAlerts(); break;

        // ATTENDANCE
        case 'attendance':      getAttendance(); break;
        case 'attendance_save': saveAttendance(); break;
        case 'absence_report':  getAbsenceReport(); break;

        // FITNESS TESTS
        case 'fitness_tests':       getFitnessTests(); break;
        case 'fitness_test_save':   saveFitnessTest(); break;
        case 'fitness_test_delete': deleteFitnessTest(); break;

        // FITNESS RESULTS
        case 'fitness_results':      getFitnessResults(); break;
        case 'fitness_results_save': saveFitnessResults(); break;
        case 'fitness_view':         getFitnessView(); break;
        case 'fitness_criteria':      getFitnessCriteria(); break;
        case 'fitness_criteria_save': saveFitnessCriteria(); break;

        // COMPETITION
        case 'competition':     getCompetition(); break;

        // PARENT PORTAL
        case 'parent_dashboard':    getParentDashboard(); break;
        case 'parent_link_phone':   linkChildrenByPhone(); break;

        // REPORTS
        case 'report_student':  getStudentReport(); break;
        case 'report_class':    getClassReport(); break;
        case 'report_compare':  getCompareReport(); break;
        case 'report_grading':  getGradingReport(); break;
        case 'send_report_email': sendReportEmail(); break;

        // USERS
        case 'users':           getUsers(); break;
        case 'user_save':       saveUser(); break;
        case 'user_delete':     deleteUser(); break;
        
        // PARENT MANAGEMENT (Admin)
        case 'parents_list':        getParents(); break;
        case 'parent_save':         saveParent(); break;
        case 'parent_delete':       deleteParent(); break;
        case 'parent_links':        getParentLinkedStudents(); break;
        case 'parent_search_students': searchStudentsForLinking(); break;
        case 'parent_link_student': linkParentStudent(); break;
        case 'parent_unlink_student': unlinkParentStudent(); break;

        // NOTIFICATIONS
        case 'notifications':           getNotifications(); break;
        case 'notification_read':       markNotificationRead(); break;
        case 'notification_mark_all_read': markAllNotificationsRead(); break;
        case 'notification_unread_count': getUnreadNotificationsCount(); break;

        // TEACHER CLASS ASSIGNMENTS (Admin)
        case 'teacher_assignments':    getTeacherAssignments(); break;
        case 'assign_teacher_class':   assignTeacherClass(); break;
        case 'unassign_teacher_class': unassignTeacherClass(); break;
        case 'save_teacher_assignments': saveTeacherAssignments(); break;

        // MY PROFILE
        case 'get_my_profile':    getMyProfile(); break;
        case 'update_my_profile': updateMyProfile(); break;

        // BADGES
        case 'get_badges':          getBadges(); break;
        case 'get_student_badges':  getStudentBadges(); break;
        case 'award_badge':         awardBadge(); break;
        case 'revoke_badge':        revokeBadge(); break;
        case 'badge_delete':        deleteBadge(); break;
        case 'run_auto_badges':     runAutoBadges(); break;

        // SPORTS CALENDAR
        case 'calendar_events':      getCalendarEvents(); break;
        case 'calendar_save':        saveCalendarEvent(); break;
        case 'calendar_delete':      deleteCalendarEvent(); break;
        case 'calendar_summary':     getCalendarSummary(); break;
        case 'calendar_export_ics':  exportCalendarICS(); break;

        // PUBLIC / ONBOARDING
        case 'get_public_plans':    getPublicPlans(); break;
        case 'register_school':     registerSchool(); break;
        case 'subscription':        getSubscriptionInfo(); break;

        default:
            jsonError('إجراء غير معروف', 404);
    }
} catch (PDOException $e) {
    if (DEBUG_MODE) jsonError('DB Error (' . ($action ?? 'none') . '): ' . $e->getMessage(), 500);
    jsonError('حدث خطأ في قاعدة البيانات', 500);
} catch (Exception $e) {
    if (DEBUG_MODE) jsonError('Logic Error (' . ($action ?? 'none') . '): ' . $e->getMessage(), 500);
    jsonError($e->getMessage(), 500);
}

/**
 * Public Plans List for Registration
 */
function getPublicPlans() {
    $db = getDB();
    $plans = $db->prepare("SELECT id, name, slug, price_monthly, max_students, max_teachers, max_classes FROM plans ORDER BY sort_order");
    $plans->execute();
    jsonSuccess($plans->fetchAll());
}

/**
 * Public School Registration
 */
function registerSchool() {
    $data = getPostData();
    validateRequired($data, ['name', 'slug', 'admin_username', 'admin_password']);
    
    // Fix: Enforce minimum password strength
    $adminPass = $data['admin_password'];
    if (strlen($adminPass) < 8) {
        jsonError('كلمة المرور يجب أن تكون 8 أحرف على الأقل');
    }

    $db = getDB();
    $name = sanitize($data['name']);
    $slug = strtolower(sanitize($data['slug']));
    $adminName = sanitize($data['admin_name'] ?? ('مدير ' . $name));
    $adminEmail = sanitize($data['admin_email'] ?? '');
    $adminUser = sanitize($data['admin_username']);
    $planId = !empty($data['plan_id']) ? (int)$data['plan_id'] : null;

    // Check slug uniqueness
    // Fix: Validate slug format — only allow lowercase letters, numbers, and hyphens
    if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
        jsonError('المعرّف الفريد يجب أن يحتوي على أحرف إنجليزية صغيرة وأرقام وشرطة (-) فقط');
    }
    $stmt = $db->prepare("SELECT id FROM schools WHERE slug = ?");
    $stmt->execute([$slug]);
    if ($stmt->fetch()) jsonError('المعرّف الفريد (slug) مستخدم بالفعل، اختر اسماً آخر');

    $db->beginTransaction();
    try {
        // Find plan limits
        $maxStudents = 100; $maxTeachers = 5;
        if ($planId) {
            $p = $db->prepare("SELECT max_students, max_teachers FROM plans WHERE id = ?");
            $p->execute([$planId]);
            $plan = $p->fetch();
            if ($plan) {
                $maxStudents = (int)$plan['max_students'];
                $maxTeachers = (int)$plan['max_teachers'];
            }
        }

        $stmt = $db->prepare("INSERT INTO schools (name, slug, email, plan_id, max_students, max_teachers, subscription_status, trial_ends_at) VALUES (?,?,?,?,?,?,'trial',?)");
        $stmt->execute([$name, $slug, $adminEmail, $planId, $maxStudents, $maxTeachers, date('Y-m-d', strtotime('+14 days'))]);
        $schoolId = $db->lastInsertId();

        // Create Admin User
        $hash = password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare("INSERT INTO users (school_id, username, password, name, role) VALUES (?,?,?,?,?)")
           ->execute([$schoolId, $adminUser, $hash, $adminName, 'admin']);

        // Create Default Grades
        $db->prepare("INSERT INTO grades (school_id, name, code, sort_order) VALUES (?,?,?,?)")->execute([$schoolId, 'الصف الأول', '1', 1]);
        $db->prepare("INSERT INTO grades (school_id, name, code, sort_order) VALUES (?,?,?,?)")->execute([$schoolId, 'الصف الثاني', '2', 2]);

        $db->commit();
        jsonSuccess(['slug' => $slug], 'تم تسجيل مدرستك بنجاح!');
    } catch (Exception $e) {
        $db->rollBack();
        jsonError('خطأ أثناء الإنشاء: ' . $e->getMessage());
    }
}
