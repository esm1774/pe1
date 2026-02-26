/**
 * PE Smart School System - Users Management Page
 */

async function renderUsers() {
    if (!isAdmin()) {
        navigateTo('dashboard');
        return;
    }

    const mc = document.getElementById('mainContent');
    mc.innerHTML = showLoading();

    const r = await API.get('users');
    if (!r || !r.success) return;

    const users = r.data;
    const roleNames = { admin: '👑 مدير', teacher: '👨‍🏫 معلم', viewer: '👁️ مشاهد', supervisor: '🔍 مشرف/موجه' };
    const roleColors = { admin: 'bg-red-100 text-red-700', teacher: 'bg-blue-100 text-blue-700', viewer: 'bg-gray-100 text-gray-700', supervisor: 'bg-purple-100 text-purple-700' };

    mc.innerHTML = `
    <div class="fade-in px-4 md:px-0">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <h2 class="text-3xl font-black text-gray-800">👥 إدارة فريق العمل</h2>
                <p class="text-gray-500 font-bold mt-1">إضافة المعلمين والمدراء وتحديد الصلاحيات ونطاقات العمل</p>
            </div>
            <button onclick="showUserForm()" class="w-full md:w-auto bg-green-600 text-white px-8 py-3 rounded-2xl font-black hover:bg-green-700 transition shadow-xl shadow-green-100 flex items-center justify-center gap-2">
                <span>➕</span> مستخدم جديد
            </button>
        </div>

        <!-- Desktop View (Table) -->
        <div class="hidden lg:block bg-white rounded-3xl shadow-xl shadow-gray-100/50 border border-gray-100 overflow-hidden">
            <table class="w-full text-right border-collapse">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-8 py-5 text-xs font-black text-gray-400 uppercase tracking-widest">الموظف</th>
                        <th class="px-8 py-5 text-xs font-black text-gray-400 uppercase tracking-widest">بيانات الدخول</th>
                        <th class="px-8 py-5 text-xs font-black text-gray-400 uppercase tracking-widest">الصلاحية</th>
                        <th class="px-8 py-5 text-xs font-black text-gray-400 uppercase tracking-widest text-center">الإجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    ${users.map(u => `
                    <tr class="hover:bg-gray-50/50 transition group">
                        <td class="px-8 py-5">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-2xl bg-gray-50 flex items-center justify-center text-2xl group-hover:scale-110 transition duration-300">👤</div>
                                <div>
                                    <div class="font-black text-gray-800">${esc(u.name)}</div>
                                    <div class="text-[10px] text-gray-400 font-bold uppercase">ID: #${u.id}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-8 py-5">
                            <div class="text-sm font-black text-emerald-600">@${esc(u.username)}</div>
                        </td>
                        <td class="px-8 py-5">
                            <span class="inline-flex px-4 py-1 rounded-full text-[10px] font-black uppercase border ${roleColors[u.role].replace('bg-', 'border-').replace('text-', 'text-')} ${roleColors[u.role]}">
                                ${roleNames[u.role]}
                            </span>
                        </td>
                        <td class="px-8 py-5 text-center">
                            <div class="flex items-center justify-center gap-2 opacity-0 group-hover:opacity-100 transition">
                                ${u.role === 'teacher' ? `
                                    <button onclick="showTeacherAssignments(${u.id})" class="w-10 h-10 flex items-center justify-center bg-green-50 text-green-600 rounded-xl hover:bg-green-600 hover:text-white transition" title="توزيع الفصول">📚</button>
                                ` : ''}
                                <button onclick="showUserForm(${u.id},'${esc(u.name)}','${esc(u.username)}','${u.role}')" class="w-10 h-10 flex items-center justify-center bg-blue-50 text-blue-600 rounded-xl hover:bg-blue-600 hover:text-white transition" title="تعديل">✏️</button>
                                ${u.id != 1 ? `<button onclick="deleteUser(${u.id})" class="w-10 h-10 flex items-center justify-center bg-red-50 text-red-600 rounded-xl hover:bg-red-600 hover:text-white transition" title="حذف">🗑️</button>` : ''}
                            </div>
                        </td>
                    </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>

        <!-- Mobile/Tablet View (Cards) -->
        <div class="lg:hidden grid grid-cols-1 md:grid-cols-2 gap-4 mb-20">
            ${users.map(u => `
            <div class="bg-white rounded-[2.5rem] p-6 border border-gray-100 shadow-sm relative overflow-hidden group active:scale-95 transition-transform">
                <div class="absolute top-0 left-0 w-2 h-full ${u.role === 'admin' ? 'bg-red-500' : u.role === 'teacher' ? 'bg-blue-500' : 'bg-gray-500'} opacity-20"></div>
                
                <div class="flex items-start justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 rounded-2xl bg-gray-50 flex items-center justify-center text-3xl">👤</div>
                        <div>
                            <h3 class="font-black text-gray-800 leading-tight">${esc(u.name)}</h3>
                            <p class="text-xs text-emerald-600 font-bold">@${esc(u.username)}</p>
                        </div>
                    </div>
                    <span class="px-3 py-1 rounded-full text-[9px] font-black uppercase ${roleColors[u.role]}">${roleNames[u.role].split(' ')[1]}</span>
                </div>

                <div class="flex gap-2">
                    ${u.role === 'teacher' ? `
                        <button onclick="showTeacherAssignments(${u.id})" class="flex-1 py-4 bg-green-50 text-green-700 rounded-2xl font-black text-xs hover:bg-green-600 hover:text-white transition flex items-center justify-center gap-2">
                            <span>📚</span> الفصول
                        </button>
                    ` : ''}
                    <button onclick="showUserForm(${u.id},'${esc(u.name)}','${esc(u.username)}','${u.role}')" class="${u.role === 'teacher' ? 'w-14' : 'flex-1'} h-14 flex items-center justify-center bg-gray-50 text-blue-600 rounded-2xl hover:bg-blue-600 hover:text-white transition">✏️</button>
                    ${u.id != 1 ? `
                        <button onclick="deleteUser(${u.id})" class="w-14 h-14 flex items-center justify-center bg-gray-50 text-red-600 rounded-2xl hover:bg-red-600 hover:text-white transition">🗑️</button>
                    ` : ''}
                </div>
            </div>
            `).join('')}
        </div>
    </div>`;
}

// ============================================================
// TEACHER CLASS ASSIGNMENTS UI
// ============================================================

async function showTeacherAssignments(teacherId) {
    showModal(showLoading('جاري تحميل التعيينات...'));

    // Fetch current assignments and all available classes
    const r = await API.get('teacher_assignments');
    if (!r || !r.success) {
        showToast('فشل تحميل البيانات', 'error');
        closeModal();
        return;
    }

    const { teachers, all_classes } = r.data;
    const teacher = teachers.find(t => t.id == teacherId);
    if (!teacher) {
        showToast('المعلم غير موجود', 'error');
        closeModal();
        return;
    }

    renderTeacherAssignmentsModal(teacher, all_classes);
}

function renderTeacherAssignmentsModal(teacher, allClasses) {
    const today = new Date().toISOString().split('T')[0];

    showModal(`
        <div class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-xl font-bold">📚 توزيع الفصول: ${esc(teacher.name)}</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600">✕</button>
            </div>

            <!-- Current Assignments -->
            <div class="mb-6">
                <h4 class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-2">الفصول الحالية</h4>
                <div class="bg-gray-50 rounded-xl p-2 border border-gray-100 min-h-[100px]">
                    ${teacher.classes && teacher.classes.length > 0 ? `
                        <div class="grid gap-2">
                            ${teacher.classes.map(c => {
        const isExpired = c.expires_at && c.expires_at < today;
        return `
                                <div class="flex items-center justify-between bg-white p-3 rounded-lg shadow-sm border ${isExpired ? 'border-red-200 bg-red-50' : 'border-gray-200'}">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-gray-800">${esc(c.full_name)}</span>
                                        ${c.is_temporary ? `
                                            <span class="badge ${isExpired ? 'bg-red-500 text-white' : 'bg-orange-100 text-orange-700'} text-[10px]">
                                                ${isExpired ? 'منتهي' : 'مؤقت'}
                                            </span>
                                        ` : '<span class="badge bg-green-100 text-green-700 text-[10px]">دائم</span>'}
                                        ${c.expires_at ? `<span class="text-[10px] text-gray-500">ينتهي: ${c.expires_at}</span>` : ''}
                                    </div>
                                    <button onclick="unassignClass(${teacher.id}, ${c.id})" class="text-red-400 hover:text-red-600 cursor-pointer">🗑️</button>
                                </div>
                                `;
    }).join('')}
                        </div>
                    ` : '<p class="text-gray-400 text-center py-8">لا توجد فصول مرتبطة حالياً</p>'}
                </div>
            </div>

            <!-- Add Assignment Form -->
            <div class="bg-green-50 rounded-2xl p-4 border border-green-100">
                <h4 class="font-bold text-green-800 mb-3">➕ إضافة فصل جديد</h4>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">اختر الفصل</label>
                        <select id="assignClassId" class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                            <option value="">-- اختر --</option>
                            ${allClasses.map(c => `<option value="${c.id}">${esc(c.full_name)}</option>`).join('')}
                        </select>
                    </div>
                    
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex-1">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" id="assignIsTemp" class="w-5 h-5 accent-green-600" onchange="document.getElementById('assignExpiryBox').classList.toggle('hidden', !this.checked)">
                                <span class="text-sm font-semibold text-gray-700">تعيين مؤقت؟</span>
                            </label>
                        </div>
                        <div id="assignExpiryBox" class="hidden flex-1">
                            <label class="block text-sm font-semibold text-gray-700 mb-1">تاريخ الانتهاء</label>
                            <input type="date" id="assignExpiresAt" class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none" min="${today}">
                        </div>
                    </div>

                    <button onclick="handleAssignClass(${teacher.id})" class="w-full bg-green-600 text-white py-3 rounded-xl font-bold hover:bg-green-700 cursor-pointer shadow-md">تأكيد التعيين</button>
                </div>
            </div>
        </div>
    `);
}

async function handleAssignClass(teacherId) {
    const data = {
        teacher_id: teacherId,
        class_id: document.getElementById('assignClassId').value,
        is_temporary: document.getElementById('assignIsTemp').checked ? 1 : 0,
        expires_at: document.getElementById('assignExpiresAt').value
    };

    if (!data.class_id) {
        showToast('يرجى اختيار الفصل', 'error');
        return;
    }

    if (data.is_temporary && !data.expires_at) {
        showToast('يرجى تحديد تاريخ الانتهاء للتعيين المؤقت', 'error');
        return;
    }

    const r = await API.post('assign_teacher_class', data);
    if (r && r.success) {
        showToast(r.message);
        // Refresh the modal
        showTeacherAssignments(teacherId);
    } else if (r) {
        showToast(r.error, 'error');
    }
}

async function unassignClass(teacherId, classId) {
    if (!confirm('هل تريد إلغاء تعيين هذا الفصل للمعلم؟')) return;

    const r = await API.post('unassign_teacher_class', { teacher_id: teacherId, class_id: classId });
    if (r && r.success) {
        showToast(r.message);
        showTeacherAssignments(teacherId);
    } else if (r) {
        showToast(r.error, 'error');
    }
}


function showUserForm(id = null, name = '', username = '', role = 'teacher') {
    showModal(`
        <div class="p-6">
            <h3 class="text-xl font-bold mb-4">${id ? 'تعديل' : 'إضافة'} مستخدم</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">الاسم</label>
                    <input type="text" id="userName" value="${name}" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">اسم المستخدم</label>
                    <input type="text" id="userUsername" value="${username}" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">${id ? 'كلمة مرور جديدة (اختياري)' : 'كلمة المرور'}</label>
                    <input type="password" id="userPassword" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">الدور</label>
                    <select id="userRole" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                        <option value="admin" ${role === 'admin' ? 'selected' : ''}>مدير</option>
                        <option value="teacher" ${role === 'teacher' ? 'selected' : ''}>معلم</option>
                        <option value="supervisor" ${role === 'supervisor' ? 'selected' : ''}>مشرف/موجه</option>
                        <option value="viewer" ${role === 'viewer' ? 'selected' : ''}>مشاهد</option>
                    </select>
                </div>
                <div class="flex gap-3 pt-2">
                    <button onclick="saveUser(${id || 'null'})" class="flex-1 bg-green-600 text-white py-3 rounded-xl font-bold hover:bg-green-700 cursor-pointer">حفظ</button>
                    <button onclick="closeModal()" class="flex-1 bg-gray-200 text-gray-700 py-3 rounded-xl font-bold hover:bg-gray-300 cursor-pointer">إلغاء</button>
                </div>
            </div>
        </div>
    `);
}

async function saveUser(id) {
    const data = {
        id,
        name: document.getElementById('userName').value.trim(),
        username: document.getElementById('userUsername').value.trim(),
        password: document.getElementById('userPassword').value,
        role: document.getElementById('userRole').value
    };

    if (!data.name || !data.username) {
        showToast('أكمل الحقول', 'error');
        return;
    }

    const r = await API.post('user_save', data);
    if (r && r.success) {
        closeModal();
        showToast(r.message);
        renderUsers();
    } else if (r) {
        showToast(r.error, 'error');
    }
}

async function deleteUser(id) {
    if (!confirm('حذف المستخدم؟')) return;
    const r = await API.post('user_delete', null, { id });
    if (r && r.success) {
        showToast(r.message);
        renderUsers();
    }
}
