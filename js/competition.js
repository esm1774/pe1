/**
 * PE Smart School System - Competition Page
 */

async function renderCompetition(sid = null) {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = showLoading();

    let parentStudents = [];
    if (currentUser && currentUser.role === 'parent') {
        const pr = await API.get('parent_dashboard');
        if (pr && pr.success) parentStudents = pr.data || [];

        if (!sid && parentStudents.length > 0) {
            sid = parentStudents[0].id; // Default to first child
        }
    }

    const r = await API.get('competition', { student_id: sid });
    if (!r || !r.success) {
        mc.innerHTML = `<div class="p-20 text-center"><p class="text-red-500 font-bold">عذراً، حدث خطأ أثناء تحميل بيانات المنافسات</p></div>`;
        return;
    }

    const { classRanking, topStudents, studentData } = r.data;

    // Redesign for Parents
    if (currentUser.role === 'parent') {
        renderParentCompetition(parentStudents, studentData, classRanking, topStudents);
        return;
    }

    // Standard view for Admin/Teacher
    mc.innerHTML = `
    <div class="fade-in px-4 md:px-0 mb-12">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
            <div>
                <h2 class="text-3xl font-black text-gray-800 tracking-tight">🏆 مركز التصنيف والتميز</h2>
                <p class="text-gray-500 font-bold mt-1 text-sm md:text-base">تتبع أداء الفصول والطلاب المتفوقين في الأنشطة الرياضية</p>
            </div>
        </div>

        <!-- Podium for Classes -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8 mb-16">
            ${classRanking.slice(0, 3).map((c, i) => `
            <div class="group bg-white rounded-[2.5rem] md:rounded-[3rem] shadow-xl shadow-gray-100/50 border-2 ${i === 0 ? 'border-yellow-400 md:order-2 md:-mt-8 scale-105' : i === 1 ? 'border-gray-200 md:order-1' : 'border-orange-200 md:order-3'} p-8 md:p-10 text-center relative overflow-hidden transition-all duration-500 hover:shadow-2xl hover:scale-[1.07]">
                <div class="absolute inset-0 bg-gradient-to-b ${i === 0 ? 'from-yellow-400/5 to-transparent' : i === 1 ? 'from-gray-400/5 to-transparent' : 'from-orange-400/5 to-transparent'} opacity-0 group-hover:opacity-100 transition-opacity"></div>
                <div class="text-6xl md:text-7xl mb-6 transform transition group-hover:scale-125 duration-500 group-hover:-rotate-12">${i === 0 ? '🥇' : i === 1 ? '🥈' : '🥉'}</div>
                <h3 class="font-black text-xl md:text-2xl text-gray-800 transition">${esc(c.full_class_name)}</h3>
                <div class="mt-6 flex flex-col items-center">
                    <p class="text-4xl md:text-5xl font-black text-emerald-600">${c.points}</p>
                    <p class="text-[10px] text-gray-400 font-black uppercase tracking-[0.2em] mt-2">نقطة تميز</p>
                </div>
                <div class="mt-8 flex items-center justify-center gap-2">
                    <span class="bg-gray-50 text-gray-500 text-[10px] font-black px-4 py-2 rounded-xl border border-gray-100 group-hover:bg-emerald-50 group-hover:text-emerald-700 transition-colors">${c.students_count} بطل مشارك</span>
                </div>
            </div>
            `).join('')}
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 md:gap-12">
            <!-- Full Ranking -->
            <div class="bg-white rounded-[2.5rem] md:rounded-[3rem] shadow-2xl shadow-gray-100/50 border border-gray-100 p-6 md:p-10">
                <div class="flex items-center justify-between mb-10">
                    <h3 class="font-black text-xl md:text-2xl text-gray-800 flex items-center gap-4">
                        <span class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl shadow-inner">📊</span>
                        ترتيب الفصول العام
                    </h3>
                </div>
                <div class="space-y-4">
                    ${classRanking.map((c, i) => `
                        <div class="group flex items-center gap-4 md:gap-6 p-4 md:p-5 rounded-[1.5rem] md:rounded-[2rem] ${i < 3 ? 'bg-emerald-50/50' : 'bg-gray-50/50'} transition-all hover:bg-white hover:shadow-xl border border-transparent hover:border-emerald-100">
                            <span class="w-10 h-10 md:w-12 md:h-12 rounded-xl md:rounded-2xl flex items-center justify-center font-black text-base md:text-lg transition-transform group-hover:scale-110 ${i === 0 ? 'bg-yellow-500 text-white shadow-lg shadow-yellow-200' : i === 1 ? 'bg-gray-400 text-white' : i === 2 ? 'bg-orange-400 text-white' : 'bg-white text-gray-400 border border-gray-100'}">${i + 1}</span>
                            <div class="flex-1 min-w-0">
                                <p class="font-black text-gray-800 group-hover:text-emerald-700 transition truncate">${esc(c.full_class_name)}</p>
                                <p class="text-[9px] md:text-[10px] text-gray-400 font-black uppercase tracking-widest">${c.students_count} طالب مسجل</p>
                            </div>
                            <div class="text-left flex-shrink-0">
                                <span class="text-xl md:text-2xl font-black text-gray-900">${c.points}</span>
                                <p class="text-[9px] text-gray-400 font-black uppercase tracking-widest">نقطة</p>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>

            <!-- Top Students -->
            <div class="bg-white rounded-[2.5rem] md:rounded-[3rem] shadow-2xl shadow-gray-100/50 border border-gray-100 p-6 md:p-10">
                <div class="flex items-center justify-between mb-10">
                    <h3 class="font-black text-xl md:text-2xl text-gray-800 flex items-center gap-4">
                        <span class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl shadow-inner">⭐</span>
                        لوحة التميز الفردي
                    </h3>
                </div>
                <div class="space-y-4">
                    ${topStudents.map((s, i) => `
                        <div class="group flex items-center gap-4 md:gap-6 p-4 md:p-5 rounded-[1.5rem] md:rounded-[2rem] ${i < 3 ? 'bg-amber-50/50' : 'bg-gray-50/50'} transition-all hover:bg-white hover:shadow-xl border border-transparent hover:border-amber-100">
                            <span class="w-10 h-10 md:w-12 md:h-12 rounded-xl md:rounded-2xl flex items-center justify-center font-black transition-transform group-hover:scale-110 ${i === 0 ? 'bg-amber-500 text-white shadow-lg shadow-amber-200' : i === 1 ? 'bg-amber-400 text-white' : i === 2 ? 'bg-amber-300 text-white' : 'bg-white text-gray-400 border border-gray-100'}">${i + 1}</span>
                            <div class="flex-1 min-w-0">
                                <p class="font-black text-gray-800 text-sm md:text-base group-hover:text-amber-700 transition truncate">${esc(s.name)}</p>
                                <p class="text-[9px] md:text-[10px] text-gray-400 font-black uppercase tracking-widest">${esc(s.class_name)}</p>
                            </div>
                            <div class="text-left flex-shrink-0">
                                <span class="text-xl md:text-2xl font-black text-emerald-600">${s.avg_score}</span>
                                <p class="text-[9px] text-gray-300 font-black uppercase tracking-widest">المعدل</p>
                            </div>
                        </div>
                    `).join('')}
                    ${topStudents.length === 0 ? `<div class="p-12 text-center text-gray-300 font-bold">لا توجد بيانات حالياً</div>` : ''}
                </div>
            </div>
        </div>
    </div>`;
}

function renderParentCompetition(children, currentChild, classRanking, topStudents) {
    const mc = document.getElementById('mainContent');
    const sid = currentChild ? currentChild.studentId : (children.length > 0 ? children[0].id : null);

    mc.innerHTML = `
    <div class="fade-in px-4 md:px-0 mb-12">
        <div class="mb-10 text-center md:text-right">
            <h2 class="text-3xl md:text-4xl font-black text-gray-800 tracking-tight">🏆 منافسات الأبناء</h2>
            <p class="text-gray-500 font-bold mt-2 text-sm md:text-base">تابع الترتيب والتميز البدني لأبنائك بين زملائهم</p>
        </div>

        <!-- Children Selection Tabs -->
        <div class="flex flex-wrap gap-3 mb-12 justify-center md:justify-start">
            ${children.map(c => `
                <button onclick="renderCompetition(${c.id})" class="px-6 py-4 rounded-[1.5rem] font-black transition-all duration-300 flex items-center gap-4 ${sid == c.id ? 'bg-emerald-600 text-white shadow-xl shadow-emerald-100 scale-105' : 'bg-white text-gray-600 hover:bg-gray-50 border border-gray-100'} cursor-pointer text-sm">
                    <div class="w-8 h-8 rounded-xl ${sid == c.id ? 'bg-white/20' : 'bg-emerald-50'} flex items-center justify-center text-xl">👦</div>
                    <span>${esc(c.name)}</span>
                    ${sid == c.id ? '<span class="relative flex h-2 w-2 ml-1"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-white"></span></span>' : ''}
                </button>
            `).join('')}
        </div>

        ${currentChild ? `
        <!-- Focused Child Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8 mb-12">
            <div class="bg-gradient-to-br from-emerald-600 via-green-600 to-green-700 rounded-[2.5rem] md:rounded-[3rem] shadow-2xl shadow-emerald-100 p-8 md:p-10 text-white relative overflow-hidden group">
                <div class="absolute -right-4 -top-4 text-9xl opacity-10 transform rotate-12 transition group-hover:scale-110 group-hover:rotate-0 duration-700">🎖️</div>
                <div class="relative z-10">
                    <div class="flex items-center justify-between mb-10">
                        <h3 class="font-black text-xl md:text-2xl flex items-center gap-3"><span>🎖️</span> ترتيب المدرسة</h3>
                        <span class="bg-white/20 backdrop-blur-md px-5 py-1.5 rounded-full text-[10px] font-black uppercase tracking-[0.2em]">School Rank</span>
                    </div>
                    <div class="flex items-end gap-2">
                        <span class="text-7xl md:text-8xl font-black leading-none">${currentChild.schoolRank}</span>
                        <span class="text-2xl md:text-3xl font-bold opacity-60 mb-2">/ ${currentChild.totalInSchool}</span>
                    </div>
                    <p class="text-emerald-100 text-sm md:text-base mt-6 font-black italic opacity-80 font-mono tracking-tight">"أداء بطولي يستحق الفخر!"</p>
                    <div class="mt-10 h-3 bg-white/20 rounded-full overflow-hidden p-0.5 shadow-inner">
                        <div class="h-full bg-white rounded-full shadow-lg transition-all duration-1000 ease-out" style="width: ${((currentChild.totalInSchool - currentChild.schoolRank + 1) / currentChild.totalInSchool) * 100}%"></div>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-teal-500 via-teal-600 to-emerald-800 rounded-[2.5rem] md:rounded-[3rem] shadow-2xl shadow-teal-100 p-8 md:p-10 text-white relative overflow-hidden group">
                <div class="absolute -right-4 -top-4 text-9xl opacity-10 transform rotate-12 transition group-hover:scale-110 group-hover:rotate-0 duration-700">🏫</div>
                <div class="relative z-10">
                    <div class="flex items-center justify-between mb-10">
                        <h3 class="font-black text-xl md:text-2xl flex items-center gap-3"><span>🏫</span> ترتيب الفصل</h3>
                        <span class="bg-white/20 backdrop-blur-md px-5 py-1.5 rounded-full text-[10px] font-black uppercase tracking-[0.2em]">Class Rank</span>
                    </div>
                    <div class="flex items-end gap-2">
                        <span class="text-7xl md:text-8xl font-black leading-none">${currentChild.classRank}</span>
                        <span class="text-2xl md:text-3xl font-bold opacity-60 mb-2">/ ${currentChild.totalInClass}</span>
                    </div>
                    <p class="text-teal-100 text-sm md:text-base mt-6 font-black italic opacity-80 font-mono tracking-tight">"منافسة شرسة في قلب الفريق"</p>
                    <div class="mt-10 h-3 bg-white/20 rounded-full overflow-hidden p-0.5 shadow-inner">
                        <div class="h-full bg-white rounded-full shadow-lg transition-all duration-1000 ease-out" style="width: ${((currentChild.totalInClass - currentChild.classRank + 1) / currentChild.totalInClass) * 100}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 md:gap-12">
            <!-- Stars of the Class -->
            <div class="bg-white rounded-[2.5rem] md:rounded-[3rem] shadow-2xl shadow-gray-100/50 border border-gray-100 p-6 md:p-10">
                <div class="flex flex-col md:flex-row md:items-center justify-between mb-10 gap-4">
                    <h3 class="font-black text-xl md:text-2xl text-gray-800 flex items-center gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl">🌟</span> نجوم الفصل
                    </h3>
                    <span class="text-[10px] text-emerald-600 font-black bg-emerald-50 px-5 py-2 rounded-full uppercase tracking-widest border border-emerald-100 self-start md:self-auto">أفضل أداء بدني</span>
                </div>
                <div class="space-y-4">
                    ${currentChild.classTop3.map((s, i) => `
                        <div class="flex items-center gap-4 md:gap-5 p-4 md:p-5 rounded-[1.5rem] md:rounded-[2rem] ${s.id == sid ? 'bg-emerald-600 text-white shadow-xl shadow-emerald-100 scale-[1.03]' : 'bg-gray-50 hover:bg-white border border-transparent hover:border-gray-100 hover:shadow-lg transition-all'}">
                            <div class="w-12 h-12 flex items-center justify-center text-3xl transition-transform hover:scale-125">${i === 0 ? '🥇' : i === 1 ? '🥈' : '🥉'}</div>
                            <div class="flex-1 min-w-0">
                                <p class="font-black text-sm md:text-base truncate">${esc(s.name)} ${s.id == sid ? '<span class="text-[10px] opacity-75 font-bold mr-2 tracking-tight">(ابنك)</span>' : ''}</p>
                                <p class="text-[9px] ${s.id == sid ? 'text-emerald-100' : 'text-gray-400'} font-black uppercase tracking-widest mt-1">متوسط الدرجات البدنية</p>
                            </div>
                            <span class="text-xl md:text-2xl font-black ${s.id == sid ? 'text-white' : 'text-emerald-600'}">${s.avg_score}</span>
                        </div>
                    `).join('')}
                </div>
                <div class="mt-8 p-6 bg-yellow-50 rounded-[2rem] border border-yellow-100 flex items-start gap-4">
                    <span class="text-3xl">💡</span>
                    <p class="text-xs md:text-sm text-yellow-800 leading-relaxed font-bold italic">كلما شارك الطالب في المزيد من اختبارات اللياقة والأنشطة، زادت فرصته في الصعود للقمة والحصول على ترتيب أفضل.</p>
                </div>
            </div>

            <!-- Global Context (Mini Ranking) -->
            <div class="bg-white rounded-[2.5rem] md:rounded-[3rem] shadow-2xl shadow-gray-100/50 border border-gray-100 p-6 md:p-10">
                <h3 class="font-black text-xl md:text-2xl text-gray-800 mb-10 flex items-center gap-3">
                    <span class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">🏫</span> وضع الفصل في المدرسة
                </h3>
                <div class="space-y-4">
                    ${classRanking.slice(0, 6).map((c, i) => `
                        <div class="flex items-center gap-4 md:gap-5 p-4 md:p-5 rounded-[1.5rem] md:rounded-[2rem] ${currentChild.classId == c.class_id ? 'bg-orange-500 text-white shadow-xl shadow-orange-100 scale-[1.03]' : 'bg-gray-50 border border-transparent hover:border-gray-100 hover:bg-white hover:shadow-lg transition-all'}">
                            <span class="w-10 h-10 md:w-12 md:h-12 rounded-xl flex items-center justify-center font-black ${currentChild.classId == c.class_id ? 'bg-white text-orange-600' : 'bg-gray-200 text-gray-500'} text-xs md:text-sm">${i + 1}</span>
                            <div class="flex-1 min-w-0">
                                <p class="font-black text-sm md:text-base truncate group-hover:text-orange-600 transition-colors">${esc(c.full_class_name)} ${currentChild.classId == c.class_id ? '<span class="text-[9px] opacity-80 font-bold mr-2 tracking-tight">(فصل ابنك)</span>' : ''}</p>
                            </div>
                            <div class="text-left font-black flex flex-col items-end">
                                <span class="text-lg md:text-xl">${c.points}</span>
                                <span class="text-[8px] uppercase tracking-widest opacity-60">نقطة</span>
                            </div>
                        </div>
                    `).join('')}
                    <div class="pt-8 border-t border-gray-100 flex justify-center">
                        <p class="text-gray-400 text-[10px] font-black uppercase tracking-[0.3em] italic opacity-60 text-center">Data Updated Daily based on Weighted Performance</p>
                    </div>
                </div>
            </div>
        </div>
        ` : `
        <div class="p-20 text-center bg-white rounded-[3rem] border-4 border-dashed border-gray-100 min-h-[500px] flex flex-col items-center justify-center shadow-inner">
            <div class="text-8xl mb-8 grayscale animate-bounce">📈</div>
            <p class="text-gray-400 font-black text-3xl tracking-tight">بانتظار الاختيار...</p>
            <p class="text-gray-300 font-bold mt-4 text-lg">يرجى اختيار أحد الأبناء أعلاه لعرض لوحة النتائج التنافسية</p>
        </div>`}
    </div>`;
}
