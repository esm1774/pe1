/**
 * PE Smart School System - Sports Calendar Module
 * ================================================
 * تقويم رياضي مدرسي: جدول سنوي، أحداث ونشاطات، تصدير Google Calendar
 */

// ── Constants ────────────────────────────────────────────────
const CAL_EVENT_TYPES = {
    match: { label: 'مباراة', icon: '⚽', color: '#10b981', bg: 'bg-green-100 text-green-700' },
    training: { label: 'تدريب', icon: '🏋️', color: '#3b82f6', bg: 'bg-blue-100 text-blue-700' },
    tournament: { label: 'بطولة', icon: '🏆', color: '#f59e0b', bg: 'bg-amber-100 text-amber-700' },
    fitness: { label: 'اختبار لياقة', icon: '💪', color: '#8b5cf6', bg: 'bg-purple-100 text-purple-700' },
    ceremony: { label: 'حفل/تكريم', icon: '🎉', color: '#ec4899', bg: 'bg-pink-100 text-pink-700' },
    meeting: { label: 'اجتماع', icon: '📋', color: '#6b7280', bg: 'bg-gray-100 text-gray-700' },
    holiday: { label: 'إجازة', icon: '🏖️', color: '#ef4444', bg: 'bg-red-100 text-red-700' },
    other: { label: 'أخرى', icon: '📌', color: '#14b8a6', bg: 'bg-teal-100 text-teal-700' }
};

const AR_MONTHS = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
const AR_DAYS = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
const AR_DAYS_SHORT = ['أحد', 'إثنين', 'ثلاثاء', 'أربعاء', 'خميس', 'جمعة', 'سبت'];

let calViewYear = new Date().getFullYear();
let calViewMonth = new Date().getMonth(); // 0-indexed
let calEvents = [];
let calViewMode = 'month'; // month, year, list

// ── Main Render ──────────────────────────────────────────────
async function renderSportsCalendar() {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = showLoading();

    await loadCalendarEvents();

    mc.innerHTML = `
    <div class="fade-in">
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h2 class="text-3xl md:text-4xl font-black text-gray-800 flex items-center gap-3">
                    <span class="w-14 h-14 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center text-3xl text-white shadow-xl">📅</span>
                    التقويم الرياضي المدرسي
                </h2>
                <p class="text-gray-400 font-bold text-sm mt-2 mr-[4.25rem]">جدول الأحداث والأنشطة الرياضية • ${calViewYear}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                ${canEdit() ? `
                <button onclick="showCalendarEventForm()" class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-3 rounded-2xl font-black shadow-xl shadow-indigo-200 hover:shadow-2xl hover:shadow-indigo-300 transition-all flex items-center gap-2 cursor-pointer active:scale-95">
                    <span>➕</span> إضافة حدث
                </button>` : ''}
                <button onclick="exportToGoogleCalendar()" class="bg-white text-gray-700 px-5 py-3 rounded-2xl font-black shadow-md border border-gray-100 hover:shadow-lg transition flex items-center gap-2 cursor-pointer active:scale-95">
                    <span>📤</span> تصدير Google Calendar
                </button>
            </div>
        </div>

        <!-- View Mode Tabs + Navigation -->
        <div class="bg-white rounded-2xl shadow-lg shadow-gray-100/50 border border-gray-50 p-3 mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex gap-1 bg-gray-100 rounded-xl p-1">
                <button onclick="calViewMode='month';renderCalendarContent()" class="px-4 py-2 rounded-lg text-sm font-black transition cursor-pointer ${calViewMode === 'month' ? 'bg-white text-indigo-600 shadow' : 'text-gray-500 hover:text-gray-700'}">📅 شهري</button>
                <button onclick="calViewMode='year';renderCalendarContent()" class="px-4 py-2 rounded-lg text-sm font-black transition cursor-pointer ${calViewMode === 'year' ? 'bg-white text-indigo-600 shadow' : 'text-gray-500 hover:text-gray-700'}">📊 سنوي</button>
                <button onclick="calViewMode='list';renderCalendarContent()" class="px-4 py-2 rounded-lg text-sm font-black transition cursor-pointer ${calViewMode === 'list' ? 'bg-white text-indigo-600 shadow' : 'text-gray-500 hover:text-gray-700'}">📋 قائمة</button>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="calNavigate(-1)" class="w-10 h-10 bg-gray-100 hover:bg-gray-200 rounded-xl flex items-center justify-center text-lg font-bold cursor-pointer transition active:scale-90">→</button>
                <span class="px-4 py-2 bg-indigo-50 text-indigo-700 rounded-xl font-black text-sm min-w-[140px] text-center" id="calNavLabel">${AR_MONTHS[calViewMonth]} ${calViewYear}</span>
                <button onclick="calNavigate(1)" class="w-10 h-10 bg-gray-100 hover:bg-gray-200 rounded-xl flex items-center justify-center text-lg font-bold cursor-pointer transition active:scale-90">←</button>
                <button onclick="calGoToday()" class="px-3 py-2 bg-indigo-600 text-white rounded-xl text-xs font-black cursor-pointer hover:bg-indigo-700 transition">اليوم</button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6" id="calStatsCards">
            ${renderCalStats()}
        </div>

        <!-- Calendar Content -->
        <div id="calendarContent" class="bg-white rounded-[2rem] shadow-xl shadow-gray-100/50 border border-gray-50 overflow-hidden">
            ${renderCalView()}
        </div>
    </div>`;
}

// ── Load Events ──────────────────────────────────────────────
async function loadCalendarEvents() {
    const r = await API.get('calendar_events', { year: calViewYear });
    if (r && r.success) {
        calEvents = r.data || [];
    }
}

// ── Navigation ───────────────────────────────────────────────
function calNavigate(dir) {
    if (calViewMode === 'year') {
        calViewYear += dir;
    } else {
        calViewMonth += dir;
        if (calViewMonth > 11) { calViewMonth = 0; calViewYear++; }
        if (calViewMonth < 0) { calViewMonth = 11; calViewYear--; }
    }
    loadCalendarEvents().then(() => renderCalendarContent());
}

function calGoToday() {
    calViewYear = new Date().getFullYear();
    calViewMonth = new Date().getMonth();
    loadCalendarEvents().then(() => renderCalendarContent());
}

function renderCalendarContent() {
    const label = document.getElementById('calNavLabel');
    if (label) {
        label.textContent = calViewMode === 'year' ? `${calViewYear}` : `${AR_MONTHS[calViewMonth]} ${calViewYear}`;
    }
    const stats = document.getElementById('calStatsCards');
    if (stats) stats.innerHTML = renderCalStats();
    const content = document.getElementById('calendarContent');
    if (content) content.innerHTML = renderCalView();
}

// ── Stats ────────────────────────────────────────────────────
function renderCalStats() {
    const yearEvents = calEvents.filter(e => new Date(e.event_date).getFullYear() === calViewYear);
    const monthEvents = yearEvents.filter(e => new Date(e.event_date).getMonth() === calViewMonth);
    const today = new Date().toISOString().split('T')[0];
    const upcoming = yearEvents.filter(e => e.event_date >= today);
    const types = [...new Set(yearEvents.map(e => e.event_type))];

    return `
        <div class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl p-4 text-white shadow-lg">
            <p class="text-3xl font-black">${yearEvents.length}</p>
            <p class="text-xs opacity-80 font-bold">أحداث هذا العام</p>
        </div>
        <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl p-4 text-white shadow-lg">
            <p class="text-3xl font-black">${monthEvents.length}</p>
            <p class="text-xs opacity-80 font-bold">أحداث الشهر</p>
        </div>
        <div class="bg-gradient-to-br from-amber-500 to-orange-600 rounded-2xl p-4 text-white shadow-lg">
            <p class="text-3xl font-black">${upcoming.length}</p>
            <p class="text-xs opacity-80 font-bold">أحداث قادمة</p>
        </div>
        <div class="bg-gradient-to-br from-pink-500 to-rose-600 rounded-2xl p-4 text-white shadow-lg">
            <p class="text-3xl font-black">${types.length}</p>
            <p class="text-xs opacity-80 font-bold">أنواع الأنشطة</p>
        </div>
    `;
}

// ── Render Calendar View ─────────────────────────────────────
function renderCalView() {
    if (calViewMode === 'year') return renderYearView();
    if (calViewMode === 'list') return renderListView();
    return renderMonthView();
}

// ── Month View (Grid) ────────────────────────────────────────
function renderMonthView() {
    const firstDay = new Date(calViewYear, calViewMonth, 1);
    const lastDay = new Date(calViewYear, calViewMonth + 1, 0);
    const startDow = firstDay.getDay(); // 0=Sun
    const daysInMonth = lastDay.getDate();
    const today = new Date();
    const todayStr = today.toISOString().split('T')[0];

    let html = `<div class="p-4">`;

    // Day headers
    html += `<div class="grid grid-cols-7 gap-1 mb-2">`;
    AR_DAYS_SHORT.forEach(d => {
        html += `<div class="text-center text-[10px] font-black text-gray-400 uppercase tracking-widest py-2">${d}</div>`;
    });
    html += `</div>`;

    // Calendar grid
    html += `<div class="grid grid-cols-7 gap-1">`;

    // Empty cells before month start
    for (let i = 0; i < startDow; i++) {
        html += `<div class="min-h-[80px] sm:min-h-[100px] bg-gray-50/50 rounded-xl"></div>`;
    }

    // Day cells
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${calViewYear}-${String(calViewMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const dayEvents = calEvents.filter(e => e.event_date === dateStr);
        const isToday = dateStr === todayStr;
        const isWeekend = (startDow + day - 1) % 7 === 5 || (startDow + day - 1) % 7 === 6;

        html += `
        <div class="min-h-[80px] sm:min-h-[100px] ${isToday ? 'bg-indigo-50 ring-2 ring-indigo-400' : isWeekend ? 'bg-gray-50' : 'bg-white'} rounded-xl p-1.5 sm:p-2 border border-gray-100 hover:border-indigo-200 transition cursor-pointer group relative" onclick="${canEdit() ? `showCalendarEventForm(null, '${dateStr}')` : ''}">
            <div class="flex justify-between items-start">
                <span class="text-xs sm:text-sm font-black ${isToday ? 'bg-indigo-600 text-white w-7 h-7 rounded-full flex items-center justify-center' : 'text-gray-600'}">${day}</span>
                ${dayEvents.length > 0 ? `<span class="w-2 h-2 bg-indigo-400 rounded-full"></span>` : ''}
            </div>
            <div class="mt-1 space-y-0.5 overflow-hidden max-h-[60px]">
                ${dayEvents.slice(0, 3).map(ev => {
            const t = CAL_EVENT_TYPES[ev.event_type] || CAL_EVENT_TYPES.other;
            return `<div class="text-[8px] sm:text-[10px] font-bold px-1.5 py-0.5 rounded-md truncate cursor-pointer hover:opacity-80" style="background: ${t.color}20; color: ${t.color}" onclick="event.stopPropagation();showEventDetail(${ev.id})" title="${esc(ev.title)}">${t.icon} ${esc(ev.title)}</div>`;
        }).join('')}
                ${dayEvents.length > 3 ? `<div class="text-[8px] text-gray-400 font-bold text-center">+${dayEvents.length - 3} أخرى</div>` : ''}
            </div>
        </div>`;
    }

    // Fill remaining cells
    const totalCells = startDow + daysInMonth;
    const remaining = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
    for (let i = 0; i < remaining; i++) {
        html += `<div class="min-h-[80px] sm:min-h-[100px] bg-gray-50/50 rounded-xl"></div>`;
    }

    html += `</div></div>`;
    return html;
}

// ── Year View (12 mini calendars) ────────────────────────────
function renderYearView() {
    let html = `<div class="p-4 sm:p-6"><div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">`;

    for (let m = 0; m < 12; m++) {
        const monthEvents = calEvents.filter(e => {
            const d = new Date(e.event_date);
            return d.getMonth() === m && d.getFullYear() === calViewYear;
        });

        const firstDay = new Date(calViewYear, m, 1);
        const daysInMonth = new Date(calViewYear, m + 1, 0).getDate();
        const startDow = firstDay.getDay();
        const isCurrentMonth = m === new Date().getMonth() && calViewYear === new Date().getFullYear();

        html += `
        <div class="border ${isCurrentMonth ? 'border-indigo-300 ring-2 ring-indigo-100' : 'border-gray-100'} rounded-2xl p-3 hover:shadow-md transition cursor-pointer" onclick="calViewMonth=${m};calViewMode='month';renderCalendarContent()">
            <div class="flex justify-between items-center mb-2">
                <span class="font-black ${isCurrentMonth ? 'text-indigo-600' : 'text-gray-700'} text-sm">${AR_MONTHS[m]}</span>
                ${monthEvents.length > 0 ? `<span class="text-[9px] bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded-full font-black">${monthEvents.length}</span>` : ''}
            </div>
            <div class="grid grid-cols-7 gap-px text-[8px]">
                ${AR_DAYS_SHORT.map(d => `<div class="text-center text-gray-400 font-bold">${d.charAt(0)}</div>`).join('')}
                ${Array(startDow).fill('<div></div>').join('')}
                ${Array.from({ length: daysInMonth }, (_, i) => {
            const day = i + 1;
            const dateStr = `${calViewYear}-${String(m + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const hasEvent = monthEvents.some(e => e.event_date === dateStr);
            const isToday = dateStr === new Date().toISOString().split('T')[0];
            return `<div class="text-center py-0.5 ${isToday ? 'bg-indigo-600 text-white rounded-full font-black' : hasEvent ? 'bg-indigo-100 text-indigo-700 rounded-full font-bold' : 'text-gray-500'}">${day}</div>`;
        }).join('')}
            </div>
        </div>`;
    }

    html += `</div></div>`;
    return html;
}

// ── List View ────────────────────────────────────────────────
function renderListView() {
    const monthEvents = calEvents.filter(e => {
        const d = new Date(e.event_date);
        return d.getMonth() === calViewMonth && d.getFullYear() === calViewYear;
    }).sort((a, b) => a.event_date.localeCompare(b.event_date));

    if (monthEvents.length === 0) {
        return `
        <div class="p-12 text-center">
            <div class="text-6xl mb-4">📭</div>
            <p class="text-xl font-black text-gray-800 mb-2">لا توجد أحداث في هذا الشهر</p>
            <p class="text-gray-400 font-bold text-sm">أضف أحداثاً ونشاطات رياضية جديدة للتقويم</p>
            ${canEdit() ? `<button onclick="showCalendarEventForm()" class="mt-4 bg-indigo-600 text-white px-6 py-3 rounded-2xl font-black cursor-pointer hover:bg-indigo-700 transition">➕ إضافة حدث</button>` : ''}
        </div>`;
    }

    let html = `<div class="divide-y divide-gray-50">`;
    monthEvents.forEach(ev => {
        const t = CAL_EVENT_TYPES[ev.event_type] || CAL_EVENT_TYPES.other;
        const d = new Date(ev.event_date);
        const dayName = AR_DAYS[d.getDay()];
        const isPast = ev.event_date < new Date().toISOString().split('T')[0];

        html += `
        <div class="flex items-center gap-4 p-4 sm:p-5 hover:bg-gray-50 transition cursor-pointer ${isPast ? 'opacity-60' : ''}" onclick="showEventDetail(${ev.id})">
            <div class="flex-shrink-0 w-14 h-14 sm:w-16 sm:h-16 rounded-2xl flex flex-col items-center justify-center text-white shadow-lg" style="background: ${t.color}">
                <span class="text-lg sm:text-xl">${t.icon}</span>
                <span class="text-[9px] font-black">${d.getDate()}</span>
            </div>
            <div class="flex-1 min-w-0">
                <h4 class="font-black text-gray-800 text-sm sm:text-base truncate">${esc(ev.title)}</h4>
                <div class="flex flex-wrap gap-2 mt-1">
                    <span class="text-[10px] font-bold text-gray-400">${dayName} • ${ev.event_date}</span>
                    ${ev.start_time ? `<span class="text-[10px] font-bold text-gray-400">🕐 ${ev.start_time.substring(0, 5)}</span>` : ''}
                    ${ev.location ? `<span class="text-[10px] font-bold text-gray-400">📍 ${esc(ev.location)}</span>` : ''}
                </div>
            </div>
            <span class="px-2.5 py-1 ${t.bg} rounded-lg text-[10px] font-black flex-shrink-0">${t.label}</span>
        </div>`;
    });
    html += `</div>`;
    return html;
}

// ── Show Event Detail ────────────────────────────────────────
function showEventDetail(eventId) {
    const ev = calEvents.find(e => e.id == eventId);
    if (!ev) return;

    const t = CAL_EVENT_TYPES[ev.event_type] || CAL_EVENT_TYPES.other;
    const d = new Date(ev.event_date);
    const dayName = AR_DAYS[d.getDay()];

    showModal(`
    <div class="p-6 sm:p-8">
        <div class="flex items-center gap-4 mb-6">
            <div class="w-16 h-16 rounded-2xl flex items-center justify-center text-4xl text-white shadow-xl" style="background: ${t.color}">${t.icon}</div>
            <div>
                <h3 class="text-2xl font-black text-gray-800">${esc(ev.title)}</h3>
                <span class="px-3 py-1 ${t.bg} rounded-full text-xs font-black">${t.label}</span>
            </div>
        </div>

        <div class="space-y-3 mb-6">
            <div class="flex items-center gap-3 text-sm">
                <span class="w-8 h-8 bg-indigo-50 rounded-lg flex items-center justify-center text-lg">📅</span>
                <div>
                    <span class="text-gray-400 font-bold text-xs">التاريخ</span>
                    <p class="font-bold text-gray-700">${dayName} ${ev.event_date}${ev.end_date && ev.end_date !== ev.event_date ? ` → ${ev.end_date}` : ''}</p>
                </div>
            </div>
            ${ev.start_time ? `
            <div class="flex items-center gap-3 text-sm">
                <span class="w-8 h-8 bg-blue-50 rounded-lg flex items-center justify-center text-lg">🕐</span>
                <div>
                    <span class="text-gray-400 font-bold text-xs">الوقت</span>
                    <p class="font-bold text-gray-700">${ev.start_time.substring(0, 5)}${ev.end_time ? ' - ' + ev.end_time.substring(0, 5) : ''}</p>
                </div>
            </div>` : ''}
            ${ev.location ? `
            <div class="flex items-center gap-3 text-sm">
                <span class="w-8 h-8 bg-green-50 rounded-lg flex items-center justify-center text-lg">📍</span>
                <div>
                    <span class="text-gray-400 font-bold text-xs">المكان</span>
                    <p class="font-bold text-gray-700">${esc(ev.location)}</p>
                </div>
            </div>` : ''}
            ${ev.description ? `
            <div class="bg-gray-50 rounded-2xl p-4 mt-4">
                <p class="text-xs text-gray-400 font-black uppercase tracking-widest mb-2">التفاصيل</p>
                <p class="text-gray-600 text-sm leading-relaxed">${esc(ev.description)}</p>
            </div>` : ''}
        </div>

        <div class="flex gap-2 pt-4 border-t border-gray-100">
            ${canEdit() ? `
            <button onclick="closeModal();showCalendarEventForm(${ev.id})" class="flex-1 bg-indigo-600 text-white py-3 rounded-2xl font-black text-sm hover:bg-indigo-700 transition cursor-pointer">✏️ تعديل</button>
            <button onclick="deleteCalendarEvent(${ev.id})" class="bg-red-50 text-red-600 px-5 py-3 rounded-2xl font-black text-sm hover:bg-red-100 transition cursor-pointer">🗑️</button>` : ''}
            <button onclick="addToGoogleCalendar(${ev.id})" class="bg-blue-50 text-blue-600 px-5 py-3 rounded-2xl font-black text-sm hover:bg-blue-100 transition cursor-pointer flex items-center gap-1">
                <span>📤</span> Google
            </button>
            <button onclick="closeModal()" class="bg-gray-100 text-gray-500 px-5 py-3 rounded-2xl font-black text-sm hover:bg-gray-200 transition cursor-pointer">إغلاق</button>
        </div>
    </div>`);
}

// ── Show Event Form ──────────────────────────────────────────
function showCalendarEventForm(eventId = null, prefillDate = null) {
    const ev = eventId ? calEvents.find(e => e.id == eventId) : null;
    const isEdit = !!ev;
    const today = new Date().toISOString().split('T')[0];

    showModal(`
    <div class="p-6 sm:p-8">
        <div class="flex items-center gap-3 mb-6">
            <span class="text-4xl">${isEdit ? '✏️' : '➕'}</span>
            <div>
                <h3 class="text-2xl font-black text-gray-800">${isEdit ? 'تعديل الحدث' : 'إضافة حدث جديد'}</h3>
                <p class="text-sm text-gray-400 font-bold">أضف نشاطاً أو حدثاً للتقويم الرياضي</p>
            </div>
        </div>

        <div class="space-y-5">
            <div>
                <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">عنوان الحدث *</label>
                <input type="text" id="ceTitle" value="${ev ? esc(ev.title) : ''}" class="w-full px-4 py-3 bg-gray-50 border-2 border-gray-50 rounded-2xl focus:bg-white focus:border-indigo-500 focus:outline-none font-bold" placeholder="مثال: بطولة كرة القدم بين الفصول">
            </div>

            <div>
                <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">نوع الحدث *</label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    ${Object.entries(CAL_EVENT_TYPES).map(([key, t]) => `
                        <label class="cursor-pointer">
                            <input type="radio" name="ceType" value="${key}" class="hidden peer" ${(ev ? ev.event_type : 'match') === key ? 'checked' : ''}>
                            <div class="peer-checked:ring-2 peer-checked:ring-indigo-500 peer-checked:bg-indigo-50 border-2 border-gray-100 p-3 rounded-xl text-center hover:bg-gray-50 transition">
                                <span class="text-2xl block mb-1">${t.icon}</span>
                                <span class="text-[10px] font-black text-gray-600">${t.label}</span>
                            </div>
                        </label>
                    `).join('')}
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">تاريخ البداية *</label>
                    <input type="date" id="ceDate" value="${ev ? ev.event_date : (prefillDate || today)}" class="w-full px-4 py-3 bg-gray-50 border-2 border-gray-50 rounded-2xl focus:bg-white focus:border-indigo-500 focus:outline-none font-bold">
                </div>
                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">تاريخ النهاية</label>
                    <input type="date" id="ceEndDate" value="${ev ? (ev.end_date || '') : ''}" class="w-full px-4 py-3 bg-gray-50 border-2 border-gray-50 rounded-2xl focus:bg-white focus:border-indigo-500 focus:outline-none font-bold">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">وقت البداية</label>
                    <input type="time" id="ceStartTime" value="${ev ? (ev.start_time || '').substring(0, 5) : ''}" class="w-full px-4 py-3 bg-gray-50 border-2 border-gray-50 rounded-2xl focus:bg-white focus:border-indigo-500 focus:outline-none font-bold">
                </div>
                <div>
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">وقت النهاية</label>
                    <input type="time" id="ceEndTime" value="${ev ? (ev.end_time || '').substring(0, 5) : ''}" class="w-full px-4 py-3 bg-gray-50 border-2 border-gray-50 rounded-2xl focus:bg-white focus:border-indigo-500 focus:outline-none font-bold">
                </div>
            </div>

            <div>
                <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">المكان</label>
                <input type="text" id="ceLocation" value="${ev ? esc(ev.location || '') : ''}" class="w-full px-4 py-3 bg-gray-50 border-2 border-gray-50 rounded-2xl focus:bg-white focus:border-indigo-500 focus:outline-none font-bold" placeholder="مثال: الملعب الرئيسي">
            </div>

            <div>
                <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">وصف / تفاصيل</label>
                <textarea id="ceDesc" rows="3" class="w-full px-4 py-3 bg-gray-50 border-2 border-gray-50 rounded-2xl focus:bg-white focus:border-indigo-500 focus:outline-none font-bold resize-none" placeholder="تفاصيل إضافية...">${ev ? esc(ev.description || '') : ''}</textarea>
            </div>

            <div>
                <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-2">لون الحدث</label>
                <input type="color" id="ceColor" value="${ev ? (ev.color || '#10b981') : '#10b981'}" class="w-16 h-10 border-2 border-gray-100 rounded-xl cursor-pointer">
            </div>
        </div>

        <div class="flex gap-3 mt-8">
            <button onclick="saveCalendarEvent(${eventId || 'null'})" class="flex-1 bg-gradient-to-r from-indigo-600 to-purple-600 text-white py-4 rounded-2xl font-black hover:shadow-xl transition cursor-pointer active:scale-[0.98]">
                ${isEdit ? '💾 حفظ التعديلات' : '✅ إضافة الحدث'}
            </button>
            <button onclick="closeModal()" class="bg-gray-100 text-gray-500 px-6 py-4 rounded-2xl font-black hover:bg-gray-200 transition cursor-pointer">إلغاء</button>
        </div>
    </div>`);
}

// ── Save Event ───────────────────────────────────────────────
async function saveCalendarEvent(id) {
    const title = document.getElementById('ceTitle').value.trim();
    const eventDate = document.getElementById('ceDate').value;
    const eventType = document.querySelector('input[name="ceType"]:checked')?.value;

    if (!title || !eventDate || !eventType) {
        showToast('يرجى ملء الحقول المطلوبة', 'error');
        return;
    }

    const data = {
        id: id,
        title,
        event_date: eventDate,
        end_date: document.getElementById('ceEndDate').value || null,
        start_time: document.getElementById('ceStartTime').value || null,
        end_time: document.getElementById('ceEndTime').value || null,
        event_type: eventType,
        location: document.getElementById('ceLocation').value.trim(),
        description: document.getElementById('ceDesc').value.trim(),
        color: document.getElementById('ceColor').value
    };

    const r = await API.post('calendar_save', data);
    if (r && r.success) {
        showToast(r.message || 'تم حفظ الحدث ✅');
        closeModal();
        await loadCalendarEvents();
        renderCalendarContent();
    } else {
        showToast(r?.error || 'خطأ في الحفظ', 'error');
    }
}

// ── Delete Event ─────────────────────────────────────────────
async function deleteCalendarEvent(id) {
    if (!confirm('هل تريد حذف هذا الحدث؟')) return;

    const r = await API.post('calendar_delete', { id });
    if (r && r.success) {
        showToast('تم حذف الحدث 🗑️');
        closeModal();
        await loadCalendarEvents();
        renderCalendarContent();
    } else {
        showToast(r?.error || 'خطأ', 'error');
    }
}

// ── Export to Google Calendar ─────────────────────────────────
async function exportToGoogleCalendar() {
    const r = await API.get('calendar_export_ics', { year: calViewYear });
    if (r && r.success && r.data) {
        const blob = new Blob([r.data.ics_content], { type: 'text/calendar;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = r.data.filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        showToast(`تم تصدير ${r.data.event_count} حدث كملف ICS 📤`);
    } else {
        showToast(r?.error || 'خطأ في التصدير', 'error');
    }
}

// ── Add single event to Google Calendar ──────────────────────
function addToGoogleCalendar(eventId) {
    const ev = calEvents.find(e => e.id == eventId);
    if (!ev) return;

    const start = ev.event_date.replace(/-/g, '') + (ev.start_time ? 'T' + ev.start_time.replace(/:/g, '') + '00' : '');
    const end = (ev.end_date || ev.event_date).replace(/-/g, '') + (ev.end_time ? 'T' + ev.end_time.replace(/:/g, '') + '00' : '');

    const url = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(ev.title)}&dates=${start}/${end}&details=${encodeURIComponent(ev.description || '')}&location=${encodeURIComponent(ev.location || '')}`;

    window.open(url, '_blank');
    showToast('تم فتح Google Calendar 📅');
}

console.log('✅ calendar.js loaded');
