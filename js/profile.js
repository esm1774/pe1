/**
 * PE Smart School System - Student Profile Page
 */

let profileTab = 'info';

async function renderStudentProfilePage() {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = showLoading();

    const sid = window._profileStudentId;
    if (!sid) {
        navigateTo('students');
        return;
    }

    const r = await API.get('student_profile', { student_id: sid });
    if (!r || !r.success) {
        mc.innerHTML = '<p class="text-red-500 text-center py-8">خطأ</p>';
        return;
    }

    const d = r.data;
    const s = d.student;
    const m = d.latestMeasurement;
    const h = d.activeAlerts;
    const att = d.attendance;
    const bmiColor = m ? `bmi-${m.bmi_category}` : '';

    mc.innerHTML = `
    <div class="fade-in max-w-full overflow-x-hidden">
        <div class="mb-4 mt-2 px-1">
            ${currentUser && (currentUser.role === 'admin' || currentUser.role === 'teacher' || currentUser.role === 'supervisor') ? `
                <button onclick="navigateTo('students')" class="text-emerald-600 hover:text-emerald-800 font-bold flex items-center gap-2 cursor-pointer transition text-xs md:text-base">
                    <span class="text-lg">→</span> العودة لقائمة الطلاب
                </button>
            ` : `
                <button onclick="navigateTo('dashboard')" class="text-emerald-600 hover:text-emerald-800 font-bold flex items-center gap-2 cursor-pointer transition text-xs md:text-base">
                    <span class="text-lg">→</span> العودة للرئيسية
                </button>
            `}
        </div>

        <!-- Header Card -->
        <div class="bg-white rounded-[2rem] shadow-xl shadow-gray-100/50 border border-gray-50 p-4 md:p-8 mb-6 relative overflow-hidden mx-1">
            <!-- Decorative Background -->
            <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-r from-green-400 via-emerald-500 to-teal-600"></div>
            <div class="absolute -right-20 -top-20 w-48 h-48 bg-green-50 rounded-full opacity-20 pointer-events-none"></div>

            <div class="relative z-10 flex flex-col xl:flex-row xl:items-start justify-between gap-6 md:gap-8">
                <div class="flex flex-col md:flex-row items-center md:items-start gap-4 md:gap-6 text-center md:text-right">
                    <div class="w-16 h-16 md:w-28 md:h-28 bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl md:rounded-[2rem] flex items-center justify-center text-3xl md:text-5xl text-white shadow-xl border-4 border-white transform -rotate-2">👦</div>
                    <div class="flex-1 w-full">
                        <h2 class="text-xl md:text-3xl lg:text-4xl font-black text-gray-800 mb-1.5">${esc(s.name)}</h2>
                        <div class="flex flex-wrap justify-center md:justify-start gap-1 pb-1 mb-2 md:mb-4">
                            <span class="px-2.5 py-0.5 bg-blue-50 text-blue-700 rounded-full text-[9px] md:text-xs font-black">${esc(s.full_class_name)}</span>
                            <span class="px-2.5 py-0.5 bg-gray-100 text-gray-600 rounded-full text-[9px] md:text-xs font-black font-mono">ID: ${esc(s.student_number)}</span>
                        </div>
                        <div class="grid grid-cols-2 lg:flex lg:flex-wrap justify-center md:justify-start gap-3 md:gap-4 md:gap-3">
                            ${s.date_of_birth ? `
                            <div class="flex flex-col md:flex-row md:items-center gap-0.5 md:gap-2">
                                <span class="text-[8px] text-gray-400 font-black uppercase md:hidden tracking-widest">العمر</span>
                                <span class="font-bold text-gray-700 text-xs md:text-base">🎂 ${s.age} سنة</span>
                            </div>` : ''}
                            <div class="hidden lg:block w-px h-6 bg-gray-100 self-center"></div>
                            ${s.blood_type ? `
                            <div class="flex flex-col md:flex-row md:items-center gap-0.5 md:gap-2">
                                <span class="text-[8px] text-gray-400 font-black uppercase md:hidden tracking-widest">الفصيلة</span>
                                <span class="font-bold text-purple-700 text-xs md:text-base">🩸 ${s.blood_type}</span>
                            </div>` : ''}
                            <div class="hidden lg:block w-px h-6 bg-gray-100 self-center"></div>
                            ${s.guardian_phone ? `
                            <div class="flex flex-col md:flex-row md:items-center gap-0.5 md:gap-2">
                                <span class="text-[8px] text-gray-400 font-black uppercase md:hidden tracking-widest">ولي الأمر</span>
                                <span class="font-bold text-gray-600 text-xs md:text-base">📱 ${esc(s.guardian_phone)}</span>
                            </div>` : ''}
                        </div>
                        <div class="flex flex-wrap justify-center md:justify-start gap-1.5 mt-4" id="headerBadges">
                            ${d.badges && d.badges.length > 0 ? d.badges.slice(0, 5).map(b => `<span class="w-8 h-8 rounded-xl ${b.color} text-white flex items-center justify-center text-sm shadow-md border-2 border-white" title="${esc(b.name)}">${b.icon}</span>`).join('') : ''}
                        </div>
                    </div>
                </div>

                <!-- Stats Summary -->
                <div class="grid grid-cols-3 md:grid-cols-5 xl:grid-cols-5 gap-2 w-full xl:w-auto mt-4 xl:mt-0">
                    ${m ? `
                    <div class="bg-blue-50/50 rounded-2xl p-2.5 md:p-4 text-center border border-blue-50 shadow-sm">
                        <p class="text-base md:text-2xl font-black text-blue-700">${m.height_cm || '-'}</p>
                        <p class="text-[7px] md:text-[10px] font-black text-gray-400 uppercase tracking-tighter">الطول سم</p>
                    </div>
                    <div class="bg-green-50/50 rounded-2xl p-2.5 md:p-4 text-center border border-green-50 shadow-sm">
                        <p class="text-base md:text-2xl font-black text-green-700">${m.weight_kg || '-'}</p>
                        <p class="text-[7px] md:text-[10px] font-black text-gray-400 uppercase tracking-tighter">الوزن كجم</p>
                    </div>
                    <div class="rounded-2xl p-2.5 md:p-4 text-center border shadow-sm ${bmiColor} bg-white ring-1 md:ring-4 ring-gray-50">
                        <p class="text-base md:text-2xl font-black">${m.bmi || '-'}</p>
                        <p class="text-[7px] md:text-[10px] font-black uppercase line-clamp-1 tracking-tighter">${BMI_AR[m.bmi_category] || ''}</p>
                    </div>
                    <div class="bg-red-50/50 rounded-2xl p-2.5 md:p-4 text-center border border-red-50 shadow-sm ${m.resting_heart_rate ? '' : 'opacity-40'}">
                        <p class="text-base md:text-2xl font-black text-red-600">${m.resting_heart_rate || '-'}</p>
                        <p class="text-[7px] md:text-[10px] font-black text-gray-400 uppercase tracking-tighter">النبض ❤️</p>
                    </div>
                     <div class="bg-indigo-50/50 rounded-2xl p-2.5 md:p-4 text-center border border-indigo-50 shadow-sm">
                        <p class="text-base md:text-2xl font-black text-indigo-700">${d.percentage}%</p>
                        <p class="text-[7px] md:text-[10px] font-black text-gray-400 uppercase tracking-tighter">اللياقة 💪</p>
                    </div>
                    ` : `
                    <div class="col-span-full bg-gray-50 rounded-2xl p-4 text-center border border-dashed border-gray-200">
                        <p class="text-gray-400 text-xs font-bold italic">لا توجد قياسات مسجلة</p>
                    </div>`}
                </div>
            </div>
            </div>

            ${h.length > 0 ? `
            <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-3">
                ${h.map(a => `
                <div class="health-alert rounded-[1.5rem] px-5 py-4 flex items-center justify-between gap-4 border border-red-100 shadow-sm animate-pulse-slow">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-white flex items-center justify-center text-2xl shadow-sm">${CONDITION_TYPES[a.condition_type]?.split(' ')[0] || '⚠️'}</div>
                        <div>
                            <span class="font-black text-red-900 block">${esc(a.condition_name)}</span>
                            <span class="text-[10px] text-red-700/60 font-black uppercase tracking-widest">${esc(a.notes || 'لا توجد ملاحظات إضافية')}</span>
                        </div>
                    </div>
                    <span class="px-3 py-1 bg-white/50 rounded-full text-[10px] font-black uppercase severity-${a.severity}">${SEVERITY_AR[a.severity]}</span>
                </div>
                `).join('')}
            </div>
            ` : ''}
        </div>

        <!-- Navigation Tabs -->
        <div class="bg-white/95 backdrop-blur-md border-x border-gray-100 flex overflow-x-auto no-print scrollbar-hide sticky top-[56px] z-20 mx-1 rounded-t-2xl shadow-sm">
            <button onclick="profileTab='info';renderProfileTab(${sid})" class="tab-btn min-w-[70px] flex-1 px-2 py-3.5 text-[10px] md:text-sm font-black transition-all ${profileTab === 'info' ? 'active text-emerald-600 border-b-4 border-emerald-600' : 'text-gray-400'} cursor-pointer whitespace-nowrap">📋 البيانات</button>
            <button onclick="profileTab='measurements';renderProfileTab(${sid})" class="tab-btn min-w-[70px] flex-1 px-2 py-3.5 text-[10px] md:text-sm font-black transition-all ${profileTab === 'measurements' ? 'active text-emerald-600 border-b-4 border-emerald-600' : 'text-gray-400'} cursor-pointer whitespace-nowrap">📏 القياسات</button>
            <button onclick="profileTab='pfitness';renderProfileTab(${sid})" class="tab-btn min-w-[70px] flex-1 px-2 py-3.5 text-[10px] md:text-sm font-black transition-all ${profileTab === 'pfitness' ? 'active text-emerald-600 border-b-4 border-emerald-600' : 'text-gray-400'} cursor-pointer whitespace-nowrap">💪 اللياقة</button>
            <button onclick="profileTab='badges';renderProfileTab(${sid})" class="tab-btn min-w-[70px] flex-1 px-2 py-3.5 text-[10px] md:text-sm font-black transition-all ${profileTab === 'badges' ? 'active text-emerald-600 border-b-4 border-emerald-600' : 'text-gray-400'} cursor-pointer whitespace-nowrap">🏅 الأوسمة</button>
            <button onclick="profileTab='health';renderProfileTab(${sid})" class="tab-btn min-w-[70px] flex-1 px-2 py-3.5 text-[10px] md:text-sm font-black transition-all ${profileTab === 'health' ? 'active text-emerald-600 border-b-4 border-emerald-600' : 'text-gray-400'} cursor-pointer whitespace-nowrap">🏥 الصحة</button>
            <button onclick="profileTab='pattendance';renderProfileTab(${sid})" class="tab-btn min-w-[70px] flex-1 px-2 py-3.5 text-[10px] md:text-sm font-black transition-all ${profileTab === 'pattendance' ? 'active text-emerald-600 border-b-4 border-emerald-600' : 'text-gray-400'} cursor-pointer whitespace-nowrap">📅 الحضور</button>
        </div>
        <div id="profileTabContent" class="bg-white rounded-b-[2rem] shadow-2xl shadow-gray-100/50 border border-gray-100 border-t-0 p-4 md:p-10 mb-20 mx-1 scroll-mt-24"></div>
    </div>`;

    renderProfileTab(sid);
}

async function renderProfileTab(sid) {
    const tc = document.getElementById('profileTabContent');
    if (!tc) return;

    // Update Tab UI
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active', 'text-emerald-600', 'border-emerald-600', 'border-b-4');
        btn.classList.add('text-gray-400');
    });

    // Find active button
    const activeBtn = Array.from(document.querySelectorAll('.tab-btn')).find(b => b.getAttribute('onclick')?.includes(`profileTab='${profileTab}'`));
    if (activeBtn) {
        activeBtn.classList.remove('text-gray-400');
        activeBtn.classList.add('active', 'text-emerald-600', 'border-emerald-600', 'border-b-4');
    }

    tc.innerHTML = showLoading();

    const r = await API.get('student_profile', { student_id: sid });
    if (!r || !r.success) return;

    const d = r.data;
    const s = d.student;

    if (profileTab === 'info') {
        tc.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-3">
                <h4 class="font-bold text-gray-800 mb-2">البيانات الشخصية</h4>
                <div class="flex justify-between py-2 border-b border-gray-100"><span class="text-gray-500">الاسم</span><span class="font-semibold">${esc(s.name)}</span></div>
                <div class="flex justify-between py-2 border-b border-gray-100"><span class="text-gray-500">رقم الطالب</span><span class="font-semibold font-mono">${esc(s.student_number)}</span></div>
                <div class="flex justify-between py-2 border-b border-gray-100"><span class="text-gray-500">الفصل</span><span class="font-semibold">${esc(s.full_class_name)}</span></div>
                <div class="flex justify-between py-2 border-b border-gray-100"><span class="text-gray-500">تاريخ الميلاد</span><span class="font-semibold">${s.date_of_birth || '-'}</span></div>
                <div class="flex justify-between py-2 border-b border-gray-100"><span class="text-gray-500">العمر</span><span class="font-semibold">${s.age ? s.age + ' سنة' : '-'}</span></div>
                <div class="flex justify-between py-2 border-b border-gray-100"><span class="text-gray-500">فصيلة الدم</span><span class="badge bg-emerald-100 text-emerald-700">${s.blood_type || '-'}</span></div>
                <div class="flex justify-between py-2 border-b border-gray-100"><span class="text-gray-500">رقم ولي الأمر</span><span class="font-semibold">${esc(s.guardian_phone) || '-'}</span></div>
                ${s.medical_notes ? `<div class="py-2"><span class="text-gray-500">ملاحظات طبية:</span><p class="mt-1 text-sm bg-yellow-50 p-3 rounded-xl border border-yellow-200">${esc(s.medical_notes)}</p></div>` : ''}
            </div>
            <div>
                <h4 class="font-bold text-gray-800 mb-2">ملخص الأداء</h4>
                <div class="grid grid-cols-3 gap-3 mb-4">
                    <div class="bg-green-50 rounded-xl p-3 text-center"><p class="text-2xl font-bold text-green-600">${d.attendance.present}</p><p class="text-xs text-gray-600">حضور</p></div>
                    <div class="bg-red-50 rounded-xl p-3 text-center"><p class="text-2xl font-bold text-red-600">${d.attendance.absent}</p><p class="text-xs text-gray-600">غياب</p></div>
                    <div class="bg-yellow-50 rounded-xl p-3 text-center"><p class="text-2xl font-bold text-yellow-600">${d.attendance.late}</p><p class="text-xs text-gray-600">تأخر</p></div>
                </div>
                <div class="bg-gradient-to-l from-green-500 to-emerald-600 rounded-xl p-4 text-white text-center">
                    <p class="text-4xl font-black">${d.percentage}%</p>
                    <p class="text-sm opacity-80">التقييم العام للياقة</p>
                    <p class="text-lg font-bold mt-1">${d.totalScore} / ${d.totalMax}</p>
                </div>
                
                ${canEdit() ? `
                <div class="mt-6">
                    <h4 class="font-bold text-gray-800 mb-3 flex items-center gap-2">📜 شهادات التفوق والتقدير</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <button onclick="showSportsCertificate('excellence', '${esc(s.name)}', ${JSON.stringify({ percentage: d.percentage, totalScore: d.totalScore, totalMax: d.totalMax, badges: d.badges?.length || 0, attendance: d.attendance }).replace(/"/g, '&quot;')})" class="bg-gradient-to-r from-amber-500 to-yellow-500 text-white px-4 py-3 rounded-2xl font-black text-xs hover:shadow-lg transition flex items-center gap-2 cursor-pointer justify-center active:scale-95">
                            <span>🏆</span> شهادة تفوق رياضي
                        </button>
                        <button onclick="showSportsCertificate('appreciation', '${esc(s.name)}', ${JSON.stringify({ percentage: d.percentage, totalScore: d.totalScore, totalMax: d.totalMax, badges: d.badges?.length || 0, attendance: d.attendance }).replace(/"/g, '&quot;')})" class="bg-gradient-to-r from-blue-500 to-indigo-500 text-white px-4 py-3 rounded-2xl font-black text-xs hover:shadow-lg transition flex items-center gap-2 cursor-pointer justify-center active:scale-95">
                            <span>🎖️</span> شهادة تقدير
                        </button>
                        <button onclick="showSportsCertificate('sports_star', '${esc(s.name)}', ${JSON.stringify({ percentage: d.percentage, totalScore: d.totalScore, totalMax: d.totalMax, badges: d.badges?.length || 0, attendance: d.attendance }).replace(/"/g, '&quot;')})" class="bg-gradient-to-r from-emerald-500 to-teal-500 text-white px-4 py-3 rounded-2xl font-black text-xs hover:shadow-lg transition flex items-center gap-2 cursor-pointer justify-center active:scale-95">
                            <span>⭐</span> نجم الرياضة
                        </button>
                        <button onclick="showSportsCertificate('attendance', '${esc(s.name)}', ${JSON.stringify({ percentage: d.percentage, totalScore: d.totalScore, totalMax: d.totalMax, badges: d.badges?.length || 0, attendance: d.attendance }).replace(/"/g, '&quot;')})" class="bg-gradient-to-r from-purple-500 to-pink-500 text-white px-4 py-3 rounded-2xl font-black text-xs hover:shadow-lg transition flex items-center gap-2 cursor-pointer justify-center active:scale-95">
                            <span>📅</span> شهادة انتظام
                        </button>
                    </div>
                </div>
                ` : ''}
            </div>
        </div>`;
    } else if (profileTab === 'measurements') {
        const meas = d.measurements || [];
        tc.innerHTML = `
        <div class="fade-in">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div>
                    <h4 class="text-xl font-black text-gray-800">📏 سجل القياسات الجسمية</h4>
                    <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mt-1">تتبع رحلة التغير البدني للطالب</p>
                </div>
                ${canEdit() ? `<button onclick="showMeasurementForm(${sid})" class="bg-green-600 text-white px-6 py-3 rounded-2xl font-black hover:bg-green-700 transition shadow-lg shadow-green-100 flex items-center justify-center gap-2 text-sm"><span>➕</span> قياس جديد</button>` : ''}
            </div>

            ${meas.length > 0 ? `
            <!-- Desktop Layout -->
            <div class="hidden lg:block overflow-hidden rounded-3xl border border-gray-100 bg-gray-50/30">
                <table class="w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-4 text-right text-xs font-black text-gray-400 uppercase tracking-widest">التاريخ</th>
                            <th class="px-4 py-4 text-center text-xs font-black text-gray-400 uppercase tracking-widest">الطول</th>
                            <th class="px-4 py-4 text-center text-xs font-black text-gray-400 uppercase tracking-widest">الوزن</th>
                            <th class="px-4 py-4 text-center text-xs font-black text-gray-400 uppercase tracking-widest">BMI</th>
                            <th class="px-4 py-4 text-center text-xs font-black text-gray-400 uppercase tracking-widest">التصنيف</th>
                            <th class="px-4 py-4 text-center text-xs font-black text-gray-400 uppercase tracking-widest">الخصر</th>
                            <th class="px-4 py-4 text-center text-xs font-black text-gray-400 uppercase tracking-widest">النبض</th>
                            <th class="px-4 py-4 text-right text-xs font-black text-gray-400 uppercase tracking-widest">ملاحظات</th>
                            ${canEdit() ? '<th class="px-4 py-4 text-center"></th>' : ''}
                        </tr>
                    </thead>
                    <tbody>
                        ${meas.map(m => `
                        <tr class="border-t border-gray-50 hover:bg-white transition-colors">
                            <td class="px-4 py-4 text-sm font-black text-gray-900">${m.measurement_date}</td>
                            <td class="px-4 py-4 text-center text-sm font-bold">${m.height_cm || '-'} <span class="text-[10px] text-gray-400">سم</span></td>
                            <td class="px-4 py-4 text-center text-sm font-bold">${m.weight_kg || '-'} <span class="text-[10px] text-gray-400">كجم</span></td>
                            <td class="px-4 py-4 text-center text-lg font-black">${m.bmi || '-'}</td>
                            <td class="px-4 py-4 text-center"><span class="px-3 py-1 rounded-full text-[10px] font-black uppercase ${m.bmi_category ? 'bmi-' + m.bmi_category : ''}">${BMI_AR[m.bmi_category] || '-'} ${BMI_ICONS[m.bmi_category] || ''}</span></td>
                            <td class="px-4 py-4 text-center text-sm">${m.waist_cm || '-'} <span class="text-[10px] text-gray-400">سم</span></td>
                            <td class="px-4 py-4 text-center text-sm font-black text-red-500">${m.resting_heart_rate ? m.resting_heart_rate + ' ❤️' : '-'}</td>
                            <td class="px-4 py-4 text-xs text-gray-500 leading-relaxed">${m.notes ? esc(m.notes) : '-'}</td>
                            ${canEdit() ? `<td class="px-4 py-4 text-center"><button onclick="deleteMeasurement(${m.id},${sid})" class="text-gray-300 hover:text-red-500 transition cursor-pointer">🗑️</button></td>` : ''}
                        </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>

            <!-- Mobile/Tablet Layout (Cards) -->
            <div class="lg:hidden space-y-4">
                ${meas.map(m => `
                <div class="bg-gray-50 rounded-[2rem] p-5 border border-gray-50 relative overflow-hidden transition active:scale-95">
                    <div class="flex items-center justify-between mb-4">
                        <span class="text-sm font-black text-gray-900 bg-white px-3 py-1 rounded-full shadow-sm">${m.measurement_date}</span>
                        <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase ${m.bmi_category ? 'bmi-' + m.bmi_category : ''}">${BMI_AR[m.bmi_category] || '-'} ${BMI_ICONS[m.bmi_category] || ''}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="bg-white/60 rounded-2xl p-3">
                            <p class="text-[9px] text-gray-400 font-black uppercase mb-1">الطول والوزن</p>
                            <p class="font-black text-gray-800 text-sm">📐 ${m.height_cm || '-'}سم • ⚖️ ${m.weight_kg || '-'}كجم</p>
                        </div>
                        <div class="bg-white/60 rounded-2xl p-3">
                            <p class="text-[9px] text-gray-400 font-black uppercase mb-1">المؤشر BMI</p>
                            <p class="font-black text-emerald-700 text-lg">${m.bmi || '-'}</p>
                        </div>
                        <div class="bg-white/60 rounded-2xl p-3">
                            <p class="text-[9px] text-gray-400 font-black uppercase mb-1">النبض</p>
                            <p class="font-black text-red-600 text-sm">${m.resting_heart_rate ? m.resting_heart_rate + ' ❤️' : '-'}</p>
                        </div>
                        <div class="bg-white/60 rounded-2xl p-3">
                            <p class="text-[9px] text-gray-400 font-black uppercase mb-1">الخصر</p>
                            <p class="font-black text-orange-600 text-sm">${m.waist_cm ? m.waist_cm + ' سم' : '-'}</p>
                        </div>
                    </div>
                    ${m.notes ? `<p class="mt-3 text-[10px] text-gray-500 italic px-2">${esc(m.notes)}</p>` : ''}
                    ${canEdit() ? `<button onclick="deleteMeasurement(${m.id},${sid})" class="absolute top-4 left-4 text-gray-300 hover:text-red-500 transition cursor-pointer">🗑️</button>` : ''}
                </div>
                `).join('')}
            </div>
            ` : '<div class="text-center py-20 bg-gray-50 rounded-[2.5rem] border-2 border-dashed border-gray-100"><p class="text-6xl mb-4 grayscale opacity-20">📏</p><p class="text-gray-400 font-black">لا توجد قياسات مسجلة حالياً</p></div>'}
        </div>`;
    } else if (profileTab === 'health') {
        const conditions = d.healthConditions || [];
        const active = conditions.filter(c => c.is_active == 1);
        const inactive = conditions.filter(c => c.is_active != 1);

        tc.innerHTML = `
        <div class="fade-in">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div>
                    <h4 class="text-xl font-black text-gray-800">🏥 الحالة الصحية والقيود</h4>
                    <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mt-1">الملاحظات الطبية والتوصيات الرياضية</p>
                </div>
                ${canEdit() ? `<button onclick="showHealthForm(${sid})" class="bg-green-600 text-white px-6 py-3 rounded-2xl font-black hover:bg-green-700 transition shadow-lg shadow-green-100 flex items-center justify-center gap-2 text-sm text-sm"><span>➕</span> إضافة حالة</button>` : ''}
            </div>
            
            ${active.length > 0 ? `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                ${active.map(c => `
                <div class="health-alert rounded-[2rem] p-6 border border-red-50 relative group">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 rounded-2xl bg-white flex items-center justify-center text-3xl shadow-sm group-hover:scale-110 transition">${CONDITION_TYPES[c.condition_type]?.split(' ')[0] || '⚠️'}</div>
                            <div>
                                <p class="font-black text-red-900 text-lg">${esc(c.condition_name)}</p>
                                <p class="text-xs text-red-700/60 font-black uppercase tracking-widest">${CONDITION_TYPES[c.condition_type] || c.condition_type}</p>
                            </div>
                        </div>
                        <span class="px-3 py-1 bg-white text-[10px] font-black uppercase severity-${c.severity} rounded-full border border-red-100">${SEVERITY_AR[c.severity]}</span>
                    </div>
                    ${c.notes ? `<div class="bg-white/40 rounded-2xl p-4 text-sm text-red-800 leading-relaxed italic border border-red-50/50">"${esc(c.notes)}"</div>` : ''}
                    ${canEdit() ? `<button onclick="deleteHealth(${c.id},${sid})" class="absolute top-4 left-4 text-red-200 hover:text-red-600 transition cursor-pointer">🗑️</button>` : ''}
                </div>
                `).join('')}
            </div>
            ` : `
            <div class="text-center py-20 bg-green-50/50 rounded-[2.5rem] border-2 border-dashed border-green-100">
                <p class="text-6xl mb-4">✅</p>
                <p class="text-green-800 font-black text-lg">لا توجد قياسات صحية مسجلة</p>
                <p class="text-green-600 text-sm mt-1">الطالب مؤهل بدنياً لجميع الأنشطة</p>
            </div>`}
        </div>`;
    } else if (profileTab === 'pfitness') {
        const fit = d.fitness || [];
        tc.innerHTML = `
        <div class="fade-in">
            <div class="mb-6">
                <h4 class="text-xl font-black text-gray-800">💪 سجل الأداء البدني</h4>
                <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mt-1">نتائج الطالب في الاختبارات الرسمية</p>
            </div>

            <!-- Dashboard Style Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-2 gap-4 md:gap-6 mb-8">
                ${fit.map(f => `
                <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm relative overflow-hidden group">
                    <div class="absolute top-0 left-0 w-2 h-full ${f.score / f.max_score >= 0.8 ? 'bg-green-500' : f.score / f.max_score >= 0.5 ? 'bg-yellow-500' : 'bg-red-500'}"></div>
                    <div class="flex justify-between items-center mb-4">
                        <h5 class="font-black text-gray-800 text-lg">${esc(f.test_name)}</h5>
                        <span class="px-3 py-1 bg-gray-50 rounded-full text-xs font-black text-gray-400">${f.test_date || '-'}</span>
                    </div>
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="text-[10px] text-gray-400 font-black uppercase mb-1">النتيجة</p>
                            <p class="text-2xl font-black text-gray-900">${f.value !== null ? f.value + ' <span class="text-sm">' + f.unit + '</span>' : '-'}</p>
                        </div>
                        <div class="text-left">
                            <p class="text-[10px] text-gray-400 font-black uppercase mb-1">الدرجة المستحقة</p>
                            <p class="text-2xl font-black ${f.score / f.max_score >= 0.8 ? 'text-green-600' : f.score / f.max_score >= 0.5 ? 'text-yellow-600' : 'text-red-600'}">${f.score !== null ? f.score + '/' + f.max_score : '-'}</p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-1000 ${f.score / f.max_score >= 0.8 ? 'bg-green-500' : f.score / f.max_score >= 0.5 ? 'bg-yellow-500' : 'bg-red-500'}" style="width: ${f.score / f.max_score * 100}%"></div>
                        </div>
                    </div>
                </div>
                `).join('')}
            </div>

            <!-- Total Score Card -->
            <div class="bg-gradient-to-br from-gray-900 to-black rounded-[2.5rem] p-8 text-white relative overflow-hidden">
                <div class="absolute -right-20 -bottom-20 w-64 h-64 bg-white/5 rounded-full blur-3xl"></div>
                <div class="relative z-10 flex flex-col lg:flex-row items-center justify-between gap-8 text-center lg:text-right">
                    <div>
                        <h4 class="text-3xl font-black mb-2">التقييم العام للياقة البدنية</h4>
                        <p class="text-gray-400 font-bold">بناءً على متوسط نتائج جميع الاختبارات المؤداة</p>
                    </div>
                    <div class="flex items-center gap-6">
                        <div class="text-center">
                            <p class="text-5xl font-black text-green-400">${d.percentage}%</p>
                            <p class="text-xs text-gray-500 font-black uppercase tracking-widest mt-1">النسبة المئوية</p>
                        </div>
                        <div class="w-px h-16 bg-gray-800"></div>
                        <div class="text-center">
                            <p class="text-5xl font-black">${d.totalScore}/${d.totalMax}</p>
                            <p class="text-xs text-gray-500 font-black uppercase tracking-widest mt-1">إجمالي النقاط</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>`;
    } else if (profileTab === 'badges') {
        const badges = d.badges || [];
        tc.innerHTML = `
        <div class="fade-in">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6 mb-8">
                <div>
                    <h4 class="text-2xl font-black text-gray-800">🏅 سجل التميز والأوسمة</h4>
                    <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mt-1">الأوسمة الرقمية التي حصل عليها الطالب</p>
                </div>
                ${canEdit() ? `
                <div class="flex flex-wrap gap-2">
                    <button onclick="showAwardBadgeForm(${sid})" class="bg-emerald-600 text-white px-6 py-3 rounded-2xl font-black hover:bg-emerald-700 transition shadow-xl shadow-emerald-100 flex items-center justify-center gap-2 text-sm"><span>✨</span> منح وسام جديد</button>
                    <button onclick="showSportsCertificate('excellence', '${esc(s.name)}', ${JSON.stringify({ percentage: d.percentage, totalScore: d.totalScore, totalMax: d.totalMax, badges: d.badges?.length || 0, attendance: d.attendance }).replace(/"/g, '&quot;')})" class="bg-amber-500 text-white px-6 py-3 rounded-2xl font-black hover:bg-amber-600 transition shadow-xl shadow-amber-100 flex items-center justify-center gap-2 text-sm cursor-pointer"><span>📜</span> شهادة تفوق</button>
                </div>` : ''}
            </div>

            ${badges.length > 0 ? `
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
                ${badges.map(b => `
                <div class="bg-white rounded-[2.5rem] p-8 border border-gray-100 relative overflow-hidden group hover:shadow-2xl hover:-translate-y-2 transition-all duration-500 flex flex-col justify-between min-h-[320px]">
                    <div class="absolute top-0 right-0 w-32 h-32 ${b.color} opacity-5 rounded-full -mr-16 -mt-16 transition group-hover:scale-150"></div>
                    
                    <div>
                        <div class="flex items-center gap-5 mb-6 relative z-10">
                            <div class="w-20 h-20 rounded-[1.5rem] ${b.color} text-white flex items-center justify-center text-4xl shadow-2xl group-hover:rotate-12 transition duration-500 transform border-4 border-white">${b.icon}</div>
                            <div>
                                <h5 class="font-black text-gray-800 text-lg leading-tight">${esc(b.name)}</h5>
                                <p class="text-[10px] text-gray-400 font-black uppercase tracking-widest mt-1">${new Date(b.awarded_at).toLocaleDateString('ar-SA')}</p>
                            </div>
                        </div>
                        <p class="text-sm text-gray-500 leading-relaxed mb-8 font-medium">${esc(b.description || 'تم منح هذا الوسام نظير التميز والأداء الاستثنائي في حصص التربية البدنية.')}</p>
                    </div>
                    
                    <button onclick='showBadgeCertificate(${JSON.stringify(b).replace(/'/g, "&apos;")}, "${esc(s.name)}")' class="w-full bg-emerald-50 text-emerald-700 py-4 rounded-2xl text-xs font-black hover:bg-emerald-600 hover:text-white transition-all duration-300 flex items-center justify-center gap-2 cursor-pointer relative z-10 shadow-sm border border-emerald-100">
                        <span>📜</span> استعراض شهادة التكريم
                    </button>

                    ${canEdit() ? `<button onclick="revokeBadge(${b.id},${sid})" class="absolute top-6 left-6 text-gray-200 hover:text-red-500 transition cursor-pointer" title="سحب الوسام">🗑️</button>` : ''}
                </div>
                `).join('')}
            </div>
            ` : `
            <div class="text-center py-24 bg-gray-50/50 rounded-[3rem] border-2 border-dashed border-gray-100">
                <div class="w-24 h-24 bg-white rounded-full flex items-center justify-center text-5xl mx-auto mb-6 shadow-sm grayscale opacity-30">🥈</div>
                <p class="text-gray-400 font-black text-xl">لا توجد أوسمة مسجلة بعد</p>
                <p class="text-gray-300 text-sm mt-2">البدايات العظيمة تبدأ بخطوة صغيرة!</p>
            </div>`}
        </div>`;
    } else if (profileTab === 'pattendance') {
        const a = d.attendance;
        const total = a.present + a.absent + a.late;
        tc.innerHTML = `
        <div class="fade-in">
            <div class="mb-8">
                <h4 class="text-xl font-black text-gray-800">📅 سجل التفويض والحضور</h4>
                <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mt-1">نسبة التزام الطالب بحضور حصص التربية البدنية</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                <div class="bg-green-50 rounded-[2rem] p-8 text-center border border-green-100 group hover:scale-105 transition-transform">
                    <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center text-3xl mx-auto mb-4 shadow-sm group-hover:bg-green-500 group-hover:text-white transition-colors">🟢</div>
                    <p class="text-5xl font-black text-green-600 mb-1">${a.present}</p>
                    <p class="text-xs text-green-800 font-black uppercase tracking-widest">يوم حضور</p>
                    ${total ? `<div class="mt-4 px-3 py-1 bg-white/50 rounded-full inline-block text-[10px] font-black pointer-events-none">${Math.round(a.present / total * 100)}% من الإجمالي</div>` : ''}
                </div>
                <div class="bg-red-50 rounded-[2rem] p-8 text-center border border-red-100 group hover:scale-105 transition-transform">
                    <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center text-3xl mx-auto mb-4 shadow-sm group-hover:bg-red-500 group-hover:text-white transition-colors">🔴</div>
                    <p class="text-5xl font-black text-red-600 mb-1">${a.absent}</p>
                    <p class="text-xs text-red-800 font-black uppercase tracking-widest">يوم غياب</p>
                    ${total ? `<div class="mt-4 px-3 py-1 bg-white/50 rounded-full inline-block text-[10px] font-black pointer-events-none">${Math.round(a.absent / total * 100)}% من الإجمالي</div>` : ''}
                </div>
                <div class="bg-yellow-50 rounded-[2rem] p-8 text-center border border-yellow-100 group hover:scale-105 transition-transform">
                    <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center text-3xl mx-auto mb-4 shadow-sm group-hover:bg-yellow-500 group-hover:text-white transition-colors">🟡</div>
                    <p class="text-5xl font-black text-yellow-600 mb-1">${a.late}</p>
                    <p class="text-xs text-yellow-800 font-black uppercase tracking-widest">يوم تأخر</p>
                    ${total ? `<div class="mt-4 px-3 py-1 bg-white/50 rounded-full inline-block text-[10px] font-black pointer-events-none">${Math.round(a.late / total * 100)}% من الإجمالي</div>` : ''}
                </div>
            </div>

            ${total > 0 ? `
            <div class="bg-gray-50 rounded-[2.5rem] p-8 border border-gray-100">
                <div class="flex flex-wrap justify-between items-center mb-6 gap-4 text-center md:text-right">
                    <div>
                        <h5 class="font-black text-gray-800">مؤشر الحضور التراكمي</h5>
                        <p class="text-sm text-gray-500">العارضة تمثل توزيع الأيام المسجلة</p>
                    </div>
                    <div class="bg-white px-6 py-2 rounded-2xl shadow-sm border border-gray-100">
                        <span class="text-3xl font-black text-green-600">${Math.round((a.present + (a.late * 0.5)) / total * 100)}%</span>
                        <span class="text-[10px] text-gray-400 font-black uppercase block">معدل الالتزام</span>
                    </div>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-10 overflow-hidden flex ring-8 ring-white shadow-inner">
                    <div class="bg-green-500 h-full flex items-center justify-center text-[10px] text-white font-bold" style="width:${a.present / total * 100}%">${a.present > 0 ? 'حضور' : ''}</div>
                    <div class="bg-yellow-400 h-full flex items-center justify-center text-[10px] text-white font-bold" style="width:${a.late / total * 100}%">${a.late > 0 ? 'تأخر' : ''}</div>
                    <div class="bg-red-500 h-full flex items-center justify-center text-[10px] text-white font-bold" style="width:${a.absent / total * 100}%">${a.absent > 0 ? 'غياب' : ''}</div>
                </div>
                <div class="flex justify-center md:justify-start gap-4 mt-6">
                    <div class="flex items-center gap-2"><div class="w-3 h-3 bg-green-500 rounded-full"></div> <span class="text-xs font-black text-gray-400">حضور</span></div>
                    <div class="flex items-center gap-2"><div class="w-3 h-3 bg-yellow-400 rounded-full"></div> <span class="text-xs font-black text-gray-400">تأخر</span></div>
                    <div class="flex items-center gap-2"><div class="w-3 h-3 bg-red-500 rounded-full"></div> <span class="text-xs font-black text-gray-400">غياب</span></div>
                </div>
            </div>
            ` : `
            <div class="text-center py-20 bg-gray-50 rounded-[2.5rem] border-2 border-dashed border-gray-100">
                <p class="text-6xl mb-4 grayscale opacity-20">📅</p>
                <p class="text-gray-400 font-black">لم يتم تسجيل أي سجلات حضور لهذا الطالب</p>
            </div>`}
        </div>`;
    }
}

// Measurement Form
async function showMeasurementForm(studentId) {
    showModal(`
        <div class="p-6">
            <h3 class="text-xl font-bold mb-4">📏 إضافة قياسات جسمية</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">تاريخ القياس *</label>
                    <input type="date" id="measDate" value="${new Date().toISOString().split('T')[0]}" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">الطول (سم) 📐</label>
                        <input type="number" step="0.1" id="measHeight" oninput="autoCalcBMI()" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none" placeholder="مثال: 170">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">الوزن (كجم) ⚖️</label>
                        <input type="number" step="0.1" id="measWeight" oninput="autoCalcBMI()" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none" placeholder="مثال: 65">
                    </div>
                </div>
                <div id="bmiPreview" class="rounded-xl p-3 text-center hidden">
                    <p class="text-sm text-gray-600">مؤشر كتلة الجسم BMI</p>
                    <p id="bmiValue" class="text-2xl font-bold"></p>
                    <p id="bmiLabel" class="text-sm"></p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">محيط الخصر (سم) 📏</label>
                        <input type="number" step="0.1" id="measWaist" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none" placeholder="مثال: 75">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">نبض القلب (نبضة/دقيقة) ❤️</label>
                        <input type="number" id="measHeartRate" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none" placeholder="مثال: 72">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">ملاحظات 📝</label>
                    <textarea id="measNotes" rows="2" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none" placeholder="أي ملاحظات إضافية عن القياسات..."></textarea>
                </div>
                <div class="flex gap-3 pt-2">
                    <button onclick="saveMeasurement(${studentId})" class="flex-1 bg-green-600 text-white py-3 rounded-xl font-bold hover:bg-green-700 cursor-pointer">💾 حفظ القياسات</button>
                    <button onclick="closeModal()" class="flex-1 bg-gray-200 text-gray-700 py-3 rounded-xl font-bold hover:bg-gray-300 cursor-pointer">إلغاء</button>
                </div>
            </div>
        </div>
    `);
}

function autoCalcBMI() {
    const h = parseFloat(document.getElementById('measHeight')?.value);
    const w = parseFloat(document.getElementById('measWeight')?.value);
    const prev = document.getElementById('bmiPreview');
    const val = document.getElementById('bmiValue');
    const lbl = document.getElementById('bmiLabel');

    if (!prev) return;

    if (h > 0 && w > 0) {
        const { bmi, cat } = calcBMI(h, w);
        prev.classList.remove('hidden', 'bmi-underweight', 'bmi-normal', 'bmi-overweight', 'bmi-obese');
        prev.classList.add('bmi-' + cat);
        val.textContent = bmi;
        lbl.textContent = `${BMI_AR[cat]} ${BMI_ICONS[cat]}`;
    } else {
        prev.classList.add('hidden');
    }
}

async function saveMeasurement(studentId) {
    const data = {
        student_id: studentId,
        measurement_date: document.getElementById('measDate').value,
        height_cm: document.getElementById('measHeight').value,
        weight_kg: document.getElementById('measWeight').value,
        waist_cm: document.getElementById('measWaist').value,
        resting_heart_rate: document.getElementById('measHeartRate').value,
        notes: document.getElementById('measNotes').value.trim()
    };

    if (!data.measurement_date) {
        showToast('أدخل التاريخ', 'error');
        return;
    }

    if (!data.height_cm && !data.weight_kg) {
        showToast('أدخل الطول أو الوزن على الأقل', 'error');
        return;
    }

    const r = await API.post('measurement_save', data);
    if (r && r.success) {
        closeModal();
        showToast(r.message);
        renderProfileTab(studentId);
    } else if (r) {
        showToast(r.error, 'error');
    }
}

async function deleteMeasurement(id, sid) {
    if (!confirm('حذف القياس؟')) return;
    const r = await API.post('measurement_delete', null, { id });
    if (r && r.success) {
        showToast(r.message);
        renderProfileTab(sid);
    }
}

// Health Form
async function showHealthForm(studentId) {
    showModal(`
        <div class="p-6">
            <h3 class="text-xl font-bold mb-4">إضافة حالة صحية</h3>
            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">نوع الحالة *</label>
                        <select id="healthType" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                            ${Object.entries(CONDITION_TYPES).map(([k, v]) => `<option value="${k}">${v}</option>`).join('')}
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">اسم الحالة *</label>
                        <input type="text" id="healthName" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none" placeholder="مثال: ربو تحسسي">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">الشدة</label>
                    <select id="healthSeverity" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                        <option value="mild">🟡 خفيف</option>
                        <option value="moderate">🟠 متوسط</option>
                        <option value="severe">🔴 شديد</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">ملاحظات وتوصيات</label>
                    <textarea id="healthNotes" rows="3" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none" placeholder="مثال: يحتاج بخاخ قبل الجري"></textarea>
                </div>
                <div class="flex gap-3 pt-2">
                    <button onclick="saveHealth(${studentId})" class="flex-1 bg-green-600 text-white py-3 rounded-xl font-bold hover:bg-green-700 cursor-pointer">حفظ</button>
                    <button onclick="closeModal()" class="flex-1 bg-gray-200 text-gray-700 py-3 rounded-xl font-bold hover:bg-gray-300 cursor-pointer">إلغاء</button>
                </div>
            </div>
        </div>
    `);
}

async function saveHealth(studentId) {
    const data = {
        student_id: studentId,
        condition_type: document.getElementById('healthType').value,
        condition_name: document.getElementById('healthName').value.trim(),
        severity: document.getElementById('healthSeverity').value,
        notes: document.getElementById('healthNotes').value.trim(),
        is_active: 1
    };

    if (!data.condition_name) {
        showToast('أدخل اسم الحالة', 'error');
        return;
    }

    const r = await API.post('health_save', data);
    if (r && r.success) {
        closeModal();
        showToast(r.message);
        renderProfileTab(studentId);
    } else if (r) {
        showToast(r.error, 'error');
    }
}

async function showAwardBadgeForm(studentId) {
    const r = await API.get('get_badges');
    if (!r || !r.success) return;
    const badges = r.data;

    showModal(`
        <div class="p-8">
            <h3 class="text-2xl font-black text-gray-800 mb-6 flex items-center gap-2">✨ منح وسام جديد</h3>
            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-black text-gray-500 uppercase tracking-widest mb-2">اختر الوسام المناسب</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        ${badges.map(b => `
                            <label class="relative flex items-center gap-4 p-4 rounded-2xl border-2 border-gray-100 cursor-pointer hover:border-emerald-100 hover:bg-emerald-50/30 transition has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50">
                                <input type="radio" name="badge_id" value="${b.id}" class="peer hidden">
                                <div class="w-12 h-12 rounded-xl ${b.color} text-white flex items-center justify-center text-xl shadow-md">${b.icon}</div>
                                <div class="flex-1">
                                    <p class="font-black text-gray-800 text-sm">${esc(b.name)}</p>
                                    <div class="absolute top-4 left-4 hidden peer-checked:block text-emerald-500">✅</div>
                                </div>
                            </label>
                        `).join('')}
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-black text-gray-500 uppercase tracking-widest mb-2">ملاحظات إضافية (اختياري)</label>
                    <textarea id="badgeNotes" rows="2" class="w-full px-4 py-3 border-2 border-gray-200 rounded-2xl focus:border-emerald-500 focus:outline-none placeholder-gray-300 text-sm" placeholder="لماذا يستحق الطالب هذا الوسام؟"></textarea>
                </div>
                <div class="flex gap-4 pt-2">
                    <button onclick="submitAwardBadge(${studentId})" class="flex-1 bg-emerald-600 text-white py-4 rounded-2xl font-black hover:bg-emerald-700 shadow-xl shadow-emerald-100 transition cursor-pointer">✨ تأكيد المنح</button>
                    <button onclick="closeModal()" class="flex-1 bg-gray-100 text-gray-500 py-4 rounded-2xl font-black hover:bg-gray-200 transition cursor-pointer">إلغاء</button>
                </div>
            </div>
        </div>
    `);
}

async function submitAwardBadge(studentId) {
    const badgeId = document.querySelector('input[name="badge_id"]:checked')?.value;
    const notes = document.getElementById('badgeNotes').value.trim();

    if (!badgeId) {
        showToast('يرجى اختيار وسام أولاً', 'error');
        return;
    }

    const r = await API.post('award_badge', { student_id: studentId, badge_id: badgeId, notes });
    if (r && r.success) {
        closeModal();
        showToast(r.message);
        renderStudentProfilePage(); // Reload everything to update header too
    } else if (r) {
        showToast(r.error, 'error');
    }
}

async function revokeBadge(id, sid) {
    if (!confirm('هل أنت متأكد من رغبتك في سحب هذا الوسام من الطالب؟')) return;
    const r = await API.get('revoke_badge', { id });
    if (r && r.success) {
        showToast(r.message);
        renderStudentProfilePage();
    }
}

/**
 * Show a beautiful digital certificate for a badge
 */
function showBadgeCertificate(badge, studentName) {
    const dateStr = new Date(badge.awarded_at || new Date()).toLocaleDateString('ar-SA', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });

    const certHtml = `
    <div class="p-4 sm:p-8 overflow-hidden bg-white">
        <!-- Top Customization Options (No Print) -->
        <div class="mb-6 p-4 bg-emerald-50 rounded-2xl border-2 border-emerald-100 flex flex-wrap items-center justify-between gap-4 no-print">
            <div class="flex items-center gap-3">
                <span class="text-2xl">🎨</span>
                <div>
                    <p class="font-black text-emerald-900 text-sm">استوديو التصميم:</p>
                    <p class="text-[10px] text-emerald-700 font-bold uppercase tracking-tight">اضغط للتحرير • اسحب للتحريك • ارفع الصور</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button onclick="toggleMoveMode()" id="moveModeBtn" class="bg-emerald-600 text-white px-4 py-2 rounded-xl text-xs font-black shadow-lg shadow-emerald-100 border border-emerald-400 hover:bg-emerald-700 transition">
                    📍 تفعيل وضع التحريك
                </button>
                <button onclick="changeCertificateLogo()" class="bg-white text-emerald-600 px-4 py-2 rounded-xl text-xs font-black shadow-sm border border-emerald-100 hover:bg-emerald-50 transition">
                    🖼️ تغيير الشعار
                </button>
                <button onclick="resetCertificateLayout()" class="bg-white text-gray-500 px-4 py-2 rounded-xl text-xs font-bold border border-gray-100 hover:bg-gray-50 transition">
                    🔄 إعادة ضبط
                </button>
            </div>
        </div>

        <div id="certificateContainer" class="relative border-[8px] sm:border-[16px] border-double border-emerald-600 p-6 sm:p-12 text-center bg-white overflow-hidden rounded-sm mx-auto transition-all" style="width: 100%; max-width: 650px; min-height: 80vh; direction: rtl;">
            <!-- Background Decorative Elements (Absolute) -->
            <div class="absolute -top-20 -right-20 w-80 h-80 bg-emerald-50 rounded-full opacity-50 pointer-events-none"></div>
            <div class="absolute -bottom-20 -left-20 w-80 h-80 bg-emerald-50 rounded-full opacity-50 pointer-events-none"></div>
            
            <!-- Draggable Sections -->
            <div id="certSectionHeader" class="draggable-element relative z-10 flex flex-col items-center mb-10 p-2 border-2 border-transparent rounded-xl transition cursor-default">
                <div id="certLogoArea" class="w-24 h-24 mb-6 flex items-center justify-center pointer-events-none">
                    <div id="certLogoEmoji" class="w-20 h-20 bg-emerald-600 text-white rounded-2xl flex items-center justify-center text-4xl shadow-xl">🏃</div>
                    <img id="certLogoImage" src="" class="hidden w-24 h-24 object-contain shadow-lg rounded-xl">
                </div>
                <h2 contenteditable="true" id="certSchoolName" class="text-2xl font-black text-emerald-900 tracking-tight uppercase outline-none px-4 rounded-lg">نظام التربية البدنية الذكي</h2>
                <div class="w-32 h-1 bg-emerald-600 mt-2 rounded-full pointer-events-none"></div>
            </div>

            <div id="certSectionTitle" class="draggable-element relative z-10 mb-12 p-2 border-2 border-transparent rounded-xl transition cursor-default">
                <h1 contenteditable="true" class="text-5xl font-black text-gray-800 mb-2 outline-none px-4 rounded-lg" style="font-family: 'Cairo', sans-serif;">شهادة إنجاز</h1>
                <p contenteditable="true" class="text-emerald-600 font-bold uppercase tracking-widest text-sm outline-none">Certificate of Achievement</p>
            </div>

            <div id="certSectionContent" class="draggable-element relative z-10 mb-12 p-2 border-2 border-transparent rounded-xl transition cursor-default">
                <p contenteditable="true" class="text-gray-500 text-lg outline-none px-2 rounded-lg mb-8">تعتز إدارة التربية البدنية بمنح هذه الشهادة للفخر والاعتزاز لـ:</p>
                <p contenteditable="true" class="text-5xl font-black text-gray-900 underline decoration-emerald-200 underline-offset-8 outline-none px-4 rounded-lg">${esc(studentName)}</p>
            </div>

            <div id="certSectionBadge" class="draggable-element relative z-10 mb-12 p-2 border-2 border-transparent rounded-xl transition cursor-default">
                <p contenteditable="true" class="text-gray-500 text-lg leading-relaxed px-4 outline-none rounded-lg mb-6">نظير تميزه وجهوده المستمرة وحصوله على وسام:</p>
                <div class="flex flex-col items-center gap-4 pointer-events-none">
                    <div class="w-28 h-28 rounded-3xl ${badge.color} text-white flex items-center justify-center text-6xl shadow-2xl transform rotate-3">${badge.icon}</div>
                    <p contenteditable="true" class="text-3xl font-black text-gray-800 outline-none pointer-events-auto">${esc(badge.name)}</p>
                    <p contenteditable="true" class="text-sm text-gray-400 max-w-sm italic font-bold outline-none pointer-events-auto">"${esc(badge.description || '')}"</p>
                </div>
            </div>

            <div id="certSectionFooter" class="draggable-element relative z-10 mt-auto pt-10 p-2 border-2 border-transparent rounded-xl transition cursor-default">
                <div class="flex justify-between w-full items-end gap-4 px-4">
                    <div class="flex-1 text-right">
                        <p class="text-xs text-gray-400 font-bold uppercase mb-1">تاريخ المنح</p>
                        <p contenteditable="true" class="text-lg font-black text-gray-700 font-mono outline-none">${dateStr}</p>
                    </div>
                    
                    <div class="relative w-28 h-28 flex items-center justify-center pointer-events-none">
                        <div class="absolute inset-0 bg-yellow-400 rounded-full opacity-10 animate-pulse"></div>
                        <div class="absolute inset-2 border-4 border-dashed border-yellow-500 rounded-full opacity-20"></div>
                        <span class="text-5xl relative z-10 drop-shadow-lg">🎖️</span>
                    </div>

                    <div class="flex-1 text-left">
                        <p contenteditable="true" class="text-xs text-gray-400 font-bold uppercase mb-1 outline-none">ختم الاعتماد</p>
                        <p contenteditable="true" class="text-lg font-black text-emerald-700 italic outline-none">المعلم المسؤول</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons (No Print) -->
        <div class="mt-8 flex gap-4 no-print max-w-[650px] mx-auto">
            <button onclick="printCertificate()" class="flex-1 bg-indigo-600 text-white py-4 rounded-2xl font-black hover:bg-indigo-700 shadow-xl shadow-indigo-100 transition flex items-center justify-center gap-2 cursor-pointer">
                <span>🖨️</span> طباعة النسخة النهائية
            </button>
            <button onclick="closeModal()" class="bg-gray-100 text-gray-500 px-8 py-4 rounded-2xl font-black hover:bg-gray-200 transition cursor-pointer">إغلاق</button>
        </div>
    </div>

    <style>
        .draggable-element.moving { background: rgba(99, 102, 241, 0.05) !important; border-color: #818cf8 !important; border-style: dashed !important; z-index: 50 !important; }
        .move-mode .draggable-element { cursor: move !important; border: 2px dashed #e2e8f0; }
        .move-mode .draggable-element:hover { border-color: #818cf8; }
        [contenteditable="true"]:hover { background: #f8fafc; border-radius: 8px; }
    </style>
    `;

    showModal(certHtml);
    initCertificateDraggable();
}

function changeCertificateLogo() {
    const url = prompt('يرجى إدخال رابط شعار المدرسة المباشر (URL) أو إيموجي جديد:');
    if (!url) return;

    const img = document.getElementById('certLogoImage');
    const emo = document.getElementById('certLogoEmoji');

    if (url.startsWith('http') || url.startsWith('data:image')) {
        img.src = url;
        img.classList.remove('hidden');
        emo.classList.add('hidden');
    } else {
        emo.textContent = url;
        emo.classList.remove('hidden');
        img.classList.add('hidden');
    }
}

function toggleMoveMode() {
    const container = document.getElementById('certificateContainer');
    const btn = document.getElementById('moveModeBtn');
    container.classList.toggle('move-mode');

    if (container.classList.contains('move-mode')) {
        btn.innerHTML = '✅ إنهاء وضع التحريك';
        btn.classList.replace('bg-indigo-600', 'bg-green-600');
        showToast('تم تفعيل وضع التحريك.. يمكنك الآن سحب العناصر لتغيير مكانها', 'info');
    } else {
        btn.innerHTML = '📍 تفعيل وضع التحريك';
        btn.classList.replace('bg-green-600', 'bg-indigo-600');
    }
}

function resetCertificateLayout() {
    if (!confirm('هل تريد إعادة تعيين أماكن جميع العناصر للوضع الافتراضي؟')) return;
    document.querySelectorAll('.draggable-element').forEach(el => {
        el.style.transform = 'none';
        el.style.position = 'relative';
        el.style.top = '0';
        el.style.left = '0';
    });
}

function initCertificateDraggable() {
    let activeEl = null;
    let offset = { x: 0, y: 0 };

    document.querySelectorAll('.draggable-element').forEach(el => {
        el.addEventListener('mousedown', (e) => {
            if (!document.getElementById('certificateContainer').classList.contains('move-mode')) return;
            if (e.target.hasAttribute('contenteditable')) return;

            activeEl = el;
            el.classList.add('moving');

            const rect = el.getBoundingClientRect();
            offset.x = e.clientX - rect.left;
            offset.y = e.clientY - rect.top;

            // Shift to relative positioning if not already
            if (el.style.position !== 'relative') {
                el.style.position = 'relative';
            }
        });
    });

    document.addEventListener('mousemove', (e) => {
        if (!activeEl) return;
        e.preventDefault();

        const container = document.getElementById('certificateContainer');
        const containerRect = container.getBoundingClientRect();

        // Calculate new positions relative to initial
        const currentTop = parseFloat(activeEl.style.top || 0);
        const currentLeft = parseFloat(activeEl.style.left || 0);

        // Simple delta movement
        const dx = e.movementX;
        const dy = e.movementY;

        activeEl.style.top = (currentTop + dy) + 'px';
        activeEl.style.left = (currentLeft + dx) + 'px';
    });

    document.addEventListener('mouseup', () => {
        if (activeEl) {
            activeEl.classList.remove('moving');
            activeEl = null;
        }
    });
}

function printCertificate() {
    // Before taking outerHTML, temporarily remove classes that shouldn't be in print
    const container = document.getElementById('certificateContainer');
    container.classList.remove('move-mode');

    const certContent = container.outerHTML;
    const printWindow = window.open('', '_blank');

    printWindow.document.write(`
        <!DOCTYPE html>
        <html dir="rtl" lang="ar">
        <head>
            <title>شهادة إنجاز - PE Smart School</title>
            <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;900&display=swap" rel="stylesheet">
            <script src="https://cdn.tailwindcss.com"></script>
            <style>
                body { margin: 0; padding: 0; background: #fff; font-family: 'Cairo', sans-serif; }
                @page { size: A4 portrait; margin: 0; }
                #print-surface {
                    width: 210mm;
                    height: 297mm;
                    display: flex;
                    flex-direction: column;
                    box-sizing: border-box;
                    background: #fff;
                    overflow: hidden;
                }
                #certificateContainer {
                    width: 100% !important;
                    height: 100% !important;
                    max-width: none !important;
                    margin: 0 !important;
                    border-width: 15px !important;
                    padding: 60px 40px !important;
                    display: flex !important;
                    flex-direction: column !important;
                    justify-content: flex-start !important; /* Start because we use offsets */
                    box-sizing: border-box !important;
                    min-height: auto !important;
                    background: #fff !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                * { box-shadow: none !important; text-shadow: none !important; }
                [contenteditable="true"] { outline: none !important; border: none !important; }
            </style>
        </head>
        <body onload="setTimeout(() => { window.print(); window.close(); }, 800);">
            <div id="print-surface">
                ${certContent}
            </div>
            <script>
                document.querySelectorAll('[contenteditable]').forEach(el => el.removeAttribute('contenteditable'));
                const cert = document.getElementById('certificateContainer');
                
                // Clear move-mode artifacts
                cert.classList.remove('move-mode');
                document.querySelectorAll('.draggable-element').forEach(el => {
                    el.classList.remove('moving');
                    el.style.border = 'none';
                });

                // Clear grey boxes
                document.querySelectorAll('.bg-indigo-600, .shadow-xl, .shadow-2xl').forEach(el => {
                    el.style.boxShadow = 'none';
                    if (el.innerText.length < 5) {
                        el.style.background = 'none';
                        el.style.border = '2px solid #6366f1';
                        el.style.color = '#000';
                    }
                });

                cert.style.width = '100%';
                cert.style.height = '100%';
            </script>
        </body>
         </html>
    `);
    printWindow.document.close();
}

// ============================================================
// SPORTS EXCELLENCE CERTIFICATES
// ============================================================
const CERT_TYPES = {
    excellence: {
        title: 'شهادة تفوق رياضي',
        titleEn: 'Certificate of Sports Excellence',
        emoji: '🏆',
        borderColor: 'border-amber-500',
        bgGrad: 'from-amber-50 to-yellow-50',
        accentColor: 'text-amber-700',
        accentBg: 'bg-amber-600',
        sealEmoji: '🥇',
        body: 'نظير تحقيقه نتائج متميزة ومستوى عالٍ من الأداء الرياضي في حصص التربية البدنية، والتزامه بمعايير اللياقة والتفوق.',
        stampText: 'ختم التفوق الرياضي'
    },
    appreciation: {
        title: 'شهادة تقدير',
        titleEn: 'Certificate of Appreciation',
        emoji: '🎖️',
        borderColor: 'border-blue-500',
        bgGrad: 'from-blue-50 to-indigo-50',
        accentColor: 'text-blue-700',
        accentBg: 'bg-blue-600',
        sealEmoji: '🏅',
        body: 'تقديرًا لجهوده المتميزة والتزامه بروح الفريق وأخلاقيات الرياضة الحميدة، وتعاونه المثمر مع زملائه في الأنشطة الرياضية.',
        stampText: 'ختم التقدير'
    },
    sports_star: {
        title: 'نجم الرياضة المدرسية',
        titleEn: 'School Sports Star',
        emoji: '⭐',
        borderColor: 'border-emerald-500',
        bgGrad: 'from-emerald-50 to-teal-50',
        accentColor: 'text-emerald-700',
        accentBg: 'bg-emerald-600',
        sealEmoji: '🌟',
        body: 'اعترافًا بموهبته الرياضية الاستثنائية وتألقه في الأنشطة البدنية والمسابقات الرياضية المدرسية، ليكون قدوة يُحتذى بها.',
        stampText: 'ختم التميز'
    },
    attendance: {
        title: 'شهادة انتظام وحضور',
        titleEn: 'Outstanding Attendance Certificate',
        emoji: '📅',
        borderColor: 'border-purple-500',
        bgGrad: 'from-purple-50 to-pink-50',
        accentColor: 'text-purple-700',
        accentBg: 'bg-purple-600',
        sealEmoji: '💫',
        body: 'نظير التزامه الكامل بحضور حصص التربية البدنية ومواظبته على المشاركة الفعّالة في جميع الأنشطة الرياضية دون غياب.',
        stampText: 'ختم الانتظام'
    }
};

function showSportsCertificate(type, studentName, stats) {
    const c = CERT_TYPES[type] || CERT_TYPES.excellence;
    const dateStr = new Date().toLocaleDateString('ar-SA', {
        year: 'numeric', month: 'long', day: 'numeric'
    });

    const att = stats.attendance || {};
    const totalAtt = (att.present || 0) + (att.absent || 0) + (att.late || 0);
    const attPct = totalAtt > 0 ? Math.round(((att.present || 0) + ((att.late || 0) * 0.5)) / totalAtt * 100) : 0;

    const certHtml = `
    <div class="p-4 sm:p-8 overflow-hidden bg-white">
        <!-- Top Customization Options (No Print) -->
        <div class="mb-6 p-4 bg-gray-50 rounded-2xl border-2 border-gray-100 flex flex-wrap items-center justify-between gap-4 no-print">
            <div class="flex items-center gap-3">
                <span class="text-2xl">🎨</span>
                <div>
                    <p class="font-black text-gray-800 text-sm">استوديو تصميم الشهادة:</p>
                    <p class="text-[10px] text-gray-500 font-bold uppercase tracking-tight">اضغط للتحرير • اسحب للتحريك • ارفع الصور</p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button onclick="toggleMoveMode()" id="moveModeBtn" class="bg-emerald-600 text-white px-4 py-2 rounded-xl text-xs font-black shadow-lg shadow-emerald-100 border border-emerald-400 hover:bg-emerald-700 transition">
                    📍 تفعيل وضع التحريك
                </button>
                <button onclick="changeCertificateLogo()" class="bg-white text-gray-600 px-4 py-2 rounded-xl text-xs font-black shadow-sm border border-gray-100 hover:bg-gray-50 transition">
                    🖼️ تغيير الشعار
                </button>
                <button onclick="resetCertificateLayout()" class="bg-white text-gray-500 px-4 py-2 rounded-xl text-xs font-bold border border-gray-100 hover:bg-gray-50 transition">
                    🔄 إعادة ضبط
                </button>
            </div>
        </div>

        <div id="certificateContainer" class="relative border-[8px] sm:border-[16px] border-double ${c.borderColor} p-6 sm:p-12 text-center bg-gradient-to-br ${c.bgGrad} overflow-hidden rounded-sm mx-auto transition-all" style="width: 100%; max-width: 650px; min-height: 80vh; direction: rtl;">
            <!-- Background Decorative Elements -->
            <div class="absolute -top-20 -right-20 w-80 h-80 bg-white rounded-full opacity-30 pointer-events-none"></div>
            <div class="absolute -bottom-20 -left-20 w-80 h-80 bg-white rounded-full opacity-30 pointer-events-none"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-[200px] opacity-[0.03] pointer-events-none select-none">${c.emoji}</div>
            
            <!-- Draggable Sections -->
            <div id="certSectionHeader" class="draggable-element relative z-10 flex flex-col items-center mb-8 p-2 border-2 border-transparent rounded-xl transition cursor-default">
                <div id="certLogoArea" class="w-24 h-24 mb-4 flex items-center justify-center pointer-events-none">
                    <div id="certLogoEmoji" class="w-20 h-20 ${c.accentBg} text-white rounded-2xl flex items-center justify-center text-4xl shadow-xl">🏃</div>
                    <img id="certLogoImage" src="" class="hidden w-24 h-24 object-contain shadow-lg rounded-xl">
                </div>
                <h2 contenteditable="true" id="certSchoolName" class="text-2xl font-black ${c.accentColor} tracking-tight uppercase outline-none px-4 rounded-lg">نظام التربية البدنية الذكي</h2>
                <div class="w-32 h-1 ${c.accentBg} mt-2 rounded-full pointer-events-none opacity-60"></div>
            </div>

            <div id="certSectionTitle" class="draggable-element relative z-10 mb-8 p-2 border-2 border-transparent rounded-xl transition cursor-default">
                <div class="text-7xl mb-3 pointer-events-none">${c.emoji}</div>
                <h1 contenteditable="true" class="text-4xl sm:text-5xl font-black text-gray-800 mb-2 outline-none px-4 rounded-lg" style="font-family: 'Cairo', sans-serif;">${c.title}</h1>
                <p contenteditable="true" class="${c.accentColor} font-bold uppercase tracking-widest text-sm outline-none">${c.titleEn}</p>
            </div>

            <div id="certSectionContent" class="draggable-element relative z-10 mb-8 p-2 border-2 border-transparent rounded-xl transition cursor-default">
                <p contenteditable="true" class="text-gray-500 text-base sm:text-lg outline-none px-2 rounded-lg mb-6">تشهد إدارة التربية البدنية بمنح هذه الشهادة للطالب المتميز:</p>
                <p contenteditable="true" class="text-4xl sm:text-5xl font-black text-gray-900 underline decoration-4 underline-offset-8 outline-none px-4 rounded-lg mb-6" style="text-decoration-color: var(--accent, ${c.borderColor.replace('border-', '#').replace('-500', '')})">${esc(studentName)}</p>
                <p contenteditable="true" class="text-gray-500 text-sm sm:text-base leading-relaxed px-4 sm:px-8 outline-none rounded-lg max-w-lg mx-auto">${c.body}</p>
            </div>

            <!-- Stats Section -->
            <div id="certSectionStats" class="draggable-element relative z-10 mb-8 p-2 border-2 border-transparent rounded-xl transition cursor-default">
                <div class="flex flex-wrap justify-center gap-4 sm:gap-6">
                    <div class="bg-white/60 backdrop-blur-sm rounded-2xl px-4 sm:px-6 py-3 border border-white shadow-sm text-center">
                        <p class="text-2xl font-black ${c.accentColor}">${stats.percentage || 0}%</p>
                        <p class="text-[9px] text-gray-400 font-black uppercase tracking-widest">اللياقة البدنية</p>
                    </div>
                    <div class="bg-white/60 backdrop-blur-sm rounded-2xl px-4 sm:px-6 py-3 border border-white shadow-sm text-center">
                        <p class="text-2xl font-black text-green-600">${attPct}%</p>
                        <p class="text-[9px] text-gray-400 font-black uppercase tracking-widest">نسبة الحضور</p>
                    </div>
                    ${stats.badges > 0 ? `
                    <div class="bg-white/60 backdrop-blur-sm rounded-2xl px-4 sm:px-6 py-3 border border-white shadow-sm text-center">
                        <p class="text-2xl font-black text-amber-600">${stats.badges}</p>
                        <p class="text-[9px] text-gray-400 font-black uppercase tracking-widest">أوسمة</p>
                    </div>` : ''}
                </div>
            </div>

            <div id="certSectionFooter" class="draggable-element relative z-10 mt-auto pt-8 p-2 border-2 border-transparent rounded-xl transition cursor-default">
                <div class="flex justify-between w-full items-end gap-4 px-4">
                    <div class="flex-1 text-right">
                        <p class="text-xs text-gray-400 font-bold uppercase mb-1">تاريخ المنح</p>
                        <p contenteditable="true" class="text-lg font-black text-gray-700 font-mono outline-none">${dateStr}</p>
                    </div>
                    
                    <div class="relative w-28 h-28 flex items-center justify-center pointer-events-none">
                        <div class="absolute inset-0 ${c.accentBg} rounded-full opacity-10 animate-pulse"></div>
                        <div class="absolute inset-2 border-4 border-dashed ${c.borderColor} rounded-full opacity-20"></div>
                        <span class="text-5xl relative z-10 drop-shadow-lg">${c.sealEmoji}</span>
                    </div>

                    <div class="flex-1 text-left">
                        <p contenteditable="true" class="text-xs text-gray-400 font-bold uppercase mb-1 outline-none">${c.stampText}</p>
                        <p contenteditable="true" class="text-lg font-black ${c.accentColor} italic outline-none">المعلم المسؤول</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons (No Print) -->
        <div class="mt-8 flex gap-4 no-print max-w-[650px] mx-auto">
            <button onclick="printCertificate()" class="flex-1 bg-indigo-600 text-white py-4 rounded-2xl font-black hover:bg-indigo-700 shadow-xl shadow-indigo-100 transition flex items-center justify-center gap-2 cursor-pointer">
                <span>🖨️</span> طباعة النسخة النهائية
            </button>
            <button onclick="closeModal()" class="bg-gray-100 text-gray-500 px-8 py-4 rounded-2xl font-black hover:bg-gray-200 transition cursor-pointer">إغلاق</button>
        </div>
    </div>

    <style>
        .draggable-element.moving { background: rgba(99, 102, 241, 0.05) !important; border-color: #818cf8 !important; border-style: dashed !important; z-index: 50 !important; }
        .move-mode .draggable-element { cursor: move !important; border: 2px dashed #e2e8f0; }
        .move-mode .draggable-element:hover { border-color: #818cf8; }
        [contenteditable="true"]:hover { background: rgba(255,255,255,0.5); border-radius: 8px; }
    </style>
    `;

    showModal(certHtml);
    initCertificateDraggable();
}
