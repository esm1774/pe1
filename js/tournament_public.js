/**
 * Live Tournament Public Viewer v1.0
 */
class TournamentPublic {
    constructor() {
        this.token = new URLSearchParams(window.location.search).get('t');
        this.apiBase = 'modules/tournaments/api.php';
        this.refreshInterval = 30000; // 30 seconds
        this.currentTab = 'matches';

        if (!this.token) {
            this.showError('رابط غير صالح أو مفقود');
            return;
        }

        this.init();
    }

    async init() {
        this.initTabs();
        await this.loadData();
        this.startLiveUpdates();
    }

    initTabs() {
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                this.currentTab = btn.dataset.tab;
                this.renderSection();
            });
        });
    }

    async loadData() {
        try {
            const response = await fetch(`${this.apiBase}?action=tournament_public&t=${this.token}`);
            const result = await response.json();

            if (result.success) {
                this.data = result.data;
                this.renderHeader();
                this.renderSection();
                this.loadTopScorers(); // Load scorers separately
            } else {
                this.showError(result.error);
            }
        } catch (error) {
            this.showError('خطأ في الاتصال بالخادم');
        }
    }

    async loadTopScorers() {
        try {
            const res = await fetch(`${this.apiBase}?action=top_scorers&t=${this.token}`);
            const result = await res.json();
            if (result.success) {
                this.scorers = result.data;
                if (this.currentTab === 'scorers') this.renderSection();
            } else {
                console.error(result.error);
            }
        } catch (e) { }
    }

    startLiveUpdates() {
        setInterval(async () => {
            await this.loadData();
            console.log('Data Refreshed');
        }, this.refreshInterval);
    }

    renderHeader() {
        document.getElementById('tournament-name').textContent = this.data.name;
        document.getElementById('tournament-sport').textContent = this.data.sport_type || 'كرة قدم';

        let statusHtml = '';

        if (this.data.status === 'completed' && this.data.winner_team_id) {
            // Find winner team name from teams list
            const winnerTeam = this.data.teams?.find(t => t.id == this.data.winner_team_id);
            const winnerName = winnerTeam ? winnerTeam.team_name : 'بطل غير معروف';
            statusHtml += `<div style="background: linear-gradient(135deg, #FFD700, #FFA500); color: #fff; padding: 0.5rem 1rem; border-radius: 2rem; display: inline-flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem; font-weight: 800; box-shadow: 0 4px 10px rgba(255,165,0,0.3);"><span style="font-size: 1.2rem;">🏆</span> بطل البطولة: ${winnerName}</div><br>`;
        }

        statusHtml += this.data.status === 'in_progress' ?
            '<span class="live-dot"></span> مباشر الآن' : 'منتهية';

        if (this.data.top_loved_team && this.data.top_loved_team.cheers_count > 0) {
            statusHtml += ` <span class="loved-badge" style="margin-right:10px">❤️ محبوب الجماهير: ${this.data.top_loved_team.team_name}</span>`;
        }

        document.getElementById('tournament-status').innerHTML = statusHtml;
    }

    renderSection() {
        const container = document.getElementById('content-area');
        container.innerHTML = '';

        switch (this.currentTab) {
            case 'matches':
                this.renderMatches(container);
                break;
            case 'schedule':
                this.renderSchedule(container);
                break;
            case 'standings':
                this.renderStandings(container);
                break;
            case 'stats':
                this.renderStats(container);
                break;
            case 'scorers':
                this.renderScorers(container);
                break;
            case 'bracket':
                this.renderBracket(container);
                break;
        }
    }

    renderMatches(container) {
        if (!this.data.recent_matches || this.data.recent_matches.length === 0) {
            container.innerHTML = '<div class="card" style="text-align:center">لا يوجد مباريات مجدولة حالياً</div>';
            return;
        }

        let html = '<div class="card"><h3>أحدث النتائج والمباريات</h3><div class="matches-list">';
        this.data.recent_matches.forEach(match => {
            const isLive = match.status === 'in_progress';
            html += `
                <div class="match-row">
                    <div class="team">
                        <div class="team-icon">⚽</div>
                        <span class="team-name">${match.team1_name}</span>
                        <button onclick="App.cheer('team', ${match.team1_id})" class="cheer-btn">❤️ <span class="cheer-count" id="cheer-team-${match.team1_id}">...</span></button>
                    </div>
                    <div class="score-box">
                        <span class="score">${match.team1_score ?? '-'} : ${match.team2_score ?? '-'}</span>
                        <div class="flex gap-2 justify-center mt-1">
                            <span class="match-meta">${isLive ? '<span class="live-badge">Live</span>' : match.match_date || ''}</span>
                            ${match.media_count > 0 ? `<button onclick="App.showMatchMedia(${match.id})" class="media-btn">🖼️ ${match.media_count}</button>` : ''}
                        </div>
                        ${match.man_of_match_name ? `<span class="match-meta clickable-player" onclick="App.openPlayerProfile(${match.man_of_match_student_id})" style="color:var(--accent)">🌟 ${match.man_of_match_name}</span>` : ''}
                    </div>
                    <div class="team">
                        <div class="team-icon">⚽</div>
                        <span class="team-name">${match.team2_name}</span>
                        <button onclick="App.cheer('team', ${match.team2_id})" class="cheer-btn">❤️ <span class="cheer-count" id="cheer-team-${match.team2_id}">...</span></button>
                    </div>
                </div>
            `;
        });
        html += '</div></div>';
        container.innerHTML = html;
    }

    renderSchedule(container) {
        if (!this.data.recent_matches || this.data.recent_matches.length === 0) {
            container.innerHTML = '<div class="card" style="text-align:center; padding:3rem 1rem">📅 لا يوجد جدول مواعيد حالياً</div>';
            return;
        }

        const schedule = [...this.data.recent_matches].sort((a, b) => {
            const dateA = a.match_date || '9999';
            const dateB = b.match_date || '9999';
            if (dateA !== dateB) return dateA.localeCompare(dateB);
            return (a.match_time || '99').localeCompare(b.match_time || '99');
        });

        let html = `
            <div class="card">
                <h3>📅 جدول مواعيد البطولة</h3>
                <p class="scroll-hint">↔️ اسحب يساراً ويميناً لمشاهدة كامل الجدول</p>
                <div class="schedule-wrapper">
                    <table class="schedule-table">
                        <thead>
                            <tr>
                                <th>م</th>
                                <th>التاريخ</th>
                                <th>الوقت</th>
                                <th>المواجهة</th>
                                <th style="text-align:center">ج</th>
                                <th>ملاحظات</th>
                            </tr>
                        </thead>
                        <tbody>
        `;

        schedule.forEach((m, i) => {
            const isCompleted = m.status === 'completed';
            const isLive = m.status === 'in_progress';
            const team1 = m.team1_name || '؟';
            const team2 = m.team2_name || '؟';

            html += `
                <tr style="${isCompleted ? 'opacity:0.55;' : isLive ? 'background:#f0fdf4;' : ''}">
                    <td style="font-size:0.75rem; color:var(--text-muted)">${i + 1}</td>
                    <td style="font-weight:800; color:var(--primary-dark); white-space:nowrap">${m.match_date || 'لم يحدد'}</td>
                    <td style="white-space:nowrap">${m.match_time || '--:--'}</td>
                    <td>
                        <div style="font-size:0.8rem; line-height:1.5">
                            <div style="font-weight:700">${team1}</div>
                            <div style="font-size:0.65rem; color:#aaa">ضد</div>
                            <div style="font-weight:700">${team2}</div>
                        </div>
                    </td>
                    <td style="text-align:center">
                        <span style="background:#e8f5e9; color:var(--primary-dark); padding:2px 6px; border-radius:8px; font-size:0.7rem; font-weight:800">${m.round_number || '-'}</span>
                    </td>
                    <td style="font-size:0.75rem; color:var(--text-muted); max-width:80px; overflow:hidden; text-overflow:ellipsis">${m.notes || '-'}</td>
                </tr>
            `;
        });

        html += '</tbody></table></div></div>';
        container.innerHTML = html;
    }

    renderStandings(container) {
        if (!this.data.teams || this.data.teams.length === 0) {
            container.innerHTML = '<div class="card" style="text-align:center; padding:3rem 1rem">📊 جدول الترتيب غير متوفر</div>';
            return;
        }

        let html = `
            <div class="card">
                <h3>📊 جدول ترتيب الفرق</h3>
                <p class="scroll-hint">↔️ اسحب يساراً ويميناً لمشاهدة كامل الجدول</p>
                <div class="standings-wrapper">
                    <table class="standings-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الفريق</th>
                                <th>لعب</th>
                                <th>نقاط</th>
                                <th>+/-</th>
                                <th>تشجيع</th>
                            </tr>
                        </thead>
                        <tbody>
        `;

        this.data.teams.forEach((team, index) => {
            const medal = index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : index + 1;
            html += `
                <tr>
                    <td class="rank" style="font-size:1rem">${medal}</td>
                    <td>
                        <div style="display:flex; align-items:center; gap:0.4rem">
                            <span style="width:10px; height:10px; background:${team.team_color || '#10b981'}; border-radius:50%; display:inline-block; flex-shrink:0"></span>
                            <span style="font-weight:700; font-size:0.85rem">${team.team_name}</span>
                        </div>
                        ${index === 0 ? '<div><span class="loved-badge" style="margin-top:3px">🔝 متصدر</span></div>' : ''}
                    </td>
                    <td style="text-align:center">${team.played || 0}</td>
                    <td style="text-align:center; font-weight:800; color:var(--primary-dark); font-size:1rem">${team.points || 0}</td>
                    <td style="text-align:center; font-weight:700; color:${(team.goal_difference || 0) > 0 ? 'var(--primary)' : (team.goal_difference || 0) < 0 ? '#ef4444' : 'inherit'}">${(team.goal_difference || 0) > 0 ? '+' : ''}${team.goal_difference || 0}</td>
                    <td style="text-align:center">
                        <button onclick="App.cheer('team', ${team.id})" class="cheer-btn">❤️ <span class="cheer-count">${team.cheers_count || 0}</span></button>
                    </td>
                </tr>
            `;
        });

        html += '</tbody></table></div></div>';
        container.innerHTML = html;
    }

    renderStats(container) {
        let html = '';

        // لوحة شرف الأبطال (الجوائز والمسميات المتميزة)
        if (this.data.awards && this.data.awards.length > 0) {
            html += `
                <div class="card" style="border: 3px solid var(--accent); background: linear-gradient(to bottom, #fffcf0, #ffffff); border-radius: 2rem;">
                    <h3 style="color:var(--accent); display:flex; align-items:center; gap:0.5rem; font-size: 1.5rem; justify-content: center; margin-bottom: 2rem;">🌟 لوحة شرف الأبطال</h3>
                    <div class="awards-list">
            `;

            // فلترة لعرض فقط من لديهم جوائز فعلاً أو نجوم مباراة
            const elite = this.data.awards.filter(a => a.awards || a.man_of_match > 0);

            if (elite.length > 0) {
                elite.forEach(a => {
                    html += `
                            <div class="award-card" style="background:white; padding:1.2rem; border-radius:1.5rem; margin-bottom:1rem; border:1px solid #eee; display:flex; justify-content:between; align-items:center; box-shadow: 0 4px 15px rgba(0,0,0,0.02)">
                                <div style="flex:1">
                                    <div class="clickable-player" onclick="App.openPlayerProfile(${a.student_id})" style="font-weight:900; color:var(--primary); font-size:1.1rem">${a.student_name}</div>
                                    <div style="font-size:0.8rem; color:var(--text-muted)">${a.team_name}</div>
                                    <button onclick="App.cheer('player', ${a.student_id})" class="cheer-btn" style="margin-top:5px">❤️ <span class="cheer-count">${a.cheers_count || 0}</span></button>
                                </div>
                                <div style="text-align:left">
                                    ${a.awards ? `<span class="badge" style="background:linear-gradient(45deg, #FFD700, #FFA500); color:white; padding:0.4rem 0.8rem; border-radius:2rem; font-size:0.75rem; font-weight:bold; box-shadow: 0 4px 10px rgba(255,215,0,0.3)">${a.awards}</span>` : ''}
                                    ${a.man_of_match > 0 ? `<div style="font-size:0.7rem; font-weight:bold; color:#6b7280; margin-top:0.5rem">⭐ نجم المباراة: ${a.man_of_match}</div>` : ''}
                                </div>
                            </div>
                        `;
                });
            } else {
                html += '<p style="text-align:center; padding:2rem; color:#999">سيتم إدراج الطالب المثالي والجوائز قريباً</p>';
            }
            html += '</div></div>';
        }

        if (!html) {
            html = `<div class="card" style="text-align:center; padding:3rem">
                <p style="font-size:3rem">🌟</p>
                <h3>الطلاب المتميزون</h3>
                <p style="color:#999; margin-top:1rem">سيظهر هنا الطلاب الحاصلون على ألقاب (اللاعب المثالي، أحسن أخلاق، نجم المباراة)</p>
            </div>`;
        }

        container.innerHTML = html;
    }

    renderScorers(container) {
        if (!this.scorers || this.scorers.length === 0) {
            container.innerHTML = `<div class="card" style="text-align:center; padding:3rem">
                <p style="font-size:3rem">⚽</p>
                <h3>هداف البطولة</h3>
                <p style="color:#999; margin-top:1rem">لم يتم تسجيل أهداف حتى الآن</p>
            </div>`;
            return;
        }

        let html = '<div class="card"><h3>🎯 قائمة هدافي البطولة</h3><div class="scorers-list" style="margin-top:1.5rem">';
        this.scorers.forEach((s, i) => {
            const isTop = i === 0;
            html += `
                <div class="scorer-row" style="${isTop ? 'background: var(--primary-light); border: 1px solid var(--primary); border-radius: 1rem;' : ''}">
                    <div class="scorer-rank" style="${isTop ? 'background:var(--primary); color:white' : ''}">${i + 1}</div>
                    <div class="scorer-info">
                        <div class="scorer-name clickable-player" onclick="App.openPlayerProfile(${s.student_id})" style="${isTop ? 'font-weight:900; font-size:1.1rem' : ''}">${s.student_name} ${isTop ? '👑' : ''}</div>
                        <div class="scorer-team">${s.team_name}</div>
                        <button onclick="App.cheer('player', ${s.student_id})" class="cheer-btn" style="padding:0">❤️ <span class="cheer-count">${s.cheers_count || 0}</span></button>
                    </div>
                    <div class="scorer-goals" style="font-weight:900; color:var(--primary)">${s.goals} <span style="font-size:0.7rem; font-weight:normal">هدف</span></div>
                </div>
            `;
        });
        html += '</div></div>';
        container.innerHTML = html;
    }

    async renderBracket(container) {
        if (!this.bracketData) {
            container.innerHTML = '<div class="loading-spinner"></div>';
            await this.loadBracketData();
        }

        const b = this.bracketData;
        if (!b || !b.main) {
            container.innerHTML = '<div class="card" style="text-align:center; padding:3rem 1rem">🌳 المخطط الشجري غير متوفر لهذه البطولة</div>';
            return;
        }

        let html = `
        <div class="card">
            <h3>🌳 مخطط البطولة</h3>
            <p class="scroll-hint">↔️ اسحب يساراً ويميناً لاستعراض كامل الشجرة</p>
            <div class="bracket-container">
        `;

        const rounds = Object.keys(b.main).sort((a, b) => a - b);
        rounds.forEach(r => {
            const totalRounds = rounds.length;
            const roundIdx = rounds.indexOf(r);
            const remaining = totalRounds - roundIdx;
            const roundName = remaining === 1 ? '🏆 النهائي' :
                remaining === 2 ? '🥇 نصف النهائي' :
                    remaining === 3 ? 'ربع النهائي' : `الجولة ${r}`;

            html += `<div class="bracket-round"><div class="round-title">${roundName}</div>`;
            b.main[r].forEach(m => {
                const isCompleted = m.status === 'completed';
                const score1 = m.team1_score ?? '';
                const score2 = m.team2_score ?? '';
                const w1 = isCompleted && m.winner_team_id == m.team1_id;
                const w2 = isCompleted && m.winner_team_id == m.team2_id;

                html += `
                    <div class="bracket-match ${isCompleted ? 'completed' : ''}">
                        <div class="b-team ${w1 ? 'winner' : w2 ? 'loser' : ''}">
                            <span class="b-name">${m.team1_name || '؟'}</span>
                            <span class="b-score">${score1}</span>
                        </div>
                        <div class="b-team ${w2 ? 'winner' : w1 ? 'loser' : ''}">
                            <span class="b-name">${m.team2_name || '؟'}</span>
                            <span class="b-score">${score2}</span>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
        });

        html += '</div></div>';
        container.innerHTML = html;
    }

    async loadBracketData() {
        try {
            const res = await fetch(`${this.apiBase}?action=bracket_public&t=${this.token}`);
            const result = await res.json();
            if (result.success) {
                this.bracketData = result.data.bracket;
                if (this.currentTab === 'bracket') this.renderSection();
            }
        } catch (e) { }
    }

    async showMatchMedia(matchId) {
        try {
            const res = await fetch(`${this.apiBase}?action=match_media_list&match_id=${matchId}`);
            const result = await res.json();
            if (result.success && result.data.length > 0) {
                this.renderMediaModal(result.data);
            }
        } catch (e) { }
    }

    renderMediaModal(media) {
        const modal = document.createElement('div');
        modal.className = 'media-modal-overlay';
        modal.onclick = (e) => { if (e.target === modal) modal.remove(); };

        let content = `
            <div class="media-modal-content">
                <button class="close-modal" onclick="this.parentElement.parentElement.remove()">✕</button>
                <div class="media-gallery">
        `;

        media.forEach(m => {
            const isVideo = m.media_type === 'video';
            content += `
                <div class="media-item">
                    ${isVideo ?
                    `<video src="${m.media_url}" controls></video>` :
                    `<img src="${m.media_url}" alt="${m.description || ''}" onclick="window.open('${m.media_url}')">`
                }
                    ${m.description ? `<p class="media-desc">${m.description}</p>` : ''}
                </div>
            `;
        });

        content += '</div></div>';
        modal.innerHTML = content;
        document.body.appendChild(modal);
    }

    async cheer(type, id) {
        // Prevent spam
        const key = `cheer_${type}_${id}_${this.data.id}`;
        if (sessionStorage.getItem(key)) return;

        try {
            const btn = event.currentTarget;
            btn.classList.add('animate-heart', 'active');
            sessionStorage.setItem(key, '1');

            const res = await fetch(`${this.apiBase}?action=cheer_action&type=${type}&id=${id}&tournament_id=${this.data.id}`);
            const result = await res.json();

            if (result.success) {
                const countSpan = btn.querySelector('.cheer-count');
                if (countSpan) {
                    const current = parseInt(countSpan.textContent) || 0;
                    countSpan.textContent = current + 1;
                }
            }
        } catch (e) { }
    }

    async openPlayerProfile(studentId) {
        if (!studentId) return;
        try {
            const res = await fetch(`${this.apiBase}?action=player_history&student_id=${studentId}`);
            const result = await res.json();
            if (result.success) {
                this.renderProfileModal(result.data);
            }
        } catch (e) { }
    }

    renderProfileModal(data) {
        const modal = document.createElement('div');
        modal.className = 'media-modal-overlay';
        modal.onclick = (e) => { if (e.target === modal) modal.remove(); };

        const { student, stats, history } = data;

        let content = `
            <div class="media-modal-content">
                <button class="close-modal" onclick="this.parentElement.parentElement.remove()">✕</button>
                
                <div class="profile-header">
                    <h2 style="font-weight:900; color:var(--primary)">${student.name}</h2>
                    <p style="color:var(--text-muted)">الفصل: ${student.class_name}</p>
                </div>

                <div class="profile-stats-grid">
                    <div class="stat-box">
                        <span class="stat-val">${stats.total_goals || 0}</span>
                        <span class="stat-lab">أهداف</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-val">${stats.total_mom || 0}</span>
                        <span class="stat-lab">نجم مباراة</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-val">${stats.total_cheers || 0}</span>
                        <span class="stat-lab">تشجيع</span>
                    </div>
                </div>

                <h3 style="margin-bottom:1rem; font-size:1.1rem">📜 السجل الرياضي بالمدرسة</h3>
                <div class="history-list">
                    ${history.length > 0 ? history.map(h => `
                        <div class="history-item">
                            <div>
                                <div style="font-weight:bold">${h.tournament_name}</div>
                                <div style="font-size:0.7rem; color:var(--text-muted)">${h.sport_type} - ${h.team_name}</div>
                            </div>
                            <div style="text-align:left">
                                <div style="color:var(--primary); font-weight:bold">${h.goals}⚽</div>
                                ${h.awards ? `<div style="font-size:0.65rem; color:var(--accent)">🏆 ${h.awards}</div>` : ''}
                            </div>
                        </div>
                    `).join('') : '<p style="text-align:center; color:#999">لا سجلات سابقة متوفرة</p>'}
                </div>
            </div>
        `;

        modal.innerHTML = content;
        document.body.appendChild(modal);
    }

    showError(msg) {
        document.querySelector('.container').innerHTML = `
            <div class="card" style="text-align:center; padding:3rem; margin-top:2rem">
                <div style="font-size:4rem; margin-bottom:1rem">⚠️</div>
                <h2 style="color:var(--danger)">عذراً، حدث خطأ</h2>
                <p style="color:var(--text-muted); margin-top:1rem">${msg}</p>
                <a href="index.html" style="display:inline-block; margin-top:2rem; padding:0.75rem 2.5rem; background:var(--primary); color:white; border-radius:1rem; text-decoration:none; font-weight:bold; shadow: var(--shadow)">العودة للرئيسية</a>
            </div>
        `;
    }
}

// Start
document.addEventListener('DOMContentLoaded', () => {
    window.App = new TournamentPublic();
});
