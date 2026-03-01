/**
 * PE Smart School - Timetable Module
 * =====================================
 * Teachers exclusively manage their own 5-day schedules
 */

let myClassesForTimetable = [];

async function renderTimetablePage() {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = showLoading();

    // 1. Fetch teacher's assigned classes to fill the dropdowns
    const clsRes = await API.get('classes');
    if (clsRes && clsRes.success) {
        myClassesForTimetable = clsRes.data || [];
    } else {
        mc.innerHTML = '<p class="text-red-500 text-center py-8">حدث خطأ في جلب الفصول الخاصة بك.</p>';
        return;
    }

    if (myClassesForTimetable.length === 0) {
        mc.innerHTML = `
        <div class="text-center py-20 bg-white rounded-3xl shadow-sm border border-gray-100 fade-in">
            <span class="text-6xl block mb-4">📭</span>
            <h3 class="text-2xl font-black text-gray-800 mb-2">لا يوجد فصول مسندة إليك!</h3>
            <p class="text-gray-500">يجب على مدير المدرسة أو المشرف إسناد فصول إليك أولاً لتتمكن من إنشاء جدولك الزمني.</p>
        </div>`;
        return;
    }

    // 2. Fetch the existing timetable
    let currentTimetable = [];
    const ttRes = await API.get('timetable');
    if (ttRes && ttRes.success) {
        currentTimetable = ttRes.data || [];
    }

    // 3. Fetch period times
    let periodTimes = [];
    const ptRes = await API.get('period_times');
    if (ptRes && ptRes.success) {
        periodTimes = ptRes.data || [];
    }

    const days = [
        { id: 1, name: 'الأحد' },
        { id: 2, name: 'الإثنين' },
        { id: 3, name: 'الثلاثاء' },
        { id: 4, name: 'الأربعاء' },
        { id: 5, name: 'الخميس' }
    ];

    const maxPeriods = 8;

    // Helper to find period time
    const getPeriodTime = (num) => periodTimes.find(p => parseInt(p.period_number) === num);

    let html = `
    <div class="fade-in max-w-7xl mx-auto px-2 md:px-0">
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-3xl font-black text-gray-800 flex items-center gap-3">
                    <span class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-indigo-200 text-2xl">
                        📅
                    </span>
                    جدولي الأسبوعي (كشكول التحضير الذكي)
                </h2>
                <p class="text-gray-500 mt-2 font-bold text-sm">حدد الفصول التي تدرّسها في كل حصة ليتعرف النظام على تحضيرك اليومي مباشرة.</p>
            </div>
            <div class="flex gap-3">
                <button onclick="saveTimetableChanges()" class="bg-gradient-to-r from-emerald-500 to-teal-500 text-white px-8 py-3 rounded-2xl font-black hover:shadow-lg hover:-translate-y-1 transition transform flex items-center gap-2">
                    💾 حفظ الجدول
                </button>
            </div>
        </div>

        <!-- Period Times Configuration -->
        <div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-black text-gray-800 flex items-center gap-2">⏰ مواعيد الحصص</h3>
                <button onclick="savePeriodTimesAction()" class="bg-indigo-500 text-white px-5 py-2 rounded-xl font-bold text-sm hover:bg-indigo-600 transition shadow-sm">
                    حفظ المواعيد
                </button>
            </div>
            <p class="text-xs text-gray-500 font-bold mb-4">حدد وقت بداية ونهاية كل حصة ليعرف النظام أي حصة أنت فيها الآن ويسهّل عليك التحضير.</p>
            <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
                ${Array.from({ length: maxPeriods }, (_, i) => {
        const pt = getPeriodTime(i + 1);
        return `
                    <div class="bg-gray-50 rounded-xl p-3 border border-gray-100 text-center">
                        <label class="block text-xs font-black text-gray-600 mb-2">الحصة ${i + 1}</label>
                        <input type="time" class="period-start w-full text-xs text-center bg-white border border-gray-200 rounded-lg px-1 py-1.5 mb-1 focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400 outline-none" 
                               data-period="${i + 1}" value="${pt ? pt.start_time.substring(0, 5) : ''}" placeholder="بداية">
                        <input type="time" class="period-end w-full text-xs text-center bg-white border border-gray-200 rounded-lg px-1 py-1.5 focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400 outline-none" 
                               data-period="${i + 1}" value="${pt ? pt.end_time.substring(0, 5) : ''}" placeholder="نهاية">
                    </div>`;
    }).join('')}
            </div>
        </div>

        <!-- Timetable Grid -->
        <div class="bg-white rounded-[2rem] shadow-xl shadow-gray-100 border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-right min-w-[800px]" id="timetableGrid">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-6 py-5 text-sm font-black text-gray-400 border-b border-gray-200 w-32">اليوم / الحصة</th>
                            ${Array.from({ length: maxPeriods }, (_, i) => {
        const pt = getPeriodTime(i + 1);
        const timeLabel = pt ? `<br><span class="text-[10px] font-bold text-gray-400">${pt.start_time.substring(0, 5)}</span>` : '';
        return `<th class="px-3 py-5 text-sm font-black text-gray-400 border-b border-l border-gray-200 text-center">الحصة ${i + 1}${timeLabel}</th>`;
    }).join('')}
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    `;

    days.forEach(day => {
        html += `<tr class="hover:bg-gray-50/30 transition-colors">
            <td class="px-6 py-4 font-black text-gray-800 bg-gray-50/50">${day.name}</td>
        `;

        for (let period = 1; period <= maxPeriods; period++) {
            const existingEntry = currentTimetable.find(e => parseInt(e.day_of_week) === day.id && parseInt(e.period_number) === period);
            const selectedClassId = existingEntry ? existingEntry.class_id : '';

            html += `
            <td class="p-2 border-l border-gray-100">
                <select class="timetable-select w-full text-xs font-bold text-gray-700 bg-gray-50 border border-gray-200 outline-none rounded-xl px-2 py-2 appearance-none focus:ring-2 focus:ring-indigo-100 focus:border-indigo-400 transition" 
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
            
            <div class="bg-indigo-50 px-6 py-5 border-t border-indigo-100 flex items-center gap-3">
                <div class="w-8 h-8 bg-indigo-100 text-indigo-500 rounded-full flex items-center justify-center">ℹ️</div>
                <p class="text-xs font-bold text-indigo-800 leading-relaxed">بمجرد حفظ جدولك ومواعيد الحصص، سيظهر في الرئيسية الحصة النشطة حالياً مع زر تحضير سريع.</p>
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

    const res = await API.post('save_timetable', { timetable: timetableData });
    if (res && res.success) {
        showToast('تم حفظ جدول الحصص بنجاح! 📅', 'success');
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
        showToast('تم حفظ مواعيد الحصص بنجاح! ⏰');
    } else {
        showToast(res?.message || 'تعذر حفظ المواعيد', 'error');
    }
}
