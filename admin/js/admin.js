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
function navigate(page) {
    currentPage = page;
    document.querySelectorAll('.sidebar-link').forEach(el => {
        el.classList.toggle('active', el.dataset.page === page);
    });

    const renderers = {
        dashboard: renderDashboard,
        schools: renderSchools,
        plans: renderPlans
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
            <p>إضافة وتعديل المدارس وإدارة اشتراكاتها</p>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h3>📋 قائمة المدارس (${schools.length})</h3>
                <button class="btn btn-emerald btn-sm" onclick="openAddSchoolModal()">➕ إضافة مدرسة</button>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم المدرسة</th>
                            <th>المعرف</th>
                            <th>الخطة</th>
                            <th>الاشتراك</th>
                            <th>الطلاب</th>
                            <th>المعلمون</th>
                            <th>الحالة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${schools.length === 0 ? '<tr><td colspan="9"><div class="empty-state"><div class="icon">🏫</div><p>لا توجد مدارس بعد</p></div></td></tr>' : ''}
                        ${schools.map(s => `
                            <tr>
                                <td>${s.id}</td>
                                <td><strong>${esc(s.name)}</strong>${s.city ? `<br><small style="color:var(--text-muted)">${esc(s.city)}</small>` : ''}</td>
                                <td><code style="color:var(--accent-emerald);font-size:12px">${esc(s.slug)}</code></td>
                                <td>${s.plan_name || '<span style="color:var(--text-muted)">—</span>'}</td>
                                <td>${subBadge(s.subscription_status)}</td>
                                <td>${s.student_count || 0} / ${s.max_students || '∞'}</td>
                                <td>${s.user_count || 0} / ${s.max_teachers || '∞'}</td>
                                <td>${s.active == 1 ? '<span class="badge badge-active">نشطة</span>' : '<span class="badge badge-inactive">معطلة</span>'}</td>
                                <td>
                                    <div class="action-btns">
                                        <button class="action-btn edit" onclick="openEditSchoolModal(${s.id})" title="تعديل">✏️</button>
                                        <button class="action-btn sub" onclick="openSubModal(${s.id})" title="الاشتراك">💳</button>
                                        <button class="action-btn enter" onclick="impersonateSchool(${s.id})" title="الدخول كمدرسة">🔑</button>
                                        ${s.active == 1
            ? `<button class="action-btn toggle-off" onclick="toggleSchool(${s.id}, 0)" title="تعطيل">⏸️</button>`
            : `<button class="action-btn toggle-on" onclick="toggleSchool(${s.id}, 1)" title="تفعيل">▶️</button>`
        }
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

// ============================================================
// SUBSCRIPTION MODAL
// ============================================================
async function openSubModal(schoolId) {
    const r = await API.get('schools');
    if (!r || !r.success) return;
    const school = r.data.find(s => s.id == schoolId);
    if (!school) return;

    const planOptions = cachedPlans.map(p =>
        `<option value="${p.id}" ${school.plan_id == p.id ? 'selected' : ''}>${esc(p.name)}</option>`
    ).join('');

    openModal(`
        <div class="modal-header">
            <h3>💳 إدارة اشتراك: ${esc(school.name)}</h3>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <div class="modal-body">
            <div style="background:var(--bg-glass);border-radius:var(--radius-sm);padding:16px;margin-bottom:20px;border:1px solid var(--border-glass)">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <span style="color:var(--text-secondary);font-size:13px">الحالة الحالية</span>
                    ${subBadge(school.subscription_status)}
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <span style="color:var(--text-secondary);font-size:13px">الخطة</span>
                    <span style="font-weight:600">${school.plan_name || 'غير محددة'}</span>
                </div>
                ${school.trial_ends_at ? `<div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px">
                    <span style="color:var(--text-secondary);font-size:13px">نهاية التجربة</span>
                    <span style="font-weight:600">${school.trial_ends_at}</span>
                </div>` : ''}
                ${school.subscription_ends_at ? `<div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px">
                    <span style="color:var(--text-secondary);font-size:13px">نهاية الاشتراك</span>
                    <span style="font-weight:600">${school.subscription_ends_at}</span>
                </div>` : ''}
            </div>

            <div class="form-group-modal">
                <label>تغيير الحالة</label>
                <select id="subStatus" class="form-select">
                    <option value="active" ${school.subscription_status === 'active' ? 'selected' : ''}>✅ نشط</option>
                    <option value="trial" ${school.subscription_status === 'trial' ? 'selected' : ''}>⏳ تجريبي</option>
                    <option value="suspended" ${school.subscription_status === 'suspended' ? 'selected' : ''}>⛔ معلق</option>
                </select>
            </div>
            <div class="form-group-modal">
                <label>الخطة</label>
                <select id="subPlan" class="form-select">
                    <option value="">— نفس الخطة —</option>
                    ${planOptions}
                </select>
            </div>
            <div class="form-group-modal">
                <label>تاريخ انتهاء الاشتراك</label>
                <input type="date" id="subEndsAt" class="form-input" value="${school.subscription_ends_at || ''}" dir="ltr">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-orange btn-sm" onclick="updateSubscription(${schoolId})">💾 تحديث الاشتراك</button>
            <button class="btn btn-outline btn-sm" onclick="closeModal()">إلغاء</button>
        </div>
    `);
}

async function updateSubscription(schoolId) {
    const data = {
        id: schoolId,
        status: document.getElementById('subStatus').value,
        plan_id: document.getElementById('subPlan').value,
        ends_at: document.getElementById('subEndsAt').value
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

// ============================================================
// PLANS
// ============================================================
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
            <p>إدارة خطط الاشتراك المتاحة للمدارس</p>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h3>📋 الخطط (${plans.length})</h3>
                <button class="btn btn-emerald btn-sm" onclick="openPlanModal()">➕ إضافة خطة</button>
            </div>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>اسم الخطة</th>
                            <th>المعرف</th>
                            <th>شهري</th>
                            <th>سنوي</th>
                            <th>الطلاب</th>
                            <th>المعلمون</th>
                            <th>الفصول</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${plans.map(p => `
                            <tr>
                                <td>${p.id}</td>
                                <td><strong>${esc(p.name)}</strong></td>
                                <td><code style="color:var(--accent-cyan);font-size:12px">${esc(p.slug)}</code></td>
                                <td>${p.price_monthly > 0 ? p.price_monthly + ' ر.س' : '<span style="color:var(--accent-green)">مجاني</span>'}</td>
                                <td>${p.price_yearly > 0 ? p.price_yearly + ' ر.س' : '—'}</td>
                                <td>${p.max_students || '∞'}</td>
                                <td>${p.max_teachers || '∞'}</td>
                                <td>${p.max_classes || '∞'}</td>
                                <td>
                                    <div class="action-btns">
                                        <button class="action-btn edit" onclick="openPlanModal(${p.id})">✏️</button>
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

function openPlanModal(editId) {
    const plan = editId ? cachedPlans.find(p => p.id == editId) : null;
    const title = plan ? `✏️ تعديل الخطة: ${esc(plan.name)}` : '➕ إضافة خطة جديدة';

    openModal(`
        <div class="modal-header">
            <h3>${title}</h3>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <div class="modal-body">
            ${plan ? `<input type="hidden" id="planId" value="${plan.id}">` : ''}
            <div class="form-row">
                <div class="form-group-modal">
                    <label>اسم الخطة *</label>
                    <input type="text" id="planName" class="form-input" value="${plan ? esc(plan.name) : ''}" placeholder="أساسي">
                </div>
                <div class="form-group-modal">
                    <label>المعرف (slug) *</label>
                    <input type="text" id="planSlug" class="form-input" value="${plan ? esc(plan.slug) : ''}" placeholder="basic" dir="ltr">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group-modal">
                    <label>السعر الشهري (ر.س)</label>
                    <input type="number" id="planMonthly" class="form-input" value="${plan ? plan.price_monthly : 0}" step="0.01">
                </div>
                <div class="form-group-modal">
                    <label>السعر السنوي (ر.س)</label>
                    <input type="number" id="planYearly" class="form-input" value="${plan ? plan.price_yearly : 0}" step="0.01">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group-modal">
                    <label>الحد الأقصى للطلاب</label>
                    <input type="number" id="planMaxStudents" class="form-input" value="${plan ? plan.max_students : 100}">
                </div>
                <div class="form-group-modal">
                    <label>الحد الأقصى للمعلمين</label>
                    <input type="number" id="planMaxTeachers" class="form-input" value="${plan ? plan.max_teachers : 5}">
                </div>
            </div>
            <div class="form-group-modal">
                <label>الحد الأقصى للفصول</label>
                <input type="number" id="planMaxClasses" class="form-input" value="${plan ? plan.max_classes : 10}">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-emerald btn-sm" onclick="savePlan()">💾 حفظ الخطة</button>
            <button class="btn btn-outline btn-sm" onclick="closeModal()">إلغاء</button>
        </div>
    `);
}

async function savePlan() {
    const idEl = document.getElementById('planId');
    const data = {
        id: idEl ? idEl.value : null,
        name: document.getElementById('planName').value.trim(),
        slug: document.getElementById('planSlug').value.trim(),
        price_monthly: document.getElementById('planMonthly').value,
        price_yearly: document.getElementById('planYearly').value,
        max_students: document.getElementById('planMaxStudents').value,
        max_teachers: document.getElementById('planMaxTeachers').value,
        max_classes: document.getElementById('planMaxClasses').value
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

// ============================================================
// MODAL HELPERS
// ============================================================
function openModal(html) {
    document.getElementById('modalContent').innerHTML = html;
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
