/**
 * PE Smart School System - Badge Management (Admin)
 */

// ── Emoji Collections ───────────────────────────────────────
const BADGE_EMOJI_CATEGORIES = {
    'رياضة': ['⚽', '🏀', '🏐', '🏈', '🎾', '🏓', '🏸', '🥏', '🏒', '⛳', '🥊', '🥋', '🤸', '🏋️', '🤾', '⛹️', '🏊', '🚴', '🏇', '🧗', '🤺', '🏂', '⛷️', '🏄', '🤽', '🏌️', '🚣'],
    'إنجازات': ['⭐', '🌟', '💫', '✨', '🏆', '🥇', '🥈', '🥉', '🏅', '🎖️', '👑', '💎', '🔥', '⚡', '💪', '🎯', '🚀', '💥', '🌠', '🎗️'],
    'أنشطة': ['🏃', '🏃‍♂️', '🤸‍♂️', '🧘', '🤼', '🏇', '🎿', '🛹', '🤿', '🪂', '🏋️‍♂️', '⛹️‍♂️', '🚶', '🧗‍♂️', '🏄‍♂️'],
    'تعبيرات': ['😎', '🤩', '💯', '👏', '🙌', '💐', '🎉', '🎊', '🎈', '🪄', '📜', '📣', '🔔', '🎓', '📚']
};

async function renderBadgeManagementPage() {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = showLoading();

    const r = await API.get('get_badges');
    if (!r || !r.success) {
        mc.innerHTML = '<div class="text-center py-20 bg-white rounded-[3rem] border-2 border-dashed border-gray-100"><p class="text-red-500 font-black">خطأ في تحميل الأوسمة</p></div>';
        return;
    }

    const badges = r.data || [];

    mc.innerHTML = `
    <div class="fade-in px-4 md:px-0">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
            <div>
                <h2 class="text-3xl font-black text-gray-800">🏅 أكاديمية الأوسمة الرقمية</h2>
                <p class="text-gray-500 font-bold mt-1">صمم وحفز طلابك بالأوسمة التقديرية لإنجازاتهم المتميزة</p>
            </div>
            <div class="flex gap-3">
                <button onclick="triggerBadgeAutomation()" class="bg-white text-emerald-600 border-2 border-emerald-100 px-6 py-4 rounded-[1.5rem] font-black hover:bg-emerald-50 transition flex items-center gap-2 active:scale-95">
                    <span>🤖</span> الأتمتة الذكية
                </button>
                <button onclick="showBadgeForm()" class="bg-emerald-600 text-white px-8 py-4 rounded-[1.5rem] font-black hover:bg-emerald-700 shadow-xl shadow-emerald-100 transition flex items-center gap-2 active:scale-95">
                    <span>➕</span> وسام جديد
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8">
            ${badges.map(b => `
                <div class="group bg-white rounded-[3rem] p-8 border border-gray-100 shadow-sm hover:shadow-2xl hover:-translate-y-2 transition-all duration-500 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-48 h-48 ${b.color} opacity-5 rounded-full -mr-24 -mt-24 transition group-hover:scale-150"></div>
                    
                    <div class="relative z-10">
                        <div class="flex items-start justify-between mb-8">
                            <div class="w-24 h-24 rounded-[2rem] ${b.color} text-white flex items-center justify-center text-5xl shadow-2xl transform group-hover:rotate-12 transition duration-500">
                                ${b.icon}
                            </div>
                            <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition duration-300">
                                <button onclick="showBadgeForm(${JSON.stringify(b).replace(/"/g, '&quot;')})" class="w-12 h-12 rounded-2xl bg-gray-50 text-emerald-600 hover:bg-emerald-600 hover:text-white transition flex items-center justify-center cursor-pointer shadow-sm">✏️</button>
                                <button onclick="deleteBadgeDefinition(${b.id})" class="w-12 h-12 rounded-2xl bg-gray-50 text-red-600 hover:bg-red-600 hover:text-white transition flex items-center justify-center cursor-pointer shadow-sm">🗑️</button>
                            </div>
                        </div>

                        <h3 class="text-2xl font-black text-gray-800 mb-3 group-hover:text-emerald-700 transition">${esc(b.name)}</h3>
                        <p class="text-gray-400 font-bold text-sm leading-relaxed mb-8 min-h-[48px] line-clamp-2">${esc(b.description || 'لا يوجد وصف متاح لهذا الوسام')}</p>
                        
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="px-4 py-1.5 bg-gray-50 text-gray-500 rounded-full text-[10px] font-black uppercase tracking-widest border border-gray-100">${b.badge_type === 'manual' ? 'منح يدوي 👋' : 'أتمتة ذكية ⚡'}</span>
                            ${b.badge_type !== 'manual' ? `<span class="px-4 py-1.5 bg-emerald-50 text-emerald-600 rounded-full text-[10px] font-black uppercase tracking-widest border border-emerald-100">المعيار: ${b.criteria_value || '-'}</span>` : ''}
                            <div class="${b.color} w-4 h-4 rounded-full ring-4 ring-white shadow-sm ml-auto"></div>
                        </div>
                    </div>
                </div>
            `).join('')}
            
            ${badges.length === 0 ? `
                <div class="col-span-full py-32 text-center bg-gray-50 rounded-[4rem] border-4 border-dashed border-gray-100">
                    <div class="text-8xl mb-8 grayscale opacity-20">🏅</div>
                    <p class="text-gray-400 font-black text-2xl">لم تقم بإنشاء أي أوسمة بعد</p>
                    <p class="text-gray-300 font-bold mt-2">ابدأ بإضافة أول وسام لتحفيز الأبطال!</p>
                </div>
            ` : ''}
        </div>
    </div>`;
}

function showBadgeForm(badge = null) {
    const isEdit = !!badge;
    const colors = [
        { name: 'زمردي', class: 'bg-emerald-500' },
        { name: 'أخضر', class: 'bg-green-500' },
        { name: 'تيل', class: 'bg-teal-500' },
        { name: 'أزرق', class: 'bg-blue-500' },
        { name: 'نيلي', class: 'bg-indigo-500' },
        { name: 'بنفسجي', class: 'bg-purple-500' },
        { name: 'برتقالي', class: 'bg-orange-500' },
        { name: 'أصفر', class: 'bg-yellow-500' },
        { name: 'أحمر', class: 'bg-red-500' }
    ];

    showModal(`
        <div class="p-8 md:p-12">
            <div class="flex items-center gap-4 mb-10">
                <div class="w-16 h-16 rounded-[1.5rem] bg-emerald-50 text-emerald-600 flex items-center justify-center text-3xl font-black">
                    ${isEdit ? '✏️' : '➕'}
                </div>
                <div>
                    <h3 class="text-3xl font-black text-gray-800">${isEdit ? 'تعديل الوسام' : 'إنشاء وسام جديد'}</h3>
                    <p class="text-gray-400 font-bold text-sm">أكمل البيانات لتحديد هوية الوسام وشروطه</p>
                </div>
            </div>
            
            <div class="space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="space-y-2">
                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mr-2">اسم الوسام المميز</label>
                        <input type="text" id="badgeName" value="${badge ? esc(badge.name) : ''}" class="w-full px-6 py-5 bg-gray-50 border-2 border-gray-50 rounded-[1.5rem] focus:bg-white focus:border-emerald-500 focus:outline-none transition-all font-black text-gray-700" placeholder="مثال: صقر الميدان">
                    </div>
                    <div class="space-y-2">
                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mr-2">الأيقونة (Emoji)</label>
                        <div class="flex items-center gap-3">
                            <div id="badgeIconPreview" class="w-20 h-20 bg-gray-50 border-2 border-gray-100 rounded-[1.5rem] flex items-center justify-center text-5xl cursor-pointer hover:border-emerald-400 hover:bg-emerald-50 transition-all" onclick="document.getElementById('badgeEmojiPicker').classList.toggle('hidden')">${badge ? badge.icon : '⭐'}</div>
                            <input type="hidden" id="badgeIcon" value="${badge ? esc(badge.icon) : '⭐'}">
                            <input type="text" id="badgeIconCustom" value="" class="flex-1 px-4 py-3 bg-gray-50 border-2 border-gray-50 rounded-xl focus:bg-white focus:border-emerald-500 focus:outline-none text-center text-2xl" placeholder="أو اكتب إيموجي" maxlength="2" oninput="if(this.value){document.getElementById('badgeIcon').value=this.value;document.getElementById('badgeIconPreview').textContent=this.value;}">
                        </div>
                        <div id="badgeEmojiPicker" class="hidden mt-3 bg-white border-2 border-emerald-100 rounded-2xl p-4 shadow-xl max-h-60 overflow-y-auto animate-in">
                            ${Object.entries(BADGE_EMOJI_CATEGORIES).map(([cat, emojis]) => `
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2 mt-3 first:mt-0">${cat}</p>
                                <div class="grid grid-cols-9 gap-1 mb-2">
                                    ${emojis.map(e => `<button type="button" onclick="document.getElementById('badgeIcon').value='${e}';document.getElementById('badgeIconPreview').textContent='${e}';document.getElementById('badgeEmojiPicker').classList.add('hidden');document.getElementById('badgeIconCustom').value='';" class="w-9 h-9 flex items-center justify-center text-xl rounded-lg hover:bg-emerald-50 hover:scale-125 transition-all cursor-pointer active:scale-90">${e}</button>`).join('')}
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
                
                <div class="space-y-2">
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mr-2">وصف الإنجاز</label>
                    <textarea id="badgeDesc" rows="2" class="w-full px-6 py-5 bg-gray-50 border-2 border-gray-50 rounded-[1.5rem] focus:bg-white focus:border-emerald-500 focus:outline-none font-bold text-gray-700" placeholder="اشرح للطلاب كيف يمكنهم الحصول على هذا الوسام...">${badge ? esc(badge.description) : ''}</textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="space-y-2">
                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mr-2">آلية المنح</label>
                        <select id="badgeType" onchange="toggleCriteriaField()" class="w-full px-6 py-5 bg-gray-50 border-2 border-gray-50 rounded-[1.5rem] focus:bg-white focus:border-emerald-500 focus:outline-none font-black text-gray-700 appearance-none cursor-pointer">
                            <option value="manual" ${badge?.badge_type === 'manual' ? 'selected' : ''}>👋 منح يدوي من المعلم</option>
                            <option value="attendance_100" ${badge?.badge_type === 'attendance_100' ? 'selected' : ''}>📅 بطل الحضور (أوتوماتيكي)</option>
                            <option value="fitness_pro" ${badge?.badge_type === 'fitness_pro' ? 'selected' : ''}>🔋 متفوق اللياقة (أوتوماتيكي)</option>
                            <option value="improvement" ${badge?.badge_type === 'improvement' ? 'selected' : ''}>📈 الأكثر تطوراً (أوتوماتيكي)</option>
                        </select>
                    </div>
                    <div id="criteriaField" class="${badge?.badge_type === 'manual' || !badge?.badge_type ? 'hidden' : ''} space-y-2">
                        <label id="criteriaLabel" class="block text-xs font-black text-gray-400 uppercase tracking-widest mr-2">قيمة المعيار</label>
                        <input type="number" step="0.1" id="criteriaValue" value="${badge ? badge.criteria_value : ''}" class="w-full px-6 py-5 bg-gray-50 border-2 border-gray-50 rounded-[1.5rem] focus:bg-white focus:border-emerald-500 focus:outline-none font-black text-gray-700" placeholder="0.0">
                    </div>
                </div>

                <div class="space-y-4">
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mr-2">اللون المميز للوسام</label>
                    <div class="grid grid-cols-5 md:grid-cols-9 gap-3">
                        ${colors.map(c => `
                            <label class="cursor-pointer group">
                                <input type="radio" name="badgeColor" value="${c.class}" class="peer hidden" ${badge?.color === c.class ? 'checked' : (c.class === 'bg-emerald-500' && !badge ? 'checked' : '')}>
                                <div class="w-full h-12 rounded-2xl ${c.class} border-4 border-transparent peer-checked:border-gray-900 group-hover:scale-110 transition shadow-sm active:scale-90"></div>
                            </label>
                        `).join('')}
                    </div>
                </div>

                <div class="flex flex-col md:flex-row gap-4 pt-6">
                    <button onclick="saveBadgeDefinition(${badge?.id || 'null'})" class="flex-1 bg-emerald-600 text-white py-5 rounded-[1.5rem] font-black hover:bg-emerald-700 shadow-2xl shadow-emerald-100 transition active:scale-95 flex items-center justify-center gap-3">
                        <span class="text-xl">💾</span> حفظ وإعتماد الوسام
                    </button>
                    <button onclick="closeModal()" class="md:w-32 bg-gray-100 text-gray-500 py-5 rounded-[1.5rem] font-black hover:bg-gray-200 transition active:scale-95">إلغاء</button>
                </div>
            </div>
        </div>
    `);
}

async function saveBadgeDefinition(id) {
    const data = {
        id: id,
        name: document.getElementById('badgeName').value.trim(),
        description: document.getElementById('badgeDesc').value.trim(),
        icon: document.getElementById('badgeIcon').value.trim(),
        badge_type: document.getElementById('badgeType').value,
        criteria_value: document.getElementById('criteriaValue').value,
        color: document.querySelector('input[name="badgeColor"]:checked')?.value
    };

    if (!data.name || !data.icon || !data.color) {
        showToast('يرجى إكمال جميع الحقول المطلوبة', 'error');
        return;
    }

    const r = await API.post('badge_save', data);
    if (r && r.success) {
        closeModal();
        showToast(r.message);
        renderBadgeManagementPage();
    } else if (r) {
        showToast(r.error, 'error');
    }
}

async function deleteBadgeDefinition(id) {
    if (!confirm('هل أنت متأكد من حذف هذا الوسام نهائياً؟ لا يمكن حذفه إذا كان ممنوحاً لطلاب.')) return;

    const r = await API.post('badge_delete', null, { id });
    if (r && r.success) {
        showToast(r.message);
        renderBadgeManagementPage();
    } else if (r) {
        showToast(r.error, 'error');
    }
}

async function triggerBadgeAutomation() {
    showToast('جاري تشغيل نظام التحليل والمنح التلقائي...', 'info');
    const r = await API.get('run_auto_badges');
    if (r && r.success) {
        showToast(r.message, 'success');
        renderBadgeManagementPage();
    } else if (r) {
        showToast(r.error, 'error');
    }
}

function toggleCriteriaField() {
    const type = document.getElementById('badgeType').value;
    const field = document.getElementById('criteriaField');
    const label = document.getElementById('criteriaLabel');
    const hint = document.getElementById('criteriaHint');
    const input = document.getElementById('criteriaValue');

    if (type === 'manual') {
        field.classList.add('hidden');
    } else {
        field.classList.remove('hidden');
        if (type === 'attendance_100') {
            label.textContent = 'نسبة الحضور المطلوبة (%)';
            hint.textContent = 'سيتم منح الوسام لكل من يحقق هذه النسبة خلال آخر 30 يوم (الافتراضي 100%)';
            if (!input.value) input.value = 100;
        } else if (type === 'fitness_pro') {
            label.textContent = 'الحد الأدنى للمعدل (من 10)';
            hint.textContent = 'سيتم منح الوسام لكل من يحقق معدل درجات يساوي أو يتجاوز هذه القيمة (مثلاً 9.0)';
            if (!input.value) input.value = 9;
        } else if (type === 'improvement') {
            label.textContent = 'مقدار التحسن المطلوب';
            hint.textContent = 'سيتم منح الوسام عند تحسن الدرجة في أي اختبار مقارنة بالمرة السابقة بهذا المقدار (مثلاً 1.0)';
            if (!input.value) input.value = 1;
        }
    }
}
