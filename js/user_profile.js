/**
 * PE Smart School System - User Profile (CV) Page
 */

async function renderUserProfilePage() {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = showLoading();

    const r = await API.get('get_my_profile');
    if (!r || !r.success) {
        mc.innerHTML = '<p class="text-red-500 text-center py-8">فشل تحميل الملف الشخصي</p>';
        return;
    }

    const u = r.data;
    const roleNames = { admin: '👑 مدير', teacher: '👨‍🏫 معلم', viewer: '👁️ مشاهد', supervisor: '🔍 مشرف/موجه', parent: '👪 ولي أمر', student: '🎓 طالب' };

    mc.innerHTML = `
    <div class="fade-in max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-gray-800">👤 ملفي الشخصي (السيرة الذاتية)</h2>
            <button onclick="showEditProfileModal(${JSON.stringify(u).replace(/"/g, '&quot;')})" class="bg-blue-600 text-white px-6 py-2 rounded-xl font-bold hover:bg-blue-700 shadow-md cursor-pointer">✏️ تعديل البيانات</button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Left Column: Basic Info Card -->
            <div class="md:col-span-1 space-y-6">
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 text-center group">
                    <div class="relative w-24 h-24 mx-auto mb-4">
                        <div class="w-full h-full bg-gradient-to-br from-blue-500 to-indigo-600 rounded-3xl flex items-center justify-center text-4xl text-white shadow-xl overflow-hidden">
                            ${u.photo_url ? `<img src="${u.photo_url}" class="w-full h-full object-cover">` : u.name.charAt(0)}
                        </div>
                        <button onclick="showUploadPhotoModal()" class="absolute -bottom-2 -right-2 bg-white text-blue-600 w-10 h-10 rounded-2xl shadow-lg border border-gray-100 flex items-center justify-center hover:scale-110 transition cursor-pointer" title="تغيير الصورة">📷</button>
                    </div>
                    <h3 class="text-xl font-bold text-gray-800">${esc(u.name)}</h3>
                    <p class="text-blue-600 font-semibold mb-2">${roleNames[u.role] || u.role}</p>
                    <div class="flex items-center justify-center gap-2 text-sm text-gray-500">
                        <span>قيد الخدمة منذ ${new Date(u.created_at).getFullYear()}</span>
                    </div>
                </div>

                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
                    <h4 class="font-bold text-gray-800 mb-4 pb-2 border-b border-gray-50">📞 معلومات الاتصال</h4>
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <span class="text-xl">📧</span>
                            <div>
                                <p class="text-xs text-gray-400 uppercase font-bold">البريد الإلكتروني</p>
                                <p class="text-sm font-semibold text-gray-700">${esc(u.email) || 'غير محدد'}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xl">📱</span>
                            <div>
                                <p class="text-xs text-gray-400 uppercase font-bold">رقم الهاتف</p>
                                <p class="text-sm font-semibold text-gray-700">${esc(u.phone) || 'غير محدد'}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Professional Details / Student Details -->
            <div class="md:col-span-2 space-y-6">
                ${u.role === 'student' ? `
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8 text-center py-12">
                    <div class="text-6xl mb-6">🎓</div>
                    <h4 class="text-2xl font-black text-gray-800 mb-2">مرحباً بك في ملفك الشخصي</h4>
                    <p class="text-gray-500 font-bold mb-8 text-lg">هنا يمكنك إدارة بياناتك الأساسية وكلمة المرور الخاصة بك.</p>
                    <div class="inline-flex items-center gap-3 px-6 py-3 bg-blue-50 text-blue-700 rounded-2xl font-black text-sm">
                        <span>رقم الطالب:</span>
                        <span class="font-mono tracking-wider">${esc(u.username)}</span>
                    </div>
                </div>
                ` : `
                <!-- Professional Summary -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-8">
                    <h4 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <span class="text-2xl text-indigo-500">📝</span> نبذة شخصية
                    </h4>
                    <p class="text-gray-600 leading-relaxed italic">
                        ${u.bio ? esc(u.bio).replace(/\n/g, '<br>') : 'لا توجد نبذة شخصية مضافة حالياً. أخبرنا المزيد عن مسيرتك المهنية!'}
                    </p>
                </div>

                <!-- Expertise Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="bg-indigo-50 rounded-3xl p-6 border border-indigo-100">
                        <p class="text-xs text-indigo-400 uppercase font-bold mb-1">🎓 المؤهل العلمي</p>
                        <p class="text-lg font-bold text-indigo-900">${esc(u.education) || 'غير محدد'}</p>
                    </div>
                    <div class="bg-emerald-50 rounded-3xl p-6 border border-emerald-100">
                        <p class="text-xs text-emerald-400 uppercase font-bold mb-1">🎯 التخصص</p>
                        <p class="text-lg font-bold text-emerald-900">${esc(u.specialization) || 'غير محدد'}</p>
                    </div>
                    <div class="bg-orange-50 rounded-3xl p-6 border border-orange-100">
                        <p class="text-xs text-orange-400 uppercase font-bold mb-1">⏳ سنوات الخبرة</p>
                        <p class="text-lg font-bold text-orange-900">${u.experience_years ? u.experience_years + ' سنوات' : 'غير محدد'}</p>
                    </div>
                    <div class="bg-purple-50 rounded-3xl p-6 border border-purple-100">
                        <p class="text-xs text-purple-400 uppercase font-bold mb-1">🎂 تاريخ الميلاد</p>
                        <p class="text-lg font-bold text-purple-900">${u.birth_date || 'غير محدد'}</p>
                    </div>
                </div>
                `}
            </div>
        </div>
    </div>
    `;
}

async function showEditProfileModal(u) {
    showModal(`
        <div class="p-8">
            <h3 class="text-2xl font-bold mb-6 text-gray-800 flex items-center gap-2">
                ✏️ تعديل الملف الشخصي
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-1">الاسم بالكامل *</label>
                    <input type="text" id="profName" value="${esc(u.name)}" class="w-full px-4 py-3 border-2 border-gray-100 rounded-2xl focus:border-blue-500 focus:outline-none bg-gray-50">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">البريد الإلكتروني</label>
                    <input type="email" id="profEmail" value="${esc(u.email || '')}" class="w-full px-4 py-3 border-2 border-gray-100 rounded-2xl focus:border-blue-500 focus:outline-none bg-gray-50">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">رقم الهاتف</label>
                    <input type="text" id="profPhone" value="${esc(u.phone || '')}" class="w-full px-4 py-3 border-2 border-gray-100 rounded-2xl focus:border-blue-500 focus:outline-none bg-gray-50">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">تاريخ الميلاد</label>
                    <input type="date" id="profBirth" value="${u.birth_date || ''}" class="w-full px-4 py-3 border-2 border-gray-100 rounded-2xl focus:border-blue-500 focus:outline-none bg-gray-50">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">كلمة المرور الجديدة</label>
                    <input type="password" id="profPass" class="w-full px-4 py-3 border-2 border-gray-100 rounded-2xl focus:border-blue-500 focus:outline-none bg-gray-50" placeholder="اتركها فارغة لعدم التغيير">
                </div>

                ${u.role !== 'student' ? `
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">المؤهل العلمي</label>
                    <input type="text" id="profEducation" value="${esc(u.education || '')}" class="w-full px-4 py-3 border-2 border-gray-100 rounded-2xl focus:border-blue-500 focus:outline-none bg-gray-50" placeholder="مثال: بكالوريوس تربية رياضية">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">التخصص</label>
                    <input type="text" id="profSpec" value="${esc(u.specialization || '')}" class="w-full px-4 py-3 border-2 border-gray-100 rounded-2xl focus:border-blue-500 focus:outline-none bg-gray-50">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">سنوات الخبرة</label>
                    <input type="number" id="profExp" value="${u.experience_years || ''}" class="w-full px-4 py-3 border-2 border-gray-100 rounded-2xl focus:border-blue-500 focus:outline-none bg-gray-50">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-700 mb-1">نبذة شخصية</label>
                    <textarea id="profBio" rows="4" class="w-full px-4 py-3 border-2 border-gray-100 rounded-2xl focus:border-blue-500 focus:outline-none bg-gray-50" placeholder="اكتب نبذة ملخصة عن نفسك وخبراتك...">${esc(u.bio || '')}</textarea>
                </div>
                ` : ''}
            </div>
            <div class="flex gap-4 mt-8">
                <button onclick="handleUpdateProfile()" class="flex-1 bg-gradient-to-r from-blue-600 to-indigo-700 text-white py-4 rounded-2xl font-bold hover:shadow-lg transform transition active:scale-95 cursor-pointer shadow-md">💾 حفظ البيانات</button>
                <button onclick="closeModal()" class="flex-1 bg-gray-100 text-gray-600 py-4 rounded-2xl font-bold hover:bg-gray-200 cursor-pointer">إلغاء</button>
            </div>
        </div>
    `);
}

async function handleUpdateProfile() {
    const data = {
        name: document.getElementById('profName').value.trim(),
        email: document.getElementById('profEmail').value.trim(),
        phone: document.getElementById('profPhone').value.trim(),
        birth_date: document.getElementById('profBirth').value,
        password: document.getElementById('profPass').value,
        bio: document.getElementById('profBio') ? document.getElementById('profBio').value.trim() : null,
        education: document.getElementById('profEducation') ? document.getElementById('profEducation').value.trim() : null,
        specialization: document.getElementById('profSpec') ? document.getElementById('profSpec').value.trim() : null,
        experience_years: document.getElementById('profExp') ? document.getElementById('profExp').value : null
    };

    if (!data.name) {
        showToast('الاسم مطلوب', 'error');
        return;
    }

    const r = await API.post('update_my_profile', data);
    if (r && r.success) {
        showToast(r.message);
        closeModal();
        renderUserProfilePage();
        // Update name in header if it exists
        const headerName = document.getElementById('currentUserName');
        if (headerName) headerName.textContent = data.name;
    } else if (r) {
        showToast(r.error, 'error');
    }
}

function showUploadPhotoModal() {
    showModal(`
        <div class="p-8 text-center">
            <h3 class="text-2xl font-bold mb-6 text-gray-800">📸 تحديث الصورة الشخصية</h3>
            
            <div id="photoPreview" class="w-40 h-40 bg-gray-100 rounded-3xl mx-auto mb-8 flex items-center justify-center text-5xl text-gray-300 border-2 border-dashed border-gray-200 overflow-hidden">
                🖼️
            </div>

            <label class="block mb-6">
                <span class="sr-only">اختر صورة</span>
                <input type="file" id="photoInput" accept="image/*" onchange="previewProfilePhoto(this)" 
                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-3 file:px-6 file:rounded-2xl file:border-0 file:text-sm file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer">
            </label>

            <div class="flex gap-4">
                <button onclick="handleUploadPhoto()" class="flex-1 bg-blue-600 text-white py-4 rounded-2xl font-bold hover:bg-blue-700 shadow-md">📤 رفع الصورة</button>
                <button onclick="closeModal()" class="flex-1 bg-gray-100 text-gray-600 py-4 rounded-2xl font-bold hover:bg-gray-200">إلغاء</button>
            </div>
        </div>
    `);
}

function previewProfilePhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
            const preview = document.getElementById('photoPreview');
            preview.innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
            preview.classList.remove('text-5xl', 'text-gray-300');
        }
        reader.readAsDataURL(input.files[0]);
    }
}

async function handleUploadPhoto() {
    const fileInput = document.getElementById('photoInput');
    if (!fileInput.files || !fileInput.files[0]) {
        showToast('يرجى اختيار صورة أولاً', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('photo', fileInput.files[0]);

    // Use raw fetch for FormData, ensuring action is in query string
    showLoading();
    try {
        const response = await fetch(`${API.base}?action=upload_profile_photo`, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.cookie.split('; ').find(row => row.startsWith('XSRF-TOKEN='))?.split('=')[1] || ''
            }
        });

        const text = await response.text();
        let r;
        try {
            r = JSON.parse(text);
        } catch (je) {
            console.error('Server response was not JSON:', text);
            showToast('خطأ في استجابة الخادم أثناء الرفع', 'error');
            return;
        }

        if (r.success) {
            showToast(r.message);
            // Update global currentUser state
            if (currentUser) currentUser.photo_url = r.data.photo_url;

            // Update top header avatar (initEl from common.js logic)
            const initEl = document.getElementById('userInitialDisplay');
            if (initEl) {
                initEl.innerHTML = `<img src="${r.data.photo_url}" class="w-full h-full object-cover rounded-2xl user-avatar-img">`;
                initEl.classList.remove('bg-green-100', 'text-green-700');
                initEl.classList.add('p-0');
            }

            closeModal();
            renderUserProfilePage();
        } else {
            showToast(r.error, 'error');
        }
    } catch (e) {
        showToast('حدث خطأ أثناء الرفع: ' + e.message, 'error');
    }
}

/**
 * Forced Password Change Logic
 * Shows a modal that cannot be closed until password is changed
 */
function showForcePasswordChangeModal() {
    // Show modal with no close button and click-outside disabled
    showModal(`
        <div class="p-8 text-center">
            <div class="w-20 h-20 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center text-4xl mx-auto mb-6">
                🔐
            </div>
            <h3 class="text-2xl font-black text-gray-800 mb-2">تحديث كلمة المرور</h3>
            <p class="text-gray-500 font-bold mb-8">لدواعي أمنية، يجب عليك تغيير كلمة المرور الافتراضية قبل متابعة استخدام المنصة.</p>
            
            <div class="space-y-4 text-right">
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">كلمة المرور الجديدة</label>
                    <input type="password" id="forceNewPass" class="w-full px-4 py-4 border-2 border-gray-100 rounded-2xl focus:border-blue-500 focus:outline-none bg-gray-50 text-center text-lg" placeholder="6 أحرف على الأقل">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1">تأكيد كلمة المرور</label>
                    <input type="password" id="forceConfirmPass" class="w-full px-4 py-4 border-2 border-gray-100 rounded-2xl focus:border-blue-500 focus:outline-none bg-gray-50 text-center text-lg">
                </div>
            </div>

            <button onclick="handleForceUpdatePassword()" class="w-full bg-gradient-to-r from-blue-600 to-indigo-700 text-white py-4 rounded-2xl font-bold mt-8 hover:shadow-lg transform transition active:scale-95 cursor-pointer shadow-md">
                تحديث والدخول للمنصة 🚀
            </button>
        </div>
    `);

    // Disable closing the modal
    const modalContainer = document.getElementById('modalContainer');
    if (modalContainer) {
        // Remove existing click listener if any or just overwrite with a no-op
        modalContainer.onclick = (e) => {
            if (e.target === modalContainer) {
                showToast('يجب تغيير كلمة المرور للمتابعة', 'warning');
            }
        };
    }
}

async function handleForceUpdatePassword() {
    const pass = document.getElementById('forceNewPass').value;
    const confirm = document.getElementById('forceConfirmPass').value;

    if (!pass || pass.length < 6) {
        showToast('كلمة المرور يجب أن تكون 6 أحرف على الأقل', 'error');
        return;
    }

    if (pass !== confirm) {
        showToast('كلمات المرور غير متطابقة', 'error');
        return;
    }

    // Reuse profile update endpoint but only for password
    const data = { password: pass };

    showLoading();
    const r = await API.post('update_my_profile', data);
    if (r && r.success) {
        showToast('تم تحديث كلمة المرور بنجاح! 🎉');
        if (currentUser) currentUser.must_change_password = 0;

        // Re-enable modal closing
        const modalContainer = document.getElementById('modalContainer');
        if (modalContainer) modalContainer.onclick = null;

        closeModal();
    } else {
        showToast(r ? r.error : 'فشل التحديث', 'error');
    }
}
