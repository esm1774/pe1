# خطة مراجعة وتحسين نظام PE Smart School

تتضمن هذه الخطة خطوات معالجة الثغرات الأمنية، تحسين الأداء، وإضافة خصائص جديدة لرفع كفاءة المنصة.

## عناصر تتطلب مراجعة المستخدم

> [!WARNING]
> تم اكتشاف تسريب لرمز التحقق (OTP) في استجابة الـ API عند تفعيل وضع التطوير `DEBUG_MODE`. سنقوم بإصلاح هذا فوراً.

> [!IMPORTANT]
> نقترح تفعيل خاصية "تغيير كلمة المرور عند أول دخول" للطلاب نظراً لأن كلمة المرور الافتراضية هي رقم الطالب.

## التغييرات المقترحة

### Proposed Changes

### Core Logic Rewrite (`matches.php`)

#### [MODIFY] `matches.php`](file:///Applications/XAMPP/xamppfiles/htdocs/pe1/modules/tournaments/core/matches.php)

**Problem with current logic:** The current mathematical algorithm forces custom rules for Loser Bracket rounds when the number of teams isn't a power of 2 (e.g., 6 teams). This breaks standard tournament topology visually shown in the image provided (where 8-team brackets have a very specific, symmetrical flow of losers dropping into the LB).

**The Solution:** The "Ghost Bracket" approach.
Instead of building a partial 6-team bracket, we will **always build a full power-of-2 bracket** (e.g., 8 teams). The missing 2 teams will be filled with "Ghost Teams" (`id < 0`). 

1. **Topology:** We generate a complete 8-team Double Elimination bracket exactly as the standard rule dictates (identical to the image provided).
2. **Seeding:** Teams are seeded 1 to 6. Ghost teams take seeds 7 and 8.
3. **Ghost Logic (BYEs):**
   - If a real team plays a Ghost team, the real team **automatically wins** (becomes a BYE match).
   - The Ghost team "loses" and correctly drops down into the exact slot in the Losers Bracket required by standard topology.
   - If two Ghost teams meet in the Losers Bracket, one Ghost team automatically "wins" to advance the empty slot.
4. **Backend `saveMatchResult`:** The auto-advance logic for ghosts will happen perfectly during bracket generation, so the user only ever interacts with matches involving at least one real team.

### Frontend UI Updates (`js/tournaments.js`)

#### [MODIFY] `tournaments.js`](file:///Applications/XAMPP/xamppfiles/htdocs/pe1/js/tournaments.js)
Update the match rendering logic so that:
- Any match involving a "Ghost" team (`team ID < 0`) is handled correctly. If the match is completely Ghost vs Ghost, it is completely hidden from the UI.
- Matches with 1 Real Team vs 1 Ghost Team are displayed as "تأهل مباشر" (BYE) for the real team, matching current expectations.مساحة التخزين، واتصال البريد.
- **تصدير التقارير الشاملة**: ميزة استخراج تقرير أداء الطالب بصيغة PDF احترافية للإرسال عبر الواتساب أو الإيميل.
- **سياسة كلمات المرور**: إجبار المستخدمين على تغيير كلمة المرور الافتراضية عند أول دخول.

## خطة التحقق (Verification Plan)

### الاختبارات المؤتمتة
- تشغيل اختبارات التحقق من الـ API للتأكد من عدم وجود تسريب لبيانات الـ OTP.
- اختبار محاولات الدخول الخاطئة للتأكد من عمل نظام الحماية من التخمين.

### التحقق اليدوي
1. محاولة تسجيل الدخول بكلمة مرور خاطئة 5 مرات وملاحظة حظر الحساب مؤقتاً.
2. استيراد ملف طلاب يحتوي على أخطاء للتحقق من دقة معالجة الأخطاء.
3. التأكد من سرعة تحميل صفحة التحليلات بعد التحسين.
