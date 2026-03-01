/**
 * PE Smart School System - Common JavaScript
 * Shared functions: API, Auth, Navigation, Utilities
 */

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
    fitness: 'renderFitness',
    competition: 'renderCompetition',
    tournaments: 'renderTournaments',
    sportsTeams: 'renderSportsTeams',
    reports: 'renderReports',
    users: 'renderUsers',
    parents: 'renderParents',
    notifications: 'renderNotifications',
    badgesAdmin: 'renderBadgeManagementPage',
    user_profile: 'renderUserProfilePage',
    studentDashboard: 'renderStudentDashboard',
    studentProfile: 'renderStudentProfilePage',
    parentDashboard: 'renderParentDashboard',
    sportsCalendar: 'renderSportsCalendar',
    analytics: 'renderAnalytics',
    audit_logs: 'renderAuditLog'
};

// ============================================================
// API HELPER
// ============================================================
const API = {
    base: 'api.php',

    async request(action, method = 'GET', data = null, params = {}) {
        try {
            let url = `${this.base}?action=${action}`;
            Object.entries(params).forEach(([k, v]) => {
                if (v !== null && v !== undefined && v !== '') {
                    url += `&${k}=${encodeURIComponent(v)}`;
                }
            });

            const options = { method, headers: {} };
            if (data && method !== 'GET') {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(data);
            }

            const r = await fetch(url, options);
            const result = await r.json();

            if (!r.ok && r.status === 401) {
                showLoginPage();
                return null;
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
    if (!r) return;

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
        showApp();
    } else {
        errorEl.textContent = r.error || 'بيانات غير صحيحة';
        errorEl.classList.remove('hidden');
    }
}

async function handleLogout() {
    await API.post('logout');
    currentUser = null;
    currentSchool = null;
    showLoginPage();
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
                    select.innerHTML += `<option value="${s.slug}">${s.name}${s.city ? ' - ' + s.city : ''}</option>`;
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
    if (initEl) initEl.textContent = (currentUser.name || '?').charAt(0);

    const roleNames = { admin: 'مدير', teacher: 'معلم', viewer: 'مشاهد', supervisor: 'مشرف/موجه', student: 'طالب', parent: 'ولي أمر' };
    if (roleEl) roleEl.textContent = roleNames[currentUser.role] || currentUser.role;

    // SaaS: Display school name + plan in header
    const schoolNameEl = document.getElementById('schoolNameDisplay');
    if (schoolNameEl && currentSchool && currentSchool.name) {
        schoolNameEl.textContent = currentSchool.name;
        schoolNameEl.classList.remove('hidden');
    }

    // Show/hide menu items based on role
    document.querySelectorAll('[data-role]').forEach(el => {
        const allowedRoles = el.dataset.role.split(',');
        el.style.display = allowedRoles.includes(currentUser.role) ? '' : 'none';
    });

    // Determine starting page (hash-based OR role-based default)
    const hash = window.location.hash.replace('#', '');
    if (hash && PAGE_MAP[hash]) {
        navigateTo(hash);
    } else if (currentUser.role === 'student') {
        navigateTo('studentDashboard');
    } else {
        navigateTo('dashboard');
    }

    // Start notification polling for parents
    if (typeof startNotificationPolling === 'function') {
        startNotificationPolling();
    }
}

// SaaS: Check if a feature is available in current plan
function hasFeature(feature) {
    if (!currentUser || !currentUser.subscription) return true;
    const features = currentUser.subscription.plan_features;
    if (!features) return true;
    try {
        const parsed = typeof features === 'string' ? JSON.parse(features) : features;
        return parsed[feature] !== false;
    } catch (e) {
        return true;
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

// ============================================================
// NAVIGATION
// ============================================================
function navigateTo(page) {
    // Role-aware dashboard redirection
    if (page === 'dashboard' && currentUser) {
        if (currentUser.role === 'student') page = 'studentDashboard';
        if (currentUser.role === 'parent') page = 'parentDashboard';
    }

    // Students should see their Fitness Profile instead of the Staff CV
    if (page === 'user_profile' && currentUser && currentUser.role === 'student') {
        window._profileStudentId = currentUser.id;
        page = 'studentProfile';
    }

    currentPage = page;
    window.location.hash = page;

    // Update sidebar active state
    document.querySelectorAll('.sidebar-link').forEach(link => {
        link.classList.remove('active');
        const onclick = link.getAttribute('onclick') || '';

        // Match exact page OR 'dashboard' if we redirected to studentDashboard/parentDashboard
        if (onclick.includes("'" + page + "'") ||
            ((page === 'studentDashboard' || page === 'parentDashboard') && onclick.includes("'dashboard'"))) {
            link.classList.add('active');
        }
    });

    // Close mobile sidebar
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) {
        sidebar.classList.add('-right-64');
        sidebar.classList.remove('right-0');
    }
    if (overlay) {
        overlay.classList.add('hidden');
    }

    // Safe function checker - prevents ReferenceError
    function safeGetFunction(name) {
        try {
            const fn = window[name];
            return typeof fn === 'function' ? fn : null;
        } catch (e) {
            return null;
        }
    }

    const funcName = PAGE_MAP[page];
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
                            <p class="text-gray-500">${e.message}</p>
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
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        info: 'bg-blue-500'
    };
    const icons = {
        success: '✅',
        error: '❌',
        info: 'ℹ️'
    };

    const toast = document.createElement('div');
    toast.className = `toast ${colors[type]} text-white px-5 py-3 rounded-xl shadow-lg flex items-center gap-2 text-sm font-semibold`;
    toast.innerHTML = `<span>${icons[type]}</span><span>${msg}</span>`;
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
        } else {
            handleLogin(); // fallback
        }
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
