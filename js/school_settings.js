/**
 * PE Smart School - School Settings Module
 */

async function renderSchoolSettings() {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = showLoading();

    const res = await API.get('get_school_info');
    if (!res || !res.success) return;

    const s = res.data;

    mc.innerHTML = `
    <div class="fade-in max-w-4xl mx-auto px-4 md:px-0">
        <div class="mb-8">
            <h2 class="text-3xl font-black text-gray-800 flex items-center gap-3">
                <span class="w-12 h-12 bg-emerald-600 rounded-2xl flex items-center justify-center text-white shadow-lg shadow-emerald-100">
                    ⚙️
                </span>
                إعدادات المدرسة والهوية
            </h2>
            <p class="text-gray-500 mt-2 font-bold">تحكم في اسم وشعار ونظام مدرستك الدراسي من مكان واحد.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Logo Upload -->
            <div class="md:col-span-1">
                <div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 p-6 text-center">
                    <h3 class="font-black text-gray-800 mb-4 text-sm">شعار المدرسة</h3>
                    <div class="relative group mx-auto w-32 h-32 mb-4">
                        <div id="logoPreview" class="w-full h-full rounded-3xl border-2 border-dashed border-gray-200 flex items-center justify-center overflow-hidden bg-gray-50">
                            ${s.logo_url ? `<img src="${s.logo_url}" class="w-full h-full object-contain">` : `<span class="text-gray-400 text-xs font-bold">لا يوجد شعار</span>`}
                        </div>
                        <label class="absolute inset-0 flex items-center justify-center bg-black/40 text-white opacity-0 group-hover:opacity-100 transition rounded-3xl cursor-pointer">
                            <input type="file" id="logoInput" class="hidden" accept="image/*" onchange="handleLogoUpload()">
                            <span class="text-xs font-bold">تغيير الشعار</span>
                        </label>
                    </div>
                    <p class="text-[10px] text-gray-400 leading-relaxed font-bold">يفضل استخدام صورة بخلفية شفافة (PNG) وبحجم لا يتعدى 2 ميجابايت.</p>
                </div>
            </div>

            <!-- Main Info Form -->
            <div class="md:col-span-2 space-y-6">
                <div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 p-8">
                    <div class="grid grid-cols-1 gap-6">
                        <div>
                            <label class="block text-sm font-black text-gray-700 mb-2">اسم المدرسة الرسمي</label>
                            <input type="text" id="schoolName" value="${esc(s.name)}" class="w-full px-5 py-3.5 bg-gray-50 border-2 border-gray-100 rounded-2xl focus:border-emerald-500 focus:bg-white outline-none transition font-bold text-gray-800">
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-black text-gray-700 mb-2">البريد الإلكتروني</label>
                                <input type="email" id="schoolEmail" value="${esc(s.email || '')}" class="w-full px-5 py-3 bg-gray-50 border-2 border-gray-100 rounded-2xl focus:border-emerald-500 focus:bg-white outline-none transition font-bold">
                            </div>
                            <div>
                                <label class="block text-sm font-black text-gray-700 mb-2">رقم الهاتف</label>
                                <input type="text" id="schoolPhone" value="${esc(s.phone || '')}" class="w-full px-5 py-3 bg-gray-50 border-2 border-gray-100 rounded-2xl focus:border-emerald-500 focus:bg-white outline-none transition font-bold" dir="ltr">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-black text-gray-700 mb-2">العنوان / المنطقة</label>
                            <input type="text" id="schoolAddress" value="${esc(s.address || '')}" class="w-full px-5 py-3 bg-gray-50 border-2 border-gray-100 rounded-2xl focus:border-emerald-500 focus:bg-white outline-none transition font-bold">
                        </div>
                    </div>
                </div>

                <!-- Academic Specs -->
                <div class="bg-white rounded-[2rem] shadow-sm border border-gray-100 p-8">
                    <h3 class="font-black text-gray-800 mb-6 flex items-center gap-2">🏫 النظام الدراسي</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-black text-gray-700 mb-2">يوم بداية الأسبوع</label>
                            <select id="weekStart" class="w-full px-5 py-3 bg-gray-50 border-2 border-gray-100 rounded-2xl focus:border-emerald-500 focus:bg-white outline-none transition font-bold text-gray-800">
                                <option value="1" ${s.week_start_day == 1 ? 'selected' : ''}>الأحد</option>
                                <option value="2" ${s.week_start_day == 2 ? 'selected' : ''}>الإثنين</option>
                                <option value="7" ${s.week_start_day == 7 ? 'selected' : ''}>السبت</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-black text-gray-700 mb-2">عدد الحصص في اليوم</label>
                            <input type="number" id="totalPeriods" value="${s.total_periods}" min="1" max="15" class="w-full px-5 py-3 bg-gray-50 border-2 border-gray-100 rounded-2xl focus:border-emerald-500 focus:bg-white outline-none transition font-bold text-gray-800">
                        </div>
                        <div>
                            <label class="block text-sm font-black text-gray-700 mb-2">بداية الدوام</label>
                            <input type="time" id="startTime" value="${s.school_start_time.substring(0, 5)}" class="w-full px-5 py-3 bg-gray-50 border-2 border-gray-100 rounded-2xl focus:border-emerald-500 focus:bg-white outline-none transition font-bold text-gray-800">
                        </div>
                        <div>
                            <label class="block text-sm font-black text-gray-700 mb-2">نهاية الدوام</label>
                            <input type="time" id="endTime" value="${s.school_end_time.substring(0, 5)}" class="w-full px-5 py-3 bg-gray-50 border-2 border-gray-100 rounded-2xl focus:border-emerald-500 focus:bg-white outline-none transition font-bold text-gray-800">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <button onclick="saveSchoolSettings()" class="bg-emerald-600 text-white px-10 py-4 rounded-2xl font-black hover:bg-emerald-700 shadow-xl shadow-emerald-100 transition active:scale-95">
                        حفظ جميع الإعدادات
                    </button>
                    <button onclick="navigateTo('dashboard')" class="bg-gray-100 text-gray-600 px-8 py-4 rounded-2xl font-black hover:bg-gray-200 transition">
                        إلغاء
                    </button>
                </div>
            </div>
        </div>
    </div>`;
}

async function saveSchoolSettings() {
    const data = {
        name: document.getElementById('schoolName').value.trim(),
        email: document.getElementById('schoolEmail').value.trim(),
        phone: document.getElementById('schoolPhone').value.trim(),
        address: document.getElementById('schoolAddress').value.trim(),
        week_start_day: document.getElementById('weekStart').value,
        total_periods: document.getElementById('totalPeriods').value,
        school_start_time: document.getElementById('startTime').value,
        school_end_time: document.getElementById('endTime').value
    };

    if (!data.name) return showToast('اسم المدرسة مطلوب', 'error');

    const res = await API.post('save_school_info', data);
    if (res && res.success) {
        showToast(res.message, 'success');
        refreshBranding();
    } else {
        showToast(res?.message || 'تعذر الحفظ', 'error');
    }
}

async function handleLogoUpload() {
    const fileInput = document.getElementById('logoInput');
    const file = fileInput.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('logo', file);

    const loader = showLoading();
    document.getElementById('logoPreview').innerHTML = loader;

    try {
        const options = {
            method: 'POST',
            body: formData,
            headers: {}
        };

        // Add CSRF Token
        const csrfToken = document.cookie.split('; ').find(row => row.startsWith('XSRF-TOKEN='))?.split('=')[1];
        if (csrfToken) {
            options.headers['X-CSRF-TOKEN'] = csrfToken;
        }

        const response = await fetch(`${API.base}?action=upload_logo`, options);
        const res = await response.json();

        if (res && res.success) {
            showToast(res.message, 'success');
            document.getElementById('logoPreview').innerHTML = `<img src="${res.data.logo_url}?v=${Date.now()}" class="w-full h-full object-contain">`;
            refreshBranding();
        } else {
            showToast(res?.message || 'فشل رفع الشعار', 'error');
            renderSchoolSettings();
        }
    } catch (e) {
        showToast('حدث خطأ أثناء الرفع', 'error');
    }
}

async function refreshBranding() {
    const res = await API.get('get_school_info');
    if (res && res.success) {
        const s = res.data;
        const headerName = document.querySelector('.school-name-display');
        if (headerName) headerName.textContent = s.name;

        const headerLogo = document.querySelector('.school-logo-display');
        if (headerLogo) {
            if (s.logo_url) {
                headerLogo.innerHTML = `<img src="${s.logo_url}" class="h-8 md:h-10 w-auto object-contain">`;
            } else {
                headerLogo.innerHTML = `<span class="bg-emerald-100 text-emerald-600 w-10 h-10 rounded-xl flex items-center justify-center font-black">${s.name.substring(0, 1)}</span>`;
            }
        }

        if (s.logo_url) {
            let link = document.querySelector("link[rel~='icon']");
            if (!link) {
                link = document.createElement('link');
                link.rel = 'icon';
                document.getElementsByTagName('head')[0].appendChild(link);
            }
            link.href = s.logo_url;
            let appleLink = document.querySelector("link[rel='apple-touch-icon']");
            if (appleLink) appleLink.href = s.logo_url;
        }
    }
}
