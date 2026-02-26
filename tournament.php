<?php
/**
 * PE Smart School - Public Tournament View
 * Responsive for all devices
 */
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#059669">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>البطولة الرياضية - PE Smart School</title>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;800;900&family=Outfit:wght@400;700;900&display=swap" rel="stylesheet">

    <!-- CSS -->
    <link rel="stylesheet" href="css/tournament_public.css">
</head>
<body>

    <header>
        <div class="container">
            <div id="tournament-live-status" class="live-badge">
                <span class="live-dot"></span>
                <span id="tournament-status">تحميل...</span>
            </div>
            <h1 id="tournament-name">بطولة المدرسة</h1>
            <p id="tournament-sport">كرة القدم</p>
        </div>
    </header>

    <main class="container">
        <!-- Navigation Tabs (scrollable on mobile) -->
        <nav class="tabs" id="mainTabs">
            <button class="tab-btn active" data-tab="matches">⚽ المباريات</button>
            <button class="tab-btn" data-tab="schedule">📅 المواعيد</button>
            <button class="tab-btn" data-tab="bracket">🌳 الشجرة</button>
            <button class="tab-btn" data-tab="standings">📊 الترتيب</button>
            <button class="tab-btn" data-tab="stats">🌟 المتميزون</button>
            <button class="tab-btn" data-tab="scorers">🎯 الهدافون</button>
        </nav>

        <!-- Dynamic Content Area -->
        <div id="content-area">
            <div class="card" style="text-align: center; padding: 3rem 1rem;">
                <div class="loading-spinner"></div>
                <p style="color: var(--text-muted); font-size: 0.9rem;">جاري تحميل بيانات البطولة...</p>
            </div>
        </div>

        <!-- Footer -->
        <footer>
            <p>© 2026 PE Smart School System</p>
            <p style="margin-top: 0.25rem; font-size: 0.75rem;">نظام ذكي لإدارة التربية البدنية</p>
        </footer>
    </main>

    <script src="js/tournament_public.js"></script>
</body>
</html>
