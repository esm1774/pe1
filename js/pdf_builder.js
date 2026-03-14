/**
 * PE Smart School – PDF Builder Module
 * =====================================
 * Generates standalone HTML with fully-inline RGB styles for each report type.
 * This bypass the oklab/oklch issue in html2canvas by never using Tailwind CSS.
 *
 * Public API:
 *   downloadReportPdfDirect(type, data, filename)
 *   emailReportPdfDirect(type, data, filename, email)
 */

// ============================================================
// PDF BASE STYLES  (no CSS vars, no oklch – pure RGB)
// ============================================================
const PDF_STYLES = `
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body, div, table, td, th, p, h1, h2, h3, h4, h5, span {
    font-family: 'Arial', 'Tahoma', sans-serif;
    direction: rtl;
  }
  .pdf-page {
    width: 750px;
    margin: 0 auto;
    box-sizing: border-box;
    background: #ffffff;
    padding: 24px 28px;
    color: #111827;
    font-size: 13px;
  }
  /* ── Header ── */
  .pdf-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 3px double #d1d5db;
    padding-bottom: 20px;
    margin-bottom: 24px;
  }
  .pdf-header-col { width: 33.3%; }
  .pdf-header-ministry { text-align: right; font-size: 10px; font-weight: 900; line-height: 1.7; color: #1f2937; }
  .pdf-header-center { text-align: center; }
  .pdf-header-center .school-name { font-size: 17px; font-weight: 900; margin-bottom: 4px; color: #111827; }
  .pdf-header-center .report-badge {
    display: inline-block; border: 2px solid #111827;
    padding: 3px 12px; border-radius: 6px;
    font-size: 9px; font-weight: 900; letter-spacing: 0.08em; text-transform: uppercase;
  }
  .pdf-header-logo { display: flex; justify-content: flex-start; align-items: center; }
  .pdf-header-logo img { height: 80px; width: auto; object-fit: contain; }
  .pdf-header-logo .logo-placeholder {
    width: 80px; height: 80px; border: 2px dashed #d1d5db;
    border-radius: 16px; display: flex; align-items: center;
    justify-content: center; text-align: center; padding: 8px;
    font-size: 9px; font-weight: 900; color: #d1d5db;
  }
  .pdf-header-meta {
    display: flex; justify-content: space-between;
    font-size: 9px; font-weight: 700; color: #9ca3af;
    margin-top: 12px;
  }
  /* ── Section title ── */
  .pdf-section-title {
    display: flex; align-items: center; gap: 10px;
    font-size: 15px; font-weight: 900; color: #111827;
    margin-bottom: 16px;
  }
  .pdf-section-icon {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; background: #f0fdf4;
  }
  /* ── Student hero ── */
  .pdf-student-hero {
    display: flex; justify-content: space-between; align-items: flex-start;
    margin-bottom: 28px; padding-bottom: 20px; border-bottom: 1px solid #f3f4f6;
    gap: 16px;
  }
  .pdf-student-info { display: flex; align-items: center; gap: 16px; }
  .pdf-avatar {
    width: 72px; height: 72px; border-radius: 20px;
    background: #16a34a; display: flex; align-items: center;
    justify-content: center; font-size: 36px; color: #fff;
    overflow: hidden; flex-shrink: 0;
  }
  .pdf-avatar img { width: 100%; height: 100%; object-fit: cover; }
  .pdf-student-name { font-size: 26px; font-weight: 900; color: #111827; }
  .pdf-student-class { font-size: 15px; color: #16a34a; font-weight: 900; margin-top: 2px; }
  .pdf-badges { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
  .pdf-badge {
    padding: 4px 12px; border-radius: 10px;
    font-size: 10px; font-weight: 900;
  }
  .pdf-badge-gray  { background: #f3f4f6; color: #6b7280; }
  .pdf-badge-green { background: #f0fdf4; color: #16a34a; }
  .pdf-badge-red   { background: #fef2f2; color: #dc2626; }
  /* ── Score circle ── */
  .pdf-score-box {
    background: #111827; color: #fff;
    padding: 20px 28px; border-radius: 24px; text-align: center;
    min-width: 160px;
  }
  .pdf-score-number { font-size: 48px; font-weight: 900; line-height: 1; }
  .pdf-score-label { font-size: 9px; font-weight: 900; letter-spacing: 0.15em; text-transform: uppercase; margin-top: 8px; }
  .pdf-score-green  { color: #4ade80; }
  .pdf-score-blue   { color: #60a5fa; }
  .pdf-score-yellow { color: #facc15; }
  .pdf-score-red    { color: #f87171; }
  /* ── Grid layout ── */
  .pdf-grid-4 { display: grid; grid-template-columns: repeat(4,1fr); gap: 12px; margin-bottom: 24px; }
  .pdf-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 28px; }
  .pdf-grid-2c { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 28px; }
  /* ── Stat Card ── */
  .pdf-stat-card {
    border-radius: 16px; padding: 14px; text-align: center;
    border: 1px solid #f3f4f6;
  }
  .pdf-stat-label { font-size: 9px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 6px; }
  .pdf-stat-value { font-size: 22px; font-weight: 900; }
  .pdf-stat-sub   { font-size: 9px; font-weight: 700; margin-top: 4px; }
  .card-blue   { background: #eff6ff; border-color: #dbeafe; }
  .card-green  { background: #f0fdf4; border-color: #bbf7d0; }
  .card-yellow { background: #fefce8; border-color: #fef08a; }
  .card-purple { background: #faf5ff; border-color: #e9d5ff; }
  .card-orange { background: #fff7ed; border-color: #fed7aa; }
  .card-indigo { background: #eef2ff; border-color: #c7d2fe; }
  .card-rose   { background: #fff1f2; border-color: #fecdd3; }
  .card-teal   { background: #f0fdfa; border-color: #99f6e4; }
  .card-gray   { background: #f9fafb; border-color: #f3f4f6; }
  .text-blue   { color: #1d4ed8; }
  .text-green  { color: #16a34a; }
  .text-yellow { color: #a16207; }
  .text-purple { color: #7c3aed; }
  .text-orange { color: #c2410c; }
  .text-indigo { color: #4338ca; }
  .text-rose   { color: #be123c; }
  .text-teal   { color: #0f766e; }
  .text-gray   { color: #6b7280; }
  /* ── Table ── */
  .pdf-table { width: 100%; border-collapse: collapse; font-size: 12px; }
  .pdf-table th {
    padding: 10px 8px; background: #f9fafb;
    font-weight: 900; color: #6b7280;
    font-size: 9px; text-transform: uppercase; letter-spacing: 0.08em;
    border-bottom: 1px solid #e5e7eb;
  }
  .pdf-table th.text-right { text-align: right; }
  .pdf-table th.text-center { text-align: center; }
  .pdf-table td {
    padding: 10px 8px; border-bottom: 1px solid #f3f4f6;
    vertical-align: middle;
  }
  .pdf-table td.text-center { text-align: center; }
  .pdf-table td.text-right  { text-align: right; }
  .pdf-table tr:hover td { background: #f0fdf4; }
  .pdf-table .row-top1 td { background: #fef9c3; }
  .pdf-table .row-top2 td { background: #f9fafb; }
  .pdf-table .row-top3 td { background: #fff7ed; }
  .pdf-table .total-row td { background: #16a34a; color: #fff; font-weight: 900; }
  .pdf-rank-badge {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 8px;
    font-size: 11px; font-weight: 900;
  }
  .rank-1 { background: #fbbf24; color: #fff; }
  .rank-2 { background: #9ca3af; color: #fff; }
  .rank-3 { background: #f97316; color: #fff; }
  .rank-n { background: #f3f4f6; color: #9ca3af; }
  /* ── Score pill ── */
  .pdf-pill {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 4px 10px; border-radius: 20px;
    font-size: 10px; font-weight: 900;
  }
  .pill-green  { background: #dcfce7; color: #166534; }
  .pill-blue   { background: #dbeafe; color: #1e40af; }
  .pill-yellow { background: #fef9c3; color: #713f12; }
  .pill-orange { background: #ffedd5; color: #7c2d12; }
  .pill-red    { background: #fee2e2; color: #7f1d1d; }
  /* ── Attendance ── */
  .pdf-att-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 16px; margin-bottom: 20px; }
  .pdf-att-box {
    border-radius: 16px; padding: 14px; text-align: center;
    border: 1px solid #e5e7eb;
  }
  .att-present { background: #f0fdf4; border-color: #bbf7d0; }
  .att-absent  { background: #fef2f2; border-color: #fecaca; }
  .att-late    { background: #fefce8; border-color: #fef08a; }
  .pdf-att-num   { font-size: 32px; font-weight: 900; }
  .pdf-att-label { font-size: 9px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.12em; margin-top: 4px; }
  .att-present .pdf-att-num   { color: #16a34a; }
  .att-present .pdf-att-label { color: #16a34a; }
  .att-absent  .pdf-att-num   { color: #dc2626; }
  .att-absent  .pdf-att-label { color: #dc2626; }
  .att-late    .pdf-att-num   { color: #ca8a04; }
  .att-late    .pdf-att-label { color: #ca8a04; }
  /* ── Progress bar ── */
  .pdf-progress-wrap { margin-top: 14px; }
  .pdf-progress-meta { display: flex; justify-content: space-between; font-size: 9px; font-weight: 900; color: #9ca3af; text-transform: uppercase; margin-bottom: 6px; }
  .pdf-progress-track { height: 10px; background: #f3f4f6; border-radius: 99px; overflow: hidden; display: flex; }
  .pdf-progress-bar   { height: 100%; border-radius: 99px; }
  .bar-green  { background: #22c55e; }
  .bar-yellow { background: #facc15; }
  /* ── Compare bar ── */
  .pdf-compare-row {
    background: #f9fafb; border-radius: 20px; padding: 18px 20px;
    margin-bottom: 10px; display: flex; align-items: center; gap: 16px;
  }
  .pdf-compare-row:hover { background: #f0fdf4; }
  .pdf-compare-rank {
    width: 48px; height: 48px; border-radius: 16px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; font-weight: 900;
  }
  .crank-1 { background: #16a34a; color: #fff; }
  .crank-n { background: #fff; border: 1px solid #e5e7eb; color: #9ca3af; }
  .pdf-compare-info { flex: 1; }
  .pdf-compare-name { font-size: 18px; font-weight: 900; color: #111827; margin-bottom: 6px; }
  .pdf-compare-sub { font-size: 10px; font-weight: 900; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.08em; }
  .pdf-compare-pct { font-size: 28px; font-weight: 900; color: #111827; text-align: left; }
  .pdf-compare-bar-wrap { margin-top: 8px; height: 8px; background: #e5e7eb; border-radius: 99px; overflow: hidden; }
  /* ── Monitoring table ── */
  .pdf-mon-table { width: 100%; border-collapse: collapse; font-size: 9px; }
  .pdf-mon-table th, .pdf-mon-table td {
    border: 1px solid #e5e7eb; padding: 4px 6px; text-align: center;
  }
  .pdf-mon-table th { background: #f9fafb; font-weight: 900; color: #6b7280; }
  .pdf-mon-table .name-cell { text-align: right; font-weight: 900; min-width: 120px; }
  .pdf-mon-table .date-header { background: #f3f4f6; font-weight: 900; border-left: 2px solid #d1d5db; }
  .mon-absent { background: #fef2f2; color: #dc2626; font-weight: 900; }
  .mon-present { color: #16a34a; font-weight: 900; }
  .mon-wrong { color: #dc2626; }
  .mon-ok { color: #16a34a; }
  /* ── Footer ── */
  .pdf-footer {
    margin-top: 24px; padding-top: 14px; border-top: 1px solid #f3f4f6;
    text-align: center; font-size: 9px; font-weight: 900;
    color: #d1d5db; text-transform: uppercase; letter-spacing: 0.15em;
  }
  /* ── Health ── */
  .pdf-health-item {
    background: #fef2f2; border: 1px solid #fecaca;
    border-radius: 12px; padding: 10px 14px; margin-bottom: 8px;
  }
  .pdf-health-name { font-size: 12px; font-weight: 900; color: #111827; margin-bottom: 4px; }
  .pdf-health-badge { font-size: 9px; font-weight: 900; padding: 2px 8px; border-radius: 20px; }
  .sev-high   { background: #fee2e2; color: #7f1d1d; }
  .sev-medium { background: #ffedd5; color: #7c2d12; }
  .sev-low    { background: #fef9c3; color: #713f12; }
  .pdf-healthy {
    background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px;
    padding: 20px; text-align: center;
    font-size: 12px; font-weight: 900; color: #166534;
  }
  /* ── Letter grade colors ── */
  .grade-ممتاز    { background: #dcfce7; color: #166534; }
  .grade-جيد\\ جداً { background: #dbeafe; color: #1e40af; }
  .grade-جيد      { background: #fef9c3; color: #713f12; }
  .grade-مقبول    { background: #ffedd5; color: #7c2d12; }
  .grade-ضعيف     { background: #fee2e2; color: #7f1d1d; }
  /* ── Page break control ── */
  table, .pdf-compare-row, .pdf-stat-card, .pdf-att-box,
  .pdf-health-item, .pdf-student-hero, .pdf-score-box {
    page-break-inside: avoid;
    break-inside: avoid;
  }
  .pdf-section-title { page-break-after: avoid; break-after: avoid; }
  tr { page-break-inside: avoid; break-inside: avoid; }
`;

// ============================================================
// CORE HELPERS
// ============================================================

/** Builds the school header block (same as getReportHeaderHTML but inline) */
function _pdfHeader(reportTitle) {
  const schoolName = (currentSchool && currentSchool.name) ? esc(currentSchool.name) : 'مدرستنا الذكية';
  const logoUrl = (currentSchool && currentSchool.logo) ? currentSchool.logo : '';
  const today = new Date().toLocaleDateString('ar-SA');
  return `
    <div class="pdf-header">
      <div class="pdf-header-col">
        <div class="pdf-header-ministry">
          المملكة العربية السعودية<br>وزارة التعليم<br>إدارة التربية والتعليم
        </div>
      </div>
      <div class="pdf-header-col pdf-header-center">
        <div style="font-size:28px;margin-bottom:6px;">🏃</div>
        <div class="school-name">${schoolName}</div>
        <div class="report-badge">${esc(reportTitle)}</div>
      </div>
      <div class="pdf-header-col pdf-header-logo">
        ${logoUrl
      ? `<img src="${logoUrl}" alt="school logo">`
      : `<div class="logo-placeholder">ختم / شعار المدرسة</div>`}
      </div>
    </div>
    <div class="pdf-header-meta">
      <span>التاريخ: ${today}</span>
      <span>PE Smart School System</span>
    </div>`;
}

/** Wraps raw HTML in a full "PDF page" div with embedded styles */
function _wrapPdfPage(bodyHtml) {
  return `<div id="pdf-container" lang="ar" dir="rtl"><style>${PDF_STYLES}</style><div class="pdf-page">${bodyHtml}</div></div>`;
}

/** Letter→pill class mapping */
function _letterClass(letter) {
  if (letter === 'ممتاز') return 'pill-green';
  if (letter === 'جيد جداً') return 'pill-blue';
  if (letter === 'جيد') return 'pill-yellow';
  if (letter === 'مقبول') return 'pill-orange';
  return 'pill-red';
}

/** Score % → colour class for the big number box */
function _scoreClass(pct) {
  if (pct >= 90) return 'pdf-score-green';
  if (pct >= 80) return 'pdf-score-blue';
  if (pct >= 70) return 'pdf-score-yellow';
  return 'pdf-score-red';
}

// ============================================================
// 1. STUDENT REPORT
// ============================================================
function buildStudentPdfHtml(d) {
  const s = d.student;
  const m = d.latestMeasurement || d.measurement || null;
  const h = d.health || d.healthConditions || [];
  const att = d.attendance;
  const attTotal = (att.present + att.absent + att.late) || 1;
  const attPct = Math.round(att.present / attTotal * 100);

  // Grading summary section (optional)
  let gradingHtml = '';
  if (d.grading_summary) {
    const gs = d.grading_summary;
    gradingHtml = `
        <div style="margin-bottom:24px;">
          <div class="pdf-section-title"><div class="pdf-section-icon">📝</div> التقييم الشامل والتقدير النهائي</div>
          <div class="pdf-grid-4" style="grid-template-columns:repeat(4,1fr);margin-bottom:12px;">
            <div class="pdf-stat-card card-blue">
              <div class="pdf-stat-label text-blue">الحضور والالتزام</div>
              <div class="pdf-stat-value text-blue">${gs.attendance_pct}%</div>
              <div class="pdf-stat-sub text-blue">الوزن: ${gs.weights.attendance_pct}%</div>
            </div>
            <div class="pdf-stat-card card-green">
              <div class="pdf-stat-label text-green">الزي الرياضي</div>
              <div class="pdf-stat-value text-green">${gs.uniform_pct}%</div>
              <div class="pdf-stat-sub text-green">الوزن: ${gs.weights.uniform_pct}%</div>
            </div>
            <div class="pdf-stat-card card-yellow">
              <div class="pdf-stat-label text-yellow">السلوك والمهارات</div>
              <div class="pdf-stat-value text-yellow">${gs.behavior_skills_pct}%</div>
              <div class="pdf-stat-sub text-yellow">الوزن: ${gs.weights.behavior_skills_pct}%</div>
            </div>
            <div class="pdf-stat-card card-purple">
              <div class="pdf-stat-label text-purple">اللياقة البدنية</div>
              <div class="pdf-stat-value text-purple">${gs.fitness_pct}%</div>
              <div class="pdf-stat-sub text-purple">الوزن: ${gs.weights.fitness_pct}%</div>
            </div>
          </div>
          <div class="pdf-grid-4" style="grid-template-columns:repeat(4,1fr);">
            <div class="pdf-stat-card card-orange">
              <div class="pdf-stat-label text-orange">المشاركة</div>
              <div class="pdf-stat-value text-orange">${gs.participation_pct}%</div>
              <div class="pdf-stat-sub text-orange">الوزن: ${gs.weights.participation_pct}%</div>
            </div>
            <div class="pdf-stat-card card-indigo">
              <div class="pdf-stat-label text-indigo">الاختبارات القصيرة</div>
              <div class="pdf-stat-value text-indigo">${gs.quiz_score}<span style="font-size:11px;opacity:.5">/${gs.quiz_max}</span></div>
              <div class="pdf-stat-sub text-indigo">الوزن: ${gs.weights.quiz_pct}%</div>
            </div>
            <div class="pdf-stat-card card-rose">
              <div class="pdf-stat-label text-rose">المشاريع والأبحاث</div>
              <div class="pdf-stat-value text-rose">${gs.project_score}<span style="font-size:11px;opacity:.5">/${gs.project_max}</span></div>
              <div class="pdf-stat-sub text-rose">الوزن: ${gs.weights.project_pct}%</div>
            </div>
            <div class="pdf-stat-card card-teal">
              <div class="pdf-stat-label text-teal">الاختبار النهائي</div>
              <div class="pdf-stat-value text-teal">${gs.final_exam_score}<span style="font-size:11px;opacity:.5">/${gs.final_exam_max}</span></div>
              <div class="pdf-stat-sub text-teal">الوزن: ${gs.weights.final_exam_pct}%</div>
            </div>
          </div>
        </div>`;
  }

  const body = `
    ${_pdfHeader('تقرير اللياقة البدنية الشامل')}

    <!-- Student hero -->
    <div class="pdf-student-hero">
      <div class="pdf-student-info">
        <div class="pdf-avatar">
          ${s.photo_url ? `<img src="${s.photo_url}" alt="">` : '👤'}
        </div>
        <div>
          <div class="pdf-student-name">${esc(s.name)}</div>
          <div class="pdf-student-class">${esc(s.full_class_name)}</div>
          <div class="pdf-badges">
            <span class="pdf-badge pdf-badge-gray">🆔 ${esc(s.student_number)}</span>
            ${s.age ? `<span class="pdf-badge pdf-badge-green">🎂 ${s.age} سنة</span>` : ''}
            ${s.blood_type ? `<span class="pdf-badge pdf-badge-red">🩸 ${s.blood_type}</span>` : ''}
          </div>
        </div>
      </div>
      <div class="pdf-score-box">
        <div class="pdf-score-number ${_scoreClass(d.percentage)}">${d.percentage}%</div>
        <div class="pdf-score-label">التقييم العام للأداء</div>
        ${d.grading_summary ? `<div style="margin-top:10px;background:#fff;color:#111827;padding:4px 14px;border-radius:10px;font-size:14px;font-weight:900;">${d.grading_summary.letter}</div>` : ''}
      </div>
    </div>

    ${gradingHtml}

    <!-- Measurements & Health -->
    <div class="pdf-grid-2c">
      <div>
        <div class="pdf-section-title"><div class="pdf-section-icon">📏</div> القياسات الجسمية والنمو</div>
        ${m ? `
        <div class="pdf-grid-4" style="grid-template-columns:repeat(4,1fr);">
          <div class="pdf-stat-card card-gray">
            <div class="pdf-stat-label text-gray">الطول</div>
            <div class="pdf-stat-value">${m.height_cm} <span style="font-size:10px;color:#9ca3af;">سم</span></div>
          </div>
          <div class="pdf-stat-card card-gray">
            <div class="pdf-stat-label text-gray">الوزن</div>
            <div class="pdf-stat-value">${m.weight_kg} <span style="font-size:10px;color:#9ca3af;">كجم</span></div>
          </div>
          <div class="pdf-stat-card card-gray">
            <div class="pdf-stat-label text-gray">BMI</div>
            <div class="pdf-stat-value">${m.bmi}</div>
            <div class="pdf-stat-sub">${(window.BMI_AR && window.BMI_AR[m.bmi_category]) || ''}</div>
          </div>
          <div class="pdf-stat-card card-gray">
            <div class="pdf-stat-label text-gray">النبض</div>
            <div class="pdf-stat-value">${m.resting_heart_rate || '-'} <span style="font-size:10px;color:#9ca3af;">ن/د</span></div>
          </div>
        </div>` : `<div style="background:#f9fafb;border:2px dashed #e5e7eb;border-radius:16px;padding:32px;text-align:center;color:#9ca3af;font-weight:900;">لا يوجد بيانات قياسات</div>`}
      </div>
      <div>
        <div class="pdf-section-title"><div class="pdf-section-icon">🏥</div> الحالة الصحية</div>
        ${h.length > 0 ? h.map(c => `
          <div class="pdf-health-item">
            <div class="pdf-health-name">${esc(c.condition_name)}</div>
            <span class="pdf-health-badge sev-${c.severity}">${(window.SEVERITY_AR && window.SEVERITY_AR[c.severity]) || c.severity}</span>
          </div>`).join('')
      : `<div class="pdf-healthy">✨ الحالة الصحية مستقرة<br><span style="font-size:10px;font-weight:700;opacity:.7;">لم يتم تسجيل أي عوارض طبية</span></div>`}
      </div>
    </div>

    <!-- Fitness & Attendance -->
    <div class="pdf-grid-2">
      <div>
        <div class="pdf-section-title"><div class="pdf-section-icon">💪</div> نتائج اختبارات اللياقة</div>
        <table class="pdf-table">
          <thead>
            <tr>
              <th class="text-right">الاختبار البدني</th>
              <th class="text-center">النتيجة</th>
              <th class="text-center">الدرجة</th>
            </tr>
          </thead>
          <tbody>
            ${d.fitness.map(f => `
            <tr>
              <td class="text-right" style="font-weight:900;">${esc(f.test_name)}</td>
              <td class="text-center">${f.value !== null ? f.value + ' ' + f.unit : '-'}</td>
              <td class="text-center">
                <div style="display:inline-flex;align-items:center;justify-content:center;padding:4px 10px;border-radius:10px;background:#f0fdf4;color:#166534;font-weight:900;min-width:40px;">
                  ${f.score !== null ? f.score + '/' + f.max_score : '-'}
                </div>
              </td>
            </tr>`).join('')}
            <tr class="total-row">
              <td colspan="2" style="padding:14px 10px;border-radius:0 12px 12px 0;">إجمالي نقاط اللياقة البدنية</td>
              <td class="text-center" style="font-size:20px;padding:14px 10px;border-radius:12px 0 0 12px;">${d.totalScore}/${d.totalMax}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div>
        <div class="pdf-section-title"><div class="pdf-section-icon">📋</div> إحصائيات الحضور والغياب</div>
        <div class="pdf-att-grid">
          <div class="pdf-att-box att-present">
            <div class="pdf-att-num">${att.present}</div>
            <div class="pdf-att-label">حضور</div>
          </div>
          <div class="pdf-att-box att-absent">
            <div class="pdf-att-num">${att.absent}</div>
            <div class="pdf-att-label">غياب</div>
          </div>
          <div class="pdf-att-box att-late">
            <div class="pdf-att-num">${att.late}</div>
            <div class="pdf-att-label">تأخر</div>
          </div>
        </div>
        <div class="pdf-progress-wrap">
          <div class="pdf-progress-meta">
            <span>نسبة الانتظام الميداني</span>
            <span style="color:#16a34a;">${attPct}%</span>
          </div>
          <div class="pdf-progress-track">
            <div class="pdf-progress-bar bar-green" style="width:${attPct}%;"></div>
            <div class="pdf-progress-bar bar-yellow" style="width:${Math.round(att.late / attTotal * 100)}%;"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="pdf-footer">PE Smart School System • ${new Date().toLocaleDateString('ar-SA')} • تقرير أداء ذكي</div>`;

  return _wrapPdfPage(body);
}

// ============================================================
// 2. CLASS REPORT
// ============================================================
function buildClassPdfHtml(d) {
  const rows = d.students.map((s, i) => {
    const rankClass = i === 0 ? 'rank-1' : i === 1 ? 'rank-2' : i === 2 ? 'rank-3' : 'rank-n';
    const trClass = i === 0 ? 'row-top1' : i === 1 ? 'row-top2' : i === 2 ? 'row-top3' : '';
    return `
        <tr class="${trClass}">
          <td class="text-center"><span class="pdf-rank-badge ${rankClass}">${i + 1}</span></td>
          <td style="font-weight:900;font-size:13px;">${esc(s.name)}</td>
          <td class="text-center">${s.total_score}/${s.total_max || 0}</td>
          <td class="text-center" style="font-weight:900;">${s.percentage}%</td>
          <td class="text-center"><span class="pdf-pill ${_letterClass(s.letter)}">${s.letter || '-'}</span></td>
          <td class="text-center">${s.latest_bmi || '-'}</td>
          <td class="text-center">${s.health_alerts > 0 ? `⚠️ ${s.health_alerts}` : '✅'}</td>
          <td class="text-center" style="font-size:10px;">P:${s.present_count} / A:${s.absent_count}</td>
        </tr>`;
  }).join('');

  const body = `
    ${_pdfHeader('تقرير تحليل أداء الفصل الدراسي')}

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;padding-bottom:16px;border-bottom:1px solid #f3f4f6;">
      <div>
        <div style="font-size:22px;font-weight:900;color:#111827;">${esc(d.class.full_name)}</div>
        <div style="color:#16a34a;font-weight:900;margin-top:4px;">● ${d.totalStudents} طالباً مسجلاً</div>
      </div>
      <div class="pdf-score-box">
        <div class="pdf-score-number pdf-score-green">${d.classAverage}%</div>
        <div class="pdf-score-label">متوسط أداء الفصل</div>
      </div>
    </div>

    <table class="pdf-table">
      <thead>
        <tr>
          <th class="text-center">الترتيب</th>
          <th class="text-right">اسم الطالب</th>
          <th class="text-center">النقاط</th>
          <th class="text-center">النسبة/%</th>
          <th class="text-center">التقدير</th>
          <th class="text-center">BMI</th>
          <th class="text-center">الصحة</th>
          <th class="text-center">حضور</th>
        </tr>
      </thead>
      <tbody>${rows}</tbody>
    </table>

    <div class="pdf-footer">نظام التحليل البدني والرياضي لعام ${new Date().getFullYear()}</div>`;

  return _wrapPdfPage(body);
}

// ============================================================
// 3. COMPARE REPORT
// ============================================================
function buildComparePdfHtml(classes) {
  const MAX_BAR = Math.max(...classes.map(c => c.percentage), 1);
  const rows = classes.map((c, i) => {
    const rankClass = i < 3 ? 'crank-1' : 'crank-n';
    const rankLabel = i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : (i + 1);
    const barW = Math.round(c.percentage / MAX_BAR * 100);
    const barColor = i === 0 ? '#eab308' : i === 1 ? '#9ca3af' : i === 2 ? '#f97316' : '#16a34a';
    return `
        <div class="pdf-compare-row">
          <div class="pdf-compare-rank ${rankClass}">${rankLabel}</div>
          <div class="pdf-compare-info">
            <div class="pdf-compare-name">${esc(c.class_name)}</div>
            <div class="pdf-compare-sub">${c.students_count} طالباً مسجلاً في هذا الفصل</div>
            <div class="pdf-compare-bar-wrap">
              <div style="height:100%;width:${barW}%;background:${barColor};border-radius:99px;"></div>
            </div>
          </div>
          <div class="pdf-compare-pct">${c.percentage}<span style="font-size:16px;color:#16a34a;">%</span></div>
        </div>`;
  }).join('');

  const body = `
    ${_pdfHeader('تحليل مقارنة الفصول')}

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
      <div>
        <div style="font-size:22px;font-weight:900;color:#111827;">⚖️ تحليل مقارنة الفصول</div>
        <div style="color:#9ca3af;font-weight:700;margin-top:4px;font-size:12px;">مؤشرات الأداء البدني لجميع الفصول التعليمية المشتركة</div>
      </div>
      <div style="background:#16a34a;padding:14px 24px;border-radius:20px;text-align:center;">
        <div style="font-size:9px;color:#bbf7d0;font-weight:900;text-transform:uppercase;letter-spacing:.1em;">إجمالي الفصول</div>
        <div style="font-size:32px;font-weight:900;color:#fff;">${classes.length}</div>
      </div>
    </div>

    ${rows}

    <div class="pdf-footer">PE Smart School Intelligence Report System</div>`;

  return _wrapPdfPage(body);
}

// ============================================================
// 4. GRADING REPORT
// ============================================================
function buildGradingPdfHtml(data) {
  const { weights, students, className, start, end } = data;

  const rows = students.map((s, i) => `
    <tr style="${i % 2 === 1 ? 'background:#f9fafb;' : ''}">
      <td class="text-center" style="color:#9ca3af;font-weight:700;">${i + 1}</td>
      <td style="font-weight:900;font-size:12px;">${esc(s.name)}</td>
      <td class="text-center">${s.attendance_score}</td>
      <td class="text-center">${s.uniform_score}</td>
      <td class="text-center">${s.behavior_skills_score}</td>
      <td class="text-center">${s.participation_score}</td>
      <td class="text-center">${s.fitness_score}</td>
      <td class="text-center">${s.quiz_score}</td>
      <td class="text-center">${s.project_score}</td>
      <td class="text-center">${s.final_exam_score}</td>
      <td class="text-center" style="font-size:16px;font-weight:900;color:#4338ca;background:#eef2ff;">${s.final_grade}</td>
      <td class="text-center"><span class="pdf-pill ${_letterClass(s.letter)}">${s.letter || '-'}</span></td>
    </tr>`).join('');

  const body = `
    ${_pdfHeader('كشف الدرجات النهائي للتربية البدنية')}

    <div style="margin-bottom:16px;">
      <div style="font-size:18px;font-weight:900;color:#111827;">📊 كشف الدرجات النهائي للتربية البدنية</div>
      <div style="color:#4338ca;font-weight:700;margin-top:4px;font-size:12px;">الفصل: ${esc(className)} | الفترة: ${start} إلى ${end}</div>
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;">
      <span class="pdf-pill pill-blue">حضور: ${weights.attendance_pct}%</span>
      <span class="pdf-pill pill-green">زي: ${weights.uniform_pct}%</span>
      <span class="pdf-pill pill-yellow">سلوك: ${weights.behavior_skills_pct}%</span>
      <span class="pdf-pill pill-orange">مشاركة: ${weights.participation_pct}%</span>
      <span class="pdf-pill" style="background:#f3e8ff;color:#6d28d9;">لياقة: ${weights.fitness_pct}%</span>
      <span class="pdf-pill" style="background:#e0e7ff;color:#3730a3;">اختبار: ${weights.quiz_pct}%</span>
      <span class="pdf-pill" style="background:#ccfbf1;color:#0f766e;">مجموع: ${weights.project_pct}%</span>
      <span class="pdf-pill pill-red">نهائي: ${weights.final_exam_pct}%</span>
    </div>

    <table class="pdf-table">
      <thead>
        <tr>
          <th>م</th>
          <th class="text-right">الطالب</th>
          <th>ح (${weights.attendance_pct})</th>
          <th>ز (${weights.uniform_pct})</th>
          <th>س (${weights.behavior_skills_pct})</th>
          <th>م (${weights.participation_pct})</th>
          <th>ل (${weights.fitness_pct})</th>
          <th>ق (${weights.quiz_pct})</th>
          <th>ج (${weights.project_pct})</th>
          <th>ن (${weights.final_exam_pct})</th>
          <th style="background:#eef2ff;color:#3730a3;">المجموع</th>
          <th>التقدير</th>
        </tr>
      </thead>
      <tbody>
        ${rows}
        ${students.length === 0 ? '<tr><td colspan="12" style="text-align:center;padding:24px;color:#9ca3af;font-weight:900;">لا توجد بيانات طلاب لهذا الفصل</td></tr>' : ''}
      </tbody>
    </table>

    <div class="pdf-footer">PE Smart System • ${new Date().toLocaleDateString('ar-SA')}</div>`;

  return _wrapPdfPage(body);
}

// ============================================================
// 5. MONITORING REPORT
// ============================================================
function buildMonitoringPdfHtml(d) {
  const students = d.students || [];
  const dates = d.dates || [];
  const matrix = d.matrix || {};
  const className = d.class.full_name;
  const attMap = { present: 'ح', absent: 'غ', late: 'م', excused: 'عذر' };

  let dateHeaders = '';
  let subHeaders = '';
  dates.forEach(dt => {
    dateHeaders += `<th colspan="5" class="date-header" style="font-size:9px;">${dt.replace(/-/g, '/')}</th>`;
    subHeaders += `
          <th style="font-size:8px;background:#f9fafb;">حضور</th>
          <th style="font-size:8px;background:#f9fafb;">ملابس</th>
          <th style="font-size:8px;background:#f9fafb;">مشاركة</th>
          <th style="font-size:8px;background:#f9fafb;">لياقة</th>
          <th style="font-size:8px;background:#f9fafb;border-left:2px solid #d1d5db;">سلوك</th>`;
  });

  const rows = students.map((s, i) => {
    let cells = '';
    dates.forEach(dt => {
      const m = (matrix[s.id] && matrix[s.id][dt]) ? matrix[s.id][dt] : null;
      if (m) {
        const uniIcon = m.uniform === 'full' ? '✓' : m.uniform === 'wrong' ? 'X' : '-';
        cells += `
                  <td class="${m.status === 'absent' ? 'mon-absent' : 'mon-present'}">${attMap[m.status] || '-'}</td>
                  <td class="${m.uniform === 'wrong' ? 'mon-wrong' : 'mon-ok'}">${uniIcon}</td>
                  <td>${m.participation || '-'}</td>
                  <td style="color:#2563eb;background:#eff6ff;">${m.fitness || '-'}</td>
                  <td style="color:#ca8a04;background:#f9fafb;border-left:2px solid #d1d5db;">${m.behavior || m.skills || '-'}</td>`;
      } else {
        cells += '<td>-</td><td>-</td><td>-</td><td>-</td><td style="border-left:2px solid #d1d5db;">-</td>';
      }
    });
    return `
        <tr style="${i % 2 === 1 ? 'background:#fafafa;' : ''}">
          <td style="text-align:center;color:#9ca3af;font-weight:700;">${i + 1}</td>
          <td class="name-cell">${esc(s.name)}</td>
          ${cells}
        </tr>`;
  }).join('');

  const body = `
    ${_pdfHeader('كشف متابعة فصل - سجل الأداء اليومي')}

    <div style="margin-bottom:14px;">
      <div style="font-size:18px;font-weight:900;color:#111827;">📋 كشف متابعة فصل (سجل الأداء اليومي)</div>
      <div style="color:#ea580c;font-weight:700;margin-top:4px;font-size:12px;">الفصل: ${esc(className)}</div>
    </div>

    <div style="overflow-x:auto;">
      <table class="pdf-mon-table">
        <thead>
          <tr>
            <th rowspan="2" style="min-width:30px;">م</th>
            <th rowspan="2" class="name-cell" style="min-width:130px;text-align:right;">اسم الطالب</th>
            ${dateHeaders}
          </tr>
          <tr>${subHeaders}</tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
    </div>

    <div class="pdf-footer">PE Smart School System • ${new Date().toLocaleDateString('ar-SA')}</div>`;

  return _wrapPdfPage(body);
}

// ============================================================
// CORE ENGINE: passes HTML string directly to html2pdf
// ============================================================

/** html2pdf options — A4 portrait, 10mm margins, windowWidth=794 (A4 at 96dpi) */
function _cleanPdfOptions(filename) {
  return {
    margin: 10,                            // 10mm all around
    filename: `${filename}.pdf`,
    image: { type: 'jpeg', quality: 0.97 },
    html2canvas: {
      scale: 2,
      useCORS: true,
      allowTaint: false,
      logging: false,
      backgroundColor: '#ffffff',
      windowWidth: 800,                    // Slightly larger than pdf-page to avoid clipping
      scrollX: 0,
      scrollY: 0
    },
    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
    pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
  };
}

/**
 * Master PDF action handler.
 * Passes the HTML string directly to html2pdf using the 'string' source type,
 * which avoids the blank-page problem caused by off-screen DOM elements.
 *
 * @param {string} htmlString  – output of one of the build*PdfHtml() functions
 * @param {string} filename    – PDF file name (without .pdf)
 * @param {'download'|'email'} action
 * @param {string} [email]     – required when action === 'email'
 */
async function _executePdfAction(htmlString, filename, action, email) {
  if (typeof html2pdf === 'undefined') {
    showToast('مكتبة PDF غير متوفرة، يرجى تحديث الصفحة.', 'error');
    return;
  }
  try {
    // Pass as string — html2pdf inserts it into a visible temporary element
    // internally, so html2canvas can render it correctly (no blank page).
    const worker = html2pdf().set(_cleanPdfOptions(filename)).from(htmlString, 'string');
    if (action === 'download') {
      showToast('جاري تحويل التقرير إلى PDF... ⏳', 'info');
      await worker.save();
      showToast('تم تحميل التقرير بنجاح! ✅', 'success');
    } else if (action === 'email') {
      showToast('جاري تجهيز التقرير كملف PDF... يرجى الانتظار ⏳', 'info');
      const pdfBase64 = await worker.outputPdf('datauristring');
      showToast('جاري الإرسال عبر البريد الإلكتروني... 📧', 'info');
      const r = await API.post('send_report_email', {
        email: email,
        pdfData: pdfBase64,
        title: filename
      });
      if (r && r.success) {
        showToast(r.message || 'تم إرسال التقرير بنجاح! ✅', 'success');
      } else {
        showToast(r?.error || 'حدث خطأ أثناء إرسال البريد', 'error');
      }
    }
  } catch (e) {
    console.error('PDF Action Error:', e);
    showToast('فشل في توليد الـ PDF: ' + (e?.message || ''), 'error');
  } finally {
    if (el && el.parentNode) el.parentNode.removeChild(el);
  }
}

// ============================================================
// PUBLIC: PER-REPORT DOWNLOAD + EMAIL ENTRY POINTS
// ============================================================

/**
 * Called by download buttons in each report.
 * @param {'student'|'class'|'compare'|'grading'|'monitoring'} type
 * @param {*} data   – the parsed API data object for that report
 * @param {string} filename
 */
async function downloadReportPdf(type, data, filename) {
  const html = _buildReportHtml(type, data);
  if (!html) return;
  await _executePdfAction(html, filename, 'download');
}

/**
 * Called by email buttons in each report (opens modal first).
 */
async function emailReportPdf(type, data, filename) {
  const html = _buildReportHtml(type, data);
  if (!html) return;

  showModal(`
        <div class="p-8 md:p-10">
            <div class="flex items-center gap-4 mb-8">
                <div class="w-16 h-16 rounded-[1.5rem] bg-indigo-50 text-indigo-600 flex items-center justify-center text-3xl">📧</div>
                <div>
                    <h3 class="text-2xl font-black text-gray-800">إرسال التقرير عبر البريد</h3>
                    <p class="text-gray-400 font-bold text-sm">${esc(filename)}</p>
                </div>
            </div>
            <div class="space-y-2 mb-8">
                <label class="block text-[10px] font-black text-gray-400 uppercase tracking-widest">البريد الإلكتروني للمستلم</label>
                <input type="email" id="pdfEmailInput"
                    class="w-full px-6 py-4 bg-gray-50 border-2 border-transparent rounded-2xl focus:bg-white focus:border-indigo-500 focus:outline-none transition-all font-bold text-gray-700 text-sm"
                    placeholder="example@school.edu.sa"
                    onkeydown="if(event.key==='Enter') _submitEmailPdf()">
            </div>
            <div class="flex gap-4">
                <button onclick="_submitEmailPdf()" class="flex-1 bg-indigo-600 text-white py-4 rounded-2xl font-black hover:bg-indigo-700 transition flex items-center justify-center gap-3">
                    <span class="text-xl">📧</span> إرسال PDF
                </button>
                <button onclick="closeModal()" class="w-32 bg-gray-100 text-gray-500 py-4 rounded-2xl font-black hover:bg-gray-200 transition">إلغاء</button>
            </div>
        </div>
    `);

  // Store pending PDF data for the submit handler
  window._pendingPdfEmail = { html, filename };
  setTimeout(() => { const inp = document.getElementById('pdfEmailInput'); if (inp) inp.focus(); }, 100);
}

/** Called by the email modal submit button */
async function _submitEmailPdf() {
  const inp = document.getElementById('pdfEmailInput');
  const email = inp ? inp.value.trim() : '';
  if (!email || !email.includes('@') || !email.includes('.')) {
    showToast('الرجاء إدخال بريد إلكتروني صالح', 'error');
    return;
  }
  const { html, filename } = window._pendingPdfEmail || {};
  if (!html) { closeModal(); return; }
  closeModal();
  await _executePdfAction(html, filename, 'email', email);
  window._pendingPdfEmail = null;
}

/** Internal: routes type → builder function */
function _buildReportHtml(type, data) {
  switch (type) {
    case 'student': return buildStudentPdfHtml(data);
    case 'class': return buildClassPdfHtml(data);
    case 'compare': return buildComparePdfHtml(data);    // data = classes array
    case 'grading': return buildGradingPdfHtml(data);
    case 'monitoring': return buildMonitoringPdfHtml(data);
    default:
      showToast('نوع التقرير غير معروف', 'error');
      return null;
  }
}
