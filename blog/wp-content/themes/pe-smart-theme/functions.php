<?php
/**
 * PE Smart Theme — functions.php
 * إعداد الثيم وتحميل الخطوط والسكريبتات
 */

// ============================================================
// إعداد الثيم الأساسي
// ============================================================
function pe_smart_setup() {
    // دعم RTL واللغة
    load_theme_textdomain('pe-smart', get_template_directory() . '/languages');
    
    // دعم الصور المميزة للمقالات
    add_theme_support('post-thumbnails');
    set_post_thumbnail_size(800, 400, true);
    
    // عنوان الصفحة الديناميكي
    add_theme_support('title-tag');
    
    // HTML5
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption']);
    
    // تسجيل قائمة التنقل
    register_nav_menus([
        'primary' => 'القائمة الرئيسية',
        'footer'  => 'قائمة الفوتر',
    ]);
}
add_action('after_setup_theme', 'pe_smart_setup');


// ============================================================
// تحميل الأنماط والسكريبتات
// ============================================================
function pe_smart_enqueue() {
    // نمط الثيم الرئيسي
    wp_enqueue_style(
        'pe-smart-style',
        get_stylesheet_uri(),
        [],
        '1.0.0'
    );

    // Lucide Icons (نفس المكتبة المستخدمة في welcome.php)
    wp_enqueue_script(
        'lucide-icons',
        'https://unpkg.com/lucide@latest',
        [],
        null,
        false
    );

    // ربط الأيقونات بعد تحميل الصفحة
    wp_add_inline_script('lucide-icons', 'document.addEventListener("DOMContentLoaded", function() { if (typeof lucide !== "undefined") lucide.createIcons(); });', 'after');

    // تأثير Reveal (انزلاق عند التمرير)
    wp_add_inline_script('lucide-icons', '
    document.addEventListener("DOMContentLoaded", function() {
        // Reveal on scroll
        const reveals = document.querySelectorAll(".reveal");
        if (reveals.length) {
            const io = new IntersectionObserver((entries) => {
                entries.forEach(e => { if (e.isIntersecting) e.target.classList.add("active"); });
            }, { threshold: 0.1 });
            reveals.forEach(el => io.observe(el));
        }

        // Mobile menu toggle
        const mobileToggle = document.getElementById("pe-mobile-toggle");
        const mobileMenu = document.getElementById("pe-mobile-menu");
        if (mobileToggle && mobileMenu) {
            mobileToggle.addEventListener("click", () => {
                mobileMenu.classList.toggle("hidden");
            });
        }
    });
    ', 'after');
}
add_action('wp_enqueue_scripts', 'pe_smart_enqueue');


// ============================================================
// تسجيل Widgets (Sidebar)
// ============================================================
function pe_smart_widgets_init() {
    register_sidebar([
        'name'          => 'الشريط الجانبي',
        'id'            => 'sidebar-1',
        'description'   => 'الشريط الجانبي للمدونة',
        'before_widget' => '<div class="pe-sidebar-widget">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="pe-sidebar-widget-title">',
        'after_title'   => '</h3>',
    ]);
}
add_action('widgets_init', 'pe_smart_widgets_init');


// ============================================================
// إضافة CTA تلقائياً بعد كل مقال
// ============================================================
function pe_smart_auto_cta($content) {
    if (is_single() && in_the_loop() && is_main_query()) {
        $app_url    = get_option('pe_smart_app_url', 'https://pesmartschool.com/register');
        $cta_title  = get_option('pe_smart_cta_title', 'جرّب PE Smart School مجاناً');
        $cta_text   = get_option('pe_smart_cta_text', 'النظام الإداري الشامل لإدارة التربية البدنية بكل كفاءة');
        $cta_btn    = get_option('pe_smart_cta_btn', '🏫 ابدأ تجربتك المجانية');

        $cta = '
        <div class="pe-cta-box">
            <h3>' . esc_html($cta_title) . '</h3>
            <p>' . esc_html($cta_text) . '</p>
            <a href="' . esc_url($app_url) . '" class="pe-cta-btn" target="_blank">' . esc_html($cta_btn) . '</a>
        </div>';

        $content .= $cta;
    }
    return $content;
}
add_filter('the_content', 'pe_smart_auto_cta');


// ============================================================
// صفحة إعدادات الثيم (في لوحة WordPress)
// ============================================================
function pe_smart_settings_page() {
    add_options_page(
        'إعدادات PE Smart Theme',
        'PE Smart Theme',
        'manage_options',
        'pe-smart-settings',
        'pe_smart_settings_render'
    );
}
add_action('admin_menu', 'pe_smart_settings_page');

function pe_smart_settings_render() {
    if (isset($_POST['pe_smart_save'])) {
        check_admin_referer('pe_smart_settings_nonce');
        update_option('pe_smart_app_url',  sanitize_url($_POST['pe_smart_app_url']));
        update_option('pe_smart_cta_title', sanitize_text_field($_POST['pe_smart_cta_title']));
        update_option('pe_smart_cta_text',  sanitize_text_field($_POST['pe_smart_cta_text']));
        update_option('pe_smart_cta_btn',   sanitize_text_field($_POST['pe_smart_cta_btn']));
        echo '<div class="notice notice-success"><p>✅ تم الحفظ بنجاح!</p></div>';
    }
    ?>
    <div class="wrap" dir="rtl" style="font-family: 'Cairo', sans-serif;">
        <h1>⚙️ إعدادات PE Smart Theme</h1>
        <form method="post">
            <?php wp_nonce_field('pe_smart_settings_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th>رابط تسجيل التطبيق</th>
                    <td><input type="url" name="pe_smart_app_url" class="regular-text" value="<?= esc_url(get_option('pe_smart_app_url', 'https://pesmartschool.com/register')) ?>"></td>
                </tr>
                <tr>
                    <th>عنوان CTA</th>
                    <td><input type="text" name="pe_smart_cta_title" class="regular-text" value="<?= esc_attr(get_option('pe_smart_cta_title', 'جرّب PE Smart School مجاناً')) ?>"></td>
                </tr>
                <tr>
                    <th>نص CTA</th>
                    <td><input type="text" name="pe_smart_cta_text" class="regular-text" value="<?= esc_attr(get_option('pe_smart_cta_text', 'النظام الإداري الشامل لإدارة التربية البدنية بكل كفاءة')) ?>"></td>
                </tr>
                <tr>
                    <th>نص زر CTA</th>
                    <td><input type="text" name="pe_smart_cta_btn" class="regular-text" value="<?= esc_attr(get_option('pe_smart_cta_btn', '🏫 ابدأ تجربتك المجانية')) ?>"></td>
                </tr>
            </table>
            <input type="submit" name="pe_smart_save" class="button button-primary" value="حفظ الإعدادات">
        </form>
    </div>
    <?php
}


// ============================================================
// Pagination مخصصة
// ============================================================
function pe_smart_pagination() {
    global $wp_query;
    $pages = paginate_links([
        'total'     => $wp_query->max_num_pages,
        'current'   => max(1, get_query_var('paged')),
        'format'    => '?paged=%#%',
        'prev_text' => '→',
        'next_text' => '←',
        'type'      => 'array',
    ]);

    if ($pages) {
        echo '<div class="pe-pagination">';
        foreach ($pages as $page) echo $page;
        echo '</div>';
    }
}


// ============================================================
// Helper: استخراج زمن القراءة التقريبي
// ============================================================
function pe_reading_time() {
    $word_count = str_word_count(strip_tags(get_the_content()));
    $minutes = ceil($word_count / 200);
    return $minutes . ' دقائق قراءة';
}
