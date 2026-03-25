<?php
require_once __DIR__ . '/config.php';

// Get Slug
$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header("Location: blog.php");
    exit;
}

// Fetch Post from DB
$db = getDB();
$user_role = $_SESSION['user_role'] ?? 'guest';

$stmt = $db->prepare("
    SELECT * FROM blog_posts 
    WHERE slug = ? 
    AND (? = 'admin' OR (status = 'published' AND (publish_at IS NULL OR publish_at <= NOW()))) 
    LIMIT 1
");
$stmt->execute([$slug, $user_role]);
$post = $stmt->fetch();

if (!$post) {
    die("المقال غير موجود أو غير منشور حالياً.");
}

// Increment Views (if not admin and not already viewed in this session)
if ($user_role !== 'admin' && !isset($_SESSION['viewed_post_' . $post['id']])) {
    $db->prepare("UPDATE blog_posts SET views = views + 1 WHERE id = ?")->execute([$post['id']]);
    $post['views'] = ($post['views'] ?? 0) + 1; // Update locally for display
    $_SESSION['viewed_post_' . $post['id']] = true;
}

// SEO Metadata
$pageTitle = $post['title'] . " | PE Smart School";
$pageDesc = $post['excerpt'] ?? mb_substr(strip_tags($post['content']), 0, 160);
$pageImage = $post['image_path'] ?? (BASE_URL . '/assets/img/default-blog.jpg');

// Check Reading Time
$wordCount = str_word_count(strip_tags($post['content']));
$readTimeMinutes = max(1, ceil($wordCount / 200)); // Average 200 words/min

// Related Posts
$relatedStmt = $db->prepare("
    SELECT * FROM blog_posts 
    WHERE category = ? AND id != ? AND status = 'published' AND (publish_at IS NULL OR publish_at <= NOW())
    ORDER BY sort_order DESC, COALESCE(publish_at, created_at) DESC LIMIT 2
");
$relatedStmt->execute([$post['category'], $post['id']]);
$relatedPosts = $relatedStmt->fetchAll();


?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($pageDesc); ?>">
    
    <!-- Open Graph / Social Media -->
    <meta property="og:type" content="article">
    <meta property="og:title" content="<?php echo htmlspecialchars($pageTitle); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($pageDesc); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($pageImage); ?>">
    <meta property="og:url" content="<?php echo BASE_URL . '/post.php?slug=' . $post['slug']; ?>">

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
        article img { border-radius: 2rem; margin: 2rem 0; width: 100%; box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1); }
        article h2 { font-size: 1.875rem; font-weight: 800; margin-top: 2.5rem; margin-bottom: 1.25rem; color: #1e293b; }
        article p { line-height: 1.8; margin-bottom: 1.5rem; font-weight: 500; color: #475569; }
        article ol { list-style-type: decimal; padding-inline-start: 1.5rem; margin-bottom: 1.5rem; }
        article ul { list-style-type: disc; padding-inline-start: 1.5rem; margin-bottom: 1.5rem; }
        article li { margin-bottom: 0.5rem; line-height: 1.8; color: #475569; }
        article blockquote { border-right: 4px solid #10b981; padding: 1rem 1.5rem; margin: 2rem 0; background: #f8fafc; font-style: italic; }
    </style>
</head>
<body class="bg-white text-slate-800">

    <!-- Header / Navbar -->
    <header class="px-4 py-4">
        <nav class="max-w-7xl mx-auto glass-panel rounded-full px-6 py-3 flex items-center justify-between shadow-lg">
            <a href="index.php" class="flex items-center gap-3">
                <div class="w-10 h-10 emerald-gradient rounded-full flex items-center justify-center text-white shadow-md">
                    <i data-lucide="activity" class="w-5 h-5"></i>
                </div>
                <div class="flex flex-col text-left">
                    <span class="text-xl font-black text-slate-800 leading-none">PE Smart</span>
                    <span class="text-[7px] font-black tracking-[0.2em] text-emerald-600 uppercase">Smart Solution</span>
                </div>
            </a>
            <div class="hidden md:flex items-center gap-6 text-sm font-bold">
                <a href="blog.php" class="flex items-center gap-2 text-emerald-600">
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    العودة للمدونة
                </a>
            </div>
            <a href="index.php" class="px-6 py-2 text-sm font-black text-slate-800 bg-slate-100 rounded-full">الرئيسية</a>
        </nav>
    </header>

    <!-- Post Content -->
    <main class="py-16 px-4">
        <article class="max-w-3xl mx-auto">
            <!-- Breadcrumbs -->
            <div class="flex items-center gap-2 text-sm font-bold text-slate-400 mb-8">
                <a href="blog.php" class="hover:text-emerald-600 transition">المدونة</a>
                <i data-lucide="chevron-left" class="w-4 h-4"></i>
                <span class="text-emerald-500"><?php echo htmlspecialchars($post['category']); ?></span>
            </div>

            <!-- Title -->
            <h1 class="text-4xl md:text-5xl font-black text-slate-900 mb-6 leading-tight">
                <?php echo htmlspecialchars($post['title']); ?>
            </h1>

            <!-- Meta -->
            <div class="flex items-center gap-6 mb-12 py-6 border-y border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center text-slate-400">
                        <i data-lucide="user" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">الكاتب</p>
                        <p class="text-sm font-black text-slate-700">إدارة المنصة</p>
                    </div>
                </div>
                <div>
                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">تاريخ النشر</p>
                    <p class="text-sm font-black text-slate-700"><?php echo date('d M, Y', strtotime($post['created_at'])); ?></p>
                </div>
                <div class="mr-auto flex items-center gap-4 text-slate-400">
                    <span class="flex items-center gap-1" title="وقت القراءة المتوقع">
                        <i data-lucide="clock" class="w-4 h-4"></i>
                        <span class="text-xs font-bold"><?php echo $readTimeMinutes; ?> دقائق حد أقصى للقراءة</span>
                    </span>
                    <span class="flex items-center gap-1" title="عدد المشاهدات">
                        <i data-lucide="eye" class="w-4 h-4"></i>
                        <span class="text-xs font-bold"><?php echo number_format($post['views'] ?? 0); ?> قراءة</span>
                    </span>
                </div>
            </div>

            <!-- Featured Image -->
            <?php if ($post['image_path']): ?>
                <img src="<?php echo htmlspecialchars($post['image_path']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>" class="w-full h-auto">
            <?php endif; ?>

            <!-- Content -->
                <?php 
                    // Content from Quill.js is HTML, no nl2br needed
                    echo $post['content']; 
                ?>
            </div>

            <!-- Related Posts -->
            <?php if (!empty($relatedPosts)): ?>
            <section class="mt-20 pt-10 border-t border-slate-100">
                <h3 class="text-2xl font-black text-slate-800 mb-8">مقالات ذات صلة بـ "<?php echo htmlspecialchars($post['category']); ?>"</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($relatedPosts as $rp): ?>
                        <a href="post.php?slug=<?php echo $rp['slug']; ?>" class="group block bg-white border border-slate-100 rounded-3xl p-4 hover:shadow-xl transition-all duration-300">
                            <div class="aspect-[16/9] mb-4 overflow-hidden rounded-2xl relative">
                                <img src="<?php echo htmlspecialchars($rp['image_path'] ?: 'assets/img/default-blog.jpg'); ?>" alt="<?php echo htmlspecialchars($rp['title']); ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                                <div class="absolute top-3 right-3 bg-white/90 backdrop-blur text-emerald-600 text-xs font-bold px-3 py-1 rounded-full shadow-sm">
                                    <?php echo htmlspecialchars($rp['category']); ?>
                                </div>
                            </div>
                            <h4 class="text-lg font-bold text-slate-800 group-hover:text-emerald-600 transition line-clamp-2"><?php echo htmlspecialchars($rp['title']); ?></h4>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
            <!-- Share Section -->
            <section class="mt-20 p-10 bg-slate-50 rounded-[2.5rem] border border-slate-100 text-center">
                <h3 class="text-xl font-black text-slate-800 mb-6">أعجبك المقال؟ شاركه مع زملائك</h3>
                <div class="flex items-center justify-center gap-4">
                    <button class="w-12 h-12 flex items-center justify-center rounded-full bg-blue-600 text-white shadow-lg hover:-translate-y-1 transition">
                        <i data-lucide="facebook" class="w-6 h-6"></i>
                    </button>
                    <button class="w-12 h-12 flex items-center justify-center rounded-full bg-sky-500 text-white shadow-lg hover:-translate-y-1 transition">
                        <i data-lucide="twitter" class="w-6 h-6"></i>
                    </button>
                    <button class="w-12 h-12 flex items-center justify-center rounded-full bg-slate-800 text-white shadow-lg hover:-translate-y-1 transition">
                        <i data-lucide="link" class="w-6 h-6"></i>
                    </button>
                </div>
            </section>
        </article>
    </main>

    <!-- Footer -->
    <footer class="bg-slate-900 py-12 px-4 text-white mt-20">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row items-center justify-between gap-6">
            <div class="flex items-center gap-3 text-white">
                <div class="w-8 h-8 emerald-gradient rounded-full flex items-center justify-center">
                    <i data-lucide="activity" class="w-4 h-4 text-white"></i>
                </div>
                <div class="flex flex-col text-left">
                    <span class="text-lg font-black leading-none text-white">PE Smart</span>
                    <span class="text-[6px] font-black tracking-[0.2em] text-emerald-400 uppercase">Smart Solution</span>
                </div>
            </div>
            <p class="text-slate-400 text-sm font-bold">&copy; 2026 PE Smart School. جميع الحقوق محفوظة.</p>
        </div>
    </footer>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
