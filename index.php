<?php
require_once 'config.php';
$db = getDB();

// 1. Fetch Blog Posts directly from WordPress DB (Refactored for Reliability)
$recentPosts = fetchRecentBlogPosts(3);

try {
    // 2. Fetch Testimonials from local database
    $stmtTest = $db->query("SELECT * FROM testimonials WHERE active = 1 ORDER BY sort_order ASC, id DESC LIMIT 6");
    $testimonials = $stmtTest->fetchAll();
} catch (Exception $e) {
    // Silent fail
}

// Fetch active subscription plans
$stmt = $db->query("SELECT * FROM plans WHERE active = 1 ORDER BY sort_order ASC");
$plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

function esc($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PE Smart School | منصة التحول الرقمي الشاملة للتربية البدنية</title>

    <!-- SEO & Identity -->
    <meta name="description"
        content="المنصة السحابية الأولى (SaaS) لإدارة التربية البدنية، البطولات المعقدة، التحليلات المتقدمة للصحة واللياقة، وإصدار الشهادات للمدارس والمجمعات التعليمية.">
    <meta name="theme-color" content="#10b981">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800;900&display=swap"
        rel="stylesheet">

    <!-- Framework -->
    <!-- Tailwind CSS (Production Optimization) -->
    <link rel="stylesheet" href="assets/css/main.css">

    <!-- Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body {
            font-family: 'Cairo', sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        /* Glassmorphism Classes */
        .glass-panel {
            background: rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.4);
        }

        .glass-dark {
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .emerald-gradient {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
        }

        .text-gradient {
            background: linear-gradient(135deg, #047857 0%, #34d399 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .text-gradient-light {
            background: linear-gradient(135deg, #a7f3d0 0%, #34d399 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        /* Interactive Elements */
        .hover-lift {
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), box-shadow 0.4s ease;
        }

        .hover-lift:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px -10px rgba(16, 185, 129, 0.15);
        }

        /* Fancy Background Patterns */
        .hero-bg {
            background-color: #f8fafc;
            background-image: radial-gradient(at 0% 0%, hsla(158, 100%, 74%, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, hsla(160, 100%, 50%, 0.1) 0px, transparent 50%);
        }

        .dot-pattern {
            background-image: radial-gradient(#10b981 1px, transparent 1px);
            background-size: 24px 24px;
            opacity: 0.05;
        }

        /* Modern Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #10b981;
        }

        /* Reveal Animation Utility */
        .reveal {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s ease-out;
        }

        .reveal.active {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>

<body class="bg-slate-50 text-slate-800 overflow-x-hidden selection:bg-emerald-200 selection:text-emerald-900">

    <!-- Navbar -->
    <header class="fixed top-0 inset-x-0 z-50 px-4 py-4 transition-all duration-300" id="navbar">
        <nav
            class="max-w-7xl mx-auto glass-panel rounded-full px-6 py-3 flex items-center justify-between shadow-lg shadow-emerald-900/5">
            <div class="flex items-center gap-3">
                <div
                    class="w-10 h-10 emerald-gradient rounded-full flex items-center justify-center text-white shadow-md">
                    <i data-lucide="activity" class="w-5 h-5"></i>
                </div>
                <div class="flex flex-col">
                    <span class="text-xl font-black text-slate-800 leading-none">PE Smart</span>
                    <span class="text-[9px] font-black tracking-widest text-emerald-600 uppercase">SaaS Platform</span>
                </div>
            </div>

            <div class="hidden lg:flex items-center gap-8 text-sm font-bold text-slate-600">
                <a href="#features" class="hover:text-emerald-600 transition-colors">المقومات الشاملة</a>
                <a href="#analytics" class="hover:text-emerald-600 transition-colors">التحليلات والصحة</a>
                <a href="#tournaments" class="hover:text-emerald-600 transition-colors">محرك البطولات</a>
                <a href="#blog" class="hover:text-emerald-600 transition-colors">المدونة والدروس</a>
                <a href="#faq" class="hover:text-emerald-600 transition-colors">أسئلة شائعة</a>
                <a href="#pricing" class="hover:text-emerald-600 transition-colors">رسوم الاستئجار</a>
            </div>

            <div class="flex items-center gap-3">
                <a href="app.html"
                    class="hidden sm:block text-sm font-black text-slate-600 px-5 py-2 hover:bg-slate-100 rounded-full transition-all">تسجيل
                    الدخول</a>
                <a href="register.html"
                    class="px-6 py-2.5 text-sm font-black text-white emerald-gradient rounded-full shadow-lg shadow-emerald-500/30 transform transition-transform hover:scale-105">تأسيس
                    مدرستك</a>
                
                <!-- Mobile Toggle -->
                <button onclick="toggleMobileMenu()" class="lg:hidden w-10 h-10 flex items-center justify-center text-slate-600 hover:bg-slate-100 rounded-full transition-all">
                    <i data-lucide="menu" class="w-6 h-6"></i>
                </button>
            </div>
        </nav>
    </header>

    <!-- Mobile Menu Overlay -->
    <div id="mobileMenu" class="fixed inset-0 z-[100] hidden">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-md" onclick="toggleMobileMenu()"></div>
        <div id="mobileMenuPanel" class="absolute right-0 top-0 bottom-0 w-[280px] bg-white shadow-2xl flex flex-col p-8 transform transition-transform duration-500 translate-x-full">
            <div class="flex items-center justify-between mb-10">
                <span class="text-xl font-black text-emerald-600">القائمة</span>
                <button onclick="toggleMobileMenu()" class="w-10 h-10 flex items-center justify-center bg-slate-50 text-slate-400 rounded-xl hover:text-rose-500 transition-all">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            <nav class="flex flex-col gap-6 font-bold text-slate-600 text-right">
                <a href="#features" onclick="toggleMobileMenu()" class="hover:text-emerald-600 transition-colors">المقومات الشاملة</a>
                <a href="#analytics" onclick="toggleMobileMenu()" class="hover:text-emerald-600 transition-colors">التحليلات والصحة</a>
                <a href="#tournaments" onclick="toggleMobileMenu()" class="hover:text-emerald-600 transition-colors">محرك البطولات</a>
                <a href="#blog" onclick="toggleMobileMenu()" class="hover:text-emerald-600 transition-colors">المدونة والدروس</a>
                <a href="#faq" onclick="toggleMobileMenu()" class="hover:text-emerald-600 transition-colors">أسئلة شائعة</a>
                <a href="#pricing" onclick="toggleMobileMenu()" class="hover:text-emerald-600 transition-colors">رسوم الاستئجار</a>
                <hr class="border-slate-100 my-4">
                <a href="app.html" class="flex items-center justify-end gap-3 text-emerald-600">
                    تسجيل الدخول <i data-lucide="log-in" class="w-5 h-5"></i>
                </a>
                <a href="register.html" class="emerald-gradient text-white py-3 px-6 rounded-2xl text-center shadow-lg shadow-emerald-500/20 mt-4">
                    تأسيس مدرستك
                </a>
            </nav>
        </div>
    </div>

    <!-- Hero Section -->
    <section class="relative pt-40 pb-32 px-4 overflow-hidden hero-bg min-h-screen flex items-center">
        <!-- Animated Blobs -->
        <div
            class="absolute top-0 -left-64 w-96 h-96 bg-emerald-300 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob">
        </div>
        <div
            class="absolute top-0 -right-64 w-96 h-96 bg-teal-300 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob animation-delay-2000">
        </div>
        <div
            class="absolute -bottom-32 left-1/2 w-96 h-96 bg-green-300 rounded-full mix-blend-multiply filter blur-3xl opacity-30 animate-blob animation-delay-4000">
        </div>

        <div class="max-w-7xl mx-auto w-full grid grid-cols-1 xl:grid-cols-2 gap-16 items-center z-10 relative">
            <div class="text-center xl:text-right">
                <div
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-white/80 border border-emerald-100 text-emerald-700 text-xs font-black mb-8 shadow-sm backdrop-blur-sm">
                    <span class="flex h-2 w-2 relative">
                        <span
                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                    تحديث 3.1: دعم لوحات التحليل المتقدمة (SaaS)
                </div>

                <h1 class="text-5xl sm:text-6xl md:text-7xl font-black leading-tight mb-6 text-slate-900">
                    النظام الإداري <br> الشامل <span class="text-gradient">للتربية البدنية</span>
                </h1>

                <p class="text-lg md:text-xl text-slate-500 max-w-2xl mx-auto xl:mx-0 mb-10 leading-relaxed font-bold">
                    منصة سحابية متكاملة لرقمنة العمل الرياضي المدرسي بالكامل. من تسجيل الحضور، تحليل البنية الجسدية،
                    المراقبة الأمنية PDPL، وتوليد البطولات والشواهد الاحترافية بضغطة زر.
                </p>

                <div class="flex flex-col sm:flex-row items-center justify-center xl:justify-start gap-4">
                    <a href="register.html"
                        class="w-full sm:w-auto px-8 py-4 emerald-gradient text-white rounded-full font-black text-lg shadow-xl shadow-emerald-500/20 hover:shadow-emerald-500/40 hover:-translate-y-1 transition-all flex items-center justify-center gap-3">
                        <i data-lucide="rocket" class="w-5 h-5"></i> ابدأ الاشتراك لمدرستك
                    </a>
                    <a href="#features"
                        class="w-full sm:w-auto px-8 py-4 glass-panel text-slate-700 rounded-full font-black text-lg hover:bg-white transition-all flex items-center justify-center gap-3 border border-slate-200 shadow-sm">
                        <i data-lucide="play-circle" class="w-5 h-5"></i> جولة في الميزات
                    </a>
                </div>
            </div>

            <div class="relative w-full h-full min-h-[500px] hidden xl:block perspective-1000">
                <!-- Mac Mockup Abstraction -->
                <div
                    class="absolute inset-0 bg-gradient-to-tr from-emerald-100 to-teal-50 rounded-[2rem] transform rotate-3 scale-105 blur-lg opacity-60">
                </div>
                <div class="relative glass-panel p-4 rounded-[2rem] shadow-2xl border border-white/60 animate-float">
                    <div class="flex items-center gap-2 mb-4 px-2">
                        <div class="w-3 h-3 rounded-full bg-red-400"></div>
                        <div class="w-3 h-3 rounded-full bg-amber-400"></div>
                        <div class="w-3 h-3 rounded-full bg-emerald-400"></div>
                    </div>
                    <!-- Since we might not have a perfect dashboard image, we build a mini abstract UI as a placeholder mimicking the app -->
                    <div
                        class="bg-slate-50 rounded-xl overflow-hidden border border-slate-100 shadow-inner grid grid-cols-4 h-[400px]">
                        <div class="col-span-1 bg-white border-l border-slate-100 p-4 space-y-4">
                            <div class="h-8 bg-slate-100 rounded-lg w-full mb-8"></div>
                            <div class="h-4 bg-emerald-50 rounded w-3/4"></div>
                            <div class="h-4 bg-slate-50 rounded w-1/2"></div>
                            <div class="h-4 bg-slate-50 rounded w-2/3"></div>
                            <div class="h-4 bg-slate-50 rounded w-full"></div>
                        </div>
                        <div class="col-span-3 p-6 space-y-6 bg-slate-50">
                            <div class="grid grid-cols-3 gap-4">
                                <div class="h-24 bg-white rounded-2xl shadow-sm border border-slate-100"></div>
                                <div class="h-24 bg-emerald-50 rounded-2xl shadow-sm border border-emerald-100"></div>
                                <div class="h-24 bg-white rounded-2xl shadow-sm border border-slate-100"></div>
                            </div>
                            <div
                                class="h-48 bg-white rounded-2xl shadow-sm border border-slate-100 p-4 flex items-end gap-2 justify-between">
                                <div class="w-10 bg-emerald-100 h-1/3 rounded-t-md"></div>
                                <div class="w-10 bg-emerald-200 h-1/2 rounded-t-md"></div>
                                <div class="w-10 bg-emerald-300 h-2/3 rounded-t-md"></div>
                                <div class="w-10 bg-emerald-400 h-full rounded-t-md"></div>
                                <div class="w-10 bg-emerald-500 h-4/5 rounded-t-md"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- High-level Features Grid (Comprehensive) -->
    <section id="features" class="py-24 px-4 bg-white relative z-20">
        <div class="max-w-7xl mx-auto">
            <div class="text-center max-w-3xl mx-auto mb-20 reveal">
                <span class="text-emerald-500 font-bold tracking-widest text-sm uppercase mb-3 block">مكتبة قدرات لا
                    متناهية</span>
                <h2 class="text-4xl md:text-5xl font-black text-slate-900 mb-6 leading-tight">كل ما تحتاجه لإدارة رياضية
                    <br> مدرسية <span class="text-emerald-600">احترافية 100%</span>
                </h2>
                <p class="text-slate-500 text-lg font-bold">بدون جداول Excel معقدة، كل وظائف الإدارة البدنية بنيت لتعمل
                    فوراً في نظام مركزي.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- 1. Analytics -->
                <div class="hover-lift bg-slate-50 rounded-[2rem] p-8 border border-slate-100">
                    <div
                        class="w-14 h-14 rounded-2xl bg-white shadow-sm flex items-center justify-center text-emerald-500 mb-6 border border-slate-100">
                        <i data-lucide="bar-chart-4" class="w-7 h-7"></i>
                    </div>
                    <h3 class="text-xl font-black text-slate-800 mb-3">تحليلات متقدمة (Analytics)</h3>
                    <p class="text-sm text-slate-500 font-bold mb-4">خرائط حرارية للحضور، رسوم بيانية (Chart.js) تعكس
                        نبض التميز البدني للمدرسة، ومتوسطات الفصول والطلاب المتفوقين.</p>
                </div>

                <!-- 2. Tournaments -->
                <div class="hover-lift bg-slate-50 rounded-[2rem] p-8 border border-slate-100 relative overflow-hidden">
                    <div class="absolute -right-4 -top-4 w-24 h-24 bg-emerald-500/10 rounded-full blur-xl"></div>
                    <div
                        class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 shadow-md flex items-center justify-center text-white mb-6">
                        <i data-lucide="trophy" class="w-7 h-7"></i>
                    </div>
                    <h3 class="text-xl font-black text-slate-800 mb-3">محرك البطولات المبتكر</h3>
                    <p class="text-sm text-slate-500 font-bold">إدارة خروج المغلوب (Brackets)، مباريات المراكز الثالثة،
                        مجاميع الدوري المجمعة، مع لوحة حساب أهداف وتحديث الجداول الحية.</p>
                </div>

                <!-- 3. Medical & BMI -->
                <div class="hover-lift bg-slate-50 rounded-[2rem] p-8 border border-slate-100">
                    <div
                        class="w-14 h-14 rounded-2xl bg-white shadow-sm flex items-center justify-center text-rose-500 mb-6 border border-slate-100">
                        <i data-lucide="heart-pulse" class="w-7 h-7"></i>
                    </div>
                    <h3 class="text-xl font-black text-slate-800 mb-3">سجل طبي وقياسات (BMI)</h3>
                    <p class="text-sm text-slate-500 font-bold">تسجيل أوزان وأطوال، حساب كتلة الجسم التلقائي مع تحذيرات
                        صحية. بالإضافة لسجل التاريخ المرضي للطالب لتنبيه المعلم.</p>
                </div>

                <!-- 4. Reports & PDFs -->
                <div class="hover-lift bg-slate-50 rounded-[2rem] p-8 border border-slate-100">
                    <div
                        class="w-14 h-14 rounded-2xl bg-white shadow-sm flex items-center justify-center text-blue-500 mb-6 border border-slate-100">
                        <i data-lucide="mail-check" class="w-7 h-7"></i>
                    </div>
                    <h3 class="text-xl font-black text-slate-800 mb-3">تقارير و PDF للإيميل</h3>
                    <p class="text-sm text-slate-500 font-bold">توليد تقارير إحصائية رائعة بضغطة واحدة وتوليد مرفق PDF
                        سحابيًا وإرساله مباشرة لبريد ولي الأمر أو الإدارة من قلب النظام.</p>
                </div>

                <!-- 5. Gamification -->
                <div class="hover-lift bg-slate-50 rounded-[2rem] p-8 border border-slate-100">
                    <div
                        class="w-14 h-14 rounded-2xl bg-white shadow-sm flex items-center justify-center text-amber-500 mb-6 border border-slate-100">
                        <i data-lucide="medal" class="w-7 h-7"></i>
                    </div>
                    <h3 class="text-xl font-black text-slate-800 mb-3">الأوسمة والتشجيع</h3>
                    <p class="text-sm text-slate-500 font-bold">أنشئ أوسمة مخصصة، إمنحها للطلاب وتتبع تطورهم التنافسي
                        لزيادة المتعة والحماس داخل الحصص البدنية وفي المباريات.</p>
                </div>

                <!-- 6. Smart Attendance -->
                <div class="hover-lift bg-slate-50 rounded-[2rem] p-8 border border-slate-100">
                    <div
                        class="w-14 h-14 rounded-2xl bg-white shadow-sm flex items-center justify-center text-violet-500 mb-6 border border-slate-100">
                        <i data-lucide="calendar-check-2" class="w-7 h-7"></i>
                    </div>
                    <h3 class="text-xl font-black text-slate-800 mb-3">تحضير ذكي (Swipe)</h3>
                    <p class="text-sm text-slate-500 font-bold">واجهة هاتفية ممتازة للمعلم لتسجيل الحضور في الملعب بنظام
                        السحب الجانبي السريع لتوفير وقت الحصة الثمين.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- Deep Section 0: Analytics & Health (Light/White) -->
    <section id="analytics" class="py-24 px-4 bg-white relative z-20">
        <div class="max-w-7xl mx-auto flex flex-col lg:flex-row items-center gap-16">
            <div class="flex-1 reveal">
                <span class="text-indigo-500 font-bold tracking-widest text-sm uppercase mb-4 block">الذكاء العملي</span>
                <h2 class="text-4xl md:text-5xl font-black text-slate-900 mb-6 leading-tight">
                    تحليلات صحية دقيقة <br> <span class="text-indigo-600">لرعاية رياضية شاملة</span>
                </h2>
                <div class="space-y-8">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 bg-indigo-50 rounded-2xl flex items-center justify-center text-indigo-500 shadow-sm border border-indigo-100 flex-shrink-0">
                            <i data-lucide="line-chart" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <h4 class="text-xl font-black text-slate-800 mb-2">لوحة التحليلات المتقدمة</h4>
                            <p class="text-slate-500 font-bold leading-relaxed">رسوم بيانية تفاعلية تعرض تطور أداء المدرسة، خرائط حرارية للحضور (Heatmaps)، ومقارنات ذكية بين الفصول بناءً على الأوزان الرياضية.</p>
                        </div>
                    </div>
                    
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 bg-rose-50 rounded-2xl flex items-center justify-center text-rose-500 shadow-sm border border-rose-100 flex-shrink-0">
                            <i data-lucide="heart" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <h4 class="text-xl font-black text-slate-800 mb-2">رصد المؤشرات الحيوية (BMI)</h4>
                            <p class="text-slate-500 font-bold leading-relaxed">تتبع آلي لمؤشر كتلة الجسم، الطول، والوزن مع تصنيف صحي تلقائي وتنبيهات فورية للمعلمين عن حالات السمنة أو النحافة الزائدة.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 bg-amber-50 rounded-2xl flex items-center justify-center text-amber-500 shadow-sm border border-amber-100 flex-shrink-0">
                            <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <h4 class="text-xl font-black text-slate-800 mb-2">نظام التنبيه الصحي المبكر</h4>
                            <p class="text-slate-500 font-bold leading-relaxed">سجل مدمج للحالات الصحية والأمراض المزمنة يظهر للمعلم أثناء التحضير الميداني لضمان سلامة الطلاب وتجنب الإجهاد الزائد.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex-1 w-full reveal">
                <div class="relative group">
                    <!-- Abstract Analytics UI Representation -->
                    <div class="absolute inset-0 bg-indigo-100 rounded-[3rem] blur-3xl opacity-30 group-hover:opacity-50 transition-opacity"></div>
                    <div class="relative glass-panel p-8 rounded-[3rem] shadow-2xl border border-white/60">
                        <div class="flex items-center justify-between mb-8 border-b border-slate-100 pb-4">
                            <div class="flex items-center gap-2">
                                <div class="w-3 h-3 rounded-full bg-slate-200"></div>
                                <div class="w-3 h-3 rounded-full bg-slate-200"></div>
                                <div class="w-3 h-3 rounded-full bg-slate-200"></div>
                            </div>
                            <span class="text-[10px] font-black tracking-widest text-slate-400 uppercase">Live Analytics Feed</span>
                        </div>
                        
                        <div class="space-y-6">
                            <!-- Fake Stat Cards -->
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-indigo-600 rounded-2xl p-4 text-white shadow-lg">
                                    <p class="text-[9px] font-bold opacity-80 uppercase">Avg Fitness</p>
                                    <p class="text-2xl font-black">92.4%</p>
                                    <div class="mt-2 h-1 w-full bg-white/20 rounded-full overflow-hidden">
                                        <div class="h-full bg-white w-[92%]"></div>
                                    </div>
                                </div>
                                <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm">
                                    <p class="text-[9px] font-bold text-slate-400 uppercase">Active Alerts</p>
                                    <p class="text-2xl font-black text-rose-500">03</p>
                                    <div class="mt-2 flex gap-1">
                                        <div class="w-2 h-2 rounded-full bg-rose-400 animate-pulse"></div>
                                        <div class="w-2 h-2 rounded-full bg-rose-400 animate-pulse"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Fake Chart Lines -->
                            <div class="h-32 flex items-end justify-between px-2 pt-4">
                                <div class="w-6 bg-indigo-100 h-[20%] rounded-t-lg transition-all hover:h-[30%]"></div>
                                <div class="w-6 bg-indigo-200 h-[45%] rounded-t-lg transition-all hover:h-[55%]"></div>
                                <div class="w-6 bg-indigo-300 h-[30%] rounded-t-lg transition-all hover:h-[40%]"></div>
                                <div class="w-6 bg-indigo-400 h-[65%] rounded-t-lg transition-all hover:h-[75%]"></div>
                                <div class="w-6 bg-indigo-500 h-[40%] rounded-t-lg transition-all hover:h-[50%]"></div>
                                <div class="w-6 bg-indigo-600 h-[90%] rounded-t-lg transition-all hover:h-[100%]"></div>
                            </div>
                            
                            <div class="text-center">
                                <span class="inline-flex py-1 px-4 bg-slate-100 rounded-full text-[10px] font-black text-slate-500 uppercase tracking-widest">Monthly Growth Performance</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Deep Section 1: Tournament & Brackets (Dark) -->

    <!-- Deep Section 1: Tournament & Brackets (Dark) -->
    <section id="tournaments" class="py-24 px-4 bg-slate-900 border-t border-slate-800 relative z-10 overflow-hidden">
        <div
            class="absolute top-1/2 left-0 w-full h-px bg-gradient-to-r from-transparent via-emerald-500/20 to-transparent">
        </div>
        <div class="absolute -right-32 top-32 w-[500px] h-[500px] bg-emerald-500/10 blur-[100px] rounded-full"></div>

        <div class="max-w-7xl mx-auto flex flex-col lg:flex-row items-center gap-16 relative z-10">
            <div class="flex-1 reveal">
                <span class="text-emerald-400 font-bold tracking-widest text-sm uppercase mb-4 block">Engine 3.0</span>
                <h2 class="text-4xl md:text-5xl font-black text-white mb-6 leading-tight">
                    معالج إلكتروني لبطولات <br> <span class="text-gradient-light">المدرسة التنافسية</span>
                </h2>
                <ul class="space-y-6 text-slate-300 font-bold text-lg mb-10">
                    <li class="flex items-start gap-4">
                        <div class="mt-1 bg-emerald-500/20 text-emerald-400 p-1.5 rounded-lg"><i
                                data-lucide="git-branch" class="w-5 h-5"></i></div>
                        <div>
                            <span class="text-white">شجرة تصفيات (Knockouts) تولّد آلياً</span>
                            <p class="text-sm text-slate-400 font-normal mt-1">وداعاً للرسم اليدوي، أدخل الفرق ودع
                                النظام يشكل شجرة المواجهات ومباريات المركز الثالث.</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-4">
                        <div class="mt-1 bg-emerald-500/20 text-emerald-400 p-1.5 rounded-lg"><i data-lucide="wand-2"
                                class="w-5 h-5"></i></div>
                        <div>
                            <span class="text-white">ساحر توزيع الفرق (Lottery Wizard)</span>
                            <p class="text-sm text-slate-400 font-normal mt-1">فلتر الطلاب، اختر القادة، ونفذ سحب قرعة
                                عشوائي ذكي لتكوين فرق عادلة بثوانٍ.</p>
                        </div>
                    </li>
                    <li class="flex items-start gap-4">
                        <div class="mt-1 bg-emerald-500/20 text-emerald-400 p-1.5 rounded-lg"><i
                                data-lucide="smartphone-nfc" class="w-5 h-5"></i></div>
                        <div>
                            <span class="text-white">لوحة تحكيم الميدان (Live Match)</span>
                            <p class="text-sm text-slate-400 font-normal mt-1">تطبيق الجوال يتيح لك تسجيل الأهداف
                                والكروت والإنذارات ونجم المباراة بضغطة.</p>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="flex-1 w-full reveal">
                <div
                    class="glass-dark p-6 rounded-[2.5rem] shadow-2xl skew-y-2 transform transition hover:skew-y-0 duration-700 relative group">
                    <div class="flex items-end gap-2 justify-center mb-6">
                        <div class="w-16 h-8 bg-slate-800 rounded-lg border border-slate-700"></div>
                        <div class="w-8 border-t-2 border-slate-600"></div>
                        <div
                            class="w-16 h-12 bg-emerald-600 rounded-lg shadow-lg flex items-center justify-center font-bold text-white text-xs">
                            Winner</div>
                        <div class="w-8 border-t-2 border-slate-600"></div>
                        <div class="w-16 h-8 bg-slate-800 rounded-lg border border-slate-700"></div>
                    </div>
                    <div class="flex items-end gap-2 justify-between px-10">
                        <div class="w-20 h-10 bg-slate-800 rounded border border-slate-700"></div>
                        <div class="w-20 h-10 bg-slate-800 rounded border border-slate-700"></div>
                    </div>
                    <!-- Aesthetic Overlay -->
                    <div
                        class="absolute inset-0 bg-gradient-to-t from-slate-900/80 to-transparent rounded-[2.5rem] flex items-end justify-center pb-8 opacity-0 group-hover:opacity-100 transition-opacity">
                        <span class="bg-emerald-500 text-white px-4 py-2 rounded-full font-bold text-sm shadow-xl">تفاعل
                            حي ⚡</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Deep Section 2: Gamification & Certificates -->
    <section class="py-24 px-4 bg-slate-50 relative z-20">
        <div class="max-w-7xl mx-auto flex flex-col lg:flex-row items-center gap-16">
            <div class="flex-1 order-2 lg:order-1 reveal">
                <div class="bg-white p-8 rounded-[3rem] shadow-xl border border-slate-100 relative">
                    <!-- Fake Certificate Designer -->
                    <div
                        class="aspect-video bg-amber-50 rounded-2xl border-2 border-dashed border-amber-200 p-6 flex flex-col items-center justify-center relative overflow-hidden">
                        <div
                            class="absolute top-4 left-4 w-12 h-12 border border-emerald-500 rounded-full flex items-center justify-center opacity-50">
                            <i data-lucide="move" class="w-5 h-5 text-emerald-600"></i>
                        </div>
                        <h4
                            class="text-3xl font-black text-slate-800 mb-2 font-serif text-amber-900 border border-amber-400 p-2 border-dashed cursor-move">
                            شهادة تفوق رياضي</h4>
                        <p
                            class="text-sm font-bold text-slate-500 border border-transparent hover:border-blue-400 p-1 cursor-move transition-colors">
                            يُمنح الطالب المتميز درع البطولة</p>
                        <div
                            class="mt-4 w-16 h-16 bg-yellow-400 rounded-full flex items-center justify-center text-white shadow-lg shadow-yellow-200">
                            <i data-lucide="award" class="w-8 h-8"></i>
                        </div>
                    </div>
                    <div
                        class="mt-6 flex justify-between items-center bg-slate-50 p-3 rounded-2xl border border-slate-100">
                        <div class="flex gap-2">
                            <div
                                class="w-8 h-8 bg-white rounded shadow-sm flex items-center justify-center text-slate-400">
                                <i data-lucide="type" class="w-4 h-4"></i>
                            </div>
                            <div
                                class="w-8 h-8 bg-white rounded shadow-sm flex items-center justify-center text-slate-400">
                                <i data-lucide="image" class="w-4 h-4"></i>
                            </div>
                        </div>
                        <button
                            class="bg-emerald-500 text-white px-4 py-2 rounded-xl text-sm font-bold shadow-md hover:bg-emerald-600">حفظ
                            النموذج</button>
                    </div>
                </div>
            </div>

            <div class="flex-1 order-1 lg:order-2 reveal">
                <span class="text-emerald-500 font-bold tracking-widest text-sm uppercase mb-4 block">التحفيز
                    والتوثيق</span>
                <h2 class="text-4xl md:text-5xl font-black text-slate-900 mb-6 leading-tight">
                    اصنع شهادات احترافية <br> وأوسمة تحفيزية للطلاب
                </h2>
                <p class="text-lg text-slate-500 font-bold mb-8">
                    نظام Certificates Builder يتيح لك السحب والإفلات لتصميم شهادة تعكس هوية مدرستك، مع ربط ذكي لنظام
                    الأوسمة والبطاقات لمكافأة الأبطال وتوثيق الحدث.
                </p>
            </div>
        </div>
    </section>

    <!-- Deep Section 3: Security & Compliance (PDPL) -->
    <section id="security" class="py-24 px-4 bg-emerald-950 text-white relative z-20 overflow-hidden">
        <div class="absolute inset-0 opacity-10 dot-pattern"></div>
        <div class="max-w-7xl mx-auto text-center reveal">
            <div
                class="w-20 h-20 bg-emerald-900 rounded-3xl mx-auto flex items-center justify-center text-emerald-400 mb-8 border border-emerald-800/50 shadow-inner">
                <i data-lucide="shield-check" class="w-10 h-10"></i>
            </div>
            <h2 class="text-4xl md:text-5xl font-black mb-6">أمان، امتثال، وراحة بال</h2>
            <p class="text-lg text-emerald-100/70 max-w-2xl mx-auto font-bold mb-16">
                صممنا بنية SaaS تتوافق مع معايير حماية البيانات الشخصية. بيانات كل مدرسة معزولة تماماً ولا يمكن التعدي
                عليها بأي شكل.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-right max-w-5xl mx-auto">
                <div class="bg-white/5 border border-white/10 p-8 rounded-3xl backdrop-blur-md">
                    <i data-lucide="database-backup" class="w-8 h-8 text-emerald-400 mb-4"></i>
                    <h4 class="text-xl font-black mb-2">نسخ احتياطي تلقائي (Auto-Backup)</h4>
                    <p class="text-sm text-emerald-100/60 font-medium leading-relaxed">سكربت ذكي يقوم بأخذ Backup مضغوط
                        بصيغة ZIP يومياً للحفاظ على ملفات النظام ونتائج المدرسة من الضياع.</p>
                </div>
                <div class="bg-white/5 border border-white/10 p-8 rounded-3xl backdrop-blur-md">
                    <i data-lucide="eye" class="w-8 h-8 text-emerald-400 mb-4"></i>
                    <h4 class="text-xl font-black mb-2">سجل الأنشطة (Audit Log)</h4>
                    <p class="text-sm text-emerald-100/60 font-medium leading-relaxed">رقابة صارمة توضح للمدير من أدخل
                        الدرجات، من قام بتسجيل الغياب، ومن عدل البيانات مع توقيت و IP المعلم.</p>
                </div>
                <div class="bg-white/5 border border-white/10 p-8 rounded-3xl backdrop-blur-md">
                    <i data-lucide="eraser" class="w-8 h-8 text-emerald-400 mb-4"></i>
                    <h4 class="text-xl font-black mb-2">المحو الشامل الآمن</h4>
                    <p class="text-sm text-emerald-100/60 font-medium leading-relaxed">في الانطباع باللوائح، يحق للمدرسة
                        تدمير قواعد بياناتها كلياً بضغطة زر عند الانتهاء دون ترك مخلفات وراءها.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Roles Grid -->
    <section class="py-24 px-4 bg-white relative z-20">
        <div class="max-w-7xl mx-auto pb-10 border-b border-slate-100">
            <h2 class="text-3xl font-black text-center text-slate-900 mb-16">يخدم كافة أطراف <span
                    class="text-emerald-500 border-b-4 border-emerald-200">المنظومة التعليمية</span></h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                <div
                    class="p-6 bg-slate-50 rounded-[2rem] hover:bg-white hover:shadow-xl hover:-translate-y-2 transition-all cursor-default">
                    <div
                        class="w-16 h-16 bg-slate-100 rounded-full mx-auto flex items-center justify-center text-slate-700 mb-4">
                        <i data-lucide="shield" class="w-8 h-8"></i>
                    </div>
                    <h5 class="font-black text-lg text-slate-800">مدير ومشرف</h5>
                    <p class="text-xs text-slate-500 font-bold mt-2">مراقبة الأداء، الإحصاءات العامة، وسجل التقارير
                        والأمان.</p>
                </div>
                <div
                    class="p-6 bg-slate-50 rounded-[2rem] hover:bg-white hover:shadow-xl hover:-translate-y-2 transition-all cursor-default">
                    <div
                        class="w-16 h-16 emerald-gradient rounded-full mx-auto flex items-center justify-center text-white mb-4">
                        <i data-lucide="pencil" class="w-8 h-8"></i>
                    </div>
                    <h5 class="font-black text-lg text-slate-800">المعلم</h5>
                    <p class="text-xs text-slate-500 font-bold mt-2">إدارة الحصص، رصد القياسات، وتنظيم البطولات والفرق
                        بسهولة.</p>
                </div>
                <div
                    class="p-6 bg-slate-50 rounded-[2rem] hover:bg-white hover:shadow-xl hover:-translate-y-2 transition-all cursor-default">
                    <div
                        class="w-16 h-16 bg-purple-100 rounded-full mx-auto flex items-center justify-center text-purple-600 mb-4">
                        <i data-lucide="users" class="w-8 h-8"></i>
                    </div>
                    <h5 class="font-black text-lg text-slate-800">ولي الأمر</h5>
                    <p class="text-xs text-slate-500 font-bold mt-2">متابعة غياب الابن، تلقي التقارير عبر البريد، وصحة
                        بدنه.</p>
                </div>
                <div
                    class="p-6 bg-slate-50 rounded-[2rem] hover:bg-white hover:shadow-xl hover:-translate-y-2 transition-all cursor-default">
                    <div
                        class="w-16 h-16 bg-amber-100 rounded-full mx-auto flex items-center justify-center text-amber-600 mb-4">
                        <i data-lucide="medal" class="w-8 h-8"></i>
                    </div>
                    <h5 class="font-black text-lg text-slate-800">الطالب</h5>
                    <p class="text-xs text-slate-500 font-bold mt-2">دافع معنوي بتوثيق تقدمه الرياضي، أوسمته ومكافآته.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Blog & Tutorials Section -->
    <section id="blog" class="py-24 px-4 bg-white relative z-20">
        <div class="max-w-7xl mx-auto">
            <div class="text-center max-w-3xl mx-auto mb-16 reveal">
                <span class="text-emerald-500 font-bold tracking-widest text-sm uppercase mb-3 block">المدونة
                    والدروس</span>
                <h2 class="text-4xl md:text-5xl font-black text-slate-900 mb-6">اكتشف أسرار <span
                        class="text-emerald-600">التميز الرياضي</span></h2>
                <p class="text-slate-500 text-lg font-bold">مقالات تعليمية، نصائح رياضية، وآخر تحديثات المنصة لنبقيك
                    دائماً في الصدارة.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php if (empty($recentPosts)): ?>
                    <div class="col-span-full text-center p-12 bg-slate-50 rounded-[2.5rem] border border-slate-100">
                        <p class="text-slate-500 font-bold">لا توجد مقالات منشورة حالياً.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentPosts as $post): ?>
                        <div class="hover-lift bg-slate-50 rounded-[2.5rem] overflow-hidden border border-slate-100 flex flex-col group">
                            <div class="h-48 bg-emerald-100 relative overflow-hidden flex items-center justify-center group-hover:scale-105 transition-transform duration-500">
                                <?php if ($post['image_path']): ?>
                                    <img src="<?= esc($post['image_path']) ?>" alt="<?= esc($post['title']) ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <span class="text-4xl text-emerald-500">📰</span>
                                <?php endif; ?>
                                <div class="absolute top-4 right-4 bg-emerald-500 text-white text-[10px] font-black px-3 py-1 rounded-full shadow-lg">
                                    <?= esc($post['category']) ?>
                                </div>
                            </div>
                            <div class="p-8">
                                <h3 class="text-xl font-black text-slate-800 mb-3 hover:text-emerald-600 transition-colors cursor-pointer">
                                    <a href="blog/<?= esc($post['slug']) ?>/"><?= esc($post['title']) ?></a>
                                </h3>
                                <p class="text-sm text-slate-500 font-bold mb-6 line-clamp-3">
                                    <?= esc($post['excerpt']) ?>
                                </p>
                                <a href="blog/<?= esc($post['slug']) ?>/" class="text-emerald-600 font-black text-sm flex items-center gap-2 group/link">
                                    اقرأ المزيد
                                    <i data-lucide="arrow-left" class="w-4 h-4 transform group-hover/link:-translate-x-1 transition-transform"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="mt-12 text-center">
                <a href="blog/" class="inline-block px-8 py-3 border-2 border-emerald-500 text-emerald-600 font-black rounded-full hover:bg-emerald-500 hover:text-white transition-all transform hover:scale-105">
                    تصفح جميع المقالات والدروس
                </a>
            </div>
        </div>
    </section>


    <!-- Testimonials Section -->
    <section id="testimonials" class="py-24 px-4 bg-slate-50 relative overflow-hidden">
        <style>
            .star-filled { fill: #f59e0b; stroke: #d97706; stroke-width: 1.5; }
            .star-empty { fill: transparent; stroke: #c3d5eaff; stroke-width: 1.5; }
            [dir="rtl"] [data-lucide="star-half"] { transform: scaleX(-1); }
        </style>
        <div class="absolute inset-0 opacity-5 pointer-events-none">
            <div class="absolute -top-24 -left-24 w-96 h-96 bg-emerald-500 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-24 -right-24 w-96 h-96 bg-cyan-500 rounded-full blur-3xl"></div>
        </div>

        <div class="max-w-7xl mx-auto relative z-10">
            <div class="text-center max-w-3xl mx-auto mb-20 reveal">
                <span class="px-4 py-1.5 rounded-full bg-emerald-100 text-emerald-600 font-bold text-sm mb-4 inline-block">صوت الميدان</span>
                <h2 class="text-4xl md:text-5xl font-black text-slate-900 mb-6">ماذا يقول <span class="text-emerald-600">خبراء الرياضة</span> عنا؟</h2>
                <p class="text-slate-500 text-lg font-bold">نفخر بدعم نخبة من المعلمين والمدارس في رحلتهم نحو التحول الرقمي الرياضي.</p>
            </div>

            <?php if (empty($testimonials)): ?>
                <!-- Placeholder items for initial setup / if no posts in category -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php for($i=1; $i<=6; $i++): ?>
                        <div class="glass-panel p-8 rounded-[2.5rem] border border-white/40 shadow-xl reveal" style="transition-delay: <?= $i*100 ?>ms;">
                            <div class="flex gap-1 mb-6 text-amber-400">
                                <?php for($j=0; $j<5; $j++) echo '<i data-lucide="star" class="w-4 h-4"></i>'; ?>
                            </div>
                            <p class="text-slate-700 font-medium mb-8 leading-relaxed italic">"هذا القسم سيظهر فيه آراء العملاء التي ستضيفها في ووردبريس ضمن تصنيف Reviews."</p>
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-2xl emerald-gradient flex items-center justify-center text-white font-bold text-xl">
                                    <?= chr(64+$i) ?>
                                </div>
                                <div class="text-right">
                                    <h4 class="font-black text-slate-800">اسم العميل قريباً</h4>
                                    <span class="text-xs text-emerald-600 font-bold">اسم المدرسة</span>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php foreach ($testimonials as $idx => $t): ?>
                        <div class="glass-panel p-8 rounded-[2.5rem] border border-white/40 shadow-xl hover:-translate-y-2 transition-all duration-500 reveal" style="transition-delay: <?= ($idx+1)*100 ?>ms;">
                            <div class="flex gap-1 mb-6">
                                <?php 
                                $rating = floatval($t['rating']);
                                for($j=1; $j<=5; $j++) {
                                    if ($j <= floor($rating)) {
                                        echo '<i data-lucide="star" class="w-5 h-5 star-filled"></i>';
                                    } elseif ($j == ceil($rating) && floor($rating) != $rating) {
                                        echo '<i data-lucide="star-half" class="w-5 h-5 star-filled"></i>';
                                    } else {
                                        echo '<i data-lucide="star" class="w-5 h-5 star-empty"></i>';
                                    }
                                }
                                ?>
                            </div>
                            <p class="text-slate-700 font-medium mb-8 leading-relaxed italic">
                                "<?= esc($t['content']) ?>"
                            </p>
                            <div class="flex items-center gap-4 mt-auto">
                                <?php if (!empty($t['image_path'])): ?>
                                    <img src="<?= esc($t['image_path']) ?>" class="w-12 h-12 rounded-2xl object-cover shadow-lg border-2 border-white/50" alt="<?= esc($t['name']) ?>">
                                <?php else: ?>
                                    <div class="w-12 h-12 rounded-2xl emerald-gradient flex items-center justify-center text-white font-bold text-xl shadow-lg">
                                        <?= mb_substr($t['name'], 0, 1) ?>
                                    </div>
                                <?php endif; ?>
                                <div class="text-right">
                                    <h4 class="font-black text-slate-800"><?= esc($t['name']) ?></h4>
                                    <span class="text-xs text-emerald-600 font-bold"><?= esc($t['school']) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- FAQ Section -->
    <section id="faq" class="py-24 px-4 bg-white relative overflow-hidden">
        <div class="max-w-4xl mx-auto relative z-10">
            <div class="text-center mb-16 reveal">
                <span class="px-4 py-1.5 rounded-full bg-emerald-100 text-emerald-600 font-bold text-sm mb-4 inline-block">دليلك السريع</span>
                <h2 class="text-4xl lg:text-5xl font-black text-slate-900 mb-6 tracking-tight">الأسئلة <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-500 to-cyan-500">الشائعة</span></h2>
                <p class="text-slate-500 font-bold max-w-2xl mx-auto text-lg hover:text-slate-700 transition-colors">إجابات وافية لأكثر الأسئلة تداولاً بين المعلمين ومدراء المدارس حول استخدام المنصة.</p>
            </div>

            <div class="space-y-4 reveal" style="transition-delay: 100ms;">
                
                <!-- FAQ Item 1 -->
                <div class="faq-item bg-white border border-slate-100 rounded-3xl overflow-hidden hover:shadow-xl hover:border-emerald-100 hover:-translate-y-1 transition-all duration-300">
                    <button class="faq-button w-full text-right px-8 py-6 flex items-center justify-between focus:outline-none">
                        <span class="font-black text-lg text-slate-800">1. ما هي منصة PE Smart School؟</span>
                        <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-emerald-500 faq-icon transition-transform duration-300 shadow-inner">
                            <i data-lucide="chevron-down" class="w-5 h-5"></i>
                        </div>
                    </button>
                    <div class="faq-content overflow-hidden max-h-0 transition-all duration-500 ease-in-out opacity-0 px-8">
                        <p class="text-slate-600 font-medium pb-6 leading-relaxed">هي منصة رقمية سحابية متكاملة صُممت خصيصاً لمعلمي التربية البدنية. تهدف إلى تحويل العمل الورقي التقليدي إلى نظام رقمي ذكي يساعد في إدارة الحصص، تقييم الطلاب، تنظيم البطولات المدرسية، وتتبع سجلات اللياقة البدنية بكل سهولة.</p>
                    </div>
                </div>

                <!-- FAQ Item 2 -->
                <div class="faq-item bg-white border border-slate-100 rounded-3xl overflow-hidden hover:shadow-xl hover:border-emerald-100 hover:-translate-y-1 transition-all duration-300">
                    <button class="faq-button w-full text-right px-8 py-6 flex items-center justify-between focus:outline-none">
                        <span class="font-black text-lg text-slate-800">2. هل المنصة تعمل على الهواتف الذكية أم أجهزة الكمبيوتر فقط؟</span>
                        <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-emerald-500 faq-icon transition-transform duration-300 shadow-inner">
                            <i data-lucide="chevron-down" class="w-5 h-5"></i>
                        </div>
                    </button>
                    <div class="faq-content overflow-hidden max-h-0 transition-all duration-500 ease-in-out opacity-0 px-8">
                        <p class="text-slate-600 font-medium pb-6 leading-relaxed">النظام سحابي ويعمل بكفاءة عالية على جميع الأجهزة. يمكنك استخدامه من خلال متصفح الكمبيوتر، أو من خلال الهاتف الآيفون والأندرويد أو الجهاز اللوحي (الآيباد) بكل سلاسة أثناء تواجدك في الصالة أو الملعب الرياضي.</p>
                    </div>
                </div>

                <!-- FAQ Item 3 -->
                <div class="faq-item bg-white border border-slate-100 rounded-3xl overflow-hidden hover:shadow-xl hover:border-emerald-100 hover:-translate-y-1 transition-all duration-300">
                    <button class="faq-button w-full text-right px-8 py-6 flex items-center justify-between focus:outline-none">
                        <span class="font-black text-lg text-slate-800">3. كيف تعمل ميزة "محرك البطولات" داخل النظام؟</span>
                        <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-emerald-500 faq-icon transition-transform duration-300 shadow-inner">
                            <i data-lucide="chevron-down" class="w-5 h-5"></i>
                        </div>
                    </button>
                    <div class="faq-content overflow-hidden max-h-0 transition-all duration-500 ease-in-out opacity-0 px-8">
                        <p class="text-slate-600 font-medium pb-6 leading-relaxed">يوفر النظام أداة احترافية لإنشاء البطولات المدرسية (مثل نظام الدوري المستمر أو خروج المغلوب). يمكنك تقسيم الطلاب إلى فرق، وسيقوم النظام تلقائياً بإنشاء جدول المباريات، تتبع النتائج، وتحديث الترتيب وإصدار إحصائيات أفضل اللاعبين والهدافين بناءً على خوارزميات رياضية دقيقة.</p>
                    </div>
                </div>

                <!-- FAQ Item 4 -->
                <div class="faq-item bg-white border border-slate-100 rounded-3xl overflow-hidden hover:shadow-xl hover:border-emerald-100 hover:-translate-y-1 transition-all duration-300">
                    <button class="faq-button w-full text-right px-8 py-6 flex items-center justify-between focus:outline-none">
                        <span class="font-black text-lg text-slate-800">4. هل يمكنني تخصيص معايير التقييم لتناسب خطتي الدراسية؟</span>
                        <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-emerald-500 faq-icon transition-transform duration-300 shadow-inner">
                            <i data-lucide="chevron-down" class="w-5 h-5"></i>
                        </div>
                    </button>
                    <div class="faq-content overflow-hidden max-h-0 transition-all duration-500 ease-in-out opacity-0 px-8">
                        <p class="text-slate-600 font-medium pb-6 leading-relaxed">بالتأكيد. المنصة مرنة وتسمح لك بضبط وتوزيع درجات التقييم (مثل الحضور، الزي الرياضي، المشاركة، واختبارات اللياقة البدنية) وتحديد الوزن الأكاديمي لكل معيار بما يتوافق بدقة مع القواعد واللوائح المعتمدة في مدرستك أو إدارتك التعليمية.</p>
                    </div>
                </div>

                <!-- FAQ Item 5 -->
                <div class="faq-item bg-white border border-slate-100 rounded-3xl overflow-hidden hover:shadow-xl hover:border-emerald-100 hover:-translate-y-1 transition-all duration-300">
                    <button class="faq-button w-full text-right px-8 py-6 flex items-center justify-between focus:outline-none">
                        <span class="font-black text-lg text-slate-800">5. هل بيانات مدرستي وطلابي آمنة؟</span>
                        <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-emerald-500 faq-icon transition-transform duration-300 shadow-inner">
                            <i data-lucide="chevron-down" class="w-5 h-5"></i>
                        </div>
                    </button>
                    <div class="faq-content overflow-hidden max-h-0 transition-all duration-500 ease-in-out opacity-0 px-8">
                        <p class="text-slate-600 font-medium pb-6 leading-relaxed">الأمان هو أولويتنا القصوى. جميع بيانات المدارس، المعلمين، والطلاب يتم تشفيرها وحفظها في خوادم سحابية آمنة ومدرعة، ولا يمكن لأي شخص الاطلاع عليها سوى المصرّح لهم من إدارة مدرستك.</p>
                    </div>
                </div>

                <!-- FAQ Item 6 -->
                <div class="faq-item bg-white border border-slate-100 rounded-3xl overflow-hidden hover:shadow-xl hover:border-emerald-100 hover:-translate-y-1 transition-all duration-300">
                    <button class="faq-button w-full text-right px-8 py-6 flex items-center justify-between focus:outline-none">
                        <span class="font-black text-lg text-slate-800">6. هل أحتاج إلى خبرة تقنية سابقة لاستخدام النظام؟</span>
                        <div class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-emerald-500 faq-icon transition-transform duration-300 shadow-inner">
                            <i data-lucide="chevron-down" class="w-5 h-5"></i>
                        </div>
                    </button>
                    <div class="faq-content overflow-hidden max-h-0 transition-all duration-500 ease-in-out opacity-0 px-8">
                        <p class="text-slate-600 font-medium pb-6 leading-relaxed">لا على الإطلاق! تم تصميم واجهة المستخدم لتكون بسيطة، واضحة، وباللغة العربية، بحيث يمكن لأي معلم أو مشرف رياضي البدء في استخدامها باحترافية منذ اليوم الأول دون الحاجة لتدريب معقد.</p>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <script>
        // Simple FAQ Accordion Script
        document.querySelectorAll('.faq-button').forEach(button => {
            button.addEventListener('click', () => {
                const faqItem = button.parentElement;
                const content = button.nextElementSibling;
                const icon = button.querySelector('.faq-icon');
                const isOpen = faqItem.classList.contains('active');

                // Close all other open items tightly
                document.querySelectorAll('.faq-item.active').forEach(item => {
                    if (item !== faqItem) {
                        item.classList.remove('active');
                        item.querySelector('.faq-content').style.maxHeight = '0px';
                        item.querySelector('.faq-content').classList.remove('opacity-100');
                        item.querySelector('.faq-content').classList.add('opacity-0');
                        item.querySelector('.faq-icon').style.transform = 'rotate(0deg)';
                    }
                });

                if (isOpen) {
                    faqItem.classList.remove('active');
                    content.style.maxHeight = '0px';
                    content.classList.remove('opacity-100');
                    content.classList.add('opacity-0');
                    icon.style.transform = 'rotate(0deg)';
                } else {
                    faqItem.classList.add('active');
                    content.classList.remove('opacity-0');
                    content.classList.add('opacity-100');
                    content.style.maxHeight = content.scrollHeight + "px";
                    icon.style.transform = 'rotate(180deg)';
                }
            });
        });
    </script>

    <!-- Pricing Section -->
    <section id="pricing" class="py-24 px-4 bg-slate-50">
        <div class="max-w-7xl mx-auto">
            <div class="text-center max-w-2xl mx-auto mb-16 reveal">
                <h2 class="text-4xl font-black text-slate-900 mb-4">خطط اشتراك تناسب طموحكم</h2>
                <p class="text-slate-500 font-bold">ابدأ الآن بدون تكلفة إطلاق. خطط واضحة تضمن التحديثات الدورية دون
                    انقطاع.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 max-w-7xl mx-auto items-stretch">
                <?php 
                foreach ($plans as $idx => $plan): 
                    $isDefault = (bool)$plan['is_default'];
                    $isEnterprise = $plan['slug'] === 'enterprise';
                    // Parse features_list (one per line)
                    $displayItems = !empty($plan['features_list']) ? explode("\n", $plan['features_list']) : [];
                    $displayItems = array_map('trim', $displayItems);
                    $displayItems = array_filter($displayItems);
                ?>
                    <div class="<?= $isDefault ? 'bg-slate-900 text-white border-emerald-500 shadow-2xl shadow-emerald-900/40 transform md:-translate-y-4 ring-4 ring-emerald-500/10' : 'bg-white text-slate-800 border-slate-200 hover:shadow-lg' ?> rounded-[3rem] p-8 border text-right transition-all flex flex-col relative group">
                        
                        <?php if ($isDefault): ?>
                            <div class="absolute top-0 left-0 right-0 transform -translate-y-1/2 flex justify-center">
                                <span class="bg-emerald-500 text-white font-black text-[10px] tracking-widest uppercase px-6 py-2 rounded-full shadow-xl z-10 border-4 border-slate-900">الباقة الأكثر اختياراً</span>
                            </div>
                        <?php endif; ?>

                        <div class="mb-8 text-center">
                            <span class="<?= $isDefault ? 'text-emerald-400 bg-emerald-900/50' : ($isEnterprise ? 'text-slate-500 bg-slate-100' : 'text-emerald-600 bg-emerald-50') ?> font-black tracking-widest text-xs uppercase px-4 py-1.5 rounded-full inline-block">
                                <?= esc($plan['name']) ?>
                            </span>
                            <div class="mt-6 flex items-baseline gap-2 justify-center">
                                <?php if ($plan['price_monthly'] > 0): ?>
                                    <span class="text-5xl font-black <?= $isDefault ? 'text-white' : 'text-slate-900' ?>"><?= number_format($plan['price_monthly'], 0) ?></span>
                                    <span class="<?= $isDefault ? 'text-emerald-100/70' : 'text-slate-500' ?> font-bold text-sm">ر.س / شهرياً</span>
                                <?php else: ?>
                                    <span class="text-5xl font-black <?= $isDefault ? 'text-white' : 'text-slate-900' ?>">مجاناً</span>
                                    <span class="<?= $isDefault ? 'text-emerald-100/70' : 'text-slate-500' ?> font-bold text-sm">للفترة التجريبية</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <ul class="space-y-4 mb-8 flex-1">
                            <li class="flex items-center gap-3 text-sm font-bold <?= $isDefault ? 'text-emerald-50' : 'text-slate-600' ?>">
                                <i data-lucide="users" class="w-4 h-4 text-emerald-500"></i> 
                                <?= $plan['max_students'] > 1000 ? 'طلاب غير محدودين' : 'حتى ' . number_format($plan['max_students']) . ' طالب' ?>
                            </li>
                            <li class="flex items-center gap-3 text-sm font-bold <?= $isDefault ? 'text-emerald-50' : 'text-slate-600' ?>">
                                <i data-lucide="user-check" class="w-4 h-4 text-emerald-500"></i> 
                                <?= $plan['max_teachers'] > 100 ? 'معلمين غير محدودين' : 'حتى ' . number_format($plan['max_teachers']) . ' معلم' ?>
                            </li>

                            <?php if ($idx > 0): ?>
                                <li class="pt-4 border-t <?= $isDefault ? 'border-emerald-500/20' : 'border-slate-100' ?> flex flex-col gap-3 text-right">
                                    <span class="text-xs font-black <?= $isDefault ? 'text-emerald-400' : 'text-emerald-600' ?> uppercase tracking-tight">جميع مميزات باقة <?= esc($plans[$idx-1]['name']) ?>، بالإضافة إلى:</span>
                                </li>
                            <?php endif; ?>

                            <?php foreach ($displayItems as $item): ?>
                                <li class="flex items-start gap-3 text-sm font-bold <?= $isDefault ? 'text-emerald-50' : 'text-slate-600' ?>">
                                    <i data-lucide="check-circle-2" class="w-4 h-4 mt-0.5 <?= $isDefault ? 'text-emerald-400' : 'text-emerald-500' ?>"></i>
                                    <span><?= $item ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <?php if ($isEnterprise): ?>
                            <a href="contact.html" class="block text-center w-full py-3 rounded-full font-black text-sm border-2 border-slate-200 text-slate-700 hover:border-slate-800 transition">تواصل معنا</a>
                        <?php else: ?>
                            <a href="register.html?plan=<?= esc($plan['slug']) ?>" class="block text-center w-full py-4 rounded-full font-black text-sm <?= $isDefault ? 'emerald-gradient text-white hover:shadow-lg hover:shadow-emerald-500/30' : 'bg-slate-100 text-slate-700 hover:bg-emerald-50 hover:text-emerald-700' ?> transition transform hover:scale-[1.02]">
                                <?= $plan['price_monthly'] > 0 ? 'اشترك الآن' : 'ابدأ مجاناً' ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>


    <!-- Footer -->
    <footer class="bg-slate-950 pt-20 pb-10 px-4 relative origin-bottom overflow-hidden">
        <div class="absolute inset-0 opacity-10"
            style="background-image: linear-gradient(to right, #ffffff 1px, transparent 1px), linear-gradient(to bottom, #ffffff 1px, transparent 1px); background-size: 40px 40px;">
        </div>

        <div
            class="max-w-7xl mx-auto relative z-10 text-center lg:text-right flex flex-col lg:flex-row justify-between items-center gap-10 border-b border-white/10 pb-10">
            <div>
                <div class="flex items-center justify-center lg:justify-start gap-3 mb-4">
                    <div
                        class="w-10 h-10 emerald-gradient rounded-full flex items-center justify-center text-white shadow-lg">
                        <i data-lucide="activity" class="w-5 h-5"></i>
                    </div>
                    <span class="text-2xl font-black text-white leading-none">PE Smart</span>
                </div>
                <p class="text-slate-400 font-bold max-w-sm text-sm">أول وأقوى نظام متكامل لدعم مسيرة التحول الرقمي
                    للنشاطات الرياضية وفق رؤية وتطلعات المستقبل.</p>
            </div>

            <div class="flex flex-wrap justify-center lg:justify-end gap-8 text-sm font-bold text-slate-400">
                <a href="terms.html" class="hover:text-emerald-400 transition">شروط الخدمة</a>
                <a href="privacy.html" class="hover:text-emerald-400 transition">حماية البيانات PDPL</a>
                <a href="contact.html" class="hover:text-emerald-400 transition">فريق الدعم</a>
            </div>
        </div>

        <div
            class="max-w-7xl mx-auto relative z-10 pt-8 text-center text-slate-600 text-xs font-black tracking-widest uppercase flex flex-col md:flex-row items-center justify-between gap-4">
            <p>&copy; 2026 PE Smart School. All rights reserved.</p>
            <p class="italic lowercase text-[10px]">Built for the future of physical education. 🇸🇦</p>
        </div>
    </footer>

    <!-- Init -->
    <script>
        lucide.createIcons();

        // Mobile Menu Toggle
        function toggleMobileMenu() {
            const menu = document.getElementById('mobileMenu');
            const panel = document.getElementById('mobileMenuPanel');
            const isOpen = !menu.classList.contains('hidden');

            if (isOpen) {
                panel.classList.add('translate-x-full');
                setTimeout(() => {
                    menu.classList.add('hidden');
                    document.body.style.overflow = 'auto';
                }, 500);
            } else {
                menu.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
                setTimeout(() => {
                    panel.classList.remove('translate-x-full');
                }, 10);
            }
        }

        // Reveal Animation Observer
        const revealCb = (entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('active');
                }
            });
        };
        const observer = new IntersectionObserver(revealCb, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
        document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

        // Navbar Scroll effect
        window.addEventListener('scroll', () => {
            const nav = document.getElementById('navbar');
            if (window.scrollY > 50) {
                nav.classList.add('py-2', 'backdrop-blur-md');
                nav.classList.remove('py-4');
            } else {
                nav.classList.add('py-4');
                nav.classList.remove('py-2', 'backdrop-blur-md');
            }
        });

    </script>
</body>

</html>