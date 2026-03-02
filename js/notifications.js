/**
 * PE Smart School System - Notifications JS
 */

async function renderNotifications() {
    if (!isParent()) {
        navigateTo('dashboard');
        return;
    }

    const mc = document.getElementById('mainContent');
    mc.innerHTML = showLoading();

    const r = await API.get('notifications');
    if (!r || !r.success) return;

    const notifications = r.data;

    mc.innerHTML = `
    <div class="fade-in max-w-4xl mx-auto">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h2 class="text-3xl font-extrabold text-gray-900 font-cairo">🔔 التنبيهات</h2>
                <p class="text-gray-500 mt-1">تابع آخر التحديثات المتعلقة بأبنائك</p>
            </div>
            <button onclick="markAllNotificationsRead()" class="text-blue-600 font-bold hover:underline text-sm">تحديد الكل كمقروء</button>
        </div>

        <div class="space-y-4">
            ${notifications.map(n => `
            <!-- Fix #3: Use data-* attributes instead of raw data in onclick to prevent XSS -->
            <div class="relative bg-white p-5 rounded-2xl shadow-sm border ${n.is_read ? 'border-gray-100 opacity-75' : 'border-blue-200 bg-blue-50/30'} transition-all hover:shadow-md cursor-pointer notif-item"
                 data-id="${n.id}"
                 data-read="${n.is_read ? '1' : '0'}"
                 data-type="${esc(n.type)}"
                 data-student="${n.student_id || ''}"
                 onclick="handleNotificationItemClick(this)">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 flex-shrink-0 flex items-center justify-center rounded-2xl ${getNotifColor(n.type)}">
                        ${getNotifIcon(n.type)}
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between mb-1">
                            <h4 class="font-bold text-gray-800">${esc(n.title)}</h4>
                            <span class="text-[10px] text-gray-400 font-semibold">${formatRelativeTime(n.created_at)}</span>
                        </div>
                        <p class="text-gray-600 text-sm leading-relaxed">${esc(n.message)}</p>
                        ${n.student_name ? `<div class="mt-2 inline-block px-3 py-1 bg-gray-100 rounded-lg text-[10px] font-bold text-gray-500">الطالب: ${esc(n.student_name)}</div>` : ''}
                    </div>
                    ${!n.is_read ? '<div class="w-3 h-3 bg-blue-500 rounded-full mt-2 animate-pulse"></div>' : ''}
                </div>
            </div>
            `).join('')}
            ${notifications.length === 0 ? `
            <div class="text-center py-20">
                <div class="text-6xl mb-4">📭</div>
                <h3 class="text-xl font-bold text-gray-800">لا توجد تنبيهات حالياً</h3>
                <p class="text-gray-400">سنقوم بإخطارك هنا عند وجود أي تحديثات جديدة</p>
            </div>` : ''}
        </div>
    </div>`;
}

function getNotifIcon(type) {
    switch (type) {
        case 'attendance': return '📅';
        case 'fitness': return '🏆';
        case 'health': return '🏥';
        default: return '📢';
    }
}

function getNotifColor(type) {
    switch (type) {
        case 'attendance': return 'bg-orange-100 text-orange-600';
        case 'fitness': return 'bg-green-100 text-green-600';
        case 'health': return 'bg-red-100 text-red-600';
        default: return 'bg-blue-100 text-blue-600';
    }
}

// Fix #3: New handler reads from data-* attributes safely
function handleNotificationItemClick(el) {
    const id = parseInt(el.dataset.id);
    const isRead = el.dataset.read === '1';
    const type = el.dataset.type;
    const studentId = el.dataset.student ? parseInt(el.dataset.student) : null;
    handleNotificationClick(id, isRead, type, studentId);
}

async function handleNotificationClick(id, isRead, type, studentId) {
    if (!isRead) {
        const r = await API.post('notification_read', { id });
        if (r && r.success) {
            updateNotificationBadge();
        }
    }

    if (!studentId) {
        renderNotifications();
        return;
    }

    // Set target student and tab for student profile
    window._profileStudentId = studentId;

    if (type === 'attendance') {
        window.profileTab = 'pattendance';
    } else if (type === 'fitness') {
        window.profileTab = 'pfitness';
    } else if (type === 'health') {
        window.profileTab = 'health';
    } else {
        window.profileTab = 'info';
    }

    navigateTo('studentProfile');
}

async function markAllNotificationsRead() {
    showToast('جاري تحديث الكل...');
    const r = await API.post('notification_mark_all_read');
    if (r && r.success) {
        updateNotificationBadge();
        renderNotifications();
    }
}

/**
 * Periodically check for new notifications (Polling)
 */
let notifPollInterval = null;
function startNotificationPolling() {
    if (!isParent() || notifPollInterval) return;

    updateNotificationBadge();
    notifPollInterval = setInterval(updateNotificationBadge, 30000); // Every 30 seconds
}

// Fix #9: Stop polling on logout to prevent requests after session ends
function stopNotificationPolling() {
    if (notifPollInterval) {
        clearInterval(notifPollInterval);
        notifPollInterval = null;
    }
}

async function updateNotificationBadge() {
    if (!isParent()) return;
    const r = await API.get('notification_unread_count');
    if (r && r.success) {
        const count = r.data.count;
        const badges = document.querySelectorAll('.notification-badge');
        badges.forEach(b => {
            if (count > 0) {
                b.innerText = count > 9 ? '9+' : count;
                b.classList.remove('hidden');
            } else {
                b.classList.add('hidden');
            }
        });
    }
}

// Fix #14: Extended to show weeks/months instead of jumping to full date after 7 days
function formatRelativeTime(dateStr) {
    const date = new Date(dateStr.replace(/-/g, '/'));
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);

    if (diff < 60) return 'الآن';
    if (diff < 3600) return `${Math.floor(diff / 60)} دقيقة`;
    if (diff < 86400) return `${Math.floor(diff / 3600)} ساعة`;
    if (diff < 604800) return `${Math.floor(diff / 86400)} يوم`;
    if (diff < 2592000) return `${Math.floor(diff / 604800)} أسبوع`;
    if (diff < 31536000) return `${Math.floor(diff / 2592000)} شهر`;

    return date.toLocaleDateString('ar-SA');
}
