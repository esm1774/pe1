/**
 * PE Smart School System - Sports Teams Module
 * الفرق الرياضية: الفرق، التدريب، القرعة
 */

// ============================================================
// API HELPER
// ============================================================
const STAPI = {
    base: 'modules/sports_teams/api.php',
    async request(action, method = 'GET', data = null, params = {}) {
        try {
            let url = `${this.base}?action=${action}`;
            Object.entries(params).forEach(([k, v]) => {
                if (v !== null && v !== undefined && v !== '') url += `&${k}=${encodeURIComponent(v)}`;
            });
            const opts = { method, headers: {} };
            if (data && method !== 'GET') {
                opts.headers['Content-Type'] = 'application/json';
                opts.body = JSON.stringify(data);
            }
            const r = await fetch(url, opts);
            return await r.json();
        } catch (e) {
            console.error('STAPI Error:', e);
            showToast('خطأ في الاتصال', 'error');
            return null;
        }
    },
    get(action, params = {}) { return this.request(action, 'GET', null, params); },
    post(action, data = {}, p = {}) { return this.request(action, 'POST', data, p); }
};

// ============================================================
// STATE
// ============================================================
let stCurrentTeam = null;
let stTab = 'members';

// ============================================================
// CONSTANTS
// ============================================================
const SPORT_ICONS = {
    'كرة قدم': '⚽', 'كرة يد': '🤾', 'كرة طائرة': '🏐',
    'كرة سلة': '🏀', 'تنس طاولة': '🏓', 'عدو وميدانية': '🏃',
    'سباحة': '🏊', 'كرة تنس': '🎾', 'كرة قدم أمريكية': '🏈', 'أخرى': '🎯'
};

const TEAM_TYPE_AR = {
    class: { text: 'منتخب فصل', color: 'bg-blue-100 text-blue-700', icon: '🏫' },
    school: { text: 'منتخب مدرسة', color: 'bg-purple-100 text-purple-700', icon: '🏫' },
    mixed: { text: 'فريق مختلط', color: 'bg-orange-100 text-orange-700', icon: '🔀' }
};

const MEMBER_STATUS_AR = {
    active: { text: 'أساسي', color: 'bg-green-100 text-green-700' },
    substitute: { text: 'احتياطي', color: 'bg-yellow-100 text-yellow-700' },
    injured: { text: 'مصاب', color: 'bg-red-100 text-red-700' },
    suspended: { text: 'موقوف', color: 'bg-gray-100 text-gray-700' }
};

// ============================================================
// MAIN RENDER
// ============================================================
async function renderSportsTeams() {
    stCurrentTeam = null;
    stTab = 'members';

    const mc = document.getElementById('mainContent');
    mc.innerHTML = showLoading();

    const r = await STAPI.get('teams_list');
    const teams = r?.data || [];

    // تجميع حسب الرياضة
    const byType = {};
    teams.forEach(t => {
        if (!byType[t.sport_type]) byType[t.sport_type] = [];
        byType[t.sport_type].push(t);
    });

    mc.innerHTML = `
    <div class="fade-in">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">🏅 الفرق الرياضية</h2>
                <p class="text-gray-500 text-sm mt-1">إدارة منتخبات الفصول والمدرسة والفرق المختلطة</p>
            </div>
            ${canEdit() ? `
            <div class="flex flex-wrap gap-2">
                <button onclick="showLotteryWizard()" class="bg-emerald-100 text-emerald-700 px-4 py-2 rounded-xl font-semibold text-sm hover:bg-emerald-200 cursor-pointer flex items-center gap-2">
                    🎲 قرعة فرق جديدة
                </button>
                <button onclick="showTeamForm()" class="bg-green-600 text-white px-4 py-2 rounded-xl font-semibold text-sm hover:bg-green-700 cursor-pointer flex items-center gap-2">
                    + فريق جديد
                </button>
            </div>
            ` : ''}
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            ${_stStatCard('إجمالي الفرق', teams.length, '🏅', 'emerald')}
            ${_stStatCard('فرق الفصول', teams.filter(t => t.team_type === 'class').length, '🏫', 'green')}
            ${_stStatCard('منتخبات المدرسة', teams.filter(t => t.team_type === 'school').length, '⭐', 'teal')}
            ${_stStatCard('فرق مختلطة', teams.filter(t => t.team_type === 'mixed').length, '🔀', 'lime')}
        </div>

        <!-- Teams Grid -->
        ${Object.keys(byType).length > 0
            ? Object.entries(byType).map(([sport, sTeams]) => `
            <div class="mb-8">
                <h3 class="font-bold text-gray-700 mb-3 flex items-center gap-2">
                    <span class="text-2xl">${SPORT_ICONS[sport] || '🎯'}</span>
                    <span>${sport}</span>
                    <span class="badge bg-gray-100 text-gray-600">${sTeams.length} فريق</span>
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    ${sTeams.map(t => renderTeamCard(t)).join('')}
                </div>
            </div>`).join('')
            : `<div class="text-center py-20 text-gray-400">
                <p class="text-6xl mb-4">🏅</p>
                <p class="text-xl font-bold mb-2">لا توجد فرق رياضية بعد</p>
                <p class="text-sm mb-6">ابدأ بإنشاء فريق جديد أو إجراء قرعة</p>
                ${canEdit() ? `
                <div class="flex gap-3 justify-center">
                    <button onclick="showLotteryWizard()" class="bg-emerald-100 text-emerald-700 px-6 py-3 rounded-xl font-semibold hover:bg-emerald-200 cursor-pointer">🎲 قرعة الفرق</button>
                    <button onclick="showTeamForm()" class="bg-green-600 text-white px-6 py-3 rounded-xl font-semibold hover:bg-green-700 cursor-pointer">+ فريق جديد</button>
                </div>` : ''}
            </div>`
        }
    </div>`;
}

function _stStatCard(label, value, icon, color) {
    const colors = {
        emerald: 'bg-emerald-50 text-emerald-600 border-emerald-100',
        green: 'bg-green-50 text-green-600 border-green-100',
        teal: 'bg-teal-50 text-teal-600 border-teal-100',
        lime: 'bg-lime-50 text-lime-600 border-lime-100'
    };
    const c = colors[color] || colors.emerald;
    return `
    <div class="bg-white rounded-[2rem] border-2 ${c.split(' ').pop()} p-6 text-center shadow-sm hover:shadow-xl transition-all duration-300 group">
        <div class="w-16 h-16 rounded-2xl ${c.split(' ').slice(0, 2).join(' ')} flex items-center justify-center text-3xl mx-auto mb-4 shadow-sm transform group-hover:rotate-12 transition">
            ${icon}
        </div>
        <p class="text-3xl font-black text-gray-800">${value}</p>
        <p class="text-[10px] text-gray-400 font-black uppercase tracking-widest mt-1">${label}</p>
    </div>`;
}

// ============================================================
// TEAM CARD
// ============================================================
function renderTeamCard(t) {
    const type = TEAM_TYPE_AR[t.team_type] || TEAM_TYPE_AR.mixed;
    return `
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden card-hover cursor-pointer"
         onclick="openTeam(${t.id})">
        <div class="h-2" style="background:${t.color}"></div>
        <div class="p-5">
            <div class="flex items-start justify-between mb-3">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl"
                         style="background:${t.color}22">
                        ${t.logo_emoji || SPORT_ICONS[t.sport_type] || '🎯'}
                    </div>
                    <div>
                        <h4 class="font-bold text-gray-800">${esc(t.name)}</h4>
                        <p class="text-xs text-gray-500">${esc(t.sport_type)}</p>
                    </div>
                </div>
                <span class="badge ${type.color} text-xs">${type.icon} ${type.text}</span>
            </div>
            <div class="flex items-center justify-between text-sm text-gray-500">
                <span>👤 ${t.member_count || 0} لاعب</span>
                ${t.coach_name ? `<span>🎽 ${esc(t.coach_name)}</span>` : ''}
                ${t.class_name ? `<span class="text-xs">${esc(t.class_name)}</span>` : ''}
            </div>
        </div>
    </div>`;
}

// ============================================================
// TEAM DETAIL
// ============================================================
async function openTeam(id) {
    stCurrentTeam = id;
    stTab = 'members';
    const mc = document.getElementById('mainContent');
    mc.innerHTML = showLoading();

    const r = await STAPI.get('team_get', { id });
    if (!r?.success) { mc.innerHTML = '<p class="text-red-500">خطأ في تحميل الفريق</p>'; return; }

    const t = r.data;
    const type = TEAM_TYPE_AR[t.team_type] || TEAM_TYPE_AR.mixed;

    mc.innerHTML = `
    <div class="fade-in">
        <div class="mb-4">
            <button onclick="renderSportsTeams()" class="text-green-600 hover:text-green-800 font-semibold cursor-pointer">→ العودة للفرق</button>
        </div>

        <!-- Team Header -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-4" style="border-top: 4px solid ${t.color}">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-2xl flex items-center justify-center text-3xl"
                         style="background:${t.color}22">
                        ${t.logo_emoji || SPORT_ICONS[t.sport_type] || '🎯'}
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">${esc(t.name)}</h2>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <span class="badge ${type.color}">${type.icon} ${type.text}</span>
                            <span class="badge bg-gray-100 text-gray-700">⚽ ${esc(t.sport_type)}</span>
                            ${t.coach_name ? `<span class="badge bg-teal-100 text-teal-700">🎽 ${esc(t.coach_name)}</span>` : ''}
                            ${t.class_name ? `<span class="badge bg-blue-100 text-blue-700">🏫 ${esc(t.class_name)}</span>` : ''}
                        </div>
                    </div>
                </div>
                ${canEdit() ? `
                <div class="flex gap-2 flex-wrap">
                    <button onclick="showTeamForm(${t.id})" class="bg-blue-100 text-blue-700 px-4 py-2 rounded-xl text-sm font-semibold hover:bg-blue-200 cursor-pointer">✏️ تعديل</button>
                    ${isAdmin() ? `<button onclick="deleteTeamAction(${t.id})" class="bg-red-100 text-red-700 px-4 py-2 rounded-xl text-sm font-semibold hover:bg-red-200 cursor-pointer">🗑️ حذف</button>` : ''}
                </div>` : ''}
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
            ${_stStatCard('اللاعبون الأساسيون', t.members?.filter(m => m.status === 'active').length || 0, '👕', 'blue')}
            ${_stStatCard('الاحتياط', t.members?.filter(m => m.status === 'substitute').length || 0, '🪑', 'orange')}
            ${_stStatCard('جلسات التدريب', t.training_stats?.total_sessions || 0, '🏋️', 'indigo')}
            ${_stStatCard('آخر تدريب', t.training_stats?.last_session || '—', '📅', 'purple')}
        </div>

        <!-- Tabs -->
        <div class="bg-white rounded-t-2xl border border-gray-100 border-b-0 flex overflow-x-auto">
            <button id="tab-btn-members"  onclick="stOpenTab('members',${t.id})"  class="tab-btn px-6 py-3 text-sm active cursor-pointer whitespace-nowrap">👥 التشكيلة (${t.members?.length || 0})</button>
            <button id="tab-btn-training" onclick="stOpenTab('training',${t.id})" class="tab-btn px-6 py-3 text-sm cursor-pointer whitespace-nowrap">🏋️ التدريبات</button>
        </div>
        <div id="stTabContent" class="bg-white rounded-b-2xl shadow-sm border border-gray-100 border-t-0 p-6">
            ${renderMembersTab(t)}
        </div>
    </div>`;
}

async function stOpenTab(tab, teamId) {
    stTab = tab;
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(`tab-btn-${tab}`)?.classList.add('active');

    const tc = document.getElementById('stTabContent');
    tc.innerHTML = showLoading();

    if (tab === 'members') {
        const r = await STAPI.get('team_get', { id: teamId });
        tc.innerHTML = r?.success ? renderMembersTab(r.data) : '<p class="text-red-500">خطأ في التحميل</p>';
    } else {
        await renderTrainingTab(teamId, tc);
    }
}

// ============================================================
// MEMBERS TAB
// ============================================================
function renderMembersTab(t) {
    const active = (t.members || []).filter(m => m.status === 'active');
    const subs = (t.members || []).filter(m => m.status === 'substitute');
    const others = (t.members || []).filter(m => !['active', 'substitute'].includes(m.status));

    const memberRow = (m) => {
        const st = MEMBER_STATUS_AR[m.status] || MEMBER_STATUS_AR.active;
        return `
        <tr class="border-t border-gray-100 hover:bg-gray-50 transition">
            <td class="px-3 py-3 text-center font-bold text-gray-500">${m.jersey_number || '—'}</td>
            <td class="px-3 py-3 font-semibold">${esc(m.student_name)}</td>
            <td class="px-3 py-3 text-sm text-gray-500">${esc(m.class_name || '')}</td>
            <td class="px-3 py-3 text-sm text-gray-500">${esc(m.position || '—')}</td>
            <td class="px-3 py-3"><span class="badge ${st.color}">${st.text}</span></td>
            ${canEdit() ? `
            <td class="px-3 py-3 text-center">
                <div class="flex gap-1 justify-center">
                    <button onclick="showEditMemberForm(${m.id},'${esc(m.student_name)}')" class="text-blue-500 hover:text-blue-700 cursor-pointer text-lg" title="تعديل">✏️</button>
                    <button onclick="removeMemberAction(${m.id})" class="text-red-500 hover:text-red-700 cursor-pointer text-lg" title="إزالة">🗑️</button>
                </div>
            </td>` : ''}
        </tr>`;
    };

    return `
    <div>
        <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
            <h4 class="font-bold text-gray-800">👥 تشكيلة الفريق (${t.members?.length || 0} لاعب)</h4>
            ${canEdit() ? `
            <div class="flex gap-2">
                <button onclick="showAddMemberForm(${t.id})" class="bg-green-600 text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-green-700 cursor-pointer">+ إضافة لاعب</button>
            </div>` : ''}
        </div>

        ${t.members?.length > 0 ? `
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-50 text-right">
                        <th class="px-3 py-3 text-sm font-bold text-gray-600 text-center">#</th>
                        <th class="px-3 py-3 text-sm font-bold text-gray-600">اللاعب</th>
                        <th class="px-3 py-3 text-sm font-bold text-gray-600">الفصل</th>
                        <th class="px-3 py-3 text-sm font-bold text-gray-600">المركز</th>
                        <th class="px-3 py-3 text-sm font-bold text-gray-600">الحالة</th>
                        ${canEdit() ? '<th class="px-3 py-3"></th>' : ''}
                    </tr>
                </thead>
                <tbody>
                    ${active.length > 0 ? `
                    <tr><td colspan="6" class="px-3 py-2 bg-green-50 text-green-700 font-bold text-sm">▶ أساسيون (${active.length})</td></tr>
                    ${active.map(memberRow).join('')}` : ''}
                    ${subs.length > 0 ? `
                    <tr><td colspan="6" class="px-3 py-2 bg-yellow-50 text-yellow-700 font-bold text-sm">▶ احتياط (${subs.length})</td></tr>
                    ${subs.map(memberRow).join('')}` : ''}
                    ${others.length > 0 ? `
                    <tr><td colspan="6" class="px-3 py-2 bg-gray-50 text-gray-500 font-bold text-sm">▶ أخرى (${others.length})</td></tr>
                    ${others.map(memberRow).join('')}` : ''}
                </tbody>
            </table>
        </div>` : `
        <div class="text-center py-12 text-gray-400">
            <p class="text-4xl mb-2">👥</p>
            <p>لا يوجد لاعبون في هذا الفريق بعد</p>
            ${canEdit() ? '<p class="text-sm mt-1">اضغط "إضافة لاعب" لإضافة أسماء يدوياً</p>' : ''}
        </div>`}
    </div>`;
}

// ============================================================
// TRAINING TAB
// ============================================================
async function renderTrainingTab(teamId, container) {
    const r = await STAPI.get('sessions_list', { team_id: teamId });
    const sessions = r?.data || [];

    container.innerHTML = `
    <div>
        <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
            <h4 class="font-bold text-gray-800">🏋️ جلسات التدريب (${sessions.length})</h4>
            ${canEdit() ? `
            <button onclick="showSessionForm(${teamId})" class="bg-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-semibold hover:bg-indigo-700 cursor-pointer">+ جلسة تدريب جديدة</button>
            ` : ''}
        </div>

        ${sessions.length > 0 ? `
        <div class="space-y-3">
            ${sessions.map(s => `
            <div class="border border-gray-200 rounded-xl p-4 hover:border-indigo-300 transition">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h5 class="font-bold text-gray-800">${esc(s.title)}</h5>
                        <div class="flex flex-wrap gap-3 mt-1 text-sm text-gray-500">
                            <span>📅 ${s.session_date}</span>
                            ${s.start_time ? `<span>🕐 ${s.start_time}${s.end_time ? ' — ' + s.end_time : ''}</span>` : ''}
                            ${s.venue ? `<span>📍 ${esc(s.venue)}</span>` : ''}
                        </div>
                        ${s.focus ? `<p class="text-xs text-indigo-600 mt-1">🎯 ${esc(s.focus)}</p>` : ''}
                    </div>
                    <div class="flex items-center gap-3">
                        ${s.total_attendance > 0 ? `
                        <div class="text-center">
                            <p class="text-lg font-black text-green-600">${s.present_count}/${s.total_attendance}</p>
                            <p class="text-xs text-gray-400">حضور</p>
                        </div>` : ''}
                        ${s.avg_performance ? `
                        <div class="text-center">
                            <p class="text-lg font-black text-indigo-600">${s.avg_performance}/10</p>
                            <p class="text-xs text-gray-400">أداء</p>
                        </div>` : ''}
                        <div class="flex gap-1">
                            <button onclick="openAttendance(${s.id})" class="bg-green-100 text-green-700 px-3 py-2 rounded-lg text-xs font-semibold hover:bg-green-200 cursor-pointer">📋 الحضور</button>
                            ${canEdit() ? `
                            <button onclick="deleteSessionAction(${s.id},${teamId})" class="text-red-400 hover:text-red-600 cursor-pointer px-2">🗑️</button>
                            ` : ''}
                        </div>
                    </div>
                </div>
            </div>`).join('')}
        </div>` : `
        <div class="text-center py-12 text-gray-400">
            <p class="text-4xl mb-2">🏋️</p>
            <p>لا توجد جلسات تدريب مسجلة</p>
            ${canEdit() ? '<p class="text-sm mt-1">اضغط "+ جلسة تدريب جديدة" لإضافة أول تدريب</p>' : ''}
        </div>`}
    </div>`;
}

// ============================================================
// LOTTERY WIZARD — 3 خطوات
// ============================================================

// مصفوفة مؤقتة للطلاب المُختارين بين الخطوات
let _lotteryAllStudents = [];
let _lotterySelectedIds = new Set();

async function showLotteryWizard() {
    // جلب جميع الطلاب مع معلومات الفصل
    const r = await STAPI.get('lottery_available_students');
    _lotteryAllStudents = r?.data || [];
    _lotterySelectedIds = new Set();

    if (_lotteryAllStudents.length === 0) {
        showToast('لا يوجد طلاب في النظام', 'error');
        return;
    }

    showModal(`
    <div class="p-4 md:p-6 w-full max-w-2xl mx-auto">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h3 class="text-xl md:text-2xl font-black text-gray-800">🎲 قرعة الفرق العشوائية</h3>
                <p class="text-gray-500 text-xs md:text-sm mt-0.5">حدد الطلاب المشاركين ثم وزّعهم على فرق</p>
            </div>
            <!-- Progress Bar -->
            <div class="flex gap-3 items-center justify-center bg-gray-50 px-4 py-2 rounded-2xl">
                <span id="lwStep1Dot" class="w-8 h-8 rounded-full bg-green-600 text-white flex items-center justify-center font-black text-sm transition-all duration-300">1</span>
                <span class="w-4 md:w-8 h-0.5 bg-gray-200"></span>
                <span id="lwStep2Dot" class="w-8 h-8 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center font-black text-sm transition-all duration-300">2</span>
                <span class="w-4 md:w-8 h-0.5 bg-gray-200"></span>
                <span id="lwStep3Dot" class="w-8 h-8 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center font-black text-sm transition-all duration-300">3</span>
            </div>
        </div>

        <!-- Step 1: Student Selection -->
        <div id="lwS1" class="fade-in">
            <div class="bg-green-50 border border-green-100 rounded-2xl p-4 mb-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-green-600 text-white flex items-center justify-center text-lg shadow-lg shadow-green-100">1</div>
                <div>
                    <span class="text-green-800 font-black block text-sm">الخطوة الأولى</span>
                    <span class="text-green-600 text-xs font-bold">اختر الطلاب المشاركين في القرعة</span>
                </div>
                <span id="lwSelCount" class="mr-auto bg-green-600 text-white text-xs font-black px-3 py-1 rounded-full shadow-sm">0 محدد</span>
            </div>

            <!-- Filters -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
                <div class="relative">
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 grayscale opacity-50">🔍</span>
                    <input type="text" id="lwSearch" oninput="lwFilterStudents()"
                           placeholder="ابحث باسم الطالب..."
                           class="w-full pr-10 pl-4 py-3 border-2 border-gray-100 rounded-2xl text-sm focus:border-green-500 focus:outline-none font-bold bg-gray-50/50">
                </div>
                <select id="lwClassFilter" onchange="lwFilterStudents()"
                        class="w-full px-4 py-3 border-2 border-gray-100 rounded-2xl text-sm focus:border-green-500 focus:outline-none font-bold bg-gray-50/50">
                    <option value="">كل الفصول</option>
                    ${[...new Map(_lotteryAllStudents.map(s => [s.class_name, s.class_name])).entries()]
            .map(([cn]) => `<option value="${esc(cn)}">${esc(cn)}</option>`).join('')}
                </select>
            </div>

            <!-- Selection Controls -->
            <div class="flex flex-wrap items-center gap-4 mb-3 text-xs md:text-sm">
                <button onclick="lwSelectAll()" class="text-green-600 hover:text-green-800 cursor-pointer font-black flex items-center gap-1 transition">✅ تحديد الكل</button>
                <button onclick="lwClearAll()" class="text-red-500 hover:text-red-700 cursor-pointer font-black flex items-center gap-1 transition">❌ إلغاء الكل</button>
                <div class="mr-auto text-gray-400 font-bold" id="lwVisCount">${_lotteryAllStudents.length} طالب متاح</div>
            </div>

            <!-- Student List -->
            <div id="lwStudentList" class="max-h-[30vh] md:max-h-64 overflow-y-auto border-2 border-gray-100 rounded-3xl divide-y divide-gray-50 bg-white">
                ${_renderLwStudentRows(_lotteryAllStudents)}
            </div>

            <div class="flex flex-col sm:flex-row gap-3 mt-6">
                <button onclick="lwGoStep2()" class="flex-1 bg-green-600 text-white py-4 rounded-2xl font-black hover:bg-green-700 shadow-xl shadow-green-100 transition order-1 sm:order-2">
                    التالي: إعدادات التوزيع ←
                </button>
                <button onclick="closeModal()" class="bg-gray-100 text-gray-500 px-8 py-4 rounded-2xl font-black hover:bg-gray-200 transition order-2 sm:order-1">إلغاء</button>
            </div>
        </div>

        <!-- Step 2: Distribution Settings -->
        <div id="lwS2" class="hidden fade-in">
            <div class="bg-blue-50 border border-blue-100 rounded-2xl p-4 mb-6 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center text-lg shadow-lg shadow-blue-100">2</div>
                <div>
                    <span class="text-blue-800 font-black block text-sm">الخطوة الثانية</span>
                    <span class="text-blue-600 text-xs font-bold">حدد طريقة التوزيع على الفرق</span>
                </div>
                <span id="lwS2Count" class="mr-auto bg-blue-600 text-white text-xs font-black px-3 py-1 rounded-full shadow-sm">0 طالب</span>
            </div>

            <div class="space-y-6">
                <div>
                    <label class="block font-black text-gray-700 mb-2">نوع النشاط الرياضي *</label>
                    <select id="lSport" class="w-full px-4 py-4 border-2 border-gray-100 rounded-2xl focus:border-green-500 focus:outline-none font-bold bg-gray-50/50">
                        ${Object.keys(SPORT_ICONS).map(s => `<option value="${s}">${SPORT_ICONS[s]} ${s}</option>`).join('')}
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-black text-gray-700 mb-2">عدد الفرق المستهدفة</label>
                        <input type="number" id="lTeamCount" min="2" max="20" value="4" oninput="lwUpdatePreview()"
                               class="w-full px-4 py-4 border-2 border-gray-100 rounded-2xl focus:border-green-500 focus:outline-none font-bold bg-gray-50/50">
                    </div>
                    <div>
                        <label class="block font-black text-gray-700 mb-2">أو: عدد اللاعبين لكل فريق</label>
                        <input type="number" id="lPerTeam" min="2" max="50" placeholder="اختياري" oninput="lwUpdatePreview()"
                               class="w-full px-4 py-4 border-2 border-gray-100 rounded-2xl focus:border-green-500 focus:outline-none font-bold bg-gray-50/50">
                    </div>
                </div>

                <!-- Preview Area -->
                <div id="lwDistPreview" class="bg-gray-900 text-white rounded-2xl p-5 text-sm md:text-base font-bold text-center border-b-4 border-green-500 shadow-inner"></div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3 mt-8">
                <button onclick="lwGoStep1()" class="bg-gray-100 text-gray-600 px-6 py-4 rounded-2xl font-black hover:bg-gray-200 transition">→ السابق</button>
                <button onclick="runLotteryPreview()" class="flex-1 bg-green-600 text-white py-4 rounded-2xl font-black hover:bg-green-700 shadow-xl shadow-green-100 transition">
                    🎲 تشغيل القرعة الآن
                </button>
                <button onclick="closeModal()" class="sm:hidden bg-white text-gray-400 py-4 font-bold">إلغاء</button>
            </div>
        </div>

        <!-- Step 3: Result Preview -->
        <div id="lwS3" class="hidden fade-in"></div>
    </div>`);

    lwUpdateCount();
    lwUpdatePreview();
}

// ---- helpers لخطوة 1 ----

function _renderLwStudentRows(students) {
    if (students.length === 0) return '<p class="text-center text-gray-400 py-10 font-bold text-sm">عذراً، لا يوجد طلاب مطابقون للبحث</p>';

    // Grouping by Class
    const byClass = {};
    students.forEach(s => {
        const cn = s.class_name || 'غير محدد';
        if (!byClass[cn]) byClass[cn] = [];
        byClass[cn].push(s);
    });

    return Object.entries(byClass).map(([cls, list]) => `
    <div class="mb-2">
        <div class="px-4 py-2 bg-gray-50 flex items-center justify-between sticky top-0 z-10 border-b border-gray-100">
            <span class="text-xs font-black text-gray-500 uppercase tracking-widest">${esc(cls)}</span>
            <div class="flex gap-3">
                <button onclick="lwSelectClass('${esc(cls)}')" class="text-[10px] bg-green-50 text-green-600 px-2 py-0.5 rounded-lg font-black hover:bg-green-600 hover:text-white transition cursor-pointer">تحديد الكل</button>
                <button onclick="lwClearClass('${esc(cls)}')" class="text-[10px] bg-gray-100 text-gray-400 px-2 py-0.5 rounded-lg font-black hover:bg-gray-400 hover:text-white transition cursor-pointer">إلغاء</button>
            </div>
        </div>
        ${list.map(s => `
        <label class="flex items-center justify-between px-4 py-3 hover:bg-green-50/50 cursor-pointer transition group border-b border-gray-50 last:border-0" data-class="${esc(s.class_name)}">
            <div class="flex items-center gap-3">
                <div class="relative w-5 h-5">
                    <input type="checkbox" value="${s.id}" onchange="lwToggle(${s.id}, this.checked)"
                           class="lw-student-cb w-5 h-5 appearance-none border-2 border-gray-200 rounded-lg checked:bg-green-600 checked:border-green-600 transition-all cursor-pointer peer"
                           ${_lotterySelectedIds.has(s.id) || _lotterySelectedIds.has(String(s.id)) ? 'checked' : ''}>
                    <span class="absolute inset-0 flex items-center justify-center text-white scale-0 peer-checked:scale-100 transition-transform pointer-events-none text-xs font-bold">✓</span>
                </div>
                <div>
                    <span class="font-bold text-gray-700 block text-sm group-hover:text-green-700 transition-colors">${esc(s.name)}</span>
                    <span class="text-[9px] text-gray-400 font-mono font-bold tracking-tighter uppercase">ID: ${esc(s.student_number)}</span>
                </div>
            </div>
        </label>`).join('')}
    </div>`).join('');
}

function lwFilterStudents() {
    const q = document.getElementById('lwSearch').value.toLowerCase();
    const cls = document.getElementById('lwClassFilter').value;

    const filtered = _lotteryAllStudents.filter(s => {
        const matchName = !q || s.name.toLowerCase().includes(q);
        const matchCls = !cls || s.class_name === cls;
        return matchName && matchCls;
    });

    document.getElementById('lwStudentList').innerHTML = _renderLwStudentRows(filtered);
    document.getElementById('lwVisCount').textContent = filtered.length + ' طالب';
}

function lwToggle(id, checked) {
    if (checked) _lotterySelectedIds.add(String(id));
    else _lotterySelectedIds.delete(String(id));
    lwUpdateCount();
}

function lwUpdateCount() {
    const n = _lotterySelectedIds.size;
    const el = document.getElementById('lwSelCount');
    if (el) el.textContent = n + ' محدد';
}

function lwSelectAll() {
    document.querySelectorAll('.lw-student-cb').forEach(cb => {
        cb.checked = true;
        _lotterySelectedIds.add(cb.value);
    });
    lwUpdateCount();
}

function lwClearAll() {
    document.querySelectorAll('.lw-student-cb').forEach(cb => {
        cb.checked = false;
        _lotterySelectedIds.delete(cb.value);
    });
    lwUpdateCount();
}

function lwSelectClass(cls) {
    document.querySelectorAll(`.lw-student-cb`).forEach(cb => {
        const row = cb.closest('label');
        if (row && row.getAttribute('data-class') === cls) {
            cb.checked = true;
            _lotterySelectedIds.add(String(cb.value));
        }
    });
    lwUpdateCount();
}

function lwClearClass(cls) {
    document.querySelectorAll(`.lw-student-cb`).forEach(cb => {
        const row = cb.closest('label');
        if (row && row.getAttribute('data-class') === cls) {
            cb.checked = false;
            _lotterySelectedIds.delete(String(cb.value));
        }
    });
    lwUpdateCount();
}

function lwGoStep1() {
    document.getElementById('lwS1')?.classList.remove('hidden');
    document.getElementById('lwS2')?.classList.add('hidden');
    document.getElementById('lwStep1Dot').className = 'w-8 h-8 rounded-full bg-green-600 text-white flex items-center justify-center font-black text-sm transition-all duration-300';
    document.getElementById('lwStep2Dot').className = 'w-8 h-8 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center font-black text-sm transition-all duration-300';
}

function lwGoStep2() {
    if (_lotterySelectedIds.size < 2) {
        showToast('اختر طالبَين على الأقل للقرعة', 'error');
        return;
    }
    document.getElementById('lwS1').classList.add('hidden');
    document.getElementById('lwS2').classList.remove('hidden');
    document.getElementById('lwS3').classList.add('hidden');
    document.getElementById('lwStep1Dot').className = 'w-8 h-8 rounded-full bg-green-100 text-green-600 flex items-center justify-center font-black text-sm transition-all duration-300';
    document.getElementById('lwStep2Dot').className = 'w-8 h-8 rounded-full bg-green-600 text-white flex items-center justify-center font-black text-sm transition-all duration-300';
    document.getElementById('lwStep3Dot').className = 'w-8 h-8 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center font-black text-sm transition-all duration-300';
    document.getElementById('lwS2Count').textContent = _lotterySelectedIds.size + ' طالب';
    lwUpdatePreview();
}

function lwUpdatePreview() {
    const n = _lotterySelectedIds.size;
    const tc = parseInt(document.getElementById('lTeamCount')?.value) || 0;
    const pp = parseInt(document.getElementById('lPerTeam')?.value) || 0;
    const preview = document.getElementById('lwDistPreview');
    if (!preview || n === 0) return;

    let teams = tc >= 2 ? tc : (pp >= 2 ? Math.ceil(n / pp) : 0);
    if (teams < 2) { preview.textContent = 'حدد عدد الفرق أو عدد اللاعبين لكل فريق'; return; }
    if (teams > n) { preview.textContent = '⚠️ عدد الفرق أكبر من عدد الطلاب'; return; }

    const base = Math.floor(n / teams);
    const extra = n % teams;
    preview.innerHTML = `
        <span class="font-bold text-orange-600">${n}</span> طالب ÷ 
        <span class="font-bold text-orange-600">${teams}</span> فرق = 
        ${extra > 0
            ? `<span class="font-bold">${extra}</span> فرق بـ <span class="font-bold">${base + 1}</span> لاعب و<span class="font-bold">${teams - extra}</span> فرق بـ <span class="font-bold">${base}</span> لاعب`
            : `<span class="font-bold">${base}</span> لاعب في كل فريق`
        }`;
}

async function runLotteryPreview() {
    const teamCount = parseInt(document.getElementById('lTeamCount').value) || 0;
    const perTeam = parseInt(document.getElementById('lPerTeam').value) || 0;
    const sport = document.getElementById('lSport').value;

    if (_lotterySelectedIds.size < 2) {
        showToast('اختر طالبَين على الأقل', 'error');
        return;
    }

    const r = await STAPI.post('lottery_preview', {
        student_ids: [..._lotterySelectedIds],
        team_count: teamCount,
        players_per_team: perTeam,
        sport_type: sport
    });

    if (!r?.success) { showToast(r?.error || 'خطأ في القرعة', 'error'); return; }

    const res = r.data;

    // Moving to Step 3
    document.getElementById('lwS2').classList.add('hidden');
    document.getElementById('lwStep2Dot').className = 'w-8 h-8 rounded-full bg-green-100 text-green-600 flex items-center justify-center font-black text-sm transition-all duration-300';
    document.getElementById('lwStep3Dot').className = 'w-8 h-8 rounded-full bg-green-600 text-white flex items-center justify-center font-black text-sm transition-all duration-300';

    const s3 = document.getElementById('lwS3');
    s3.classList.remove('hidden');
    s3.innerHTML = `
    <div class="fade-in">
        <div class="bg-green-50 border border-green-100 rounded-2xl p-4 mb-6 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-green-600 text-white flex items-center justify-center text-lg shadow-lg shadow-green-100">3</div>
            <div>
                <span class="text-green-800 font-black block text-sm">الخطوة الثالثة والأخيرة</span>
                <span class="text-green-600 text-xs font-bold">راجع توزيع القرعة ثم احفظ الفرق</span>
            </div>
            <span class="mr-auto text-xs text-green-600 font-black bg-white px-3 py-1 rounded-full border border-green-100">${res.total_teams} فرق • ${res.total_players} لاعب</span>
        </div>

        <div class="space-y-3 max-h-[40vh] md:max-h-80 overflow-y-auto mb-6 px-1">
            ${res.teams.map((t, i) => `
            <div class="group border-2 border-gray-100 rounded-2xl p-4 hover:border-green-300 hover:bg-green-50/30 transition-all">
                <div class="flex items-center gap-3 mb-3">
                    <span class="w-8 h-8 rounded-xl bg-green-100 text-green-600 flex items-center justify-center text-xs font-black group-hover:bg-green-600 group-hover:text-white transition-colors">${i + 1}</span>
                    <input id="lName${i}" type="text" value="${esc(t.name)}"
                           class="flex-1 font-black text-gray-800 border-b-2 border-dashed border-gray-200 focus:outline-none focus:border-green-500 bg-transparent py-1">
                    <span class="text-[10px] md:text-xs text-gray-400 font-bold whitespace-nowrap bg-gray-50 px-2 py-1 rounded-lg">${t.count} لاعب</span>
                </div>
                <div class="flex flex-wrap gap-1.5 md:gap-2 pr-11">
                    ${t.members.map(m => `<span class="text-[10px] md:text-xs bg-white border border-gray-100 text-gray-600 px-2.5 py-1 rounded-lg font-bold shadow-sm">${esc(m.name || m.student_name)}</span>`).join('')}
                </div>
            </div>`).join('')}
        </div>

        <div class="flex flex-col sm:flex-row gap-3">
            <button onclick="lwGoStep2()" class="bg-gray-100 text-gray-600 px-6 py-4 rounded-2xl font-black hover:bg-gray-200 transition">→ السابق</button>
            <button onclick="runLotteryPreview()" class="bg-green-50 text-green-700 px-6 py-4 rounded-2xl font-black hover:bg-green-100 transition flex items-center justify-center gap-2">🔄 إعادة القرعة</button>
            <button onclick="confirmLottery(${JSON.stringify(res).replace(/"/g, '&quot;')})"
                    class="flex-1 bg-green-600 text-white py-4 rounded-2xl font-black hover:bg-green-700 shadow-xl shadow-green-100 transition">
                ✅ اعتماد وحفظ الفرق
            </button>
        </div>
    </div>`;
}

async function confirmLottery(previewData) {
    const sport = document.getElementById('lSport')?.value || 'كرة قدم';

    const teams = previewData.teams.map((t, i) => ({
        name: document.getElementById(`lName${i}`)?.value || t.name,
        members: t.members
    }));

    const r = await STAPI.post('lottery_confirm', { teams, sport_type: sport });
    if (r?.success) {
        closeModal();
        showToast(`✅ تم إنشاء ${r.data.count} فرق بنجاح!`);
        renderSportsTeams();
    } else {
        showToast(r?.error || 'خطأ في الحفظ', 'error');
    }
}


// ============================================================
// TEAM FORM
// ============================================================
async function showTeamForm(id = null) {
    let team = null;
    if (id) {
        const r = await STAPI.get('team_get', { id });
        team = r?.data;
    }

    const classesR = await STAPI.get('available_classes');
    const classes = classesR?.data || [];

    const usersR = await STAPI.get('available_students', { limit: 0 }).catch(() => ({ data: [] }));

    showModal(`
    <div class="p-6">
        <h3 class="text-xl font-bold mb-4">${team ? 'تعديل' : 'إنشاء'} فريق رياضي</h3>
        <div class="space-y-4">
            <div>
                <label class="block font-semibold text-gray-700 mb-1">اسم الفريق *</label>
                <input type="text" id="tfName" value="${team ? esc(team.name) : ''}"
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none"
                       placeholder="مثال: أبطال الفصل الأول">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">نوع الرياضة *</label>
                    <select id="tfSport" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                        ${Object.keys(SPORT_ICONS).map(s =>
        `<option value="${s}" ${team?.sport_type === s ? 'selected' : ''}>${SPORT_ICONS[s]} ${s}</option>`
    ).join('')}
                    </select>
                </div>
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">نوع الفريق *</label>
                    <select id="tfType" onchange="stToggleClassSelect()" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                        <option value="mixed"  ${team?.team_type === 'mixed' ? 'selected' : ''}>🔀 فريق مختلط</option>
                        <option value="class"  ${team?.team_type === 'class' ? 'selected' : ''}>🏫 منتخب فصل</option>
                        <option value="school" ${team?.team_type === 'school' ? 'selected' : ''}>⭐ منتخب مدرسة</option>
                    </select>
                </div>
            </div>

            <div id="tfClassWrapper" class="${!team || team?.team_type !== 'class' ? 'hidden' : ''}">
                <label class="block font-semibold text-gray-700 mb-1">الفصل</label>
                <select id="tfClass" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                    <option value="">— اختر فصلاً —</option>
                    ${classes.map(c => `<option value="${c.id}" ${team?.class_id == c.id ? 'selected' : ''}>${esc(c.full_name)}</option>`).join('')}
                </select>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">لون الفريق</label>
                    <input type="color" id="tfColor" value="${team?.color || '#10b981'}"
                           class="w-full h-12 border-2 border-gray-200 rounded-xl cursor-pointer">
                </div>
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">رمز الفريق</label>
                    <input type="text" id="tfEmoji" value="${team?.logo_emoji || '⚽'}" maxlength="2"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none text-center text-3xl">
                </div>
            </div>

            <div>
                <label class="block font-semibold text-gray-700 mb-1">ملاحظات</label>
                <textarea id="tfDesc" rows="2" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none"
                          placeholder="وصف اختياري...">${team?.description || ''}</textarea>
            </div>

            <div class="flex gap-3 pt-2">
                <button onclick="saveTeam(${id || 'null'})" class="flex-1 bg-green-600 text-white py-3 rounded-xl font-bold hover:bg-green-700 cursor-pointer">حفظ</button>
                <button onclick="closeModal()" class="flex-1 bg-gray-200 text-gray-700 py-3 rounded-xl font-bold hover:bg-gray-300 cursor-pointer">إلغاء</button>
            </div>
        </div>
    </div>`);
}

function stToggleClassSelect() {
    const type = document.getElementById('tfType').value;
    document.getElementById('tfClassWrapper').classList.toggle('hidden', type !== 'class');
}

async function saveTeam(id) {
    const data = {
        name: document.getElementById('tfName').value.trim(),
        sport_type: document.getElementById('tfSport').value,
        team_type: document.getElementById('tfType').value,
        class_id: document.getElementById('tfClass')?.value || null,
        color: document.getElementById('tfColor').value,
        logo_emoji: document.getElementById('tfEmoji').value,
        description: document.getElementById('tfDesc').value.trim()
    };
    if (!data.name) { showToast('أدخل اسم الفريق', 'error'); return; }

    let r;
    if (id) { data.id = id; r = await STAPI.post('team_update', data); }
    else { r = await STAPI.post('team_create', data); }

    if (r?.success) {
        closeModal();
        showToast(r.message);
        if (id && stCurrentTeam === id) openTeam(id);
        else renderSportsTeams();
    } else {
        showToast(r?.error || 'خطأ في الحفظ', 'error');
    }
}

async function deleteTeamAction(id) {
    if (!confirm('هل تريد حذف هذا الفريق ومعه جميع بياناته؟')) return;
    const r = await STAPI.get('team_delete', { id });
    if (r?.success) { showToast('تم حذف الفريق'); renderSportsTeams(); }
    else showToast(r?.error || 'خطأ', 'error');
}

// ============================================================
// MEMBER FORMS
// ============================================================
async function showAddMemberForm(teamId) {
    const r = await STAPI.get('available_students', { team_id: teamId });
    const students = r?.data || [];

    showModal(`
    <div class="p-6">
        <h3 class="text-xl font-bold mb-4">+ إضافة لاعب للفريق</h3>
        <div class="space-y-4">
            <div>
                <label class="block font-semibold text-gray-700 mb-1">اختر الطالب *</label>
                <input type="text" id="memberSearch" oninput="filterMemberList(this.value)"
                       placeholder="ابحث باسم الطالب..."
                       class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none mb-2">
                <select id="mStudent" size="5" class="w-full border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                    ${students.map(s => `<option value="${s.id}" data-name="${esc(s.name)}">${esc(s.name)} — ${esc(s.class_name || '')} (${s.student_number})</option>`).join('')}
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">رقم القميص</label>
                    <input type="number" id="mJersey" min="1" max="99" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                </div>
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">المركز</label>
                    <input type="text" id="mPosition" placeholder="مهاجم / مدافع / حارس ..." class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                </div>
            </div>
            <div>
                <label class="block font-semibold text-gray-700 mb-1">الحالة</label>
                <select id="mStatus" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                    <option value="active">أساسي</option>
                    <option value="substitute">احتياطي</option>
                </select>
            </div>
            <div class="flex gap-3 pt-2">
                <button onclick="addMemberAction(${teamId})" class="flex-1 bg-green-600 text-white py-3 rounded-xl font-bold hover:bg-green-700 cursor-pointer">إضافة</button>
                <button onclick="closeModal()" class="flex-1 bg-gray-200 text-gray-700 py-3 rounded-xl font-bold hover:bg-gray-300 cursor-pointer">إلغاء</button>
            </div>
        </div>
    </div>`);
}

function filterMemberList(query) {
    document.querySelectorAll('#mStudent option').forEach(opt => {
        opt.style.display = opt.dataset.name?.includes(query) || !query ? '' : 'none';
    });
}

async function addMemberAction(teamId) {
    const studentId = document.getElementById('mStudent').value;
    if (!studentId) { showToast('اختر طالباً', 'error'); return; }

    const r = await STAPI.post('member_add', {
        team_id: teamId, student_id: studentId,
        jersey_number: document.getElementById('mJersey').value || null,
        position: document.getElementById('mPosition').value || null,
        status: document.getElementById('mStatus').value
    });
    if (r?.success) { closeModal(); showToast('تم إضافة اللاعب'); openTeam(teamId); }
    else showToast(r?.error || 'خطأ', 'error');
}

function showEditMemberForm(memberId, name) {
    showModal(`
    <div class="p-6">
        <h3 class="text-xl font-bold mb-4">✏️ تعديل بيانات ${esc(name)}</h3>
        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">رقم القميص</label>
                    <input type="number" id="emJersey" min="1" max="99" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                </div>
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">المركز</label>
                    <input type="text" id="emPosition" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                </div>
            </div>
            <div>
                <label class="block font-semibold text-gray-700 mb-1">الحالة</label>
                <select id="emStatus" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                    <option value="active">أساسي</option>
                    <option value="substitute">احتياطي</option>
                    <option value="injured">مصاب</option>
                    <option value="suspended">موقوف</option>
                </select>
            </div>
            <div>
                <label class="block font-semibold text-gray-700 mb-1">ملاحظات</label>
                <textarea id="emNotes" rows="2" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none"></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button onclick="saveMemberUpdate(${memberId})" class="flex-1 bg-green-600 text-white py-3 rounded-xl font-bold hover:bg-green-700 cursor-pointer">حفظ</button>
                <button onclick="closeModal()" class="flex-1 bg-gray-200 text-gray-700 py-3 rounded-xl font-bold hover:bg-gray-300 cursor-pointer">إلغاء</button>
            </div>
        </div>
    </div>`);
}

async function saveMemberUpdate(memberId) {
    const r = await STAPI.post('member_update', {
        id: memberId,
        jersey_number: document.getElementById('emJersey').value || null,
        position: document.getElementById('emPosition').value || null,
        status: document.getElementById('emStatus').value,
        notes: document.getElementById('emNotes').value || null
    });
    if (r?.success) { closeModal(); showToast('تم التحديث'); openTeam(stCurrentTeam); }
    else showToast(r?.error || 'خطأ', 'error');
}

async function removeMemberAction(memberId) {
    if (!confirm('إزالة هذا اللاعب من الفريق؟')) return;
    const r = await STAPI.get('member_remove', { id: memberId });
    if (r?.success) { showToast('تم الإزالة'); openTeam(stCurrentTeam); }
    else showToast(r?.error || 'خطأ', 'error');
}

// ============================================================
// SESSION FORM
// ============================================================
function showSessionForm(teamId) {
    showModal(`
    <div class="p-6">
        <h3 class="text-xl font-bold mb-4">+ جلسة تدريب جديدة</h3>
        <div class="space-y-4">
            <div>
                <label class="block font-semibold text-gray-700 mb-1">عنوان الجلسة</label>
                <input type="text" id="sTitle" value="تدريب" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">التاريخ *</label>
                    <input type="date" id="sDate" value="${new Date().toISOString().split('T')[0]}"
                           class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                </div>
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">من</label>
                    <input type="time" id="sStart" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                </div>
                <div>
                    <label class="block font-semibold text-gray-700 mb-1">إلى</label>
                    <input type="time" id="sEnd" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                </div>
            </div>
            <div>
                <label class="block font-semibold text-gray-700 mb-1">المكان</label>
                <input type="text" id="sVenue" placeholder="الملعب / القاعة ..." class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
            </div>
            <div>
                <label class="block font-semibold text-gray-700 mb-1">محور التدريب</label>
                <input type="text" id="sFocus" placeholder="مثال: تدريب على الأركنة والضربات الحرة"
                       class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
            </div>
            <div class="flex gap-3 pt-2">
                <button onclick="saveSession(${teamId})" class="flex-1 bg-indigo-600 text-white py-3 rounded-xl font-bold hover:bg-indigo-700 cursor-pointer">حفظ</button>
                <button onclick="closeModal()" class="flex-1 bg-gray-200 text-gray-700 py-3 rounded-xl font-bold hover:bg-gray-300 cursor-pointer">إلغاء</button>
            </div>
        </div>
    </div>`);
}

async function saveSession(teamId) {
    const date = document.getElementById('sDate').value;
    if (!date) { showToast('التاريخ مطلوب', 'error'); return; }

    const r = await STAPI.post('session_create', {
        team_id: teamId,
        title: document.getElementById('sTitle').value || 'تدريب',
        session_date: date,
        start_time: document.getElementById('sStart').value || null,
        end_time: document.getElementById('sEnd').value || null,
        venue: document.getElementById('sVenue').value || null,
        focus: document.getElementById('sFocus').value || null
    });

    if (r?.success) { closeModal(); showToast('تم إنشاء الجلسة'); stOpenTab('training', teamId); }
    else showToast(r?.error || 'خطأ', 'error');
}

async function deleteSessionAction(sessionId, teamId) {
    if (!confirm('حذف جلسة التدريب وجميع بيانات حضورها؟')) return;
    const r = await STAPI.get('session_delete', { id: sessionId });
    if (r?.success) { showToast('تم الحذف'); stOpenTab('training', teamId); }
    else showToast(r?.error || 'خطأ', 'error');
}

// ============================================================
// ATTENDANCE PAGE
// ============================================================
async function openAttendance(sessionId) {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = showLoading();

    const r = await STAPI.get('attendance_get', { session_id: sessionId });
    if (!r?.success) { mc.innerHTML = '<p class="text-red-500">خطأ</p>'; return; }

    const { session, attendance } = r.data;

    const ATND_STATUS = {
        present: { text: 'حاضر', color: 'bg-green-500' },
        absent: { text: 'غائب', color: 'bg-red-500' },
        late: { text: 'متأخر', color: 'bg-yellow-500' },
        excused: { text: 'معذور', color: 'bg-gray-400' }
    };

    mc.innerHTML = `
    <div class="fade-in">
        <div class="mb-4 flex items-center gap-3">
            <button onclick="openTeam(${session.team_id})" class="text-green-600 hover:text-green-800 font-semibold cursor-pointer">→ العودة</button>
            <span class="text-gray-400">|</span>
            <h3 class="font-bold text-gray-800">📋 حضور: ${esc(session.title)} — ${session.session_date}</h3>
        </div>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-3 py-3 text-right text-sm font-bold text-gray-600">اللاعب</th>
                            <th class="px-3 py-3 text-right text-sm font-bold text-gray-600">الفصل</th>
                            <th class="px-3 py-3 text-center text-sm font-bold text-gray-600">الحضور</th>
                            <th class="px-3 py-3 text-center text-sm font-bold text-gray-600">الأداء (1-10)</th>
                            <th class="px-3 py-3 text-right text-sm font-bold text-gray-600">ملاحظات</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${attendance.map(a => `
                        <tr class="border-t border-gray-100" data-student="${a.student_id}">
                            <td class="px-3 py-3 font-semibold">${esc(a.student_name)}</td>
                            <td class="px-3 py-3 text-sm text-gray-500">${esc(a.class_name || '')}</td>
                            <td class="px-3 py-3 text-center">
                                <select class="atnd-status border-2 border-gray-200 rounded-xl px-2 py-1 text-sm focus:outline-none focus:border-green-500">
                                    ${Object.entries(ATND_STATUS).map(([k, v]) =>
        `<option value="${k}" ${a.status === k ? 'selected' : ''}>${v.text}</option>`
    ).join('')}
                                </select>
                            </td>
                            <td class="px-3 py-3 text-center">
                                <input type="number" min="1" max="10" value="${a.performance || ''}"
                                       class="atnd-perf w-16 border-2 border-gray-200 rounded-xl px-2 py-1 text-sm text-center focus:outline-none focus:border-green-500">
                            </td>
                            <td class="px-3 py-3">
                                <input type="text" value="${esc(a.notes || '')}"
                                       class="atnd-notes w-full border-b border-dashed border-gray-200 focus:outline-none focus:border-green-400 text-sm bg-transparent">
                            </td>
                        </tr>`).join('')}
                    </tbody>
                </table>
            </div>

            ${canEdit() ? `
            <div class="mt-6 flex gap-3">
                <button onclick="saveAttendanceAction(${sessionId},${session.team_id})"
                        class="bg-green-600 text-white px-8 py-3 rounded-xl font-bold hover:bg-green-700 cursor-pointer">
                    💾 حفظ الحضور
                </button>
            </div>` : ''}
        </div>
    </div>`;
}

async function saveAttendanceAction(sessionId, teamId) {
    const records = [];
    document.querySelectorAll('tr[data-student]').forEach(row => {
        records.push({
            student_id: row.dataset.student,
            status: row.querySelector('.atnd-status').value,
            performance: row.querySelector('.atnd-perf').value || null,
            notes: row.querySelector('.atnd-notes').value || null
        });
    });

    const r = await STAPI.post('attendance_save', { session_id: sessionId, records });
    if (r?.success) showToast('✅ تم حفظ الحضور بنجاح');
    else showToast(r?.error || 'خطأ', 'error');
}

console.log('✅ sports_teams.js loaded');
