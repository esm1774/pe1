/**
 * PE Smart School System - Common JavaScript
 * Shared functions: API, Auth, Navigation, Utilities
 */

// SaaS/Localhost: Auto-detect project root
window.APP_BASE = window.APP_BASE || (function () {
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

            // Add school context if available
            if (window.SCHOOL_SLUG) {
                options.headers['X-School-Slug'] = window.SCHOOL_SLUG;
            }

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
                    if (result?.error && result.error.includes('معطل')) {
                        handleDeactivation(result);
                    } else {
                        showToast(result?.error || 'ليس لديك صلاحية', 'error');
                        // Optionally redirect to dashboard if they are completely blocked
                    }
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
let currentSchool = null; // SaaS: current school context
window.formIsDirty = false; // Flag to prevent data loss on navigate

// Fix: Catch accidental browser tab/page close or refresh
window.addEventListener('beforeunload', (e) => {
    if (window.formIsDirty) {
        // MODERN BROWSER STANDARD
        e.preventDefault();
        e.returnValue = 'هل أنت متأكد من المغادرة؟ قد تفقد البيانات غير المحفوظة.'; 
        return e.returnValue;
    }
});

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
        return;
    }

    if (r.success) {
        // Multi-School Handling
        if (r.data.requires_school_selection) {
            showSchoolPicker(r.data.schools, username, password);
            return;
        }

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
            const root = window.APP_ROOT || '/';
            const hash = window.location.hash || '#dashboard';
            window.location.href = root + r.data.school_slug + '/' + hash;
        } else {
            showApp();
            checkPostLoginRequirements(r.data);
        }
    } else {
        errorEl.textContent = r.error || 'بيانات غير صحيحة';
        errorEl.classList.remove('hidden');
    }
}

/**
 * Handle requirements after login (e.g. mandatory email, password change)
 */
function checkPostLoginRequirements(user) {
    if (user.must_change_password) {
        setTimeout(() => showToast('⚠️ يرجى تغيير كلمة المرور الافتراضية لحماية حسابك', 'info'), 3000);
    }

    // Mandatory Email Check for staff
    if (['admin', 'teacher', 'supervisor'].includes(user.role) && !user.email) {
        showMandatoryEmailModal();
    }
}

/**
 * Show a modal for users with access to multiple schools
 */
function showSchoolPicker(schools, username, password) {
    const modalHtml = `
        <div id="schoolPickerModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 animate-fade-in">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">🏢</div>
                    <h3 class="text-xl font-bold text-gray-900">اختر المدرسة</h3>
                    <p class="text-gray-500 text-sm mt-1">حسابك مرتبط بأكثر من مدرسة، يرجى اختيار المدرسة المراد الدخول إليها:</p>
                </div>
                
                <div class="space-y-3 max-h-60 overflow-y-auto mb-6 pr-2">
                    ${schools.map(s => `
                        <button onclick="selectSchoolAndLogin('${s.slug}', '${username}', '${password}', ${s.id})" 
                                class="w-full flex items-center p-3 border-2 border-gray-100 rounded-xl hover:border-blue-500 hover:bg-blue-50 transition-all text-right group">
                            <span class="flex-1 font-semibold text-gray-700 group-hover:text-blue-700">${s.name}</span>
                            <span class="text-xs bg-gray-100 text-gray-500 px-2 py-1 rounded-lg group-hover:bg-blue-100 group-hover:text-blue-600">${s.role === 'admin' ? 'مدير' : 'معلم'}</span>
                        </button>
                    `).join('')}
                </div>
                
                <button onclick="document.getElementById('schoolPickerModal').remove()" class="w-full py-2 text-gray-400 hover:text-gray-600 font-medium transition-colors">إلغاء</button>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}



/**
 * Show modal to force staff members to provide an email
 */
function showMandatoryEmailModal() {
    const modalHtml = `
        <div id="emailEnforcementModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 animate-fade-in">
                <div class="text-center mb-6">
                    <div class="w-20 h-20 bg-yellow-100 text-yellow-600 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">📧</div>
                    <h3 class="text-2xl font-bold text-gray-900">تحديث البريد الإلكتروني</h3>
                    <p class="text-gray-600 mt-2">يرجى إضافة بريدك الإلكتروني لتتمكن من استعادة حسابك في حال فقدان كلمة المرور ولتصلك إشعارات المنصة.</p>
                </div>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">البريد الإلكتروني <span class="text-red-500">*</span></label>
                        <input type="email" id="enforcedEmail" placeholder="example@domain.com" 
                               class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-center" dir="ltr">
                    </div>
                    
                    <button onclick="saveEnforcedEmail()" id="btnSaveEmail"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-xl shadow-lg transform hover:-translate-y-0.5 transition-all">
                        حفظ ومتابعة
                    </button>
                    
                    <p class="text-xs text-center text-gray-400">لن نتمكن من إرسال تنبيهات هامة لك بدون بريد إلكتروني صحيح.</p>
                </div>
            </div>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

window.saveEnforcedEmail = async () => {
    const email = document.getElementById('enforcedEmail').value.trim();
    if (!email || !email.includes('@')) {
        showToast('يرجى إدخال بريد إلكتروني صحيح', 'error');
        return;
    }

    const btn = document.getElementById('btnSaveEmail');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin ml-2"></i> جاري الحفظ...';

    const r = await API.post('update_my_profile', { email: email });
    if (r && r.success) {
        showToast('تم تحديث البريد الإلكتروني بنجاح', 'success');
        document.getElementById('emailEnforcementModal').remove();
        if (currentUser) currentUser.email = email;
    } else {
        showToast(r?.error || 'فشل تحديث البريد الإلكتروني', 'error');
        btn.disabled = false;
        btn.textContent = 'حفظ ومتابعة';
    }
};

async function handleLogout() {
    // Fix #9: Stop notification polling before clearing session
    if (typeof stopNotificationPolling === 'function') {
        stopNotificationPolling();
    }
    await API.post('logout');
    currentUser = null;
    currentSchool = null;

    // Redirect to root home instead of staying on the school slug path
    // This prevents accidental re-login on refresh and cleans the URL
    window.location.href = window.APP_BASE;
}

async function exitImpersonation() {
    try {
        const r = await API.post('exit_impersonation');
        if (r && r.success) {
            window.location.href = window.APP_BASE + 'admin/';
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
        const expectedSlugSegment = `/${currentSchool.slug}/`;

        let targetSlugPath;
        if (window.APP_BASE.endsWith(expectedSlugSegment)) {
            targetSlugPath = window.APP_BASE;
        } else {
            targetSlugPath = `${window.APP_BASE}${currentSchool.slug}/`;
        }

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
            // Check if sibling is a link and is not hidden by the logic above
            if (sibling.classList.contains('sidebar-link') && sibling.style.display !== 'none') {
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

    // SaaS: Multi-School Check
    checkMultiSchoolLink();
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
    'assessments': 'assessments_bank',
};

// ============================================================
// NAVIGATION
// ============================================================
function navigateTo(page, event = null) {
    // If an actual event is passed (from an onclick), prevent default browser navigation
    if (event) {
        if (typeof event.preventDefault === 'function') event.preventDefault();
        if (typeof event.stopPropagation === 'function') event.stopPropagation();
    }

    // Unsaved changes protection
    if (window.formIsDirty) {
        if (!confirm('لديك تغييرات غير محفوظة، هل أنت متأكد من مغادرة هذه الصفحة؟')) {
            return false;
        }
        window.formIsDirty = false;
    }

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
    window.onscroll = null; // Cleanup scroll listeners from previous pages

    const parts = page.split('/');
    const basePage = parts[0];
    const params = parts.slice(1);

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

    const funcName = PAGE_MAP[basePage];
    const renderer = funcName ? safeGetFunction(funcName) : null;

    if (renderer) {
        try {
            const result = renderer(...params);
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
// NOTE: checkAuth() is called from app.html AFTER all JS files load
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

/**
 * Switch current school for multi-school users
 */
window.triggerSchoolSwitch = async () => {
    if (!currentUser) return;

    // Fetch user schools
    const r = await API.get('check_auth', { include_schools: 1 });
    if (r && r.success && r.data.schools && r.data.schools.length > 1) {
        showSchoolPicker(r.data.schools, currentUser.username, '');
    } else {
        showToast('لا توجد مدارس أخرى مرتبطة بهذا الحساب', 'info');
    }
};

/**
 * Select a specific school and update context
 */
window.selectSchoolAndLogin = async (slug, username, password, schoolId = null) => {
    const modal = document.getElementById('schoolPickerModal');
    if (modal) modal.remove();

    // If password is not provided (from switcher), we use a session-based switch endpoint
    if (!password) {
        // Find school ID from slug if not provided
        if (!schoolId) {
            const rCheck = await API.get('check_auth', { include_schools: 1 });
            const school = rCheck.data.schools.find(s => s.slug === slug);
            if (school) schoolId = school.id;
        }

        if (schoolId) {
            const rSwitch = await API.post('switch_school', { school_id: schoolId });
            if (rSwitch && rSwitch.success) {
                const root = window.APP_ROOT || '/';
                window.location.href = root + slug + '/#dashboard';
                return;
            }
        }
        showToast('فشل تبديل المدرسة', 'error');
        return;
    }

    // Traditional login with school selector
    const r = await API.post('login', { username, password, school: slug });
    if (r && r.success) {
        currentUser = r.data;
        if (r.data.school_slug) {
            const root = window.APP_ROOT || '/';
            window.location.href = root + r.data.school_slug + '/#dashboard';
        } else {
            showApp();
        }
    } else {
        showToast(r?.error || 'فشل تسجيل الدخول', 'error');
    }
};

/**
 * Check if the user has multiple schools to show the switcher
 */
function checkMultiSchoolLink() {
    if (!currentUser || currentUser.role === 'student' || currentUser.role === 'parent') return;

    API.get('check_auth', { include_schools: 1 }).then(r => {
        if (r && r.success && r.data.schools && r.data.schools.length > 1) {
            const menu = document.getElementById('switchSchoolMenuItem');
            if (menu) menu.classList.remove('hidden');
        }
    });
}
/**
 * Password Validator UI Helper
 * Enforces strong password criteria with real-time feedback
 */
class PasswordValidator {
    constructor(config) {
        this.input = document.getElementById(config.inputId);
        this.confirmInput = document.getElementById(config.confirmId);
        this.container = document.getElementById(config.containerId);
        this.rules = [
            { id: 'length', text: '8 أحرف على الأقل', regex: /.{8,}/ },
            { id: 'upper', text: 'حرف كبير واحد (A-Z)', regex: /[A-Z]/ },
            { id: 'lower', text: 'حرف صغير واحد (a-z)', regex: /[a-z]/ },
            { id: 'number', text: 'رقم واحد على الأقل (0-9)', regex: /[0-9]/ },
            { id: 'symbol', text: 'رمز خاص واحد (@#$%)', regex: /[\W_]/ }
        ];

        if (this.input) {
            this.init();
        }
    }

    init() {
        // Create UI HTML
        let html = `<div class="password-checklist">`;
        this.rules.forEach(rule => {
            html += `<div class="rule-item" data-rule="${rule.id}"><span class="bullet">○</span> ${rule.text}</div>`;
        });
        if (this.confirmInput) {
            html += `<div class="rule-item" data-rule="match"><span class="bullet">○</span> تطابق كلمتي المرور</div>`;
        }
        html += `</div>`;

        if (this.container) {
            this.container.innerHTML = html;
        }

        this.input.addEventListener('input', () => this.validate());
        if (this.confirmInput) {
            this.confirmInput.addEventListener('input', () => this.validate());
        }
    }

    validate() {
        const val = this.input.value;
        const confirmVal = this.confirmInput ? this.confirmInput.value : null;
        let allValid = true;

        this.rules.forEach(rule => {
            const el = this.container.querySelector(`[data-rule="${rule.id}"]`);
            const isValid = rule.regex.test(val);
            this.updateUI(el, isValid);
            if (!isValid) allValid = false;
        });

        if (this.confirmInput) {
            const el = this.container.querySelector(`[data-rule="match"]`);
            const isMatch = val === confirmVal && val.length > 0;
            this.updateUI(el, isMatch);
            if (!isMatch) allValid = false;
        }

        return allValid;
    }

    updateUI(el, isValid) {
        if (!el) return;
        const bullet = el.querySelector('.bullet');
        if (isValid) {
            el.classList.add('valid');
            bullet.innerText = '✅';
        } else {
            el.classList.remove('valid');
            bullet.innerText = '○';
        }
    }
}

// Global expose
window.PasswordValidator = PasswordValidator;

/**
 * XSS Protection: Escape HTML characters
 */
function escapeHTML(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
window.escapeHTML = escapeHTML;
