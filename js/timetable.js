/**
 * PE Smart School - Timetable Module
 * =====================================
 */

let myClassesForTimetable = [];
let currentTimetableTeacherId = null;

async function renderTimetablePage(teacherId = null) {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = showLoading();

    // Set target teacher (default to self)
    currentTimetableTeacherId = teacherId || currentUser.id;

    // 1. Fetch School Config (to get dynamic period count)
    const schoolRes = await API.get('get_school_info');
    let totalPeriods = 8; // Default fallback
    if (schoolRes && schoolRes.success) {
        totalPeriods = parseInt(schoolRes.data.total_periods) || 8;
    }

    // 2. Fetch classes (filtered by target teacher if not admin)
    const clsUrl = (isAdmin() || isSupervisor()) ? `classes&teacher_id=${currentTimetableTeacherId}` : 'classes';
    const clsRes = await API.get(clsUrl);
    if (clsRes && clsRes.success) {
        myClassesForTimetable = clsRes.data || [];
    } else {
        mc.innerHTML = '<p class="text-red-500 text-center py-8">حدث خطأ في جلب الفصول.</p>';
        return;
    }

    // 3. Fetch the existing timetable for target teacher
    let currentTimetable = [];
    const ttRes = await API.get('timetable', { teacher_id: currentTimetableTeacherId });
    if (ttRes && ttRes.success) {
        currentTimetable = ttRes.data || [];
    }

    // 4. Fetch period times (school-wide)
    let periodTimes = [];
    const ptRes = await API.get('period_times');
    if (ptRes && ptRes.success) {
        periodTimes = ptRes.data || [];
    }

    // 5. If admin, fetch all teachers to show selector
    let allTeachers = [];
    if (isAdmin() || isSupervisor()) {
        const tRes = await API.get('users', { role: 'teacher' });
        if (tRes && tRes.success) allTeachers = tRes.data || [];
    }

    const days = [{ id: 1, name: 'الأحد' }, { id: 2, name: 'الإثنين' }, { id: 3, name: 'الثلاثاء' }, { id: 4, name: 'الأربعاء' }, { id: 5, name: 'الخميس' }];
    const getPeriodTime = (num) => periodTimes.find(p => parseInt(p.period_number) === num);

    let html = `
    <div class="fade-in max-w-7xl mx-auto px-2 md:px-0">
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-3xl font-black text-gray-800 flex items-center gap-3">
                    <span class="w-12 h-12 bg-emerald-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-emerald-100">
                        📅
                    </span>
                    جدول الحصص الأسبوعي
                </h2>
                <p class="text-gray-500 mt-2 font-bold text-sm">إدارة الحصص والفصول الموزعة خلال الأسبوع حسب نظام المدرسة.</p>
            </div>
            
            <div class="flex flex-wrap gap-3">
                ${(isAdmin() || isSupervisor()) ? `
                <div class="flex items-center gap-2 bg-white border border-gray-200 rounded-2xl px-4 py-2">
                    <span class="text-xs font-black text-gray-400">المعلم:</span>
                    <select onchange="renderTimetablePage(this.value)" class="text-sm font-bold border-none outline-none bg-transparent text-emerald-600 cursor-pointer">
                        <option value="${currentUser.id}" ${currentTimetableTeacherId == currentUser.id ? 'selected' : ''}>جدولي الخاص</option>
                        ${allTeachers.map(t => `<option value="${t.id}" ${currentTimetableTeacherId == t.id ? 'selected' : ''}>${esc(t.name)}</option>`).join('')}
                    </select>
                </div>
                ` : ''}
                <button onclick="saveTimetableChanges()" class="bg-emerald-600 text-white px-8 py-3 rounded-2xl font-black hover:bg-emerald-700 transition flex items-center gap-2 shadow-xl shadow-emerald-100">
                    💾 حفظ الجدول
                </button>
            </div>
        </div>

        ${isAdmin() ? `
        <!-- Period Times Configuration (Admin Only) -->
        <div class="bg-gradient-to-br from-emerald-800 to-green-950 rounded-[2rem] shadow-xl p-6 mb-6 text-white border border-white/10">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-black flex items-center gap-2">⏰ ضبط مواعيد الـ ${totalPeriods} حصص المدرسية</h3>
                <button onclick="savePeriodTimesAction()" class="bg-emerald-500 hover:bg-emerald-400 text-white px-6 py-2.5 rounded-xl font-black text-sm transition">
                    حفظ المواعيد
                </button>
            </div>
            <div class="flex flex-wrap gap-3">
                ${Array.from({ length: totalPeriods }, (_, i) => {
        const pt = getPeriodTime(i + 1);
        return `
                    <div class="bg-white/5 rounded-2xl p-4 border border-white/10 text-center flex-1 min-w-[120px]">
                        <label class="block text-[10px] font-black opacity-60 mb-2 uppercase">الحصة ${i + 1}</label>
                        <div class="flex flex-col gap-1">
                            <input type="time" class="period-start w-full text-xs text-center bg-white/10 border border-white/10 text-white rounded-lg px-2 py-2 outline-none focus:bg-white/20 transition" 
                                   data-period="${i + 1}" value="${pt ? pt.start_time.substring(0, 5) : ''}">
                            <input type="time" class="period-end w-full text-xs text-center bg-white/10 border border-white/10 text-white rounded-lg px-2 py-2 outline-none focus:bg-white/20 transition" 
                                   data-period="${i + 1}" value="${pt ? pt.end_time.substring(0, 5) : ''}">
                        </div>
                    </div>`;
    }).join('')}
            </div>
        </div>
        ` : ''}

        <!-- Timetable Grid -->
        <div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-right min-w-[1000px]" id="timetableGrid">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-5 text-sm font-black text-gray-400 border-b border-gray-200 w-32">اليوم / الحصة</th>
                            ${Array.from({ length: totalPeriods }, (_, i) => {
        const pt = getPeriodTime(i + 1);
        const timeLabel = pt ? `<br><span class="text-[10px] font-bold text-gray-400">${pt.start_time.substring(0, 5)} - ${pt.end_time.substring(0, 5)}</span>` : '';
        return `<th class="px-3 py-5 text-sm font-black text-gray-400 border-b border-l border-gray-200 text-center">الحصـة ${i + 1}${timeLabel}</th>`;
    }).join('')}
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    `;

    days.forEach(day => {
        html += `<tr class="hover:bg-emerald-50/20 transition-colors">
            <td class="px-6 py-4 font-black text-gray-800 bg-gray-50/50 border-emerald-100">${day.name}</td>
        `;

        for (let period = 1; period <= totalPeriods; period++) {
            const existingEntry = currentTimetable.find(e => parseInt(e.day_of_week) === day.id && parseInt(e.period_number) === period);
            const selectedClassId = existingEntry ? existingEntry.class_id : '';

            html += `
            <td class="p-2 border-l border-gray-50">
                <select class="timetable-select w-full text-xs font-bold text-gray-700 bg-gray-50 border border-gray-100 outline-none rounded-xl px-2 py-3 appearance-none focus:ring-2 focus:ring-emerald-100 focus:border-emerald-400 transition" 
                        data-day="${day.id}" 
                        data-period="${period}">
                    <option value="">- فراغ -</option>
                    ${myClassesForTimetable.map(cls => `
                        <option value="${cls.id}" ${parseInt(selectedClassId) === parseInt(cls.id) ? 'selected' : ''}>
                            ${esc(cls.full_name || cls.name)}
                        </option>
                    `).join('')}
                </select>
            </td>
            `;
        }
        html += `</tr>`;
    });

    html += `
                    </tbody>
                </table>
            </div>
            
            <div class="bg-emerald-50 px-6 py-5 border-t border-emerald-100 flex items-center gap-3">
                <div class="w-8 h-8 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center">ℹ️</div>
                <p class="text-xs font-bold text-emerald-800 leading-relaxed">
                    ${(isAdmin() || isSupervisor()) ? 'أنت الآن تقوم بعرض وتعديل جدول الحصة الخاص بالمعلم المختار.' : 'بمجرد حفظ جدولك ومواعيد الحصص، سيظهر في الرئيسية الحصة النشطة حالياً مع زر تحضير سريع.'}
                </p>
            </div>
        </div>
    </div>
    `;

    mc.innerHTML = html;
}

// Save timetable classes
async function saveTimetableChanges() {
    const selects = document.querySelectorAll('.timetable-select');
    let timetableData = [];

    selects.forEach(select => {
        const classId = parseInt(select.value);
        if (classId > 0) {
            timetableData.push({
                day_of_week: parseInt(select.dataset.day),
                period_number: parseInt(select.dataset.period),
                class_id: classId
            });
        }
    });

    const btn = event.currentTarget;
    const oldText = btn.innerHTML;
    btn.innerHTML = '<span class="animate-spin inline-block w-5 h-5 rounded-full border-2 border-white border-t-transparent"></span> جاري الحفظ...';
    btn.disabled = true;

    const res = await API.post('save_timetable', {
        timetable: timetableData,
        teacher_id: currentTimetableTeacherId
    });

    if (res && res.success) {
        showToast('تم حفظ الجدول بنجاح! 📅', 'success');
    } else {
        showToast(res?.message || 'تعذر حفظ الجدول', 'error');
    }

    btn.innerHTML = oldText;
    btn.disabled = false;
}

// Save period times
async function savePeriodTimesAction() {
    const starts = document.querySelectorAll('.period-start');
    const ends = document.querySelectorAll('.period-end');
    let periods = [];

    starts.forEach((input, i) => {
        const start = input.value;
        const end = ends[i].value;
        const num = parseInt(input.dataset.period);
        if (start && end) {
            periods.push({ period_number: num, start_time: start, end_time: end });
        }
    });

    if (periods.length === 0) {
        showToast('يرجى تحديد وقت حصة واحدة على الأقل', 'error');
        return;
    }

    const res = await API.post('save_period_times', { periods });
    if (res && res.success) {
        showToast('تم حفظ مواعيد الحصص المدرسية بنجاح! ⏰');
    } else {
        showToast(res?.message || 'تعذر حفظ المواعيد', 'error');
    }
}
