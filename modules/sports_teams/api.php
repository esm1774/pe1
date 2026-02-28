<?php
/**
 * PE Smart School System - Sports Teams API
 * مودول إدارة الفرق الرياضية - الموزع الرئيسي
 * Isolated Module v1.0
 */

require_once '../../config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// تحميل ملفات المنطق الأساسية
require_once 'core/tables.php';    // إنشاء الجداول
require_once 'core/teams.php';     // إدارة الفرق
require_once 'core/lottery.php';   // القرعة
require_once 'core/training.php';  // التدريب

$action = getParam('action', '');

// Resolve current tenant (school) context
Tenant::resolve();

// التأكد من وجود الجداول
ensureSportsTeamsTables();

try {
    switch ($action) {
        // ── الفرق ─────────────────────────────────────────────
        case 'teams_list':          listTeams();         break;
        case 'team_get':            getTeam();           break;
        case 'team_create':         createTeam();        break;
        case 'team_update':         updateTeam();        break;
        case 'team_delete':         deleteTeam();        break;

        // ── الأعضاء ───────────────────────────────────────────
        case 'members_list':        listMembers();       break;
        case 'member_add':          addMember();         break;
        case 'member_update':       updateMember();      break;
        case 'member_remove':       removeMember();      break;

        // ── القرعة ────────────────────────────────────────────
        case 'lottery_preview':     lotteryPreview();    break;
        case 'lottery_confirm':     lotteryConfirm();    break;
        case 'lottery_available_students': lotteryAvailableStudents(); break;

        // ── التدريب ───────────────────────────────────────────
        case 'sessions_list':       listSessions();      break;
        case 'session_create':      createSession();     break;
        case 'session_update':      updateSession();     break;
        case 'session_delete':      deleteSession();     break;
        case 'attendance_save':     saveAttendance();    break;
        case 'attendance_get':      getAttendance();     break;

        // ── مساعد ─────────────────────────────────────────────
        case 'available_classes':   availableClassesST(); break;
        case 'available_students':  availableStudentsST(); break;
        case 'team_stats':          getTeamStats();      break;

        default:
            jsonError('إجراء غير معروف', 404);
    }
} catch (PDOException $e) {
    if (DEBUG_MODE) jsonError('DB Error: ' . $e->getMessage(), 500);
    jsonError('حدث خطأ في قاعدة البيانات', 500);
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}
