<!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl" class="scroll-smooth">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#10b981">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
    <?php wp_head(); ?>
</head>
<body <?php body_class('bg-slate-50'); ?>>
<?php wp_body_open(); ?>

<!-- =====================================================
     NAVBAR — زجاجي عائم مطابق لـ welcome.php
===================================================== -->
<header id="pe-navbar">
    <nav>
        <!-- Logo -->
        <a href="<?php echo esc_url(get_option('pe_smart_app_url', home_url('/'))); ?>" class="pe-logo">
            <div class="pe-logo-icon">
                <i data-lucide="activity"></i>
            </div>
            <div class="pe-logo-text">
                <span>PE Smart</span>
                <span>SaaS Platform</span>
            </div>
        </a>

        <!-- Desktop Navigation -->
        <?php
        wp_nav_menu([
            'theme_location' => 'primary',
            'container'      => false,
            'menu_class'     => 'pe-nav-links',
            'fallback_cb'    => false,
        ]);
        ?>

        <!-- CTA Buttons -->
        <div class="pe-nav-cta">
            <a href="<?php echo esc_url(get_option('pe_smart_app_url', 'https://pesmartschool.com')); ?>/index.html"
               class="btn-ghost" target="_blank">
                تسجيل الدخول
            </a>
            <a href="<?php echo esc_url(get_option('pe_smart_app_url', 'https://pesmartschool.com')); ?>/register.html"
               class="btn-primary" target="_blank">
                تأسيس مدرستك
            </a>
            <!-- Mobile Toggle -->
            <button id="pe-mobile-toggle" class="pe-mobile-toggle" aria-label="القائمة">
                <i data-lucide="menu"></i>
            </button>
        </div>
    </nav>

    <!-- Mobile Menu -->
    <div id="pe-mobile-menu" class="hidden">
        <div class="pe-mobile-sidebar">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-weight: 900; font-size: 1.1rem; color: #059669;">القائمة</span>
                <button id="pe-mobile-close" onclick="document.getElementById('pe-mobile-menu').classList.add('hidden')"
                    style="width: 2.5rem; height: 2.5rem; border: none; background: #f8fafc; border-radius: 0.75rem; cursor: pointer; font-size: 1.25rem; color: #94a3b8;">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <nav class="pe-mobile-nav">
                <?php
                wp_nav_menu([
                    'theme_location' => 'primary',
                    'container'      => false,
                    'menu_class'     => 'pe-mobile-ul',
                    'fallback_cb'    => false,
                ]);
                ?>
                <a href="<?php echo esc_url(get_option('pe_smart_app_url', 'https://pesmartschool.com')); ?>/register.html"
                   class="btn-mobile-cta">
                    تأسيس مدرستك
                </a>
            </nav>
        </div>
    </div>
</header>
