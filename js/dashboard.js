/**
 * PE Smart School System - Dashboard Page
 */

async function renderDashboard() {
    // Redirection failsafe for students
    if (currentUser && currentUser.role === 'student') {
        return renderStudentDashboard();
    }

    const mc = document.getElementById('mainContent');
    mc.innerHTML = showLoading();

    const r = await API.get('dashboard');
    if (!r || !r.success) {
        mc.innerHTML = '<p class="text-red-500 text-center py-8">خطأ في تحميل البيانات</p>';
        return;
    }

    const d = r.data;
    const s = d.stats;
    const ranking = d.ranking || [];
    const top = d.topStudent;

    mc.innerHTML = `
    <div class="fade-in">
        <div class="mb-6">
            <h2 class="text-2xl font-bold text-gray-800">مرحباً، ${esc(currentUser.name)} 👋</h2>
            <p class="text-gray-500 mt-1">لوحة التحكم - نظرة عامة</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">الطلاب</p>
                        <p class="text-3xl font-bold text-gray-800 mt-1">${s.totalStudents}</p>
                    </div>
                    <div class="stat-icon bg-blue-100 text-blue-600">👨‍🎓</div>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">الفصول</p>
                        <p class="text-3xl font-bold text-gray-800 mt-1">${s.totalClasses}</p>
                    </div>
                    <div class="stat-icon bg-green-100 text-green-600">🏫</div>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">حضور اليوم</p>
                        <p class="text-3xl font-bold text-green-600 mt-1">${s.presentToday}</p>
                    </div>
                    <div class="stat-icon bg-emerald-100 text-emerald-600">✅</div>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">غياب اليوم</p>
                        <p class="text-3xl font-bold text-red-600 mt-1">${s.absentToday}</p>
                    </div>
                    <div class="stat-icon bg-red-100 text-red-600">❌</div>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">تنبيهات صحية</p>
                        <p class="text-3xl font-bold text-orange-600 mt-1">${s.healthAlerts}</p>
                    </div>
                    <div class="stat-icon bg-orange-100 text-orange-600">🏥</div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Class Ranking -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-lg font-bold text-gray-800 mb-4">🏆 ترتيب الفصول</h3>
                <div class="space-y-3">
                    ${/* Fix #11: Renamed 'r' → 'rank' to avoid shadowing outer API result variable */
        ranking.map((rank, i) => `
                        <div class="flex items-center gap-3 p-3 rounded-xl ${i === 0 ? 'bg-yellow-50 border border-yellow-200' : 'bg-gray-50'}">
                            <span class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold ${i === 0 ? 'rank-1' : i === 1 ? 'rank-2' : i === 2 ? 'rank-3' : 'bg-gray-300 text-white'}">${i + 1}</span>
                            <div class="flex-1">
                                <p class="font-semibold text-gray-800">${esc(rank.class_name)}</p>
                            </div>
                            <span class="font-bold text-lg ${i === 0 ? 'text-yellow-600' : 'text-gray-600'}">${rank.avg_score}</span>
                        </div>
                    `).join('')}
                    ${ranking.length === 0 ? '<p class="text-gray-400 text-center py-4">لا توجد بيانات</p>' : ''}
                </div>
            </div>

            <!-- Right Column -->
            <div class="space-y-6">
                <!-- Today's Timetable (Teachers Only) -->
                ${d.todayTimetable ? (d.todayTimetable.length > 0 ? `
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-2 h-full bg-indigo-500"></div>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-800 flex items-center gap-2">📅 حصصك اليوم</h3>
                        <span class="bg-indigo-50 text-indigo-600 text-[10px] font-black px-2 py-1 rounded-full uppercase tracking-wider">${d.serverTime}</span>
                    </div>
                    <div class="space-y-3">
                        ${d.todayTimetable.map(t => {
            // Check if active: serverTime is between start_time and end_time
            const now = d.serverTime;
            const start = t.start_time ? t.start_time.substring(0, 5) : null;
            const end = t.end_time ? t.end_time.substring(0, 5) : null;
            const isActive = (start && end && now >= start && now <= end);

            return `
                            <div class="flex items-center justify-between p-3 rounded-xl border transition-all ${isActive ? 'bg-indigo-50 border-indigo-200 ring-2 ring-indigo-500/10 shadow-md scale-[1.02]' : 'bg-gray-50 border-gray-100 opacity-80'}">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center font-black text-sm border ${isActive ? 'bg-indigo-600 text-white border-indigo-600 animate-pulse' : 'bg-white text-indigo-600 border-indigo-100'}">
                                        ${t.period_number}
                                    </div>
                                    <div>
                                        <span class="block font-bold text-gray-800 text-sm">${esc(t.class_name)}</span>
                                        ${start ? `<span class="text-[10px] font-bold ${isActive ? 'text-indigo-600' : 'text-gray-400'}">${start} - ${end}</span>` : ''}
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    ${isActive ? '<span class="flex h-2 w-2 rounded-full bg-indigo-600 animate-ping"></span> <span class="text-[10px] font-black text-indigo-600 ml-1">الآن</span>' : ''}
                                    <button onclick="navigateTo('attendance/${t.class_id}')" title="الانتقال لشاشة التحضير" class="text-xs ${isActive ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-100' : 'bg-white text-gray-500 border border-gray-200'} px-3 py-2 rounded-lg font-bold hover:opacity-90 transition">
                                        تحضير
                                    </button>
                                </div>
                            </div>
                            `;
        }).join('')}
                    </div>
                </div>
                ` : `
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col items-center justify-center text-center">
                    <span class="text-4xl mb-3 block opacity-50">🏖️</span>
                    <h3 class="text-sm font-bold text-gray-800 mb-1">جدول حصصك فارغ اليوم</h3>
                    <p class="text-xs text-gray-500 mb-4">لم تقم بجدولة أي حصص لهذا اليوم في كشكول التحضير.</p>
                    <button onclick="navigateTo('timetable')" class="text-xs font-bold bg-indigo-50 text-indigo-600 px-5 py-2 rounded-xl hover:bg-indigo-100 transition shadow-sm border border-indigo-100">إدارة جدولي الأسبوعي</button>
                </div>
                `) : ''}

                <!-- Top Student -->
                ${top ? `
                <div class="bg-gradient-to-l from-green-500 to-emerald-600 rounded-2xl p-6 text-white text-center shadow-lg shadow-green-100">
                    <h3 class="font-bold text-lg mb-2 flex items-center justify-center gap-2">⭐ أفضل طالب في فصولك</h3>
                    <p class="text-2xl font-black mt-2">${esc(top.name)}</p>
                    <p class="opacity-80 mt-1 text-sm font-bold bg-white/20 inline-block px-3 py-1 rounded-full">${esc(top.class_name)}</p>
                    <div class="mt-4 flex items-center justify-center gap-2">
                        <span class="text-4xl font-black">${top.avg_score}</span> <span class="text-lg font-bold opacity-80 pt-2">/ 10</span>
                    </div>
                </div>
                ` : ''}

                <!-- Quick Actions -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4">⚡ إجراءات سريعة</h3>
                    <div class="grid grid-cols-2 gap-3">
                        ${canEdit() ? `
                        <button onclick="navigateTo('attendance')" class="p-4 bg-blue-50 rounded-xl text-center hover:bg-blue-100 transition cursor-pointer">
                            <span class="text-2xl block mb-1">📋</span>
                            <span class="text-sm font-semibold text-blue-700">تسجيل الحضور</span>
                        </button>
                        <button onclick="navigateTo('fitness')" class="p-4 bg-purple-50 rounded-xl text-center hover:bg-purple-100 transition cursor-pointer">
                            <span class="text-2xl block mb-1">💪</span>
                            <span class="text-sm font-semibold text-purple-700">اختبار لياقة</span>
                        </button>
                        ` : ''}
                        <button onclick="navigateTo('competition')" class="p-4 bg-yellow-50 rounded-xl text-center hover:bg-yellow-100 transition cursor-pointer">
                            <span class="text-2xl block mb-1">🏆</span>
                            <span class="text-sm font-semibold text-yellow-700">التنافس</span>
                        </button>
                        <button onclick="navigateTo('reports')" class="p-4 bg-green-50 rounded-xl text-center hover:bg-green-100 transition cursor-pointer">
                            <span class="text-2xl block mb-1">📈</span>
                            <span class="text-sm font-semibold text-green-700">التقارير</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>`;
}
