/**
 * Live Match Panel — لوحة تحكيم الميدان
 * ==========================================
 * Mobile-first full-screen match control panel
 * for teachers to record goals, cards, and man of match in real time.
 *
 * Depends on: TAPI (from tournaments.js)
 */

// ============================================================
// STATE
// ============================================================
let _lm = {
    matchId: null,
    match: null,
    events: [],
    team1Players: [],
    team2Players: [],
    liveScore: { team1: 0, team2: 0 },
    manOfMatch: null,
    timerInterval: null,
    startTime: null,
    elapsedSeconds: 0,
};

// ============================================================
// OPEN LIVE MATCH PANEL
// ============================================================
async function openLiveMatchPanel(matchId) {
    _lm.matchId = matchId;

    // Show Loading overlay
    document.body.insertAdjacentHTML('beforeend', `
        <div id="lmOverlay" class="fixed inset-0 z-[9999] bg-slate-900 flex flex-col overflow-hidden"
             style="overscroll-behavior:none; touch-action:none;">
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center text-white">
                    <div class="animate-spin text-5xl mb-4">⚽</div>
                    <p class="font-bold text-lg">جاري تحميل المباراة...</p>
                </div>
            </div>
        </div>
    `);

    try {
        const r = await TAPI.get('live_match_state', { match_id: matchId });
        if (!r?.data) throw new Error('فشل تحميل بيانات المباراة');
        _lm.match = r.data.match;
        _lm.events = r.data.events || [];
        _lm.team1Players = r.data.team1_players || [];
        _lm.team2Players = r.data.team2_players || [];
        _lm.liveScore = r.data.live_score || { team1: 0, team2: 0 };
        _lm.manOfMatch = r.data.man_of_match || null;

        _renderLiveMatchPanel();
    } catch (e) {
        document.getElementById('lmOverlay')?.remove();
        alert('❌ ' + (e.message || 'خطأ في تحميل المباراة'));
    }
}

// ============================================================
// RENDER FULL PANEL
// ============================================================
function _renderLiveMatchPanel() {
    const m = _lm.match;
    if (!m) return;

    const t1Color = m.team1_color || '#10b981';
    const t2Color = m.team2_color || '#3b82f6';
    const s1 = _lm.liveScore.team1;
    const s2 = _lm.liveScore.team2;

    const overlay = document.getElementById('lmOverlay');
    if (!overlay) return;

    overlay.innerHTML = `
    <!-- TOP BAR -->
    <div class="flex items-center justify-between px-4 py-3 bg-slate-800 border-b border-slate-700 flex-shrink-0">
        <button onclick="closeLiveMatchPanel()" class="text-slate-400 hover:text-white p-2 rounded-lg">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
        <div class="text-center">
            <p class="text-white font-black text-sm">${m.tournament_name || ''}</p>
            <p class="text-slate-400 text-xs">${m.sport_type || ''}</p>
        </div>
        <div id="lmTimer" class="bg-red-600 text-white text-xs font-black px-3 py-1 rounded-full cursor-pointer" onclick="toggleLiveTimer()">
            ⏱ ${_lm.startTime ? _formatTime(_lm.elapsedSeconds) : 'ابدأ'}
        </div>
    </div>

    <!-- SCOREBOARD -->
    <div class="flex items-stretch bg-slate-800 flex-shrink-0">
        <div class="flex-1 flex flex-col items-center justify-center py-5 px-2" style="background: ${t1Color}18;">
            <div class="w-10 h-10 rounded-full mb-2 border-2 border-white/30" style="background:${t1Color};"></div>
            <p class="text-white font-black text-center text-sm leading-tight">${m.team1_name || 'الفريق ١'}</p>
        </div>
        <div class="flex flex-col items-center justify-center px-6 bg-slate-900">
            <div class="flex items-center gap-4">
                <span id="lmScore1" class="text-5xl font-black text-white">${s1}</span>
                <span class="text-slate-500 text-2xl font-black">-</span>
                <span id="lmScore2" class="text-5xl font-black text-white">${s2}</span>
            </div>
            <span class="text-slate-400 text-xs mt-1 font-bold" id="lmMatchStatus">${_lmStatusLabel(m.status)}</span>
        </div>
        <div class="flex-1 flex flex-col items-center justify-center py-5 px-2" style="background: ${t2Color}18;">
            <div class="w-10 h-10 rounded-full mb-2 border-2 border-white/30" style="background:${t2Color};"></div>
            <p class="text-white font-black text-center text-sm leading-tight">${m.team2_name || 'الفريق ٢'}</p>
        </div>
    </div>

    <!-- ACTION BUTTONS -->
    <div class="grid grid-cols-2 gap-3 p-4 bg-slate-850 flex-shrink-0" style="background:#0f172a;">
        <!-- Team 1 Goal -->
        <button onclick="lmRecordEvent('goal', ${m.team1_id}, '${m.team1_name}', 1)"
            class="flex items-center gap-3 bg-emerald-600 hover:bg-emerald-500 active:scale-95 text-white rounded-2xl p-4 font-black transition-all shadow-lg shadow-emerald-900/50">
            <span class="text-2xl">⚽</span>
            <span class="text-sm leading-tight">هدف<br><span class="font-normal opacity-80 text-xs">${m.team1_name}</span></span>
        </button>
        <!-- Team 2 Goal -->
        <button onclick="lmRecordEvent('goal', ${m.team2_id}, '${m.team2_name}', 2)"
            class="flex items-center gap-3 bg-emerald-600 hover:bg-emerald-500 active:scale-95 text-white rounded-2xl p-4 font-black transition-all shadow-lg shadow-emerald-900/50">
            <span class="text-2xl">⚽</span>
            <span class="text-sm leading-tight">هدف<br><span class="font-normal opacity-80 text-xs">${m.team2_name}</span></span>
        </button>
        <!-- Team 1 Yellow Card -->
        <button onclick="lmRecordEvent('yellow_card', ${m.team1_id}, '${m.team1_name}', 1)"
            class="flex items-center gap-3 bg-amber-500 hover:bg-amber-400 active:scale-95 text-white rounded-2xl p-3 font-black transition-all">
            <span class="text-2xl">🟨</span>
            <span class="text-sm leading-tight">إنذار<br><span class="font-normal opacity-80 text-xs">${m.team1_name}</span></span>
        </button>
        <!-- Team 2 Yellow Card -->
        <button onclick="lmRecordEvent('yellow_card', ${m.team2_id}, '${m.team2_name}', 2)"
            class="flex items-center gap-3 bg-amber-500 hover:bg-amber-400 active:scale-95 text-white rounded-2xl p-3 font-black transition-all">
            <span class="text-2xl">🟨</span>
            <span class="text-sm leading-tight">إنذار<br><span class="font-normal opacity-80 text-xs">${m.team2_name}</span></span>
        </button>
        <!-- Team 1 Red Card -->
        <button onclick="lmRecordEvent('red_card', ${m.team1_id}, '${m.team1_name}', 1)"
            class="flex items-center gap-3 bg-red-700 hover:bg-red-600 active:scale-95 text-white rounded-2xl p-3 font-black transition-all">
            <span class="text-2xl">🟥</span>
            <span class="text-sm leading-tight">طرد<br><span class="font-normal opacity-80 text-xs">${m.team1_name}</span></span>
        </button>
        <!-- Team 2 Red Card -->
        <button onclick="lmRecordEvent('red_card', ${m.team2_id}, '${m.team2_name}', 2)"
            class="flex items-center gap-3 bg-red-700 hover:bg-red-600 active:scale-95 text-white rounded-2xl p-3 font-black transition-all">
            <span class="text-2xl">🟥</span>
            <span class="text-sm leading-tight">طرد<br><span class="font-normal opacity-80 text-xs">${m.team2_name}</span></span>
        </button>
        <!-- Man of Match -->
        <button onclick="lmPickManOfMatch()"
            class="flex items-center gap-3 bg-yellow-500 hover:bg-yellow-400 active:scale-95 text-slate-900 rounded-2xl p-3 font-black transition-all col-span-1">
            <span class="text-2xl">⭐</span>
            <span class="text-sm">نجم المباراة</span>
        </button>
        <!-- End Match -->
        <button onclick="lmEndMatch()"
            class="flex items-center gap-3 bg-slate-700 hover:bg-slate-600 active:scale-95 text-white rounded-2xl p-3 font-black transition-all col-span-1">
            <span class="text-2xl">🏁</span>
            <span class="text-sm">إنهاء المباراة</span>
        </button>
    </div>

    <!-- EVENTS LOG -->
    <div class="flex-1 overflow-y-auto" style="overscroll-behavior:contain;">
        <div class="px-4 pt-2 pb-2 border-b border-slate-700">
            <p class="text-slate-400 text-xs font-bold uppercase tracking-widest">سجل الأحداث</p>
        </div>
        <div id="lmEventsList" class="divide-y divide-slate-800">
            ${_renderLmEvents()}
        </div>
    </div>
    `;
}

function _lmStatusLabel(status) {
    const map = { scheduled: 'مجدولة', in_progress: '🔴 جارية', completed: '✅ منتهية', postponed: 'مؤجلة' };
    return map[status] || status;
}

function _renderLmEvents() {
    if (_lm.events.length === 0) {
        return `<div class="text-center py-8 text-slate-600">
            <p class="text-3xl mb-2">📋</p>
            <p class="text-sm font-bold">لا توجد أحداث بعد</p>
        </div>`;
    }
    return _lm.events.slice().reverse().map(ev => {
        const icon = { goal: '⚽', own_goal: '🙈', penalty: '🎯', yellow_card: '🟨', red_card: '🟥', substitution: '🔄', injury: '🚑', man_of_match: '🌟' }[ev.event_type] || '📌';
        const label = { goal: 'هدف', own_goal: 'هدف عكسي', penalty: 'ركلة جزاء', yellow_card: 'بطاقة صفراء', red_card: 'بطاقة حمراء', substitution: 'تبديل', injury: 'إصابة', man_of_match: 'نجم المباراة' }[ev.event_type] || ev.event_type;
        const minute = ev.minute ? `${ev.minute}'` : '';
        return `
        <div class="flex items-center gap-3 px-4 py-3">
            <span class="text-xl">${icon}</span>
            <div class="flex-1 min-w-0">
                <p class="text-white text-sm font-bold">${label} ${minute ? '<span class="text-slate-400 font-normal">' + minute + '</span>' : ''}</p>
                ${ev.student_name ? `<p class="text-slate-400 text-xs">${ev.student_name}</p>` : ''}
                <p class="text-slate-500 text-xs">${ev.team_name || ''}</p>
            </div>
            <button onclick="lmDeleteEvent(${ev.id})" class="text-slate-600 hover:text-red-400 p-1.5 rounded-lg transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </button>
        </div>`;
    }).join('');
}

// ============================================================
// RECORD EVENT — show player picker modal
// ============================================================
async function lmRecordEvent(type, teamId, teamName, teamNum) {
    const players = teamNum === 1 ? _lm.team1Players : _lm.team2Players;
    const minute = _lm.startTime ? Math.floor(_lm.elapsedSeconds / 60) : null;

    // For card/injury events we pick a player; for others optional
    const label = { goal: 'هدف', own_goal: 'هدف عكسي', penalty: 'ركلة جزاء', yellow_card: 'بطاقة صفراء', red_card: 'بطاقة حمراء', substitution: 'تبديل', injury: 'إصابة' }[type] || type;

    // Modal for player selection
    const modalHtml = `
    <div id="lmPlayerModal" class="fixed inset-0 z-[10000] flex items-end justify-center" style="background:rgba(0,0,0,0.7);">
        <div class="bg-slate-800 w-full max-w-lg rounded-t-3xl p-5 max-h-[70vh] flex flex-col">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-white font-black text-lg">${label} — ${teamName}</h3>
                <button onclick="document.getElementById('lmPlayerModal')?.remove()" class="text-slate-400 hover:text-white">✕</button>
            </div>
            <p class="text-slate-400 text-sm mb-3">اختر اللاعب (اختياري)</p>
            <div class="overflow-y-auto flex-1 space-y-2">
                <!-- Quick record without player -->
                <button onclick="_lmSubmitEvent('${type}', ${teamId}, null, ${minute})"
                    class="w-full flex items-center gap-3 bg-slate-700 hover:bg-slate-600 text-white rounded-2xl p-3 font-bold transition-all">
                    <span class="text-lg">📌</span> تسجيل بدون لاعب
                </button>
                ${players.map(p => `
                <button onclick="_lmSubmitEvent('${type}', ${teamId}, ${p.id}, ${minute})"
                    class="w-full flex items-center gap-3 bg-slate-700 hover:bg-emerald-700 text-white rounded-2xl p-3 font-bold transition-all">
                    <span class="w-7 h-7 bg-emerald-600 rounded-full flex items-center justify-center text-xs font-black">
                        ${p.name.charAt(0)}
                    </span>
                    <span class="text-sm">${p.name}</span>
                </button>`).join('')}
            </div>
        </div>
    </div>`;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

async function _lmSubmitEvent(type, teamId, studentId, minute) {
    document.getElementById('lmPlayerModal')?.remove();

    const btn = document.querySelector(`[onclick*="lmRecordEvent"]`);
    if (btn) btn.disabled = true;

    try {
        const r = await TAPI.post('live_match_event', {
            match_id: _lm.matchId,
            team_id: teamId,
            event_type: type,
            student_id: studentId,
            minute: minute,
        });

        if (r?.data?.live_score) {
            _lm.liveScore = r.data.live_score;
        }

        // Refresh state
        const stateR = await TAPI.get('live_match_state', { match_id: _lm.matchId });
        if (stateR?.data) {
            _lm.events = stateR.data.events || [];
            _lm.team1Players = stateR.data.team1_players || [];
            _lm.team2Players = stateR.data.team2_players || [];
            _lm.liveScore = stateR.data.live_score || _lm.liveScore;
            _lm.match = stateR.data.match;
        }

        _updateLmScoreboard();
        document.getElementById('lmEventsList').innerHTML = _renderLmEvents();

        // Haptic feedback
        if (navigator.vibrate) navigator.vibrate([50, 30, 50]);

    } catch (e) {
        alert('❌ ' + (e.message || 'فشل تسجيل الحدث'));
    } finally {
        if (btn) btn.disabled = false;
    }
}

function _updateLmScoreboard() {
    const el1 = document.getElementById('lmScore1');
    const el2 = document.getElementById('lmScore2');
    if (el1) { el1.textContent = _lm.liveScore.team1; el1.classList.add('scale-125'); setTimeout(() => el1.classList.remove('scale-125'), 300); }
    if (el2) { el2.textContent = _lm.liveScore.team2; el2.classList.add('scale-125'); setTimeout(() => el2.classList.remove('scale-125'), 300); }
}

// ============================================================
// DELETE EVENT
// ============================================================
async function lmDeleteEvent(eventId) {
    if (!confirm('حذف هذا الحدث؟')) return;
    try {
        const r = await TAPI.post('live_match_delete_event', { event_id: eventId });
        if (r?.data?.live_score) _lm.liveScore = r.data.live_score;

        const stateR = await TAPI.get('live_match_state', { match_id: _lm.matchId });
        if (stateR?.data) {
            _lm.events = stateR.data.events || [];
            _lm.liveScore = stateR.data.live_score || _lm.liveScore;
        }

        _updateLmScoreboard();
        document.getElementById('lmEventsList').innerHTML = _renderLmEvents();
    } catch (e) {
        alert('❌ ' + (e.message || 'فشل حذف الحدث'));
    }
}

// ============================================================
// MAN OF MATCH
// ============================================================
async function lmPickManOfMatch() {
    const allPlayers = [..._lm.team1Players, ..._lm.team2Players];
    if (allPlayers.length === 0) {
        alert('لا يوجد لاعبون مسجلون في هذه المباراة');
        return;
    }

    const modalHtml = `
    <div id="lmMomModal" class="fixed inset-0 z-[10000] flex items-end justify-center" style="background:rgba(0,0,0,0.7);">
        <div class="bg-slate-800 w-full max-w-lg rounded-t-3xl p-5 max-h-[70vh] flex flex-col">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-white font-black text-lg">⭐ اختر نجم المباراة</h3>
                <button onclick="document.getElementById('lmMomModal')?.remove()" class="text-slate-400 hover:text-white">✕</button>
            </div>
            <div class="overflow-y-auto flex-1 space-y-2">
                ${allPlayers.map(p => `
                <button onclick="_lmSetManOfMatch(${p.id}, '${p.name.replace(/'/g, "\\'")}')"
                    class="w-full flex items-center gap-3 bg-slate-700 hover:bg-yellow-600 text-white rounded-2xl p-3 font-bold transition-all">
                    <span class="text-lg">⭐</span>
                    <span class="text-sm">${p.name}</span>
                </button>`).join('')}
            </div>
        </div>
    </div>`;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

async function _lmSetManOfMatch(studentId, studentName) {
    document.getElementById('lmMomModal')?.remove();
    try {
        await TAPI.post('man_of_match_set', {
            match_id: _lm.matchId,
            student_id: studentId,
            student_name: studentName,
            tournament_id: _lm.match?.tournament_id,
        });
        _lm.manOfMatch = { student_name: studentName };

        // Refresh events to show MoM in list
        const stateR = await TAPI.get('live_match_state', { match_id: _lm.matchId });
        if (stateR?.data?.events) {
            _lm.events = stateR.data.events;
            document.getElementById('lmEventsList').innerHTML = _renderLmEvents();
        }

        _lmToast(`⭐ نجم المباراة: ${studentName}`);
    } catch (e) {
        alert('❌ ' + (e.message || 'فشل تعيين نجم المباراة'));
    }
}

// ============================================================
// END MATCH
// ============================================================
async function lmEndMatch() {
    const s1 = _lm.liveScore.team1;
    const s2 = _lm.liveScore.team2;
    if (!confirm(`إنهاء المباراة؟\n\nالنتيجة: ${_lm.match?.team1_name} ${s1} - ${s2} ${_lm.match?.team2_name}`)) return;

    try {
        _stopLiveTimer();
        const r = await TAPI.post('live_match_end', {
            match_id: _lm.matchId,
            team1_score: s1,
            team2_score: s2,
        });

        const statusEl = document.getElementById('lmMatchStatus');
        if (statusEl) statusEl.textContent = '✅ منتهية';
        _lmToast('🏁 انتهت المباراة بنجاح!');

        // Auto-close after 2s and refresh parent
        setTimeout(() => {
            closeLiveMatchPanel();
            if (typeof renderTournamentTab === 'function' && _lm.match?.tournament_id) {
                renderTournamentTab(_lm.match.tournament_id);
            }
        }, 2000);
    } catch (e) {
        alert('❌ ' + (e.message || 'فشل إنهاء المباراة'));
    }
}

// ============================================================
// TIMER
// ============================================================
function toggleLiveTimer() {
    if (_lm.timerInterval) {
        _stopLiveTimer();
    } else {
        _startLiveTimer();
    }
}

function _startLiveTimer() {
    if (_lm.timerInterval) return;
    if (!_lm.startTime) _lm.startTime = Date.now() - (_lm.elapsedSeconds * 1000);
    _lm.timerInterval = setInterval(() => {
        _lm.elapsedSeconds = Math.floor((Date.now() - _lm.startTime) / 1000);
        const timerEl = document.getElementById('lmTimer');
        if (timerEl) timerEl.textContent = '⏱ ' + _formatTime(_lm.elapsedSeconds);
    }, 1000);

    // Also start the match on server if needed
    if (_lm.match?.status === 'scheduled') {
        TAPI.post('live_match_start', { match_id: _lm.matchId }).catch(() => { });
    }
}

function _stopLiveTimer() {
    if (_lm.timerInterval) {
        clearInterval(_lm.timerInterval);
        _lm.timerInterval = null;
    }
}

function _formatTime(seconds) {
    const m = Math.floor(seconds / 60).toString().padStart(2, '0');
    const s = (seconds % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
}

// ============================================================
// CLOSE
// ============================================================
function closeLiveMatchPanel() {
    _stopLiveTimer();
    document.getElementById('lmOverlay')?.remove();
    // Reset state
    _lm = {
        matchId: null, match: null, events: [], team1Players: [], team2Players: [],
        liveScore: { team1: 0, team2: 0 }, manOfMatch: null,
        timerInterval: null, startTime: null, elapsedSeconds: 0
    };
}

// ============================================================
// TOAST HELPER
// ============================================================
function _lmToast(msg) {
    const toast = document.createElement('div');
    toast.className = 'fixed top-6 left-1/2 -translate-x-1/2 z-[10001] bg-emerald-600 text-white px-6 py-3 rounded-full font-black text-sm shadow-xl transition-all';
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

console.log('✅ live_match.js loaded');
