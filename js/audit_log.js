/**
 * PE Smart School System - Audit Log
 * =====================================
 * عرض سجل الأنشطة (Audit Log) للمشرفين والمدراء
 */

async function renderAuditLog() {
    const mc = document.getElementById('mainContent');
    mc.innerHTML = showLoading();

    const r = await API.get('audit_logs');
    if (!r || !r.success) {
        mc.innerHTML = '<p class="text-red-500 text-center py-8">خطأ في جلب سجل الأنشطة. تأكد من توفر الصلاحيات الكافية لتشغيل هذه الصفحة.</p>';
        return;
    }

    const logs = r.data || [];

    const actionMap = {
        'login': { label: 'تسجيل الدخول', icon: '🔑', color: 'text-blue-500', bg: 'bg-blue-50' },
        'logout': { label: 'تسجيل الخروج', icon: '🚪', color: 'text-gray-500', bg: 'bg-gray-50' },
        'create': { label: 'إنشاء/إضافة', icon: '➕', color: 'text-emerald-500', bg: 'bg-emerald-50' },
        'update': { label: 'تحديث بيانات', icon: '✏️', color: 'text-yellow-500', bg: 'bg-yellow-50' },
        'delete': { label: 'حذف بيانات', icon: '🗑️', color: 'text-red-500', bg: 'bg-red-50' },
        'import': { label: 'استيراد بيانات', icon: '📥', color: 'text-indigo-500', bg: 'bg-indigo-50' },
        'export': { label: 'تصدير بيانات', icon: '📤', color: 'text-purple-500', bg: 'bg-purple-50' },
        'score_add': { label: 'إضافة درجة', icon: '🌟', color: 'text-orange-500', bg: 'bg-orange-50' },
        'attendance_mark': { label: 'تسجيل حضور', icon: '📋', color: 'text-teal-500', bg: 'bg-teal-50' }
    };

    mc.innerHTML = `
    <div class="fade-in max-w-7xl mx-auto px-4 md:px-0">
        <div class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-black text-gray-800 flex items-center gap-3">
                    <span class="w-10 h-10 bg-gradient-to-br from-gray-700 to-gray-900 rounded-xl flex items-center justify-center text-xl text-white shadow-lg">🛡️</span>
                    سجل الأنشطة (Audit Log)
                </h2>
                <p class="text-gray-500 mt-2 font-bold text-sm">مراقبة كافة حركات وتغييرات النظام للامتثال الأمني.</p>
            </div>
            <div>
                <button onclick="renderAuditLog()" class="bg-white border border-gray-200 text-gray-600 px-4 py-2 rounded-xl font-bold hover:bg-gray-50 transition shadow-sm flex items-center gap-2 text-sm">
                    <span>🔄</span> تحديث السجل
                </button>
            </div>
        </div>

        <div class="bg-white rounded-[2rem] shadow-xl shadow-gray-100 border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto p-2">
                <table class="w-full text-right whitespace-nowrap">
                    <thead>
                        <tr class="bg-gray-50 rounded-xl">
                            <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase tracking-widest rounded-r-xl">رقم الحدث</th>
                            <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase tracking-widest">التاريخ والوقت</th>
                            <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase tracking-widest">المستخدم</th>
                            <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase tracking-widest">نوع الإجراء</th>
                            <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase tracking-widest">الكيان / التفاصيل</th>
                            <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase tracking-widest rounded-l-xl">IP Address</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        ${logs.length === 0 ? `
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <span class="text-4xl opacity-50 block mb-3">📭</span>
                                <p class="text-gray-400 font-bold">لا توجد أنشطة مسجلة حتى الآن.</p>
                            </td>
                        </tr>
                        ` : ''}
                        ${logs.map(log => {
        // Extract mapped style or default
        let actStyle = actionMap[log.action];
        if (!actStyle) {
            // Default styling for unknown actions
            actStyle = { label: log.action, icon: '📌', color: 'text-gray-600', bg: 'bg-gray-100' };
        }

        // Format Date
        const dateObj = new Date(log.created_at);
        const formattedDate = dateObj.toLocaleDateString('ar-SA') + ' - ' + dateObj.toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit' });

        return `
                            <tr class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-6 py-5">
                                    <span class="text-xs font-black text-gray-400 bg-gray-100 px-2 py-1 rounded-lg">#${log.id}</span>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="text-sm font-bold text-gray-700" dir="ltr">${formattedDate}</div>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-xs font-bold text-gray-500">
                                            ${log.user_name ? log.user_name.charAt(0) : '🤖'}
                                        </div>
                                        <div>
                                            <p class="text-sm font-black text-gray-800">${esc(log.user_name || 'نظام ')}</p>
                                            <p class="text-[10px] text-gray-400 uppercase font-black tracking-widest mt-0.5">${esc(log.user_role || 'System')}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl ${actStyle.bg} ${actStyle.color} text-xs font-black border border-white shadow-sm">
                                        <span>${actStyle.icon}</span> ${esc(actStyle.label)}
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <p class="text-sm font-bold text-gray-800">
                                        ${log.entity_type ? '<span class="text-gray-500 italic mr-1">[' + esc(log.entity_type) + ' ' + (log.entity_id || '') + ']</span>' : ''}
                                    </p>
                                    <p class="text-xs text-gray-500 mt-1 max-w-xs truncate" title="${esc(log.details || '')}">${esc(log.details || '—')}</p>
                                </td>
                                <td class="px-6 py-5">
                                    <code class="text-xs font-black text-gray-400 bg-gray-50 px-2 py-1 rounded-lg border border-gray-100" dir="ltr">${esc(log.ip_address || 'Unknown')}</code>
                                </td>
                            </tr>
                            `;
    }).join('')}
                    </tbody>
                </table>
            </div>
            
            <div class="bg-gray-50 px-6 py-4 rounded-b-[2rem] border-t border-gray-100 flex justify-between items-center text-xs text-gray-400 font-bold uppercase tracking-widest">
                <span>يتم عرض أحدث 500 عملية للامتثال (PDPL Audit Log)</span>
                <span>PE Smart School System 🛡️</span>
            </div>
        </div>
    </div>`;
}

console.log('✅ audit_log.js loaded');
