/**
 * PE Smart School System - Reports Page
 */

let reportType = 'student';

async function renderReports() {
    if (currentUser && currentUser.role === 'student') {
        document.getElementById('mainContent').innerHTML = `
        <div class="fade-in px-4 md:px-0">
            <div class="mb-6">
                <h2 class="text-xl md:text-2xl font-black text-gray-800">📈 تقريري</h2>
                <p class="text-xs md:text-sm text-gray-500 mt-1">تقرير اللياقة البدنية والنتائج الشخصية</p>
            </div>
            <div id="reportContent">${showLoading()}</div>
            <div id="reportOutput" class="space-y-6"></div>
        </div>`;

        // Load personal report directly
        const r = await API.get('report_student', { student_id: currentUser.id });
        if (r && r.success) {
            renderStudentReportDirect(r.data);
        } else {
            document.getElementById('reportContent').innerHTML = '<p class="text-red-500 text-center py-8">خطأ في تحميل التقرير</p>';
        }
        return;
    }

    if (currentUser && currentUser.role === 'parent') {
        const mc = document.getElementById('mainContent');
        mc.innerHTML = `
        <div class="fade-in max-w-6xl mx-auto">
            <div class="mb-8">
                <h2 class="text-3xl font-black text-gray-800">📊 تقارير الأداء البدني</h2>
                <p class="text-gray-500 mt-2">استعرض التقارير التفصيلية والنمو البدني لأبنائك</p>
            </div>
            
            <div id="parentChildrenCards" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-10 no-print">
                <!-- Data loaded via API -->
                ${showLoading()}
            </div>

            <div id="reportOutput" class="space-y-8"></div>
        </div>`;

        // Load linked students
        const r = await API.get('parent_dashboard');
        const container = document.getElementById('parentChildrenCards');

        if (r && r.success && r.data) {
            const children = r.data;
            if (children.length === 0) {
                container.innerHTML = '<div class="col-span-full py-12 text-center text-gray-400 font-bold bg-white rounded-3xl border-2 border-dashed">لم يتم العثور على أبناء مربوطين بالحساب</div>';
            } else {
                container.innerHTML = children.map(s => `
                    <div onclick="generateParentStudentReport(${s.id})" class="child-report-card bg-white p-6 rounded-3xl border border-gray-100 shadow-sm cursor-pointer transition hover:shadow-xl hover:scale-[1.02] group" data-student-id="${s.id}">
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 bg-green-50 text-green-600 rounded-2xl flex items-center justify-center text-3xl transition group-hover:bg-green-600 group-hover:text-white">👦</div>
                            <div class="flex-1">
                                <h4 class="font-black text-gray-800 group-hover:text-green-700 transition">${esc(s.name)}</h4>
                                <p class="text-xs text-gray-400 font-bold uppercase">${esc(s.full_class_name)}</p>
                            </div>
                        </div>
                    </div>
                `).join('');

                // Auto-generate for the first child
                generateParentStudentReport(children[0].id);
            }
        } else {
            container.innerHTML = '<div class="col-span-full py-12 text-center text-red-500 font-bold">فشل في جلب بيانات الأبناء</div>';
        }
        return;
    }

    document.getElementById('mainContent').innerHTML = `
    <div class="fade-in px-4 md:px-0">
        <div class="mb-8">
            <h2 class="text-3xl font-black text-gray-800">📊 مركز التقارير والذكاء الرياضي</h2>
            <p class="text-gray-500 font-bold mt-2">تحليل البيانات البدنية واستخراج تقارير الأداء المتقدمة</p>
        </div>
        
        <div class="flex overflow-x-auto gap-2 mb-8 no-print scrollbar-hide pb-2 -mx-4 px-4 md:mx-0 md:px-0">
            <button onclick="reportType='student';renderReports()" class="whitespace-nowrap px-6 md:px-8 py-3 rounded-2xl font-black transition-all duration-300 ${reportType === 'student' ? 'bg-emerald-600 text-white shadow-xl shadow-emerald-100 scale-105' : 'bg-white text-gray-400 border border-gray-100 hover:text-gray-600'} cursor-pointer text-xs md:text-sm">📄 تقرير الطالب</button>
            <button onclick="reportType='class';renderReports()" class="whitespace-nowrap px-6 md:px-8 py-3 rounded-2xl font-black transition-all duration-300 ${reportType === 'class' ? 'bg-green-600 text-white shadow-xl shadow-green-100 scale-105' : 'bg-white text-gray-400 border border-gray-100 hover:text-gray-600'} cursor-pointer text-xs md:text-sm">🏫 تقرير الفصل</button>
            <button onclick="reportType='compare';renderReports()" class="whitespace-nowrap px-6 md:px-8 py-3 rounded-2xl font-black transition-all duration-300 ${reportType === 'compare' ? 'bg-teal-600 text-white shadow-xl shadow-teal-100 scale-105' : 'bg-white text-gray-400 border border-gray-100 hover:text-gray-600'} cursor-pointer text-xs md:text-sm">⚖️ لوحة المقارنة</button>
            ${hasFeature('weighted_grading') ? `<button onclick="reportType='grading';renderReports()" class="whitespace-nowrap px-6 md:px-8 py-3 rounded-2xl font-black transition-all duration-300 ${reportType === 'grading' ? 'bg-indigo-600 text-white shadow-xl shadow-indigo-100 scale-105' : 'bg-white text-gray-400 border border-gray-100 hover:text-gray-600'} cursor-pointer text-xs md:text-sm">📝 كشف الدرجات النهائي</button>` : ''}
            ${hasFeature('monitoring_report') ? `<button onclick="reportType='monitoring';renderReports()" class="whitespace-nowrap px-6 md:px-8 py-3 rounded-2xl font-black transition-all duration-300 ${reportType === 'monitoring' ? 'bg-orange-600 text-white shadow-xl shadow-orange-100 scale-105' : 'bg-white text-gray-400 border border-gray-100 hover:text-gray-600'} cursor-pointer text-xs md:text-sm">📋 كشف متابعة فصل</button>` : ''}
        </div>
        
        <div id="reportContent" class="mb-12">${showLoading()}</div>
    </div>`;

    if (reportType === 'student') renderStudentReport();
    else if (reportType === 'class') renderClassReport();
    else if (reportType === 'grading' && hasFeature('weighted_grading')) renderGradingReport();
    else if (reportType === 'monitoring' && hasFeature('monitoring_report')) renderMonitoringReport();
    else renderCompareReport();
}

function renderStudentReportDirect(data) {
    document.getElementById('reportContent').innerHTML = `
    <div class="flex flex-col md:flex-row justify-end gap-3 mb-4 no-print px-1">
        <button onclick="sendReportEmail('reportOutput', 'تقرير الطالب')" class="bg-indigo-600 text-white px-6 py-2 rounded-xl font-semibold hover:bg-indigo-700 cursor-pointer flex items-center justify-center gap-2">
            <span>📧 إرسال التقرير كـ PDF بالبريد</span>
        </button>
        <button onclick="window.print()" class="bg-blue-600 text-white px-6 py-2 rounded-xl font-semibold hover:bg-blue-700 cursor-pointer flex items-center justify-center gap-2">
            <span>🖨️ طباعة التقرير</span>
        </button>
    </div>`;

    // Use existing generator logic but inject data directly
    const reportOutput = document.getElementById('reportOutput');
    renderStudentReportHTML(data, reportOutput);
}

async function renderStudentReport() {
    const cl = await API.get('classes');

    document.getElementById('reportContent').innerHTML = `
    <div class="fade-in">
        <div class="bg-white rounded-[2rem] md:rounded-[2.5rem] shadow-xl shadow-gray-100/50 border border-gray-100 p-5 md:p-8 mb-8 md:mb-10 no-print">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 items-end">
                <div class="space-y-2">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mr-2">اختيار الفصل</label>
                    <select id="reportClass" onchange="updateReportStudents()" class="w-full px-5 py-3.5 bg-gray-50 border-2 border-gray-50 rounded-2xl md:rounded-[1.5rem] focus:bg-white focus:border-green-500 focus:outline-none transition-all font-bold text-gray-700 appearance-none cursor-pointer text-sm">
                        <option value="">-- اضغط للاختيار --</option>
                        ${(cl?.data || []).map(c => `<option value="${c.id}">${esc(c.full_name || c.name)}</option>`).join('')}
                    </select>
                </div>
                <div class="space-y-2">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mr-2">اختيار الطالب</label>
                    <select id="reportStudent" class="w-full px-5 py-3.5 bg-gray-50 border-2 border-gray-50 rounded-2xl md:rounded-[1.5rem] focus:bg-white focus:border-green-500 focus:outline-none transition-all font-bold text-gray-700 appearance-none cursor-pointer text-sm">
                        <option value="">-- اختر الفصل أولاً --</option>
                    </select>
                </div>
                <div class="space-y-2">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mr-2">من تاريخ</label>
                    <input type="date" id="reportStartDate" value="${new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0]}" class="w-full px-5 py-3.5 bg-gray-50 border-2 border-gray-50 rounded-2xl md:rounded-[1.5rem] focus:bg-white focus:border-green-500 focus:outline-none transition-all font-bold text-gray-700 text-sm">
                </div>
                <div class="space-y-2">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mr-2">إلى تاريخ</label>
                    <input type="date" id="reportEndDate" value="${new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).toISOString().split('T')[0]}" class="w-full px-5 py-3.5 bg-gray-50 border-2 border-gray-50 rounded-2xl md:rounded-[1.5rem] focus:bg-white focus:border-green-500 focus:outline-none transition-all font-bold text-gray-700 text-sm">
                </div>
                <div class="pt-1">
                    <button onclick="generateStudentReport()" class="w-full bg-emerald-600 text-white px-6 py-3.5 rounded-2xl md:rounded-[1.5rem] font-black hover:bg-emerald-700 transition shadow-lg shadow-emerald-100 flex items-center justify-center gap-3 active:scale-95 text-sm md:text-base">
                        <span class="text-xl">📄</span> عرض التقرير
                    </button>
                </div>
                <div class="pt-1 flex gap-2">
                    <button onclick="window.print()" class="w-1/2 bg-gray-900 text-white px-2 py-3.5 rounded-2xl md:rounded-[1.5rem] font-black hover:bg-black transition shadow-lg shadow-gray-200 flex items-center justify-center gap-2 active:scale-95 text-xs md:text-sm">
                        <span class="text-lg">🖨️</span> طباعة
                    </button>
                    <button onclick="sendReportEmail('reportOutput', 'تقرير الطالب')" class="w-1/3 bg-indigo-600 text-white px-2 py-3.5 rounded-2xl md:rounded-[1.5rem] font-black hover:bg-indigo-700 transition shadow-lg shadow-indigo-200 flex items-center justify-center gap-2 active:scale-95 text-xs md:text-sm auto-export-btn">
                        <span class="text-lg">📧</span> بريد
                    </button>
                    <button onclick="downloadReportPDF('reportOutput', 'تقرير الطالب')" class="w-1/3 bg-emerald-600 text-white px-2 py-3.5 rounded-2xl md:rounded-[1.5rem] font-black hover:bg-emerald-700 transition shadow-lg shadow-emerald-200 flex items-center justify-center gap-2 active:scale-95 text-xs md:text-sm">
                        <span class="text-lg">💾</span> PDF
                    </button>
                </div>
            </div>
        </div>
        <div id="reportOutput" class="scroll-mt-20">
            <div class="text-center py-24 bg-white rounded-[3rem] border-2 border-dashed border-gray-100">
                <div class="w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center text-4xl mx-auto mb-6 grayscale opacity-30">👤</div>
                <p class="text-gray-400 font-black text-xl">يرجى اختيار الطالب لاستعراض تقريره</p>
                <p class="text-gray-300 text-sm mt-1">التقارير تتضمن القياسات البدنية، اللياقة، والحضور</p>
            </div>
        </div>
    </div>`;
}

async function updateReportStudents() {
    const cid = document.getElementById('reportClass').value;
    if (!cid) return;

    const r = await API.get('students', { class_id: cid });
    document.getElementById('reportStudent').innerHTML = `<option value="">اختر</option>` +
        (r?.data || []).map(s => `<option value="${s.id}">${esc(s.name)}</option>`).join('');
}

async function generateStudentReport() {
    const sid = document.getElementById('reportStudent').value;
    const startDate = document.getElementById('reportStartDate').value;
    const endDate = document.getElementById('reportEndDate').value;

    if (!sid) {
        showToast('اختر طالب', 'error');
        return;
    }

    const r = await API.get('report_student', { student_id: sid, start_date: startDate, end_date: endDate });
    if (!r || !r.success) return;

    renderStudentReportHTML(r.data, document.getElementById('reportOutput'));
}

async function generateParentStudentReport(sid) {
    if (!sid) {
        const select = document.getElementById('parentStudentSelect');
        if (select) sid = select.value;
    }
    if (!sid) return;

    // Dates for parents (current month by default)
    const startDate = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
    const endDate = new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).toISOString().split('T')[0];

    // Highlight selected card if it exists
    document.querySelectorAll('.child-report-card').forEach(el => {
        if (el.dataset.studentId == sid) {
            el.classList.add('ring-4', 'ring-green-500', 'bg-green-50', 'border-green-200');
            el.classList.remove('border-gray-100');
        } else {
            el.classList.remove('ring-4', 'ring-green-500', 'bg-green-50', 'border-green-200');
            el.classList.add('border-gray-100');
        }
    });

    const ro = document.getElementById('reportOutput');
    if (ro) ro.innerHTML = showLoading();

    const r = await API.get('report_student', { student_id: sid, start_date: startDate, end_date: endDate });
    if (r && r.success) {
        renderStudentReportHTML(r.data, ro);
    } else if (ro) {
        ro.innerHTML = '<p class="text-red-500 text-center py-8">خطأ في تحميل التقرير</p>';
    }
}

function renderClassReportHTML(d, container) {
    // Capture data for PDFBuilder
    window._lastReportData = d;
    window._lastReportType = 'class';

    container.innerHTML = `
    <div class="bg-white rounded-[2rem] md:rounded-[3rem] shadow-2xl border border-gray-100 p-6 md:p-12 fade-in">
        ${getReportHeaderHTML('تقرير تحليل أداء الفصل الدراسي')}

        <div class="flex flex-col md:flex-row justify-between items-center gap-6 mb-10 pb-8 border-b border-gray-50">
            <div class="text-center md:text-right">
                <h3 class="text-2xl md:text-3xl font-black text-gray-800">${esc(d.class.full_name)}</h3>
                <p class="text-green-600 font-black flex items-center justify-center md:justify-start gap-2 mt-1">
                    <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                    ${d.totalStudents} طالباً مسجلاً
                </p>
            </div>
            <div class="bg-gray-900 text-white p-6 rounded-[2rem] text-center min-w-[160px] shadow-xl">
                <p class="text-4xl font-black text-green-400">${d.classAverage}%</p>
                <p class="text-[10px] font-black uppercase tracking-widest mt-2 opacity-60">متوسط أداء الفصل</p>
            </div>
        </div>

        <!-- Desktop View -->
        <div class="hidden md:block overflow-hidden rounded-[2rem] border border-gray-100 shadow-sm bg-gray-50/30">
            <table class="w-full">
                <thead>
                    <tr class="bg-gray-100/50">
                        <th class="px-5 py-4 text-right text-[10px] font-black text-gray-400 uppercase tracking-widest">الترتيب</th>
                        <th class="px-5 py-4 text-right text-[10px] font-black text-gray-400 uppercase tracking-widest">اسم الطالب</th>
                        <th class="px-5 py-4 text-center text-[10px] font-black text-gray-400 uppercase tracking-widest">النقاط</th>
                        <th class="px-4 py-4 text-center text-xs font-black text-gray-400 uppercase tracking-widest">النسبة/%</th>
                        <th class="px-4 py-4 text-center text-xs font-black text-gray-400 uppercase tracking-widest">التقدير</th>
                        <th class="px-5 py-4 text-center text-[10px] font-black text-gray-400 uppercase tracking-widest">BMI</th>
                        <th class="px-5 py-4 text-center text-[10px] font-black text-gray-400 uppercase tracking-widest">الصحة</th>
                        <th class="px-5 py-4 text-center text-[10px] font-black text-gray-400 uppercase tracking-widest">حضور</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    ${d.students.map((s, i) => `
                    <tr class="hover:bg-green-50/50 transition-colors ${i < 3 ? 'bg-green-50/20' : ''}">
                        <td class="px-5 py-4">
                            <span class="w-8 h-8 rounded-lg ${i === 0 ? 'bg-yellow-400 text-white' : i === 1 ? 'bg-gray-300 text-white' : i === 2 ? 'bg-orange-400 text-white' : 'bg-gray-100 text-gray-400'} flex items-center justify-center font-black text-xs">
                                ${i + 1}
                            </span>
                        </td>
                        <td class="px-5 py-4">
                            <div class="font-black text-gray-800 text-sm whitespace-nowrap">${esc(s.name)}</div>
                        </td>
                        <td class="px-5 py-4 text-center font-bold text-gray-600 text-sm">${s.total_score}/${s.total_max || 0}</td>
                        <td class="px-4 py-4 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <div class="w-12 bg-gray-100 rounded-full h-1.5 overflow-hidden hidden md:block">
                                    <div class="h-full ${s.percentage >= 90 ? 'bg-green-500' : s.percentage >= 70 ? 'bg-yellow-500' : 'bg-red-500'}" style="width: ${s.percentage}%"></div>
                                </div>
                                <span class="font-black text-gray-800">${s.percentage}%</span>
                            </div>
                        </td>
                        <td class="px-4 py-4 text-center">
                            <span class="inline-flex px-2 py-0.5 rounded-lg text-[10px] font-black ${s.letter === 'ممتاز' ? 'bg-green-100 text-green-700' :
            s.letter === 'جيد جداً' ? 'bg-blue-100 text-blue-700' :
                s.letter === 'جيد' ? 'bg-yellow-100 text-yellow-700' :
                    s.letter === 'مقبول' ? 'bg-orange-100 text-orange-700' :
                        'bg-red-100 text-red-700'
        }">${s.letter || '-'}</span>
                        </td>
                        <td class="px-5 py-4 text-center">
                            ${s.latest_bmi ? `<span class="badge bmi-${s.bmi_category || 'normal'} !text-[10px] font-black">${s.latest_bmi}</span>` : '<span class="text-gray-300">-</span>'}
                        </td>
                        <td class="px-5 py-4 text-center">
                            ${s.health_alerts > 0 ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 bg-red-50 text-red-600 rounded-md text-[10px] font-black border border-red-100">⚠️ ${s.health_alerts}</span>` : '<span class="text-green-500 text-xs text-center">--</span>'}
                        </td>
                        <td class="px-5 py-4 text-center">
                            <div class="flex items-center justify-center gap-1.5 font-bold text-xs ring-1 ring-gray-100 rounded-full py-1">
                                <span class="text-green-600">P:${s.present_count}</span>
                                <span class="text-gray-200">|</span>
                                <span class="text-red-600">A:${s.absent_count}</span>
                            </div>
                        </td>
                    </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>

        <!-- Mobile View Cards -->
        <div class="md:hidden space-y-4">
            ${d.students.map((s, i) => `
            <div class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm relative overflow-hidden group ${i < 3 ? 'ring-2 ring-green-100 border-green-200' : ''}">
                ${i < 3 ? `<div class="absolute top-2 left-2 text-2xl">${i === 0 ? '🥇' : i === 1 ? '🥈' : '🥉'}</div>` : ''}
                <div class="flex items-start gap-4">
                    <div class="w-10 h-10 rounded-xl ${i < 3 ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-400'} flex items-center justify-center font-black flex-shrink-0">
                        ${i + 1}
                    </div>
                    <div class="flex-1">
                        <h4 class="font-black text-gray-800 text-base mb-1 group-hover:text-green-600 transition-colors">${esc(s.name)}</h4>
                        <div class="flex items-center gap-3">
                             <span class="text-[10px] font-black text-green-600 uppercase">الأداء: ${s.percentage}%</span>
                             <span class="text-[10px] font-bold text-gray-300">|</span>
                             <span class="text-[10px] font-black text-gray-400 uppercase">النقاط: ${s.total_score}/${s.total_max}</span>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-2 mt-4 pt-4 border-t border-gray-50">
                    <div class="text-center">
                        <p class="text-[8px] font-black text-gray-400 uppercase mb-1">BMI</p>
                        <span class="text-[10px] font-black text-gray-700">${s.latest_bmi || '-'}</span>
                    </div>
                    <div class="text-center">
                        <p class="text-[8px] font-black text-gray-400 uppercase mb-1">صحة</p>
                        <span class="text-[10px] font-black ${s.health_alerts > 0 ? 'text-red-500' : 'text-green-500'}">${s.health_alerts > 0 ? '⚠️ تنبيه' : '✅'}</span>
                    </div>
                    <div class="text-center">
                        <p class="text-[8px] font-black text-gray-400 uppercase mb-1">حضور</p>
                        <span class="text-[10px] font-black text-gray-700">${s.present_count} / ${s.present_count + s.absent_count}</span>
                    </div>
                </div>
            </div>
            `).join('')}
        </div>

        <div class="text-center text-[10px] text-gray-400 mt-12 opacity-50 font-black uppercase tracking-widest">
            نظام التحليل البدني والرياضي لعام ${new Date().getFullYear()}
        </div>
    </div>`;
}

function renderStudentReportHTML(d, container) {
    // Capture data for PDFBuilder
    window._lastReportData = d;
    window._lastReportType = 'student';
    const s = d.student;
    // Fix #5: API returns 'latestMeasurement' not 'measurement'
    const m = d.latestMeasurement || d.measurement || null;
    const h = d.health || d.healthConditions || [];
    const att = d.attendance;

    container.innerHTML = `
    <div id="printableStudentReport" class="bg-white rounded-[2rem] md:rounded-[3rem] shadow-2xl border border-gray-100 p-6 md:p-12 fade-in relative overflow-hidden">
        <!-- Decoration Decor -->
        <div class="absolute top-0 right-0 w-64 h-64 bg-green-50 rounded-full -mr-32 -mt-32 opacity-40 animate-pulse"></div>
        
        <div class="relative z-10">
            ${getReportHeaderHTML('تقرير اللياقة البدنية الشامل')}

            <div class="flex flex-col md:flex-row justify-between items-center md:items-start gap-8 mb-10 pb-10 border-b border-gray-50">
                <div class="flex flex-col md:flex-row items-center gap-6 text-center md:text-right">
                    <div class="w-24 h-24 bg-gradient-to-br from-green-400 to-emerald-600 rounded-[2rem] flex items-center justify-center text-5xl text-white shadow-xl shadow-green-100 overflow-hidden">
                        ${s.photo_url ? `<img src="${s.photo_url}" class="w-full h-full object-cover">` : '👤'}
                    </div>
                    <div>
                        <h3 class="text-2xl md:text-4xl font-black text-gray-800">${esc(s.name)}</h3>
                        <p class="text-lg md:text-xl text-green-600 font-black mt-1">${esc(s.full_class_name)}</p>
                        <div class="flex flex-wrap justify-center md:justify-start gap-2 mt-4">
                            <span class="bg-gray-100 text-gray-500 px-4 py-1.5 rounded-xl text-xs font-black tracking-tighter">🆔 ${esc(s.student_number)}</span>
                            ${s.age ? `<span class="bg-green-50 text-green-600 px-4 py-1.5 rounded-xl text-xs font-black tracking-tighter">🎂 ${s.age} سنة</span>` : ''}
                            ${s.blood_type ? `<span class="bg-red-50 text-red-600 px-4 py-1.5 rounded-xl text-xs font-black tracking-tighter">🩸 ${s.blood_type}</span>` : ''}
                        </div>
                    </div>
                </div>
                <div class="bg-gray-900 text-white p-8 rounded-[2.5rem] text-center shadow-2xl min-w-[180px] transform hover:scale-105 transition-transform">
                    <p class="text-6xl font-black ${d.percentage >= 90 ? 'text-green-400' : d.percentage >= 80 ? 'text-blue-400' : d.percentage >= 70 ? 'text-yellow-400' : 'text-red-400'}">${d.percentage}%</p>
                    <p class="text-[10px] font-black uppercase tracking-[0.2em] mt-3 opacity-60">التقييم العام للأداء</p>
                    ${d.grading_summary ? `<p class="mt-4 px-4 py-2 bg-white/10 rounded-xl font-black text-sm">${d.grading_summary.letter}</p>` : ''}
                </div>
            </div>

            ${d.grading_summary ? `
            <!-- Grading Summary Section -->
            <div class="mb-12">
                <h4 class="font-black text-gray-800 mb-8 flex items-center gap-3 text-xl">
                    <span class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center text-xl">📝</span> التقييم الشامل والتقدير النهائي
                </h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6 mb-6">
                    <div class="bg-blue-50/50 border border-blue-100 rounded-3xl p-5 text-center">
                        <p class="text-[10px] text-blue-400 font-black uppercase mb-1">الحضور والالتزام</p>
                        <p class="text-2xl font-black text-blue-700">${d.grading_summary.attendance_pct}%</p>
                        <p class="text-[9px] text-blue-400/60 font-medium">الوزن: ${d.grading_summary.weights.attendance_pct}%</p>
                    </div>
                    <div class="bg-emerald-50/50 border border-emerald-100 rounded-3xl p-5 text-center">
                        <p class="text-[10px] text-emerald-400 font-black uppercase mb-1">الزي الرياضي</p>
                        <p class="text-2xl font-black text-emerald-700">${d.grading_summary.uniform_pct}%</p>
                        <p class="text-[9px] text-emerald-400/60 font-medium">الوزن: ${d.grading_summary.weights.uniform_pct}%</p>
                    </div>
                    <div class="bg-yellow-50/50 border border-yellow-100 rounded-3xl p-5 text-center">
                        <p class="text-[10px] text-yellow-500 font-black uppercase mb-1">السلوك والمهارات</p>
                        <p class="text-2xl font-black text-yellow-700">${d.grading_summary.behavior_skills_pct}%</p>
                        <p class="text-[9px] text-yellow-500/60 font-medium">الوزن: ${d.grading_summary.weights.behavior_skills_pct}%</p>
                    </div>
                    <div class="bg-purple-50/50 border border-purple-100 rounded-3xl p-5 text-center">
                        <p class="text-[10px] text-purple-400 font-black uppercase mb-1">اللياقة البدنية</p>
                        <p class="text-2xl font-black text-purple-700">${d.grading_summary.fitness_pct}%</p>
                        <p class="text-[9px] text-purple-400/60 font-medium">الوزن: ${d.grading_summary.weights.fitness_pct}%</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
                    <div class="bg-orange-50/50 border border-orange-100 rounded-3xl p-5 text-center">
                        <p class="text-[10px] text-orange-400 font-black uppercase mb-1">المشاركة</p>
                        <p class="text-2xl font-black text-orange-700">${d.grading_summary.participation_pct}%</p>
                        <p class="text-[9px] text-orange-400/60 font-medium">الوزن: ${d.grading_summary.weights.participation_pct}%</p>
                    </div>
                    <div class="bg-indigo-50/50 border border-indigo-100 rounded-3xl p-5 text-center">
                        <p class="text-[10px] text-indigo-400 font-black uppercase mb-1">الاختبارات القصيرة</p>
                        <p class="text-2xl font-black text-indigo-700">${d.grading_summary.quiz_score}<span class="text-xs text-indigo-300">/${d.grading_summary.quiz_max}</span></p>
                        <p class="text-[9px] text-indigo-400/60 font-medium">الوزن: ${d.grading_summary.weights.quiz_pct}%</p>
                    </div>
                    <div class="bg-rose-50/50 border border-rose-100 rounded-3xl p-5 text-center">
                        <p class="text-[10px] text-rose-400 font-black uppercase mb-1">المشاريع والأبحاث</p>
                        <p class="text-2xl font-black text-rose-700">${d.grading_summary.project_score}<span class="text-xs text-rose-300">/${d.grading_summary.project_max}</span></p>
                        <p class="text-[9px] text-rose-400/60 font-medium">الوزن: ${d.grading_summary.weights.project_pct}%</p>
                    </div>
                    <div class="bg-teal-50/50 border border-teal-100 rounded-3xl p-5 text-center">
                        <p class="text-[10px] text-teal-400 font-black uppercase mb-1">الاختبار النهائي</p>
                        <p class="text-2xl font-black text-teal-700">${d.grading_summary.final_exam_score}<span class="text-xs text-teal-300">/${d.grading_summary.final_exam_max}</span></p>
                        <p class="text-[9px] text-teal-400/60 font-medium">الوزن: ${d.grading_summary.weights.final_exam_pct}%</p>
                    </div>
                </div>
            </div>
            ` : ''}

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-10 mb-12">
                <!-- Measurements -->
                <div class="lg:col-span-2">
                    <h4 class="font-black text-gray-800 mb-8 flex items-center gap-3 text-xl">
                        <span class="w-10 h-10 rounded-xl bg-green-100 text-green-600 flex items-center justify-center text-xl">📏</span> القياسات الجسمية والنمو
                    </h4>
                    ${m ? `
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
                        <div class="bg-gray-50 border border-gray-100 rounded-3xl p-5 text-center group hover:bg-white hover:shadow-xl transition-all">
                            <p class="text-[10px] text-gray-400 font-black uppercase mb-2">الطول</p>
                            <p class="text-2xl font-black text-gray-800 transition group-hover:text-green-600">${m.height_cm} <span class="text-xs text-gray-400">سم</span></p>
                        </div>
                        <div class="bg-gray-50 border border-gray-100 rounded-3xl p-5 text-center group hover:bg-white hover:shadow-xl transition-all">
                            <p class="text-[10px] text-gray-400 font-black uppercase mb-2">الوزن</p>
                            <p class="text-2xl font-black text-gray-800 transition group-hover:text-emerald-600">${m.weight_kg} <span class="text-xs text-gray-400">كجم</span></p>
                        </div>
                        <div class="rounded-3xl p-5 text-center border-2 bmi-${m.bmi_category}-light group hover:shadow-xl transition-all">
                            <p class="text-[10px] font-black uppercase mb-2 opacity-60 italic">BMI</p>
                            <p class="text-2xl font-black">${m.bmi}</p>
                            <p class="text-[10px] font-black mt-1 text-center bg-white/50 py-1 rounded-lg">${BMI_AR[m.bmi_category] || ''}</p>
                        </div>
                        <div class="bg-gray-50 border border-gray-100 rounded-3xl p-5 text-center group hover:bg-white hover:shadow-xl transition-all">
                            <p class="text-[10px] text-gray-400 font-black uppercase mb-2">النبض</p>
                            <p class="text-2xl font-black text-gray-800 transition group-hover:text-orange-500">${m.resting_heart_rate || '-'} <span class="text-xs text-gray-400">ن/د</span></p>
                        </div>
                    </div>
                    ` : '<div class="p-10 bg-gray-50 rounded-[2rem] text-center text-gray-400 font-black border-2 border-dashed border-gray-100">لا يوجد بيانات قياسات حالية</div>'}
                </div>

                <!-- Health Status -->
                <div>
                    <h4 class="font-black text-gray-800 mb-8 flex items-center gap-3 text-xl">
                        <span class="w-10 h-10 rounded-xl bg-green-100 text-green-600 flex items-center justify-center text-xl">🏥</span> الحالة الصحية
                    </h4>
                    ${h.length > 0 ? `
                    <div class="space-y-4">
                        ${h.map(c => `
                        <div class="health-alert-mini rounded-2xl p-4 border border-red-100 bg-red-50/30">
                            <p class="font-black text-gray-800 text-sm mb-1">${esc(c.condition_name)}</p>
                            <span class="badge severity-${c.severity} !text-[9px] !px-3 font-black">${SEVERITY_AR[c.severity]}</span>
                        </div>
                        `).join('')}
                    </div>
                    ` : `
                    <div class="p-8 bg-green-50 rounded-[2rem] text-center border border-green-100">
                        <span class="text-4xl block mb-3">✨</span>
                        <p class="text-green-800 font-black text-sm">الحالة الصحية مستقرة</p>
                        <p class="text-green-600/60 text-[10px] mt-1 font-bold">لم يتم تسجيل أي عوارض طبية</p>
                    </div>`}
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 mb-12">
                <!-- Fitness Results -->
                <div>
                    <h4 class="font-black text-gray-800 mb-8 flex items-center gap-3 text-xl">
                        <span class="w-10 h-10 rounded-xl bg-green-100 text-green-600 flex items-center justify-center text-xl">💪</span> نتائج اختبارات اللياقة
                    </h4>
                    
                    <!-- Desktop Table -->
                    <div class="hidden md:block bg-white rounded-[2rem] overflow-hidden border border-gray-100 shadow-sm">
                        <table class="w-full">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-6 py-5 text-right text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">الاختبار البدني</th>
                                    <th class="px-6 py-5 text-center text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">النتيجة</th>
                                    <th class="px-6 py-5 text-center text-[10px] font-black text-gray-400 uppercase tracking-[0.2em]">الدرجة</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                ${d.fitness.map(f => `
                                <tr class="hover:bg-green-50/30 transition-colors">
                                    <td class="px-6 py-5 font-black text-gray-700 text-sm">${esc(f.test_name)}</td>
                                    <td class="px-6 py-5 text-center text-gray-600 font-black text-sm">${f.value !== null ? f.value + ' <span class="text-[10px] text-gray-400 font-bold">' + f.unit + '</span>' : '-'}</td>
                                    <td class="px-6 py-5 text-center">
                                        <div class="inline-flex items-center justify-center p-2 rounded-xl bg-emerald-50 text-emerald-700 font-black text-sm min-w-[50px]">
                                            ${f.score !== null ? f.score + '/' + f.max_score : '-'}
                                        </div>
                                    </td>
                                </tr>
                                `).join('')}
                                <tr class="bg-emerald-600 text-white shadow-xl">
                                    <td class="px-6 py-6 font-black rounded-r-2xl" colspan="2">إجمالي نقاط اللياقة البدنية</td>
                                    <td class="px-6 py-6 text-center text-2xl font-black rounded-l-2xl">${d.totalScore}/${d.totalMax}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Cards -->
                    <div class="md:hidden space-y-3">
                        ${d.fitness.map(f => `
                        <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100 flex justify-between items-center group active:scale-95 transition-all">
                            <div>
                                <h5 class="font-black text-gray-800 text-sm group-hover:text-green-600 transition-colors">${esc(f.test_name)}</h5>
                                <p class="text-xs text-gray-400 font-bold mt-1">${f.value !== null ? f.value + ' ' + f.unit : 'لم يؤدَ'}</p>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-white shadow-sm flex items-center justify-center text-green-600 font-black border border-green-50">
                                ${f.score !== null ? f.score : '-'}
                            </div>
                        </div>`).join('')}
                        <div class="bg-green-600 rounded-3xl p-6 text-white text-center shadow-lg shadow-green-100">
                            <p class="text-xs font-black uppercase tracking-widest opacity-60 mb-1">المجموع الكلي</p>
                            <h4 class="text-3xl font-black">${d.totalScore} <span class="text-sm opacity-60">/ ${d.totalMax}</span></h4>
                        </div>
                    </div>
                </div>

                <!-- Attendance -->
                <div class="flex flex-col">
                    <h4 class="font-black text-gray-800 mb-8 flex items-center gap-3 text-xl">
                        <span class="w-10 h-10 rounded-xl bg-green-100 text-green-600 flex items-center justify-center text-xl">📋</span> إحصائيات الحضور والغياب
                    </h4>
                    <div class="bg-white border-2 border-gray-50 rounded-[2.5rem] p-8 md:p-10 flex-1 flex flex-col justify-center shadow-inner">
                        <div class="grid grid-cols-3 gap-4 md:gap-8">
                            <div class="text-center group">
                                <div class="w-16 h-16 md:w-20 md:h-20 bg-green-50 rounded-[1.5rem] md:rounded-[2rem] flex items-center justify-center mx-auto mb-3 transition group-hover:scale-110 group-hover:bg-green-500 group-hover:text-white shadow-sm">
                                    <span class="text-2xl md:text-3xl font-black">${att.present}</span>
                                </div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">حضور</p>
                            </div>
                            <div class="text-center group">
                                <div class="w-16 h-16 md:w-20 md:h-20 bg-red-50 rounded-[1.5rem] md:rounded-[2rem] flex items-center justify-center mx-auto mb-3 transition group-hover:scale-110 group-hover:bg-red-500 group-hover:text-white shadow-sm">
                                    <span class="text-2xl md:text-3xl font-black">${att.absent}</span>
                                </div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">غياب</p>
                            </div>
                            <div class="text-center group">
                                <div class="w-16 h-16 md:w-20 md:h-20 bg-yellow-50 rounded-[1.5rem] md:rounded-[2rem] flex items-center justify-center mx-auto mb-3 transition group-hover:scale-110 group-hover:bg-yellow-500 group-hover:text-white shadow-sm">
                                    <span class="text-2xl md:text-3xl font-black">${att.late}</span>
                                </div>
                                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest">تأخر</p>
                            </div>
                        </div>
                        
                        <div class="mt-12">
                            <div class="flex justify-between text-xs font-black text-gray-400 uppercase mb-3 px-1">
                                <span>نسبة الانتظام الميداني</span>
                                <span class="text-green-600">${att.present + att.absent + att.late > 0 ? Math.round(att.present / (att.present + att.absent + att.late) * 100) : 100}%</span>
                            </div>
                            <div class="h-5 bg-gray-100 rounded-full overflow-hidden flex shadow-inner p-1">
                                <div class="bg-gradient-to-r from-green-400 to-green-600 h-full rounded-full shadow-lg transition-all duration-1000" style="width:${att.present + att.absent + att.late > 0 ? (att.present / (att.present + att.absent + att.late) * 100) : 100}%"></div>
                                <div class="bg-yellow-400 h-full rounded-full shadow-lg" style="width:${att.present + att.absent + att.late > 0 ? (att.late / (att.present + att.absent + att.late) * 100) : 0}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center text-[10px] text-gray-400 mt-12 pt-8 border-t border-gray-50 italic font-black uppercase tracking-[0.2em] opacity-50">
                PE Smart School System • ${new Date().toLocaleDateString('ar-SA')} • تقرير أداء ذكي
            </div>
        </div>
    </div>`;
}

async function renderClassReport() {
    const cl = await API.get('classes');

    document.getElementById('reportContent').innerHTML = `
    <div class="fade-in">
        <div class="bg-white rounded-[2rem] md:rounded-[2.5rem] shadow-xl shadow-gray-100/50 border border-gray-100 p-5 md:p-8 mb-8 md:mb-10 no-print">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6 items-end">
                <div class="space-y-2">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mr-2">اختيار الفصل المستهدف</label>
                    <select id="classReportSelect" class="w-full px-5 py-3.5 bg-gray-50 border-2 border-gray-50 rounded-2xl md:rounded-[1.5rem] focus:bg-white focus:border-green-500 focus:outline-none transition-all font-bold text-gray-700 appearance-none cursor-pointer text-sm">
                        <option value="">-- اضغط للاختيار --</option>
                        ${(cl?.data || []).map(c => `<option value="${c.id}">${esc(c.full_name || c.name)}</option>`).join('')}
                    </select>
                </div>
                <div class="pt-1">
                    <button onclick="generateClassReport()" class="w-full bg-green-600 text-white px-6 py-3.5 rounded-2xl md:rounded-[1.5rem] font-black hover:bg-green-700 transition shadow-lg shadow-green-100 flex items-center justify-center gap-3 active:scale-95 text-sm md:text-base">
                        <span class="text-xl">🏫</span> عرض التقرير
                    </button>
                </div>
                <div class="pt-1 flex gap-2">
                    <button onclick="window.print()" class="w-1/2 bg-gray-900 text-white px-2 py-3.5 rounded-2xl md:rounded-[1.5rem] font-black hover:bg-black transition shadow-lg shadow-gray-200 flex items-center justify-center gap-2 active:scale-95 text-xs md:text-sm">
                        <span class="text-lg">🖨️</span> طباعة
                    </button>
                    <button onclick="sendReportEmail('reportOutput', 'تقرير الفصل')" class="w-1/3 bg-indigo-600 text-white px-2 py-3.5 rounded-2xl md:rounded-[1.5rem] font-black hover:bg-indigo-700 transition shadow-lg shadow-indigo-200 flex items-center justify-center gap-2 active:scale-95 text-xs md:text-sm">
                        <span class="text-lg">📧</span> بريد
                    </button>
                    <button onclick="downloadReportPDF('reportOutput', 'تقرير الفصل')" class="w-1/3 bg-emerald-600 text-white px-2 py-3.5 rounded-2xl md:rounded-[1.5rem] font-black hover:bg-emerald-700 transition shadow-lg shadow-emerald-200 flex items-center justify-center gap-2 active:scale-95 text-xs md:text-sm">
                        <span class="text-lg">💾</span> PDF
                    </button>
                </div>
            </div>
        </div>
        <div id="reportOutput">
            <div class="text-center py-24 bg-white rounded-[3rem] border-2 border-dashed border-gray-100">
                <div class="w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center text-4xl mx-auto mb-6 grayscale opacity-30">🏆</div>
                <p class="text-gray-400 font-black text-xl">يرجى اختيار الفصل لتحليل النتائج الجماعية</p>
                <p class="text-gray-300 text-sm mt-1">يستعرض هذا التقرير ترتيب الطلاب ومعدل أداء الفصل</p>
            </div>
        </div>
    </div>`;
}

async function generateClassReport() {
    const cid = document.getElementById('classReportSelect').value;
    if (!cid) {
        showToast('اختر فصل', 'error');
        return;
    }

    const r = await API.get('report_class', { class_id: cid });
    if (!r || !r.success) return;

    const d = r.data;
    renderClassReportHTML(d, document.getElementById('reportOutput'));
}

async function renderCompareReport() {
    const r = await API.get('report_compare');
    if (!r || !r.success) return;

    const classes = r.data;
    
    // Capture data for PDFBuilder
    window._lastReportData = { classes: classes };
    window._lastReportType = 'compare';


    document.getElementById('reportContent').innerHTML = `
    <div class="fade-in px-4 md:px-0">
        <div class="flex flex-col md:flex-row justify-center md:justify-end gap-3 mb-8 no-print px-1">
            <button onclick="sendReportEmail('reportContent', 'لوحة مقارنة الفصول')" class="w-full md:w-auto bg-indigo-600 text-white px-8 py-4 rounded-[1.5rem] font-black hover:bg-indigo-700 transition shadow-xl flex items-center justify-center gap-3 active:scale-95 text-sm md:text-base">
                <span class="text-2xl">📧</span> بريد
            </button>
            <button onclick="downloadReportPDF('reportContent', 'لوحة مقارنة الفصول')" class="w-full md:w-auto bg-emerald-600 text-white px-8 py-4 rounded-[1.5rem] font-black hover:bg-emerald-700 transition shadow-xl flex items-center justify-center gap-3 active:scale-95 text-sm md:text-base">
                <span class="text-2xl">💾</span> تحميل PDF
            </button>
            <button onclick="window.print()" class="w-full md:w-auto bg-gray-900 text-white px-8 py-4 rounded-[1.5rem] font-black hover:bg-black transition shadow-xl flex items-center justify-center gap-3 active:scale-95 text-sm md:text-base">
                <span class="text-2xl">🖨️</span> طباعة لوحة المقارنة
            </button>
        </div>
        
        <div class="bg-white rounded-[2rem] md:rounded-[3.5rem] shadow-2xl shadow-gray-200/50 border border-gray-100 p-6 md:p-12 relative overflow-hidden">
            <!-- Decorative Elements -->
            <div class="absolute top-0 right-0 w-80 h-80 bg-emerald-50 rounded-full -mr-40 -mt-40 opacity-40"></div>
            <div class="absolute bottom-0 left-0 w-64 h-64 bg-green-50 rounded-full -ml-32 -mb-32 opacity-30"></div>
            
            <div class="relative z-10">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-8 mb-12">
                    <div class="text-center md:text-right">
                        <h3 class="text-3xl md:text-4xl font-black text-gray-800">⚖️ تحليل مقارنة الفصول</h3>
                        <p class="text-gray-400 font-bold mt-2 text-sm md:text-base tracking-tight">مؤشرات الأداء البدني لجميع الفصول التعليمية المشتركة</p>
                    </div>
                    <div class="bg-green-600 px-8 py-4 rounded-[2rem] border border-green-500 shadow-xl shadow-green-100 flex flex-col items-center md:items-end self-center md:self-auto min-w-[140px]">
                        <span class="text-[10px] text-green-100 font-black uppercase tracking-widest block mb-1">إجمالي الفصول</span>
                        <span class="text-4xl font-black text-white">${classes.length}</span>
                    </div>
                </div>

                <div class="space-y-4 md:space-y-6">
                    ${classes.map((c, i) => `
                    <div class="group bg-gray-50/50 hover:bg-white hover:shadow-2xl transition-all duration-500 p-5 md:p-8 rounded-[2rem] md:rounded-[2.5rem] border border-transparent hover:border-green-100">
                        <div class="flex flex-col md:flex-row md:items-center gap-6 md:gap-8">
                            <!-- Rank Icon -->
                            <div class="w-14 h-14 md:w-16 md:h-16 rounded-[1.5rem] md:rounded-[2rem] ${i < 3 ? 'bg-gradient-to-br from-green-500 to-emerald-700 text-white shadow-xl shadow-green-100' : 'bg-white text-gray-400 border border-gray-100'} font-black flex items-center justify-center text-xl md:text-2xl flex-shrink-0 transition-transform group-hover:scale-110 group-hover:rotate-6">
                                ${i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : (i + 1)}
                            </div>
                            
                            <div class="flex-1 w-full">
                                <div class="flex flex-col md:flex-row justify-between items-start md:items-end mb-4 gap-2">
                                    <div>
                                        <h5 class="font-black text-gray-800 text-lg md:text-2xl group-hover:text-green-600 transition-colors">${esc(c.class_name)}</h5>
                                        <p class="text-[10px] md:text-xs text-gray-400 font-black uppercase tracking-widest mt-0.5">${c.students_count} طالباً مسجلاً في هذا الفصل</p>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-2xl md:text-4xl font-black text-gray-900">${c.percentage}<span class="text-sm md:text-xl text-green-600 ml-1">%</span></span>
                                    </div>
                                </div>
                                <div class="w-full bg-gray-200/50 rounded-full h-5 md:h-6 p-1 overflow-hidden shadow-inner ring-4 ring-white/80">
                                    <div class="h-full rounded-full transition-all duration-1000 ease-out ${i === 0 ? 'bg-gradient-to-l from-yellow-400 to-yellow-600' : i === 1 ? 'bg-gradient-to-l from-gray-300 to-gray-500' : i === 2 ? 'bg-gradient-to-l from-orange-400 to-orange-600' : 'bg-gradient-to-l from-green-500 to-emerald-600'}" 
                                         style="width:${c.bar_width}%">
                                        <div class="w-full h-full bg-white/20 animate-pulse"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    `).join('')}
                </div>
            </div>
        </div>
        
        <div class="text-center text-[10px] text-gray-400 mt-12 mb-8 opacity-50 font-black uppercase tracking-[0.3em]">
            PE Smart School Intelligence Report System
        </div>
    </div>`;
}

// ============================================================
// PDF GENERATION AND EMAIL DELIVERY
// ============================================================
async function sendReportEmail(elementId, title) {
    if (!window._lastReportData || !window._lastReportType) {
        showToast('يرجى توليد التقرير وعرضه أولاً لاختيار البيانات المطلوبة.', 'error');
        return;
    }

    const element = document.getElementById(elementId);
    if (!element || element.innerText.includes('يرجى اختيار')) {
        showToast('يرجى توليد التقرير وعرضه أولاً لاختيار البيانات المطلوبة.', 'error');
        return;
    }

    showModal(`
        <div class="p-8 md:p-10">
            <div class="flex items-center gap-4 mb-8">
                <div class="w-16 h-16 rounded-[1.5rem] bg-indigo-50 text-indigo-600 flex items-center justify-center text-3xl">📧</div>
                <div>
                    <h3 class="text-2xl font-black text-gray-800">إرسال التقرير عبر البريد</h3>
                    <p class="text-gray-400 font-bold text-sm">${esc(title)}</p>
                </div>
            </div>
            <div class="space-y-2 mb-8">
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest">البريد الإلكتروني للمستلم</label>
                <input type="email" id="reportEmailInput" 
                    class="w-full px-6 py-4 bg-gray-50 border-2 border-transparent rounded-2xl focus:bg-white focus:border-indigo-500 focus:outline-none transition-all font-bold text-gray-700 text-sm" 
                    placeholder="example@school.edu.sa">
            </div>
            <div class="flex gap-4">
                <button onclick="_doSendReport('${elementId}', '${title}')" class="flex-1 bg-indigo-600 text-white py-4 rounded-2xl font-black hover:bg-indigo-700 transition flex items-center justify-center gap-3">
                    <span class="text-xl">📧</span> إرسال PDF
                </button>
                <button onclick="closeModal()" class="w-32 bg-gray-100 text-gray-500 py-4 rounded-2xl font-black hover:bg-gray-200 transition">إلغاء</button>
            </div>
        </div>
    `);
}

/**
 * Internal: performs the actual PDF generation and email send
 * Called from the email modal above
 */
async function _doSendReport(elementId, title) {
    const emailInput = document.getElementById('reportEmailInput');
    const email = emailInput ? emailInput.value.trim() : '';

    if (!email) {
        showToast('الرجاء إدخال البريد الإلكتروني', 'error');
        return;
    }
    if (!email.includes('@') || !email.includes('.')) {
        showToast('البريد الإلكتروني غير صالح', 'error');
        return;
    }

    closeModal();
    showToast('جاري تحويل التقرير وإرساله... ⏳', 'info');

    try {
        const type = window._lastReportType || 'student';
        const data = window._lastReportData || {};

        const response = await fetch(window.APP_BASE + 'api/generate_pdf.php?_t=' + Date.now(), {
            method: 'POST',
            credentials: 'include',
            body: JSON.stringify({ 
                type: type, 
                data: data, 
                filename: title,
                recipient_email: email 
            }),
            headers: { 'Content-Type': 'application/json' }
        });

        const result = await response.json();
        if (result.success) {
            showToast(result.message || 'تم إرسال التقرير بنجاح! ✅', 'success');
        } else {
            throw new Error(result.error || 'فشل الإرسال من الخادم');
        }
    } catch (e) {
        showToast('خطأ أثناء الإرسال: ' + e.message, 'error');
        console.error('Email Error:', e);
    }
}

/**
 * Shared PDF options to ensure consistency and fix compatibility
 */
function getPDFFunctionOptions(filename) {
    return {
        margin: [0.3, 0.3, 0.3, 0.3],
        filename: `${filename}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: {
            scale: 2,
            useCORS: true,
            letterRendering: true,
            allowTaint: true,
            logging: false,
            // Force higher contrast for the canvas to avoid issues with translucent vars
            backgroundColor: '#ffffff'
        },
        jsPDF: { unit: 'in', format: 'a4', orientation: 'portrait' }
    };
}

/**
 * Direct PDF Download function
 */
/**
 * Server-side PDF Download using mPDF
 */
async function downloadReportPDF(elementId, title) {
    if (!window._lastReportData || !window._lastReportType) {
        showToast('يرجى عرض التقرير أولاً', 'error');
        return;
    }

    showToast('جاري تجهيز التقرير الاحترافي (A4)... ⏳', 'info');

    try {
        const response = await fetch(window.APP_BASE + 'api/generate_pdf.php?_t=' + Date.now(), {
            method: 'POST',
            credentials: 'include',
            body: JSON.stringify({
                type: window._lastReportType,
                data: window._lastReportData,
                filename: title
            }),
            headers: { 'Content-Type': 'application/json' }
        });

        if (!response.ok) throw new Error('Server returned ' + response.status);

        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${title}.pdf`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        
        showToast('تم تحميل التقرير بنجاح! ✅', 'success');
    } catch (e) {
        showToast('فشل في تحميل الـ PDF من الخادم', 'error');
        console.error('Server PDF Download Error:', e);
        
        // Absolute fallback to legacy if server fails
        await _legacyDownloadPDF(elementId, title);
    }
}

async function _legacyDownloadPDF(elementId, title) {
    const element = document.getElementById(elementId);
    if (!element) return;
    showToast('جاري التحميل بالوضع الاحتياطي... ⏳', 'warning');
    try {
        await html2pdf().set(getPDFFunctionOptions(title)).from(element).save();
    } catch (e) {
        showToast('فشل التحميل النهائي', 'error');
    }
}


// ============================================================
// GRADING REPORT
// ============================================================
async function renderGradingReport() {
    const cl = await API.get('classes');

    document.getElementById('reportContent').innerHTML = `
    <div class="fade-in">
        <div class="bg-white rounded-[2rem] md:rounded-[2.5rem] shadow-xl shadow-gray-100/50 border border-gray-100 p-5 md:p-8 mb-8 md:mb-10 no-print">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div class="space-y-2">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mr-2">اختيار الفصل المستهدف</label>
                    <select id="gradingReportClass" class="w-full px-5 py-3.5 bg-gray-50 border-2 border-gray-50 rounded-2xl focus:bg-white focus:border-indigo-500 focus:outline-none transition-all font-bold text-gray-700 appearance-none cursor-pointer">
                        <option value="">-- اضغط للاختيار --</option>
                        ${(cl?.data || []).map(c => `<option value="${c.id}">${esc(c.full_name || c.name)}</option>`).join('')}
                    </select>
                </div>
                <div class="space-y-2">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mr-2">من تاريخ</label>
                    <input type="date" id="gradingDateStart" value="${new Date().getFullYear() + '-' + String(new Date().getMonth()).padStart(2, '0') + '-01'}" class="w-full px-4 py-3 bg-gray-50 border-2 border-transparent rounded-2xl focus:bg-white focus:border-indigo-500 focus:outline-none font-bold text-gray-700">
                </div>
                <div class="space-y-2">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mr-2">إلى تاريخ</label>
                    <input type="date" id="gradingDateEnd" value="${new Date().toISOString().split('T')[0]}" class="w-full px-4 py-3 bg-gray-50 border-2 border-transparent rounded-2xl focus:bg-white focus:border-indigo-500 focus:outline-none font-bold text-gray-700">
                </div>
                <div class="pt-1 flex gap-2">
                    <button onclick="generateGradingReport()" class="flex-1 bg-indigo-600 text-white px-2 py-3.5 rounded-2xl font-black hover:bg-indigo-700 transition shadow-lg shadow-indigo-100 flex items-center justify-center gap-2">
                        <span class="text-xl">📊</span> استخراج
                    </button>
                    <button onclick="window.print()" class="w-16 bg-gray-900 text-white p-3.5 rounded-2xl font-black hover:bg-black transition shadow-lg flex items-center justify-center">
                        🖨️
                    </button>
                </div>
            </div>
        </div>
        <div id="reportOutput">
            <div class="text-center py-24 bg-white rounded-[3rem] border-2 border-dashed border-gray-100">
                <div class="w-24 h-24 bg-gray-50 rounded-full flex items-center justify-center text-4xl mx-auto mb-6 grayscale opacity-30">📋</div>
                <p class="text-gray-400 font-black text-xl">يرجى تحديد الفصل والفترة لاستخراج كشف الدرجات</p>
                <p class="text-gray-300 text-sm mt-1">يعتمد هذا الكشف على أوزان التقييم المخصصة للمدرسة من الإدارة</p>
            </div>
        </div>
    </div>`;
}

async function generateGradingReport() {
    const classId = document.getElementById('gradingReportClass').value;
    const start = document.getElementById('gradingDateStart').value;
    const end = document.getElementById('gradingDateEnd').value;

    if (!classId) {
        showToast('يرجى اختيار الفصل', 'error');
        return;
    }

    const reportOutput = document.getElementById('reportOutput');
    reportOutput.innerHTML = showLoading();

    const r = await API.get('report_grading', {
        class_id: classId,
        start_date: start,
        end_date: end
    });

    if (!r || !r.success) {
        reportOutput.innerHTML = '<p class="text-red-500 text-center py-8">فشل استخراج التقرير</p>';
        return;
    }

    const { weights, students } = r.data;
    const className = document.getElementById('gradingReportClass').options[document.getElementById('gradingReportClass').selectedIndex].text;

    // Capture data for PDFBuilder
    window._lastReportData = r.data;
    window._lastReportData.className = className;
    window._lastReportData.start = start;
    window._lastReportData.end = end;
    window._lastReportType = 'grading';

    reportOutput.innerHTML = `
    <div class="bg-white rounded-[2rem] shadow-2xl border border-gray-100 p-6 md:p-10 fade-in">
        <div class="flex justify-between items-center mb-6 pb-6 border-b border-gray-100">
            <div>
                <h3 class="text-2xl font-black text-gray-800">📊 كشف الدرجات النهائي للتربية البدنية</h3>
                <p class="text-indigo-600 font-bold mt-1">الفصل: ${className} | الفترة: ${start} إلى ${end}</p>
            </div>
            <div class="text-left hidden md:block">
                <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-2">توزيع الدرجات المعتمد (100٪)</p>
                <div class="flex flex-wrap gap-2">
                    <span class="bg-blue-50 text-blue-700 px-3 py-1 rounded-lg text-xs font-bold ring-1 ring-blue-100">حضور: ${weights.attendance_pct}%</span>
                    <span class="bg-emerald-50 text-emerald-700 px-3 py-1 rounded-lg text-xs font-bold ring-1 ring-emerald-100">زي: ${weights.uniform_pct}%</span>
                    <span class="bg-yellow-50 text-yellow-700 px-3 py-1 rounded-lg text-xs font-bold ring-1 ring-yellow-100">سلوك: ${weights.behavior_skills_pct}%</span>
                    <span class="bg-orange-50 text-orange-700 px-3 py-1 rounded-lg text-xs font-bold ring-1 ring-orange-100">مشاركة: ${weights.participation_pct}%</span>
                    <span class="bg-purple-50 text-purple-700 px-3 py-1 rounded-lg text-xs font-bold ring-1 ring-purple-100">لياقة: ${weights.fitness_pct}%</span>
                    <span class="bg-indigo-50 text-indigo-700 px-3 py-1 rounded-lg text-xs font-bold ring-1 ring-indigo-100">اختبار: ${weights.quiz_pct}%</span>
                    <span class="bg-teal-50 text-teal-700 px-3 py-1 rounded-lg text-xs font-bold ring-1 ring-teal-100">مجموع: ${weights.project_pct}%</span>
                    <span class="bg-rose-50 text-rose-700 px-3 py-1 rounded-lg text-xs font-bold ring-1 ring-rose-100">نهائي: ${weights.final_exam_pct}%</span>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto pb-4">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50/50">
                        <th class="px-4 py-3 text-right font-black text-gray-500 rounded-r-xl">م</th>
                        <th class="px-4 py-3 text-right font-black text-gray-500 border-x border-gray-100">الطالب</th>
                        <th class="px-2 py-3 text-center font-black text-gray-500 border-l border-gray-100" title="الحضور (${weights.attendance_pct})">ح (${weights.attendance_pct})</th>
                        <th class="px-2 py-3 text-center font-black text-gray-500 border-l border-gray-100" title="الزي (${weights.uniform_pct})">ز (${weights.uniform_pct})</th>
                        <th class="px-2 py-3 text-center font-black text-gray-500 border-l border-gray-100" title="السلوك (${weights.behavior_skills_pct})">س (${weights.behavior_skills_pct})</th>
                        <th class="px-2 py-3 text-center font-black text-gray-500 border-l border-gray-100" title="المشاركة (${weights.participation_pct})">م (${weights.participation_pct})</th>
                        <th class="px-2 py-3 text-center font-black text-gray-500 border-l border-gray-100" title="اللياقة (${weights.fitness_pct})">ل (${weights.fitness_pct})</th>
                        <th class="px-2 py-3 text-center font-black text-gray-500 border-l border-gray-100" title="إختبار قصير (${weights.quiz_pct})">ق (${weights.quiz_pct})</th>
                        <th class="px-2 py-3 text-center font-black text-gray-500 border-l border-gray-100" title="مشروع/بحث (${weights.project_pct})">ج (${weights.project_pct})</th>
                        <th class="px-2 py-3 text-center font-black text-gray-500 border-l border-gray-100" title="اختبار نهائي (${weights.final_exam_pct})">ن (${weights.final_exam_pct})</th>
                        <th class="px-4 py-3 text-center font-black text-gray-800 border-l border-gray-100 bg-gray-100">المجموع</th>
                        <th class="px-4 py-3 text-center font-black text-gray-500 rounded-l-xl">التقدير</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    ${students.map((s, i) => `
                    <tr class="hover:bg-indigo-50/30 transition-colors">
                        <td class="px-4 py-3 text-gray-400 font-bold">${i + 1}</td>
                        <td class="px-4 py-3 font-black text-gray-800 border-x border-gray-50">${esc(s.name)}</td>
                        <td class="px-2 py-3 text-center font-bold text-gray-600 border-l border-gray-50">${s.attendance_score}</td>
                        <td class="px-2 py-3 text-center font-bold text-gray-600 border-l border-gray-50">${s.uniform_score}</td>
                        <td class="px-2 py-3 text-center font-bold text-gray-600 border-l border-gray-50">${s.behavior_skills_score}</td>
                        <td class="px-2 py-3 text-center font-bold text-gray-600 border-l border-gray-50">${s.participation_score}</td>
                        <td class="px-2 py-3 text-center font-bold text-gray-600 border-l border-gray-50">${s.fitness_score}</td>
                        <td class="px-2 py-3 text-center font-bold text-gray-600 border-l border-gray-50">${s.quiz_score}</td>
                        <td class="px-2 py-3 text-center font-bold text-gray-600 border-l border-gray-50">${s.project_score}</td>
                        <td class="px-2 py-3 text-center font-bold text-gray-600 border-l border-gray-50">${s.final_exam_score}</td>
                        <td class="px-4 py-3 text-center font-black border-l border-gray-50 bg-gray-50/50 text-indigo-700 text-lg">
                            ${s.final_grade}
                        </td>
                        <td class="px-4 py-3 text-center font-black">
                            <span class="inline-flex px-3 py-1 rounded-full items-center justify-center whitespace-nowrap text-xs font-bold ${s.letter === 'ممتاز' ? 'bg-green-100 text-green-700 ring-1 ring-green-200' :
            s.letter === 'جيد جداً' ? 'bg-blue-100 text-blue-700 ring-1 ring-blue-200' :
                s.letter === 'جيد' ? 'bg-yellow-100 text-yellow-700 ring-1 ring-yellow-200' :
                    s.letter === 'مقبول' ? 'bg-orange-100 text-orange-700 ring-1 ring-orange-200' :
                        'bg-red-100 text-red-700 ring-1 ring-red-200'
        }">
                                ${s.letter}
                            </span>
                        </td>
                    </tr>
                    `).join('')}
                    ${students.length === 0 ? `<tr><td colspan="8" class="px-4 py-8 text-center text-gray-400 font-bold">لاتوجد بيانات طلاب لهذا الفصل</td></tr>` : ''}
                </tbody>
            </table>
        </div>
        
        <div class="mt-8 flex flex-col md:flex-row justify-between items-center gap-4 no-print">
             <div class="flex gap-2 w-full md:w-auto">
                <button onclick="sendReportEmail('reportOutput', 'كشف درجات الفصل ${className}')" class="flex-1 bg-indigo-100 text-indigo-700 px-6 py-2 rounded-xl font-bold hover:bg-indigo-200 transition flex items-center justify-center gap-2">
                    📧 إرسال بريد
                </button>
                <button onclick="downloadReportPDF('reportOutput', 'كشف درجات الفصل ${className}')" class="flex-1 bg-emerald-100 text-emerald-700 px-6 py-2 rounded-xl font-bold hover:bg-emerald-200 transition flex items-center justify-center gap-2">
                    💾 تحميل PDF
                </button>
             </div>
             <p class="text-[10px] text-gray-400 uppercase tracking-widest font-black">PE Smart System • ${new Date().toLocaleDateString('ar-SA')}</p>
        </div>
    </div>`;
}

// ============================================================
// MONITORING REPORT (كشف متابعة فصل)
// ============================================================
async function renderMonitoringReport() {
    const cl = await API.get('classes');

    document.getElementById('reportContent').innerHTML = `
    <div class="fade-in">
        <div class="bg-white rounded-[2rem] md:rounded-[2.5rem] shadow-xl shadow-gray-100/50 border border-gray-100 p-5 md:p-8 mb-8 md:mb-10 no-print">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div class="space-y-2">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mr-2">اختيار الفصل</label>
                    <select id="monReportClass" class="w-full px-5 py-3.5 bg-gray-50 border-2 border-gray-50 rounded-2xl focus:bg-white focus:border-orange-500 focus:outline-none transition-all font-bold text-gray-700 appearance-none cursor-pointer">
                        <option value="">-- اضغط للاختيار --</option>
                        ${(cl?.data || []).map(c => `<option value="${c.id}">${esc(c.full_name || c.name)}</option>`).join('')}
                    </select>
                </div>
                <div class="space-y-2">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mr-2">من تاريخ</label>
                    <input type="date" id="monDateStart" value="${new Date().getFullYear() + '-' + String(new Date().getMonth() + 1).padStart(2, '0') + '-01'}" class="w-full px-4 py-3 bg-gray-50 border-2 border-transparent rounded-2xl focus:bg-white focus:border-orange-500 focus:outline-none font-bold text-gray-700">
                </div>
                <div class="space-y-2">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mr-2">إلى تاريخ</label>
                    <input type="date" id="monDateEnd" value="${new Date().toISOString().split('T')[0]}" class="w-full px-4 py-3 bg-gray-50 border-2 border-transparent rounded-2xl focus:bg-white focus:border-orange-500 focus:outline-none font-bold text-gray-700">
                </div>
                <div class="pt-1 flex gap-2">
                    <button onclick="generateMonitoringReport()" class="flex-1 bg-orange-600 text-white px-2 py-3.5 rounded-2xl font-black hover:bg-orange-700 transition shadow-lg shadow-orange-100 flex items-center justify-center gap-2">
                        <span class="text-xl">📋</span> استخراج
                    </button>
                    <button onclick="window.print()" class="w-16 bg-gray-900 text-white p-3.5 rounded-2xl font-black hover:bg-black transition shadow-lg flex items-center justify-center">
                        🖨️
                    </button>
                </div>
            </div>
        </div>
        <div id="reportOutput">
            <div class="text-center py-24 bg-white rounded-[3rem] border-2 border-dashed border-gray-100 text-orange-200">
                <div class="w-24 h-24 bg-orange-50 rounded-full flex items-center justify-center text-4xl mx-auto mb-6 grayscale opacity-30">📋</div>
                <p class="text-gray-400 font-black text-xl">يرجى تحديد الفصل والفترة لاستخراج كشف المتابعة</p>
                <p class="text-gray-300 text-sm mt-1">يظهر هذا التقرير تفاصيل الحصص: الحضور، الزي، المشاركة، اللياقة، والسلوك</p>
            </div>
        </div>
    </div>`;
}

async function generateMonitoringReport() {
    const classId = document.getElementById('monReportClass').value;
    const start = document.getElementById('monDateStart').value;
    const end = document.getElementById('monDateEnd').value;

    if (!classId) {
        showToast('يرجى اختيار الفصل', 'error');
        return;
    }

    const reportOutput = document.getElementById('reportOutput');
    reportOutput.innerHTML = showLoading();

    const r = await API.get('report_monitoring', {
        class_id: classId,
        start_date: start,
        end_date: end
    });

    if (!r || !r.success) {
        reportOutput.innerHTML = '<p class="text-red-500 text-center py-8">فشل استخراج التقرير</p>';
        return;
    }

    renderMonitoringReportHTML(r.data, reportOutput);
}

function renderMonitoringReportHTML(d, container) {
    // Capture data for PDFBuilder
    window._lastReportData = d;
    window._lastReportType = 'monitoring';
    const students = d.students || [];
    const dates = d.dates || [];
    const matrix = d.matrix || {};
    const className = d.class.full_name;

    if (dates.length === 0) {
        container.innerHTML = `<div class="p-12 text-center text-gray-400 font-bold bg-white rounded-3xl border-2 border-dashed">لا توجد حصص مسجلة في هذه الفترة لهذا الفصل</div>`;
        return;
    }

    // Header structure for dates: 1 header spanning 5 columns
    let headerHTML = '';
    let subHeaderHTML = '';
    const attMap = { 'present': 'ح', 'absent': 'غ', 'late': 'م', 'excused': 'عذر' };

    dates.forEach(dt => {
        headerHTML += `<th colspan="5" class="px-2 py-3 text-center border-l-2 border-gray-200 bg-gray-50 text-[10px] font-black">${dt.replace(/-/g, '/')}</th>`;
        subHeaderHTML += `
            <th class="px-1 py-2 text-[8px] font-black text-gray-400 border-l border-gray-100">حضور</th>
            <th class="px-1 py-2 text-[8px] font-black text-gray-400 border-l border-gray-100">ملابس</th>
            <th class="px-1 py-2 text-[8px] font-black text-gray-400 border-l border-gray-100">مشاركة</th>
            <th class="px-1 py-2 text-[8px] font-black text-gray-400 border-l border-gray-200">لياقة</th>
            <th class="px-1 py-2 text-[8px] font-black text-gray-400 border-l-2 border-gray-200 bg-gray-50/50">سلوك</th>
        `;
    });

    container.innerHTML = `
    <div class="bg-white rounded-[2rem] shadow-2xl border border-gray-100 p-4 md:p-8 fade-in overflow-hidden">
        <div class="flex justify-between items-center mb-8 pb-4 border-b border-gray-100 no-print">
            <div>
                <h3 class="text-xl font-black text-gray-800">📋 كشف متابعة فصل (سجل الأداء اليومي)</h3>
                <p class="text-orange-600 font-bold mt-1">الفصل: ${esc(className)}</p>
            </div>
            <div class="flex gap-2">
                <button onclick="window.print()" class="bg-gray-900 text-white px-5 py-2 rounded-xl font-black text-sm">🖨️ طباعة</button>
                <button onclick="downloadReportPDF('monitoringTableWrapper', 'كشف متابعة ${esc(className)}')" class="bg-emerald-600 text-white px-5 py-2 rounded-xl font-black text-sm">💾 PDF</button>
            </div>
        </div>

        <div id="monitoringTableWrapper" class="overflow-x-auto print:overflow-visible">
            <div class="min-w-max">
                <h2 class="text-center font-black text-xl mb-6 hidden print:block">كشف متابعة فصل (${esc(className)})</h2>
                <table class="w-full text-center border-collapse border-2 border-gray-200">
                    <thead>
                        <tr class="bg-gray-100">
                            <th rowspan="2" class="px-3 py-4 border-2 border-gray-200 text-xs font-black w-10">م</th>
                            <th rowspan="2" class="px-6 py-4 border-2 border-gray-200 text-sm font-black min-w-[200px] text-right">اسم الطالب</th>
                            ${headerHTML}
                        </tr>
                        <tr class="bg-white">
                            ${subHeaderHTML}
                        </tr>
                    </thead>
                    <tbody>
                        ${students.map((s, i) => {
        let rowCells = '';
        dates.forEach(dt => {
            const m = (matrix[s.id] && matrix[s.id][dt]) ? matrix[s.id][dt] : null;
            if (m) {
                const uniIcon = m.uniform === 'full' ? '✓' : m.uniform === 'wrong' ? 'X' : '-';
                rowCells += `
                                        <td class="border border-gray-200 px-1 py-3 text-xs font-black ${m.status === 'absent' ? 'text-red-600 bg-red-50/30' : 'text-gray-800'}">${attMap[m.status] || '-'}</td>
                                        <td class="border border-gray-200 px-1 py-3 text-xs font-bold ${m.uniform === 'wrong' ? 'text-red-500' : 'text-green-600'}">${uniIcon}</td>
                                        <td class="border border-gray-200 px-1 py-3 text-xs font-black text-gray-600">${m.participation || '-'}</td>
                                        <td class="border border-gray-200 px-1 py-3 text-xs font-black text-blue-600 bg-blue-50/10">${m.fitness || '-'}</td>
                                        <td class="border border-gray-200 px-1 py-3 text-xs font-black text-yellow-600 border-l-2 bg-gray-50/30">${m.behavior || m.skills || '-'}</td>
                                    `;
            } else {
                rowCells += `
                                        <td class="border border-gray-200 bg-gray-50/20" colspan="5">-</td>
                                    `;
            }
        });

        return `
                                <tr class="hover:bg-orange-50/20 transition-colors">
                                    <td class="border-2 border-gray-200 px-2 py-3 font-bold text-gray-400 text-xs">${i + 1}</td>
                                    <td class="border-2 border-gray-200 px-4 py-3 font-black text-gray-800 text-right text-sm">${esc(s.name)}</td>
                                    ${rowCells}
                                </tr>
                            `;
    }).join('')}
                    </tbody>
                </table>
            </div>
            <div class="mt-6 text-[10px] text-gray-400 font-bold uppercase tracking-widest text-center hidden print:block">
                نظام PE Smart School • تم الاستخراج في ${new Date().toLocaleDateString('ar-SA')}
            </div>
        </div>
    </div>`;
}
