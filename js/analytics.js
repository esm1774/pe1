/**
 * PE Smart School System - Advanced Analytics Dashboard
 * ======================================================
 * لوحة التحليلات المتقدمة: رسومات بيانية متطورة للمشرفين والمدراء
 */

async function renderAnalytics() {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = showLoading();

    // Fetch data from backend API
    const r = await API.get('analytics_dashboard');
    if (!r || !r.success) {
        mc.innerHTML = `
        <div class="text-center py-20 bg-white rounded-[3rem] border-2 border-dashed border-gray-100 max-w-2xl mx-auto mt-10 shadow-sm">
            <div class="text-6xl mb-6 opacity-80">🔒</div>
            <h3 class="text-2xl font-black text-gray-800 mb-2">هذه الميزة غير مشمولة في باقتك الحالية</h3>
            <p class="text-gray-500 font-bold mb-8 px-6">للوصول إلى التحليلات المتقدمة والتقارير الشاملة، يرجى ترقية اشتراك المدرسة إلى باقة أعلى.</p>
            <button onclick="navigateTo('subscription')" class="bg-emerald-600 text-white px-8 py-4 rounded-[1.5rem] font-black hover:bg-emerald-700 shadow-xl shadow-emerald-100 transition active:scale-95 cursor-pointer">
                ⭐ ترقية الاشتراك الآن
            </button>
        </div>`;
        return;
    }

    const { timeline, classComparison, heatmap, top10, insights } = r.data;

    mc.innerHTML = `
        <div class="fade-in">
        <div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 flex items-center gap-3">
                    <span class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center text-xl text-white shadow-lg">📊</span>
                    لوحة التحليلات المتقدمة
                </h2>
                <p class="text-gray-500 mt-1 text-sm mr-12 font-bold">رسومات بيانية وتحليل للأداء الشامل (الموزون) عبر الزمن والمقارنات الدقيقة.</p>
            </div>
            <div>
                <!-- Could add export buttons or timeframe filters here later -->
            </div>
        </div>

        <!--KPI Cards-- >
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-gradient-to-tr from-blue-600 to-indigo-700 rounded-2xl p-5 text-white shadow-xl hover:-translate-y-1 transition duration-300">
                <p class="text-xs font-bold opacity-80 mb-1">متوسط التدريب والحضور <span class="text-[10px]">(آخر 30 يوم)</span></p>
                <div class="text-4xl font-black mt-2">${insights.avgAttendance30d}%</div>
            </div>
            <div class="bg-gradient-to-tr from-emerald-500 to-green-600 rounded-2xl p-5 text-white shadow-xl hover:-translate-y-1 transition duration-300">
                <p class="text-xs font-bold opacity-80 mb-1">أفضل فصل في التقييم الشامل</p>
                <div class="text-xl font-black mt-2 truncate">${esc(insights.bestClass)}</div>
            </div>
            <div class="bg-gradient-to-tr from-orange-500 to-red-600 rounded-2xl p-5 text-white shadow-xl hover:-translate-y-1 transition duration-300">
                <p class="text-xs font-bold opacity-80 mb-1">أفضل طالب (التقييم العام)</p>
                <div class="text-xl font-black mt-2 truncate">${top10.length > 0 ? esc(top10[0].name) : '-'}</div>
            </div>
            <div class="bg-gradient-to-tr from-purple-500 to-pink-600 rounded-2xl p-5 text-white shadow-xl hover:-translate-y-1 transition duration-300">
                <p class="text-xs font-bold opacity-80 mb-1">الأيام المسجلة (الخريطة الحرارية)</p>
                <div class="text-4xl font-black mt-2">${heatmap.labels.length} يوم</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Timeline Line Chart -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 relative">
                <div class="flex items-center justify-between mb-4 border-b border-gray-50 pb-3">
                    <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
                        <span class="text-indigo-500">📈</span> رسم بياني تطور أداء الطالب عبر الزمن
                    </h3>
                </div>
                <div class="relative h-64 w-full">
                    <canvas id="timelineChart"></canvas>
                </div>
            </div>

            <!-- Class Comparison Bar Chart -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 relative">
                <div class="flex items-center justify-between mb-4 border-b border-gray-50 pb-3">
                    <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
                        <span class="text-emerald-500">📊</span> مقارنة الفصول الدراسية
                    </h3>
                </div>
                <div class="relative h-64 w-full">
                    <canvas id="classCompChart"></canvas>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Heatmap (Simulated via Bar with intensity) -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 lg:col-span-2 relative">
                <div class="flex items-center justify-between mb-4 border-b border-gray-50 pb-3">
                    <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
                        <span class="text-orange-500">🔥</span> خريطة حرارية للحضور والتفاعل
                    </h3>
                    <div class="flex gap-1 text-[10px] font-bold text-gray-400">
                        <div class="w-3 h-3 rounded-sm bg-orange-100"></div> بارد
                        <div class="w-3 h-3 rounded-sm bg-orange-500 mr-2"></div> ساخن
                    </div>
                </div>
                <div class="relative h-64 w-full">
                    <canvas id="heatmapChart"></canvas>
                </div>
            </div>

            <!-- Top 10 Students List -->
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 relative">
                <div class="flex items-center justify-between mb-4 border-b border-gray-50 pb-3">
                    <h3 class="text-base font-bold text-gray-800 flex items-center gap-2">
                        <span class="text-yellow-500">🌟</span> أفضل ١٠ طلاب في كل معيار
                    </h3>
                </div>
                <div class="space-y-3 overflow-y-auto max-h-[16.5rem] pr-1 custom-scrollbar">
                    ${top10.map((t, idx) => `
                        <div class="flex items-center gap-3 p-2 rounded-xl hover:bg-gray-50 transition">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center font-black text-xs ${idx === 0 ? 'bg-gradient-to-br from-yellow-300 to-yellow-500 text-white shadow-md' : idx === 1 ? 'bg-gradient-to-br from-gray-300 to-gray-400 text-white shadow-md' : idx === 2 ? 'bg-gradient-to-br from-orange-300 to-orange-400 text-white shadow-md' : 'bg-gray-100 text-gray-500'}">
                                ${idx + 1}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-gray-800 truncate">${esc(t.name)}</p>
                                <p class="text-[10px] text-gray-400 font-bold truncate">${esc(t.class_name)}</p>
                            </div>
                            <div class="font-black text-indigo-700 bg-indigo-50 px-2 py-1 rounded-lg text-xs border border-indigo-100">
                                ${t.avg_score} <span class="text-[8px] font-bold opacity-60">نقطة</span>
                            </div>
                        </div>
                    `).join('')}
                    ${top10.length === 0 ? '<p class="text-gray-400 text-center text-sm font-bold py-8">لا توجد بيانات حالياً 🚫</p>' : ''}
                </div>
            </div>
        </div>
    </div > `;

    // Render Charts after DOM injection
    setTimeout(() => {
        initCharts(timeline, classComparison, heatmap);
    }, 150);
}

function initCharts(timeline, classComparison, heatmap) {
    // Shared Chart.js config for modern styling
    Chart.defaults.font.family = "'Outfit', 'Tajawal', sans-serif";
    Chart.defaults.color = '#9ca3af';

    // 1. Timeline Chart (Line)
    const ctxTimeline = document.getElementById('timelineChart').getContext('2d');

    // Create gradient for the line fill
    const gradientLine = ctxTimeline.createLinearGradient(0, 0, 0, 300);
    gradientLine.addColorStop(0, 'rgba(99, 102, 241, 0.4)'); // text-indigo-500 with opacity
    gradientLine.addColorStop(1, 'rgba(99, 102, 241, 0.01)');

    new Chart(ctxTimeline, {
        type: 'line',
        data: {
            labels: timeline.labels.map(l => l.replace('-', '/')),
            datasets: [{
                label: 'معدل التقييم الشامل الموزون %',
                data: timeline.data,
                borderColor: '#6366f1',
                backgroundColor: gradientLine,
                borderWidth: 3,
                tension: 0.4, // smooth curvy lines
                fill: true,
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#6366f1',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { backgroundColor: 'rgba(17, 24, 39, 0.8)', padding: 10, cornerRadius: 8 }
            },
            scales: {
                y: { beginAtZero: true, max: 100, grid: { borderDash: [4, 4], color: '#f3f4f6' } },
                x: { grid: { display: false }, ticks: { font: { weight: 'bold' } } }
            }
        }
    });

    // 2. Class Comparison Chart (Bar)
    const ctxClass = document.getElementById('classCompChart').getContext('2d');
    new Chart(ctxClass, {
        type: 'bar',
        data: {
            labels: classComparison.labels,
            datasets: [{
                label: 'متوسط التقييم الموزون للفصل',
                data: classComparison.data,
                // Make the leading class green, the rest a slightly faded green
                backgroundColor: classComparison.data.map((_, i) => i === 0 ? '#10b981' : '#6ee7b7'),
                borderRadius: 6,
                borderSkipped: false,
                barPercentage: 0.6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { backgroundColor: 'rgba(17, 24, 39, 0.8)', padding: 10, cornerRadius: 8 }
            },
            scales: {
                y: { beginAtZero: true, grid: { borderDash: [4, 4], color: '#f3f4f6' } },
                x: { grid: { display: false }, ticks: { maxRotation: 45, minRotation: 45, font: { size: 10, weight: 'bold' } } }
            }
        }
    });

    // 3. Heatmap Chart (Represented as Bar with intense colors for Hot vs Cold days)
    const ctxHeatmap = document.getElementById('heatmapChart').getContext('2d');

    // Map percentages to "heat" colors dynamically
    const bgColorsHeat = heatmap.data.map(val => {
        if (val >= 95) return '#ea580c'; // orange-600
        if (val >= 85) return '#f97316'; // orange-500
        if (val >= 70) return '#fba94c'; // orange-400
        if (val >= 50) return '#fcd34d'; // amber-300
        return '#ffedd5'; // orange-50 (Very cold/low)
    });

    new Chart(ctxHeatmap, {
        type: 'bar',
        data: {
            labels: heatmap.labels.map(l => {
                // Shorten '2026-03-01' to '03/01'
                const p = l.split('-');
                return p.length === 3 ? `${p[1]}/${p[2]}` : l;
            }),
            datasets: [{
                label: 'كثافة الحضور والتفاعل اليومي %',
                data: heatmap.data,
                backgroundColor: bgColorsHeat,
                borderRadius: 4,
                borderSkipped: false,
                barPercentage: 0.9,
                categoryPercentage: 1.0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: { label: (ctx) => `الكثافة: ${ctx.raw}%` },
                    backgroundColor: 'rgba(17, 24, 39, 0.8)',
                    padding: 10,
                    cornerRadius: 8
                }
            },
            scales: {
                y: { display: false, max: 100 }, // hide Y axis for cleaner heatmap look
                x: {
                    grid: { display: false },
                    ticks: { maxRotation: 90, minRotation: 90, font: { size: 9, weight: 'bold' } },
                    border: { display: false }
                }
            }
        }
    });
}

console.log('✅ analytics.js loaded');
