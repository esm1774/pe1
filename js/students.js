/**
 * PE Smart School System - Students Page
 */

let studentFilter = { grade_id: '', class_id: '', search: '' };

async function renderStudents() {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = showLoading();

    const [gr, cl, st] = await Promise.all([
        API.get('grades'),
        API.get('classes', { grade_id: studentFilter.grade_id }),
        API.get('students', studentFilter)
    ]);

    if (!st || !st.success) {
        mc.innerHTML = `
        <div class="fade-in text-center py-20 bg-white rounded-[3rem] border border-gray-100 shadow-xl mx-4">
            <p class="text-6xl mb-6">⚠️</p>
            <p class="text-red-600 text-2xl font-black mb-2 tracking-tight">خطأ في تحميل بيانات الطلاب</p>
            <p class="text-gray-500 mb-8 font-bold">${esc(st?.error || 'تحقق من الاتصال بقاعدة البيانات')}</p>
            <div class="flex flex-col md:flex-row gap-4 justify-center px-8">
                <button onclick="renderStudents()" class="bg-emerald-600 text-white px-10 py-4 rounded-2xl font-black cursor-pointer hover:bg-emerald-700 shadow-xl shadow-emerald-100 transition active:scale-95">🔄 إعادة المحاولة</button>
                <a href="install.php" target="_blank" class="bg-gray-100 text-gray-600 px-10 py-4 rounded-2xl font-black hover:bg-gray-200 transition">🔧 تشغيل المثبّت</a>
            </div>
        </div>`;
        return;
    }

    const grades = gr?.data || [];
    const classes = cl?.data || [];
    const students = st?.data || [];

    mc.innerHTML = `
    <div class="fade-in max-w-full overflow-x-hidden">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8 px-1">
            <div>
                <h2 class="text-2xl md:text-4xl font-black text-gray-800 tracking-tight">👨‍🎓 إدارة الطلاب</h2>
                <div class="flex items-center gap-2 mt-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <p class="text-gray-400 font-bold tracking-tight text-[10px] md:text-base">${students.length} طالباً مسجلاً في النظام</p>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row gap-3">
                ${canEdit() ? `
                <button onclick="showStudentForm()" class="w-full md:w-auto bg-emerald-600 text-white px-6 py-3.5 rounded-2xl font-black hover:bg-emerald-700 transition shadow-lg shadow-emerald-100 cursor-pointer text-xs flex items-center justify-center gap-2">
                    <span class="text-lg">+</span> إضافة طالب جديد
                </button>
                <button onclick="showImportModal()" class="w-full md:w-auto bg-white text-emerald-600 border-2 border-emerald-50 px-6 py-3.5 rounded-2xl font-black hover:bg-emerald-50 transition cursor-pointer text-xs shadow-sm flex items-center justify-center gap-2">
                    📥 استيراد بيانات
                </button>
                ` : ''}
            </div>
        </div>

        <!-- Filters & Search -->
        <div class="bg-white rounded-[2rem] md:rounded-[2.5rem] p-4 md:p-6 mb-8 border border-gray-100 shadow-xl shadow-gray-100/50 mx-1">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-3">
                    <label class="block text-[8px] md:text-[10px] font-black text-gray-400 uppercase tracking-widest mr-1 mb-1.5">تصفية حسب الصف</label>
                    <select onchange="studentFilter.grade_id=this.value;studentFilter.class_id='';renderStudents()" class="w-full bg-gray-50 px-4 py-3.5 border-2 border-transparent focus:border-emerald-500 focus:bg-white focus:outline-none font-bold text-gray-700 rounded-xl text-sm appearance-none cursor-pointer transition-all">
                        <option value="">كل الصفوف الدراسية</option>
                        ${grades.map(g => `<option value="${g.id}" ${studentFilter.grade_id == g.id ? 'selected' : ''}>${esc(g.name)}</option>`).join('')}
                    </select>
                </div>
                <div class="md:col-span-3">
                    <label class="block text-[8px] md:text-[10px] font-black text-gray-400 uppercase tracking-widest mr-1 mb-1.5">تصفية حسب الفصل</label>
                    <select onchange="studentFilter.class_id=this.value;renderStudents()" class="w-full bg-gray-50 px-4 py-3.5 border-2 border-transparent focus:border-emerald-500 focus:bg-white focus:outline-none font-bold text-gray-700 rounded-xl text-sm appearance-none cursor-pointer transition-all">
                        <option value="">كل الفصول التعليمية</option>
                        ${classes.map(c => `<option value="${c.id}" ${studentFilter.class_id == c.id ? 'selected' : ''}>${esc(c.full_name || c.name)}</option>`).join('')}
                    </select>
                </div>
                <div class="md:col-span-6 relative">
                    <label class="block text-[8px] md:text-[10px] font-black text-gray-400 uppercase tracking-widest mr-1 mb-1.5">البحث الذكي</label>
                    <div class="relative group">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-lg group-focus-within:scale-110 transition-transform">🔍</span>
                        <input type="text" onkeyup="studentFilter.search=this.value;clearTimeout(window._st);window._st=setTimeout(()=>renderStudents(),400)" value="${studentFilter.search}" class="w-full bg-gray-50 pr-12 pl-4 py-3.5 border-2 border-transparent focus:border-emerald-500 focus:bg-white focus:outline-none font-bold rounded-xl text-sm transition-all shadow-inner" placeholder="ابحث باسم الطالب، رقم القيد...">
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 mb-6 no-print">
            <button onclick="downloadTemplate(false)" class="text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-emerald-600 transition flex items-center gap-2">📄 تحميل قالب Excel</button>
            ${students.length > 0 ? `<button onclick="downloadTemplate(true)" class="text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-emerald-600 transition flex items-center gap-2">📤 تصدير القائمة الحالية</button>` : ''}
        </div>

        <!-- Desktop View (Table) -->
        <div class="hidden lg:block bg-white rounded-[2.5rem] shadow-2xl shadow-gray-100/50 border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50/70 border-b border-gray-100">
                            <th class="px-6 py-5 text-right text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">#</th>
                            <th class="px-6 py-5 text-right text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">رقم القيد</th>
                            <th class="px-6 py-5 text-right text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">اسم الطالب</th>
                            <th class="px-6 py-5 text-right text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">الفصل الدراسي</th>
                            <th class="px-6 py-5 text-center text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">العمر</th>
                            <th class="px-6 py-5 text-center text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">الحالة</th>
                            <th class="px-6 py-5 text-center text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        ${students.map((s, i) => `
                        <tr class="hover:bg-emerald-50/30 transition-all group">
                            <td class="px-6 py-5 text-xs text-gray-400 font-bold">${i + 1}</td>
                            <td class="px-6 py-5 text-xs font-mono font-black text-gray-500">${esc(s.student_number)}</td>
                            <td class="px-6 py-5">
                                <div class="flex items-center gap-4">
                                    <div class="w-11 h-11 rounded-2xl bg-gray-100 flex items-center justify-center text-xl group-hover:bg-emerald-100 group-hover:rotate-6 transition-all shadow-inner">👦</div>
                                    <span class="font-black text-gray-800 text-sm group-hover:text-emerald-700 transition">${esc(s.name)}</span>
                                </div>
                            </td>
                            <td class="px-6 py-5"><span class="px-4 py-1.5 bg-emerald-50 text-emerald-700 rounded-xl text-[10px] font-black uppercase tracking-tight">${esc(s.full_class_name)}</span></td>
                            <td class="px-6 py-5 text-center text-sm font-black text-gray-600">${s.age || calcAge(s.date_of_birth)} سنة</td>
                            <td class="px-6 py-5 text-center">
                                ${s.health_alerts > 0
            ? `<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-red-50 text-red-600 rounded-full text-[10px] font-black border border-red-100 animate-pulse">⚠️ ${s.health_alerts} تنبيه</span>`
            : '<span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-black border border-emerald-100">● مستقر</span>'}
                            </td>
                            <td class="px-6 py-5 text-center">
                                <div class="flex items-center justify-center gap-2 opacity-0 group-hover:opacity-100 transition-all transform translate-x-4 group-hover:translate-x-0">
                                    <button onclick="window._profileStudentId=${s.id};navigateTo('studentProfile')" class="w-9 h-9 rounded-xl bg-white text-emerald-600 border border-emerald-100 flex items-center justify-center hover:bg-emerald-600 hover:text-white transition shadow-sm cursor-pointer" title="ملف الطالب">👤</button>
                                    ${canEdit() ? `
                                    <button onclick="showStudentForm(${s.id})" class="w-9 h-9 rounded-xl bg-white text-amber-500 border border-amber-100 flex items-center justify-center hover:bg-amber-500 hover:text-white transition shadow-sm cursor-pointer" title="تعديل">✏️</button>
                                    <button onclick="deleteStudent(${s.id})" class="w-9 h-9 rounded-xl bg-white text-red-500 border border-red-100 flex items-center justify-center hover:bg-red-500 hover:text-white transition shadow-sm cursor-pointer" title="حذف">🗑️</button>
                                    ` : ''}
                                </div>
                            </td>
                        </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mobile/Tablet View (Cards) -->
        <div class="lg:hidden grid grid-cols-1 md:grid-cols-2 gap-4 pb-10">
            ${students.map((s, i) => `
            <div class="bg-white rounded-[2rem] p-4 md:p-6 border border-gray-100 shadow-lg shadow-gray-100/30 relative group transition-all duration-300 active:scale-95 mx-1">
                <div class="flex items-start justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-[1.5rem] bg-gradient-to-br from-emerald-50 to-green-100 text-3xl flex items-center justify-center shadow-inner">👦</div>
                        <div>
                            <h3 class="font-black text-gray-800 text-base leading-tight">${esc(s.name)}</h3>
                            <p class="text-[10px] text-emerald-600 font-black mt-1 uppercase tracking-tight">${esc(s.full_class_name)}</p>
                            <p class="text-[9px] text-gray-300 font-black font-mono mt-0.5 tracking-[0.2em]">#${esc(s.student_number)}</p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 mb-6">
                    <div class="bg-gray-50/80 rounded-2xl p-3 text-center border border-gray-50">
                        <p class="text-[8px] text-gray-400 font-black uppercase mb-1 tracking-widest">العمر</p>
                        <p class="font-black text-gray-800 text-sm">${s.age || calcAge(s.date_of_birth)}</p>
                    </div>
                    <div class="bg-gray-50/80 rounded-2xl p-3 text-center border border-gray-50">
                        <p class="text-[8px] text-gray-400 font-black uppercase mb-1 tracking-widest">فصيلة الدم</p>
                        <p class="font-black text-emerald-700 text-sm">${s.blood_type || 'N/A'}</p>
                    </div>
                    <div class="bg-gray-50/80 rounded-2xl p-3 text-center border border-gray-50">
                        <p class="text-[8px] text-gray-400 font-black uppercase mb-1 tracking-widest">الحالة</p>
                        <p class="font-black ${s.health_alerts > 0 ? 'text-red-500' : 'text-emerald-500'} text-xs">${s.health_alerts > 0 ? '⚠️ تنبيه' : '✅ مستقر'}</p>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button onclick="window._profileStudentId=${s.id};navigateTo('studentProfile')" class="flex-1 py-4 bg-emerald-600 text-white rounded-[1.2rem] font-black text-xs hover:bg-emerald-700 transition shadow-lg shadow-emerald-100">استعراض الملف</button>
                    ${canEdit() ? `
                    <button onclick="showStudentForm(${s.id})" class="w-14 h-14 flex items-center justify-center bg-gray-50 text-amber-500 rounded-[1.2rem] hover:bg-amber-500 hover:text-white transition-all border border-gray-100 shadow-sm">✏️</button>
                    <button onclick="deleteStudent(${s.id})" class="w-14 h-14 flex items-center justify-center bg-gray-50 text-red-500 rounded-[1.2rem] hover:bg-red-500 hover:text-white transition-all border border-gray-100 shadow-sm">🗑️</button>
                    ` : ''}
                </div>
            </div>
            `).join('')}
        </div>

        ${students.length === 0 ? `
        <div class="text-center py-32 bg-white rounded-[3rem] border-4 border-dashed border-gray-50 shadow-inner">
            <div class="text-8xl mb-8 grayscale opacity-20 animate-pulse">🔎</div>
            <p class="text-gray-400 font-black text-2xl tracking-tight">لا يوجد نتائج تطابق بحثك</p>
            <p class="text-gray-300 font-bold mt-2">حاول تجربة كلمات بحث أخرى أو تغيير الفلاتر المختارة</p>
        </div>` : ''}
    </div>`;
}

// Student Form
async function showStudentForm(id = null) {
    const [gr, cl] = await Promise.all([
        API.get('grades'),
        API.get('classes')
    ]);

    const grades = gr?.data || [];
    const allClasses = cl?.data || [];

    let student = null;
    if (id) {
        const r = await API.get('students', { search: '' });
        student = (r?.data || []).find(s => s.id == id);
    }

    const selectedGrade = student ? student.grade_id : (grades[0]?.id || '');
    const filteredClasses = allClasses.filter(c => c.grade_id == selectedGrade);
    window._allClasses = allClasses;

    showModal(`
        <div class="p-6">
            <h3 class="text-xl font-bold mb-4">${student ? 'تعديل' : 'إضافة'} طالب</h3>
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">اسم الطالب *</label>
                        <input type="text" id="studentName" value="${student ? esc(student.name) : ''}" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">رقم الطالب *</label>
                        <input type="text" id="studentNumber" value="${student ? esc(student.student_number) : ''}" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">الصف</label>
                        <select id="studentGrade" onchange="updateStudentClasses()" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                            ${grades.map(g => `<option value="${g.id}" ${selectedGrade == g.id ? 'selected' : ''}>${esc(g.name)}</option>`).join('')}
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">الفصل</label>
                        <select id="studentClass" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                            ${filteredClasses.map(c => `<option value="${c.id}" ${student && student.class_id == c.id ? 'selected' : ''}>${esc(c.name)}</option>`).join('')}
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">تاريخ الميلاد</label>
                        <input type="date" id="studentDOB" value="${student?.date_of_birth || ''}" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">فصيلة الدم</label>
                        <select id="studentBlood" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                            <option value="">اختر</option>
                            ${BLOOD_TYPES.map(b => `<option value="${b}" ${student?.blood_type === b ? 'selected' : ''}>${b}</option>`).join('')}
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">رقم ولي الأمر</label>
                        <input type="tel" id="studentGuardian" value="${student?.guardian_phone || ''}" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none" placeholder="05XXXXXXXX">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">ملاحظات طبية</label>
                    <textarea id="studentMedical" rows="2" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">${student?.medical_notes || ''}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">كلمة مرور بوابة الطالب ${student ? '<span class="text-xs text-gray-400">(اتركها فارغة لعدم التغيير)</span>' : ''}</label>
                    <input type="password" id="studentPassword" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none" placeholder="${student ? '••••••••' : 'اسم الطالب هو رقم هويته'}">
                </div>
                <div class="flex gap-3 pt-2">
                    <button onclick="saveStudent(${id || 'null'})" class="flex-1 bg-green-600 text-white py-3 rounded-xl font-bold hover:bg-green-700 cursor-pointer">حفظ</button>
                    <button onclick="closeModal()" class="flex-1 bg-gray-200 text-gray-700 py-3 rounded-xl font-bold hover:bg-gray-300 cursor-pointer">إلغاء</button>
                </div>
            </div>
        </div>
    `);
}

function updateStudentClasses() {
    const gradeId = document.getElementById('studentGrade').value;
    const filtered = (window._allClasses || []).filter(c => c.grade_id == gradeId);
    document.getElementById('studentClass').innerHTML = filtered.map(c => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
}

async function saveStudent(id) {
    const data = {
        id,
        name: document.getElementById('studentName').value.trim(),
        student_number: document.getElementById('studentNumber').value.trim(),
        class_id: document.getElementById('studentClass').value,
        date_of_birth: document.getElementById('studentDOB').value,
        blood_type: document.getElementById('studentBlood').value,
        guardian_phone: document.getElementById('studentGuardian').value.trim(),
        medical_notes: document.getElementById('studentMedical').value.trim(),
        password: document.getElementById('studentPassword').value
    };

    if (!data.name || !data.student_number) {
        showToast('أكمل الحقول المطلوبة', 'error');
        return;
    }

    if (!data.class_id) {
        showToast('اختر الفصل', 'error');
        return;
    }

    const r = await API.post('student_save', data);
    if (r && r.success) {
        closeModal();
        showToast(r.message);
        renderStudents();
    } else {
        showToast(r?.error || 'خطأ في حفظ الطالب', 'error');
    }
}

async function deleteStudent(id) {
    if (!confirm('حذف الطالب؟')) return;
    const r = await API.post('student_delete', null, { id });
    if (r && r.success) {
        showToast(r.message);
        renderStudents();
    }
}

// ============================================================
// IMPORT / EXPORT STUDENTS
// ============================================================

/**
 * Download CSV Template
 */
function downloadTemplate(withData = false) {
    const classId = studentFilter.class_id || '';
    let url = `api.php?action=students_template&with_data=${withData ? '1' : '0'}&format=xlsx`;
    if (classId) url += `&class_id=${classId}`;

    // Open download in new tab
    window.open(url, '_blank');

    if (!withData) {
        showToast('جاري تحميل القالب... افتحه في Excel واملأ البيانات', 'info');
    } else {
        showToast('جاري تصدير بيانات الطلاب...', 'info');
    }
}

/**
 * Show Import Modal
 */
async function showImportModal() {
    const cl = await API.get('classes');
    const classes = cl?.data || [];

    showModal(`
        <div class="p-6">
            <h3 class="text-xl font-bold mb-4">📥 استيراد الطلاب من ملف Excel/CSV</h3>
            
            <!-- Instructions -->
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-4">
                <h4 class="font-bold text-blue-800 mb-2">📋 التعليمات:</h4>
                <ol class="text-sm text-blue-700 space-y-1 list-decimal mr-5">
                    <li>حمّل <button onclick="downloadTemplate(false)" class="text-blue-600 underline font-bold cursor-pointer">القالب الفارغ</button> أولاً</li>
                    <li>افتحه في Excel واملأ بيانات الطلاب</li>
                    <li>ارفع الملف هنا بصيغة <strong>Excel (xlsx)</strong> أو <strong>CSV</strong></li>
                </ol>
            </div>
            
            <!-- Required Columns -->
            <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 mb-4">
                <h4 class="font-bold text-gray-700 mb-2">📊 الأعمدة المطلوبة:</h4>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div class="flex items-center gap-1">
                        <span class="text-red-500 font-bold">*</span>
                        <span class="font-semibold">اسم الطالب</span>
                        <span class="text-gray-400">(إجباري)</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="text-red-500 font-bold">*</span>
                        <span class="font-semibold">رقم الطالب</span>
                        <span class="text-gray-400">(إجباري)</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="text-blue-500">○</span>
                        <span>رمز الصف</span>
                        <span class="text-gray-400">(1, 2, 3)</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="text-blue-500">○</span>
                        <span>رقم الفصل</span>
                        <span class="text-gray-400">(1, 2, 3)</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="text-blue-500">○</span>
                        <span>تاريخ الميلاد</span>
                        <span class="text-gray-400">(YYYY-MM-DD)</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="text-blue-500">○</span>
                        <span>فصيلة الدم</span>
                        <span class="text-gray-400">(A+, B-, O+...)</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="text-blue-500">○</span>
                        <span>رقم ولي الأمر</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="text-blue-500">○</span>
                        <span>ملاحظات طبية</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="text-blue-500">○</span>
                        <span class="font-bold">كلمة المرور</span>
                        <span class="text-gray-400 text-[10px]">(اختياري - الافتراضي: رقم الطالب)</span>
                    </div>
                </div>
            </div>
            
            <!-- Default Class (if not in file) -->
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">الفصل الافتراضي <span class="text-gray-400 font-normal">(إذا لم يُحدد في الملف)</span></label>
                <select id="importDefaultClass" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                    <option value="">اختر الفصل</option>
                    ${classes.map(c => `<option value="${c.id}">${esc(c.full_name || c.name)}</option>`).join('')}
                </select>
            </div>
            
            <!-- File Upload -->
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-700 mb-1">اختر ملف CSV</label>
                <div id="dropZone" class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-green-400 hover:bg-green-50 transition cursor-pointer"
                     onclick="document.getElementById('importFile').click()"
                     ondragover="event.preventDefault();this.classList.add('border-green-500','bg-green-50')"
                     ondragleave="this.classList.remove('border-green-500','bg-green-50')"
                     ondrop="event.preventDefault();this.classList.remove('border-green-500','bg-green-50');handleFileDrop(event)">
                    <span class="text-4xl block mb-2">📂</span>
                    <p class="text-gray-500 font-semibold">اسحب الملف هنا أو اضغط لاختياره</p>
                    <p class="text-gray-400 text-sm mt-1">Excel (xlsx), CSV أو TXT</p>
                    <p id="selectedFileName" class="text-green-600 font-bold mt-2 hidden"></p>
                </div>
                <input type="file" id="importFile" accept=".csv,.txt,.xlsx" class="hidden" onchange="handleFileSelect(this)">
            </div>
            
            <!-- Import Result -->
            <div id="importResult" class="hidden mb-4"></div>
            
            <!-- Buttons -->
            <div class="flex gap-3 pt-2">
                <button onclick="executeImport()" id="importBtn" class="flex-1 bg-green-600 text-white py-3 rounded-xl font-bold hover:bg-green-700 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                    📥 استيراد
                </button>
                <button onclick="closeModal()" class="flex-1 bg-gray-200 text-gray-700 py-3 rounded-xl font-bold hover:bg-gray-300 cursor-pointer">إلغاء</button>
            </div>
        </div>
    `);
}

/**
 * Handle file selection
 */
function handleFileSelect(input) {
    const file = input.files[0];
    if (file) {
        showSelectedFile(file);
    }
}

/**
 * Handle file drop
 */
function handleFileDrop(event) {
    const file = event.dataTransfer.files[0];
    if (file) {
        // Set file to input
        const input = document.getElementById('importFile');
        const dt = new DataTransfer();
        dt.items.add(file);
        input.files = dt.files;
        showSelectedFile(file);
    }
}

/**
 * Show selected file name
 */
function showSelectedFile(file) {
    const nameEl = document.getElementById('selectedFileName');
    const btn = document.getElementById('importBtn');
    const ext = file.name.split('.').pop().toLowerCase();

    if (!['csv', 'txt', 'xlsx'].includes(ext)) {
        nameEl.textContent = '❌ صيغة غير مدعومة! استخدم XLSX أو CSV';
        nameEl.className = 'text-red-600 font-bold mt-2';
        nameEl.classList.remove('hidden');
        btn.disabled = true;
        return;
    }

    const sizeMB = (file.size / 1024 / 1024).toFixed(2);
    nameEl.textContent = `✅ ${file.name} (${sizeMB} MB)`;
    nameEl.className = 'text-green-600 font-bold mt-2';
    nameEl.classList.remove('hidden');
    btn.disabled = false;
}

/**
 * Execute Import
 */
async function executeImport() {
    const fileInput = document.getElementById('importFile');
    const defaultClass = document.getElementById('importDefaultClass')?.value || '';
    const resultDiv = document.getElementById('importResult');
    const btn = document.getElementById('importBtn');

    if (!fileInput.files[0]) {
        showToast('اختر ملف أولاً', 'error');
        return;
    }

    // Check if class is selected when no grade/section in file
    if (!defaultClass) {
        if (!confirm('لم تختر فصلاً افتراضياً. إذا كان الملف لا يحتوي على أعمدة "رمز الصف" و"رقم الفصل"، سيتم تخطي الطلاب. هل تريد المتابعة؟')) {
            return;
        }
    }

    // Show loading
    btn.disabled = true;
    btn.innerHTML = '<span class="inline-block animate-spin">⏳</span> جاري الاستيراد...';
    resultDiv.classList.add('hidden');

    // Build FormData
    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    if (defaultClass) formData.append('default_class_id', defaultClass);

    try {
        const response = await fetch(`api.php?action=students_import`, {
            method: 'POST',
            body: formData
        });

        const r = await response.json();

        if (r.success) {
            const d = r.data;
            let html = `
                <div class="bg-green-50 border border-green-200 rounded-xl p-4">
                    <h4 class="font-bold text-green-800 mb-2">✅ ${esc(r.message)}</h4>
                    <div class="grid grid-cols-3 gap-3 text-center mb-3">
                        <div class="bg-white rounded-lg p-2">
                            <p class="text-2xl font-bold text-green-600">${d.imported}</p>
                            <p class="text-xs text-gray-500">طالب جديد</p>
                        </div>
                        <div class="bg-white rounded-lg p-2">
                            <p class="text-2xl font-bold text-blue-600">${d.updated}</p>
                            <p class="text-xs text-gray-500">تم تحديثه</p>
                        </div>
                        <div class="bg-white rounded-lg p-2">
                            <p class="text-2xl font-bold text-red-600">${d.skipped}</p>
                            <p class="text-xs text-gray-500">تم تخطيه</p>
                        </div>
                    </div>`;

            if (d.errors && d.errors.length > 0) {
                html += `
                    <div class="mt-2">
                        <p class="text-sm font-semibold text-red-700 mb-1">⚠️ أخطاء:</p>
                        <div class="max-h-32 overflow-y-auto bg-white rounded-lg p-2 text-xs text-red-600 space-y-1">
                            ${d.errors.map(e => `<p>• ${esc(e)}</p>`).join('')}
                        </div>
                    </div>`;
            }

            html += '</div>';
            resultDiv.innerHTML = html;
            resultDiv.classList.remove('hidden');

            showToast(r.message);

            // Refresh students list after 1 second
            setTimeout(() => renderStudents(), 1000);
        } else {
            resultDiv.innerHTML = `
                <div class="bg-red-50 border border-red-200 rounded-xl p-4">
                    <p class="font-bold text-red-800">❌ ${esc(r.error)}</p>
                </div>`;
            resultDiv.classList.remove('hidden');
            showToast(r.error, 'error');
        }
    } catch (e) {
        resultDiv.innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-xl p-4">
                <p class="font-bold text-red-800">❌ خطأ في الاتصال: ${esc(e.message)}</p>
            </div>`;
        resultDiv.classList.remove('hidden');
        showToast('خطأ في الاتصال', 'error');
    }

    // Reset button
    btn.disabled = false;
    btn.innerHTML = '📥 استيراد';
}
