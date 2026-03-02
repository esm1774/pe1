/**
 * PE Smart School System - Tournaments Module
 * Complete tournament management with brackets, standings, and print
 */

// ============================================================
// TOURNAMENT API HELPER
// ============================================================
const TAPI = {
    // Ensure we always point to the module root regardless of current path slug
    get base() {
        return (window.APP_BASE || '/') + 'modules/tournaments/api.php';
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
            if (data && method !== 'GET') {
                options.headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(data);
            }

            const r = await fetch(url, options);
            const text = await r.text();

            try {
                const result = JSON.parse(text);
                if (!r.ok && r.status === 401) {
                    showLoginPage();
                    return null;
                }
                return result;
            } catch (e) {
                console.error('Tournament API parse error:', text.substring(0, 200));
                showToast('خطأ في استجابة الخادم', 'error');
                return null;
            }
        } catch (e) {
            console.error('Tournament API Error:', e);
            showToast('خطأ في الاتصال بنظام البطولات', 'error');
            return null;
        }
    },

    get(action, params = {}) { return this.request(action, 'GET', null, params); },
    post(action, data = {}, params = {}) { return this.request(action, 'POST', data, params); }
};

// ============================================================
// CONSTANTS
// ============================================================
const TOURNAMENT_TYPES = {
    single_elimination: '🏆 خروج مباشر (خسارة واحدة = إقصاء)',
    double_elimination: '🔄 خروج مزدوج (خسارتان = إقصاء)',
    round_robin_single: '📊 دوري دور واحد',
    round_robin_double: '📊 دوري دورين (ذهاب وإياب)',
    mixed: '🔄 خلط (مجموعات + خروج المغلوب)'
};

const TOURNAMENT_TYPES_SHORT = {
    single_elimination: '🏆 خروج مباشر',
    double_elimination: '🔄 خروج مزدوج',
    round_robin_single: '📊 دوري دور واحد',
    round_robin_double: '📊 دوري دورين',
    mixed: '🔄 خلط (مجموعات إقصائي)'
};

const STATUS_AR = {
    draft: { text: 'مسودة', color: 'bg-gray-100 text-gray-700', icon: '📝' },
    registration: { text: 'تسجيل', color: 'bg-emerald-100 text-emerald-700', icon: '📋' },
    in_progress: { text: 'جارية', color: 'bg-green-100 text-green-700', icon: '⚡' },
    completed: { text: 'منتهية', color: 'bg-teal-100 text-teal-700', icon: '✅' },
    cancelled: { text: 'ملغية', color: 'bg-red-100 text-red-700', icon: '❌' }
};

const MATCH_STATUS = {
    scheduled: { text: 'مجدولة', color: 'text-gray-400' },
    in_progress: { text: 'جارية', color: 'text-emerald-600' },
    completed: { text: 'منتهية', color: 'text-green-600' },
    cancelled: { text: 'ملغية', color: 'text-red-500' }
};

// ============================================================
// STATE
// ============================================================
let currentTournament = null;
let tournamentTab = 'teams';
let tournamentFilter = 'all';

// ============================================================
// MAIN RENDER
// ============================================================
async function renderTournaments() {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = showLoading();

    if (currentTournament) {
        await renderTournamentDetail(currentTournament);
        return;
    }

    const r = await TAPI.get('tournaments_list');
    if (!r || !r.success) {
        mc.innerHTML = `
            <div class="text-center py-12 fade-in">
                <p class="text-5xl mb-4">⚽</p>
                <p class="text-xl font-bold text-gray-600 mb-2">لا يمكن تحميل البطولات</p>
                <p class="text-gray-400 mb-4">${esc(r?.error || 'تحقق من تشغيل install_tournaments.php')}</p>
                <div class="flex gap-3 justify-center">
                    <button onclick="renderTournaments()" class="bg-emerald-600 text-white px-6 py-3 rounded-xl font-semibold cursor-pointer">🔄 إعادة المحاولة</button>
                    <a href="install_tournaments.php" target="_blank" class="bg-teal-600 text-white px-6 py-3 rounded-xl font-semibold hover:bg-teal-700">🔧 تثبيت الجداول</a>
                </div>
            </div>`;
        return;
    }

    const tournaments = r.data || [];
    const filtered = tournamentFilter === 'all' ? tournaments :
        tournaments.filter(t => t.status === tournamentFilter);

    mc.innerHTML = `
    <div class="fade-in">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">⚽ البطولات والدوريات</h2>
                <p class="text-gray-500">${tournaments.length} بطولة</p>
            </div>
            ${canEdit() ? `
            <button onclick="showTournamentForm()" class="bg-green-600 text-white px-5 py-2 rounded-xl font-semibold hover:bg-green-700 cursor-pointer">+ بطولة جديدة</button>
            ` : ''}
        </div>

        <!-- Filter Tabs -->
        <div class="flex flex-wrap gap-2 mb-4">
            ${['all', 'draft', 'in_progress', 'completed'].map(f => `
                <button onclick="tournamentFilter='${f}';renderTournaments()" 
                    class="px-4 py-2 rounded-xl font-semibold text-sm cursor-pointer ${tournamentFilter === f ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300'}">
                    ${f === 'all' ? '📋 الكل (' + tournaments.length + ')' :
            (STATUS_AR[f]?.icon || '') + ' ' + (STATUS_AR[f]?.text || f) + ' (' + tournaments.filter(t => t.status === f).length + ')'}
                </button>
            `).join('')}
        </div>

        <!-- Tournament Cards -->
        ${filtered.length > 0 ? `
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            ${filtered.map(t => renderTournamentCard(t)).join('')}
        </div>
        ` : `
        <div class="text-center py-16 bg-white rounded-2xl shadow-sm border border-gray-100">
            <p class="text-5xl mb-4">🏆</p>
            <p class="text-xl font-bold text-gray-500 mb-2">لا توجد بطولات${tournamentFilter !== 'all' ? ' بهذه الحالة' : ''}</p>
            ${canEdit() ? '<p class="text-gray-400">اضغط "بطولة جديدة" لإنشاء أول بطولة</p>' : ''}
        </div>
        `}
    </div>`;
}

function renderTournamentCard(t) {
    const status = STATUS_AR[t.status] || STATUS_AR.draft;
    const type = TOURNAMENT_TYPES_SHORT[t.type] || t.type;

    return `
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden card-hover cursor-pointer" onclick="openTournament(${t.id})">
        <div class="bg-gradient-to-l from-emerald-500 to-green-600 p-4 text-white">
            <div class="flex justify-between items-start">
                <div>
                    <h3 class="font-bold text-lg">${esc(t.name)}</h3>
                    <p class="opacity-80 text-sm">${type}</p>
                </div>
                <span class="badge ${status.color} text-xs">${status.icon} ${status.text}</span>
            </div>
        </div>
        <div class="p-4">
            <div class="grid grid-cols-3 gap-2 text-center mb-3">
                <div class="bg-emerald-50 rounded-lg p-2">
                    <p class="text-xl font-bold text-emerald-600">${t.team_count || 0}</p>
                    <p class="text-xs text-gray-500">فريق</p>
                </div>
                <div class="bg-green-50 rounded-lg p-2">
                    <p class="text-xl font-bold text-green-600">${t.match_count || 0}</p>
                    <p class="text-xs text-gray-500">مباراة</p>
                </div>
                <div class="bg-teal-50 rounded-lg p-2">
                    <p class="text-xl font-bold text-teal-600">${esc(t.sport_type || '⚽')}</p>
                    <p class="text-xs text-gray-500">الرياضة</p>
                </div>
            </div>
            ${t.start_date ? `<p class="text-xs text-gray-400">📅 ${t.start_date}${t.end_date ? ' → ' + t.end_date : ''}</p>` : ''}
        </div>
    </div>`;
}

// ============================================================
// TOURNAMENT DETAIL
// ============================================================
async function openTournament(id) {
    currentTournament = id;
    tournamentTab = 'teams';
    renderTournaments();
}

function closeTournament() {
    currentTournament = null;
    tournamentTab = 'teams';
    renderTournaments();
}

async function renderTournamentDetail(id) {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = showLoading();

    const r = await TAPI.get('tournament_get', { id });
    if (!r || !r.success) {
        mc.innerHTML = '<p class="text-red-500 text-center py-8">خطأ في تحميل البطولة</p>';
        return;
    }

    const t = r.data;
    const status = STATUS_AR[t.status] || STATUS_AR.draft;
    const type = TOURNAMENT_TYPES[t.type] || t.type;
    const teams = t.teams || [];
    const isElimination = t.type.includes('elimination') || t.type === 'mixed';
    const isLeague = t.type.includes('round_robin') || t.type === 'mixed';

    mc.innerHTML = `
    <div class="fade-in">
        <!-- Back Button -->
        <div class="mb-4">
            <button onclick="closeTournament()" class="text-green-600 hover:text-green-800 font-semibold cursor-pointer">→ العودة للبطولات</button>
        </div>

        <!-- Tournament Header -->
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">${esc(t.name)}</h2>
                    <p class="text-gray-500 mt-1">${type}</p>
                    ${t.description ? `<p class="text-gray-400 text-sm mt-1">${esc(t.description)}</p>` : ''}
                    <div class="flex flex-wrap gap-2 mt-3">
                        <span class="badge ${status.color}">${status.icon} ${status.text}</span>
                        <span class="badge bg-emerald-100 text-emerald-700">👥 ${teams.length} فريق</span>
                        <span class="badge bg-gray-100 text-gray-700">跑 ${esc(t.sport_type || 'كرة قدم')}</span>
                        ${t.start_date ? `<span class="badge bg-gray-100 text-gray-700">📅 ${t.start_date}</span>` : ''}
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    ${canEdit() && t.status === 'draft' ? `
                        <button onclick="showTournamentForm(${t.id})" class="bg-emerald-100 text-emerald-700 px-4 py-2 rounded-xl font-semibold text-sm hover:bg-emerald-200 cursor-pointer">✏️ تعديل</button>
                    ` : ''}
                    ${canEdit() && (t.status === 'draft' || t.status === 'registration') && teams.length >= 2 ? `
                        <button onclick="startTournamentAction(${t.id})" class="bg-emerald-600 text-white px-4 py-2 rounded-xl font-semibold text-sm hover:bg-emerald-700 cursor-pointer">🚀 بدء البطولة</button>
                    ` : ''}
                    ${canEdit() && t.status === 'in_progress' ? `
                        <button onclick="completeTournamentAction(${t.id})" class="bg-emerald-600 text-white px-4 py-2 rounded-xl font-semibold text-sm hover:bg-emerald-700 cursor-pointer">🏁 إنهاء البطولة</button>
                    ` : ''}
                    ${t.status === 'in_progress' || t.status === 'completed' ? `
                        <button onclick="shareTournamentAction(${t.id})" class="bg-emerald-50 text-emerald-700 px-4 py-2 rounded-xl font-semibold text-sm hover:bg-emerald-100 border border-emerald-100 cursor-pointer">🔗 رابط الجمهور</button>
                    ` : ''}
                    ${t.status === 'completed' || t.status === 'in_progress' ? `
                        <a href="tournament_report.php?id=${t.id}" target="_blank" class="bg-emerald-600 text-white px-4 py-2 rounded-xl font-semibold text-sm hover:bg-emerald-700 cursor-pointer flex items-center gap-1">📁 تقرير الإنجاز</a>
                    ` : ''}
                    <button onclick="printTournament(${t.id})" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-xl font-semibold text-sm hover:bg-gray-200 cursor-pointer">🖨️ طباعة</button>
                    ${(canEdit() && t.status === 'draft') || (isAdmin() && t.status === 'completed') ? `
                        <button onclick="deleteTournamentAction(${t.id})" class="bg-red-50 text-red-600 px-4 py-2 rounded-xl font-semibold text-sm hover:bg-red-100 cursor-pointer">🗑️ حذف</button>
                    ` : ''}
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bg-white rounded-t-2xl border border-gray-100 border-b-0 flex overflow-x-auto">
            <button onclick="tournamentTab='teams';renderTournamentTab(${t.id})" class="tab-btn px-6 py-3 text-sm ${tournamentTab === 'teams' ? 'active' : ''} cursor-pointer whitespace-nowrap">👥 الفرق (${teams.length})</button>
            <button onclick="tournamentTab='matches';renderTournamentTab(${t.id})" class="tab-btn px-6 py-3 text-sm ${tournamentTab === 'matches' ? 'active' : ''} cursor-pointer whitespace-nowrap">⚔️ المباريات</button>
            ${isLeague ? `
            <button onclick="tournamentTab='standings';renderTournamentTab(${t.id})" class="tab-btn px-6 py-3 text-sm ${tournamentTab === 'standings' ? 'active' : ''} cursor-pointer whitespace-nowrap">📊 الترتيب</button>
            ` : ''}
            ${isElimination ? `
            <button onclick="tournamentTab='bracket';renderTournamentTab(${t.id})" class="tab-btn px-6 py-3 text-sm ${tournamentTab === 'bracket' ? 'active' : ''} cursor-pointer whitespace-nowrap">🌳 الشجرة</button>
            ` : ''}
            <button onclick="tournamentTab='awards';renderTournamentTab(${t.id})" class="tab-btn px-6 py-3 text-sm ${tournamentTab === 'awards' ? 'active' : ''} cursor-pointer whitespace-nowrap">⭐ الجوائز</button>
        </div>
        <div id="tournamentTabContent" class="bg-white rounded-b-2xl shadow-sm border border-gray-100 border-t-0 p-3 md:p-6 overflow-x-auto"></div>
    </div>`;

    renderTournamentTab(t.id);
}

// ============================================================
// SCHEDULING & FAIR PLAY TOOLS
// ============================================================

function showSchedulingForm(tournamentId) {
    const today = new Date().toISOString().split('T')[0];
    showModal(`
        <div class="p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4 text-center">📅 جدولة مواعيد البطولة</h3>
            <p class="text-xs text-gray-500 mb-6 text-center leading-relaxed">سيقوم النظام بتوزيع المباريات آلياً بطريقة عادلة تضمن عدم تكرار لعب الفريق في نفس اليوم، مع استبعاد أيام الجمعة والسبت.</p>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">تاريخ البدء</label>
                    <input type="date" id="sched_start_date" value="${today}" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">وقت أول مباراة</label>
                    <input type="time" id="sched_start_time" value="08:00" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">مباراة / يوم</label>
                    <input type="number" id="sched_matches_per_day" value="4" min="1" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">مدة المباراة (دقيقة)</label>
                    <input type="number" id="sched_duration" value="20" min="5" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none">
                </div>
            </div>

            <div class="flex gap-3">
                <button onclick="doScheduleMatches(${tournamentId})" class="flex-1 bg-emerald-600 text-white py-3 rounded-xl font-bold hover:bg-emerald-700 transition">تطبيق الجدولة الآلية</button>
                <button onclick="closeModal()" class="flex-1 bg-gray-100 py-3 rounded-xl font-bold">إلغاء</button>
            </div>
        </div>
    `);
}

async function doScheduleMatches(tournamentId) {
    const data = {
        tournament_id: tournamentId,
        start_date: document.getElementById('sched_start_date').value,
        start_time: document.getElementById('sched_start_time').value,
        matches_per_day: document.getElementById('sched_matches_per_day').value,
        match_duration: document.getElementById('sched_duration').value,
        break_between: 5
    };

    showToast('جاري الجدولة...', 'info');
    const r = await TAPI.post('matches_schedule', data);
    if (r?.success) {
        showToast('تمت الجدولة بنجاح ✅');
        closeModal();
        renderMatchesTab(tournamentId);
    }
}

function editMatchSchedule(matchId, date, time, notes) {
    showModal(`
        <div class="p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4">✏️ تعديل موعد المباراة</h3>
            
            <div class="space-y-4 mb-6">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">تاريخ المباراة</label>
                    <input type="date" id="edit_match_date" value="${date}" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">الوقت</label>
                    <input type="time" id="edit_match_time" value="${time}" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl outline-none">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">ملاحظات (مثل: إجازة، اعتذار...)</label>
                    <textarea id="edit_match_notes" rows="2" class="w-full p-3 bg-gray-50 border border-gray-200 rounded-xl outline-none" placeholder="اكتب أي ملاحظات هنا...">${notes}</textarea>
                </div>
            </div>

            <div class="flex gap-3">
                <button onclick="doUpdateMatchSchedule(${matchId})" class="flex-1 bg-green-600 text-white py-3 rounded-xl font-bold hover:bg-green-700 transition">حفظ التعديلات</button>
                <button onclick="closeModal()" class="flex-1 bg-gray-100 py-3 rounded-xl font-bold">إلغاء</button>
            </div>
        </div>
    `);
}

async function doUpdateMatchSchedule(matchId) {
    const data = {
        id: matchId,
        match_date: document.getElementById('edit_match_date').value,
        match_time: document.getElementById('edit_match_time').value,
        notes: document.getElementById('edit_match_notes').value
    };

    const r = await TAPI.post('match_update', data);
    if (r?.success) {
        const tid = document.querySelector('[onclick*="renderMatchesTab"]').getAttribute('onclick').match(/\d+/)[0];
        showToast('تم التعديل بنجاح');
        closeModal();
        renderMatchesTab(parseInt(tid));
    }
}

// ============================================================
// TAB RENDERING
// ============================================================
async function renderTournamentTab(id) {
    const tc = document.getElementById('tournamentTabContent');
    if (!tc) return;
    tc.innerHTML = showLoading();

    // Update tab styles
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.textContent.includes(
            tournamentTab === 'teams' ? 'الفرق' :
                tournamentTab === 'matches' ? 'المباريات' :
                    tournamentTab === 'standings' ? 'الترتيب' :
                        tournamentTab === 'awards' ? 'الجوائز' : 'الشجرة'
        )) {
            btn.classList.add('active');
        }
    });

    switch (tournamentTab) {
        case 'teams': await renderTeamsTab(id); break;
        case 'matches': await renderMatchesTab(id); break;
        case 'standings': await renderStandingsTab(id); break;
        case 'bracket': await renderBracketTab(id); break;
        case 'awards': await renderAwardsTab(id); break;
    }
}

// ============================================================
// TEAMS TAB
// ============================================================
async function renderTeamsTab(id) {
    const tc = document.getElementById('tournamentTabContent');
    const r = await TAPI.get('teams_list', { tournament_id: id });
    const teams = r?.data || [];

    // Get tournament status
    const tr = await TAPI.get('tournament_get', { id });
    const tournament = tr?.data;
    const isDraft = tournament && (tournament.status === 'draft' || tournament.status === 'registration');

    tc.innerHTML = `
    <div>
        <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
            <h4 class="font-bold text-gray-800">👥 الفرق المشاركة (${teams.length})</h4>
            ${canEdit() && isDraft ? `
            <div class="flex gap-2">
                <button onclick="showAddClassesModal(${id})" class="bg-emerald-600 text-white px-4 py-2 rounded-xl font-semibold text-sm hover:bg-emerald-700 cursor-pointer">🏫 إضافة فصول</button>
                <button onclick="showAddSportsTeamsModal(${id})" class="bg-teal-600 text-white px-4 py-2 rounded-xl font-semibold text-sm hover:bg-teal-700 cursor-pointer">🏅 إضافة من الفرق</button>
                <button onclick="showAddTeamForm(${id})" class="bg-emerald-600 text-white px-4 py-2 rounded-xl font-semibold text-sm hover:bg-emerald-700 cursor-pointer">+ فريق يدوي</button>
            </div>
            ` : ''}
        </div>

        ${teams.length > 0 ? `
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            ${teams.map((team, i) => `
            <div class="bg-gray-50 rounded-xl p-4 border border-gray-200 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-lg" 
                         style="background-color: ${team.team_color || '#10b981'}">
                        ${i + 1}
                    </div>
                    <div>
                        <p class="font-bold text-gray-800">${esc(team.team_name)}</p>
                        ${team.full_class_name ? `<p class="text-xs text-gray-500">${esc(team.full_class_name)}</p>` : ''}
                        <button onclick="showTeamMembersModal(${team.id}, '${esc(team.team_name)}')" class="text-emerald-600 text-[10px] font-bold hover:underline cursor-pointer">👥 أعضاء الفريق</button>
                        ${team.is_eliminated == 1 ? '<span class="badge bg-red-100 text-red-700 text-xs">مُقصى</span>' : ''}
                    </div>
                </div>
                ${canEdit() && isDraft ? `
                <button onclick="removeTeam(${team.id}, ${id})" class="text-red-500 hover:text-red-700 cursor-pointer text-lg" title="حذف">🗑️</button>
                ` : ''}
            </div>
            `).join('')}
        </div>
        ` : `
        <div class="text-center py-12 text-gray-400">
            <p class="text-4xl mb-2">👥</p>
            <p>لا توجد فرق مسجلة</p>
            ${canEdit() ? '<p class="text-sm mt-1">أضف فصول أو فرق يدوية</p>' : ''}
        </div>
        `}
    </div>`;
}

// ============================================================
// MATCHES TAB
// ============================================================
async function renderMatchesTab(id) {
    const tc = document.getElementById('tournamentTabContent');

    const [mr, tr] = await Promise.all([
        TAPI.get('matches_list', { tournament_id: id }),
        TAPI.get('tournament_get', { id })
    ]);

    const matches = mr?.data || [];
    const tournament = tr?.data;
    const isDraft = tournament && (tournament.status === 'draft' || tournament.status === 'registration');
    const isInProgress = tournament && tournament.status === 'in_progress';

    // Group matches by round
    const rounds = {};
    matches.forEach(m => {
        const round = m.round_number || 1;
        if (!rounds[round]) rounds[round] = [];
        rounds[round].push(m);
    });

    const roundNumbers = Object.keys(rounds).sort((a, b) => a - b);
    const isMixed = tournament?.type === 'mixed';
    // For mixed: separate group rounds from knockout rounds
    const groupRounds = isMixed ? roundNumbers.filter(r => rounds[r].some(m => m.group_name)) : [];
    const knockoutRounds = isMixed ? roundNumbers.filter(r => rounds[r].every(m => !m.group_name)) : roundNumbers;

    tc.innerHTML = `
    <div>
        <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
            <h4 class="font-bold text-gray-800">⚔️ المباريات (${matches.filter(m => !m.is_bye).length})</h4>
            <div class="flex gap-2">
                ${canEdit() && isDraft && matches.length > 0 ? `
                <button onclick="showSchedulingForm(${id})" class="bg-emerald-600 text-white px-4 py-2 rounded-xl font-semibold text-sm hover:bg-emerald-700 cursor-pointer">📅 جدولة المواعيد</button>
                ` : ''}
                ${canEdit() && isDraft ? `
                <button onclick="generateMatchesAction(${id})" class="bg-teal-600 text-white px-4 py-2 rounded-xl font-semibold text-sm hover:bg-teal-700 cursor-pointer">⚡ توليد المباريات</button>
                ` : ''}
            </div>
        </div>

        ${roundNumbers.length > 0 ? roundNumbers.map(round => {
        const roundMatches = rounds[round].filter(m => !m.is_bye || m.team1_name || m.team2_name);
        if (roundMatches.length === 0) return '';

        const isGroupRound = isMixed && rounds[round].some(m => m.group_name);
        const roundName = isGroupRound
            ? `🟢 الجولة ${round} (دور المجموعات)`
            : getRoundName(parseInt(round), knockoutRounds.length > 0 ? knockoutRounds : roundNumbers, tournament?.type, roundMatches.filter(m => !m.is_bye).length);

        return `
            <div class="mb-6">
                <h5 class="font-bold text-emerald-700 mb-3 text-lg">${roundName}</h5>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    ${roundMatches.map(m => renderMatchCard(m, isInProgress)).join('')}
                </div>
            </div>`;
    }).join('') : `
        <div class="text-center py-12 text-gray-400">
            <p class="text-4xl mb-2">⚔️</p>
            <p>لا توجد مباريات</p>
            ${canEdit() ? '<p class="text-sm mt-1">اضغط "توليد المباريات" لإنشائها تلقائياً</p>' : ''}
        </div>
        `}
    </div>`;
}

function renderMatchCard(m, isInProgress) {
    const isCompleted = m.status === 'completed';
    const isBye = m.is_bye == 1;
    const team1Name = m.team1_name || 'يُحدد لاحقاً';
    const team2Name = m.team2_name || 'يُحدد لاحقاً';
    const canEnterResult = isInProgress && m.team1_id && m.team2_id && !isCompleted && !isBye;

    if (isBye && !m.team1_name && !m.team2_name) return '';

    return `
    <div class="border-2 ${isCompleted ? 'border-green-300 bg-green-50/30' : 'border-gray-200'} rounded-xl p-4 transition">
        <div class="flex items-center justify-between mb-2">
            <div>
                <span class="text-xs ${MATCH_STATUS[m.status]?.color || 'text-gray-400'} font-semibold">
                    ${isBye ? '🎫 تأهل مباشر' : MATCH_STATUS[m.status]?.text || m.status}
                </span>
                ${m.group_name ? `<span class="badge bg-emerald-50 text-emerald-700 text-[10px] ml-2">المجموعة ${m.group_name}</span>` : ''}
                ${m.match_date ? `<span class="text-[10px] text-emerald-600 font-bold ml-2">📅 ${m.match_date} ${m.match_time || ''}</span>` : ''}
            </div>
            <div class="flex gap-2">
                ${canEdit() && !isBye ? `<button onclick="editMatchSchedule(${m.id}, '${m.match_date || ''}', '${m.match_time || ''}', '${esc(m.notes || '')}')" class="text-gray-400 hover:text-emerald-600 text-xs text-xs underline cursor-pointer">تعديل الموعد</button>` : ''}
                <span class="text-xs text-gray-400">مباراة #${m.match_number}</span>
            </div>
        </div>
        ${m.notes ? `<div class="bg-yellow-50 text-[10px] p-1.5 rounded mb-2 border border-yellow-100">📝 ${esc(m.notes)}</div>` : ''}
        
        <!-- Team 1 -->
        <div class="flex items-center justify-between py-2 ${isCompleted && m.winner_team_id == m.team1_id ? 'font-bold text-green-700' : isCompleted && m.winner_team_id && m.winner_team_id != m.team1_id ? 'opacity-50' : ''}">
            <div class="flex items-center gap-2">
                ${m.team1_color ? `<div class="w-4 h-4 rounded-full" style="background:${m.team1_color}"></div>` : ''}
                <span class="${m.team1_id ? '' : 'text-gray-400 italic'}">${esc(team1Name)}</span>
                ${isCompleted && m.winner_team_id == m.team1_id ? ' ✓' : ''}
            </div>
            <span class="text-lg font-bold">${m.team1_score !== null ? m.team1_score : '-'}</span>
        </div>
        
        <div class="border-t border-gray-200 my-1"></div>
        
        <!-- Team 2 -->
        <div class="flex items-center justify-between py-2 ${isCompleted && m.winner_team_id == m.team2_id ? 'font-bold text-green-700' : isCompleted && m.winner_team_id && m.winner_team_id != m.team2_id ? 'opacity-50' : ''}">
            <div class="flex items-center gap-2">
                ${m.team2_color ? `<div class="w-4 h-4 rounded-full" style="background:${m.team2_color}"></div>` : ''}
                <span class="${m.team2_id ? '' : 'text-gray-400 italic'}">${esc(team2Name)}</span>
                ${isCompleted && m.winner_team_id == m.team2_id ? ' ✓' : ''}
            </div>
            <span class="text-lg font-bold">${m.team2_score !== null ? m.team2_score : '-'}</span>
        </div>

        ${canEnterResult && canEdit() ? `
        <button onclick="showMatchResultForm(${m.id}, '${esc(team1Name)}', '${esc(team2Name)}', ${m.team1_id}, ${m.team2_id})" 
                class="w-full mt-3 bg-emerald-600 text-white py-2 rounded-lg font-semibold text-sm hover:bg-emerald-700 cursor-pointer">
            📝 إدخال النتيجة
        </button>
        ` : ''}

        ${(isCompleted || isInProgress) && canEdit() && !isBye ? `
            <button onclick="showMatchMediaManager(${m.id}, '${esc(team1Name)}', '${esc(team2Name)}')" 
                    class="w-full mt-2 bg-emerald-50 text-emerald-600 border border-emerald-200 py-1.5 rounded-lg font-bold text-xs hover:bg-emerald-100 cursor-pointer transition-colors">
                📸 الوسائط الإعلامية
            </button>
        ` : ''}
    </div>`;
}

// ============================================================
// STANDINGS TAB (League)
// ============================================================
async function renderStandingsTab(id) {
    const tc = document.getElementById('tournamentTabContent');
    const r = await TAPI.get('standings_get', { tournament_id: id });
    const standings = r?.data || [];

    // Group standings by group_name
    const groups = {};
    standings.forEach(s => {
        const gn = s.group_name || 'الكل';
        if (!groups[gn]) groups[gn] = [];
        groups[gn].push(s);
    });

    const groupNames = Object.keys(groups).sort();

    tc.innerHTML = `
    <div>
        <div class="flex justify-between items-center mb-4">
            <h4 class="font-bold text-gray-800">📊 جدول الترتيب</h4>
            ${canEdit() ? `
            <button onclick="recalculateStandingsAction(${id})" class="bg-gray-200 text-gray-700 px-3 py-1 rounded-lg text-sm font-semibold hover:bg-gray-300 cursor-pointer">🔄 إعادة حساب</button>
            ` : ''}
        </div>
        <p class="text-[10px] text-gray-400 mb-3 flex items-center gap-1 md:hidden">
            <span>↔️</span> اسحب يساراً ويميناً لمشاهدة كامل الجدول
        </p>

        ${groupNames.length > 0 ? groupNames.map(gn => `
        <div class="mb-8">
            ${gn !== 'الكل' ? `<h5 class="font-bold text-emerald-700 mb-3 bg-emerald-50 p-2 rounded-lg">المجموعة ${esc(gn)}</h5>` : ''}
            <div class="overflow-x-auto -mx-3 md:mx-0 rounded-xl">
                <div class="min-w-[600px]">  
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-3 py-3 text-center text-sm font-bold text-gray-600">#</th>
                            <th class="px-3 py-3 text-right text-sm font-bold text-gray-600">الفريق</th>
                            <th class="px-3 py-3 text-center text-sm font-bold text-gray-600">لعب</th>
                            <th class="px-3 py-3 text-center text-sm font-bold text-gray-600">فوز</th>
                            <th class="px-3 py-3 text-center text-sm font-bold text-gray-600">تعادل</th>
                            <th class="px-3 py-3 text-center text-sm font-bold text-gray-600">خسارة</th>
                            <th class="px-3 py-3 text-center text-sm font-bold text-gray-600">له</th>
                            <th class="px-3 py-3 text-center text-sm font-bold text-gray-600">عليه</th>
                            <th class="px-3 py-3 text-center text-sm font-bold text-gray-600">+/-</th>
                            <th class="px-3 py-3 text-center text-sm font-bold text-gray-600 bg-green-50">النقاط</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${groups[gn].map((s, i) => `
                        <tr class="border-t border-gray-100 hover:bg-gray-50 ${i < 2 && gn !== 'الكل' ? 'bg-green-50/30' : i === 0 ? 'bg-yellow-50' : ''}">
                            <td class="px-3 py-3 text-center font-bold ${i === 0 ? 'text-yellow-600' : 'text-gray-400'}">${i === 0 ? '🥇' : i === 1 ? '🥈' : i + 1}</td>
                            <td class="px-3 py-3 font-semibold">
                                <div class="flex items-center gap-2">
                                    <div class="w-4 h-4 rounded-full flex-shrink-0" style="background:${s.team_color || '#10b981'}"></div>
                                    ${esc(s.team_name)}
                                </div>
                            </td>
                            <td class="px-3 py-3 text-center">${s.played}</td>
                            <td class="px-3 py-3 text-center text-green-600 font-semibold">${s.wins}</td>
                            <td class="px-3 py-3 text-center text-gray-500">${s.draws}</td>
                            <td class="px-3 py-3 text-center text-red-500">${s.losses}</td>
                            <td class="px-3 py-3 text-center">${s.goals_for}</td>
                            <td class="px-3 py-3 text-center">${s.goals_against}</td>
                            <td class="px-3 py-3 text-center font-semibold ${s.goal_difference > 0 ? 'text-green-600' : s.goal_difference < 0 ? 'text-red-500' : ''}">${s.goal_difference > 0 ? '+' : ''}${s.goal_difference}</td>
                            <td class="px-3 py-3 text-center font-black text-lg text-green-700 bg-green-50">${s.points}</td>
                        </tr>
                        `).join('')}
                    </tbody>
                </table>
                </div>
            </div>
        </div>
        `).join('') : `
        <div class="text-center py-12 text-gray-400">
            <p class="text-4xl mb-2">📊</p>
            <p>لا توجد بيانات ترتيب - ابدأ البطولة أولاً</p>
        </div>
        `}
    </div>`;
}

// ============================================================
// BRACKET TAB (Elimination)
// ============================================================
async function renderBracketTab(id) {
    const tc = document.getElementById('tournamentTabContent');
    const r = await TAPI.get('bracket_get', { tournament_id: id });

    if (!r || !r.success) {
        tc.innerHTML = '<p class="text-gray-400 text-center py-8">لا توجد بيانات شجرة</p>';
        return;
    }

    const { tournament, bracket } = r.data;
    const isDouble = tournament.type === 'double_elimination';

    let html = `<div><h4 class="font-bold text-gray-800 mb-2">🌳 شجرة البطولة</h4>
    <p class="text-[10px] text-gray-400 mb-4 flex items-center gap-1 md:hidden">
        <span>↔️</span> اسحب يساراً ويميناً لاستعراض كامل الشجرة
    </p>`;

    // 1. شعبة الفائزين (Winners Bracket)
    const mainBracket = bracket.main || {};
    const mainRounds = Object.keys(mainBracket).sort((a, b) => a - b);
    if (mainRounds.length > 0) {
        if (isDouble) html += `<h5 class="font-bold text-emerald-600 mb-2 mt-4">🔹 شعبة الفائزين (Winners Bracket)</h5>`;
        html += renderBracketSection(mainBracket, mainRounds, tournament.type);
    }

    // 2. شعبة الخاسرين (Losers Bracket)
    const losersBracket = bracket.losers || {};
    const losersRounds = Object.keys(losersBracket).sort((a, b) => a - b);
    if (losersRounds.length > 0) {
        html += `<h5 class="font-bold text-teal-600 mb-2 mt-8">🔸 شعبة الخاسرين (Losers Bracket)</h5>`;
        html += renderBracketSection(losersBracket, losersRounds, tournament.type, true);
    }

    // 3. النهائي الكبير (Grand Final)
    const finalBracket = bracket.final || {};
    const finalRounds = Object.keys(finalBracket).sort((a, b) => a - b);
    if (finalRounds.length > 0) {
        html += `<h5 class="font-bold text-emerald-700 mb-2 mt-8">🏆 النهائي الكبير (Grand Final)</h5>`;
        html += renderBracketSection(finalBracket, finalRounds, tournament.type, false, true);
    }

    html += `
        <!-- Legend -->
        <div class="flex flex-wrap gap-4 mt-8 pt-4 border-t border-gray-200 text-sm text-gray-500">
            <span>🟢 فائز</span>
            <span>🔴 خاسر/مُقصى</span>
            <span>⬜ لم تُلعب بعد</span>
            <span>🎫 تأهل مباشر (BYE)</span>
        </div>
    </div>`;

    tc.innerHTML = html;
}

function renderBracketSection(bracketData, roundKeys, tournamentType, isLosers = false, isFinal = false) {
    const totalRounds = roundKeys.length;
    return `
    <div class="overflow-x-auto -mx-3 md:mx-0 pb-4">
        <div class="flex gap-8 min-w-max px-3 md:px-0" style="direction: ltr">
            ${roundKeys.map(round => {
        const matches = bracketData[round].filter(m =>
            !m.is_bye || m.team1_name || m.team2_name
        );
        if (matches.length === 0) return '';

        let roundName = '';
        if (isLosers) {
            roundName = `خاسرين ${round}`;
        } else if (isFinal) {
            roundName = roundKeys.length > 1 && round == roundKeys[0] ? '🏆 النهائي الكبير' : '🔄 مباراة الإعادة';
        } else {
            const mCount = matches.filter(m => !m.is_bye).length;
            roundName = getRoundName(parseInt(round), roundKeys, tournamentType, mCount);
        }

        return `
                <div class="bracket-round">
                    <div class="text-center mb-3">
                        <span class="badge ${isLosers ? 'bg-teal-50 text-teal-700' : isFinal ? 'bg-emerald-100 text-emerald-800' : 'bg-emerald-50 text-emerald-600'} text-sm">${roundName}</span>
                    </div>
                    ${matches.map(m => renderBracketMatch(m)).join('')}
                </div>`;
    }).join('')}
        </div>
    </div>`;
}

function renderBracketMatch(m) {
    const isCompleted = m.status === 'completed';
    const isBye = m.is_bye == 1;
    const team1 = m.team1_name || 'يُحدد لاحقاً';
    const team2 = m.team2_name || 'يُحدد لاحقاً';

    if (isBye && !m.team1_name && !m.team2_name) return '';

    return `
    <div class="bracket-match ${isCompleted ? 'completed' : ''} mb-4">
        <div class="bracket-team ${isCompleted && m.winner_team_id == m.team1_id ? 'winner' : isCompleted && m.team1_id && m.winner_team_id != m.team1_id ? 'loser' : ''}" 
             style="border-right: 3px solid ${m.team1_color || '#e5e7eb'}">
            <span class="text-sm ${m.team1_id ? 'font-semibold' : 'text-gray-400 italic'}">${esc(team1)}</span>
            <span class="font-bold ${isCompleted && m.winner_team_id == m.team1_id ? 'text-green-600' : ''}">${m.team1_score !== null ? m.team1_score : ''}</span>
        </div>
        <div class="border-t border-gray-200"></div>
        <div class="bracket-team ${isCompleted && m.winner_team_id == m.team2_id ? 'winner' : isCompleted && m.team2_id && m.winner_team_id != m.team2_id ? 'loser' : ''}"
             style="border-right: 3px solid ${m.team2_color || '#e5e7eb'}">
            <span class="text-sm ${m.team2_id ? 'font-semibold' : 'text-gray-400 italic'}">${isBye ? '🎫 تأهل مباشر' : esc(team2)}</span>
            <span class="font-bold ${isCompleted && m.winner_team_id == m.team2_id ? 'text-green-600' : ''}">${m.team2_score !== null ? m.team2_score : ''}</span>
        </div>
    </div>`;
}

// ============================================================
// HELPER: Round Names
// ============================================================
function getRoundName(round, knockoutRounds, type, matchCount) {
    if (type && type.includes('round_robin')) {
        return `الجولة ${round}`;
    }

    // knockoutRounds is array of round numbers in the knockout stage
    const krArray = Array.isArray(knockoutRounds) ? knockoutRounds.map(Number) : [];
    const roundNum = Number(round);
    const kIdx = krArray.indexOf(roundNum); // 0-based index in knockout stage
    const totalKnockout = krArray.length;

    if (totalKnockout === 0) return `الجولة ${round}`;

    const stepsFromEnd = totalKnockout - 1 - kIdx; // 0 = final, 1 = semi, 2 = QF etc.

    if (stepsFromEnd === 0) return '🏆 النهائي';
    if (stepsFromEnd === 1) return '🥇 نصف النهائي';
    if (stepsFromEnd === 2) return '🎯 ربع النهائي';

    // For earlier rounds, compute how many teams play in that round
    // Each match has 2 teams, so teams = matches * 2
    const teamsInRound = matchCount ? matchCount * 2 : Math.pow(2, stepsFromEnd + 1);
    if (teamsInRound >= 32) return `⚡ دور الـ 32`;
    if (teamsInRound >= 16) return `⚡ دور الـ 16`;
    if (teamsInRound >= 8) return `⚡ دور الـ 8`;
    return `الجولة ${round}`;
}

// ============================================================
// TOURNAMENT CRUD
// ============================================================
async function showTournamentForm(id = null) {
    let tournament = null;
    if (id) {
        const r = await TAPI.get('tournament_get', { id });
        tournament = r?.data;
    }

    showModal(`
        <div class="p-6">
            <h3 class="text-xl font-bold mb-4">${tournament ? 'تعديل' : 'إنشاء'} بطولة</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">اسم البطولة *</label>
                    <input type="text" id="tName" value="${tournament ? esc(tournament.name) : ''}" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none" placeholder="مثال: بطولة كأس المدرسة">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">نوع البطولة *</label>
                    <select id="tType" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                        ${Object.entries(TOURNAMENT_TYPES).map(([k, v]) =>
        `<option value="${k}" ${tournament?.type === k ? 'selected' : ''}>${v}</option>`
    ).join('')}
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">نوع الرياضة</label>
                    <input type="text" id="tSport" value="${tournament ? esc(tournament.sport_type || '') : 'كرة قدم'}" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none" placeholder="مثال: كرة قدم">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">الوصف</label>
                    <textarea id="tDesc" rows="2" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none" placeholder="وصف اختياري...">${tournament?.description || ''}</textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">تاريخ البدء</label>
                        <input type="date" id="tStart" value="${tournament?.start_date || ''}" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">تاريخ الانتهاء</label>
                        <input type="date" id="tEnd" value="${tournament?.end_date || ''}" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                    </div>
                </div>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="tRandomize" ${!tournament || tournament.randomize_teams == 1 ? 'checked' : ''} class="w-5 h-5 accent-green-600">
                        <span class="text-sm font-semibold text-gray-700">🔀 ترتيب عشوائي للفرق</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="tAutoGenerate" ${!tournament || tournament.auto_generate == 1 ? 'checked' : ''} class="w-5 h-5 accent-green-600">
                        <span class="text-sm font-semibold text-gray-700">⚡ توليد تلقائي للمباريات</span>
                    </label>
                </div>
                <div class="flex gap-3 pt-2">
                    <button onclick="saveTournament(${id || 'null'})" class="flex-1 bg-green-600 text-white py-3 rounded-xl font-bold hover:bg-green-700 cursor-pointer">حفظ</button>
                    <button onclick="closeModal()" class="flex-1 bg-gray-200 text-gray-700 py-3 rounded-xl font-bold hover:bg-gray-300 cursor-pointer">إلغاء</button>
                </div>
            </div>
        </div>
    `);
}

async function saveTournament(id) {
    const data = {
        name: document.getElementById('tName').value.trim(),
        type: document.getElementById('tType').value,
        sport_type: document.getElementById('tSport').value.trim(),
        description: document.getElementById('tDesc').value.trim(),
        start_date: document.getElementById('tStart').value || null,
        end_date: document.getElementById('tEnd').value || null,
        randomize_teams: document.getElementById('tRandomize').checked ? 1 : 0,
        auto_generate: document.getElementById('tAutoGenerate').checked ? 1 : 0
    };

    if (!data.name) { showToast('أدخل اسم البطولة', 'error'); return; }

    let r;
    if (id) {
        data.id = id;
        r = await TAPI.post('tournament_update', data);
    } else {
        r = await TAPI.post('tournament_create', data);
    }

    if (r && r.success) {
        closeModal();
        showToast(r.message);
        if (!id && r.data?.id) {
            currentTournament = r.data.id;
        }
        renderTournaments();
    } else {
        showToast(r?.error || 'خطأ في الحفظ', 'error');
    }
}

// ============================================================
// TOURNAMENT ACTIONS
// ============================================================
async function startTournamentAction(id) {
    if (!confirm('هل تريد بدء البطولة؟ سيتم توليد المباريات تلقائياً.')) return;

    const r = await TAPI.get('tournament_start', { id });
    if (r && r.success) {
        showToast('تم بدء البطولة! 🎉');
        tournamentTab = 'matches';
        renderTournamentDetail(id);
    } else {
        showToast(r?.error || 'خطأ', 'error');
    }
}

async function completeTournamentAction(id) {
    if (!confirm('هل تريد إنهاء البطولة؟')) return;

    const r = await TAPI.get('tournament_complete', { id });
    if (r && r.success) {
        showToast('تم إنهاء البطولة! 🏆');
        renderTournamentDetail(id);
    } else {
        showToast(r?.error || 'خطأ', 'error');
    }
}

async function deleteTournamentAction(id) {
    if (!confirm('هل تريد حذف البطولة نهائياً؟')) return;

    const r = await TAPI.get('tournament_delete', { id });
    if (r && r.success) {
        showToast('تم حذف البطولة');
        closeTournament();
    } else {
        showToast(r?.error || 'خطأ', 'error');
    }
}

async function generateMatchesAction(id) {
    if (!confirm('سيتم حذف المباريات الحالية وتوليد مباريات جديدة. متأكد؟')) return;

    const r = await TAPI.get('matches_generate', { tournament_id: id });
    if (r && r.success) {
        showToast('تم توليد المباريات بنجاح! ⚡');
        tournamentTab = 'matches';
        renderTournamentTab(id);
    } else {
        showToast(r?.error || 'خطأ في توليد المباريات', 'error');
    }
}

async function recalculateStandingsAction(id) {
    const r = await TAPI.get('standings_recalculate', { tournament_id: id });
    if (r && r.success) {
        showToast('تم إعادة حساب الترتيب');
        renderTournamentTab(id);
    } else {
        showToast(r?.error || 'خطأ', 'error');
    }
}

async function shareTournamentAction(id) {
    const r = await TAPI.get('tournament_share', { id });
    if (r && r.success) {
        const { url, name } = r.data;
        showModal(`
            <div class="p-6 text-center">
                <div class="text-4xl mb-4">📢</div>
                <h3 class="text-xl font-bold mb-2">رابط متابعة البطولة</h3>
                <p class="text-gray-500 text-sm mb-4">انسخ الرابط التالي وأرسله للطلاب وأولياء الأمور لمتابعة نتائج <b>${esc(name)}</b> مباشرة:</p>
                <div class="flex gap-2 bg-gray-100 p-3 rounded-xl border border-gray-200 mb-6">
                    <input type="text" readonly value="${url}" id="shareUrlInput" class="bg-transparent border-none w-full text-center text-sm font-mono focus:outline-none">
                    <button onclick="copyShareLink()" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-green-700 cursor-pointer">نسخ</button>
                </div>
                <button onclick="closeModal()" class="w-full bg-gray-200 text-gray-700 py-3 rounded-xl font-bold hover:bg-gray-300 cursor-pointer">إغلاق</button>
            </div>
        `);
    } else {
        showToast(r?.error || 'رابط المشاركة متاح فقط للبطولات التي بدأت', 'error');
    }
}

function copyShareLink() {
    const input = document.getElementById('shareUrlInput');
    input.select();
    document.execCommand('copy');
    showToast('تم نسخ الرابط! ✅');
}

// ============================================================
// TEAM MANAGEMENT
// ============================================================
async function showAddClassesModal(tournamentId) {
    const r = await TAPI.get('available_classes', { tournament_id: tournamentId });
    if (!r || !r.success) {
        showToast('خطأ في جلب الفصول', 'error');
        return;
    }

    const classes = r.data || [];
    if (classes.length === 0) {
        showToast('جميع الفصول مضافة بالفعل', 'info');
        return;
    }

    showModal(`
        <div class="p-6">
            <h3 class="text-xl font-bold mb-4">🏫 إضافة فريق من فصل</h3>
            <p class="text-gray-500 text-sm mb-4">اختر الفصل، ثم سيمكنك اختيار طلاب محددين منه:</p>
            <div class="space-y-3 max-h-80 overflow-y-auto mb-6">
                ${classes.map(c => `
                <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 flex justify-between items-center hover:bg-white hover:shadow-sm transition-all">
                    <div>
                        <span class="font-bold block text-gray-800">${esc(c.full_name || c.name)}</span>
                        <span class="text-xs text-gray-500">${c.student_count} طالب متوفر في هذا الفصل</span>
                    </div>
                    <button onclick="showClassStudentPicker(${tournamentId}, ${c.id}, '${esc(c.full_name || c.name)}')" 
                        class="bg-green-600 text-white px-4 py-2 rounded-lg text-xs font-bold hover:bg-green-700 cursor-pointer">
                        👥 اختيار الطلاب
                    </button>
                </div>
                `).join('')}
            </div>
            <div class="flex gap-3">
                <button onclick="closeModal()" class="flex-1 bg-gray-200 text-gray-700 py-3 rounded-xl font-bold hover:bg-gray-300 cursor-pointer">إغلاق</button>
            </div>
        </div>
    `);
}

async function showClassStudentPicker(tournamentId, classId, className) {
    const r = await TAPI.get('class_students', { class_id: classId });
    if (!r || !r.success) {
        showToast('خطأ في جلب الطلاب', 'error');
        return;
    }

    const students = r.data || [];

    showModal(`
        <div class="p-6">
            <h3 class="text-xl font-bold mb-2">👥 اختيار أعضاء الفريق</h3>
            <p class="text-gray-500 text-sm mb-4">الفصل: <span class="text-green-600 font-bold">${className}</span></p>
            
            <div class="mb-4">
                <label class="flex items-center gap-2 cursor-pointer p-2 bg-gray-100 rounded-lg">
                    <input type="checkbox" id="selectAllStudents" onchange="document.querySelectorAll('.student-check').forEach(c=>c.checked=this.checked)" class="w-5 h-5 accent-green-600">
                    <span class="text-sm font-bold">تحديد جميع طلاب الفصل</span>
                </label>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-60 overflow-y-auto mb-6">
                ${students.map(s => `
                <label class="flex items-center gap-3 p-3 border border-gray-100 rounded-xl cursor-pointer hover:bg-green-50">
                    <input type="checkbox" value="${s.id}" class="student-check w-5 h-5 accent-green-600">
                    <span class="text-sm">${esc(s.name)}</span>
                </label>
                `).join('')}
            </div>
            <div class="flex gap-3">
                <button onclick="addSelectedClassesWithStudents(${tournamentId}, ${classId})" class="flex-1 bg-green-600 text-white py-3 rounded-xl font-bold hover:bg-green-700 cursor-pointer">✅ حفظ الفريق للبطولة</button>
                <button onclick="showAddClassesModal(${tournamentId})" class="flex-1 bg-gray-200 text-gray-700 py-3 rounded-xl font-bold hover:bg-gray-300 cursor-pointer">رجوع</button>
            </div>
        </div>
    `);
}

async function addSelectedClassesWithStudents(tournamentId, classId) {
    const checked = document.querySelectorAll('.student-check:checked');
    if (checked.length === 0) {
        showToast('يجب اختيار طالب واحد على الأقل', 'error');
        return;
    }

    const studentIds = Array.from(checked).map(c => parseInt(c.value));
    const r = await TAPI.post('teams_add_classes', {
        tournament_id: tournamentId,
        class_ids: [classId],
        student_ids: studentIds
    });

    if (r && r.success) {
        closeModal();
        showToast('تم إضافة الفريق مع الطلاب المختارين بنجاح ✅');
        renderTournamentTab(tournamentId);
    } else {
        showToast(r?.error || 'خطأ في الإضافة', 'error');
    }
}

function showAddTeamForm(tournamentId) {
    const colors = ['#ef4444', '#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'];
    const randomColor = colors[Math.floor(Math.random() * colors.length)];

    showModal(`
        <div class="p-6">
            <h3 class="text-xl font-bold mb-4">+ إضافة فريق يدوي</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">اسم الفريق *</label>
                    <input type="text" id="teamName" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none" placeholder="مثال: فريق النمور">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">لون الفريق</label>
                    <div class="flex gap-2 flex-wrap">
                        ${colors.map(c => `
                            <button onclick="document.getElementById('teamColor').value='${c}';document.querySelectorAll('.color-btn').forEach(b=>b.classList.remove('ring-4'));this.classList.add('ring-4')"
                                class="color-btn w-10 h-10 rounded-full cursor-pointer border-2 border-white shadow ${c === randomColor ? 'ring-4 ring-offset-1' : ''}" 
                                style="background:${c}"></button>
                        `).join('')}
                    </div>
                    <input type="hidden" id="teamColor" value="${randomColor}">
                </div>
                <div class="flex gap-3 pt-2">
                    <button onclick="addManualTeam(${tournamentId})" class="flex-1 bg-green-600 text-white py-3 rounded-xl font-bold hover:bg-green-700 cursor-pointer">إضافة</button>
                    <button onclick="closeModal()" class="flex-1 bg-gray-200 text-gray-700 py-3 rounded-xl font-bold hover:bg-gray-300 cursor-pointer">إلغاء</button>
                </div>
            </div>
        </div>
    `);
}

async function addManualTeam(tournamentId) {
    const name = document.getElementById('teamName').value.trim();
    const color = document.getElementById('teamColor').value;

    if (!name) { showToast('أدخل اسم الفريق', 'error'); return; }

    const r = await TAPI.post('team_add', {
        tournament_id: tournamentId,
        team_name: name,
        team_color: color
    });

    if (r && r.success) {
        closeModal();
        showToast('تم إضافة الفريق');
        renderTournamentTab(tournamentId);
    } else {
        showToast(r?.error || 'خطأ', 'error');
    }
}

async function removeTeam(teamId, tournamentId) {
    if (!confirm('حذف الفريق من البطولة؟')) return;

    const r = await TAPI.get('team_remove', { id: teamId });
    if (r && r.success) {
        showToast('تم حذف الفريق');
        renderTournamentTab(tournamentId);
    } else {
        showToast(r?.error || 'خطأ', 'error');
    }
}

// ============================================================
// MATCH RESULT
// ============================================================
async function showMatchResultForm(matchId, team1Name, team2Name, team1Id, team2Id) {
    const [r1, r2] = await Promise.all([
        TAPI.get('team_members_get', { team_id: team1Id }),
        TAPI.get('team_members_get', { team_id: team2Id })
    ]);

    const members1 = r1?.data || [];
    const members2 = r2?.data || [];

    showModal(`
        <div class="p-6 max-w-3xl">
            <h3 class="text-xl font-bold mb-4 text-center">📝 إدخال نتيجة المباراة</h3>
            
            <div class="space-y-6">
                <!-- النتيجة الأساسية -->
                <div class="grid grid-cols-2 gap-8 bg-emerald-50 p-6 rounded-3xl border border-emerald-100">
                    <div class="text-center">
                        <p class="font-bold text-emerald-900 mb-3 truncate text-lg">${esc(team1Name)}</p>
                        <input type="number" id="score1" min="0" value="0"
                            class="w-full px-4 py-6 border-2 border-white rounded-2xl text-center text-5xl font-black bg-white shadow-sm focus:border-emerald-500 focus:outline-none transition-all">
                    </div>
                    <div class="text-center">
                        <p class="font-bold text-emerald-900 mb-3 truncate text-lg">${esc(team2Name)}</p>
                        <input type="number" id="score2" min="0" value="0"
                            class="w-full px-4 py-6 border-2 border-white rounded-2xl text-center text-5xl font-black bg-white shadow-sm focus:border-emerald-500 focus:outline-none transition-all">
                    </div>
                </div>

                <!-- الهدافون (مقسمين حسب الفريق) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- هدافو الفريق 1 -->
                    <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100">
                        <div class="flex justify-between items-center mb-3">
                            <h5 class="text-sm font-bold text-gray-700">⚽ هدافو ${esc(team1Name)}</h5>
                            <button onclick="addScorerRow('scorers1', 1)" class="text-[10px] bg-white text-emerald-600 px-2 py-1 rounded border border-gray-200 hover:bg-gray-50">+ هداف</button>
                        </div>
                        <div id="scorers1" class="space-y-2"></div>
                    </div>

                    <!-- هدافو الفريق 2 -->
                    <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100">
                        <div class="flex justify-between items-center mb-3">
                            <h5 class="text-sm font-bold text-gray-700">⚽ هدافو ${esc(team2Name)}</h5>
                            <button onclick="addScorerRow('scorers2', 2)" class="text-[10px] bg-white text-emerald-600 px-2 py-1 rounded border border-gray-200 hover:bg-gray-50">+ هداف</button>
                        </div>
                        <div id="scorers2" class="space-y-2"></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4">
                    <div class="bg-yellow-50 p-4 rounded-2xl border border-yellow-100">
                        <label class="block text-sm font-bold text-yellow-800 mb-2">🌟 أفضل لاعب في المباراة (Man of the Match)</label>
                        <select id="momId" class="w-full px-4 py-3 border-2 border-white bg-white rounded-xl focus:border-yellow-400 focus:outline-none">
                            <option value="">-- اختر النجم --</option>
                            <optgroup label="${esc(team1Name)}">
                                ${members1.map(p => `<option value="${p.id}" data-name="${esc(p.name)}" data-team="${team1Id}">${esc(p.name)}</option>`).join('')}
                            </optgroup>
                            <optgroup label="${esc(team2Name)}">
                                ${members2.map(p => `<option value="${p.id}" data-name="${esc(p.name)}" data-team="${team2Id}">${esc(p.name)}</option>`).join('')}
                            </optgroup>
                        </select>
                    </div>
                </div>

                <div class="flex gap-4 pt-2">
                    <button onclick="saveMatchResultWithStats(${matchId})" class="flex-[2] bg-emerald-600 text-white py-4 rounded-2xl font-bold text-lg hover:bg-emerald-700 shadow-lg shadow-emerald-100 transition-all cursor-pointer">💾 حفظ النتيجة ومزامنة الهدافين</button>
                    <button onclick="closeModal()" class="flex-1 bg-gray-100 text-gray-500 py-4 rounded-2xl font-bold hover:bg-gray-200 transition-all cursor-pointer">إلغاء</button>
                </div>
            </div>
        </div>
    `);

    // تخزين بيانات اللاعبين للوصول السريع
    window.matchData_T1Players = members1;
    window.matchData_T2Players = members2;
    window.matchData_T1Id = team1Id;
    window.matchData_T2Id = team2Id;

    // إضافة سطر مبدئي إذا كانت النتيجة > 0 (اختياري)
}

function addScorerRow(containerId, teamNum) {
    const container = document.getElementById(containerId);
    const players = teamNum === 1 ? window.matchData_T1Players : window.matchData_T2Players;
    const teamId = teamNum === 1 ? window.matchData_T1Id : window.matchData_T2Id;

    const div = document.createElement('div');
    div.className = 'scorer-entry flex gap-1 items-center bg-white p-1 rounded-lg border border-gray-100';
    div.innerHTML = `
        <select class="s-id flex-1 px-2 py-1 text-xs border-none focus:ring-0">
            <option value="">-- اللاعب --</option>
            ${players.map(p => `<option value="${p.id}" data-team="${teamId}">${esc(p.name)}</option>`).join('')}
        </select>
        <input type="number" class="s-goals w-12 px-1 py-1 text-xs border border-gray-100 rounded text-center font-bold" value="1" min="1">
        <button onclick="this.parentElement.remove()" class="text-red-300 hover:text-red-500 px-1">✕</button>
    `;
    container.appendChild(div);
}

async function saveMatchResultWithStats(matchId) {
    const score1 = parseInt(document.getElementById('score1').value) || 0;
    const score2 = parseInt(document.getElementById('score2').value) || 0;

    // تجميع الهدافين من المجموعتين المنفصلتين
    const scorers = [];
    const getScorersFrom = (containerId) => {
        let total = 0;
        document.querySelectorAll(`#${containerId} .scorer-entry`).forEach(row => {
            const sid = row.querySelector('.s-id').value;
            const goals = parseInt(row.querySelector('.s-goals').value) || 0;
            const tid = row.querySelector('.s-id').options[row.querySelector('.s-id').selectedIndex].dataset.team;
            if (sid && goals > 0) {
                scorers.push({ student_id: sid, goals, team_id: tid });
                total += goals;
            }
        });
        return total;
    };

    const t1ScorerGoals = getScorersFrom('scorers1');
    const t2ScorerGoals = getScorersFrom('scorers2');

    // التحقق المنطقي: لا يمكن للهدافين تجاوز النتيجة المسجلة للفريق
    // (الأهداف المسجلة لابد أن تكون أقل من أو تساوي النتيجة، والفرق يعتبر أهدافاً عكسية لا تحتسب للاعب)
    if (t1ScorerGoals > score1) {
        showToast('خطأ: مجموع أهداف لاعبي الفريق الأول أكثر من أهداف الفريق المسجلة', 'error');
        return;
    }
    if (t2ScorerGoals > score2) {
        showToast('خطأ: مجموع أهداف لاعبي الفريق الثاني أكثر من أهداف الفريق المسجلة', 'error');
        return;
    }

    const momSelect = document.getElementById('momId');
    const momId = momSelect.value;
    const momName = momId ? momSelect.options[momSelect.selectedIndex].dataset.name : '';
    const momTeamId = momId ? momSelect.options[momSelect.selectedIndex].dataset.team : null;

    // 1. حفظ النتيجة الأساسية
    const r = await TAPI.post('match_result', {
        match_id: matchId,
        team1_score: score1,
        team2_score: score2,
        scorers: scorers
    });

    if (r && r.success) {
        // 2. حفظ أفضل لاعب إذا تم اختياره
        if (momId) {
            await TAPI.post('man_of_match_set', {
                match_id: matchId,
                student_id: momId,
                student_name: momName,
                team_id: momTeamId
            });
        }

        closeModal();
        showToast('تم حفظ النتيجة وتحديث قائمة المتميزين ✅');
        renderTournamentTab(currentTournament);
    } else {
        showToast(r?.error || 'خطأ في حفظ النتيجة', 'error');
    }
}

// ============================================================
// PRINT / PDF
// ============================================================
async function printTournament(id) {
    const r = await TAPI.get('tournament_print', { id });
    if (!r || !r.success) {
        showToast('خطأ في تحميل بيانات الطباعة', 'error');
        return;
    }

    const { tournament, matches, standings } = r.data;
    const type = TOURNAMENT_TYPES_SHORT[tournament.type] || tournament.type;
    const status = STATUS_AR[tournament.status] || {};
    const teams = tournament.teams || [];
    const isLeague = tournament.type.includes('round_robin');

    // Group matches by round
    const rounds = {};
    (matches || []).forEach(m => {
        const round = m.round_number || 1;
        if (!rounds[round]) rounds[round] = [];
        rounds[round].push(m);
    });
    const roundNumbers = Object.keys(rounds).sort((a, b) => a - b);
    const totalRounds = roundNumbers.length;

    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title>طباعة: ${esc(tournament.name)}</title>
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap');
            * { font-family: 'Cairo', sans-serif; margin: 0; padding: 0; box-sizing: border-box; }
            body { padding: 20px; color: #1f2937; font-size: 12px; }
            h1 { font-size: 22px; text-align: center; margin-bottom: 5px; }
            h2 { font-size: 16px; margin: 20px 0 10px; color: #4f46e5; border-bottom: 2px solid #e5e7eb; padding-bottom: 5px; }
            .subtitle { text-align: center; color: #6b7280; margin-bottom: 20px; }
            .info { display: flex; justify-content: center; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
            .info-item { background: #f3f4f6; padding: 5px 15px; border-radius: 8px; font-weight: 600; }
            table { width: 100%; border-collapse: collapse; margin: 10px 0; }
            th, td { border: 1px solid #d1d5db; padding: 6px 10px; text-align: center; }
            th { background: #f3f4f6; font-weight: 700; }
            tr:nth-child(even) { background: #f9fafb; }
            .winner { font-weight: 700; color: #059669; }
            .team-color { display: inline-block; width: 12px; height: 12px; border-radius: 50%; margin-left: 5px; }
            .footer { text-align: center; margin-top: 30px; padding-top: 10px; border-top: 1px solid #e5e7eb; color: #9ca3af; font-size: 10px; }
            @media print { body { padding: 10px; } @page { size: A4 landscape; margin: 1cm; } }
        </style>
    </head>
    <body>
        <h1>🏆 ${esc(tournament.name)}</h1>
        <p class="subtitle">${type} | ${esc(tournament.sport_type || 'كرة قدم')} | ${status.text || tournament.status}</p>
        
        <div class="info">
            <span class="info-item">👥 ${teams.length} فريق</span>
            <span class="info-item">⚔️ ${(matches || []).filter(m => !m.is_bye).length} مباراة</span>
            ${tournament.start_date ? `<span class="info-item">📅 ${tournament.start_date}</span>` : ''}
        </div>

        <!-- Teams -->
        <h2>👥 الفرق المشاركة</h2>
        <table>
            <thead><tr><th>#</th><th>الفريق</th></tr></thead>
            <tbody>
                ${teams.map((t, i) => `<tr><td>${i + 1}</td><td><span class="team-color" style="background:${t.team_color || '#10b981'}"></span> ${esc(t.team_name)}</td></tr>`).join('')}
            </tbody>
        </table>

        ${isLeague && standings && standings.length > 0 ? `
        <!-- Standings -->
        <h2>📊 جدول الترتيب</h2>
        <table>
            <thead>
                <tr><th>#</th><th>الفريق</th><th>لعب</th><th>فوز</th><th>تعادل</th><th>خسارة</th><th>له</th><th>عليه</th><th>+/-</th><th>النقاط</th></tr>
            </thead>
            <tbody>
                ${standings.map((s, i) => `
                <tr>
                    <td>${i + 1}</td>
                    <td style="text-align:right"><span class="team-color" style="background:${s.team_color || '#10b981'}"></span> ${esc(s.team_name)}</td>
                    <td>${s.played}</td><td>${s.wins}</td><td>${s.draws}</td><td>${s.losses}</td>
                    <td>${s.goals_for}</td><td>${s.goals_against}</td><td>${s.goal_difference > 0 ? '+' : ''}${s.goal_difference}</td>
                    <td><strong>${s.points}</strong></td>
                </tr>`).join('')}
            </tbody>
        </table>
        ` : ''}

        <!-- Matches -->
        <h2>⚔️ جدول المباريات</h2>
        ${roundNumbers.map(round => {
        const roundMatches = rounds[round].filter(m => !m.is_bye || m.team1_name);
        if (roundMatches.length === 0) return '';
        return `
            <h3 style="margin:10px 0 5px;color:#6b7280;font-size:13px">${getRoundName(parseInt(round), totalRounds, tournament.type)}</h3>
            <table>
                <thead><tr><th>#</th><th>الفريق الأول</th><th>النتيجة</th><th>الفريق الثاني</th><th>الحالة</th></tr></thead>
                <tbody>
                    ${roundMatches.map(m => `
                    <tr>
                        <td>${m.match_number}</td>
                        <td class="${m.winner_team_id == m.team1_id ? 'winner' : ''}">${esc(m.team1_name || 'يُحدد')}</td>
                        <td>${m.team1_score !== null ? m.team1_score + ' - ' + m.team2_score : '- : -'}</td>
                        <td class="${m.winner_team_id == m.team2_id ? 'winner' : ''}">${esc(m.team2_name || 'يُحدد')}</td>
                        <td>${m.is_bye == 1 ? 'تأهل مباشر' : (MATCH_STATUS[m.status]?.text || m.status)}</td>
                    </tr>`).join('')}
                </tbody>
            </table>`;
    }).join('')}

        <div class="footer">
            PE Smart School System | ${new Date().toLocaleDateString('ar-SA')} | تم الإنشاء بواسطة النظام
        </div>

        <script>setTimeout(() => window.print(), 500);<\/script>
    </body>
    </html>`);
    printWindow.document.close();
}

// ============================================================
// ============================================================
// AWARDS & STARS TAB
// ============================================================
async function renderAwardsTab(id) {
    const tc = document.getElementById('tournamentTabContent');
    const r = await TAPI.get('top_scorers', { tournament_id: id }); // جلب الإحصائيات الحالية
    const stars = r?.data || [];

    tc.innerHTML = `
    <div>
        <div class="flex justify-between items-center mb-6">
            <h4 class="font-bold text-gray-800">⭐ نجوم وجوائز البطولة</h4>
            ${canEdit() ? `
            <button onclick="showAddAwardModal(${id})" class="bg-emerald-600 text-white px-4 py-2 rounded-xl font-semibold text-sm hover:bg-emerald-700 cursor-pointer">+ إضافة مسمى متميز</button>
            ` : ''}
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- قسم هدافي البطولة -->
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-4">
                <h5 class="font-bold text-gray-700 mb-4 flex items-center gap-2">🎯 قائمة الهدافين</h5>
                <div class="space-y-3">
                    ${stars.filter(s => s.goals > 0).map(s => `
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                            <div>
                                <p class="font-bold text-sm">${esc(s.student_name)}</p>
                                <p class="text-[10px] text-gray-500">${esc(s.team_name)}</p>
                            </div>
                            <span class="text-xl font-black text-green-600">${s.goals} ⚽</span>
                        </div>
                    `).join('') || '<p class="text-gray-400 text-center py-4">لا توجد أهداف مسجلة حتى الآن</p>'}
                </div>
            </div>

            <!-- قسم الجوائز المخصصة -->
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-4">
                <h5 class="font-bold text-gray-700 mb-4 flex items-center gap-2">🏆 الجوائز والمسميات</h5>
                <div class="space-y-3">
                    ${stars.filter(s => s.awards || s.man_of_match > 0).map(s => `
                        <div class="flex items-center justify-between p-3 bg-emerald-50 rounded-xl border border-emerald-100">
                            <div>
                                <p class="font-bold text-sm text-emerald-900">${esc(s.student_name)}</p>
                                <p class="text-[10px] text-emerald-600">${esc(s.team_name)}</p>
                            </div>
                            <div class="text-right">
                                ${s.awards ? `<span class="badge bg-emerald-600 text-white text-[10px] block mb-1">${esc(s.awards)}</span>` : ''}
                                ${s.man_of_match > 0 ? `<span class="badge bg-yellow-500 text-white text-[10px]">🌟 نجم المباراة (${s.man_of_match})</span>` : ''}
                            </div>
                        </div>
                    `).join('') || '<p class="text-gray-400 text-center py-4">لم يتم منح جوائز مخصصة بعد</p>'}
                </div>
            </div>
        </div>
    </div>`;
}

async function showAddAwardModal(tournamentId) {
    // جلب الطلاب المشاركين في البطولة
    const r = await TAPI.get('tournament_students', { tournament_id: tournamentId });
    const students = r?.data || [];

    showModal(`
        <div class="p-6">
            <h3 class="text-xl font-bold mb-4">🏆 منح مسمى متميز لطالب</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">اختر الطالب</label>
                    <select id="awardStudentId" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-indigo-500 focus:outline-none">
                        <option value="">-- اختر من القائمة --</option>
                        ${students.map(s => `<option value="${s.id}" data-team="${s.tournament_team_id}">${esc(s.name)} (${esc(s.team_name)})</option>`).join('')}
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">المسمى المتميز (اللقب)</label>
                    <input type="text" id="awardName" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-indigo-500 focus:outline-none" 
                        placeholder="مثال: أفضل حارس، اللاعب المثالي، الصخرة...">
                </div>
                <div class="flex gap-3 pt-2">
                    <button onclick="savePlayerAward(${tournamentId})" class="flex-1 bg-green-600 text-white py-3 rounded-xl font-bold hover:bg-green-700 cursor-pointer">منح الجائزة</button>
                    <button onclick="closeModal()" class="flex-1 bg-gray-200 text-gray-700 py-3 rounded-xl font-bold hover:bg-gray-300 cursor-pointer">إلغاء</button>
                </div>
            </div>
        </div>
    `);
}

async function savePlayerAward(tournamentId) {
    const studentSelect = document.getElementById('awardStudentId');
    const studentId = studentSelect.value;
    const selectedOption = studentSelect.options[studentSelect.selectedIndex];
    const teamId = selectedOption ? selectedOption.getAttribute('data-team') : null;
    const awardName = document.getElementById('awardName').value.trim();

    if (!studentId || !awardName || !teamId || teamId === 'undefined') {
        showToast('يرجى اختيار الطالب وكتابة المسمى', 'error');
        return;
    }

    const r = await TAPI.post('player_award_set', {
        tournament_id: tournamentId,
        student_id: studentId,
        team_id: teamId,
        award_name: awardName
    });

    if (r && r.success) {
        closeModal();
        showToast('تم منح الجائزة بنجاح 🌟');
        renderAwardsTab(tournamentId);
    } else {
        showToast(r?.error || 'خطأ في العملية', 'error');
    }
}

// ============================================================
// SPORTS TEAMS IMPORT
// ============================================================
async function showAddSportsTeamsModal(tournamentId) {
    const r = await TAPI.get('available_sports_teams', { tournament_id: tournamentId });
    if (!r || !r.success) {
        showToast('خطأ في جلب الفرق الرياضية', 'error');
        return;
    }

    const teams = r.data || [];
    if (teams.length === 0) {
        showToast('لا توجد فرق رياضية متاحة للإضافة', 'info');
        return;
    }

    showModal(`
        <div class="p-6">
            <h3 class="text-xl font-bold mb-4">🏅 إضافة من الفرق الرياضية</h3>
            <p class="text-gray-500 text-sm mb-4">اختر الفرق الرياضية التي قمت بإعدادها مسبقاً لإضافتها للبطولة:</p>
            <div class="space-y-2 max-h-60 overflow-y-auto mb-4">
                ${teams.map(t => `
                <label class="flex items-center gap-3 p-3 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100">
                    <input type="checkbox" value="${t.id}" class="s-team-check w-5 h-5 accent-emerald-600">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs" style="background:${t.color}">${t.logo_emoji || '⚽'}</div>
                    <div>
                        <span class="font-semibold block text-sm">${esc(t.name)}</span>
                        <span class="text-[10px] text-gray-400">${esc(t.sport_type)}</span>
                    </div>
                </label>
                `).join('')}
            </div>
            <div class="flex gap-3">
                <button onclick="addSelectedSportsTeams(${tournamentId})" class="flex-1 bg-emerald-600 text-white py-3 rounded-xl font-bold hover:bg-emerald-700 cursor-pointer">✅ إضافة المحدد</button>
                <button onclick="closeModal()" class="flex-1 bg-gray-200 text-gray-700 py-3 rounded-xl font-bold hover:bg-gray-300 cursor-pointer">إلغاء</button>
            </div>
        </div>
    `);
}

async function addSelectedSportsTeams(tournamentId) {
    const checked = document.querySelectorAll('.s-team-check:checked');
    if (checked.length === 0) {
        showToast('اختر فريقاً واحداً على الأقل', 'error');
        return;
    }

    const teamIds = Array.from(checked).map(c => parseInt(c.value));
    const r = await TAPI.post('teams_add_sports_teams', {
        tournament_id: tournamentId,
        team_ids: teamIds
    });

    if (r && r.success) {
        closeModal();
        showToast(r.message);
        renderTournamentTab(tournamentId);
    } else {
        showToast(r?.error || 'خطأ في الإضافة', 'error');
    }
}

console.log('✅ tournaments.js loaded');
async function showTeamMembersModal(teamId, teamName) {
    const r = await TAPI.get('team_members_get', { team_id: teamId });
    if (!r || !r.success) {
        showToast('خطأ في جلب الأعضاء', 'error');
        return;
    }

    const members = r.data || [];

    showModal(`
        <div class="p-6">
            <h3 class="text-xl font-bold mb-1">👥 أعضاء الفريق</h3>
            <p class="text-emerald-600 font-bold mb-4">${esc(teamName)}</p>
            
            <div class="space-y-2 max-h-80 overflow-y-auto mb-6">
                ${members.length > 0 ? members.map(m => `
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl border border-gray-100">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 bg-white border border-gray-200 rounded-full flex items-center justify-center font-bold text-gray-400 text-xs">
                            ${m.jersey_number || '•'}
                        </div>
                        <span class="font-semibold text-sm">${esc(m.name)}</span>
                    </div>
                    ${m.position ? `<span class="text-[10px] bg-gray-200 px-2 py-1 rounded text-gray-600">${esc(m.position)}</span>` : ''}
                </div>
                `).join('') : '<p class="text-center py-8 text-gray-400">لا يوجد أعضاء مسجلين في هذا الفريق</p>'}
            </div>
            
            <div class="flex gap-3">
                <button onclick="closeModal()" class="flex-1 bg-gray-200 text-gray-700 py-3 rounded-xl font-bold hover:bg-gray-300 cursor-pointer">إغلاق</button>
            </div>
        </div>
    `);
}

// ============================================================
// MEDIA MANAGER (System-Generated v2.0)
// ============================================================
async function showMatchMediaManager(matchId, t1, t2) {
    const r = await TAPI.get('match_media_list', { match_id: matchId });
    const media = r?.data || [];

    showModal(`
        <div class="p-6 max-w-2xl">
            <h3 class="text-xl font-bold mb-2">📸 الوسائط الإعلامية للمباراة</h3>
            <p class="text-xs text-gray-500 mb-6">${t1} ضد ${t2}</p>
            
            <!-- Upload Form -->
            <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100 mb-6">
                <h4 class="font-bold text-sm mb-3">➕ إضافة لقطة جديدة</h4>
                <div class="space-y-3">
                    <input type="file" id="mediaFile" accept="image/*,video/mp4" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-bold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100">
                    <input type="text" id="mediaDesc" placeholder="وصف اللقطة (مثلاً: هدف عالمي)" class="w-full px-4 py-2 border rounded-xl text-sm">
                    <button onclick="doUploadMedia(${matchId}, '${esc(t1)}', '${esc(t2)}')" class="bg-emerald-600 text-white px-6 py-2 rounded-xl font-bold text-sm">رفع الآن</button>
                </div>
            </div>

            <!-- Media List -->
            <div class="grid grid-cols-2 gap-4 max-h-80 overflow-y-auto">
                ${media.length > 0 ? media.map(m => `
                    <div class="relative bg-white border rounded-xl overflow-hidden group">
                        ${m.media_type === 'video' ?
            '<div class="h-32 bg-gray-900 flex items-center justify-center text-white">🎬 فيديو</div>' :
            `<img src="${m.media_url}" class="h-32 w-full object-cover">`
        }
                        <div class="p-2">
                            <p class="text-[10px] truncate">${esc(m.description || '')}</p>
                        </div>
                        <button onclick="deleteMedia(${m.id}, ${matchId}, '${esc(t1)}', '${esc(t2)}')" class="absolute top-2 right-2 bg-red-600/80 text-white w-6 h-6 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">✕</button>
                    </div>
                `).join('') : '<p class="col-span-2 text-center py-4 text-gray-400">لا يوجد وسائط بعد</p>'}
            </div>
            
            <div class="mt-8">
                <button onclick="closeModal()" class="w-full bg-gray-100 py-3 rounded-xl font-bold">إغلاق</button>
            </div>
        </div>
    `);
}

async function doUploadMedia(matchId, t1, t2) {
    const fileInput = document.getElementById('mediaFile');
    const desc = document.getElementById('mediaDesc').value;

    if (!fileInput.files[0]) {
        showToast('يرجى اختيار ملف أولاً', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('match_id', matchId);
    formData.append('description', desc);
    formData.append('media', fileInput.files[0]);

    showToast('جاري الرفع...', 'info');

    try {
        // نمرر الإجراء في الرابط لضمان التعرف عليه بواسطة getParam
        const response = await fetch('modules/tournaments/api.php?action=match_media_upload', {
            method: 'POST',
            body: formData
        });

        if (!response.ok) {
            const text = await response.text();
            let err = 'خطأ في الخادم';
            try {
                const j = JSON.parse(text);
                err = j.error || err;
            } catch (e) { }
            showToast(err, 'error');
            return;
        }

        const result = await response.json();
        if (result.success) {
            showToast('تم الرفع بنجاح ✅');
            showMatchMediaManager(matchId, t1, t2); // Refresh modal
        } else {
            showToast(result.error || 'فشل الرفع', 'error');
        }
    } catch (e) {
        showToast('خطأ في الاتصال بالخادم: تأكد من حجم الملف والإنترنت', 'error');
        console.error(e);
    }
}

async function deleteMedia(id, matchId, t1, t2) {
    if (!confirm('هل متأكد من حذف هذه اللقطة؟')) return;
    const r = await TAPI.post('match_media_delete', { id });
    if (r?.success) {
        showToast('تم الحذف بنجاح');
        showMatchMediaManager(matchId, t1, t2);
    }
}

