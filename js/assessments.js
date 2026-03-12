/**
 * PE Smart School - Assessments Module
 */

async function renderAssessments() {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = showLoading();

    const resClasses = await API.get('classes'); // Using valid classes action
    if (!resClasses || !resClasses.success) {
        mc.innerHTML = `<div class="p-12 text-center text-red-500 font-bold">فشل تحميل الفصول</div>`;
        return;
    }

    const classes = resClasses.data;
    const defaultClassId = classes.length > 0 ? classes[0].id : 0;

    mc.innerHTML = `
    <div class="fade-in">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h2 class="text-3xl font-black text-gray-800 flex items-center gap-3">
                    <span class="w-12 h-12 bg-emerald-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-emerald-100">
                        📝
                    </span>
                    الاختبارات والأبحاث
                </h2>
                <p class="text-gray-500 mt-2 font-bold">رصد درجات الاختبارات القصيرة، المشاريع، والاختبارات النهائية.</p>
            </div>
            
            <div class="flex items-center gap-3 bg-white p-2 rounded-2xl shadow-sm border border-gray-100">
                <select id="assessmentClassSelect" onchange="loadAssessments()" class="bg-gray-50 border-none rounded-xl px-4 py-2.5 font-bold text-gray-700 outline-none focus:ring-2 focus:ring-emerald-500 transition cursor-pointer">
                    <option value="">اختر الفصل</option>
                    ${classes.map(c => `<option value="${c.id}">${esc(c.full_name || c.name)}</option>`).join('')}
                </select>
                <button onclick="saveAssessmentsScores()" class="bg-emerald-600 text-white px-6 py-2.5 rounded-xl font-bold hover:bg-emerald-700 transition shadow-lg shadow-emerald-100 flex items-center gap-2">
                    <span>💾</span> حفظ الدرجات
                </button>
            </div>
        </div>

        <div id="assessmentsContainer" class="bg-white rounded-[2rem] shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-12 text-center text-gray-400 font-bold">يرجى اختيار الفصل لتحميل الطلاب...</div>
        </div>
    </div>`;

    if (defaultClassId) loadAssessments();
}

let CURRENT_MAX_SCORES = { quiz: 10, project: 10, final_exam: 10 };

async function loadAssessments() {
    const classId = document.getElementById('assessmentClassSelect').value;
    if (!classId) return;
    const container = document.getElementById('assessmentsContainer');
    container.innerHTML = showLoading();

    const res = await API.get('get_assessments', { class_id: classId });
    if (!res || !res.success) {
        container.innerHTML = `<div class="p-12 text-center text-red-500 font-bold">${res?.message || 'فشل تحميل البيانات'}</div>`;
        return;
    }

    const students = res.data.students;
    const max = res.data.max_scores;
    CURRENT_MAX_SCORES = max;

    if (students.length === 0) {
        container.innerHTML = `<div class="p-12 text-center text-gray-400 font-bold">لا يوجد طلاب في هذا الفصل.</div>`;
        return;
    }

    let html = `
    <div class="overflow-x-auto">
        <table class="w-full border-collapse">
            <thead>
                <tr class="bg-gray-50/50 border-b border-gray-100">
                    <th class="px-6 py-5 text-right text-[10px] font-black text-gray-400 uppercase tracking-widest w-16">#</th>
                    <th class="px-6 py-5 text-right text-[10px] font-black text-gray-400 uppercase tracking-widest">اسم الطالب</th>
                    <th class="px-4 py-5 text-center text-[10px] font-black text-gray-400 uppercase tracking-widest w-32 text-emerald-600">اختبار قصير (${max.quiz})</th>
                    <th class="px-4 py-5 text-center text-[10px] font-black text-gray-400 uppercase tracking-widest w-32 text-blue-600">مشروع/بحث (${max.project})</th>
                    <th class="px-4 py-5 text-center text-[10px] font-black text-gray-400 uppercase tracking-widest w-40 text-emerald-700">اختبار نهائي (${max.final_exam})</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">`;

    students.forEach((s, idx) => {
        html += `
                <tr class="hover:bg-gray-50/50 transition-colors group">
                    <td class="px-6 py-4 text-sm font-black text-gray-300">${idx + 1}</td>
                    <td class="px-6 py-4">
                        <div class="font-bold text-gray-800">${esc(s.name)}</div>
                        <div class="text-[10px] text-gray-400 font-black tracking-widest">${esc(s.student_number)}</div>
                    </td>
                    <td class="px-4 py-4 text-center">
                        <input type="number" step="0.5" min="0" max="${max.quiz}" value="${s.quiz || 0}" 
                               class="score-input w-20 text-center bg-gray-50 border-2 border-transparent rounded-xl px-2 py-2 font-black text-emerald-600 focus:border-emerald-500 focus:bg-white transition outline-none"
                               data-sid="${s.student_id}" data-type="quiz">
                    </td>
                    <td class="px-4 py-4 text-center">
                        <input type="number" step="0.5" min="0" max="${max.project}" value="${s.project || 0}" 
                               class="score-input w-20 text-center bg-gray-50 border-2 border-transparent rounded-xl px-2 py-2 font-black text-blue-600 focus:border-blue-500 focus:bg-white transition outline-none"
                               data-sid="${s.student_id}" data-type="project">
                    </td>
                    <td class="px-4 py-4 text-center">
                        <input type="number" step="0.5" min="0" max="${max.final_exam}" value="${s.final_exam || 0}" 
                               class="score-input w-24 text-center bg-gray-50 border-2 border-transparent rounded-xl px-2 py-2 font-black text-emerald-700 focus:border-emerald-700 focus:bg-white transition outline-none"
                               data-sid="${s.student_id}" data-type="final_exam">
                    </td>
                </tr>`;
    });

    html += `
            </tbody>
        </table>
    </div>`;

    container.innerHTML = html;
}

async function saveAssessmentsScores() {
    const inputs = document.querySelectorAll('.score-input');
    const scoresMap = {};
    let hasError = false;

    inputs.forEach(input => {
        const sid = input.dataset.sid;
        const type = input.dataset.type;
        const val = parseFloat(input.value) || 0;
        const maxVal = CURRENT_MAX_SCORES[type] || 10;

        if (val > maxVal) {
            input.classList.add('border-red-500');
            hasError = true;
        } else {
            input.classList.remove('border-red-500');
        }

        if (!scoresMap[sid]) scoresMap[sid] = { student_id: sid };
        scoresMap[sid][type] = val;
    });

    if (hasError) {
        return showToast('بعض الدرجات تتجاوز الحد الأقصى المسموح به', 'error');
    }

    const scores = Object.values(scoresMap);

    showToast('جاري حفظ الدرجات...', 'info');

    const res = await API.post('save_assessments', { scores: scores });
    if (res && res.success) {
        showToast(res.message, 'success');
    } else {
        showToast(res?.message || 'فشل حفظ الدرجات', 'error');
    }
}
