/**
 * PE Smart School - Super Admin Panel
 * ====================================
 * Full SPA for platform management
 */

// ============================================================
// API
// ============================================================
const API = {
    base: 'api.php',

    async request(action, method = 'GET', data = null) {
        try {
            let url = `${this.base}?action=${action}`;
            const options = { method, headers: {} };
            if (data && method !== 'GET') {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(data);
            }
            const r = await fetch(url, options);
            const result = await r.json();
            if (!r.ok && r.status === 401) {
                showLogin();
                return null;
            }
            return result;
        } catch (e) {
            console.error('API Error:', e);
            toast('خطأ في الاتصال بالخادم', 'error');
            return null;
        }
    },

    get(action) { return this.request(action, 'GET'); },
    post(action, data) { return this.request(action, 'POST', data); }
};

// ============================================================
// STATE
// ============================================================
let adminUser = null;
let currentPage = 'dashboard';
let cachedPlans = [];
let quill = null;

// ============================================================
// THEME
// ============================================================
function initTheme() {
    const saved = localStorage.getItem('theme_admin') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
    updateThemeIcon(saved);
}

function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme');
    const target = current === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', target);
    localStorage.setItem('theme_admin', target);
    updateThemeIcon(target);
}

function updateThemeIcon(theme) {
    const btn = document.getElementById('themeToggleBtn');
    if (btn) {
        btn.innerHTML = theme === 'dark' ? '☀️' : '🌙';
        btn.title = theme === 'dark' ? 'الوضع الفاتح' : 'الوضع الداكن';
    }
}
initTheme();

// ============================================================
// AUTH
// ============================================================
async function handleLogin() {
    const username = document.getElementById('loginUsername').value.trim();
    const password = document.getElementById('loginPassword').value.trim();
    const errorEl = document.getElementById('loginError');

    if (!username || !password) {
        errorEl.textContent = 'الرجاء إدخال البيانات';
        errorEl.classList.remove('hidden');
        return;
    }

    const r = await API.post('platform_login', { username, password });
    if (!r) return;

    if (r.success) {
        adminUser = r.data;
        errorEl.classList.add('hidden');
        showApp();
    } else {
        errorEl.textContent = r.error || 'بيانات غير صحيحة';
        errorEl.classList.remove('hidden');
    }
}

async function handleLogout() {
    await API.post('platform_logout');
    adminUser = null;
    showLogin();
}

async function checkAuth() {
    const r = await API.get('platform_check');
    if (r && r.success) {
        adminUser = r.data;
        showApp();
    }
}

function showLogin() {
    document.getElementById('loginPage').style.display = '';
    document.getElementById('adminApp').classList.remove('active');
}

function showApp() {
    document.getElementById('loginPage').style.display = 'none';
    document.getElementById('adminApp').classList.add('active');
    document.getElementById('adminNameDisplay').textContent = adminUser.name;
    navigate('dashboard');
}

// ============================================================
// NAVIGATION
// ============================================================
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.toggle('open');
    if (overlay) overlay.classList.toggle('active');
}

function navigate(page) {
    currentPage = page;
    document.querySelectorAll('.sidebar-link').forEach(el => {
        el.classList.toggle('active', el.dataset.page === page);
    });

    // Close mobile sidebar if open
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar && sidebar.classList.contains('open')) {
        sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
    }

    const renderers = {
        dashboard: renderDashboard,
        schools: renderSchools,
        plans: renderPlans,
        announcements: renderAnnouncements,
        audit_logs: renderGlobalLogs,
        analytics: renderAdvancedAnalytics,
        settings: renderPlatformSettings,
        blog: renderBlog,
        media: renderMedia,
        health: renderHealth
    };

    if (renderers[page]) renderers[page]();
}

// ============================================================
// DASHBOARD
// ============================================================
async function renderDashboard() {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = '<div class="spinner"></div>';

    const r = await API.get('platform_stats');
    if (!r || !r.success) {
        mc.innerHTML = '<div class="empty-state"><div class="icon">⚠️</div><p>خطأ في جلب البيانات</p></div>';
        return;
    }

    const s = r.data;
    mc.innerHTML = `
        <div class="page-header">
            <h1>📊 لوحة المعلومات</h1>
            <p>نظرة عامة على المنصة</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card emerald">
                <div class="stat-icon" style="background:rgba(16,185,129,0.15)">🏫</div>
                <div class="stat-value">${s.total_schools}</div>
                <div class="stat-label">إجمالي المدارس</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon" style="background:rgba(16,185,129,0.15)">👨‍🎓</div>
                <div class="stat-value">${s.total_students}</div>
                <div class="stat-label">إجمالي الطلاب</div>
            </div>
            <div class="stat-card cyan">
                <div class="stat-icon" style="background:rgba(6,182,212,0.15)">👨‍🏫</div>
                <div class="stat-value">${s.total_teachers}</div>
                <div class="stat-label">إجمالي المعلمين</div>
            </div>
            <div class="stat-card green">
                <div class="stat-icon" style="background:rgba(16,185,129,0.15)">✅</div>
                <div class="stat-value">${s.active_subscriptions}</div>
                <div class="stat-label">اشتراكات نشطة</div>
            </div>
            <div class="stat-card orange">
                <div class="stat-icon" style="background:rgba(245,158,11,0.15)">⏳</div>
                <div class="stat-value">${s.trial_subscriptions}</div>
                <div class="stat-label">فترة تجريبية</div>
            </div>
            <div class="stat-card pink">
                <div class="stat-icon" style="background:rgba(239,68,68,0.15)">⛔</div>
                <div class="stat-value">${s.suspended_subscriptions}</div>
                <div class="stat-label">اشتراكات معلقة</div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h3>⚡ إجراءات سريعة</h3>
            </div>
            <div class="panel-body" style="display:flex;gap:12px;flex-wrap:wrap">
                <button class="btn btn-emerald btn-sm" onclick="navigate('schools')">🏫 إدارة المدارس</button>
                <button class="btn btn-cyan btn-sm" onclick="navigate('plans')">💎 إدارة الخطط</button>
                <button class="btn btn-success btn-sm" onclick="openAddSchoolModal()">➕ إضافة مدرسة</button>
                <button class="btn btn-outline btn-sm" onclick="window.location.href='api.php?action=export_subscribers'">📥 تصدير الإيميلات</button>
            </div>
        </div>
    `;
}

// ============================================================
// SCHOOLS
// ============================================================
async function renderSchools() {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = '<div class="spinner"></div>';

    const [schoolsRes, plansRes] = await Promise.all([
        API.get('schools'),
        API.get('plans')
    ]);

    if (!schoolsRes || !schoolsRes.success) {
        mc.innerHTML = '<div class="empty-state"><div class="icon">⚠️</div><p>خطأ في جلب المدارس</p></div>';
        return;
    }

    cachedPlans = plansRes?.data || [];
    const schools = schoolsRes.data;

    mc.innerHTML = `
        <div class="page-header">
            <h1>🏫 إدارة المدارس</h1>
            <p>إضافة وتعديل المدارس وإدارة اشتراكاتها والتحكم فـي صلاحيات الدخول.</p>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h3>📋 سجل المدارس (${schools.length})</h3>
                <button class="btn btn-emerald btn-sm" onclick="openAddSchoolModal()">➕ إضافة مدرسة</button>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم المدرسة</th>
                            <th>المعرف</th>
                            <th>تاريخ الانضمام</th>
                            <th>الخطة الحالية</th>
                            <th>الاشتراك</th>
                            <th>الطلاب / المعلمون</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="blogTableBody">
                        ${schools.length === 0 ? '<tr><td colspan="9"><div class="empty-state"><div class="icon">🏫</div><p>لا توجد مدارس بعد</p></div></td></tr>' : ''}
                        ${schools.map(s => `
                            <tr>
                                <td>${s.id}</td>
                                <td>
                                    <strong>${esc(s.name)}</strong>
                                    ${s.city ? `<span style="display:block;font-size:10px;color:var(--text-muted)">📍 ${esc(s.city)}</span>` : ''}
                                </td>
                                <td><code style="color:var(--accent-emerald);font-size:11px">${esc(s.slug)}</code></td>
                                <td style="font-size:12px;color:var(--text-muted)">${s.created_at ? s.created_at.split(' ')[0] : '—'}</td>
                                <td>
                                    <div style="font-size:12px;font-weight:600">${s.plan_name || '<small style="color:var(--text-muted)">بدون خطة</small>'}</div>
                                    <small style="font-size:9px;color:var(--text-muted)">نهاية الخدمة: ${s.subscription_ends_at || s.trial_ends_at || '—'}</small>
                                </td>
                                <td>${subBadge(s.subscription_status)}</td>
                                <td style="font-size:12px">
                                    <span style="color:var(--accent-orange)">👤 ${s.student_count || 0} / ${s.max_students || '∞'}</span><br>
                                    <span style="color:var(--accent-cyan)">👨‍🏫 ${s.user_count || 0} / ${s.max_teachers || '∞'}</span>
                                </td>
                                <td>${s.active == 1 ? '<span class="badge badge-active">نشطة</span>' : '<span class="badge badge-inactive">معطلة</span>'}</td>
                                <td>
                                    <div class="action-btns">
                                        <button class="action-btn edit" onclick="openEditSchoolModal(${s.id})" title="تعديل البيانات">✏️</button>
                                        <button class="action-btn sub" onclick="openSubModal(${s.id})" title="الاشتراك والميزات">💳</button>
                                        <button class="action-btn enter" onclick="impersonateSchool(${s.id})" title="دخول سريع">🔑</button>
                                        <button class="action-btn" style="background:var(--accent-orange);color:white" onclick="openResetAdminPasswordModal(${s.id}, '${esc(s.name)}')" title="تغيير كلمة مرور المدير">🔒</button>
                                        ${s.active == 1
            ? `<button class="action-btn toggle-off" onclick="toggleSchool(${s.id}, 0)" title="إيقاف المؤقت">⏸️</button>`
            : `<button class="action-btn toggle-on" onclick="toggleSchool(${s.id}, 1)" title="تفعيل">▶️</button>`
        }
                                        <button class="action-btn delete" onclick="deleteSchool(${s.id}, '${esc(s.name)}')" title="حذف نهائي">🗑️</button>
                                    </div>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;
}

function subBadge(status) {
    const map = {
        active: ['badge-active', '✅ نشط'],
        trial: ['badge-trial', '⏳ تجريبي'],
        suspended: ['badge-suspended', '⛔ معلق'],
        expired: ['badge-expired', '⌛ منتهي']
    };
    const [cls, label] = map[status] || ['badge-inactive', '—'];
    return `<span class="badge ${cls}">${label}</span>`;
}

function openAddSchoolModal() {
    const planOptions = cachedPlans.map(p => `<option value="${p.id}">${esc(p.name)}</option>`).join('');
    openModal(`
        <div class="modal-header">
            <h3>➕ إضافة مدرسة جديدة</h3>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-row">
                <div class="form-group-modal">
                    <label>اسم المدرسة *</label>
                    <input type="text" id="schoolName" class="form-input" placeholder="مثال: مدرسة النور">
                </div>
                <div class="form-group-modal">
                    <label>المعرف (slug) *</label>
                    <input type="text" id="schoolSlug" class="form-input" placeholder="alnoor" dir="ltr">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group-modal">
                    <label>البريد الإلكتروني</label>
                    <input type="email" id="schoolEmail" class="form-input" placeholder="info@school.com" dir="ltr">
                </div>
                <div class="form-group-modal">
                    <label>الجوال</label>
                    <input type="text" id="schoolPhone" class="form-input" placeholder="05xxxxxxxx" dir="ltr">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group-modal">
                    <label>المدينة</label>
                    <input type="text" id="schoolCity" class="form-input" placeholder="الرياض">
                </div>
                <div class="form-group-modal">
                    <label>المنطقة</label>
                    <input type="text" id="schoolRegion" class="form-input" placeholder="منطقة الرياض">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group-modal">
                    <label>الخطة</label>
                    <select id="schoolPlan" class="form-select">
                        <option value="">— بدون خطة —</option>
                        ${planOptions}
                    </select>
                </div>
                <div class="form-group-modal">
                    <label>الحد الأقصى للطلاب</label>
                    <input type="number" id="schoolMaxStudents" class="form-input" value="100">
                </div>
            </div>
            <div class="form-group-modal">
                <label>الحد الأقصى للمعلمين</label>
                <input type="number" id="schoolMaxTeachers" class="form-input" value="5">
            </div>
            <hr style="border-color:var(--border-glass);margin:20px 0">
            <h4 style="font-size:14px;margin-bottom:12px;color:var(--accent-emerald)">🔑 حساب مدير المدرسة</h4>
            <div class="form-row">
                <div class="form-group-modal">
                    <label>اسم المستخدم للمدير</label>
                    <input type="text" id="adminUsername" class="form-input" placeholder="admin" dir="ltr">
                </div>
                <div class="form-group-modal">
                    <label>كلمة المرور</label>
                    <input type="password" id="adminPassword" class="form-input" placeholder="••••••">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-emerald btn-sm" onclick="saveSchool()">💾 حفظ المدرسة</button>
            <button class="btn btn-outline btn-sm" onclick="closeModal()">إلغاء</button>
        </div>
    `);
}

async function openEditSchoolModal(id) {
    const r = await API.get('schools');
    if (!r || !r.success) return;
    const school = r.data.find(s => s.id == id);
    if (!school) return toast('المدرسة غير موجودة', 'error');

    const planOptions = cachedPlans.map(p =>
        `<option value="${p.id}" ${school.plan_id == p.id ? 'selected' : ''}>${esc(p.name)}</option>`
    ).join('');

    openModal(`
        <div class="modal-header">
            <h3>✏️ تعديل المدرسة: ${esc(school.name)}</h3>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="schoolId" value="${school.id}">
            <div class="form-row">
                <div class="form-group-modal">
                    <label>اسم المدرسة *</label>
                    <input type="text" id="schoolName" class="form-input" value="${esc(school.name)}">
                </div>
                <div class="form-group-modal">
                    <label>المعرف (slug) *</label>
                    <input type="text" id="schoolSlug" class="form-input" value="${esc(school.slug)}" dir="ltr">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group-modal">
                    <label>البريد الإلكتروني</label>
                    <input type="email" id="schoolEmail" class="form-input" value="${esc(school.email || '')}" dir="ltr">
                </div>
                <div class="form-group-modal">
                    <label>الجوال</label>
                    <input type="text" id="schoolPhone" class="form-input" value="${esc(school.phone || '')}" dir="ltr">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group-modal">
                    <label>المدينة</label>
                    <input type="text" id="schoolCity" class="form-input" value="${esc(school.city || '')}">
                </div>
                <div class="form-group-modal">
                    <label>المنطقة</label>
                    <input type="text" id="schoolRegion" class="form-input" value="${esc(school.region || '')}">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group-modal">
                    <label>الخطة</label>
                    <select id="schoolPlan" class="form-select">
                        <option value="">— بدون خطة —</option>
                        ${planOptions}
                    </select>
                </div>
                <div class="form-group-modal">
                    <label>الحد الأقصى للطلاب</label>
                    <input type="number" id="schoolMaxStudents" class="form-input" value="${school.max_students || 100}">
                </div>
            </div>
            <div class="form-group-modal">
                <label>الحد الأقصى للمعلمين</label>
                <input type="number" id="schoolMaxTeachers" class="form-input" value="${school.max_teachers || 5}">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-emerald btn-sm" onclick="saveSchool()">💾 حفظ التعديلات</button>
            <button class="btn btn-outline btn-sm" onclick="closeModal()">إلغاء</button>
        </div>
    `);
}

async function saveSchool() {
    const idEl = document.getElementById('schoolId');
    const data = {
        id: idEl ? idEl.value : null,
        name: document.getElementById('schoolName').value.trim(),
        slug: document.getElementById('schoolSlug').value.trim(),
        email: document.getElementById('schoolEmail').value.trim(),
        phone: document.getElementById('schoolPhone').value.trim(),
        city: document.getElementById('schoolCity').value.trim(),
        region: document.getElementById('schoolRegion').value.trim(),
        plan_id: document.getElementById('schoolPlan').value,
        max_students: document.getElementById('schoolMaxStudents').value,
        max_teachers: document.getElementById('schoolMaxTeachers').value
    };

    if (!data.name || !data.slug) return toast('اسم المدرسة والمعرف مطلوبان', 'error');

    // Include admin credentials for new school
    const adminUser = document.getElementById('adminUsername');
    const adminPass = document.getElementById('adminPassword');
    if (adminUser && adminPass) {
        data.admin_username = adminUser.value.trim();
        data.admin_password = adminPass.value.trim();
    }

    const r = await API.post('school_save', data);
    if (r && r.success) {
        toast(r.message || 'تم حفظ المدرسة بنجاح');
        closeModal();
        renderSchools();
    } else {
        toast(r?.error || 'خطأ في الحفظ', 'error');
    }
}

async function toggleSchool(id, active) {
    const r = await API.post('school_toggle', { id, active });
    if (r && r.success) {
        toast(r.message);
        renderSchools();
    } else {
        toast(r?.error || 'خطأ', 'error');
    }
}

async function impersonateSchool(id) {
    const r = await API.post('impersonate', { school_id: id });
    if (r && r.success) {
        toast('جاري الدخول كمدرسة...');
        setTimeout(() => {
            window.location.href = '../index.html';
        }, 500);
    } else {
        toast(r?.error || 'خطأ في الدخول', 'error');
    }
}

async function openResetAdminPasswordModal(schoolId, schoolName) {
    // 1. Fetch admins for this school
    toast('جاري تحميل مدراء المدرسة...', 'info');
    const r = await API.get(`get_school_admins&school_id=${schoolId}`);
    if (!r || !r.success) {
        toast(r?.error || 'خطأ في جلب المدراء', 'error');
        return;
    }

    const admins = r.data;
    if (admins.length === 0) {
        toast('هذه المدرسة لا تحتوي على حساب مدير', 'error');
        return;
    }

    const adminOptions = admins.map(a => `<option value="${a.id}">${esc(a.name)} (@${esc(a.username)})</option>`).join('');

    openModal(`
        <div class="modal-header">
            <h3>🔒 تغيير كلمة مرور مدير مدرسة: ${esc(schoolName)}</h3>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group-modal">
                <label>اختر حساب المدير</label>
                <select id="resetAdminId" class="form-select">
                    ${adminOptions}
                </select>
            </div>
            <div class="form-group-modal mt-3">
                <label>كلمة المرور الجديدة</label>
                <input type="password" id="resetAdminNewPassword" class="form-input" placeholder="••••••" minlength="6">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-emerald btn-sm" onclick="resetSchoolAdminPassword()">💾 تعيين كلمة المرور</button>
            <button class="btn btn-outline btn-sm" onclick="closeModal()">إلغاء</button>
        </div>
    `);
}

async function resetSchoolAdminPassword() {
    const adminId = document.getElementById('resetAdminId').value;
    const newPassword = document.getElementById('resetAdminNewPassword').value.trim();

    if (!adminId) return toast('اختر المدير', 'error');
    if (newPassword.length < 6) return toast('يجب أن تكون كلمة المرور 6 أحرف على الأقل', 'error');

    const btn = document.querySelector('.modal-footer .btn-emerald');
    if (btn) btn.disabled = true;

    const r = await API.post('reset_school_admin', { user_id: adminId, new_password: newPassword });
    if (r && r.success) {
        toast(r.message || 'تم تغيير كلمة المرور بنجاح!', 'success');
        closeModal();
    } else {
        toast(r?.error || 'حدث خطأ', 'error');
        if (btn) btn.disabled = false;
    }
}

async function deleteSchool(id, name) {
    if (!confirm(`⚠️ تحذير خطير جداً ⚠️\n\nهل أنت متأكد من مسح المدرسة "${name}" وبيانات كل المعلمين والطلاب والاختبارات التابعة لها بشكل نهائي؟! لا يمكن التراجع عن هذا الإجراء.`)) {
        return;
    }

    if (!confirm(`تأكيد أخير: اكتب "نعم" أو اضغط OK لإكمال الحذف للمدرسة "${name}"`)) {
        return;
    }

    const r = await API.post('school_delete', { id });
    if (r && r.success) {
        toast(r.message || 'تم مسح المدرسة نهائياً');
        renderSchools();
    } else {
        toast(r?.error || 'حدث خطأ أثناء الحذف', 'error');
    }
}

// ============================================================
// PLANS
// ============================================================
// PLANS
// ============================================================

const ALL_FEATURES = {
    tournaments: { icon: '🏆', label: 'البطولات الرياضية', desc: 'إنشاء وإدارة البطولات وتتبع النتائج' },
    sports_teams: { icon: '🛡️', label: 'الفرق المدرسية', desc: 'تشكيل الفرق والتدريبات وإدارة الأعضاء' },
    badges: { icon: '🏅', label: 'الأوسمة والتحفيز', desc: 'منح الأوسمة والنقاط التشجيعية للطلاب' },
    certificates: { icon: '📜', label: 'إصدار الشهادات', desc: 'طباعة شهادات أداء وتميز مخصصة' },
    notifications: { icon: '🔔', label: 'إشعارات أولياء الأمور', desc: 'إرسال تنبيهات فورية لأولياء الأمور' },
    reports: { icon: '📊', label: 'التقارير المتقدمة', desc: 'تقارير تحليلية وإحصائية شاملة' },
    analytics: { icon: '📈', label: 'لوحة التحليلات', desc: 'رؤية تحليلية لأداء المدرسة والطلاب' },
    fitness_tests: { icon: '💪', label: 'اختبارات اللياقة البدنية', desc: 'تسجيل ومتابعة نتائج اختبارات اللياقة' },
    timetable: { icon: '🗓️', label: 'جدول الحصص', desc: 'إعداد وعرض جداول الحصص الأسبوعية' },
    weighted_grading: { icon: '⚖️', label: 'محرك التقييم الموزون', desc: 'تخصيص أوزان الدرجات بدقة عالية' },
    monitoring_report: { icon: '📋', label: 'كشف المتابعة اليومي', desc: 'تقرير يومي شامل يجمع كافة المعايير' },
    assessments_bank: { icon: '📚', label: 'بنك المشاريع والأبحاث', desc: 'إدارة وتقييم الأبحاث والاختبارات القصيرة' },
    behavior_analytics: { icon: '🧠', label: 'تحليلات السلوك', desc: 'تحليل نمو تفاعل الطالب وانضباطه' },
    white_label: { icon: '🏷️', label: 'تخصيص الهوية', desc: 'إخفاء هوية النظام ووضع شعار المدرسة' }
};

async function renderPlans() {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = '<div class="spinner"></div>';

    const r = await API.get('plans');
    if (!r || !r.success) {
        mc.innerHTML = '<div class="empty-state"><div class="icon">⚠️</div><p>خطأ في جلب الخطط</p></div>';
        return;
    }

    const plans = r.data;
    cachedPlans = plans;

    mc.innerHTML = `
        <div class="page-header">
            <h1>💎 الخطط والأسعار</h1>
            <p>تحكم كامل في كل خطط الاشتراك — الميزات، الحدود، الأسعار، والصلاحيات.</p>
        </div>

        <!-- Plan Cards Preview -->
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;margin-bottom:28px">
            ${plans.map(p => {
        const features = p.features ? JSON.parse(p.features) : {};
        const activeCount = Object.keys(ALL_FEATURES).filter(f => features[f]).length;
        const totalCount = Object.keys(ALL_FEATURES).length;
        return `
                <div style="background:var(--bg-card);border:${p.is_default ? '2px solid var(--accent-emerald)' : '1px solid var(--border-glass)'};border-radius:var(--radius-lg);padding:20px;position:relative;overflow:hidden;transition:var(--transition)" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform=''">
                    ${p.is_default ? '<div style="position:absolute;top:0;left:0;right:0;height:3px;background:var(--gradient-primary)"></div>' : ''}
                    ${!p.active ? '<div style="position:absolute;top:12px;left:12px;background:rgba(239,68,68,0.15);color:var(--accent-red);font-size:9px;padding:2px 8px;border-radius:20px">معطلة</div>' : ''}
                    ${p.is_default ? '<div style="position:absolute;top:12px;left:12px;background:rgba(16,185,129,0.15);color:var(--accent-emerald);font-size:9px;padding:2px 8px;border-radius:20px">افتراضية</div>' : ''}
                    <div style="font-size:24px;margin-bottom:8px">💎</div>
                    <div style="font-size:18px;font-weight:800;margin-bottom:2px">${esc(p.name)}</div>
                    <div style="font-size:11px;color:var(--text-muted);margin-bottom:12px">${esc(p.name_en || p.slug)}</div>
                    <div style="font-size:28px;font-weight:900;color:var(--accent-emerald);line-height:1">${p.price_monthly > 0 ? p.price_monthly : 'مجاني'}</div>
                    ${p.price_monthly > 0 ? '<div style="font-size:10px;color:var(--text-muted)">ر.س / شهرياً</div>' : ''}
                    <hr style="border-color:var(--border-glass);margin:14px 0">
                    <div style="font-size:11px;color:var(--text-secondary);margin-bottom:8px">
                        👤 ${p.max_students || '∞'} طالب &nbsp;|&nbsp; 👨‍🏫 ${p.max_teachers || '∞'} معلم &nbsp;|&nbsp; 🏛️ ${p.max_classes || '∞'} فصل
                    </div>
                    <div style="background:var(--bg-glass);border-radius:8px;padding:4px 8px;font-size:11px;color:${activeCount >= totalCount * 0.7 ? 'var(--accent-emerald)' : 'var(--text-secondary)'}">
                        ✅ ${activeCount} / ${totalCount} ميزة مفعّلة
                    </div>
                    <div style="display:flex;gap:8px;margin-top:12px">
                        <button class="btn btn-emerald btn-sm" style="flex:1;font-size:12px" onclick="openPlanModal(${p.id})">✏️ تعديل</button>
                        <button class="btn btn-outline btn-sm" style="font-size:12px" onclick="confirmDeletePlan(${p.id},'${esc(p.name)}')" title="مسح">🗑️</button>
                    </div>
                </div>
                `;
    }).join('')}
            <div style="background:rgba(16,185,129,0.05);border:2px dashed var(--border-glass);border-radius:var(--radius-lg);padding:20px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;cursor:pointer;min-height:220px" onclick="openPlanModal()">
                <div style="font-size:36px;opacity:0.4">➕</div>
                <p style="color:var(--text-muted);font-size:13px;text-align:center">إضافة خطة اشتراك جديدة</p>
            </div>
        </div>

        <!-- Detailed Table -->
        <div class="panel">
            <div class="panel-header">
                <h3>📋 جدول تفصيلي للخطط</h3>
                <button class="btn btn-emerald btn-sm" onclick="openPlanModal()">➕ إضافة خطة</button>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>الترتيب</th>
                            <th>الخطة</th>
                            <th>الأسعار</th>
                            <th>الحدود</th>
                            <th>الميزات</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="blogTableBody">
                        ${plans.map(p => {
        const features = p.features ? JSON.parse(p.features) : {};
        const activeFeatures = Object.keys(ALL_FEATURES).filter(f => features[f]);
        const inactiveFeatures = Object.keys(ALL_FEATURES).filter(f => !features[f]);
        return `
                            <tr>
                                <td><span style="font-size:12px;color:var(--text-muted)">#${p.sort_order || p.id}</span></td>
                                <td>
                                    <div style="font-weight:700">${esc(p.name)} ${p.is_default ? '<span style="font-size:9px;background:rgba(16,185,129,0.15);color:var(--accent-emerald);padding:1px 6px;border-radius:10px">افتراضي</span>' : ''}</div>
                                    <div style="font-size:10px;color:var(--text-muted)">${esc(p.name_en || '')} · <code>${esc(p.slug)}</code></div>
                                    ${p.description ? `<div style="font-size:10px;color:var(--text-secondary);margin-top:2px">${esc(p.description)}</div>` : ''}
                                </td>
                                <td style="font-size:12px">
                                    <div><span style="color:var(--accent-emerald);font-weight:700">${p.price_monthly > 0 ? p.price_monthly + ' ر.س' : 'مجاني'}</span> / شهر</div>
                                    <div style="color:var(--text-muted)">${p.price_yearly > 0 ? p.price_yearly + ' ر.س / سنة' : '—'}</div>
                                </td>
                                <td style="font-size:11px;line-height:1.8">
                                    👤 <strong>${p.max_students || '∞'}</strong> طالب<br>
                                    👨‍🏫 <strong>${p.max_teachers || '∞'}</strong> معلم<br>
                                    🏛️ <strong>${p.max_classes || '∞'}</strong> فصل
                                </td>
                                <td>
                                    <div style="display:flex;flex-wrap:wrap;gap:3px;max-width:220px">
                                        ${activeFeatures.map(f => `<span title="${ALL_FEATURES[f].label}" style="background:rgba(16,185,129,0.12);color:var(--accent-emerald);border:1px solid rgba(16,185,129,0.2);padding:1px 6px;border-radius:4px;font-size:9px">${ALL_FEATURES[f].icon} ${ALL_FEATURES[f].label.split(' ')[0]}</span>`).join('')}
                                        ${inactiveFeatures.map(f => `<span title="${ALL_FEATURES[f].label}" style="background:rgba(239,68,68,0.05);color:var(--text-muted);border:1px solid rgba(239,68,68,0.1);padding:1px 6px;border-radius:4px;font-size:9px;text-decoration:line-through">${ALL_FEATURES[f].icon}</span>`).join('')}
                                    </div>
                                </td>
                                <td>${p.active ? '<span class="badge badge-active">نشطة</span>' : '<span class="badge badge-inactive">معطلة</span>'}</td>
                                <td>
                                    <div class="action-btns">
                                        <button class="action-btn edit" onclick="openPlanModal(${p.id})" title="تعديل">✏️</button>
                                        <button class="action-btn delete" onclick="confirmDeletePlan(${p.id},'${esc(p.name)}')" title="مسح">🗑️</button>
                                    </div>
                                </td>
                            </tr>`;
    }).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;
}

function openPlanModal(editId) {
    const plan = editId ? cachedPlans.find(p => p.id == editId) : null;
    const title = plan ? `✏️ تعديل: ${esc(plan.name)}` : '➕ خطة اشتراك جديدة';
    const features = plan && plan.features ? JSON.parse(plan.features) : {};

    openModal(`
        <div class="modal-header">
            <h3>${title}</h3>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <div class="modal-body" style="max-height:75vh;overflow-y:auto">
            ${plan ? `<input type="hidden" id="planId" value="${plan.id}">` : ''}

            <!-- القسم الأول: الهوية -->
            <div style="background:rgba(16,185,129,0.05);border:1px solid rgba(16,185,129,0.15);border-radius:10px;padding:16px;margin-bottom:16px">
                <h4 style="font-size:12px;color:var(--accent-emerald);margin-bottom:12px;text-transform:uppercase;letter-spacing:1px">🏷️ هوية الخطة</h4>
                <div class="form-row">
                    <div class="form-group-modal">
                        <label>الاسم بالعربي *</label>
                        <input type="text" id="planName" class="form-input" value="${plan ? esc(plan.name) : ''}" placeholder="مثال: الخطة المتقدمة">
                    </div>
                    <div class="form-group-modal">
                        <label>الاسم بالإنجليزي</label>
                        <input type="text" id="planNameEn" class="form-input" value="${plan ? esc(plan.name_en || '') : ''}" placeholder="e.g. Advanced Plan" dir="ltr">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group-modal">
                        <label>المعرف (slug) *</label>
                        <input type="text" id="planSlug" class="form-input" value="${plan ? esc(plan.slug) : ''}" placeholder="advanced" dir="ltr">
                    </div>
                    <div class="form-group-modal">
                        <label>ترتيب العرض</label>
                        <input type="number" id="planSortOrder" class="form-input" value="${plan ? (plan.sort_order || 0) : 0}" min="0">
                    </div>
                </div>
                <div class="form-group-modal">
                    <label>وصف الخطة</label>
                    <textarea id="planDesc" class="form-input" rows="2" placeholder="وصف مختصر يظهر لعملائك...">${plan ? esc(plan.description || '') : ''}</textarea>
                </div>
                <!-- قائمة المميزات للعرض -->
                <div class="form-group-modal">
                    <label>🎁 قائمة المميزات (تظهر في الواجهة الرئيسية)</label>
                    <textarea id="planFeaturesList" class="form-input" rows="5" placeholder="اكتب كل ميزة في سطر منفصل...">${plan ? esc(plan.features_list || '') : ''}</textarea>
                    <small style="font-size:10px;color:var(--text-muted)">💡 اكتب كل ميزة في سطر جديد ليتم عرضها كقائمة نقاط.</small>
                </div>
                <div style="display:flex;gap:20px">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
                        <input type="checkbox" id="planIsDefault" ${plan && plan.is_default ? 'checked' : ''}>
                        <span>🌟 خطة افتراضية للتسجيل</span>
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px">
                        <input type="checkbox" id="planActive" ${!plan || plan.active ? 'checked' : ''}>
                        <span>✅ خطة نشطة ومرئية</span>
                    </label>
                </div>
            </div>

            <!-- القسم الثاني: الأسعار -->
            <div style="background:rgba(245,158,11,0.05);border:1px solid rgba(245,158,11,0.15);border-radius:10px;padding:16px;margin-bottom:16px">
                <h4 style="font-size:12px;color:var(--accent-orange);margin-bottom:12px;text-transform:uppercase;letter-spacing:1px">💰 الأسعار</h4>
                <div class="form-row">
                    <div class="form-group-modal">
                        <label>السعر الشهري (ر.س)</label>
                        <input type="number" id="planMonthly" class="form-input" value="${plan ? plan.price_monthly : 0}" step="0.01" min="0">
                        <small style="font-size:10px;color:var(--text-muted)">اترك 0 للخطة المجانية</small>
                    </div>
                    <div class="form-group-modal">
                        <label>السعر السنوي (ر.س)</label>
                        <input type="number" id="planYearly" class="form-input" value="${plan ? plan.price_yearly : 0}" step="0.01" min="0">
                        <small style="font-size:10px;color:var(--text-muted)">يُعرض للعملاء كخيار توفير</small>
                    </div>
                </div>
            </div>

            <!-- القسم الثالث: حدود الاستهلاك -->
            <div style="background:rgba(6,182,212,0.05);border:1px solid rgba(6,182,212,0.15);border-radius:10px;padding:16px;margin-bottom:16px">
                <h4 style="font-size:12px;color:var(--accent-cyan);margin-bottom:12px;text-transform:uppercase;letter-spacing:1px">📊 حدود الاستهلاك</h4>
                <div class="form-row">
                    <div class="form-group-modal">
                        <label>👤 الحد الأقصى للطلاب</label>
                        <input type="number" id="planMaxStudents" class="form-input" value="${plan ? plan.max_students : 100}" min="1">
                    </div>
                    <div class="form-group-modal">
                        <label>👨‍🏫 الحد الأقصى للمعلمين</label>
                        <input type="number" id="planMaxTeachers" class="form-input" value="${plan ? plan.max_teachers : 5}" min="1">
                    </div>
                    <div class="form-group-modal">
                        <label>🏛️ الحد الأقصى للفصول</label>
                        <input type="number" id="planMaxClasses" class="form-input" value="${plan ? plan.max_classes : 10}" min="1">
                    </div>
                </div>
                <small style="font-size:10px;color:var(--text-muted)">💡 استخدم 9999 للخطط غير المحدودة</small>
            </div>

            <!-- القسم الرابع: الميزات -->
            <div style="background:rgba(139,92,246,0.05);border:1px solid rgba(139,92,246,0.15);border-radius:10px;padding:16px">
                <h4 style="font-size:12px;color:#8b5cf6;margin-bottom:4px;text-transform:uppercase;letter-spacing:1px">🔓 الميزات والصلاحيات</h4>
                <p style="font-size:10px;color:var(--text-muted);margin-bottom:14px">فعّل أو أوقف كل ميزة بشكل مستقل لهذه الخطة</p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
                    ${Object.entries(ALL_FEATURES).map(([key, info]) => `
                        <label style="display:flex;align-items:flex-start;gap:10px;background:var(--bg-glass);padding:10px 12px;border-radius:8px;border:1px solid ${features[key] ? 'rgba(16,185,129,0.3)' : 'var(--border-glass)'};cursor:pointer;transition:all 0.2s" onchange="this.style.borderColor=this.querySelector('input').checked?'rgba(16,185,129,0.3)':'var(--border-glass)'">
                            <input type="checkbox" class="plan-feature-chk" data-feature="${key}" ${features[key] ? 'checked' : ''} style="margin-top:3px;cursor:pointer;accent-color:var(--accent-emerald)">
                            <div>
                                <div style="font-size:12px;font-weight:700">${info.icon} ${info.label}</div>
                                <div style="font-size:10px;color:var(--text-muted);margin-top:1px">${info.desc}</div>
                            </div>
                        </label>
                    `).join('')}
                </div>
                <div style="display:flex;gap:10px;margin-top:10px">
                    <button type="button" onclick="document.querySelectorAll('.plan-feature-chk').forEach(c=>c.checked=true);document.querySelectorAll('label:has(.plan-feature-chk)').forEach(l=>l.style.borderColor='rgba(16,185,129,0.3)')" style="background:rgba(16,185,129,0.1);color:var(--accent-emerald);border:none;padding:5px 12px;border-radius:6px;font-size:11px;cursor:pointer">✅ تفعيل الكل</button>
                    <button type="button" onclick="document.querySelectorAll('.plan-feature-chk').forEach(c=>c.checked=false);document.querySelectorAll('label:has(.plan-feature-chk)').forEach(l=>l.style.borderColor='var(--border-glass)')" style="background:rgba(239,68,68,0.1);color:var(--accent-red);border:none;padding:5px 12px;border-radius:6px;font-size:11px;cursor:pointer">🚫 إيقاف الكل</button>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-emerald btn-sm" onclick="savePlan()">💾 حفظ الخطة</button>
            <button class="btn btn-outline btn-sm" onclick="closeModal()">إلغاء</button>
        </div>
    `, 'lg');
}

async function savePlan() {
    const idEl = document.getElementById('planId');
    const featureChks = document.querySelectorAll('.plan-feature-chk');
    const features = {};
    featureChks.forEach(chk => {
        features[chk.dataset.feature] = chk.checked;
    });

    const data = {
        id: idEl ? idEl.value : null,
        name: document.getElementById('planName').value.trim(),
        name_en: document.getElementById('planNameEn').value.trim(),
        slug: document.getElementById('planSlug').value.trim(),
        description: document.getElementById('planDesc').value.trim(),
        price_monthly: document.getElementById('planMonthly').value,
        price_yearly: document.getElementById('planYearly').value,
        max_students: document.getElementById('planMaxStudents').value,
        max_teachers: document.getElementById('planMaxTeachers').value,
        max_classes: document.getElementById('planMaxClasses').value,
        is_default: document.getElementById('planIsDefault').checked ? 1 : 0,
        active: document.getElementById('planActive').checked ? 1 : 0,
        sort_order: document.getElementById('planSortOrder').value,
        features: features,
        features_list: document.getElementById('planFeaturesList').value.trim()
    };

    if (!data.name || !data.slug) return toast('اسم الخطة والمعرف مطلوبان', 'error');

    const r = await API.post('plan_save', data);
    if (r && r.success) {
        toast(r.message || 'تم حفظ الخطة');
        closeModal();
        renderPlans();
    } else {
        toast(r?.error || 'خطأ في الحفظ', 'error');
    }
}

async function confirmDeletePlan(id, name) {
    if (!confirm(`⚠️ هل تريد حذف خطة "${name}"؟ لا يمكن حذف خطة عليها مشتركون.`)) return;
    const r = await API.post('plan_delete', { id });
    if (r && r.success) {
        toast(r.message || 'تم مسح الخطة');
        renderPlans();
    } else {
        toast(r?.error || 'خطأ في الحذف', 'error');
    }
}

// ============================================================
// SUBSCRIPTION MODAL (School)
// ============================================================
async function openSubModal(schoolId) {
    const r = await API.get('schools');
    if (!r || !r.success) return;
    const school = r.data.find(s => s.id == schoolId);
    if (!school) return;

    const planOptions = cachedPlans.map(p =>
        `<option value="${p.id}" ${school.plan_id == p.id ? 'selected' : ''}>${esc(p.name)} — ${p.price_monthly > 0 ? p.price_monthly + ' ر.س/شهر' : 'مجاني'}</option>`
    ).join('');

    // Pre-calculate merged features
    // If school has specific features, use them (explicitly override), else follow plan
    const schoolFeatures = school.features ? JSON.parse(school.features) : null;
    const planFeatures = school.plan_features ? JSON.parse(school.plan_features) : {};
    const mergedFeatures = schoolFeatures || planFeatures;

    openModal(`
        <div class="modal-header">
            <h3>💳 اشتراك: ${esc(school.name)}</h3>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <div class="modal-body" style="max-height:75vh;overflow-y:auto">

            <!-- معلومات الحالة الراهنة -->
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:18px">
                <div style="background:var(--bg-glass);border-radius:10px;padding:12px;border:1px solid var(--border-glass);text-align:center">
                    <p style="font-size:9px;color:var(--text-muted);margin-bottom:4px">تاريخ الانضمام</p>
                    <p style="font-weight:700;font-size:12px;color:var(--accent-cyan)">📅 ${school.created_at ? school.created_at.split(' ')[0] : '—'}</p>
                </div>
                <div style="background:var(--bg-glass);border-radius:10px;padding:12px;border:1px solid var(--border-glass);text-align:center">
                    <p style="font-size:9px;color:var(--text-muted);margin-bottom:4px">حالة الاشتراك</p>
                    ${subBadge(school.subscription_status)}
                </div>
                <div style="background:var(--bg-glass);border-radius:10px;padding:12px;border:1px solid var(--border-glass);text-align:center">
                    <p style="font-size:9px;color:var(--text-muted);margin-bottom:4px">الخطة الحالية</p>
                    <p style="font-weight:700;font-size:12px">${school.plan_name || '—'}</p>
                </div>
            </div>

            <!-- ميزات مخصصة للمدرسة -->
            <div style="background:rgba(139,92,246,0.05);border:1px solid rgba(139,92,246,0.15);border-radius:10px;padding:14px;margin-bottom:18px">
                <h4 style="font-size:12px;color:#8b5cf6;margin-bottom:4px;text-transform:uppercase">🔓 التحكم في الميزات (تجاوز الخطة)</h4>
                <p style="font-size:10px;color:var(--text-muted);margin-bottom:12px">يمكنك تفعيل ميزات محددة لهذه المدرسة حتى لو لم تكن في خطتها</p>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:6px">
                    ${Object.entries(ALL_FEATURES).map(([key, info]) => `
                        <label style="display:flex;align-items:center;gap:6px;font-size:10px;padding:5px 8px;border-radius:6px;background:var(--bg-glass);border:1px solid ${mergedFeatures[key] ? 'rgba(16,185,129,0.3)' : 'var(--border-glass)'};cursor:pointer">
                            <input type="checkbox" class="sub-feature-chk" data-feature="${key}" ${mergedFeatures[key] ? 'checked' : ''} style="margin:0;accent-color:var(--accent-emerald)">
                            <span>${info.icon} ${info.label.split(' ').slice(0, 2).join(' ')}</span>
                        </label>
                    `).join('')}
                </div>
                ${!schoolFeatures ? `<p style="font-size:9px;color:var(--accent-orange);margin-top:8px">ℹ️ هذه الميزات مأخوذة حالياً من "خطة الاشتراك". عند تغييرها سيتم حفظ "نسخة مخصصة" لهذه المدرسة.</p>` : ''}
            </div>

            <!-- تحديث الاشتراك -->
            <div style="background:rgba(245,158,11,0.05);border:1px solid rgba(245,158,11,0.15);border-radius:10px;padding:14px;margin-bottom:18px">
                <h4 style="font-size:12px;color:var(--accent-orange);margin-bottom:12px;text-transform:uppercase">🔄 تحديث حالة الاشتراك</h4>
                <div class="form-row">
                    <div class="form-group-modal">
                        <label>حالة الاشتراك</label>
                        <select id="subStatus" class="form-select" onchange="toggleSubscriptionUI(this.value)">
                            <option value="active"    ${school.subscription_status === 'active' ? 'selected' : ''}>✅ نشط (مدفوع)</option>
                            <option value="trial"     ${school.subscription_status === 'trial' ? 'selected' : ''}>⏳ تجريبي (مجاني)</option>
                            <option value="suspended" ${school.subscription_status === 'suspended' ? 'selected' : ''}>⛔ معلق (محجوب)</option>
                            <option value="cancelled" ${school.subscription_status === 'cancelled' ? 'selected' : ''}>🚫 ملغي</option>
                        </select>
                    </div>
                    <div class="form-group-modal" id="subPeriodRow" style="display:${school.subscription_status === 'active' ? 'block' : 'none'}">
                        <label>دورة الدفع</label>
                        <select id="subPeriod" class="form-select" onchange="recalculateExpiry()">
                            <option value="monthly">شهر واحد (Monthly)</option>
                            <option value="yearly">سنة كاملة (Yearly)</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group-modal">
                        <label>خطة التسعير</label>
                        <select id="subPlan" class="form-select" onchange="recalculateExpiry()">
                            <option value="">— الخطة الحالية —</option>
                            ${planOptions}
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group-modal">
                        <label>📅 تاريخ بداية الاشتراك</label>
                        <input type="date" id="subStartsAt" class="form-input" value="${school.subscription_starts_at || new Date().toISOString().split('T')[0]}" dir="ltr" onchange="recalculateExpiry()">
                    </div>
                    <div class="form-group-modal">
                        <label>📅 تاريخ انتهاء الصلاحية</label>
                        <input type="date" id="subEndsAt" class="form-input" value="${school.subscription_status === 'trial' ? (school.trial_ends_at || '') : (school.subscription_ends_at || '')}" dir="ltr">
                        <small style="font-size:10px;color:var(--text-muted)">سيُوقف النظام تلقائياً بعد هذا التاريخ</small>
                    </div>
                </div>
                <div id="trialDaysRow" style="display:${school.subscription_status === 'trial' ? 'block' : 'none'}">
                    <div class="form-group-modal">
                        <label>⏳ أيام الفترة التجريبية</label>
                        <input type="number" id="subTrialDays" class="form-input" value="14" min="1" max="365" oninput="recalculateExpiry()">
                        <small style="font-size:10px;color:var(--text-muted)">يُطبق عند تحويل الاشتراك لتجريبي</small>
                    </div>
                </div>
            </div>

            <!-- تجاوز حدود الخطة -->
            <div style="background:rgba(6,182,212,0.05);border:1px solid rgba(6,182,212,0.15);border-radius:10px;padding:14px;margin-bottom:18px">
                <h4 style="font-size:12px;color:var(--accent-cyan);margin-bottom:4px;text-transform:uppercase">🛠️ تجاوز حدود الخطة</h4>
                <p style="font-size:10px;color:var(--text-muted);margin-bottom:12px">ادخل أرقاماً لتحديد سعة خاصة، أو اترك فارغاً لاتباع حدود الخطة</p>
                <div class="form-row">
                    <div class="form-group-modal">
                        <label>👤 الحد الأقصى للطلاب</label>
                        <input type="number" id="subMaxStudents" class="form-input" placeholder="حسب الخطة" value="${school.max_students || ''}">
                    </div>
                    <div class="form-group-modal">
                        <label>👨‍🏫 الحد الأقصى للمعلمين</label>
                        <input type="number" id="subMaxTeachers" class="form-input" placeholder="حسب الخطة" value="${school.max_teachers || ''}">
                    </div>
                    <div class="form-group-modal">
                        <label>🏛️ الحد الأقصى للفصول</label>
                        <input type="number" id="subMaxClasses" class="form-input" placeholder="حسب الخطة" value="${school.max_classes || ''}">
                    </div>
                </div>
            </div>

            <div class="form-group-modal" style="margin-top:10px">
                <label>📝 ملاحظات إدارية حول الاشتراك</label>
                <textarea id="subNotes" class="form-input" rows="2" placeholder="أضف أي ملاحظات خاصة بالدفع أو العقد هنا...">${esc(school.subscription_notes || '')}</textarea>
                <small style="font-size:10px;color:var(--text-muted)">تظهر هذه الملاحظات لمدير المدرسة في صفحة اشتراكه.</small>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-emerald btn-sm" onclick="updateSubscription(${schoolId})">💾 حفظ التحديثات</button>
            <button class="btn btn-outline btn-sm" onclick="closeModal()">إلغاء</button>
        </div>
    `, 'lg');
}

async function updateSubscription(schoolId) {
    const gets = id => document.getElementById(id);
    if (!gets('subStatus')) return;

    const featureChks = document.querySelectorAll('.sub-feature-chk');
    const features = {};
    featureChks.forEach(chk => {
        features[chk.dataset.feature] = chk.checked;
    });

    const data = {
        id: schoolId,
        status: gets('subStatus').value,
        plan_id: gets('subPlan').value,
        starts_at: gets('subStartsAt')?.value || '',
        ends_at: gets('subEndsAt').value,
        trial_days: gets('subTrialDays')?.value || 14,
        max_students: gets('subMaxStudents')?.value || null,
        max_teachers: gets('subMaxTeachers')?.value || null,
        max_classes: gets('subMaxClasses')?.value || null,
        subscription_notes: gets('subNotes').value.trim(),
        features: features
    };

    const r = await API.post('school_subscription', data);
    if (r && r.success) {
        toast(r.message || 'تم تحديث الاشتراك');
        closeModal();
        renderSchools();
    } else {
        toast(r?.error || 'خطأ في التحديث', 'error');
    }
}

function toggleSubscriptionUI(status) {
    const trialRow = document.getElementById('trialDaysRow');
    const periodRow = document.getElementById('subPeriodRow');
    if (trialRow) trialRow.style.display = status === 'trial' ? 'block' : 'none';
    if (periodRow) periodRow.style.display = status === 'active' ? 'block' : 'none';
    recalculateExpiry();
}

function recalculateExpiry() {
    const status = document.getElementById('subStatus')?.value;
    const startsAt = document.getElementById('subStartsAt')?.value;
    const endsAt = document.getElementById('subEndsAt');
    if (!startsAt || !endsAt) return;

    let date = new Date(startsAt);
    if (isNaN(date.getTime())) return;

    if (status === 'trial') {
        const days = parseInt(document.getElementById('subTrialDays')?.value || 14);
        date.setDate(date.getDate() + days);
    } else if (status === 'active') {
        const period = document.getElementById('subPeriod')?.value;
        if (period === 'monthly') {
            date.setMonth(date.getMonth() + 1);
        } else if (period === 'yearly') {
            date.setFullYear(date.getFullYear() + 1);
        }
    } else {
        // Suspended or cancelled - usually don't touch or clear
        return;
    }

    endsAt.value = date.toISOString().split('T')[0];
}

// ============================================================
// MODAL HELPERS
// ============================================================
function openModal(html, size = 'md') {
    document.getElementById('modalContent').innerHTML = html;
    const modal = document.querySelector('.modal');
    if (modal) modal.style.maxWidth = size === 'lg' ? '720px' : '560px';
    document.getElementById('modalOverlay').classList.add('active');
}

function closeModal() {
    document.getElementById('modalOverlay').classList.remove('active');
}

// ============================================================
// UTILITIES
// ============================================================
function toast(msg, type = 'success') {
    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.innerHTML = `<span>${type === 'success' ? '✅' : '❌'}</span><span>${msg}</span>`;
    document.getElementById('toastContainer').appendChild(el);
    setTimeout(() => el.remove(), 3500);
}

function esc(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================================
// ANNOUNCEMENTS
// ============================================================
async function renderAnnouncements() {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = '<div class="spinner"></div>';

    const [ansRes, schoolsRes] = await Promise.all([
        API.get('announcements'),
        API.get('schools')
    ]);

    if (!ansRes || !ansRes.success) {
        mc.innerHTML = '<div class="empty-state"><div class="icon">⚠️</div><p>خطأ في جلب الإعلانات</p></div>';
        return;
    }

    const announcements = ansRes.data;
    const schools = schoolsRes?.data || [];

    mc.innerHTML = `
        <div class="page-header">
            <h1>📢 رسائل النظام والإعلانات</h1>
            <p>تواصل مع المدارس من خلال شريط إعلانات يظهر في لوحة تحكمهم.</p>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h3>📋 سجل الإعلانات (${announcements.length})</h3>
                <button class="btn btn-emerald btn-sm" onclick="openAnnouncementModal()">➕ إضافة إعلان</button>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>العنوان</th>
                            <th>النوع</th>
                            <th>المستهدف</th>
                            <th>تاريخ الانتهاء</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="blogTableBody">
                        ${announcements.length === 0 ? '<tr><td colspan="7"><div class="empty-state"><div class="icon">📢</div><p>لا توجد إعلانات حالياً</p></div></td></tr>' : ''}
                        ${announcements.map(a => `
                            <tr>
                                <td>${a.id}</td>
                                <td>
                                    <div style="font-weight:700">${esc(a.title)}</div>
                                    <div style="font-size:10px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:200px">${esc(a.message)}</div>
                                </td>
                                <td>
                                    <span class="badge" style="background:var(--accent-${a.type === 'danger' ? 'red' : (a.type === 'info' ? 'cyan' : a.type)}); color:white; font-size:10px">
                                        ${a.type.toUpperCase()}
                                    </span>
                                </td>
                                <td style="font-size:12px">${a.target_school_id ? `🏫 ${esc(a.school_name)}` : '🌍 للجميع'}</td>
                                <td style="font-size:12px;color:var(--text-muted)">${a.expires_at || '—'}</td>
                                <td>
                                    ${a.is_active ? '<span class="badge badge-active">نشط</span>' : '<span class="badge badge-inactive">معطل</span>'}
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <button class="action-btn edit" onclick="openAnnouncementModal(${a.id})" title="تعديل">✏️</button>
                                        <button class="action-btn delete" onclick="deleteAnnouncement(${a.id})" title="حذف">🗑️</button>
                                    </div>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;
}

async function openAnnouncementModal(id = null) {
    const res = await API.get('announcements');
    const announcements = res?.data || [];
    const a = id ? announcements.find(x => x.id == id) : null;

    // Fetch schools for targeting
    const sRes = await API.get('schools');
    const schools = sRes?.data || [];
    const schoolOptions = schools.map(s => `<option value="${s.id}" ${a?.target_school_id == s.id ? 'selected' : ''}>${esc(s.name)}</option>`).join('');

    openModal(`
        <div class="modal-header">
            <h3>${id ? '✏️ تعديل إعلان' : '📢 إضافة إعلان جديد'}</h3>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="annId" value="${a?.id || ''}">
            <div class="form-group-modal">
                <label>عنوان الإعلان *</label>
                <input type="text" id="annTitle" class="form-input" value="${esc(a?.title || '')}" placeholder="مثال: تنبيه بخصوص الصيانة">
            </div>
            <div class="form-group-modal mt-3">
                <label>نص الإعلان *</label>
                <textarea id="annMessage" class="form-input" style="height:100px">${esc(a?.message || '')}</textarea>
            </div>
            <div class="form-row mt-3">
                <div class="form-group-modal">
                    <label>النوع (اللون)</label>
                    <select id="annType" class="form-select">
                        <option value="info" ${a?.type === 'info' ? 'selected' : ''}>🔵 معلومات (Info)</option>
                        <option value="warning" ${a?.type === 'warning' ? 'selected' : ''}>🟡 تنبيه (Warning)</option>
                        <option value="danger" ${a?.type === 'danger' ? 'selected' : ''}>🔴 خطر (Danger)</option>
                        <option value="success" ${a?.type === 'success' ? 'selected' : ''}>🟢 نجاح (Success)</option>
                    </select>
                </div>
                <div class="form-group-modal">
                    <label>المدرسة المستهدفة</label>
                    <select id="annTarget" class="form-select">
                        <option value="">🌍 جميع المدارس</option>
                        ${schoolOptions}
                    </select>
                </div>
            </div>
            <div class="form-row mt-3">
                <div class="form-group-modal">
                    <label>تاريخ الانتهاء</label>
                    <input type="date" id="annExpires" class="form-input" value="${a?.expires_at || ''}">
                </div>
                <div class="form-group-modal">
                    <label>الحالة</label>
                    <select id="annActive" class="form-select">
                        <option value="1" ${a?.is_active != 0 ? 'selected' : ''}>✅ تفعيل</option>
                        <option value="0" ${a?.is_active == 0 ? 'selected' : ''}>⏸️ تعطيل</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-emerald btn-sm" onclick="saveAnnouncement()">💾 حفظ الإعلان</button>
            <button class="btn btn-outline btn-sm" onclick="closeModal()">إلغاء</button>
        </div>
    `);
}

async function saveAnnouncement() {
    const data = {
        id: document.getElementById('annId').value || null,
        title: document.getElementById('annTitle').value.trim(),
        message: document.getElementById('annMessage').value.trim(),
        type: document.getElementById('annType').value,
        target_school_id: document.getElementById('annTarget').value || null,
        is_active: document.getElementById('annActive').value,
        expires_at: document.getElementById('annExpires').value || null
    };

    if (!data.title || !data.message) return toast('العنوان والنص مطلوبان', 'error');

    const r = await API.post('announcement_save', data);
    if (r && r.success) {
        toast(r.message);
        closeModal();
        renderAnnouncements();
    } else {
        toast(r?.error || 'خطأ في الحفظ', 'error');
    }
}

async function deleteAnnouncement(id) {
    if (!confirm('هل أنت متأكد من مسح هذا الإعلان؟')) return;
    const r = await API.post('announcement_delete', { id });
    if (r && r.success) {
        toast(r.message);
        renderAnnouncements();
    } else {
        toast(r?.error || 'خطأ في المسح', 'error');
    }
}

// ============================================================
// GLOBAL AUDIT LOGS
// ============================================================
async function renderGlobalLogs() {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = '<div class="spinner"></div>';

    const res = await API.get('global_audit_logs');
    if (!res || !res.success) {
        mc.innerHTML = '<div class="empty-state"><div class="icon">⚠️</div><p>خطأ في جلب سجل النشاط</p></div>';
        return;
    }

    const logs = res.data;

    mc.innerHTML = `
        <div class="page-header">
            <h1>📜 سجل نشاط النظام الشامل</h1>
            <p>مراقبة آخر العمليات والأنشطة عبر جميع المدارس في المنصة.</p>
        </div>

        <div class="panel">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>المدرسة</th>
                            <th>المستخدم</th>
                            <th>العملية</th>
                            <th>التفاصيل</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody id="blogTableBody">
                        ${logs.length === 0 ? '<tr><td colspan="6" class="text-center">لا توجد سجلات حالياً</td></tr>' : ''}
                        ${logs.map(log => `
                            <tr>
                                <td style="font-size:12px; white-space:nowrap">${log.created_at}</td>
                                <td style="font-weight:700">${esc(log.school_name || 'System')}</td>
                                <td>
                                    <div style="font-size:13px">${esc(log.user_name || '—')}</div>
                                    <div style="font-size:10px; color:var(--text-muted)">${log.user_role || ''}</div>
                                </td>
                                <td>
                                    <span class="badge" style="background:#f3f4f6; color:#374151; font-size:10px">
                                        ${log.action.toUpperCase()}
                                    </span>
                                </td>
                                <td style="font-size:12px; max-width:300px; white-space:normal">${esc(log.details)}</td>
                                <td style="font-size:10px; color:var(--text-muted)">${log.ip_address}</td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;
}

// ============================================================
// ADVANCED ANALYTICS
// ============================================================
async function renderAdvancedAnalytics() {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = '<div class="spinner"></div>';

    const res = await API.get('advanced_analytics');
    if (!res || !res.success) {
        mc.innerHTML = '<div class="empty-state"><div class="icon">⚠️</div><p>خطأ في جلب التحليلات</p></div>';
        return;
    }

    const { most_active, alert_schools, subscription_distribution } = res.data;

    mc.innerHTML = `
        <div class="page-header">
            <h1>📈 التحليلات المتقدمة</h1>
            <p>إحصائيات تفصيلية تساعدك على فهم نشاط المدارس واستهدافهم للترقية.</p>
        </div>

        <div class="analytics-grid" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap:20px;">
            
            <!-- Most Active Schools (Last 7 Days) -->
            <div class="panel">
                <div class="panel-header"><h3>🔥 المدارس الأكثر نشاطاً (آخر 7 أيام)</h3></div>
                <div class="p-4">
                    ${most_active.length === 0 ? '<p class="text-muted">لا يوجد نشاط مسجل كافٍ</p>' : ''}
                    ${most_active.map(s => {
        const maxVal = most_active[0].activity_count || 1;
        const pct = Math.round((s.activity_count / maxVal) * 100);
        return `
                            <div class="mb-4">
                                <div class="flex justify-between mb-1">
                                    <span style="font-weight:700">${esc(s.name)}</span>
                                    <span style="color:var(--text-muted)">${s.activity_count} عملية</span>
                                </div>
                                <div style="height:10px; background:#f3f4f6; border-radius:5px; overflow:hidden">
                                    <div style="width:${pct}%; height:100%; background:var(--accent-emerald); border-radius:5px"></div>
                                </div>
                            </div>
                        `;
    }).join('')}
                </div>
            </div>

            <!-- Schools Needing Attention (Near Limits) -->
            <div class="panel">
                <div class="panel-header"><h3>🚨 مدارس تحتاج اهتمام (قاربت على استنفاد طاقتها)</h3></div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>المدرسة</th>
                                <th>استهلاك الطلاب</th>
                                <th>استهلاك المعلمين</th>
                                <th>إجراء</th>
                            </tr>
                        </thead>
                        <tbody id="blogTableBody">
                            ${alert_schools.length === 0 ? '<tr><td colspan="4" class="text-muted">جميع المدارس ضمن الحدود الآمنة</td></tr>' : ''}
                            ${alert_schools.map(s => `
                                <tr>
                                    <td style="font-weight:700">${esc(s.name)}</td>
                                    <td>
                                        <div style="font-size:11px">${s.counts.students} / ${s.counts.max_students}</div>
                                        <div style="height:6px; background:#f3f4f6; border-radius:3px">
                                            <div style="width:${s.student_usage}%; height:100%; background:${s.student_usage > 90 ? 'var(--accent-red)' : 'var(--accent-orange)'}; border-radius:3px"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size:11px">${s.counts.teachers} / ${s.counts.max_teachers}</div>
                                        <div style="height:6px; background:#f3f4f6; border-radius:3px">
                                            <div style="width:${s.teacher_usage}%; height:100%; background:${s.teacher_usage > 90 ? 'var(--accent-red)' : 'var(--accent-orange)'}; border-radius:3px"></div>
                                        </div>
                                    </td>
                                    <td>
                                        <button class="btn btn-emerald btn-sm" onclick="impersonateSchool(${s.id})">🔍 فحص</button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Subscription Status Distribution -->
            <div class="panel">
                <div class="panel-header"><h3>💳 توزيع الاشتراكات</h3></div>
                <div class="p-6 flex flex-wrap gap-4 items-center justify-around">
                    ${subscription_distribution.map(d => {
        const colors = { active: 'emerald', trial: 'cyan', suspended: 'red', expired: 'orange' };
        const color = colors[d.subscription_status] || 'gray';
        return `
                            <div class="text-center">
                                <div style="font-size:24px; font-weight:900; color:var(--accent-${color})">${d.count}</div>
                                <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase">${d.subscription_status}</div>
                            </div>
                        `;
    }).join('')}
                </div>
            </div>
        </div>
    `;
}

// ============================================================
// PLATFORM SETTINGS (Maintenance Mode)
// ============================================================
async function renderPlatformSettings() {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = '<div class="spinner"></div>';

    const res = await API.get('maintenance_get');
    if (!res || !res.success) {
        mc.innerHTML = '<div class="empty-state"><div class="icon">⚠️</div><p>خطأ في جلب الإعدادات</p></div>';
        return;
    }

    const s = res.data;

    mc.innerHTML = `
        <div class="page-header">
            <h1>⚙️ إعدادات المنصة العامة</h1>
            <p>التحكم في حالة المنصة، وضع الصيانة، والرسائل التحذيرية العامة.</p>
        </div>

        <div class="panel" style="max-width: 800px;">
            <div class="panel-header"><h3>🛠️ وضع الصيانة (Maintenance Mode)</h3></div>
            <div class="p-6">
                <div class="form-group mb-6">
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" id="maintMode" ${s.mode === '1' ? 'checked' : ''} style="width:20px; height:20px">
                        <span style="font-weight:700; font-size:1.1rem">تفعيل وضع الصيانة</span>
                    </label>
                    <p class="text-muted mt-2">عند التفعيل، لن يتمكن مدراء المدارس والمعلمون من الدخول، وستظهر لهم صفحة الصيانة.</p>
                </div>

                <div class="form-group mb-4">
                    <label>رسالة الصيانة</label>
                    <textarea id="maintMessage" class="form-input" rows="3" placeholder="اكتب هنا الرسالة التي ستظهر للمستخدمين...">${esc(s.message)}</textarea>
                </div>

                <div class="form-group mb-6">
                    <label>الوقت المتوقع للعودة (اختياري)</label>
                    <input type="text" id="maintUntil" class="form-input" placeholder="مثال: غداً الساعة 10 صباحاً" value="${esc(s.until)}">
                </div>

                <hr style="border:0; border-top:1px solid var(--border-color); margin:2rem 0">

                <button class="btn btn-emerald" onclick="savePlatformSettings()">💾 حفظ الإعدادات العامة</button>
            </div>
        </div>

        <div class="panel mt-6" style="max-width: 800px; border: 1px dashed var(--accent-orange); background: rgba(245, 158, 11, 0.05)">
            <div class="p-4 flex gap-4">
                <div style="font-size:24px">⚠️</div>
                <div>
                    <strong style="display:block; margin-bottom:5px">تنبيه هام</strong>
                    <p class="text-sm text-muted">وضع الصيانة لا يطبق على مدير المنصة (أنت). يمكنك دائماً الدخول للوحة التحكم هذه حتى عند تفعيل الصيانة.</p>
                </div>
            </div>
        </div>
    `;
}

async function savePlatformSettings() {
    const data = {
        mode: document.getElementById('maintMode').checked ? '1' : '0',
        message: document.getElementById('maintMessage').value.trim(),
        until: document.getElementById('maintUntil').value.trim()
    };

    const btn = document.querySelector('.btn-emerald');
    btn.disabled = true;
    btn.innerText = '⌛ جاري الحفظ...';

    const r = await API.post('maintenance_save', data);
    if (r && r.success) {
        toast(r.message || 'تم حفظ الإعدادات');
    } else {
        toast(r?.error || 'خطأ في الحفظ', 'error');
    }

    btn.disabled = false;
    btn.innerText = '💾 حفظ الإعدادات العامة';
}

// ============================================================
// BLOG MANAGEMENT
// ============================================================
async function renderBlog() {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = '<div class="spinner"></div>';

    const [r, catR] = await Promise.all([API.get('blog_posts'), API.get('blog_categories')]);
    if (!r || !r.success) {
        mc.innerHTML = '<div class="empty-state"><div class="icon">⚠️</div><p>خطأ في جلب المقالات</p></div>';
        return;
    }

    const posts = r.data;
    window.allBlogPosts = posts; // store for filtering
    const categories = catR?.data || [];

    mc.innerHTML = `
        <div class="page-header">
            <h1>📰 المدونة والدروس</h1>
            <p>إدارة المقالات، الشروحات، وأخبار المنصة المنشورة على صفحة الهبوط.</p>
        </div>

        <div class="panel">
            <div class="panel-header" style="flex-wrap:wrap;gap:10px">
                <h3>📋 قائمة المقالات (<span id="blogCount">${posts.length}</span>)</h3>
                <div style="display:flex;gap:10px;flex:1;justify-content:flex-end">
                    <input type="text" id="blogSearch" class="form-input" placeholder="🔍 بحث في المقالات..." style="max-width:250px" oninput="filterBlogPosts()">
                    <select id="blogCategoryFilter" class="form-select" style="max-width:200px" onchange="filterBlogPosts()">
                        <option value="">كل التصنيفات</option>
                        ${categories.map(c => `<option value="${esc(c.name)}">${esc(c.name)}</option>`).join('')}
                    </select>
                    <button class="btn btn-outline btn-sm" onclick="openCategoriesModal()">🏷️ إدارة التصنيفات</button>
                    <button class="btn btn-emerald btn-sm" onclick="openPostModal()">➕ إضافة مقال</button>
                </div>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>المقال</th>
                            <th>التصنيف</th>
                            <th>الحالة</th>
                            <th>تاريخ النشر</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody id="blogTableBody">
                        ${posts.length === 0 ? '<tr><td colspan="6"><div class="empty-state"><div class="icon">📰</div><p>لا توجد مقالات بعد</p></div></td></tr>' : ''}
                        ${posts.map(p => `
                            <tr>
                                <td>${p.id}</td>
                                <td>
                                    <strong>${esc(p.title)}</strong>
                                    <div style="font-size:10px;color:var(--text-muted)">/${esc(p.slug)}</div>
                                </td>
                                <td><span class="badge" style="background:var(--bg-glass);color:var(--text-primary)">${esc(p.category)}</span></td>
                                <td>${p.status === 'published' ? '<span class="badge badge-active">منشور</span>' : '<span class="badge badge-trial">مسودة</span>'}</td>
                                <td style="font-size:12px;color:var(--text-muted)">${p.published_at || p.created_at.split(' ')[0]}</td>
                                <td>
                                    <div class="action-btns">
                                        <button class="action-btn edit" onclick="openPostModal(${p.id})" title="تعديل">✏️</button>
                                        <a href="../post.php?slug=${p.slug}" target="_blank" class="action-btn enter" style="text-decoration:none;display:flex;align-items:center;justify-content:center" title="معاينة">👁️</a>
                                        <button class="action-btn delete" onclick="deletePost(${p.id}, '${esc(p.title)}')" title="حذف">🗑️</button>
                                    </div>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    `;
}

async function openPostModal(id = null) {
    let post = { title: '', slug: '', content: '', excerpt: '', category: 'الدروس', status: 'published', image_path: '' };

    if (id) {
        // Fetch only the requested post to avoid pulling all posts' content
        const r = await API.get('blog_post&id=' + id);
        post = r.data || post;
    }

    const catRes = await API.get('blog_categories');
    const categories = catRes?.data || [];
    const catOptions = categories.map(c => `<option value="${esc(c.name)}" ${post.category === c.name ? 'selected' : ''}>${esc(c.name)}</option>`).join('');

    openModal(`
        <div class="modal-header">
            <h3>${id ? '✏️ تعديل مقال' : '➕ إضافة مقال جديد'}</h3>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <div class="modal-body" style="max-height:80vh;overflow-y:auto">
            <input type="hidden" id="postId" value="${id || ''}">
            <div class="form-group-modal">
                <label>عنوان المقال *</label>
                <input type="text" id="postTitle" class="form-input" value="${esc(post.title)}" oninput="generateSlug(this.value)">
            </div>
            <div class="form-group-modal">
                <label>الرابط الصديق (Slug) *</label>
                <input type="text" id="postSlug" class="form-input" value="${esc(post.slug)}" dir="ltr">
            </div>
            <div class="form-row">
                <div class="form-group-modal">
                    <label>التصنيف</label>
                    <select id="postCategory" class="form-select">
                        ${catOptions}
                        ${categories.length === 0 ? '<option value="general">عام</option>' : ''}
                    </select>
                </div>
                <div class="form-group-modal">
                    <label>الحالة</label>
                    <select id="postStatus" class="form-select">
                        <option value="published" ${post.status === 'published' ? 'selected' : ''}>منشور</option>
                        <option value="draft" ${post.status === 'draft' ? 'selected' : ''}>مسودة</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group-modal">
                    <label>تاريخ النشر (للجدولة)</label>
                    <input type="datetime-local" id="postPublishAt" class="form-input" value="${post.publish_at ? post.publish_at.replace(' ', 'T').substring(0, 16) : ''}" dir="ltr">
                </div>
                <div class="form-group-modal">
                    <label>أولوية الترتيب</label>
                    <input type="number" id="postSortOrder" class="form-input" value="${post.sort_order || 0}">
                </div>
            </div>
            <div class="form-group-modal">
                <label>رابط الصورة البارزة</label>
                <div style="display:flex;gap:8px;align-items:center">
                    <input type="text" id="postImage" class="form-input" value="${esc(post.image_path || '')}" placeholder="أو اكتب رابط URL مباشرة..." dir="ltr" style="flex:1">
                    <button type="button" class="btn btn-cyan btn-sm" onclick="openMediaPicker('postImage','postImagePreview')" style="white-space:nowrap">🖼️ مكتبة</button>
                </div>
                <div id="postImagePreview" style="margin-top:8px">${post.image_path ? `<img src="${esc(post.image_path)}" style="max-height:80px;border-radius:8px;object-fit:cover">` : ''}</div>
            </div>
            <div class="form-group-modal">
                <label>ملخص قصير (Excerpt)</label>
                <textarea id="postExcerpt" class="form-input" rows="2">${esc(post.excerpt || '')}</textarea>
            </div>
            <div class="form-group-modal editor-wrapper">
                <label>المحتوى الكامل (المحرر المرئي)</label>
                <div id="postEditor"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-emerald btn-sm" onclick="savePost()">💾 حفظ المقال</button>
            <button class="btn btn-outline btn-sm" onclick="closeModal()">إلغاء</button>
        </div>
    `, 'lg');

    // Initialize Quill with CUSTOM IMAGE HANDLER (blocks Base64, uses Media Library)
    quill = new Quill('#postEditor', {
        theme: 'snow',
        placeholder: 'اكتب محتوى المقال هنا...',
        modules: {
            toolbar: {
                container: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    ['blockquote', 'code-block'],
                    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                    [{ 'direction': 'rtl' }],
                    ['link', 'image'],
                    ['clean']
                ],
                handlers: {
                    image: quillImagePickerHandler
                }
            }
        }
    });

    if (post.content) {
        quill.root.innerHTML = post.content;
    }
}

// Custom Quill image handler: opens Media Library instead of Base64 upload
async function quillImagePickerHandler() {
    const r = await API.get('get_media');
    const media = r?.data || [];

    const gridHtml = media.map(m => `
        <div onclick="insertQuillImage('${m.file_path}')"
             style="cursor:pointer;border:2px solid var(--border-glass);border-radius:10px;overflow:hidden;transition:all 0.2s"
             onmouseover="this.style.borderColor='var(--accent-emerald)';this.style.transform='scale(1.03)'"
             onmouseout="this.style.borderColor='var(--border-glass)';this.style.transform=''">
            <div style="height:90px;overflow:hidden">
                <img src="../${m.file_path}" style="width:100%;height:100%;object-fit:cover" loading="lazy">
            </div>
            <div style="padding:4px 6px;font-size:9px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${m.original_name}</div>
        </div>`).join('');

    const tipHtml = media.length > 0 ? `<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px">${gridHtml}</div>` :
        `<div style="text-align:center;padding:40px;color:var(--text-muted)"><div style="font-size:48px;opacity:0.3">🖼️</div><p>لا توجد صور — ارفع صورة أولاً</p></div>`;

    const html = `
        <div class="modal-header">
            <h3>🖼️ إدراج صورة من المكتبة</h3>
            <button class="modal-close" onclick="closeMediaPicker()">✕</button>
        </div>
        <div class="modal-body" style="max-height:65vh;overflow-y:auto">
            <div style="background:rgba(16,185,129,0.09);border:1px solid rgba(16,185,129,0.2);border-radius:8px;padding:10px;margin-bottom:12px;font-size:12px;color:var(--accent-emerald)">
                💡 الصور تُرفع لمكتبة الوسائط ثم تُدرج برابط — هذا يمنع حفظ الصور داخل قاعدة البيانات.
            </div>
            <div style="margin-bottom:12px">
                <button class="btn btn-cyan btn-sm" onclick="closeMediaPicker();closeModal();navigate('media')">📤 رفع صورة جديدة</button>
            </div>
            ${tipHtml}
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline btn-sm" onclick="closeMediaPicker()">إغلاق</button>
        </div>
    `;

    const overlay = document.getElementById('mediaPickerOverlay');
    const cnt = document.getElementById('mediaPickerContent');
    if (overlay && cnt) { cnt.innerHTML = html; overlay.classList.add('active'); }
}

function insertQuillImage(filePath) {
    const base = window.location.origin;
    const rootPath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/admin/'));
    const fullUrl = base + rootPath + '/' + filePath;
    if (quill) {
        const range = quill.getSelection(true);
        quill.insertEmbed(range.index, 'image', fullUrl);
        quill.setSelection(range.index + 1);
    }
    closeMediaPicker();
    toast('✅ تم إدراج الصورة في المحرر');
}

function generateSlug(title) {
    const slugEl = document.getElementById('postSlug');
    if (slugEl && (!slugEl.value || document.getElementById('postId').value === '')) {
        slugEl.value = title.toLowerCase()
            .replace(/[^\w\s\u0621-\u064A]/gi, '')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-');
    }
}


// ============================================================
// BLOG CATEGORIES & FILTERING
// ============================================================
function filterBlogPosts() {
    const q = document.getElementById('blogSearch').value.toLowerCase();
    const c = document.getElementById('blogCategoryFilter').value;
    const posts = window.allBlogPosts || [];
    const filtered = posts.filter(p => {
        const matchQ = p.title.toLowerCase().includes(q) || p.slug.toLowerCase().includes(q);
        const matchC = c ? p.category === c : true;
        return matchQ && matchC;
    });

    document.getElementById('blogCount').textContent = filtered.length;
    const tbody = document.getElementById('blogTableBody');
    if (!tbody) return;

    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6"><div class="empty-state"><p>لا توجد مقالات مطابقة للبحث</p></div></td></tr>';
        return;
    }

    tbody.innerHTML = filtered.map(p => `
        <tr>
            <td>${p.id}</td>
            <td>
                <strong>${esc(p.title)}</strong>
                <div style="font-size:10px;color:var(--text-muted)">/${esc(p.slug)}</div>
            </td>
            <td><span class="badge" style="background:var(--bg-glass);color:var(--text-primary)">${esc(p.category)}</span></td>
            <td>${p.status === 'published' ? '<span class="badge badge-active">منشور</span>' : '<span class="badge badge-trial">مسودة</span>'}</td>
            <td style="font-size:12px;color:var(--text-muted)">${p.published_at || p.created_at.split(' ')[0]}</td>
            <td>
                <div class="action-btns">
                    <button class="action-btn edit" onclick="openPostModal(${p.id})" title="تعديل">✏️</button>
                    <a href="../post.php?slug=${p.slug}" target="_blank" class="action-btn enter" style="text-decoration:none;display:flex;align-items:center;justify-content:center" title="معاينة">👁️</a>
                    <button class="action-btn delete" onclick="deletePost(${p.id}, '${esc(p.title)}')" title="حذف">🗑️</button>
                </div>
            </td>
        </tr>
    `).join('');
}

async function openCategoriesModal() {
    const r = await API.get('blog_categories');
    const categories = r?.data || [];
    openModal(`
        <div class="modal-header">
            <h3>🏷️ إدارة التصنيفات</h3>
            <button class="modal-close" onclick="closeModal(); renderBlog()">✕</button>
        </div>
        <div class="modal-body" style="max-height:60vh;overflow-y:auto">
            <div style="display:flex;gap:8px;margin-bottom:15px">
                <input type="text" id="newCategoryName" class="form-input" placeholder="اسم التصنيف الجديد..." style="flex:1">
                <button class="btn btn-emerald" onclick="saveCategory()">إضافة</button>
            </div>
            <table style="width:100%;text-align:right" class="custom-table">
                <tbody>
                    ${categories.map(c => `
                        <tr style="border-bottom:1px solid #eee">
                            <td style="padding:10px 0">${esc(c.name)}</td>
                            <td style="padding:10px 0;text-align:left">
                                <button class="action-btn edit" onclick="editCategory(${c.id}, '${esc(c.name)}')">✏️</button>
                                <button class="action-btn delete" onclick="deleteCategory(${c.id}, '${esc(c.name)}')">🗑️</button>
                            </td>
                        </tr>
                    `).join('')}
                    ${categories.length === 0 ? '<tr><td colspan="2" style="text-align:center;padding:20px;color:#999">لا توجد تصنيفات</td></tr>' : ''}
                </tbody>
            </table>
        </div>
    `, 'md');
}

async function saveCategory(id = null, name = null) {
    const catName = name || document.getElementById('newCategoryName')?.value?.trim();
    if (!catName) return toast('يرجى إدخال اسم التصنيف', 'error');

    const r = await API.post('blog_category_save', { id, name: catName });
    if (r && r.success) {
        toast('تم الحفظ بنجاح');
        openCategoriesModal(); // reload modal
    } else {
        toast(r?.error || 'خطأ في الحفظ', 'error');
    }
}

function editCategory(id, oldName) {
    const newName = prompt('تعديل اسم التصنيف:', oldName);
    if (newName && newName.trim() !== oldName) {
        saveCategory(id, newName.trim());
    }
}

async function deleteCategory(id, name) {
    if (!confirm(`هل أنت متأكد من حذف التصنيف "${name}"؟`)) return;
    const r = await API.post('blog_category_delete', { id });
    if (r && r.success) {
        toast('تم الحذف بنجاح');
        openCategoriesModal(); // reload modal
    } else {
        toast(r?.error || 'خطأ في الحذف', 'error');
    }
}

async function savePost() {
    const data = {
        id: document.getElementById('postId').value || null,
        title: document.getElementById('postTitle').value.trim(),
        slug: document.getElementById('postSlug').value.trim(),
        category: document.getElementById('postCategory').value,
        status: document.getElementById('postStatus').value,
        image_path: document.getElementById('postImage').value.trim(),
        excerpt: document.getElementById('postExcerpt').value.trim(),
        publish_at: document.getElementById('postPublishAt').value,
        sort_order: document.getElementById('postSortOrder').value,
        content: quill.root.innerHTML
    };

    if (!data.title || !data.slug || !data.content) {
        return toast('يرجى ملء العنوان والرابط والمحتوى', 'error');
    }

    const r = await API.post('blog_save', data);
    if (r && r.success) {
        toast('تم حفظ المقال بنجاح');
        closeModal();
        renderBlog();
    } else {
        toast(r?.error || 'خطأ في الحفظ', 'error');
    }
}

async function deletePost(id, title) {
    if (!confirm(`هل أنت متأكد من حذف المقال "${title}" نهائياً؟`)) return;

    const r = await API.post('blog_delete', { id });
    if (r && r.success) {
        toast('تم حذف المقال');
        renderBlog();
    } else {
        toast(r?.error || 'خطأ في الحذف', 'error');
    }
}

// ============================================================
// KEYBOARD
// ============================================================
document.addEventListener('keydown', e => {
    if (e.key === 'Enter' && !adminUser) handleLogin();
    if (e.key === 'Escape') closeModal();
});

// ============================================================
// INIT
// ============================================================
checkAuth();
console.log('✅ Super Admin Panel loaded');
// ============================================================
// MEDIA LIBRARY - Appended by system
// ============================================================

async function renderMedia() {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = '<div class="spinner"></div>';

    const r = await API.get('get_media');
    if (!r || !r.success) {
        mc.innerHTML = '<div class="empty-state"><div class="icon">⚠️</div><p>خطأ في جلب الوسائط</p></div>';
        return;
    }

    const media = r.data;
    const totalSize = media.reduce((acc, m) => acc + (m.file_size || 0), 0);
    const formatSize = bytes => bytes < 1024 * 1024
        ? (bytes / 1024).toFixed(1) + ' KB'
        : (bytes / 1024 / 1024).toFixed(2) + ' MB';

    mc.innerHTML = `
        <div class="page-header">
            <h1>🖼️ مكتبة الوسائط</h1>
            <p>رفع وإدارة صور المدونة والمقالات.</p>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:12px;margin-bottom:24px">
            <div style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:var(--radius-lg);padding:16px;text-align:center">
                <div style="font-size:24px;font-weight:900;color:var(--accent-emerald)">${media.length}</div>
                <div style="font-size:12px;color:var(--text-muted)">إجمالي الصور</div>
            </div>
            <div style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:var(--radius-lg);padding:16px;text-align:center">
                <div style="font-size:24px;font-weight:900;color:var(--accent-cyan)">${formatSize(totalSize)}</div>
                <div style="font-size:12px;color:var(--text-muted)">إجمالي الحجم</div>
            </div>
            <div style="background:var(--bg-card);border:1px solid var(--border-glass);border-radius:var(--radius-lg);padding:16px;text-align:center">
                <div style="font-size:24px;font-weight:900;color:var(--accent-orange)">3 MB</div>
                <div style="font-size:12px;color:var(--text-muted)">الحد الأقصى للصورة</div>
            </div>
        </div>

        <div class="panel" style="margin-bottom:24px">
            <div class="panel-header"><h3>📤 رفع صورة جديدة</h3></div>
            <div class="panel-body" style="padding:20px">
                <div id="mediaDropZone" style="border:2px dashed var(--border-glass);border-radius:12px;padding:32px;text-align:center;cursor:pointer;transition:all 0.2s"
                    ondragover="event.preventDefault();this.style.borderColor='var(--accent-emerald)';this.style.background='rgba(16,185,129,0.05)'"
                    ondragleave="this.style.borderColor='var(--border-glass)';this.style.background=''"
                    ondrop="handleMediaDrop(event)"
                    onclick="document.getElementById('mediaFileInput').click()">
                    <input type="file" id="mediaFileInput" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none" onchange="uploadMediaFile(this.files[0])">
                    <div style="font-size:48px;margin-bottom:8px;opacity:0.4">📷</div>
                    <p style="font-weight:700;color:var(--text-secondary);margin-bottom:4px">اسحب وأفلت الصورة هنا</p>
                    <p style="font-size:11px;color:var(--text-muted)">أو اضغط للاختيار · JPG, PNG, WEBP, GIF · حد أقصى 3 MB</p>
                </div>
                <div id="mediaUploadProgress" style="display:none;margin-top:12px">
                    <div style="background:var(--bg-glass);border-radius:8px;overflow:hidden;height:8px">
                        <div id="mediaProgressBar" style="height:100%;width:0%;background:var(--accent-emerald);transition:width 0.4s;border-radius:8px"></div>
                    </div>
                    <p style="font-size:11px;color:var(--text-muted);margin-top:6px;text-align:center">⌛ جاري الرفع...</p>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header"><h3>📁 الصور المرفوعة (${media.length})</h3></div>
            <div id="mediaGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:16px;padding:20px">
                ${media.length === 0 ? `<div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--text-muted)"><div style="font-size:48px;opacity:0.3">🖼️</div><p style="margin-top:8px">لا توجد صور بعد</p></div>` : ''}
                ${media.map(m => `
                    <div class="media-item" id="media-item-${m.id}" style="border:1px solid var(--border-glass);border-radius:12px;overflow:hidden;position:relative;transition:all 0.2s"
                         onmouseover="this.querySelector('.media-overlay').style.opacity='1'"
                         onmouseout="this.querySelector('.media-overlay').style.opacity='0'">
                        <div style="height:130px;background:#f1f5f9;overflow:hidden">
                            <img src="../${esc(m.file_path)}" alt="${esc(m.original_name)}" style="width:100%;height:100%;object-fit:cover" loading="lazy">
                        </div>
                        <div class="media-overlay" style="position:absolute;inset:0;background:rgba(0,0,0,0.55);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;opacity:0;transition:opacity 0.2s">
                            <button onclick="copyMediaUrl('${esc(m.file_path)}')" style="background:white;color:#1e293b;border:none;padding:6px 14px;border-radius:20px;font-size:11px;font-weight:700;cursor:pointer">📋 نسخ الرابط</button>
                            <button onclick="confirmDeleteMedia(${m.id},'${esc(m.filename)}')" style="background:#ef4444;color:white;border:none;padding:6px 14px;border-radius:20px;font-size:11px;font-weight:700;cursor:pointer">🗑️ حذف</button>
                        </div>
                        <div style="padding:8px">
                            <div style="font-size:10px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="${esc(m.original_name)}">${esc(m.original_name)}</div>
                            <div style="font-size:9px;color:var(--text-muted);margin-top:2px">${formatSize(m.file_size)}${m.width ? ` · ${m.width}×${m.height}` : ''}</div>
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
}

function handleMediaDrop(event) {
    event.preventDefault();
    const dz = document.getElementById('mediaDropZone');
    if (dz) { dz.style.borderColor = 'var(--border-glass)'; dz.style.background = ''; }
    const file = event.dataTransfer?.files[0];
    if (file) uploadMediaFile(file);
}

async function uploadMediaFile(file) {
    if (!file) return;
    const maxSize = 3 * 1024 * 1024;
    if (file.size > maxSize) {
        toast(`حجم الصورة ${(file.size / 1024 / 1024).toFixed(2)} MB — يتجاوز الحد الأقصى (3 MB)`, 'error');
        return;
    }
    const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!allowedTypes.includes(file.type)) {
        toast('نوع الملف غير مسموح. يُقبل فقط: JPG, PNG, WEBP, GIF', 'error');
        return;
    }

    const progressEl = document.getElementById('mediaUploadProgress');
    const barEl = document.getElementById('mediaProgressBar');
    if (progressEl) progressEl.style.display = 'block';
    if (barEl) barEl.style.width = '30%';

    const formData = new FormData();
    formData.append('file', file);
    try {
        if (barEl) barEl.style.width = '70%';
        const response = await fetch('api.php?action=upload_media', { method: 'POST', body: formData });
        const result = await response.json();
        if (barEl) barEl.style.width = '100%';
        setTimeout(() => { if (progressEl) progressEl.style.display = 'none'; if (barEl) barEl.style.width = '0%'; }, 800);
        if (result.success) {
            toast('✅ تم رفع الصورة بنجاح!');
            renderMedia();
        } else {
            toast(result.error || 'خطأ في رفع الصورة', 'error');
        }
    } catch (e) {
        toast('خطأ في الاتصال بالخادم', 'error');
        if (progressEl) progressEl.style.display = 'none';
    }
}

async function confirmDeleteMedia(id, filename) {
    if (!confirm(`⚠️ هل تريد حذف الصورة "${filename}" نهائياً؟`)) return;
    const r = await API.post('delete_media', { id });
    if (r && r.success) {
        toast('تم حذف الصورة');
        const el = document.getElementById(`media-item-${id}`);
        if (el) el.remove();
    } else {
        toast(r?.error || 'خطأ في الحذف', 'error');
    }
}

function copyMediaUrl(filePath) {
    const base = window.location.origin;
    const path = window.location.pathname.replace('/admin/index.html', '');
    const fullUrl = base + path + '/' + filePath;
    navigator.clipboard.writeText(fullUrl).then(() => toast('✅ تم نسخ الرابط')).catch(() => {
        prompt('انسخ الرابط يدوياً:', base + path + '/' + filePath);
    });
}

// Open in dedicated second overlay — does NOT replace the blog post form
async function openMediaPicker(inputId, previewId) {
    const r = await API.get('get_media');
    const media = r?.data || [];

    const emptyMsg = `
        <div style="text-align:center;padding:40px;color:var(--text-muted)">
            <div style="font-size:48px;opacity:0.3">🖼️</div>
            <p style="margin-top:8px">لا توجد صور في المكتبة بعد</p>
            <button class="btn btn-emerald btn-sm" style="margin-top:14px" onclick="closeMediaPicker();closeModal();navigate('media')">📤 ارفع صورة الآن</button>
        </div>`;

    const gridHtml = media.map(m => `
        <div onclick="selectMediaImage('${m.file_path}','${inputId}','${previewId}')"
             style="cursor:pointer;border:2px solid var(--border-glass);border-radius:10px;overflow:hidden;transition:all 0.2s"
             onmouseover="this.style.borderColor='var(--accent-emerald)';this.style.transform='scale(1.03)'"
             onmouseout="this.style.borderColor='var(--border-glass)';this.style.transform=''">
            <div style="height:90px;overflow:hidden">
                <img src="../${m.file_path}" alt="${m.original_name}" style="width:100%;height:100%;object-fit:cover" loading="lazy">
            </div>
            <div style="padding:5px 6px;font-size:9px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${m.original_name}</div>
        </div>`).join('');

    const html = `
        <div class="modal-header">
            <h3>🖼️ اختيار صورة من المكتبة</h3>
            <button class="modal-close" onclick="closeMediaPicker()">✕</button>
        </div>
        <div class="modal-body" style="max-height:65vh;overflow-y:auto">
            ${media.length === 0 ? emptyMsg : `
                <p style="font-size:11px;color:var(--text-muted);margin-bottom:12px">اضغط على الصورة لاختيارها وإدراجها في المقال</p>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px">${gridHtml}</div>
            `}
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline btn-sm" onclick="closeMediaPicker()">إغلاق</button>
            <button class="btn btn-cyan btn-sm" onclick="closeMediaPicker();closeModal();navigate('media')">📤 رفع صورة جديدة</button>
        </div>
    `;

    // Use the DEDICATED media picker overlay, NOT the main modal
    const overlay = document.getElementById('mediaPickerOverlay');
    const pickerContent = document.getElementById('mediaPickerContent');
    if (overlay && pickerContent) {
        pickerContent.innerHTML = html;
        overlay.classList.add('active');
    }
}

function closeMediaPicker() {
    const overlay = document.getElementById('mediaPickerOverlay');
    if (overlay) overlay.classList.remove('active');
}

function selectMediaImage(filePath, inputId, previewId) {
    // Build the correct absolute URL based on current location
    const base = window.location.origin;
    const adminPath = window.location.pathname; // e.g. /pe1/admin/index.html
    const rootPath = adminPath.substring(0, adminPath.lastIndexOf('/admin/')); // /pe1
    const fullUrl = base + rootPath + '/' + filePath;

    // Update the input field & preview in the blog post form (still in the main modal)
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    if (input) input.value = fullUrl;
    if (preview) preview.innerHTML = `<img src="${fullUrl}" style="max-height:80px;border-radius:8px;object-fit:cover;margin-top:4px">`;

    closeMediaPicker();
    toast('✅ تم اختيار الصورة');
}
// ============================================================
// SYSTEM HEALTH
// ============================================================
async function renderHealth() {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = '<div class="spinner"></div>';

    const r = await API.get('system_health');
    if (!r || !r.success) {
        mc.innerHTML = '<div class="empty-state"><div class="icon">🏥</div><p>خطأ في جلب بيانات صحة النظام</p></div>';
        return;
    }

    const h = r.data;
    const dbStatusColor = h.database.status === 'ok' ? 'var(--accent-emerald)' : 'var(--accent-red)';
    const storageStatusColor = h.storage.status === 'ok' ? 'var(--accent-emerald)' : (h.storage.status === 'warning' ? 'var(--accent-orange)' : 'var(--text-muted)');

    mc.innerHTML = `
        <div class="page-header">
            <h1>🏥 حالة وصحة النظام</h1>
            <p>معلومات تقنية شاملة عن السيرفر وقاعدة البيانات والموارد.</p>
        </div>

        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
            <!-- Server Info -->
            <div class="panel">
                <div class="panel-header">
                    <h3>🖥️ معلومات السيرفر</h3>
                </div>
                <div class="panel-body">
                    <table class="simple-table">
                        <tr><td>نظام التشغيل:</td><td><strong>${h.server.os}</strong></td></tr>
                        <tr><td>إصدار PHP:</td><td><strong>${h.server.php_version}</strong></td></tr>
                        <tr><td>حد الذاكرة (Memory):</td><td><strong>${h.server.memory_limit}</strong></td></tr>
                        <tr><td>أقصى حجم للرفع:</td><td><strong>${h.server.upload_max}</strong></td></tr>
                        <tr><td>وضع التصحيح (Debug):</td><td><span class="badge ${h.server.debug_mode ? 'badge-trial' : 'badge-active'}">${h.server.debug_mode ? 'مفعل' : 'معطل'}</span></td></tr>
                        <tr><td>وقت التنفيذ الأقصى:</td><td><strong>${h.server.execution_time}</strong></td></tr>
                    </table>
                </div>
            </div>

            <!-- Database Info -->
            <div class="panel">
                <div class="panel-header">
                    <h3>🗄️ قاعدة البيانات</h3>
                    <span class="badge" style="background:${dbStatusColor}; color:white">${h.database.status.toUpperCase()}</span>
                </div>
                <div class="panel-body">
                    <table class="simple-table">
                        <tr><td>الإصدار (MySQL/MariaDB):</td><td><strong>${h.database.version || '—'}</strong></td></tr>
                        <tr><td>حالة الاتصال:</td><td><strong style="color:${dbStatusColor}">${h.database.details}</strong></td></tr>
                        <tr><td>إجمالي المدارس:</td><td><strong>${h.counts.schools}</strong></td></tr>
                        <tr><td>إجمالي الطلاب:</td><td><strong>${h.counts.students}</strong></td></tr>
                    </table>
                </div>
            </div>

            <!-- Storage Info -->
            <div class="panel">
                <div class="panel-header">
                    <h3>💾 مساحة التخزين</h3>
                </div>
                <div class="panel-body">
                    <div style="margin-bottom:15px">
                        <div style="display:flex; justify-content:space-between; margin-bottom:5px; font-size:12px">
                            <span>المساحة المتاحة</span>
                            <span>${h.storage.free} / ${h.storage.total}</span>
                        </div>
                        <div style="height:10px; background:var(--bg-glass); border-radius:10px; overflow:hidden">
                            <div style="height:100%; width:${h.storage.percent}%; background:${storageStatusColor}"></div>
                        </div>
                        <div style="text-align:left; font-size:10px; margin-top:5px; color:var(--text-muted)">${h.storage.percent}% متاح</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h3>📊 إحصائيات الجداول (MB)</h3>
            </div>
            <div class="panel-body">
                <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap:10px">
                    ${h.database.tables.map(t => `
                        <div style="background:var(--bg-glass); padding:10px; border-radius:8px; display:flex; justify-content:space-between; align-items:center">
                            <span style="font-size:12px; font-family:monospace">${t.TABLE_NAME}</span>
                            <span class="badge" style="background:rgba(6,182,212,0.1); color:var(--accent-cyan)">${t.size_mb} MB</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>

        <style>
            .simple-table { width: 100%; border-collapse: collapse; }
            .simple-table td { padding: 8px 0; border-bottom: 1px solid var(--border-glass); font-size: 13px; }
            .simple-table tr:last-child td { border-bottom: none; }
            .simple-table td:first-child { color: var(--text-secondary); width: 60%; }
            .simple-table td:last-child { text-align: left; }
        </style>

        <div class="panel" style="margin-top: 20px; border-left: 4px solid var(--accent-red);">
            <div class="panel-header">
                <h3 style="color: var(--accent-red);">🛡️ عمليات الأمان والطوارئ</h3>
            </div>
            <div class="panel-body">
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 15px;">
                    في حال تعذر دخول المعلمين أو المدارس بسبب إدخال كلمة المرور بشكل خاطئ أكثر من 5 مرات متتالية، يتم حظر الحسابات مؤقتاً كإجراء أمني. يمكنك فك الحظر عن الجميع فوراً من هنا.
                </p>
                <button onclick="unlockLogins()" class="btn" style="background:var(--bg-glass); color:var(--accent-red); border: 1px solid var(--accent-red);">
                    🔓 فك حظر تسجيل الدخول لجميع الحسابات
                </button>
            </div>
        </div>
    `;
}

async function unlockLogins() {
    if (!confirm('هل أنت متأكد من رغبتك في فك الحظر عن جميع الحسابات المحظورة؟')) return;
    const r = await API.post('unlock_logins');
    if (r && r.success) {
        toast('✅ ' + r.message);
    } else {
        toast(r?.error || 'حدث خطأ', 'error');
    }
}

