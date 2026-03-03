/**
 * PE Smart School System - Fitness Tests Page
 */

let fitnessTab = 'tests';
let fitnessFilter = { class_id: '', test_id: '' };

async function renderFitness() {
    if (typeof hasFeature === 'function' && !hasFeature('fitness_tests')) {
        document.getElementById('mainContent').innerHTML = `
            <div class="text-center flex flex-col items-center justify-center py-20 bg-white rounded-[2.5rem] shadow-sm border border-gray-100 mt-6 mx-2">
                <div class="w-24 h-24 bg-red-50 text-red-500 rounded-full flex items-center justify-center text-4xl mb-6 shadow-inner">🔒</div>
                <h3 class="text-2xl font-black text-gray-800 mb-3">الصلاحية غير متوفرة</h3>
                <p class="text-gray-500 font-bold max-w-md">ميزة "اختبارات اللياقة البدنية" غير مشمولة في باقة اشتراككم الحالية. يرجى التواصل مع إدارة النظام لترقية الحساب.</p>
            </div>
        `;
        return;
    }

    document.getElementById('mainContent').innerHTML = `
    <div class="fade-in max-w-full overflow-x-hidden">
        <div class="mb-6 px-1">
            <h2 class="text-2xl md:text-4xl font-black text-gray-800 tracking-tight">💪 اختبارات الأداء البدني</h2>
            <p class="text-gray-400 font-bold mt-1 text-[10px] md:text-base opacity-80">إدارة الاختبارات الميدانية وتسجيل النتائج الرسمية</p>
        </div>
        
        <div class="flex overflow-x-auto gap-2 mb-8 no-print scrollbar-hide pb-2 px-1">
            <button onclick="fitnessTab='tests';renderFitness()" class="whitespace-nowrap px-4 md:px-8 py-3 rounded-2xl font-black transition-all duration-300 text-xs md:text-base ${fitnessTab === 'tests' ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-100 scale-105 active:scale-95' : 'bg-white text-gray-400 hover:text-gray-600 border border-gray-100'} cursor-pointer">📏 الاختبارات المعيارية</button>
            <button onclick="fitnessTab='results';renderFitness()" class="whitespace-nowrap px-4 md:px-8 py-3 rounded-2xl font-black transition-all duration-300 text-xs md:text-base ${fitnessTab === 'results' ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-100 scale-105 active:scale-95' : 'bg-white text-gray-400 hover:text-gray-600 border border-gray-100'} cursor-pointer">📝 رصد النتائج</button>
            <button onclick="fitnessTab='view';renderFitness()" class="whitespace-nowrap px-4 md:px-8 py-3 rounded-2xl font-black transition-all duration-300 text-xs md:text-base ${fitnessTab === 'view' ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-100 scale-105 active:scale-95' : 'bg-white text-gray-400 hover:text-gray-600 border border-gray-100'} cursor-pointer">📊 استعراض البيانات</button>
        </div>
        
        <div id="fitnessContent" class="mb-20 min-h-[400px] w-full">${showLoading()}</div>
    </div>`;

    if (fitnessTab === 'tests') renderFitnessTests();
    else if (fitnessTab === 'results') renderFitnessEntry();
    else renderFitnessView();
}

async function renderFitnessTests() {
    const r = await API.get('fitness_tests');
    const tests = r?.data || [];

    document.getElementById('fitnessContent').innerHTML = `
    <div class="fade-in w-full overflow-hidden">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8 px-1">
            <div>
                <h4 class="text-xl md:text-3xl font-black text-gray-800">📋 الاختبارات المعتمدة</h4>
                <p class="text-gray-400 font-bold mt-1 text-[9px] md:text-sm">المعايير الوطنية لتقييم القدرات الحركية والبدنية</p>
            </div>
            ${canEdit() ? `
            <button onclick="showTestForm()" class="w-full md:w-auto bg-emerald-600 text-white px-6 py-3.5 rounded-2xl font-black hover:bg-emerald-700 transition shadow-lg shadow-emerald-100 flex items-center justify-center gap-3 active:scale-95 text-xs">
                <span class="text-lg">➕</span> إضافة اختبار جديد
            </button>
            ` : ''}
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 md:gap-6">
            ${tests.map(t => {
        const isLowerBetter = t.type === 'lower_better';
        const icon = t.name.includes('جري') || t.name.includes('ركض') ? '🏃'
            : t.name.includes('وثب') || t.name.includes('قفز') ? '👟'
                : t.name.includes('مرونة') ? '🧘'
                    : t.name.includes('قوة') || t.name.includes('ضغط') ? '💪'
                        : '📐';
        return `
                <div class="group bg-white rounded-[2rem] p-4 md:p-8 border border-gray-100 shadow-xl shadow-gray-100/50 hover:shadow-2xl hover:-translate-y-1 transition-all duration-300 relative overflow-hidden mx-0.5">
                    <!-- Background Accent -->
                    <div class="absolute top-0 right-0 w-24 h-24 bg-gray-50 rounded-full -mr-12 -mt-12 group-hover:bg-emerald-50 transition duration-500"></div>
                    
                    <div class="relative z-10">
                        <div class="flex flex-row-reverse justify-between items-start mb-6">
                            <div class="w-12 h-12 md:w-20 md:h-20 rounded-xl md:rounded-2xl bg-gray-50 group-hover:bg-white flex items-center justify-center text-xl md:text-4xl group-hover:scale-110 group-hover:rotate-6 transition-all duration-500 shadow-inner">
                                ${icon}
                            </div>
                            ${canEdit() ? `
                            <div class="flex gap-1 pt-1">
                                <button onclick='showTestForm(${JSON.stringify(t)})' class="w-8 h-8 md:w-11 md:h-11 flex items-center justify-center bg-gray-50 text-amber-500 rounded-lg hover:bg-amber-500 hover:text-white transition shadow-sm border border-gray-100" title="تعديل">✏️</button>
                                <button onclick="showCriteriaModal(${t.id}, '${esc(t.name)}')" class="w-8 h-8 md:w-11 md:h-11 flex items-center justify-center bg-gray-50 text-emerald-600 rounded-lg hover:bg-emerald-600 hover:text-white transition shadow-sm border border-gray-100" title="معايير التقييم الآلي">⚖️</button>
                                <button onclick="deleteFitnessTest(${t.id})" class="w-8 h-8 md:w-11 md:h-11 flex items-center justify-center bg-gray-50 text-red-500 rounded-lg hover:bg-red-600 hover:text-white transition shadow-sm border border-gray-100" title="حذف">🗑️</button>
                            </div>
                            ` : ''}
                        </div>
                        
                        <div>
                            <h5 class="font-black text-lg md:text-2xl text-gray-800 mb-2 truncate group-hover:text-emerald-700 transition" title="${esc(t.name)}">${esc(t.name)}</h5>
                            <div class="flex flex-wrap gap-1.5 mb-6">
                                <span class="px-2.5 py-1 bg-gray-50 text-[8px] md:text-[10px] font-black uppercase text-gray-400 rounded-full border border-gray-100">الوحدة: ${esc(t.unit)}</span>
                                <span class="px-2.5 py-1 ${isLowerBetter ? 'bg-orange-50 text-orange-600 border-orange-100' : 'bg-emerald-50 text-emerald-600 border-emerald-100'} text-[8px] md:text-[10px] font-black uppercase rounded-full border shadow-sm">${isLowerBetter ? 'الأقل أفضل ⬇️' : 'الأكثر أفضل ⬆️'}</span>
                            </div>
                            
                            <div class="bg-gray-900 rounded-2xl md:rounded-[2rem] p-4 md:p-6 flex items-center justify-between text-white shadow-xl shadow-gray-200">
                                <div>
                                    <p class="text-[8px] md:text-[10px] text-gray-400 font-black uppercase tracking-widest">الدرجة القصوى</p>
                                    <p class="text-xl md:text-3xl font-black text-emerald-400">${t.max_score}</p>
                                </div>
                                <div class="w-8 h-8 md:w-12 md:h-12 rounded-full border-2 border-emerald-500/30 flex items-center justify-center font-black text-sm text-emerald-500">
                                    ★
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`;
    }).join('')}
        </div>
    </div>`;
}

function showTestForm(t = null) {
    const isEdit = !!t;
    showModal(`
        <div class="p-8 md:p-12">
            <div class="flex items-center gap-4 mb-10">
                <div class="w-16 h-16 rounded-[1.5rem] bg-green-50 text-green-600 flex items-center justify-center text-3xl font-black">
                    ${isEdit ? '✏️' : '➕'}
                </div>
                <div>
                    <h3 class="text-3xl font-black text-gray-800">${isEdit ? 'تعديل الاختبار البدني' : 'إضافة اختبار بدني جديد'}</h3>
                    <p class="text-gray-400 font-bold text-sm">حدد معايير القياس ونوع التقييم لهذا الاختبار</p>
                </div>
            </div>
            
            <div class="space-y-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="space-y-2">
                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mr-2">اسم الاختبار</label>
                        <input type="text" id="testName" value="${t ? esc(t.name) : ''}" class="w-full px-6 py-5 bg-gray-50 border-2 border-gray-50 rounded-[1.5rem] focus:bg-white focus:border-green-600 focus:outline-none transition-all font-black text-gray-700" placeholder="مثال: جري 100 متر">
                    </div>
                    <div class="space-y-2">
                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mr-2">وحدة القياس</label>
                        <input type="text" id="testUnit" value="${t ? esc(t.unit) : ''}" class="w-full px-6 py-5 bg-gray-50 border-2 border-gray-50 rounded-[1.5rem] focus:bg-white focus:border-green-600 focus:outline-none transition-all font-black text-gray-700" placeholder="مثال: ثانية، متر، تكرار">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div class="space-y-2">
                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mr-2">نوع التقييم</label>
                        <select id="testType" class="w-full px-6 py-5 bg-gray-50 border-2 border-gray-50 rounded-[1.5rem] focus:bg-white focus:border-green-600 focus:outline-none transition-all font-black text-gray-700 appearance-none cursor-pointer">
                            <option value="higher_better" ${t && t.type === 'higher_better' ? 'selected' : ''}>الأكثر أفضل (تصاعدي) ⬆️</option>
                            <option value="lower_better" ${t && t.type === 'lower_better' ? 'selected' : ''}>الأقل أفضل (تنازلي) ⬇️</option>
                        </select>
                    </div>
                    <div class="space-y-2">
                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mr-2">الدرجة القصوى</label>
                        <input type="number" id="testMaxScore" value="${t ? t.max_score : 10}" class="w-full px-6 py-5 bg-gray-50 border-2 border-gray-50 rounded-[1.5rem] focus:bg-white focus:border-green-600 focus:outline-none transition-all font-black text-gray-700" placeholder="10">
                    </div>
                </div>

                <div class="flex flex-col md:flex-row gap-4 pt-6">
                    <button onclick="saveFitnessTest(${t ? t.id : 'null'})" class="flex-1 bg-green-600 text-white py-5 rounded-[1.5rem] font-black hover:bg-green-700 shadow-2xl shadow-green-100 transition active:scale-95 flex items-center justify-center gap-3">
                        <span class="text-xl">💾</span> حفظ وإعتماد الاختبار
                    </button>
                    <button onclick="closeModal()" class="md:w-32 bg-gray-100 text-gray-500 py-5 rounded-[1.5rem] font-black hover:bg-gray-200 transition active:scale-95">إلغاء</button>
                </div>
            </div>
        </div>
    `);
}

async function saveFitnessTest(id) {
    const data = {
        id,
        name: document.getElementById('testName').value.trim(),
        unit: document.getElementById('testUnit').value.trim(),
        type: document.getElementById('testType').value,
        max_score: document.getElementById('testMaxScore').value
    };

    if (!data.name) {
        showToast('أدخل اسم الاختبار', 'error');
        return;
    }

    const r = await API.post('fitness_test_save', data);
    if (r && r.success) {
        closeModal();
        showToast(r.message);
        renderFitness();
    }
}

async function deleteFitnessTest(id) {
    if (!confirm('حذف؟')) return;
    const r = await API.post('fitness_test_delete', null, { id });
    if (r && r.success) {
        showToast(r.message);
        renderFitness();
    }
}

// --- Automated Scoring Criteria ---
async function showCriteriaModal(testId, testName) {
    showModal(showLoading());
    const r = await API.get('fitness_criteria', { test_id: testId });
    const criteria = r?.data || [];

    const renderRows = () => (criteria.length > 0 ? criteria.map((c, i) => `
        <div class="grid grid-cols-3 gap-4 p-4 bg-gray-50 rounded-2xl relative group">
            <div class="space-y-1">
                <label class="text-[10px] font-black text-gray-400 uppercase">من قيمة</label>
                <input type="number" step="0.01" class="c-min w-full px-4 py-3 rounded-xl border-2 border-transparent focus:border-blue-500 focus:outline-none font-bold" value="${c.min_value}">
            </div>
            <div class="space-y-1">
                <label class="text-[10px] font-black text-gray-400 uppercase">إلى قيمة</label>
                <input type="number" step="0.01" class="c-max w-full px-4 py-3 rounded-xl border-2 border-transparent focus:border-blue-500 focus:outline-none font-bold" value="${c.max_value}">
            </div>
            <div class="space-y-1 relative">
                <label class="text-[10px] font-black text-gray-400 uppercase">الدرجة</label>
                <input type="number" class="c-score w-full px-4 py-3 rounded-xl border-2 border-transparent focus:border-green-500 focus:outline-none font-bold text-green-700" value="${c.score}">
                <button onclick="this.parentElement.parentElement.remove()" class="absolute -left-2 top-0 text-red-400 hover:text-red-600 opacity-0 group-hover:opacity-100 transition">✕</button>
            </div>
        </div>
    `).join('') : '<p class="text-center py-10 text-gray-400 italic">لا توجد معايير مضافة لهذا الاختبار</p>');

    showModal(`
        <div class="p-8 md:p-12" style="max-width:600px">
            <div class="flex items-center gap-4 mb-8">
                <div class="w-16 h-16 rounded-[1.5rem] bg-blue-50 text-blue-600 flex items-center justify-center text-3xl">⚖️</div>
                <div>
                    <h3 class="text-2xl font-black text-gray-800">معايير التقييم الآلي</h3>
                    <p class="text-gray-400 font-bold">${testName}</p>
                </div>
            </div>
            
            <div id="criteriaList" class="space-y-4 max-h-[400px] overflow-y-auto mb-8 pr-2">
                ${renderRows()}
            </div>
            
            <button onclick="addCriteriaRow()" class="w-full py-4 border-2 border-dashed border-gray-200 rounded-2xl text-gray-400 font-black hover:border-blue-400 hover:text-blue-500 transition mb-8">+ إضافة نطاق جديد</button>
            
            <div class="flex gap-4">
                <button onclick="saveCriteria(${testId})" class="flex-1 bg-gray-900 text-white py-5 rounded-[1.5rem] font-black hover:bg-black transition active:scale-95">حفظ وتفعيل المعايير</button>
                <button onclick="closeModal()" class="w-32 bg-gray-100 text-gray-500 py-5 rounded-[1.5rem] font-black">إغلاق</button>
            </div>
        </div>
    `);
}

function addCriteriaRow() {
    const div = document.createElement('div');
    div.className = 'grid grid-cols-3 gap-4 p-4 bg-gray-50 rounded-2xl relative group fade-in';
    div.innerHTML = `
        <div class="space-y-1">
            <label class="text-[10px] font-black text-gray-400 uppercase">من قيمة</label>
            <input type="number" step="0.01" class="c-min w-full px-4 py-3 rounded-xl border-2 border-transparent focus:border-blue-500 focus:outline-none font-bold">
        </div>
        <div class="space-y-1">
            <label class="text-[10px] font-black text-gray-400 uppercase">إلى قيمة</label>
            <input type="number" step="0.01" class="c-max w-full px-4 py-3 rounded-xl border-2 border-transparent focus:border-blue-500 focus:outline-none font-bold">
        </div>
        <div class="space-y-1 relative">
            <label class="text-[10px] font-black text-gray-400 uppercase">الدرجة</label>
            <input type="number" class="c-score w-full px-4 py-3 rounded-xl border-2 border-transparent focus:border-green-500 focus:outline-none font-bold text-green-700">
            <button onclick="this.parentElement.parentElement.remove()" class="absolute -left-2 top-0 text-red-400 hover:text-red-600 opacity-0 group-hover:opacity-100 transition">✕</button>
        </div>
    `;
    const list = document.getElementById('criteriaList');
    if (list.querySelector('p')) list.innerHTML = '';
    list.appendChild(div);
}

async function saveCriteria(testId) {
    const rows = document.querySelectorAll('#criteriaList > div');
    const criteria = [];
    rows.forEach(row => {
        const min = row.querySelector('.c-min').value;
        const max = row.querySelector('.c-max').value;
        const score = row.querySelector('.c-score').value;
        if (min !== '' && max !== '' && score !== '') {
            criteria.push({ min_value: min, max_value: max, score: score });
        }
    });

    const r = await API.post('fitness_criteria_save', { test_id: testId, criteria });
    if (r && r.success) {
        showToast(r.message);
        closeModal();
    }
}

async function renderFitnessEntry() {
    const [cl, ts] = await Promise.all([
        API.get('classes'),
        API.get('fitness_tests')
    ]);

    const classes = cl?.data || [];
    const tests = ts?.data || [];
    window._fitnessTests = tests;

    document.getElementById('fitnessContent').innerHTML = `
    <div class="fade-in">
        <div class="bg-white rounded-[2.5rem] shadow-xl shadow-gray-100/50 border border-gray-100 p-6 md:p-10 mb-10 overflow-hidden relative">
            <!-- Decorative Accent -->
            <div class="absolute top-0 left-0 w-2 h-full bg-emerald-500"></div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-10 items-end">
                <div class="space-y-3">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mr-2">اختيار الفصل التعليمي</label>
                    <div class="relative group">
                        <select id="fitClass" onchange="fitnessFilter.class_id=this.value;loadFitnessEntry()" class="w-full px-6 md:px-8 py-4 md:py-5 bg-gray-50 border-2 border-transparent rounded-2xl md:rounded-[1.8rem] focus:bg-white focus:border-emerald-500 focus:outline-none transition-all font-black text-gray-700 appearance-none cursor-pointer shadow-inner">
                            <option value="">-- اضغط للاختيار --</option>
                            ${classes.map(c => `<option value="${c.id}" ${fitnessFilter.class_id == c.id ? 'selected' : ''}>${esc(c.full_name || c.name)}</option>`).join('')}
                        </select>
                        <div class="absolute inset-y-0 left-6 flex items-center pointer-events-none text-gray-400 group-hover:text-emerald-500 transition">
                            <span class="text-xl">🏫</span>
                        </div>
                    </div>
                </div>
                <div class="space-y-3">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mr-2">نوع الاختبار البدني</label>
                    <div class="relative group">
                        <select id="fitTest" onchange="fitnessFilter.test_id=this.value;loadFitnessEntry()" class="w-full px-6 md:px-8 py-4 md:py-5 bg-gray-50 border-2 border-transparent rounded-2xl md:rounded-[1.8rem] focus:bg-white focus:border-emerald-500 focus:outline-none transition-all font-black text-gray-700 appearance-none cursor-pointer shadow-inner">
                            <option value="">-- اضغط للاختيار --</option>
                            ${tests.map(t => `<option value="${t.id}" ${fitnessFilter.test_id == t.id ? 'selected' : ''}>${esc(t.name)}</option>`).join('')}
                        </select>
                        <div class="absolute inset-y-0 left-6 flex items-center pointer-events-none text-gray-400 group-hover:text-emerald-500 transition">
                            <span class="text-xl">📐</span>
                        </div>
                    </div>
                </div>
                ${canEdit() ? `
                <div class="pt-2">
                    <button onclick="saveFitnessResults()" class="w-full bg-emerald-600 text-white px-8 py-4 md:py-5 rounded-2xl md:rounded-[1.8rem] font-black hover:bg-emerald-700 transition shadow-xl shadow-emerald-100 flex items-center justify-center gap-4 active:scale-95 group text-sm md:text-base">
                        <span class="text-2xl group-hover:rotate-12 transition transform">💾</span> 
                        <span>حفظ بيانات الرصد</span>
                    </button>
                </div>` : ''}
            </div>
        </div>
        
        <div id="fitnessEntryList">
            <div class="text-center py-32 bg-white rounded-[3.5rem] border-2 border-dashed border-gray-100 overflow-hidden relative">
                <div class="absolute -right-20 -top-20 w-64 h-64 bg-gray-50 rounded-full opacity-50 blur-3xl"></div>
                <div class="relative z-10">
                    <div class="w-32 h-32 bg-gray-50 rounded-3xl flex items-center justify-center text-6xl mx-auto mb-8 grayscale opacity-20 transform -rotate-12 group-hover:rotate-0 transition">📝</div>
                    <p class="text-gray-400 font-black text-2xl">بانتظار تحديد نطاق الرصد</p>
                    <p class="text-gray-300 font-bold mt-2">اختر الفصل ونوع الاختبار للبدء في إدخال النتائج الرسمية</p>
                </div>
            </div>
        </div>
    </div>`;

    if (fitnessFilter.class_id && fitnessFilter.test_id) loadFitnessEntry();
}

async function loadFitnessEntry() {
    if (!fitnessFilter.class_id || !fitnessFilter.test_id) return;

    const r = await API.get('fitness_results', { class_id: fitnessFilter.class_id, test_id: fitnessFilter.test_id });
    const cr = await API.get('fitness_criteria', { test_id: fitnessFilter.test_id });

    if (!r || !r.success) return;

    const students = r.data;
    const test = (window._fitnessTests || []).find(t => t.id == fitnessFilter.test_id);
    const criteria = cr?.data || [];

    // Helper to calculate score
    window.autoCalcScore = (input) => {
        const val = parseFloat(input.value);
        if (isNaN(val)) return;
        const row = input.closest('.fit-row');
        const scoreInput = row.querySelector('.fit-score');

        const match = criteria.find(c => val >= parseFloat(c.min_value) && val <= parseFloat(c.max_value));
        if (match) {
            scoreInput.value = match.score;
            scoreInput.classList.add('bg-green-50', 'border-green-500');
            setTimeout(() => scoreInput.classList.remove('bg-green-50', 'border-green-500'), 1000);
        }
    };

    // Fix #7: Guard against undefined test (find() may return undefined if test_id doesn't match)
    if (!test) {
        document.getElementById('fitnessEntryList').innerHTML =
            `<div class="p-10 text-center text-red-500 font-bold">لم يتم العثور على بيانات الاختبار المحدد</div>`;
        return;
    }

    document.getElementById('fitnessEntryList').innerHTML = `
    <div class="fade-in">
        <!-- Dashboard Header Info -->
        <div class="bg-green-900 rounded-[2.5rem] p-8 md:p-10 mb-8 text-white relative overflow-hidden shadow-2xl">
            <div class="absolute -right-20 -bottom-20 w-64 h-64 bg-white/5 rounded-full blur-3xl"></div>
            <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-8">
                <div>
                    <h5 class="text-2xl font-black mb-2">${esc(test.name)}</h5>
                    <p class="text-green-200 font-bold opacity-80">رصد مخرجات الطلاب بـ (${esc(test.unit)}) - الدرجة من ${test.max_score}</p>
                    ${criteria.length > 0 ? '<span class="inline-block mt-3 px-3 py-1 bg-white/10 rounded-full text-[10px] font-black uppercase tracking-widest text-emerald-400">⚡ الرصد الآلي مفعل</span>' : ''}
                </div>
                <div class="flex items-center gap-6 text-center">
                    <div class="bg-white/10 px-6 py-3 rounded-2xl backdrop-blur-md border border-white/10">
                        <p class="text-3xl font-black">${students.length}</p>
                        <p class="text-[10px] uppercase font-black opacity-60">إجمالي طلاب الفصل</p>
                    </div>
                    <div class="bg-emerald-500/20 px-6 py-3 rounded-2xl backdrop-blur-md border border-emerald-500/20">
                        <p class="text-3xl font-black text-emerald-400">${students.filter(s => s.value !== null).length}</p>
                        <p class="text-[10px] uppercase font-black text-emerald-300">تم رصدهم</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Desktop View -->
        <div class="hidden lg:block bg-white rounded-[2.5rem] shadow-xl shadow-gray-100/50 border border-gray-100 overflow-hidden mb-12">
            <table class="w-full text-right border-collapse">
                <thead>
                    <tr class="bg-gray-50/50 border-b border-gray-100">
                        <th class="px-8 py-5 text-xs font-black text-gray-400 uppercase tracking-widest w-16">#</th>
                        <th class="px-8 py-5 text-xs font-black text-gray-400 uppercase tracking-widest">اسم الطالب</th>
                        <th class="px-8 py-5 text-xs font-black text-gray-400 uppercase tracking-widest text-center">الحالة الصحية</th>
                        <th class="px-8 py-5 text-xs font-black text-gray-400 uppercase tracking-widest text-center">القيمة المكتسبة (${test ? esc(test.unit) : ''})</th>
                        <th class="px-8 py-5 text-xs font-black text-gray-400 uppercase tracking-widest text-center">الدرجة النهائية</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    ${students.map((s, i) => `
                    <tr class="hover:bg-gray-50/50 transition group fit-row ${s.health_notes ? 'bg-red-50/30' : ''}" data-student-id="${s.student_id}">
                        <td class="px-8 py-5 text-sm font-black text-gray-400">${i + 1}</td>
                        <td class="px-8 py-5">
                            <div class="font-black text-gray-800">${esc(s.name)}</div>
                            <div class="text-[10px] text-gray-400 font-bold mt-0.5 uppercase">ID: ${s.student_number || 'N/A'}</div>
                        </td>
                        <td class="px-8 py-5 text-center">
                            ${s.health_notes ? `<span class="inline-flex px-3 py-1 rounded-full bg-red-100 text-red-700 text-[10px] font-black cursor-help" title="${esc(s.health_notes)}">⚠️ ملاحظة طبية</span>` : '<span class="text-emerald-500 text-lg">✅</span>'}
                        </td>
                        <td class="px-8 py-5 text-center">
                            <input type="number" step="0.1" oninput="autoCalcScore(this)" class="fit-value w-32 px-4 py-3 bg-gray-50 border-2 border-gray-50 rounded-xl text-center focus:bg-white focus:border-green-600 focus:outline-none transition-all font-black text-lg" value="${s.value || ''}" ${!canEdit() ? 'disabled' : ''} placeholder="0.0">
                        </td>
                        <td class="px-8 py-5 text-center font-black">
                            <input type="number" min="0" max="${test ? test.max_score : 10}" class="fit-score w-24 px-4 py-3 bg-gray-50 border-2 border-gray-50 rounded-xl text-center focus:bg-white focus:border-emerald-500 focus:outline-none transition-all font-black text-lg" value="${s.score || ''}" ${!canEdit() ? 'disabled' : ''} placeholder="0">
                        </td>
                    </tr>
                    `).join('')}
                </tbody>
            </table>
        </div>

        <!-- Mobile/Tablet View -->
        <div class="lg:hidden space-y-4 mb-20">
            ${students.map((s, i) => `
            <div class="bg-white rounded-[2rem] p-5 md:p-6 border border-gray-100 shadow-xl shadow-gray-100/20 relative overflow-hidden fit-row ${s.health_notes ? 'ring-4 ring-red-50' : ''} transition-all duration-300 active:scale-95" data-student-id="${s.student_id}">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-gray-50 text-gray-400 font-black flex items-center justify-center text-sm shadow-inner">${i + 1}</div>
                        <div>
                            <h5 class="font-black text-gray-800 text-base leading-tight">${esc(s.name)}</h5>
                            <p class="text-[9px] text-gray-400 font-black mt-0.5 tracking-tight">رقم القيد: ${s.student_number || 'N/A'}</p>
                        </div>
                    </div>
                    ${s.health_notes ? `
                        <div class="w-10 h-10 rounded-xl bg-red-50 text-red-500 flex items-center justify-center animate-pulse" title="${esc(s.health_notes)}">⚠️</div>
                    ` : '<div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-500 flex items-center justify-center text-sm">✅</div>'}
                </div>
                
                <div class="grid grid-cols-2 gap-3 md:gap-4 bg-gray-50 p-1 rounded-3xl border border-gray-100 shadow-inner">
                    <div class="space-y-1.5 p-3">
                        <label class="block text-[8px] font-black text-gray-400 uppercase tracking-widest mr-1">القيمة (${test ? esc(test.unit) : ''})</label>
                        <input type="number" step="0.1" oninput="autoCalcScore(this)" class="fit-value w-full px-2 py-3 bg-white border-2 border-transparent rounded-2xl text-center focus:border-emerald-500 focus:outline-none transition-all font-black text-lg shadow-sm" value="${s.value || ''}" ${!canEdit() ? 'disabled' : ''} placeholder="0.0">
                    </div>
                    <div class="space-y-1.5 p-3">
                        <label class="block text-[8px] font-black text-gray-400 uppercase tracking-widest mr-1">الدرجة</label>
                        <input type="number" min="0" max="${test ? test.max_score : 10}" class="fit-score w-full px-2 py-3 bg-white border-2 border-transparent rounded-2xl text-center focus:border-emerald-500 focus:outline-none transition-all font-black text-lg shadow-sm" value="${s.score || ''}" ${!canEdit() ? 'disabled' : ''} placeholder="0">
                    </div>
                </div>
            </div>
            `).join('')}
        </div>
    </div>`;
}

async function saveFitnessResults() {
    const rows = document.querySelectorAll('.fit-row');
    const records = [];

    rows.forEach(row => {
        const sid = row.dataset.studentId;
        const v = row.querySelector('.fit-value').value;
        const sc = row.querySelector('.fit-score').value;
        if (v !== '' && sc !== '') {
            records.push({ student_id: sid, value: parseFloat(v), score: parseInt(sc) });
        }
    });

    const r = await API.post('fitness_results_save', { test_id: fitnessFilter.test_id, records });
    if (r && r.success) {
        showToast(r.message);
    } else if (r) {
        showToast(r.error, 'error');
    }
}

async function renderFitnessView() {
    const cl = await API.get('classes');
    const classes = cl?.data || [];

    document.getElementById('fitnessContent').innerHTML = `
    <div class="fade-in">
        <div class="bg-white rounded-[2.5rem] shadow-xl shadow-gray-100/50 border border-gray-100 p-6 md:p-10 mb-10 overflow-hidden relative">
            <div class="absolute top-0 left-0 w-2 h-full bg-emerald-600"></div>
            <div class="flex flex-col md:flex-row md:items-end gap-6">
                <div class="flex-1 space-y-3">
                    <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest mr-2">استعراض نتائج الفصل</label>
                    <div class="relative group">
                        <select onchange="loadFitnessView(this.value)" class="w-full px-8 py-5 bg-gray-50 border-2 border-transparent rounded-[1.8rem] focus:bg-white focus:border-emerald-600 focus:outline-none transition-all font-black text-gray-700 appearance-none cursor-pointer shadow-inner">
                            <option value="">-- اضغط لاختيار الفصل --</option>
                            ${classes.map(c => `<option value="${c.id}">${esc(c.full_name || c.name)}</option>`).join('')}
                        </select>
                        <div class="absolute inset-y-0 left-6 flex items-center pointer-events-none text-gray-400 group-hover:text-emerald-600 transition">
                            <span class="text-xl">🏫</span>
                        </div>
                    </div>
                </div>
                <div class="hidden md:block">
                    <button onclick="window.print()" class="bg-white text-gray-600 border-2 border-emerald-50 px-8 py-5 rounded-[1.8rem] font-black hover:bg-emerald-50 hover:text-emerald-700 transition flex items-center gap-2 active:scale-95 shadow-sm">
                        <span>🖨️</span> طباعة التقرير
                    </button>
                </div>
            </div>
        </div>
        <div id="fitnessViewResults">
            <div class="text-center py-24 bg-white rounded-[3rem] border-2 border-dashed border-gray-100/50">
                <div class="text-7xl mb-6 grayscale opacity-20">📊</div>
                <p class="text-gray-400 font-black text-xl">اختر الفصل لعرض المصفوفة التحليلية</p>
                <p class="text-gray-300 text-sm mt-1">سيتم عرض نتائج جميع الاختبارات في جدول موحد</p>
            </div>
        </div>
    </div>`;
}

async function loadFitnessView(classId) {
    if (!classId) return;

    const fvr = document.getElementById('fitnessViewResults');
    fvr.innerHTML = showLoading();

    const r = await API.get('fitness_view', { class_id: classId });
    if (!r || !r.success) {
        fvr.innerHTML = `<div class="p-10 text-center text-red-500 font-bold">${r?.error || 'خطأ في التحميل'}</div>`;
        return;
    }

    const { tests, students, results } = r.data;

    if (students.length === 0) {
        fvr.innerHTML = `<div class="p-10 text-center text-gray-400 font-bold italic">لا يوجد طلاب في هذا الفصل</div>`;
        return;
    }

    // --- Desktop Matrix Table ---
    let desktopHtml = `
    <div class="hidden lg:block bg-white rounded-[3rem] shadow-2xl shadow-gray-100/50 border border-gray-100 overflow-hidden mb-12">
        <table class="w-full text-right border-collapse">
            <thead>
                <tr class="bg-gray-900 text-white">
                    <th class="px-8 py-6 text-xs font-black uppercase tracking-widest text-emerald-400">اسم الطالب البطل</th>
                    ${tests.map(t => `<th class="px-4 py-6 text-center text-xs font-black uppercase tracking-widest">${esc(t.name)}</th>`).join('')}
                    <th class="px-6 py-6 text-center text-xs font-black uppercase tracking-widest bg-emerald-600 border-r border-white/10">المجموع</th>
                    <th class="px-6 py-6 text-center text-xs font-black uppercase tracking-widest bg-emerald-700">المتوسط</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">`;

    students.forEach(s => {
        let total = 0, count = 0;
        desktopHtml += `
        <tr class="hover:bg-emerald-50/30 transition group">
            <td class="px-8 py-5 font-black text-gray-800 border-l border-gray-50">${esc(s.name)}</td>`;

        tests.forEach(t => {
            const res = results[s.id] && results[s.id][t.id];
            if (res) {
                total += parseFloat(res.score);
                count++;
            }
            desktopHtml += `
            <td class="px-4 py-5 text-center">
                ${res ? `
                    <div class="flex flex-col">
                        <span class="font-black text-lg text-gray-800">${res.score}</span>
                        <span class="text-[9px] text-gray-400 font-bold uppercase">MAX: ${t.max_score}</span>
                    </div>
                ` : '<span class="text-gray-200 font-black">—</span>'}
            </td>`;
        });

        const avg = count ? (total / count).toFixed(1) : '-';
        desktopHtml += `
            <td class="px-6 py-5 text-center font-black text-xl text-emerald-600 bg-emerald-50/30 border-r border-emerald-100/50">${total || '-'}</td>
            <td class="px-6 py-5 text-center font-black text-xl text-white bg-emerald-600">${avg}</td>
        </tr>`;
    });

    desktopHtml += '</tbody></table></div>';

    // --- Mobile Card View ---
    let mobileHtml = `
    <div class="lg:hidden space-y-6 mb-20 px-1">
        ${students.map(s => {
        let total = 0, count = 0;
        const studentTestsHtml = tests.map(t => {
            const res = results[s.id] && results[s.id][t.id];
            if (res) {
                total += parseFloat(res.score);
                count++;
            }
            return `
                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-100 shadow-inner">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-white flex items-center justify-center text-xl shadow-sm">
                            ${t.name.includes('جري') ? '🏃' : t.name.includes('وثب') ? '👟' : t.name.includes('مرونة') ? '🧘' : '📐'}
                        </div>
                        <div>
                            <p class="text-[9px] text-gray-400 font-black uppercase tracking-tighter leading-none mb-1">${esc(t.name)}</p>
                            <p class="text-[10px] font-bold text-emerald-600 uppercase">القصوى: ${t.max_score}</p>
                        </div>
                    </div>
                    <div class="text-left">
                        ${res ? `
                            <span class="text-xl font-black text-gray-800">${res.score}</span>
                            <span class="text-[10px] text-gray-400 font-black mr-1">نقطة</span>
                        ` : '<span class="text-gray-200 font-black text-xl">—</span>'}
                    </div>
                </div>`;
        }).join('');

        const avg = count ? (total / count).toFixed(1) : '-';

        return `
            <div class="bg-white rounded-[2.5rem] shadow-xl shadow-gray-100/30 border border-gray-100 overflow-hidden relative group">
                <div class="bg-gradient-to-br from-emerald-950 via-green-900 to-emerald-900 p-6 text-white relative">
                    <!-- Fix #8: Removed external CDN texture URL - use CSS instead -->
                    <div class="absolute inset-0 opacity-5" style="background-image:repeating-linear-gradient(45deg,#fff 0,#fff 1px,transparent 0,transparent 50%);background-size:8px 8px;"></div>
                    <div class="relative z-10 flex items-center justify-between">
                        <div>
                            <h6 class="font-black text-xl tracking-tight">${esc(s.name)}</h6>
                            <p class="text-[10px] text-emerald-400 font-black uppercase tracking-widest mt-1">سجل التقييم البدني والشامل</p>
                        </div>
                        <div class="text-left">
                            <div class="text-4xl font-black text-emerald-400 leading-none">${avg}</div>
                            <p class="text-[9px] text-emerald-200/50 font-black uppercase tracking-widest mt-1">المعدل العام</p>
                        </div>
                    </div>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 gap-3 mb-6">
                        ${studentTestsHtml}
                    </div>
                    <div class="p-5 bg-emerald-50 rounded-[2rem] border border-emerald-100 flex items-center justify-between shadow-inner">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 bg-emerald-600 text-white rounded-2xl flex items-center justify-center text-2xl shadow-lg shadow-emerald-200">🏆</div>
                            <span class="text-xs font-black text-emerald-700 uppercase tracking-widest">إجمالي النقاط المكتسبة</span>
                        </div>
                        <span class="text-2xl font-black text-emerald-800">${total}</span>
                    </div>
                </div>
            </div>`;
    }).join('')}
    </div>`;

    fvr.innerHTML = desktopHtml + mobileHtml;
}
