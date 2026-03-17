<?php
/**
 * PE Smart School - Tournament Archive & Evidence Report
 * Mobile-First Responsive Design
 */
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#059669">
    <title>تقرير ختامي - البطولة الرياضية</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;800;900&display=swap" rel="stylesheet">

    <style>
        /* ============================================================
           VARIABLES & RESET
           ============================================================ */
        :root {
            --primary: #059669;
            --primary-dark: #065f46;
            --primary-light: #ecfdf5;
            --accent: #10b981;
            --gold: #f59e0b;
            --gold-light: #fef3c7;
            --bg: #f0fdf4;
            --text: #1e293b;
            --text-light: #64748b;
            --card-bg: #ffffff;
            --border: #e2e8f0;
            --shadow: 0 4px 20px rgba(5, 150, 105, 0.08);
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        html { -webkit-text-size-adjust: 100%; }

        body {
            font-family: 'Cairo', sans-serif;
            background-color: var(--bg);
            color: var(--text);
            line-height: 1.65;
            overflow-x: hidden;
        }

        /* ============================================================
           LAYOUT
           ============================================================ */
        .container {
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
            padding: 1rem;
        }

        @media (min-width: 768px) {
            .container { padding: 2rem; }
        }

        /* ============================================================
           LOADING OVERLAY
           ============================================================ */
        .loading-overlay {
            position: fixed;
            inset: 0;
            background: white;
            z-index: 100;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1rem;
        }

        .spinner {
            width: 48px;
            height: 48px;
            border: 4px solid var(--primary-light);
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            animation: spin 0.9s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        /* ============================================================
           CONTROLS BAR
           ============================================================ */
        .controls {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
        }

        @media (min-width: 540px) {
            .controls {
                flex-direction: row;
                justify-content: center;
            }
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0.85rem 1.5rem;
            border-radius: 0.85rem;
            font-weight: 800;
            font-size: 0.95rem;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: all 0.2s;
            font-family: 'Cairo', sans-serif;
            width: 100%;
        }

        @media (min-width: 540px) {
            .btn { width: auto; min-width: 180px; }
        }

        .btn-print {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 14px rgba(5, 150, 105, 0.35);
        }

        .btn-print:hover { background: var(--primary-dark); transform: translateY(-2px); }

        .btn-back {
            background: white;
            color: var(--text);
            border: 1.5px solid var(--border);
        }

        .btn-back:hover { background: var(--bg); }

        /* ============================================================
           REPORT HEADER
           ============================================================ */
        .report-header {
            text-align: center;
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
            color: white;
            padding: 2rem 1.25rem;
            border-radius: 1.5rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 12px 30px rgba(5, 150, 105, 0.2);
            position: relative;
            overflow: hidden;
        }

        .report-header::before {
            content: '';
            position: absolute;
            top: -40%;
            left: -10%;
            width: 180px;
            height: 180px;
            background: rgba(255,255,255,0.07);
            border-radius: 50%;
            pointer-events: none;
        }

        .report-header::after {
            content: "🏆";
            position: absolute;
            bottom: -15px;
            right: 10px;
            font-size: 5rem;
            opacity: 0.07;
            transform: rotate(-10deg);
            pointer-events: none;
        }

        .report-header h1 {
            font-size: 1.5rem;
            font-weight: 900;
            margin-bottom: 0.4rem;
            line-height: 1.25;
            position: relative;
            z-index: 1;
        }

        .report-header p {
            font-size: 0.85rem;
            opacity: 0.88;
            font-weight: 700;
            position: relative;
            z-index: 1;
        }

        @media (min-width: 640px) {
            .report-header { padding: 3rem 2rem; }
            .report-header h1 { font-size: 2.2rem; }
            .report-header p { font-size: 1rem; }
        }

        @media (min-width: 768px) {
            .report-header h1 { font-size: 2.75rem; }
        }

        /* ============================================================
           CHAMPION BANNER
           ============================================================ */
        .champion-card {
            background: linear-gradient(135deg, #fffbeb, var(--gold-light));
            border: 2.5px solid #fbbf24;
            text-align: center;
            padding: 1.5rem 1rem;
            border-radius: 1.5rem;
            margin-bottom: 1.25rem;
            box-shadow: 0 8px 20px rgba(251, 191, 36, 0.18);
        }

        .champion-emoji {
            font-size: 3rem;
            display: block;
            margin-bottom: 0.4rem;
        }

        .champion-label {
            font-size: 0.95rem;
            font-weight: 800;
            color: #92400e;
            opacity: 0.8;
        }

        .champion-name {
            font-size: 1.75rem;
            font-weight: 900;
            color: #78350f;
            margin: 0.35rem 0;
            line-height: 1.2;
        }

        @media (min-width: 640px) {
            .champion-card { padding: 2.5rem 2rem; }
            .champion-emoji { font-size: 4rem; }
            .champion-name { font-size: 2.5rem; }
        }

        .champion-sub {
            font-size: 0.9rem;
            color: #92400e;
            font-weight: 700;
        }

        /* ============================================================
           SECTIONS
           ============================================================ */
        .section {
            background: var(--card-bg);
            border-radius: 1.25rem;
            padding: 1.25rem;
            margin-bottom: 1.25rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid #e8f5e9;
        }

        @media (min-width: 768px) {
            .section { padding: 2rem; margin-bottom: 1.75rem; border-radius: 1.5rem; }
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.05rem;
            font-weight: 900;
            margin-bottom: 1.25rem;
            color: var(--primary-dark);
            border-bottom: 2px solid var(--primary-light);
            padding-bottom: 0.65rem;
        }

        @media (min-width: 768px) {
            .section-title { font-size: 1.35rem; }
        }

        /* ============================================================
           STATS GRID (Awards + Scorers side by side on desktop)
           ============================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.25rem;
            margin-bottom: 0;
        }

        @media (min-width: 768px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }

        .stats-grid .section {
            margin-bottom: 0;
        }

        /* ============================================================
           STANDINGS TABLE
           ============================================================ */
        .table-wrapper {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: 0.75rem;
            border: 1px solid var(--border);
            margin-top: 0.5rem;
        }

        .scroll-hint {
            font-size: 0.7rem;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 0.6rem;
        }

        @media (min-width: 640px) { .scroll-hint { display: none; } }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 420px;
        }

        th {
            background: var(--primary-light);
            color: var(--primary-dark);
            text-align: right;
            padding: 0.75rem 0.85rem;
            font-size: 0.78rem;
            font-weight: 900;
            white-space: nowrap;
            letter-spacing: 0.03em;
        }

        td {
            padding: 0.75rem 0.85rem;
            border-bottom: 1px solid #f0fdf4;
            font-size: 0.9rem;
            vertical-align: middle;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover td { background-color: #f9fffe; }

        /* ============================================================
           AWARD ITEMS
           ============================================================ */
        .award-item {
            background: var(--bg);
            padding: 0.85rem 1rem;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 0.6rem;
            border: 1px solid transparent;
            transition: all 0.25s;
        }

        .award-item:hover {
            border-color: var(--accent);
            background: white;
            box-shadow: 0 3px 10px rgba(5, 150, 105, 0.07);
        }

        .award-icon {
            width: 42px;
            height: 42px;
            background: var(--primary-light);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.35rem;
            flex-shrink: 0;
        }

        .award-info { flex: 1; min-width: 0; }
        .award-name { font-weight: 800; font-size: 0.9rem; }
        .award-sub { font-size: 0.72rem; color: var(--text-light); margin-top: 1px; }

        /* ============================================================
           MATCH CARDS
           ============================================================ */
        .match-card {
            background: white;
            border: 1px solid var(--border);
            padding: 0.85rem 1rem;
            border-radius: 1rem;
            margin-bottom: 0.6rem;
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
            gap: 0.5rem;
            transition: box-shadow 0.2s;
        }

        .match-card:hover {
            box-shadow: 0 3px 10px rgba(0,0,0,0.06);
        }

        .match-team {
            font-weight: 700;
            font-size: 0.85rem;
            line-height: 1.3;
        }

        .match-team-1 { text-align: right; }
        .match-team-2 { text-align: left; }

        .match-score-box {
            text-align: center;
            flex-shrink: 0;
        }

        .match-score {
            font-weight: 900;
            font-size: 1.1rem;
            background: #f0fdf4;
            color: var(--primary-dark);
            padding: 0.25rem 0.75rem;
            border-radius: 0.5rem;
            display: inline-block;
            white-space: nowrap;
        }

        .match-round {
            font-size: 0.65rem;
            color: var(--text-light);
            margin-top: 4px;
        }

        /* ============================================================
           MEDIA GALLERY
           ============================================================ */
        .media-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 0.75rem;
        }

        @media (min-width: 480px) {
            .media-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 14px; }
        }

        @media (min-width: 768px) {
            .media-grid { grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; }
        }

        .media-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: transform 0.3s;
        }

        .media-card:hover { transform: scale(1.02); }

        .media-card img {
            width: 100%;
            aspect-ratio: 4/3;
            object-fit: cover;
            display: block;
        }

        .media-info {
            padding: 8px;
            font-size: 0.68rem;
            color: var(--text-light);
            text-align: center;
            font-weight: 700;
        }

        /* ============================================================
           FOOTER
           ============================================================ */
        .report-footer {
            text-align: center;
            margin-top: 3rem;
            padding: 2rem 1rem;
            border-top: 1px solid var(--border);
            color: var(--text-light);
            font-size: 0.82rem;
        }

        .report-footer p + p { margin-top: 0.25rem; }

        /* ============================================================
           PRINT STYLES
           ============================================================ */
        @media print {
            body { background: white; color: black; }
            .container { margin: 0; padding: 0; max-width: 100%; }
            .controls { display: none !important; }
            .section {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
                break-inside: avoid;
                margin-bottom: 12px !important;
                padding: 1.25rem !important;
                border-radius: 0 !important;
            }
            .report-header {
                border-radius: 0 !important;
                background: #f1f5f9 !important;
                color: black !important;
                border-bottom: 2px solid #000 !important;
                box-shadow: none !important;
            }
            .report-header h1,
            .report-header p { color: black !important; }
            .champion-card { background: white !important; border: 2px solid #000 !important; }
            .champion-name { color: #000 !important; }
            th { background: #eee !important; color: #000 !important; }
            .award-icon { background: #eee !important; }
            .media-grid { grid-template-columns: repeat(3, 1fr) !important; }
            .scroll-hint { display: none !important; }
        }
    </style>
</head>
<body>

    <div id="loading" class="loading-overlay">
        <div class="spinner"></div>
        <p style="color:var(--text-light); font-size:0.9rem;">جاري تحضير التقرير الختامي...</p>
    </div>

    <div class="container">

        <!-- Controls -->
        <div class="controls">
            <a href="app.html" class="btn btn-back">⬅️ عودة للوحة التحكم</a>
            <button onclick="window.print()" class="btn btn-print">🖨️ طباعة / حفظ PDF</button>
        </div>

        <div id="report-content">

            <!-- Report Header -->
            <header class="report-header">
                <p id="report-date">التاريخ: ...</p>
                <h1 id="tournament-name">تقرير الإنجاز الرياضي</h1>
                <p id="tournament-meta">... | ...</p>
            </header>

            <!-- Champion Banner -->
            <div id="champion-section" style="display:none;">
                <div class="champion-card">
                    <span class="champion-emoji">🏆</span>
                    <div class="champion-label">بطل النسخة 🥇</div>
                    <div id="champion-name" class="champion-name">...</div>
                    <p class="champion-sub">بطل البطولة بجدارة واستحقاق</p>
                </div>
            </div>

            <!-- Standings -->
            <div class="section">
                <h2 class="section-title">📊 الترتيب النهائي للفرق</h2>
                <p class="scroll-hint">↔️ اسحب يساراً ويميناً لعرض كامل الجدول</p>
                <div class="table-wrapper">
                    <table id="standings-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الفريق / الفصل</th>
                                <th>لعب</th>
                                <th>فوز</th>
                                <th>أهداف</th>
                                <th>نقاط</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Awards & Scorers -->
            <div class="stats-grid" style="margin-bottom:1.25rem;">
                <div class="section">
                    <h2 class="section-title">⭐ الطلاب المتميزون</h2>
                    <div id="awards-list"></div>
                </div>
                <div class="section">
                    <h2 class="section-title">⚽ هدافو البطولة</h2>
                    <div id="scorers-list"></div>
                </div>
            </div>

            <!-- Media Gallery -->
            <div class="section" id="media-section">
                <h2 class="section-title">📸 شواهد مصورة من قلب الحدث</h2>
                <div id="media-gallery" class="media-grid"></div>
            </div>

            <!-- Match History -->
            <div class="section">
                <h2 class="section-title">⚔️ سجل المواجهات والنتائج</h2>
                <div id="matches-summary"></div>
            </div>

            <footer class="report-footer">
                <p>صُدر هذا التقرير آلياً بواسطة نظام PE Smart School</p>
                <p>يُعتبر هذا التقرير مستنداً رسمياً لإنجازات المعلم في النشاط الرياضي</p>
            </footer>

        </div><!-- /report-content -->
    </div><!-- /container -->

    <script>
        const tournamentId = new URLSearchParams(window.location.search).get('id');

        async function loadReport() {
            if (!tournamentId) {
                document.getElementById('loading').style.display = 'none';
                alert('عذراً، معرّف البطولة مفقود');
                return;
            }

            try {
                const res = await fetch(`modules/tournaments/api.php?action=tournament_full_report&id=${tournamentId}`);
                const result = await res.json();

                if (result.success) {
                    renderReport(result.data);
                } else {
                    alert(result.error || 'فشل تحميل بيانات التقرير');
                }
            } catch (e) {
                console.error(e);
                alert('خطأ في الاتصال بالخادم');
            } finally {
                document.getElementById('loading').style.display = 'none';
            }
        }

        function renderReport(data) {
            const t = data.tournament;

            // Header
            document.getElementById('tournament-name').textContent = t.name;
            document.getElementById('tournament-meta').textContent =
                `${t.sport_type} | ${t.type.replace(/_/g,' ')}`;
            document.getElementById('report-date').textContent =
                `تاريخ التقرير: ${new Date().toLocaleDateString('ar-SA', {year:'numeric', month:'long', day:'numeric'})}`;

            // Champion
            if (data.champion) {
                document.getElementById('champion-section').style.display = 'block';
                document.getElementById('champion-name').textContent = data.champion.team_name;
            }

            // Standings
            const standingsBody = document.querySelector('#standings-table tbody');
            if (data.standings && data.standings.length > 0) {
                data.standings.forEach((s, ix) => {
                    const medal = ix === 0 ? '🥇' : ix === 1 ? '🥈' : ix === 2 ? '🥉' : ix + 1;
                    standingsBody.innerHTML += `
                        <tr style="${ix === 0 ? 'background:#fffbeb;' : ''}">
                            <td style="font-size:1.1rem; text-align:center;">${medal}</td>
                            <td>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <div style="width:12px; height:12px; border-radius:50%; background:${s.team_color || '#10b981'}; flex-shrink:0;"></div>
                                    <span style="font-weight:700;">${s.team_name}</span>
                                </div>
                            </td>
                            <td style="text-align:center;">${s.played}</td>
                            <td style="text-align:center; color:var(--primary); font-weight:700;">${s.wins}</td>
                            <td style="text-align:center;">${s.goals_for}</td>
                            <td style="text-align:center; font-weight:900; font-size:1.1rem; color:var(--primary-dark);">${s.points}</td>
                        </tr>`;
                });
            } else {
                standingsBody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:#999; padding:2rem;">لا توجد بيانات ترتيب</td></tr>';
            }

            // Awards
            const awardsList = document.getElementById('awards-list');
            if (data.awards && data.awards.length > 0) {
                data.awards.forEach(a => {
                    awardsList.innerHTML += `
                        <div class="award-item">
                            <span class="award-icon">🌟</span>
                            <div class="award-info">
                                <div class="award-name">${a.student_name}</div>
                                <div class="award-sub">${a.awards || 'نجم البطولة'} · ${a.team_name}</div>
                            </div>
                        </div>`;
                });
            } else {
                awardsList.innerHTML = '<p style="color:#999; text-align:center; padding:1.5rem 0;">لم يتم رصد جوائز بعد</p>';
            }

            // Scorers
            const scorersList = document.getElementById('scorers-list');
            if (data.scorers && data.scorers.length > 0) {
                data.scorers.forEach((s, ix) => {
                    const medal = ix === 0 ? '🥇' : ix === 1 ? '🥈' : ix === 2 ? '🥉' : '⚽';
                    scorersList.innerHTML += `
                        <div class="award-item">
                            <span class="award-icon">${medal}</span>
                            <div class="award-info">
                                <div class="award-name">${s.student_name}</div>
                                <div class="award-sub">${s.team_name}</div>
                            </div>
                            <div style="font-weight:900; font-size:1.2rem; color:var(--primary-dark); flex-shrink:0;">
                                ${s.goals}<span style="font-size:0.7rem; font-weight:700; color:var(--text-light);"> هدف</span>
                            </div>
                        </div>`;
                });
            } else {
                scorersList.innerHTML = '<p style="color:#999; text-align:center; padding:1.5rem 0;">لا يوجد هدافون مسجلون</p>';
            }

            // Gallery
            const gallery = document.getElementById('media-gallery');
            if (data.media && data.media.length > 0) {
                data.media.forEach(m => {
                    if (m.media_type === 'photo') {
                        gallery.innerHTML += `
                            <div class="media-card">
                                <img src="${m.media_url}" loading="lazy" alt="صورة من المباراة">
                                <div class="media-info">${m.t1 || ''} ضد ${m.t2 || ''} ${m.description ? '· ' + m.description : ''}</div>
                            </div>`;
                    }
                });
                if (!gallery.innerHTML.trim()) {
                    document.getElementById('media-section').style.display = 'none';
                }
            } else {
                document.getElementById('media-section').style.display = 'none';
            }

            // Matches
            const matchSummary = document.getElementById('matches-summary');
            if (data.matches && data.matches.length > 0) {
                data.matches.forEach(m => {
                    const isCompleted = m.team1_score !== null;
                    matchSummary.innerHTML += `
                        <div class="match-card">
                            <div class="match-team match-team-1">${m.team1_name || '؟'}</div>
                            <div class="match-score-box">
                                <div class="match-score">${isCompleted ? m.team1_score + ' : ' + m.team2_score : '- : -'}</div>
                                <div class="match-round">${m.match_date ? m.match_date : ''} جولة ${m.round_number}</div>
                            </div>
                            <div class="match-team match-team-2">${m.team2_name || '؟'}</div>
                        </div>`;
                });
            } else {
                matchSummary.innerHTML = '<p style="color:#999; text-align:center; padding:2rem;">لا توجد مباريات مسجلة</p>';
            }
        }

        window.onload = loadReport;
    </script>
</body>
</html>
