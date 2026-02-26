/**
 * PE Smart School System - Parent Dashboard
 */

async function renderParentDashboard() {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = showLoading();

    const r = await API.get('parent_dashboard');
    if (!r || !r.success) {
        mc.innerHTML = `<div class="p-8 text-center text-red-600 font-bold">خطأ في تحميل لوحة تحكم ولي الأمر</div>`;
        return;
    }

    const children = r.data || [];

    if (children.length === 0) {
        renderEmptyParentState(mc);
        return;
    }

    let html = `
    <div class="fade-in">
        <div class="mb-8">
            <h2 class="text-3xl font-black text-gray-800">👋 مرحباً بك في بوابة ولي الأمر</h2>
            <p class="text-gray-500 mt-2">إليك ملخص للأداء البدني والصحي لأبنائك</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            ${children.map(child => renderChildCard(child)).join('')}
        </div>
        
        <div class="mt-8 bg-blue-50 border border-blue-100 rounded-2xl p-6 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h4 class="font-bold text-blue-900">هل لديك أبناء آخرون؟</h4>
                <p class="text-blue-700 text-sm">إذا كان لديك أبناء آخرون في المدرسة، تأكد من تحديث رقم جوالك في ملفك الشخصي ليقوم النظام بربطهم تلقائياً.</p>
            </div>
            <button onclick="linkChildrenByPhone()" class="bg-blue-600 text-white px-6 py-2 rounded-xl font-bold hover:bg-blue-700 transition">تحديث ربط الأبناء 🔄</button>
        </div>
    </div>`;

    mc.innerHTML = html;
}

function renderChildCard(child) {
    const att = child.attendance_summary;
    const attRate = att.total_days > 0 ? Math.round((att.present_days / att.total_days) * 100) : 100;
    const meas = child.latest_measurement;

    return `
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden card-hover">
        <div class="p-6">
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h3 class="text-xl font-bold text-gray-800">${esc(child.name)}</h3>
                    <p class="text-gray-500 text-sm">${esc(child.full_class_name)}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-2xl flex items-center justify-center text-2xl">👦</div>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-6">
                <div class="bg-gray-50 rounded-2xl p-3 text-center">
                    <p class="text-[10px] text-gray-400 font-bold uppercase mb-1">اللياقة</p>
                    <p class="text-xl font-black text-indigo-600">${child.avg_fitness_score || 0}<span class="text-xs">/10</span></p>
                </div>
                <div class="bg-gray-50 rounded-2xl p-3 text-center">
                    <p class="text-[10px] text-gray-400 font-bold uppercase mb-1">الكتلة BMI</p>
                    <p class="text-xl font-black ${meas ? getBMICategoryColor(meas.bmi_category) : 'text-gray-400'}">${meas ? meas.bmi : '-'}</p>
                </div>
            </div>

            <div class="space-y-4">
                <!-- Attendance Bar -->
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="text-gray-500">نسبة الحضور</span>
                        <span class="font-bold text-green-600">${attRate}%</span>
                    </div>
                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-green-500" style="width: ${attRate}%"></div>
                    </div>
                </div>

                ${child.health_alerts > 0 ? `
                <div class="flex items-center gap-2 text-red-600 bg-red-50 p-2 rounded-lg text-xs font-bold">
                    <span>⚠️</span>
                    <span>تنبيه صحي: ${child.health_alerts} ملاحظة نشطة</span>
                </div>
                ` : `
                <div class="flex items-center gap-2 text-green-600 bg-green-50 p-2 rounded-lg text-xs font-bold">
                    <span>✅</span>
                    <span>الحالة الصحية مستقرة</span>
                </div>
                `}

                <!-- Badges -->
                ${child.badges && child.badges.length > 0 ? `
                <div class="pt-2">
                    <p class="text-[10px] text-gray-400 font-black uppercase mb-2 tracking-widest">الأوسمة الحاصل عليها</p>
                    <div class="flex flex-wrap gap-2">
                        ${child.badges.map(b => `<span class="w-8 h-8 rounded-full ${b.color} text-white flex items-center justify-center text-sm shadow-sm" title="${esc(b.name)}">${b.icon}</span>`).join('')}
                    </div>
                </div>
                ` : ''}
            </div>
            
            <button onclick="window._profileStudentId=${child.id};navigateTo('studentProfile')" class="w-full mt-6 py-3 bg-gray-900 text-white rounded-2xl font-bold hover:bg-black transition">استعراض الملف بالكامل ←</button>
        </div>
    </div>`;
}

function renderEmptyParentState(mc) {
    mc.innerHTML = `
    <div class="fade-in text-center py-20 bg-white rounded-3xl border border-dashed border-gray-200">
        <div class="text-6xl mb-6">👨‍👩‍👦‍👦</div>
        <h3 class="text-2xl font-bold text-gray-800 mb-2">أهلاً بك في بوابة ولي الأمر</h3>
        <p class="text-gray-500 max-w-md mx-auto mb-8">لم يتم ربط أي أبناء بحسابك حتى الآن. يرجى التأكد من أن رقم جوالك في "ملفي الشخصي" يطابق المسجل في المدرسة لكل ابن.</p>
        <div class="flex gap-4 justify-center">
            <button onclick="navigateTo('user_profile')" class="bg-gray-900 text-white px-8 py-3 rounded-2xl font-bold hover:bg-black transition">⚙️ تحديث ملفي الشخصي</button>
            <button onclick="linkChildrenByPhone()" class="bg-green-600 text-white px-8 py-3 rounded-2xl font-bold hover:bg-green-700 transition">🔄 جاري الربط الآن</button>
        </div>
    </div>`;
}

async function linkChildrenByPhone() {
    showLoading();
    const r = await API.post('parent_link_phone');
    if (r && r.success) {
        showToast(r.message, 'success');
        renderParentDashboard();
    } else {
        showToast(r?.error || 'فشل الربط', 'error');
    }
}

function getBMICategoryColor(cat) {
    switch (cat) {
        case 'normal': return 'text-green-600';
        case 'obese': return 'text-red-600';
        case 'overweight': return 'text-orange-500';
        case 'underweight': return 'text-blue-500';
        default: return 'text-gray-600';
    }
}
