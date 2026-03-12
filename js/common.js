/**
 * PE Smart School System - Common JavaScript
 * Shared functions: API, Auth, Navigation, Utilities
 */

// SaaS/Localhost: Auto-detect project root
window.APP_BASE = (function () {
    // 1. Check for <base> tag (highest priority)
    const baseTag = document.querySelector('base');
    if (baseTag && baseTag.href) {
        try {
            const url = new URL(baseTag.href);
            return url.pathname.endsWith('/') ? url.pathname : url.pathname + '/';
        } catch (e) { }
    }

    // 2. Fallback: Detection via js/common.js script tag
    const scripts = document.getElementsByTagName('script');
    for (let i = 0; i < scripts.length; i++) {
        if (scripts[i].src.includes('js/common.js')) {
            const src = scripts[i].src;
            // Clean versioning/query strings
            const cleanPath = src.split('?')[0].split('#')[0];
            // Extract path part
            const match = cleanPath.match(/^(https?:\/\/[^\/]+)?(.*?)(js\/common\.js)$/);
            if (match && match[2]) {
                return match[2].endsWith('/') ? match[2] : match[2] + '/';
            }
            // Fallback to URL object
            try {
                const url = new URL(src);
                let path = url.pathname.replace('js/common.js', '');
                return path.endsWith('/') ? path : path + '/';
            } catch (e) { }
        }
    }
    return '/'; // Final fallback
})();

// ============================================================
// CONSTANTS
// ============================================================
const BLOOD_TYPES = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];

const CONDITION_TYPES = {
    asthma: '🫁 ربو',
    diabetes: '💉 سكري',
    heart: '❤️ مشاكل القلب',
    allergy: '🤧 حساسية',
    bones: '🦴 عظام/مفاصل',
    vision: '👁️ مشاكل البصر',
    exemption: '🏥 إعفاء طبي',
    other: '📝 أخرى'
};

const SEVERITY_AR = {
    mild: 'خفيف',
    moderate: 'متوسط',
    severe: 'شديد'
};

const BMI_AR = {
    underweight: 'نحيف',
    normal: 'طبيعي',
    overweight: 'وزن زائد',
    obese: 'سمنة'
};

const BMI_ICONS = {
    underweight: '🔵',
    normal: '🟢',
    overweight: '🟡',
    obese: '🔴'
};

const PAGE_MAP = {
    dashboard: 'renderDashboard',
    grades: 'renderGrades',
    students: 'renderStudents',
    attendance: 'renderAttendance',
    assessments: 'renderAssessments',
    fitness: 'renderFitness',
    competition: 'renderCompetition',
    tournaments: 'renderTournaments',
    sportsTeams: 'renderSportsTeams',
    reports: 'renderReports',
    users: 'renderUsers',
    parents: 'renderParents',
    'parentDashboard': 'renderParentDashboard',
    'studentProfile': 'renderStudentProfilePage',
    'notifications': 'renderNotifications',
    badgesAdmin: 'renderBadgeManagementPage',
    user_profile: 'renderUserProfilePage',
    studentDashboard: 'renderStudentDashboard',
    studentProfile: 'renderStudentProfilePage',
    parentDashboard: 'renderParentDashboard',
    sportsCalendar: 'renderSportsCalendar',
    analytics: 'renderAnalytics',
    audit_logs: 'renderAuditLog',
    timetable: 'renderTimetablePage',
    school_settings: 'renderSchoolSettings',
    subscription: 'renderSubscriptionPage'
};

// ============================================================
// THEME MANAGEMENT (DARK/LIGHT MODE)
// ============================================================
function initTheme() {
    const saved = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
    updateThemeIcon(saved);
}

function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme');
    const target = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', target);
    localStorage.setItem('theme', target);
    updateThemeIcon(target);
}

function updateThemeIcon(theme) {
    const btn = document.getElementById('themeToggleBtn');
    if (btn) {
        btn.innerHTML = theme === 'dark' ? '☀️' : '🌙';
        btn.title = theme === 'dark' ? 'الوضع الفاتح' : 'الوضع الداكن';
    }
}

// Call on load
initTheme();

// ============================================================
// API HELPER
// ============================================================
const API = {
    // Ensure we always point to the root api.php regardless of current path slug
    get base() {
        return window.APP_BASE + 'api.php';
    },

    async request(action, method = 'GET', data = null, params = {}) {
        try {
            let url = `${this.base}?action=${action}`;
            Object.entries(params).forEach(([k, v]) => {
                if (v !== null && v !== undefined && v !== '') {
                    url += `&${k}=${encodeURIComponent(v)}`;
                }
            });

            const options = { method, headers: {} };

            // Add CSRF Token for state-changing requests
            if (method !== 'GET') {
                const csrfToken = document.cookie
                    .split('; ')
                    .find(row => row.startsWith('XSRF-TOKEN='))
                    ?.split('=')[1];
                if (csrfToken) {
                    options.headers['X-CSRF-TOKEN'] = csrfToken;
                }
            }

            if (data && method !== 'GET') {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(data);
            }

            const r = await fetch(url, options);
            const result = await r.json();

            if (!r.ok) {
                if (r.status === 401) {
                    showLoginPage();
                    return null;
                }
                if (r.status === 403) {
                    handleDeactivation(result);
                    return null;
                }
                if (r.status === 503 && result.error === 'maintenance') {
                    handleMaintenance(result);
                    return null;
                }
            }

            return result;
        } catch (e) {
            console.error('API Error:', e);
            showToast('خطأ في الاتصال', 'error');
            return null;
        }
    },

    get(action, params = {}) {
        return this.request(action, 'GET', null, params);
    },

    post(action, data = {}, params = {}) {
        return this.request(action, 'POST', data, params);
    }
};

// ============================================================
// GLOBAL STATE
// ============================================================
let currentUser = null;
let currentPage = 'dashboard';
let currentSchool = null; // SaaS: current school context

// ============================================================
// AUTH FUNCTIONS
// ============================================================
async function handleLogin() {
    const username = document.getElementById('loginUsername').value.trim();
    const password = document.getElementById('loginPassword').value.trim();
    const errorEl = document.getElementById('loginError');
    const schoolSelect = document.getElementById('schoolSelect');

    if (!username || !password) {
        errorEl.textContent = 'الرجاء إدخال البيانات';
        errorEl.classList.remove('hidden');
        return;
    }

    const loginData = { username, password };
    // If school selector exists, include school slug
    if (schoolSelect && schoolSelect.value) {
        loginData.school = schoolSelect.value;
    }

    const r = await API.post('login', loginData);
    if (!r) {
        errorEl.textContent = 'تعذر الاتصال بالخادم. تأكد من عمل XAMPP وقاعدة البيانات.';
        errorEl.classList.remove('hidden');
        alert('فشل الاتصال بـ: ' + API.base); // Useful for local debugging
        return;
    }

    if (r.success) {
        currentUser = r.data;
        // Store school context from login response
        if (r.data.school_id) {
            currentSchool = {
                id: r.data.school_id,
                name: r.data.school_name || '',
                slug: r.data.school_slug || '',
                logo: r.data.school_logo || ''
            };
        }
        errorEl.classList.add('hidden');

        // Redirect to slug-based URL if school context exists
        if (r.data.school_slug) {
            window.location.href = window.APP_BASE + r.data.school_slug + '/';
        } else {
            showApp();
            if (r.data.weak_password) {
                setTimeout(() => {
                    showToast('⚠️ يرجى تغيير كلمة المرور الافتراضية لحماية حسابك', 'info');
                }, 3000);
            }
        }
    } else {
        errorEl.textContent = r.error || 'بيانات غير صحيحة';
        errorEl.classList.remove('hidden');
    }
}

async function handleLogout() {
    // Fix #9: Stop notification polling before clearing session
    if (typeof stopNotificationPolling === 'function') {
        stopNotificationPolling();
    }
    await API.post('logout');
    currentUser = null;
    currentSchool = null;
    showLoginPage();
}

async function exitImpersonation() {
    try {
        const r = await API.post('exit_impersonation');
        if (r && r.success) {
            window.location.href = '../admin/';
        } else {
            showToast(r?.error || 'فشل الخروج من الإشراف', 'error');
        }
    } catch (e) {
        showToast('خطأ في الاتصال', 'error');
    }
}

async function checkAuth() {
    const r = await API.get('check_auth');
    if (r && r.success) {
        currentUser = r.data;
        // Restore school context
        if (r.data.school_id) {
            currentSchool = {
                id: r.data.school_id,
                name: r.data.school_name || '',
                slug: r.data.school_slug || '',
                logo: r.data.school_logo || ''
            };
        }
        showApp();
    } else {
        if (r && r.error) {
            const errorEl = document.getElementById('loginError');
            if (errorEl) {
                errorEl.textContent = r.error;
                errorEl.classList.remove('hidden');
                showLoginPage();
            }
        }
        // Try loading schools list for login page
        loadSchoolsList();
    }
}

// SaaS: Load list of schools for login page selector
async function loadSchoolsList() {
    try {
        const r = await API.get('schools_list');
        if (r && r.success && r.data && r.data.length > 0) {
            const select = document.getElementById('schoolSelect');
            const container = document.getElementById('schoolSelectContainer');
            if (select && container) {
                container.classList.remove('hidden');
                select.innerHTML = '<option value="">اختر المدرسة...</option>';
                r.data.forEach(s => {
                    const option = document.createElement('option');
                    option.value = s.slug;
                    option.textContent = s.name + (s.city ? ' - ' + s.city : '');
                    select.appendChild(option);
                });
            }
        }
    } catch (e) {
        // Silent - school selector is optional
    }
}

function showLoginPage() {
    currentUser = null;
    document.getElementById('mainApp').classList.add('hidden');
    document.getElementById('loginPage').classList.remove('hidden');
    document.getElementById('loginUsername').value = '';
    document.getElementById('loginPassword').value = '';
}

function showApp() {
    const loginPage = document.getElementById('loginPage');
    const mainApp = document.getElementById('mainApp');
    if (loginPage) loginPage.classList.add('hidden');
    if (mainApp) mainApp.classList.remove('hidden');

    const nameEl = document.getElementById('currentUserDisplay');
    const initEl = document.getElementById('userInitialDisplay');
    const roleEl = document.getElementById('userRoleBadge');

    if (nameEl) nameEl.textContent = currentUser.name;
    if (initEl) {
        if (currentUser.photo_url) {
            initEl.innerHTML = `<img src="${currentUser.photo_url}" class="w-full h-full object-cover rounded-2xl user-avatar-img">`;
            initEl.classList.remove('bg-green-100', 'text-green-700');
            initEl.classList.add('p-0');
        } else {
            initEl.textContent = (currentUser.name || '?').charAt(0);
            initEl.classList.add('bg-green-100', 'text-green-700');
            initEl.classList.remove('p-0');
        }
    }

    const roleNames = { admin: 'مدير', teacher: 'معلم', viewer: 'مشاهد', supervisor: 'مشرف/موجه', student: 'طالب', parent: 'ولي أمر' };
    if (roleEl) roleEl.textContent = roleNames[currentUser.role] || currentUser.role;

    // Show Impersonation Banner if active
    const impBanner = document.getElementById('impersonateBanner');
    if (impBanner) {
        if (currentUser.is_impersonating) {
            impBanner.classList.remove('hidden');
        } else {
            impBanner.classList.add('hidden');
        }
    }

    // SaaS: Display school name + branding
    if (typeof refreshBranding === 'function') {
        refreshBranding();
    } else {
        const schoolNameEl = document.getElementById('school-name-display');
        if (schoolNameEl && currentSchool) {
            schoolNameEl.textContent = currentSchool.name;
        }
    }

    // Ensure URL has school slug if we are logged in with a school context
    if (currentSchool && currentSchool.slug) {
        const path = window.location.pathname;
        const targetSlugPath = `${window.APP_BASE}${currentSchool.slug}/`;

        // If the current path doesn't match the specific school's slug path
        if (path !== targetSlugPath) {
            const currentHash = window.location.hash || '';
            // Silently rewrite the URL without reloading the page
            window.history.replaceState(null, '', `${targetSlugPath}${currentHash}`);
        }
    }

    // Show/hide menu items based on role and active features
    document.querySelectorAll('[data-role]').forEach(el => {
        const allowedRoles = el.dataset.role.split(',');
        let allowed = allowedRoles.includes(currentUser.role);
        if (allowed && typeof hasFeature === 'function' && el.dataset.feature) {
            allowed = hasFeature(el.dataset.feature);
        }
        el.style.display = allowed ? '' : 'none';
    });

    // Hide empty sidebar sections
    document.querySelectorAll('.sidebar-section-header').forEach(header => {
        let sibling = header.nextElementSibling;
        let hasVisibleLink = false;
        while (sibling && !sibling.classList.contains('sidebar-section-header')) {
            if (sibling.offsetParent !== null && sibling.dataset.role) {
                hasVisibleLink = true;
                break;
            }
            sibling = sibling.nextElementSibling;
        }
        header.style.display = hasVisibleLink ? '' : 'none';
    });

    // Determine starting page (hash-based OR role-based default)
    const rawHash = window.location.hash.replace('#', '');
    const basePage = rawHash.split('/')[0];

    if (basePage && PAGE_MAP[basePage]) {
        navigateTo(rawHash);
    } else if (currentUser.role === 'student') {
        navigateTo('studentDashboard');
    } else {
        navigateTo('dashboard');
    }

    // Start notification polling for parents
    if (typeof startNotificationPolling === 'function') {
        startNotificationPolling();
    }

    // SaaS: Load system announcements
    if (typeof loadAnnouncements === 'function') {
        loadAnnouncements();
    }

    // Forced Password Change Policy
    if (currentUser && currentUser.must_change_password == 1) {
        if (typeof showForcePasswordChangeModal === 'function') {
            showForcePasswordChangeModal();
        } else {
            console.warn('showForcePasswordChangeModal is not defined. Falling back to toast.');
            showToast('⚠️ يرجى تغيير كلمة المرور فوراً لتأمين حسابك', 'warning');
        }
    }
}

// SaaS: Check if a feature is available in current plan
function hasFeature(feature) {
    if (!currentUser) return false;

    // Non-SaaS single-school mode: allow all
    if (!currentUser.subscription) return true;

    // Use the merged 'features' field which includes plan + school overrides
    let features = currentUser.subscription.features;
    if (!features) return false;

    try {
        const parsed = typeof features === 'string' ? JSON.parse(features) : features;
        // Fail-closed: return false if feature is undefined or false
        return !!parsed[feature];
    } catch (e) {
        console.error('Feature parse error:', e);
        return false;
    }
}

function canEdit() {
    return currentUser && (currentUser.role === 'admin' || currentUser.role === 'teacher');
}

function isAdmin() {
    return currentUser && currentUser.role === 'admin';
}

function isParent() {
    return currentUser && currentUser.role === 'parent';
}

function isStudent() {
    return currentUser && currentUser.role === 'student';
}

function isSupervisor() {
    return currentUser && (currentUser.role === 'supervisor' || currentUser.role === 'موجه' || currentUser.role === 'مشرف');
}

function isTeacher() {
    return currentUser && currentUser.role === 'teacher';
}

// Map pages to their required feature key (for subscription-based access control)
const PAGE_FEATURE_MAP = {
    'fitness': 'fitness_tests',
    'timetable': 'timetable',
    'sportsTeams': 'sports_teams',
    'tournaments': 'tournaments',
    'reports': 'reports',
    'analytics': 'analytics',
    'badgesAdmin': 'badges',
};

// ============================================================
// NAVIGATION
// ============================================================
function navigateTo(page) {
    // Role-aware dashboard redirection
    if (page === 'dashboard' && currentUser) {
        if (currentUser.role === 'student') page = 'studentDashboard';
        if (currentUser.role === 'parent') page = 'parentDashboard';
    }

    // Feature-access guard: show permission-denied page if subscription lacks this feature
    const requiredFeature = PAGE_FEATURE_MAP[page];
    if (requiredFeature && typeof hasFeature === 'function' && !hasFeature(requiredFeature)) {
        const deniedPage = document.getElementById('permissionDeniedPage');
        const mainContent = document.getElementById('mainContent');
        if (deniedPage) deniedPage.classList.remove('hidden');
        if (mainContent) mainContent.innerHTML = ''; // Clear main content
        return; // Stop navigation
    }

    // Hide permission-denied page if visible (from a previous denied attempt)
    const deniedPage = document.getElementById('permissionDeniedPage');
    if (deniedPage && !deniedPage.classList.contains('hidden')) {
        deniedPage.classList.add('hidden');
    }

    currentPage = page;
    window.location.hash = page;

    const basePage = page.split('/')[0];

    // Update sidebar active state
    document.querySelectorAll('.sidebar-link').forEach(link => {
        link.classList.remove('active');
        const onclick = link.getAttribute('onclick') || '';

        // Match exact page OR base page OR 'dashboard' if we redirected to studentDashboard/parentDashboard
        if (onclick.includes("'" + basePage + "'") || onclick.includes("'" + page + "'") ||
            ((page === 'studentDashboard' || page === 'parentDashboard') && onclick.includes("'dashboard'"))) {
            link.classList.add('active');
        }
    });

    // Close mobile sidebar
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');

    // Safe function checker - prevents ReferenceError
    function safeGetFunction(name) {
        try {
            const fn = window[name];
            return typeof fn === 'function' ? fn : null;
        } catch (e) {
            return null;
        }
    }

    const basePageForFunc = page.split('/')[0];
    const funcName = PAGE_MAP[basePageForFunc];
    const renderer = funcName ? safeGetFunction(funcName) : null;

    if (renderer) {
        try {
            const result = renderer();
            // Handle async functions - catch Promise rejections
            if (result && typeof result.catch === 'function') {
                result.catch(e => {
                    console.error('Async page render error:', page, e);
                    document.getElementById('mainContent').innerHTML = `
                        <div class="text-center py-12">
                            <p class="text-5xl mb-4">⚠️</p>
                            <p class="text-xl font-bold text-red-600 mb-2">خطأ في تحميل الصفحة</p>
                            <p class="text-gray-500">${esc(e.message)}</p>
                            <button onclick="navigateTo('dashboard')" class="mt-4 bg-green-600 text-white px-6 py-3 rounded-xl font-semibold cursor-pointer">العودة للرئيسية</button>
                        </div>`;
                });
            }
        } catch (e) {
            console.error('Page render error:', page, e);
            document.getElementById('mainContent').innerHTML = `
                <div class="text-center py-12">
                    <p class="text-5xl mb-4">⚠️</p>
                    <p class="text-xl font-bold text-red-600 mb-2">خطأ في تحميل الصفحة</p>
                    <p class="text-gray-500">${e.message}</p>
                    <button onclick="navigateTo('dashboard')" class="mt-4 bg-green-600 text-white px-6 py-3 rounded-xl font-semibold cursor-pointer">العودة للرئيسية</button>
                </div>`;
        }
    } else {
        console.warn('Page renderer not found:', page, funcName);
        document.getElementById('mainContent').innerHTML = `
            <div class="text-center py-12">
                <p class="text-5xl mb-4">⏳</p>
                <p class="text-xl font-bold text-gray-600 mb-2">جاري التحميل...</p>
                <p class="text-gray-400">إذا استمرت المشكلة، أعد تحميل الصفحة</p>
                <button onclick="location.reload()" class="mt-4 bg-green-600 text-white px-6 py-3 rounded-xl font-semibold cursor-pointer">🔄 إعادة التحميل</button>
            </div>`;
    }
}

// function toggleSidebar() {
//     const sidebar = document.getElementById('sidebar');
//     const overlay = document.getElementById('sidebarOverlay');
//     if (sidebar.classList.contains('-right-64')) {
//         sidebar.classList.remove('-right-64');
//         sidebar.classList.add('right-0');
//         overlay.classList.remove('hidden');
//     } else {
//         sidebar.classList.add('-right-64');
//         sidebar.classList.remove('right-0');
//         overlay.classList.add('hidden');
//     }
// }

// ============================================================
// UTILITIES
// ============================================================
function showToast(msg, type = 'success') {
    const container = document.getElementById('toastContainer');
    // Fix #15: Added 'warning' as a valid toast type (yellow)
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        info: 'bg-blue-500',
        warning: 'bg-amber-500'
    };
    const icons = {
        success: '✅',
        error: '❌',
        info: 'ℹ️',
        warning: '⚠️'
    };

    const toast = document.createElement('div');
    toast.className = `toast ${colors[type]} text-white px-5 py-3 rounded-xl shadow-lg flex items-center gap-2 text-sm font-semibold`;

    const iconSpan = document.createElement('span');
    iconSpan.textContent = icons[type];
    const msgSpan = document.createElement('span');
    msgSpan.textContent = msg;

    toast.appendChild(iconSpan);
    toast.appendChild(msgSpan);
    container.appendChild(toast);

    setTimeout(() => toast.remove(), 3000);
}

function showModal(html) {
    document.getElementById('modalContent').innerHTML = html;
    document.getElementById('modalContainer').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('modalContainer').classList.add('hidden');
}

function showLoading() {
    return '<div class="loading"><div class="spinner"></div></div>';
}

function esc(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function calcAge(dob) {
    if (!dob) return '-';
    const birth = new Date(dob);
    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    if (today.getMonth() < birth.getMonth() ||
        (today.getMonth() === birth.getMonth() && today.getDate() < birth.getDate())) {
        age--;
    }
    return age;
}

function calcBMI(height, weight) {
    if (!height || !weight || height <= 0) return { bmi: null, cat: null };
    const heightM = height / 100;
    const bmi = +(weight / (heightM * heightM)).toFixed(1);
    let cat = 'normal';
    if (bmi < 18.5) cat = 'underweight';
    else if (bmi < 25) cat = 'normal';
    else if (bmi < 30) cat = 'overweight';
    else cat = 'obese';
    return { bmi, cat };
}

// ============================================================
// KEYBOARD
// ============================================================
document.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !currentUser) {
        if (document.getElementById('forgotPasswordContainer') && !document.getElementById('forgotPasswordContainer').classList.contains('hidden')) {
            handleForgotPassword();
        } else if (document.getElementById('resetPasswordContainer') && !document.getElementById('resetPasswordContainer').classList.contains('hidden')) {
            handleResetPassword();
        } else if (document.getElementById('loginFormContainer') && !document.getElementById('loginFormContainer').classList.contains('hidden')) {
            handleLogin();
        }
        // Fix #6: Removed duplicate fallback handleLogin() call that fired for any unmatched case
    }
    if (e.key === 'Escape') closeModal();
});

// ============================================================
// FORGOT PASSWORD
// ============================================================
function toggleForgotPassword(show) {
    if (show) {
        document.getElementById('loginFormContainer').classList.add('hidden');
        document.getElementById('resetPasswordContainer').classList.add('hidden');
        document.getElementById('forgotPasswordContainer').classList.remove('hidden');
        document.getElementById('loginError').classList.add('hidden');
    } else {
        document.getElementById('forgotPasswordContainer').classList.add('hidden');
        document.getElementById('resetPasswordContainer').classList.add('hidden');
        document.getElementById('loginFormContainer').classList.remove('hidden');
        document.getElementById('loginError').classList.add('hidden');
    }
}

async function handleForgotPassword() {
    const email = document.getElementById('forgotEmail').value.trim();
    if (!email) {
        showToast('الرجاء إدخال البريد الإلكتروني', 'error');
        return;
    }
    const r = await API.post('forgot_password', { email });
    if (r && r.success) {
        showToast(r.message || 'تم الإرسال');
        document.getElementById('forgotPasswordContainer').classList.add('hidden');
        document.getElementById('resetPasswordContainer').classList.remove('hidden');
        document.getElementById('resetEmail').value = email;
    } else {
        showToast(r?.error || 'حدث خطأ', 'error');
    }
}

async function handleResetPassword() {
    const email = document.getElementById('resetEmail').value.trim();
    const otp = document.getElementById('resetOTP').value.trim();
    const newPassword = document.getElementById('resetNewPassword').value;

    if (!otp || !newPassword) {
        showToast('الرجاء تعبئة جميع الحقول', 'error');
        return;
    }
    if (newPassword.length < 6) {
        showToast('كلمة المرور يجب أن تكون 6 أحرف على الأقل', 'error');
        return;
    }

    const r = await API.post('reset_password', { email, otp, new_password: newPassword });
    if (r && r.success) {
        showToast(r.message || 'تم تعيين كلمة المرور بنجاح');
        toggleForgotPassword(false);
        document.getElementById('loginPassword').value = '';
    } else {
        showToast(r?.error || 'رمز الاستعادة غير صحيح أو منتهي', 'error');
    }
}

// ============================================================
// NOTE: checkAuth() is called from index.html AFTER all JS files load
// ============================================================
console.log('✅ common.js loaded');

/**
 * Professional Triple Header for Reports
 * Right: Ministry Info | Middle: School Name & Report Title | Left: School Logo
 */
function getReportHeaderHTML(title = 'تقرير رياضي شامل') {
    const schoolName = (currentSchool && currentSchool.name) ? currentSchool.name : 'مدرستنا الذكية';
    const logoUrl = (currentSchool && currentSchool.logo) ? currentSchool.logo : '';

    return `
    <div class="print-only mb-10 pb-8 border-b-4 border-double border-gray-300">
        <div class="flex items-center justify-between text-gray-900">
            <!-- Right: Official Ministry Info -->
            <div class="w-1/3 text-right">
                <div class="font-black text-[11px] leading-relaxed">
                    المملكة العربية السعودية<br>
                    وزارة التعليم<br>
                    إدارة التربية والتعليم بمحافظتكم
                </div>
            </div>
            
            <!-- Middle: School Identity & Report Title -->
            <div class="w-1/3 text-center">
                <div class="w-12 h-12 bg-gray-900 text-white rounded-full flex items-center justify-center mx-auto mb-2 text-xl">🏃</div>
                <h1 class="text-xl font-black mb-1">${esc(schoolName)}</h1>
                <!-- Fix #13: Title is escaped to prevent XSS via user-controlled report titles -->
                <div class="inline-block border-2 border-gray-900 px-4 py-1 rounded-lg text-xs font-black uppercase tracking-widest">${esc(title)}</div>
            </div>
            
            <!-- Left: School Logo -->
            <div class="w-1/3 flex justify-end">
                ${logoUrl ? `<img src="${logoUrl}" class="h-24 w-auto object-contain">` : `
                    <div class="w-24 h-24 bg-gray-50 border-2 border-dashed border-gray-200 rounded-3xl flex items-center justify-center text-center p-2">
                        <span class="text-[10px] font-black text-gray-300">ختم / شعار المدرسة</span>
                    </div>
                `}
            </div>
        </div>
        <div class="mt-4 flex justify-between items-center text-[10px] font-bold text-gray-400">
            <span>التاريخ: ${new Date().toLocaleDateString('ar-SA')}</span>
            <span>نظام التربية البدنية الذكي - PE Smart School</span>
        </div>
    </div>
    `;
}
/**
 * SaaS: Load and Display System Announcements
 */
async function loadAnnouncements() {
    const container = document.getElementById('systemAnnouncements');
    if (!container) return;

    try {
        const r = await API.get('get_active_announcements');
        if (!r || !r.success || !r.data || r.data.length === 0) {
            container.innerHTML = '';
            return;
        }

        const dismissed = JSON.parse(localStorage.getItem('dismissedAnnouncements') || '[]');
        const activeAnnouncements = r.data.filter(a => !dismissed.includes(a.id));

        if (activeAnnouncements.length === 0) {
            container.innerHTML = '';
            return;
        }

        container.innerHTML = activeAnnouncements.map(a => {
            const colors = {
                info: 'bg-cyan-600',
                warning: 'bg-orange-500',
                danger: 'bg-red-600',
                success: 'bg-emerald-600'
            };
            const bgColor = colors[a.type] || colors.info;

            return `
                <div id="announcement-${a.id}" class="${bgColor} text-white px-6 py-3 flex items-start justify-between shadow-lg relative z-40 animate-slide-down">
                    <div class="flex gap-3">
                        <span class="text-xl">📢</span>
                        <div>
                            <div class="font-black text-sm uppercase tracking-wider mb-0.5">${esc(a.title)}</div>
                            <div class="text-sm opacity-95 leading-relaxed">${esc(a.message)}</div>
                        </div>
                    </div>
                    <button onclick="dismissAnnouncement(${a.id})" class="mt-1 hover:bg-white/20 p-1 rounded-full transition-colors" title="إغلاق">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            `;
        }).join('');

    } catch (e) {
        console.error('Error loading announcements:', e);
    }
}

function dismissAnnouncement(id) {
    const element = document.getElementById(`announcement-${id}`);
    if (element) {
        element.style.maxHeight = element.scrollHeight + 'px';
        setTimeout(() => {
            element.style.transition = 'all 0.4s ease';
            element.style.maxHeight = '0';
            element.style.opacity = '0';
            element.style.paddingTop = '0';
            element.style.paddingBottom = '0';
            element.style.marginTop = '0';
            element.style.marginBottom = '0';
            element.style.overflow = 'hidden';
            setTimeout(() => element.remove(), 400);
        }, 10);
    }

    const dismissed = JSON.parse(localStorage.getItem('dismissedAnnouncements') || '[]');
    if (!dismissed.includes(id)) {
        dismissed.push(id);
        localStorage.setItem('dismissedAnnouncements', JSON.stringify(dismissed));
    }
}
/**
 * MAINTENANCE HANDLER
 */
function handleMaintenance(data) {
    const overlay = document.getElementById('maintenanceOverlay');
    if (!overlay) return;

    document.getElementById('maintenanceMessage').innerText = data.message || 'المنصة في صيانة حالياً، سنعود قريباً.';

    if (data.until) {
        document.getElementById('maintenanceUntilRow').style.display = 'inline-flex';
        document.getElementById('maintenanceUntilText').innerText = data.until;
    } else {
        document.getElementById('maintenanceUntilRow').style.display = 'none';
    }

    overlay.style.display = 'flex';

    // Hide all other main components
    const login = document.getElementById('loginPage');
    if (login) login.classList.add('hidden');

    const app = document.getElementById('mainApp');
    if (app) app.classList.add('hidden');

    // Disable any background tasks
    if (window.announcementTimer) clearInterval(window.announcementTimer);
}

/**
 * DEACTIVATION HANDLER
 */
function handleDeactivation(data) {
    const overlay = document.getElementById('deactivationOverlay');
    if (!overlay) return;

    if (data.error) {
        document.getElementById('deactivationMessage').innerText = data.error;
    }

    overlay.style.display = 'flex';

    // Hide all other main components
    const login = document.getElementById('loginPage');
    if (login) login.classList.add('hidden');

    const app = document.getElementById('mainApp');
    if (app) app.classList.add('hidden');

    // Hide maintenance if both active?
    const maintenance = document.getElementById('maintenanceOverlay');
    if (maintenance) maintenance.style.display = 'none';

    // Disable any background tasks
    if (window.announcementTimer) clearInterval(window.announcementTimer);
}
