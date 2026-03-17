<?php
require_once __DIR__ . '/config.php';

// SEO Metadata
$pageTitle = "المدونة والدروس | PE Smart School";
$pageDesc = "استكشف أحدث المقالات، الشروحات التعليمية، وأخبار منصة PE Smart School لتطوير الرياضة المدرسية.";

// Fetch Posts from DB
$db = getDB();

// Fetch active categories
$categories = $db->query("SELECT * FROM blog_categories ORDER BY name ASC")->fetchAll();

// Fetch published posts respecting schedule
$postsQuery = "
    SELECT * FROM blog_posts 
    WHERE status = 'published' AND (publish_at IS NULL OR publish_at <= NOW()) 
    ORDER BY sort_order DESC, COALESCE(publish_at, created_at) DESC
";
$posts = $db->query($postsQuery)->fetchAll();

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <meta name="description" content="<?php echo $pageDesc; ?>">
    
    <!-- Tailwind & Fonts -->
    <!-- Tailwind CSS (Production Optimization) -->
    <link rel="stylesheet" href="assets/css/main.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        body { font-family: 'Cairo', sans-serif; }
        .emerald-gradient { background: linear-gradient(135deg, #059669 0%, #10b981 100%); }
        .glass-panel {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .text-gradient {
            background: linear-gradient(135deg, #047857 0%, #34d399 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hover-lift { transition: all 0.3s ease; }
        .hover-lift:hover { transform: translateY(-5px); box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1); }
    </style>
</head>
<body class="bg-slate-50 text-slate-800">

    <!-- Header / Navbar -->
    <header class="sticky top-0 z-50 px-4 py-4">
        <nav class="max-w-7xl mx-auto glass-panel rounded-full px-6 py-3 flex items-center justify-between shadow-lg">
            <a href="index.php" class="flex items-center gap-3">
                <div class="w-10 h-10 emerald-gradient rounded-full flex items-center justify-center text-white shadow-md">
                    <i data-lucide="activity" class="w-5 h-5"></i>
                </div>
                <span class="text-xl font-black text-slate-800">PE Smart</span>
            </a>
            <div class="hidden md:flex items-center gap-6 text-sm font-bold">
                <a href="index.php#features" class="hover:text-emerald-600 transition">المقومات</a>
                <a href="index.php#blog" class="text-emerald-600">المدونة</a>
                <a href="index.php#pricing" class="hover:text-emerald-600 transition">الأسعار</a>
            </div>
            <a href="app.html" class="px-6 py-2 text-sm font-black text-white emerald-gradient rounded-full shadow-lg">دخول النظام</a>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="py-20 px-4">
        <div class="max-w-4xl mx-auto text-center">
            <h1 class="text-5xl md:text-6xl font-black text-slate-900 mb-6 leading-tight">الدروس <span class="text-gradient">والمقالات</span></h1>
            <p class="text-lg text-slate-500 font-bold mb-10">استكشف أدلة الاستخدام، نصائح الرياضة، وآخر تحديثات المنصة في مكان واحد.</p>
        </div>
    </section>

    <!-- Blog Grid -->
    <section class="pb-32 px-4">
        <div class="max-w-7xl mx-auto">
            <?php if (empty($posts)): ?>
                <div class="bg-white rounded-[3rem] p-20 text-center border border-slate-100 shadow-sm">
                    <i data-lucide="book-open" class="w-16 h-16 text-slate-200 mx-auto mb-6"></i>
                    <h2 class="text-2xl font-black text-slate-400">لا يوجد مقالات منشورة حالياً</h2>
                    <p class="text-slate-400 mt-2 font-bold">سنقوم بنشر دروس جديدة قريباً، تابعنا!</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <?php foreach ($posts as $post): ?>
                        <article class="hover-lift bg-white rounded-[2.5rem] overflow-hidden border border-slate-100 flex flex-col group shadow-sm">
                            <div class="h-52 bg-slate-100 relative overflow-hidden flex items-center justify-center">
                                <?php if ($post['image_path']): ?>
                                    <img src="<?php echo htmlspecialchars($post['image_path']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700">
                                <?php else: ?>
                                    <div class="text-6xl opacity-20">📰</div>
                                <?php endif; ?>
                                <div class="absolute top-4 right-4 bg-emerald-500 text-white text-[10px] font-black px-3 py-1 rounded-full shadow-lg z-10">
                                    <?php echo htmlspecialchars($post['category']); ?>
                                </div>
                            </div>
                            <div class="p-8 flex-1 flex flex-col">
                                <span class="text-[10px] text-slate-400 font-bold mb-2 uppercase tracking-widest">
                                    <?php echo date('d M, Y', strtotime($post['created_at'])); ?>
                                </span>
                                <h2 class="text-xl font-black text-slate-800 mb-3 group-hover:text-emerald-600 transition-colors">
                                    <a href="post.php?slug=<?php echo $post['slug']; ?>">
                                        <?php echo htmlspecialchars($post['title']); ?>
                                    </a>
                                </h2>
                                <p class="text-sm text-slate-500 font-bold mb-6 line-clamp-3 leading-relaxed">
                                    <?php echo htmlspecialchars($post['excerpt']); ?>
                                </p>
                                <div class="mt-auto">
                                    <a href="post.php?slug=<?php echo $post['slug']; ?>" class="text-emerald-600 font-black text-sm flex items-center gap-2 group/link">
                                        اقرأ المقال الكامل
                                        <i data-lucide="arrow-left" class="w-4 h-4 transform group-hover/link:-translate-x-1 transition-transform"></i>
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-slate-900 py-12 px-4 text-white">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 emerald-gradient rounded-full flex items-center justify-center text-white">
                    <i data-lucide="activity" class="w-4 h-4"></i>
                </div>
                <span class="text-xl font-black">PE Smart</span>
            </div>
            <p class="text-slate-400 text-sm font-bold">&copy; 2026 PE Smart School. جميع الحقوق محفوظة.</p>
        </div>
    </footer>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
