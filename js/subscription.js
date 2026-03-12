/**
 * PE Smart School - Subscription & Billing Module
 */

async function renderSubscriptionPage() {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = showLoading();

    const res = await API.get('subscription');
    if (!res || !res.success) {
        mc.innerHTML = `
            <div class="text-center py-20">
                <div class="text-6xl mb-4">💳</div>
                <h3 class="text-2xl font-black text-gray-800 mb-2">تعذر جلب بيانات الاشتراك</h3>
                <p class="text-gray-500 mb-6">يرجى المحاولة مرة أخرى أو التواصل مع الدعم الفني.</p>
                <button onclick="renderSubscriptionPage()" class="bg-emerald-600 text-white px-8 py-3 rounded-2xl font-black">إعادة المحاولة</button>
            </div>
        `;
        return;
    }

    const s = res.data;
    const isTrial = s.status === 'trial';

    // Status translation & colors
    const statuses = {
        'active': { label: 'مفعّل', color: 'bg-emerald-100 text-emerald-700' },
        'trial': { label: 'فترة تجريبية', color: 'bg-amber-100 text-amber-700' },
        'suspended': { label: 'معلّق', color: 'bg-red-100 text-red-700' },
        'cancelled': { label: 'ملغي', color: 'bg-gray-100 text-gray-700' }
    };
    const currentStatus = statuses[s.status] || { label: s.status, color: 'bg-gray-100 text-gray-700' };

    // Usage Calculations
    const usage = s.usage || { students: 0, teachers: 0, classes: 0 };
    const studentPct = Math.min(100, Math.round((usage.students / s.max_students) * 100)) || 0;
    const teacherPct = Math.min(100, Math.round((usage.teachers / s.max_teachers) * 100)) || 0;
    const classPct = Math.min(100, Math.round((usage.classes / s.max_classes) * 100)) || 0;

    mc.innerHTML = `
    <div class="fade-in max-w-5xl mx-auto px-4 md:px-0">
        <!-- Header -->
        <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <span class="px-3 py-1 ${currentStatus.color} rounded-full text-[10px] font-black uppercase tracking-widest">${currentStatus.label}</span>
                    <span class="text-gray-400 font-bold text-xs">رقم المدرسة: #${currentUser.school_id}</span>
                </div>
                <h2 class="text-4xl font-black text-gray-800">تفاصيل الاشتراك</h2>
                <p class="text-gray-500 mt-2 font-bold">إدارة خطتك، متابعة الاستهلاك، وتاريخ الفوترة.</p>
            </div>
            
            <div class="bg-white border border-gray-100 rounded-[2rem] p-6 shadow-xl shadow-gray-100/50 flex items-center gap-6 min-w-[300px]">
                <div class="w-16 h-16 bg-emerald-50 rounded-2xl flex items-center justify-center text-3xl">🗓️</div>
                <div>
                    <p class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-1">الأيام المتبقية</p>
                    <p class="text-3xl font-black text-gray-800">${s.days_left || 0} <span class="text-sm text-gray-400">يوم</span></p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Plan Details -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Main Plan Card -->
                <div class="bg-gradient-to-br from-gray-900 to-slate-800 rounded-[3rem] p-8 md:p-12 text-white relative overflow-hidden shadow-2xl">
                    <div class="absolute -right-20 -top-20 w-80 h-80 bg-emerald-500/10 rounded-full blur-3xl"></div>
                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-8">
                            <div>
                                <h3 class="text-3xl font-black mb-1">${esc(s.plan_name)}</h3>
                                <p class="text-emerald-400 font-bold opacity-80">الخطة المفعلة لمدرستك</p>
                            </div>
                            <div class="text-right">
                                <span class="px-4 py-2 bg-white/10 rounded-xl text-xs font-black uppercase tracking-widest border border-white/10">ID: ${s.plan_slug}</span>
                            </div>
                        </div>

                        <!-- All Features List -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-10">
                            ${Object.entries(ALL_FEATURES_MAP).map(([key, info]) => {
        const has = s.features && s.features[key];
        return `
                                <div class="flex items-center gap-3 bg-white/5 rounded-2xl p-4 border border-white/5 ${!has ? 'opacity-40 grayscale' : ''}">
                                    <span class="text-lg">${has ? '✅' : '🚫'}</span>
                                    <div class="leading-tight">
                                        <p class="text-sm font-black">${info.icon} ${info.label}</p>
                                    </div>
                                </div>
                                `;
    }).join('')}
                        </div>

                        <div class="flex flex-wrap gap-4">
                            <button onclick="window.open('https://wa.me/966507949591', '_blank')" class="bg-emerald-500 hover:bg-emerald-400 text-white px-10 py-4 rounded-2xl font-black transition shadow-xl shadow-emerald-500/20 active:scale-95 cursor-pointer leading-none">ترقية الخطة</button>
                            ${s.notes ? `
                                <div class="w-full mt-4 p-4 bg-white/5 rounded-2xl border border-white/10 text-xs italic opacity-70">
                                    📝 ملاحظات الإدارة: ${esc(s.notes)}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>

                <!-- Usage Stats -->
                <div class="bg-white rounded-[3rem] p-10 shadow-xl shadow-gray-100/50 border border-gray-50">
                    <h3 class="text-2xl font-black text-gray-800 mb-8 flex items-center gap-3">📊 استهلاك الموارد المخصصة</h3>
                    
                    <div class="space-y-8">
                        <!-- Students -->
                        <div>
                            <div class="flex justify-between items-end mb-3">
                                <div>
                                    <span class="text-sm font-black text-gray-800">إجمالي الطلاب</span>
                                    <p class="text-xs text-gray-400 font-bold">المسجلين حالياً في كافة الصفوف</p>
                                </div>
                                <span class="text-sm font-black text-emerald-600">${usage.students} / ${s.max_students || '∞'}</span>
                            </div>
                            <div class="h-3 bg-gray-50 rounded-full overflow-hidden border border-gray-100 p-0.5">
                                <div class="h-full bg-emerald-500 rounded-full transition-all duration-1000" style="width: ${studentPct}%"></div>
                            </div>
                        </div>

                        <!-- Teachers -->
                        <div>
                            <div class="flex justify-between items-end mb-3">
                                <div>
                                    <span class="text-sm font-black text-gray-800">حسابات المعلمين</span>
                                    <p class="text-xs text-gray-400 font-bold">المعلمين المفعلين على المنصة</p>
                                </div>
                                <span class="text-sm font-black text-blue-600">${usage.teachers} / ${s.max_teachers || '∞'}</span>
                            </div>
                            <div class="h-3 bg-gray-50 rounded-full overflow-hidden border border-gray-100 p-0.5">
                                <div class="h-full bg-blue-500 rounded-full transition-all duration-1000" style="width: ${teacherPct}%"></div>
                            </div>
                        </div>

                        <!-- Classes -->
                        <div>
                            <div class="flex justify-between items-end mb-3">
                                <div>
                                    <span class="text-sm font-black text-gray-800">عدد الفصول</span>
                                    <p class="text-xs text-gray-400 font-bold">الفصول المدرسية الموزعة</p>
                                </div>
                                <span class="text-sm font-black text-purple-600">${usage.classes} / ${s.max_classes || '∞'}</span>
                            </div>
                            <div class="h-3 bg-gray-50 rounded-full overflow-hidden border border-gray-100 p-0.5">
                                <div class="h-full bg-purple-500 rounded-full transition-all duration-1000" style="width: ${classPct}%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar Info -->
            <div class="space-y-6">
                <!-- Info Card -->
                <div class="bg-emerald-50 rounded-[2.5rem] p-8 border border-emerald-100">
                    <h4 class="text-emerald-800 font-black mb-4">💡 هل تعلم؟</h4>
                    <p class="text-emerald-700/80 text-sm leading-relaxed font-bold">
                        يمكن لإدارة المنصة تخصيص حدود استهلاك وميزات خاصة لمدرستك تتجاوز ما هو محدد في الخطة العامة.
                    </p>
                    <hr class="my-6 border-emerald-200/50">
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <span class="text-lg">🗓️</span>
                            <span class="text-xs font-black text-emerald-800">تاريخ بداية الاشتراك: ${s.starts_at || '-'}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-lg">🛡️</span>
                            <span class="text-xs font-black text-emerald-800">دعم فني متميز ومباشر</span>
                        </div>
                    </div>
                </div>

                <!-- Next Payment / Expiry -->
                <div class="bg-white rounded-[2.5rem] p-8 shadow-xl shadow-gray-100/50 border border-gray-50">
                    <h4 class="text-sm font-black text-gray-400 uppercase tracking-widest mb-6">${isTrial ? 'نهاية التجربة' : 'تاريخ التجديد'}</h4>
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-12 h-12 bg-gray-50 rounded-2xl flex items-center justify-center text-xl">💳</div>
                        <div>
                            <p class="text-sm font-black text-gray-800">${s.ends_at || '-'}</p>
                            <p class="text-[10px] text-gray-400 font-bold uppercase">يوم الاستحقاق</p>
                        </div>
                    </div>
                    <button onclick="window.open('https://wa.me/966507949591', '_blank')" class="w-full bg-gray-900 text-white py-4 rounded-2xl font-black text-sm hover:bg-black transition cursor-pointer">تواصل مع المبيعات</button>
                </div>
            </div>
        </div>
    </div>`;
}

const ALL_FEATURES_MAP = {
    tournaments: { icon: '🏆', label: 'البطولات الرياضية' },
    sports_teams: { icon: '🛡️', label: 'إدارة الفرق المدرسية' },
    badges: { icon: '🏅', label: 'الأوسمة والتحفيز' },
    certificates: { icon: '📜', label: 'إصدار الشهادات التقائية' },
    notifications: { icon: '🔔', label: 'إشعارات أولياء الأمور' },
    reports: { icon: '📊', label: 'التقارير المتقدمة' },
    analytics: { icon: '📈', label: 'لوحة التحليلات' },
    fitness_tests: { icon: '💪', label: 'اختبارات اللياقة' },
    timetable: { icon: '🗓️', label: 'جدول الحصص' },
    weighted_grading: { icon: '⚖️', label: 'محرك التقييم الموزون' },
    monitoring_report: { icon: '📋', label: 'كشف المتابعة اليومي' },
    assessments_bank: { icon: '📚', label: 'بنك المشاريع والأبحاث' },
    behavior_analytics: { icon: '🧠', label: 'تحليلات السلوك والمشاركة' },
    white_label: { icon: '🏷️', label: 'تخصيص هوية التقارير' }
};
