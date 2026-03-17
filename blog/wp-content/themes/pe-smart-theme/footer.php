<!-- =====================================================
     FOOTER — داكن مطابق لـ welcome.php
===================================================== -->
<footer class="pe-footer">
    <div class="pe-footer-inner">
        <!-- العلامة التجارية -->
        <div class="pe-footer-brand">
            <a href="<?php echo esc_url(get_option('pe_smart_app_url', home_url('/'))); ?>" class="pe-logo" style="margin-bottom: 1rem;">
                <div class="pe-logo-icon">
                    <i data-lucide="activity" style="width:1.2rem; height:1.2rem;"></i>
                </div>
                <div class="pe-logo-text">
                    <span style="color: white;">PE Smart</span>
                    <span>SaaS Platform</span>
                </div>
            </a>
            <p>المنصة السحابية الأولى لرقمنة التربية البدنية في المدارس والمجمعات التعليمية.</p>
        </div>

        <!-- روابط سريعة -->
        <div class="pe-footer-col">
            <h4>المنصة</h4>
            <?php
            wp_nav_menu([
                'theme_location' => 'footer',
                'container'      => false,
                'fallback_cb'    => false,
            ]);
            ?>
        </div>

        <!-- روابط قانونية -->
        <div class="pe-footer-col">
            <h4>قانوني</h4>
            <ul>
                <li><a href="<?php echo esc_url($app_base); ?>/privacy.html" target="_blank">سياسة الخصوصية</a></li>
                <li><a href="<?php echo esc_url($app_base); ?>/terms.html" target="_blank">شروط الاستخدام</a></li>
                <li><a href="<?php echo esc_url($app_base); ?>/contact.html" target="_blank">تواصل معنا</a></li>
            </ul>
        </div>
    </div>

    <div class="pe-footer-bottom">
        <p>© <?php echo date('Y'); ?> PE Smart School. جميع الحقوق محفوظة.</p>
        <p>مدعوم بـ WordPress مع ثيم PE Smart المخصص</p>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
