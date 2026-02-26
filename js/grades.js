/**
 * PE Smart School System - Grades & Classes Page
 */

async function renderGrades() {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = showLoading();

    const r = await API.get('grades');
    if (!r || !r.success) return;

    const grades = r.data;

    mc.innerHTML = `
    <div class="fade-in">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">🏫 الصفوف والفصول</h2>
            </div>
            ${canEdit() ? `
            <div class="flex gap-2">
                <button onclick="showGradeForm()" class="bg-emerald-50 text-emerald-700 border-2 border-emerald-100 px-4 py-2 rounded-xl font-black hover:bg-emerald-100 transition active:scale-95 flex items-center gap-2">
                    <span>➕</span> صف جديد
                </button>
                <button onclick="showClassForm()" class="bg-emerald-600 text-white px-4 py-2 rounded-xl font-black hover:bg-emerald-700 shadow-xl shadow-emerald-100 transition active:scale-95 flex items-center gap-2">
                    <span>➕</span> فصل جديد
                </button>
            </div>
            ` : ''}
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            ${grades.map(g => `
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden card-hover">
                    <div class="bg-gradient-to-l from-green-500 to-emerald-600 p-4 text-white flex justify-between items-center">
                        <div>
                            <h3 class="font-bold text-lg">${esc(g.name)}</h3>
                            <p class="opacity-80 text-sm">${g.class_count} فصل • ${g.student_count} طالب</p>
                        </div>
                        ${canEdit() ? `
                        <div class="flex gap-1">
                            <button onclick="showGradeForm(${g.id},'${esc(g.name)}','${esc(g.code)}')" class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center hover:bg-white/30 cursor-pointer">✏️</button>
                            <button onclick="deleteGrade(${g.id})" class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center hover:bg-white/30 cursor-pointer">🗑️</button>
                        </div>
                        ` : ''}
                    </div>
                    <div class="p-4 space-y-2">
                        ${(g.classes || []).map(c => `
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-xl">
                                <div>
                                    <span class="font-semibold text-gray-700">فصل ${esc(c.section)}</span>
                                    <span class="text-gray-400 text-sm mr-2">(${c.student_count} طالب)</span>
                                </div>
                                ${canEdit() ? `
                                <div class="flex gap-1">
                                    <button onclick="showClassForm(${c.id},${c.grade_id},'${esc(c.section)}')" class="text-emerald-500 hover:text-emerald-700 cursor-pointer text-sm font-bold">✏️</button>
                                    <button onclick="deleteClass(${c.id})" class="text-red-500 hover:text-red-700 cursor-pointer text-sm">🗑️</button>
                                </div>
                                ` : ''}
                            </div>
                        `).join('')}
                        ${(!g.classes || g.classes.length === 0) ? '<p class="text-gray-400 text-center py-3 text-sm">لا توجد فصول</p>' : ''}
                    </div>
                </div>
            `).join('')}
        </div>
    </div>`;
}

// Grade Form
function showGradeForm(id = null, name = '', code = '') {
    showModal(`
        <div class="p-6">
            <h3 class="text-xl font-bold mb-4">${id ? 'تعديل' : 'إضافة'} صف</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">اسم الصف</label>
                    <input type="text" id="gradeName" value="${name}" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">الرمز</label>
                    <input type="text" id="gradeCode" value="${code}" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                </div>
                <div class="flex gap-3 pt-2">
                    <button onclick="saveGrade(${id || 'null'})" class="flex-1 bg-green-600 text-white py-3 rounded-xl font-bold hover:bg-green-700 cursor-pointer">حفظ</button>
                    <button onclick="closeModal()" class="flex-1 bg-gray-200 text-gray-700 py-3 rounded-xl font-bold hover:bg-gray-300 cursor-pointer">إلغاء</button>
                </div>
            </div>
        </div>
    `);
}

async function saveGrade(id) {
    const name = document.getElementById('gradeName').value.trim();
    const code = document.getElementById('gradeCode').value.trim();

    if (!name) {
        showToast('أدخل اسم الصف', 'error');
        return;
    }

    const r = await API.post('grade_save', { id, name, code });
    if (r && r.success) {
        closeModal();
        showToast(r.message);
        renderGrades();
    }
}

async function deleteGrade(id) {
    if (!confirm('حذف الصف؟')) return;
    const r = await API.post('grade_delete', null, { id });
    if (r && r.success) {
        showToast(r.message);
        renderGrades();
    }
}

// Class Form
async function showClassForm(id = null, gradeId = null, section = '') {
    const gr = await API.get('grades');
    if (!gr || !gr.success) return;

    showModal(`
        <div class="p-6">
            <h3 class="text-xl font-bold mb-4">${id ? 'تعديل' : 'إضافة'} فصل</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">الصف</label>
                    <select id="classGrade" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                        ${gr.data.map(g => `<option value="${g.id}" ${gradeId == g.id ? 'selected' : ''}>${esc(g.name)}</option>`).join('')}
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">رقم القسم</label>
                    <input type="text" id="classSection" value="${section}" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                </div>
                <div class="flex gap-3 pt-2">
                    <button onclick="saveClass(${id || 'null'})" class="flex-1 bg-green-600 text-white py-3 rounded-xl font-bold hover:bg-green-700 cursor-pointer">حفظ</button>
                    <button onclick="closeModal()" class="flex-1 bg-gray-200 text-gray-700 py-3 rounded-xl font-bold hover:bg-gray-300 cursor-pointer">إلغاء</button>
                </div>
            </div>
        </div>
    `);
}

async function saveClass(id) {
    const gradeId = document.getElementById('classGrade').value;
    const section = document.getElementById('classSection').value.trim();

    if (!section) {
        showToast('أدخل رقم القسم', 'error');
        return;
    }

    const r = await API.post('class_save', { id, grade_id: gradeId, section });
    if (r && r.success) {
        closeModal();
        showToast(r.message);
        renderGrades();
    }
}

async function deleteClass(id) {
    if (!confirm('حذف الفصل؟')) return;
    const r = await API.post('class_delete', null, { id });
    if (r && r.success) {
        showToast(r.message);
        renderGrades();
    }
}
