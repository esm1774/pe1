/**
 * PE Smart School System - Student Dashboard
 * Specialized UI for students to view their own performance
 */

async function renderStudentDashboard() {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = showLoading();

    // Fetch student summary data
    const r = await API.get('student_dashboard_summary');
    if (!r || !r.success) {
        mc.innerHTML = `
            <div class="text-center py-20">
                <p class="text-5xl mb-4">⚠️</p>
                <p class="text-xl font-bold text-red-600">فشل تحميل البيانات</p>
                <button onclick="location.reload()" class="mt-4 bg-green-600 text-white px-6 py-2 rounded-xl">إعادة محاولة</button>
            </div>`;
        return;
    }

    const { student, measurements, attendance, teams } = r.data;

    mc.innerHTML = `
    <div class="fade-in space-y-6">
        <!-- Welcoming Header -->
        <div class="bg-gradient-to-l from-indigo-600 to-indigo-800 rounded-3xl p-8 text-white shadow-xl relative overflow-hidden">
            <div class="relative z-10">
                <h2 class="text-3xl font-bold mb-2">أهلاً بك، ${esc(student.name)} 👋</h2>
                <p class="text-indigo-100 italic opacity-90">مستقبلك الرياضي يبدأ من هنا. استمر في التميز!</p>
            </div>
            <div class="absolute -left-10 -bottom-10 text-[120px] opacity-10">🏃</div>
        </div>

        <!-- Quick Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            ${renderStatCard('📏 الطول', `${measurements?.height_cm || '-'} سم`, 'bg-blue-50 text-blue-700')}
            ${renderStatCard('⚖️ الوزن', `${measurements?.weight_kg || '-'} كجم`, 'bg-green-50 text-green-700')}
            ${renderStatCard('📊 BMI', `${measurements?.bmi || '-'}`, getBMICategoryStyle(measurements?.bmi_category), measurements?.bmi_category_ar || 'غير محدد')}
            ${renderStatCard('📅 الحضور', `${attendance?.percentage || 0}%`, 'bg-orange-50 text-orange-700', `حضر ${attendance?.present || 0} من ${attendance?.total || 0}`)}
        </div>

        <!-- Main Content Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Side: Health & Fitness -->
            <div class="lg:col-span-2 space-y-6">
                <!-- BMI History Chart Placeholder -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-xl font-bold mb-4 flex items-center gap-2">📈 تطور مستوى اللياقة</h3>
                    <div class="h-64 bg-gray-50 rounded-2xl flex items-center justify-center text-gray-400">
                        <p>رسم بياني لتطور الوزن واللياقة (قيد التطوير)</p>
                    </div>
                </div>

                <!-- Recent Measurements -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-6 border-b border-gray-50 flex items-center justify-between">
                        <h3 class="text-xl font-bold">⏱️ آخر القياسات</h3>
                        <span class="text-sm text-gray-500">تم التحديث مؤخراً</span>
                    </div>
                    <div class="p-6">
                        ${measurements ? `
                            <div class="space-y-4">
                                <div class="flex justify-between items-center p-4 bg-gray-50 rounded-2xl">
                                    <span class="font-bold">معدل نبضات القلب (الراحة)</span>
                                    <span class="badge bg-red-100 text-red-700">${measurements.resting_heart_rate || '-'} bpm</span>
                                </div>
                                <div class="flex justify-between items-center p-4 bg-gray-50 rounded-2xl">
                                    <span class="font-bold">محيط الخصر</span>
                                    <span class="badge bg-indigo-100 text-indigo-700">${measurements.waist_cm || '-'} سم</span>
                                </div>
                            </div>
                        ` : '<p class="text-center text-gray-500 py-4">لا توجد قياسات مسجلة بعد</p>'}
                    </div>
                </div>
            </div>

                <!-- Badges & Achievements -->
                <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-xl font-bold mb-6 flex items-center gap-2">🏅 أوسمتي وإنجازاتي</h3>
                    ${r.data.badges && r.data.badges.length > 0 ? `
                    <div class="grid grid-cols-2 gap-4">
                        ${r.data.badges.map(b => `
                            <div class="p-4 rounded-2xl bg-gray-50 border border-gray-100 text-center group hover:bg-white hover:shadow-md transition">
                                <div class="w-12 h-12 ${b.color} text-white rounded-xl flex items-center justify-center text-xl mx-auto mb-3 shadow-sm group-hover:scale-110 transition">${b.icon}</div>
                                <p class="font-bold text-gray-800 text-xs">${esc(b.name)}</p>
                            </div>
                        `).join('')}
                    </div>
                    ` : `
                    <div class="text-center py-8 bg-gray-50 rounded-2xl border-2 border-dashed border-gray-100">
                        <p class="text-3xl mb-2 opacity-30">🥈</p>
                        <p class="text-xs text-gray-400 font-bold">بإمكانك الحصول على أوسمة من خلال التميز في الحضور والأداء البدني!</p>
                    </div>`}
                </div>

                <!-- Medical Alert Small -->
                ${student.medical_notes ? `
                    <div class="bg-red-50 rounded-3xl p-6 border-2 border-red-100">
                        <h4 class="text-red-700 font-bold mb-2 flex items-center gap-2">⚠️ تنبيه طبي</h4>
                        <p class="text-sm text-red-600">${esc(student.medical_notes)}</p>
                    </div>
                ` : ''}
            </div>
        </div>
    </div>
    `;
}

function renderStatCard(title, value, colorClass, subtitle = '') {
    return `
    <div class="bg-white rounded-3xl shadow-sm border border-gray-100 p-6 flex flex-col justify-between hover:shadow-md transition">
        <p class="text-sm font-bold text-gray-500 mb-2 uppercase tracking-wider">${title}</p>
        <div>
            <p class="text-3xl font-black ${colorClass.split(' ')[1]}">${value}</p>
            ${subtitle ? `<p class="text-xs text-gray-400 mt-1">${subtitle}</p>` : ''}
        </div>
    </div>
    `;
}

function getBMICategoryStyle(cat) {
    const styles = {
        underweight: 'bg-blue-50 text-blue-700',
        normal: 'bg-green-50 text-green-700',
        overweight: 'bg-yellow-50 text-yellow-700',
        obese: 'bg-red-50 text-red-700'
    };
    return styles[cat] || 'bg-gray-50 text-gray-700';
}

// Student profile navigation handlers
async function renderStudentProfile() {
    // We will reuse some parts of js/profile.js or create a simplified view
    // For now, redirect to dashboard as a placeholder
    renderStudentDashboard();
}
