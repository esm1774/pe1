/**
 * PE Smart School System - Attendance Page
 */

let attFilter = { class_id: '', date: new Date().toISOString().split('T')[0] };

async function renderAttendance(classId = null) {
    if (classId) {
        attFilter.class_id = classId;
    }

    const mc = document.getElementById('mainContent');
    mc.innerHTML = showLoading();

    const cl = await API.get('classes');
    const classes = cl?.data || [];

    mc.innerHTML = `
    <div class="fade-in px-4 md:px-0">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
            <div>
                <h2 class="text-3xl md:text-4xl font-black text-gray-800 tracking-tight">📋 سجل الحضور اليومي</h2>
                <p class="text-gray-500 font-bold mt-1 text-sm md:text-base">متابعة انضباط الطلاب وإدارة السجلات الصحية الفورية</p>
            </div>
            ${canEdit() ? `
            <div class="flex flex-col sm:flex-row gap-3">
                <button onclick="markAllPresent()" class="bg-emerald-50 text-emerald-700 px-8 py-4 rounded-2xl font-black hover:bg-emerald-100 transition active:scale-95 flex items-center justify-center gap-2 border border-emerald-100 shadow-sm">
                    ✅ تحضير الكل
                </button>
                <button onclick="saveAttendance()" class="bg-emerald-600 text-white px-10 py-4 rounded-2xl font-black hover:bg-emerald-700 shadow-xl shadow-emerald-100 transition active:scale-95 flex items-center justify-center gap-2">
                    💾 حفظ السجل
                </button>
            </div>
            ` : ''}
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-xl shadow-gray-100/50 border border-gray-100 p-6 md:p-8 mb-10 flex flex-col md:flex-row gap-6 md:items-end">
            <div class="flex-1">
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mr-2 mb-2">تاريخ التحضير</label>
                <input type="date" id="attDate" value="${attFilter.date}" onchange="attFilter.date=this.value;loadAttendanceList()" class="w-full px-6 py-4 bg-gray-50 border-2 border-transparent focus:border-emerald-500 focus:bg-white focus:outline-none transition-all font-black text-gray-700 rounded-2xl shadow-inner">
            </div>
            <div class="flex-1">
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mr-2 mb-2">الفصل التعليمي</label>
                <div class="relative">
                    <select id="attClass" onchange="attFilter.class_id=this.value;loadAttendanceList()" class="relative z-10 w-full px-6 py-4 bg-gray-50 border-2 border-transparent focus:border-emerald-500 focus:bg-white focus:outline-none transition-all font-black text-gray-700 rounded-2xl appearance-none cursor-pointer shadow-inner">
                        <option value="">اختر الفصل لفتح السجل</option>
                        ${classes.map(c => `<option value="${c.id}" ${attFilter.class_id == c.id ? 'selected' : ''}>${esc(c.full_name || c.name)}</option>`).join('')}
                    </select>
                    <div class="absolute left-6 top-1/2 -translate-y-1/2 text-gray-400 z-0 text-sm">▼</div>
                </div>
            </div>
        </div>

        <div id="attendanceList" class="min-h-[500px]">
            <div class="text-center py-32 bg-gray-50/50 rounded-[3.5rem] border-4 border-dashed border-gray-100 flex flex-col items-center justify-center shadow-inner">
                <div class="text-8xl mb-8 grayscale opacity-20 animate-bounce">📋</div>
                <p class="text-gray-400 font-black text-2xl tracking-tight">يرجى اختيار الفصل للمتابعة</p>
                <p class="text-gray-300 font-bold mt-2">حدد الصف والفصل التعليمي أعلاه لاستعراض قائمة الطلاب وتجضيرهم</p>
            </div>
        </div>
    </div>`;

    if (attFilter.class_id) loadAttendanceList();
}

async function loadAttendanceList() {
    if (!attFilter.class_id) return;

    const r = await API.get('attendance', { class_id: attFilter.class_id, date: attFilter.date });
    if (!r || !r.success) {
        document.getElementById('attendanceList').innerHTML = `<div class="p-20 text-center text-red-500 font-bold">فشل في تحميل السجل</div>`;
        return;
    }

    const students = r.data;
    const al = document.getElementById('attendanceList');

    // Desktop View
    let html = `
    <div class="hidden lg:block bg-white rounded-[2.5rem] shadow-2xl shadow-gray-100/50 border border-gray-100 overflow-hidden mb-12">
        <table class="w-full">
            <thead>
                <tr class="bg-gray-50/70 border-b border-gray-100">
                    <th class="px-8 py-6 text-right text-[10px] font-black text-gray-400 uppercase tracking-widest">الطالب</th>
                    <th class="px-8 py-6 text-center text-[10px] font-black text-gray-400 uppercase tracking-widest">الصحة</th>
                    <th class="px-8 py-6 text-center text-[10px] font-black text-gray-400 uppercase tracking-widest w-48">التحضير الرسمي</th>
                    <th class="px-4 py-6 text-center text-[10px] font-black text-gray-400 uppercase tracking-widest w-48">الزي الرياضي</th>
                    ${hasFeature('behavior_analytics') ? `
                    <th class="px-4 py-6 text-center text-[10px] font-black text-gray-400 uppercase tracking-widest w-24">المهارة</th>
                    <th class="px-4 py-6 text-center text-[10px] font-black text-gray-400 uppercase tracking-widest w-24">السلوك</th>
                    <th class="px-4 py-6 text-center text-[10px] font-black text-gray-400 uppercase tracking-widest w-24">المشاركة</th>
                    ` : ''}
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                ${students.map((s, i) => {
        const st = s.status || 'present';
        return `
                    <tr class="hover:bg-emerald-50/20 transition-all att-row ${s.health_alerts > 0 ? 'bg-red-50/10' : ''}" data-student-id="${s.student_id}">
                        <td class="px-8 py-6">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-2xl bg-gray-50 flex items-center justify-center text-sm font-black text-gray-400 border border-gray-50 group-hover:bg-white transition-colors">${i + 1}</div>
                                <div>
                                    <div class="font-black text-gray-800 text-sm">${esc(s.name)}</div>
                                    <div class="text-[10px] text-gray-400 font-bold font-mono tracking-tighter mt-0.5">@ID_${s.student_id}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-8 py-6 text-center">
                            ${s.health_alerts > 0
                ? `<div class="inline-flex items-center gap-2 px-4 py-2 bg-red-50 text-red-600 rounded-full text-[10px] font-black border border-red-100 cursor-help" title="${esc(s.health_summary || '')}">⚠️ ${s.health_alerts} تنبيه</div>`
                : '<div class="w-9 h-9 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto border border-emerald-100">✓</div>'}
                        </td>
                        <td class="px-4 py-6">
                            <div class="flex items-center justify-center gap-1 bg-gray-50 p-1.5 rounded-[1.2rem] border border-gray-100 shadow-inner text-xs">
                                <label class="flex-1 cursor-pointer" title="حاضر">
                                    <input type="radio" name="att_${s.student_id}" value="present" ${st === 'present' ? 'checked' : ''} class="peer hidden" ${!canEdit() ? 'disabled' : ''}>
                                    <div class="py-2 text-center rounded-xl font-black peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:shadow-lg transition-all text-gray-400 hover:text-gray-600">ح</div>
                                </label>
                                <label class="flex-1 cursor-pointer" title="غائب">
                                    <input type="radio" name="att_${s.student_id}" value="absent" ${st === 'absent' ? 'checked' : ''} class="peer hidden" ${!canEdit() ? 'disabled' : ''}>
                                    <div class="py-2 text-center rounded-xl font-black peer-checked:bg-red-600 peer-checked:text-white peer-checked:shadow-lg transition-all text-gray-400 hover:text-gray-600">غ</div>
                                </label>
                                <label class="flex-1 cursor-pointer" title="متأخر">
                                    <input type="radio" name="att_${s.student_id}" value="late" ${st === 'late' ? 'checked' : ''} class="peer hidden" ${!canEdit() ? 'disabled' : ''}>
                                    <div class="py-2 text-center rounded-xl font-black peer-checked:bg-amber-500 peer-checked:text-white peer-checked:shadow-lg transition-all text-gray-400 hover:text-gray-600">ت</div>
                                </label>
                            </div>
                        </td>
                        <td class="px-4 py-6 text-center">
                            <div class="flex items-center justify-center gap-1">
                                <label class="cursor-pointer text-center group" title="كامل">
                                    <input type="radio" name="uni_${s.student_id}" value="full" ${s.uniform_status === 'full' ? 'checked' : ''} class="peer hidden" ${!canEdit() ? 'disabled' : ''}>
                                    <div class="px-2 py-1 rounded-lg bg-gray-50 border border-gray-100 text-emerald-600 peer-checked:bg-emerald-600 peer-checked:border-emerald-600 peer-checked:text-white transition-all text-xs font-black flex flex-col items-center">
                                        👕<span class="text-[8px] mt-0.5 opacity-50 group-hover:opacity-100 peer-checked:opacity-100">كامل</span>
                                    </div>
                                </label>
                                <label class="cursor-pointer text-center group" title="ناقص">
                                    <input type="radio" name="uni_${s.student_id}" value="partial" ${s.uniform_status === 'partial' ? 'checked' : ''} class="peer hidden" ${!canEdit() ? 'disabled' : ''}>
                                    <div class="px-2 py-1 rounded-lg bg-gray-50 border border-gray-100 text-amber-500 peer-checked:bg-amber-500 peer-checked:border-amber-500 peer-checked:text-white transition-all text-xs font-black flex flex-col items-center">
                                        👕<span class="text-[8px] mt-0.5 opacity-50 group-hover:opacity-100 peer-checked:opacity-100">ناقص</span>
                                    </div>
                                </label>
                                <label class="cursor-pointer text-center group" title="مخالف">
                                    <input type="radio" name="uni_${s.student_id}" value="wrong" ${s.uniform_status === 'wrong' ? 'checked' : ''} class="peer hidden" ${!canEdit() ? 'disabled' : ''}>
                                    <div class="px-2 py-1 rounded-lg bg-gray-50 border border-gray-100 text-red-500 peer-checked:bg-red-500 peer-checked:border-red-500 peer-checked:text-white transition-all text-xs font-black flex flex-col items-center">
                                        👕<span class="text-[8px] mt-0.5 opacity-50 group-hover:opacity-100 peer-checked:opacity-100">مخالف</span>
                                    </div>
                                </label>
                                <label class="cursor-pointer text-center group" title="بدون زي">
                                    <input type="radio" name="uni_${s.student_id}" value="missing" ${s.uniform_status === 'missing' ? 'checked' : ''} class="peer hidden" ${!canEdit() ? 'disabled' : ''}>
                                    <div class="px-2 py-1 rounded-lg bg-gray-50 border border-gray-100 text-gray-500 peer-checked:bg-gray-600 peer-checked:border-gray-600 peer-checked:text-white transition-all text-xs font-black flex flex-col items-center">
                                        ❌<span class="text-[8px] mt-0.5 opacity-50 group-hover:opacity-100 peer-checked:opacity-100">بدون</span>
                                    </div>
                                </label>
                            </div>
                        </td>
                        ${hasFeature('behavior_analytics') ? `
                        <td class="px-4 py-6 text-center">
                            <div class="flex items-center justify-center rating-stars" dir="ltr">
                                ${[1, 2, 3].map(v => `<span class="cursor-pointer text-xl transition-colors ${s.skills_stars >= v ? 'text-amber-400' : 'text-gray-300'}" onclick="setStars(this, ${s.student_id}, 'skills', ${v})">★</span>`).join('')}
                                <input type="hidden" id="skills_${s.student_id}" value="${s.skills_stars || 0}">
                            </div>
                        </td>
                        <td class="px-4 py-6 text-center">
                            <div class="flex items-center justify-center rating-stars" dir="ltr">
                                ${[1, 2, 3].map(v => `<span class="cursor-pointer text-xl transition-colors ${s.behavior_stars >= v ? 'text-blue-500' : 'text-gray-300'}" onclick="setStars(this, ${s.student_id}, 'behavior', ${v})">★</span>`).join('')}
                                <input type="hidden" id="behavior_${s.student_id}" value="${s.behavior_stars || 0}">
                            </div>
                        </td>
                        <td class="px-4 py-6 text-center">
                            <div class="flex items-center justify-center rating-stars" dir="ltr">
                                ${[1, 2, 3].map(v => `<span class="cursor-pointer text-xl transition-colors ${s.participation_stars >= v ? 'text-purple-600' : 'text-gray-300'}" onclick="setStars(this, ${s.student_id}, 'participation', ${v})">★</span>`).join('')}
                                <input type="hidden" id="participation_${s.student_id}" value="${s.participation_stars || 0}">
                            </div>
                        </td>
                        ` : ''}
                    </tr>
                    `;
    }).join('')}
            </tbody>
        </table>
    </div>`;

    // Mobile View
    html += `
    <div class="lg:hidden space-y-4 mb-12">
        ${students.map((s, i) => {
        const st = s.status || 'present';
        return `
            <div class="bg-white rounded-[2rem] p-6 border border-gray-100 shadow-xl shadow-gray-100/30 att-row ${s.health_alerts > 0 ? 'ring-4 ring-red-50' : ''} active:scale-[0.98] transition-all" data-student-id="${s.student_id}">
                <div class="flex items-start justify-between mb-8">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-gray-50 flex items-center justify-center font-black text-gray-400 border border-gray-100 shadow-inner">${i + 1}</div>
                        <div>
                            <div class="font-black text-gray-800 text-base leading-tight">${esc(s.name)}</div>
                            <div class="flex items-center gap-2 mt-1">
                                <span class="text-[10px] text-gray-300 font-black font-mono">@ID_${s.student_id}</span>
                                ${s.health_alerts > 0 ? `<span class="px-2 py-0.5 bg-red-50 text-red-600 rounded-md text-[8px] font-black border border-red-100 animate-pulse">⚠️ تنبيه صحي</span>` : ''}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mobile: Attendance -->
                <div class="mb-4">
                    <p class="text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest">التحضير الرسمي</p>
                    <div class="grid grid-cols-3 gap-2 bg-gray-50 p-1.5 rounded-[1.5rem] border border-gray-100 shadow-inner">
                        <label class="cursor-pointer">
                            <input type="radio" name="m_att_${s.student_id}" value="present" ${st === 'present' ? 'checked' : ''} class="peer hidden" onchange="syncMobileRadio(${s.student_id}, 'present')" ${!canEdit() ? 'disabled' : ''}>
                            <div class="py-2.5 text-center rounded-2xl font-black text-[10px] peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:shadow-xl transition-all text-gray-400">حاضر</div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="m_att_${s.student_id}" value="absent" ${st === 'absent' ? 'checked' : ''} class="peer hidden" onchange="syncMobileRadio(${s.student_id}, 'absent')" ${!canEdit() ? 'disabled' : ''}>
                            <div class="py-2.5 text-center rounded-2xl font-black text-[10px] peer-checked:bg-red-600 peer-checked:text-white peer-checked:shadow-xl transition-all text-gray-400">غائب</div>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="m_att_${s.student_id}" value="late" ${st === 'late' ? 'checked' : ''} class="peer hidden" onchange="syncMobileRadio(${s.student_id}, 'late')" ${!canEdit() ? 'disabled' : ''}>
                            <div class="py-2.5 text-center rounded-2xl font-black text-[10px] peer-checked:bg-amber-500 peer-checked:text-white peer-checked:shadow-xl transition-all text-gray-400">متأخر</div>
                        </label>
                    </div>
                </div>

                <!-- Mobile: Uniform -->
                <div class="mb-4">
                    <p class="text-[10px] font-black text-gray-400 mb-2 uppercase tracking-widest">الزي الرياضي</p>
                    <div class="grid grid-cols-4 gap-2">
                        <label class="cursor-pointer text-center">
                            <input type="radio" name="m_uni_${s.student_id}" value="full" ${s.uniform_status === 'full' ? 'checked' : ''} class="peer hidden" onchange="syncMobileUniform(${s.student_id}, 'full')" ${!canEdit() ? 'disabled' : ''}>
                            <div class="py-2 rounded-xl border-2 border-emerald-100 bg-emerald-50 text-emerald-600 peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:border-emerald-600 transition-all font-bold text-lg">👕<div class="text-[9px] mt-1 font-black leading-none">كامل</div></div>
                        </label>
                        <label class="cursor-pointer text-center">
                            <input type="radio" name="m_uni_${s.student_id}" value="partial" ${s.uniform_status === 'partial' ? 'checked' : ''} class="peer hidden" onchange="syncMobileUniform(${s.student_id}, 'partial')" ${!canEdit() ? 'disabled' : ''}>
                            <div class="py-2 rounded-xl border-2 border-amber-100 bg-amber-50 text-amber-500 peer-checked:bg-amber-500 peer-checked:text-white peer-checked:border-amber-500 transition-all font-bold text-lg">👕<div class="text-[9px] mt-1 font-black leading-none">ناقص</div></div>
                        </label>
                        <label class="cursor-pointer text-center">
                            <input type="radio" name="m_uni_${s.student_id}" value="wrong" ${s.uniform_status === 'wrong' ? 'checked' : ''} class="peer hidden" onchange="syncMobileUniform(${s.student_id}, 'wrong')" ${!canEdit() ? 'disabled' : ''}>
                            <div class="py-2 rounded-xl border-2 border-red-100 bg-red-50 text-red-500 peer-checked:bg-red-500 peer-checked:text-white peer-checked:border-red-500 transition-all font-bold text-lg">👕<div class="text-[9px] mt-1 font-black leading-none">مخالف</div></div>
                        </label>
                        <label class="cursor-pointer text-center">
                            <input type="radio" name="m_uni_${s.student_id}" value="missing" ${s.uniform_status === 'missing' ? 'checked' : ''} class="peer hidden" onchange="syncMobileUniform(${s.student_id}, 'missing')" ${!canEdit() ? 'disabled' : ''}>
                            <div class="py-2 rounded-xl border-2 border-gray-200 bg-gray-100 text-gray-500 peer-checked:bg-gray-600 peer-checked:text-white peer-checked:border-gray-600 transition-all font-bold text-lg">❌<div class="text-[9px] mt-1 font-black leading-none">بدون</div></div>
                        </label>
                    </div>
                </div>

                <!-- Mobile: Stars -->
                ${hasFeature('behavior_analytics') ? `
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="bg-gray-50 rounded-xl p-3 border border-gray-100 flex flex-col items-center">
                        <span class="text-[10px] font-black text-gray-500 mb-1">المهارة البدنية</span>
                        <div class="rating-stars text-xl" dir="ltr">
                            ${[1, 2, 3].map(v => `<span class="cursor-pointer transition-colors ${s.skills_stars >= v ? 'text-amber-400' : 'text-gray-300'}" onclick="setStars(this, ${s.student_id}, 'skills', ${v}, true)">★</span>`).join('')}
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-3 border border-gray-100 flex flex-col items-center">
                        <span class="text-[10px] font-black text-gray-500 mb-1">السلوك الرياضي</span>
                        <div class="rating-stars text-xl" dir="ltr">
                            ${[1, 2, 3].map(v => `<span class="cursor-pointer transition-colors ${s.behavior_stars >= v ? 'text-blue-500' : 'text-gray-300'}" onclick="setStars(this, ${s.student_id}, 'behavior', ${v}, true)">★</span>`).join('')}
                        </div>
                    </div>
                    <div class="bg-gray-50 rounded-xl p-3 border border-gray-100 flex flex-col items-center">
                        <span class="text-[10px] font-black text-gray-500 mb-1">المشاركة الصفية</span>
                        <div class="rating-stars text-xl" dir="ltr">
                            ${[1, 2, 3].map(v => `<span class="cursor-pointer transition-colors ${s.participation_stars >= v ? 'text-purple-600' : 'text-gray-300'}" onclick="setStars(this, ${s.student_id}, 'participation', ${v}, true)">★</span>`).join('')}
                        </div>
                    </div>
                </div>
                ` : ''}
            </div>
            `;
    }).join('')}
    </div>`;

    al.innerHTML = html;
}

function syncMobileRadio(sid, val) {
    const desktopRadio = document.querySelector(`input[name="att_${sid}"][value="${val}"]`);
    if (desktopRadio) desktopRadio.checked = true;
}

function markAllPresent() {
    document.querySelectorAll('input[type="radio"][value="present"]').forEach(r => r.checked = true);
    showToast('تم تحديد جميع الطلاب كحضور', 'success');
}

async function saveAttendance() {
    const rows = document.querySelectorAll('.att-row[data-student-id]');
    const records = [];
    const processedIds = new Set();

    rows.forEach(row => {
        const sid = row.dataset.studentId;
        if (processedIds.has(sid)) return;

        // Check attendance status
        const attRadio = document.querySelector(`input[name="att_${sid}"]:checked`) ||
            document.querySelector(`input[name="m_att_${sid}"]:checked`);

        if (attRadio) {
            // Check uniform
            const uniRadio = document.querySelector(`input[name="uni_${sid}"]:checked`) ||
                document.querySelector(`input[name="m_uni_${sid}"]:checked`);

            // Check stars
            const skillsVal = document.getElementById(`skills_${sid}`) ? document.getElementById(`skills_${sid}`).value : 0;
            const behaviorVal = document.getElementById(`behavior_${sid}`) ? document.getElementById(`behavior_${sid}`).value : 0;
            const participationVal = document.getElementById(`participation_${sid}`) ? document.getElementById(`participation_${sid}`).value : 0;

            records.push({
                student_id: sid,
                status: attRadio.value,
                uniform_status: uniRadio ? uniRadio.value : null,
                skills_stars: skillsVal,
                behavior_stars: behaviorVal,
                participation_stars: participationVal
            });
            processedIds.add(sid);
        }
    });

    if (records.length === 0) {
        // Fix #2: 'warning' is not a valid toast type; use 'info' instead
        showToast('يرجى تحضير الطلاب أولاً', 'info');
        return;
    }

    const r = await API.post('attendance_save', { date: attFilter.date, records });
    if (r && r.success) {
        showToast(r.message);
        loadAttendanceList();
    } else if (r) {
        showToast(r.error, 'error');
    }
}

// Flexible Grading Sync Functions
function syncMobileUniform(sid, val) {
    const desktopRadio = document.querySelector(`input[name="uni_${sid}"][value="${val}"]`);
    if (desktopRadio) desktopRadio.checked = true;
}

function setStars(element, sid, type, val, isMobile = false) {
    if (!canEdit()) return;

    // Select the hidden input
    const input = document.getElementById(`${type}_${sid}`);
    if (input) {
        // Toggle logic: clicking same star turns it off
        if (parseInt(input.value) === val) {
            val = 0;
        }
        input.value = val;
    }

    // Update visual stars dynamically (find parent, update children)
    const container = element.parentElement;
    const allStars = container.querySelectorAll('span');
    allStars.forEach((star, idx) => {
        if (idx < val) {
            star.classList.remove('text-gray-300');
            if (type === 'skills') star.classList.add('text-amber-400');
            else if (type === 'behavior') star.classList.add('text-blue-500');
            else if (type === 'participation') star.classList.add('text-purple-600');
        } else {
            star.classList.add('text-gray-300');
            star.classList.remove('text-amber-400', 'text-blue-500', 'text-purple-600');
        }
    });

    // Also update the counterpart view (desktop vs mobile)
    const counterpartSelector = isMobile ? `input#${type}_${sid}` : `.lg\\:hidden input#${type}_${sid}`; // Basic workaround
    // Let's rely on re-rendering for cross-sync or we just mutate both on submit. 
    // Since IDs are unique across the page, one hidden input per ID is safer.
    // The current template generates ONE input per ID inside Desktop block, and mobile manipulates the Desktop ID.
    // Notice mobile template didn't render hidden inputs, so it edits the desktop one safely.
}
