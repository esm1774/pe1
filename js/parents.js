/**
 * PE Smart School System - Parent Management JS
 */

async function renderParents() {
    if (!isAdmin()) {
        navigateTo('dashboard');
        return;
    }

    const mc = document.getElementById('mainContent');
    mc.innerHTML = showLoading();

    const r = await API.get('parents_list');
    if (!r || !r.success) return;

    const parents = r.data;

    mc.innerHTML = `
    <div class="fade-in px-4 md:px-0">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h2 class="text-2xl md:text-3xl font-black text-gray-800">👨‍👩‍👦 أولياء الأمور</h2>
                <p class="text-gray-500 font-bold tracking-tight">إدارة حسابات أولياء الأمور وربطهم بالطلاب (${parents.length} حساب)</p>
            </div>
            <button onclick="showParentForm()" class="w-full md:w-auto bg-green-600 text-white px-8 py-3 rounded-2xl font-black hover:bg-green-700 transition shadow-xl shadow-green-100 flex items-center justify-center gap-2">
                <span>➕</span> ولي أمر جديد
            </button>
        </div>

        <!-- Desktop View (Table) -->
        <div class="hidden lg:block bg-white rounded-3xl shadow-xl shadow-gray-100/50 border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-right border-collapse">
                    <thead>
                        <tr class="bg-gray-50/50 border-b border-gray-100">
                            <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase tracking-widest">المستخدم</th>
                            <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase tracking-widest">بيانات الاتصال</th>
                            <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase tracking-widest text-center">الأبناء</th>
                            <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase tracking-widest text-center">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        ${parents.map(p => `
                        <tr class="hover:bg-green-50/30 transition group">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center text-xl group-hover:bg-green-100 transition">👤</div>
                                    <div>
                                        <div class="font-black text-gray-800">${esc(p.name)}</div>
                                        <div class="text-[10px] text-emerald-600 font-bold uppercase tracking-tighter">@${esc(p.username)}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm font-bold text-gray-700 font-mono">${esc(p.phone) || '-'}</div>
                                <div class="text-[10px] text-gray-400 font-bold">${esc(p.email) || '-'}</div>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <span class="inline-flex items-center justify-center px-4 py-1 rounded-full bg-blue-50 text-blue-700 font-black text-xs">
                                    ${p.students_count} أبناء
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <div class="flex items-center justify-center gap-2 opacity-0 group-hover:opacity-100 transition">
                                    <button onclick="showParentLinks(${p.id}, '${esc(p.name)}')" class="w-10 h-10 flex items-center justify-center bg-teal-50 text-teal-600 rounded-xl hover:bg-teal-600 hover:text-white transition" title="إدارة الأبناء">🔗</button>
                                    <button onclick="showParentForm(${p.id}, '${esc(p.name)}', '${esc(p.username)}', '${esc(p.email)}', '${esc(p.phone)}')" class="w-10 h-10 flex items-center justify-center bg-blue-50 text-blue-600 rounded-xl hover:bg-blue-600 hover:text-white transition" title="تعديل">✏️</button>
                                    <button onclick="deleteParent(${p.id})" class="w-10 h-10 flex items-center justify-center bg-red-50 text-red-600 rounded-xl hover:bg-red-600 hover:text-white transition" title="حذف">🗑️</button>
                                </div>
                            </td>
                        </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mobile/Tablet View (Cards) -->
        <div class="lg:hidden grid grid-cols-1 md:grid-cols-2 gap-4">
            ${parents.map(p => `
            <div class="bg-white rounded-[2.5rem] p-6 border border-gray-100 shadow-sm relative group active:scale-95 transition-transform overflow-hidden">
                <div class="absolute top-0 left-0 w-2 h-full bg-green-500 opacity-20"></div>
                
                <div class="flex items-start justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-gray-50 to-gray-100 text-3xl flex items-center justify-center shadow-inner">👤</div>
                        <div>
                            <h3 class="font-black text-gray-800 text-lg leading-tight">${esc(p.name)}</h3>
                            <p class="text-xs text-emerald-600 font-bold tracking-tighter">@${esc(p.username)}</p>
                        </div>
                    </div>
                    <div class="text-left">
                         <span class="inline-flex items-center justify-center px-3 py-1 rounded-full bg-blue-50 text-blue-700 font-black text-[10px]">
                            ${p.students_count} أبناء
                        </span>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-6">
                    <div class="bg-gray-50/50 rounded-2xl p-3 border border-gray-50">
                        <p class="text-[9px] text-gray-400 font-black uppercase mb-1">رقم الجوال</p>
                        <p class="font-black text-gray-700 text-xs font-mono">${esc(p.phone) || '-'}</p>
                    </div>
                    <div class="bg-gray-50/50 rounded-2xl p-3 border border-gray-50">
                        <p class="text-[9px] text-gray-400 font-black uppercase mb-1">تاريخ التسجيل</p>
                        <p class="font-black text-gray-700 text-xs">${String(p.created_at).split(' ')[0]}</p>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button onclick="showParentLinks(${p.id}, '${esc(p.name)}')" class="flex-1 py-4 bg-emerald-50 text-emerald-700 rounded-2xl font-black text-xs hover:bg-emerald-600 hover:text-white transition flex items-center justify-center gap-2">
                        <span>🔗</span> إدارة الأبناء
                    </button>
                    <button onclick="showParentForm(${p.id}, '${esc(p.name)}', '${esc(p.username)}', '${esc(p.email)}', '${esc(p.phone)}')" class="w-14 h-14 flex items-center justify-center bg-gray-50 text-blue-600 rounded-2xl hover:bg-blue-600 hover:text-white transition">✏️</button>
                    <button onclick="deleteParent(${p.id})" class="w-14 h-14 flex items-center justify-center bg-gray-50 text-red-600 rounded-2xl hover:bg-red-600 hover:text-white transition">🗑️</button>
                </div>
            </div>
            `).join('')}
        </div>

        ${parents.length === 0 ? `
        <div class="text-center py-20 bg-white rounded-3xl border border-dashed border-gray-200">
            <p class="text-6xl mb-4 grayscale opacity-20">👨‍👩‍👦</p>
            <p class="text-gray-400 font-bold">لا يوجد أولياء أمور مسجلين حالياً</p>
        </div>` : ''}
    </div>`;
}

function showParentForm(id = null, name = '', username = '', email = '', phone = '') {
    showModal(`
    <div class="p-6">
        <h3 class="text-xl font-bold text-gray-800 mb-6">${id ? '✏️ تعديل بيانات ولي الأمر' : '➕ إضافة ولي أمر جديد'}</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="col-span-1 md:col-span-2">
                <label class="block text-sm font-bold text-gray-700 mb-1">الاسم الكامل</label>
                <input type="text" id="parentName" value="${esc(name)}" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none transition">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">اسم المستخدم (للدخول)</label>
                <input type="text" id="parentUsername" value="${esc(username)}" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none transition">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">${id ? 'كلمة مرور جديدة (اختياري)' : 'كلمة المرور'}</label>
                <input type="password" id="parentPassword" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none transition">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">رقم الجوال</label>
                <input type="text" id="parentPhone" value="${esc(phone)}" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none transition" dir="ltr">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1">البريد الإلكتروني</label>
                <input type="email" id="parentEmail" value="${esc(email)}" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none transition" dir="ltr">
            </div>
        </div>
        <div class="mt-8 flex gap-3">
            <button onclick="handleSaveParent(${id})" class="flex-1 bg-green-600 text-white py-3 rounded-xl font-bold hover:bg-green-700 transition shadow-md shadow-green-100">حفظ البيانات</button>
            <button onclick="closeModal()" class="px-8 bg-gray-100 text-gray-600 py-3 rounded-xl font-bold hover:bg-gray-200 transition">إلغاء</button>
        </div>
    </div>`);
}

async function handleSaveParent(id) {
    const data = {
        id,
        name: document.getElementById('parentName').value.trim(),
        username: document.getElementById('parentUsername').value.trim(),
        password: document.getElementById('parentPassword').value,
        email: document.getElementById('parentEmail').value.trim(),
        phone: document.getElementById('parentPhone').value.trim()
    };

    if (!data.name || !data.username) {
        showToast('يرجى إكمال الحقول الإجبارية', 'error');
        return;
    }

    const r = await API.post('parent_save', data);
    if (r && r.success) {
        showToast(r.message);
        closeModal();
        renderParents();
    } else if (r) {
        showToast(r.error, 'error');
    }
}

async function deleteParent(id) {
    if (!confirm('هل أنت متأكد من تعطيل هذا الحساب؟')) return;
    const r = await API.post('parent_delete', null, { id });
    if (r && r.success) {
        showToast(r.message);
        renderParents();
    }
}

async function showParentLinks(parentId, parentName) {
    showModal(`
    <div class="p-6">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-gray-800">🔗 أبناء ولي الأمر: ${esc(parentName)}</h3>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-xl">✕</button>
        </div>
        
        <div class="space-y-6">
            <!-- Add New Student Search -->
            <div class="bg-indigo-50 p-4 rounded-2xl border border-indigo-100">
                <label class="block text-sm font-bold text-indigo-800 mb-2 font-cairo">➕ ربط ابن جديد</label>
                <div class="relative">
                    <input type="text" id="studentSearch" placeholder="ابحث باسم الطالب أو رقم الهوية..." 
                        class="w-full px-4 py-3 pr-10 border-2 border-indigo-200 rounded-xl focus:border-indigo-500 focus:outline-none transition"
                        onkeyup="searchStudentsForLinking(${parentId})">
                    <span class="absolute right-3 top-3.5 opacity-50">🔍</span>
                </div>
                <div id="searchResults" class="mt-2 space-y-1 max-h-48 overflow-y-auto hidden"></div>
            </div>

            <!-- Current Links -->
            <div>
                <h4 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-3">الأبناء المرتبطين حالياً</h4>
                <div id="linkedStudentsList" class="space-y-2">
                    <div class="text-center py-4">${showLoading()}</div>
                </div>
            </div>
        </div>
    </div>`);

    loadLinkedStudents(parentId);
}

async function loadLinkedStudents(parentId) {
    const list = document.getElementById('linkedStudentsList');
    const r = await API.get('parent_links', { parent_id: parentId });
    if (!r || !r.success) return;

    if (r.data.length === 0) {
        list.innerHTML = '<div class="text-center py-6 bg-gray-50 rounded-xl text-gray-400 text-sm">لا يوجد أبناء مرتبطين حالياً</div>';
        return;
    }

    list.innerHTML = r.data.map(s => `
        <div class="flex items-center justify-between p-3 bg-white border border-gray-100 rounded-xl shadow-sm">
            <div>
                <div class="font-bold text-gray-800 text-sm">${esc(s.name)}</div>
                <div class="text-[10px] text-gray-500">${esc(s.class_name)} | ${esc(s.student_number)}</div>
            </div>
            <button onclick="unlinkStudent(${parentId}, ${s.id}, '${esc(s.name)}')" class="text-red-400 hover:text-red-600 p-1">✕</button>
        </div>
    `).join('');
}

let searchTimeout = null;
async function searchStudentsForLinking(parentId) {
    const q = document.getElementById('studentSearch').value.trim();
    const results = document.getElementById('searchResults');

    if (q.length < 2) {
        results.classList.add('hidden');
        return;
    }

    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(async () => {
        const r = await API.get('parent_search_students', { q });
        if (r && r.data) {
            results.classList.remove('hidden');
            if (r.data.length === 0) {
                results.innerHTML = '<div class="p-4 text-center text-xs text-gray-500">لا توجد نتائج</div>';
            } else {
                results.innerHTML = r.data.map(s => `
                    <div class="flex items-center justify-between p-3 hover:bg-indigo-100 cursor-pointer rounded-lg transition" onclick="linkStudent(${parentId}, ${s.id})">
                        <div class="text-xs">
                            <span class="font-bold text-gray-800">${esc(s.name)}</span>
                            <span class="text-gray-500 ml-2">(${esc(s.class_name)})</span>
                        </div>
                        <span class="text-indigo-600 font-bold">+</span>
                    </div>
                `).join('');
            }
        }
    }, 300);
}

async function linkStudent(parentId, studentId) {
    const r = await API.post('parent_link_student', { parent_id: parentId, student_id: studentId });
    if (r && r.success) {
        showToast(r.message);
        document.getElementById('studentSearch').value = '';
        document.getElementById('searchResults').classList.add('hidden');
        loadLinkedStudents(parentId);
        // Refresh parents list to update child count
        renderParents();
    }
}

async function unlinkStudent(parentId, studentId, studentName) {
    if (!confirm(`هل أنت متأكد من فك ربط الطالب (${studentName})؟`)) return;
    const r = await API.post('parent_unlink_student', { parent_id: parentId, student_id: studentId });
    if (r && r.success) {
        showToast(r.message);
        loadLinkedStudents(parentId);
        renderParents();
    }
}
