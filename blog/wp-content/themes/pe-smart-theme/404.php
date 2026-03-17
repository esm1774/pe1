<?php get_header(); ?>

<div class="pe-container" style="padding-top:8rem;padding-bottom:5rem;">
    <div class="pe-404">
        <div>
            <div class="pe-404-icon">🔍</div>
            <h1>404</h1>
            <p>الصفحة التي تبحث عنها غير موجودة أو تم نقلها.</p>

            <!-- Search -->
            <form class="pe-search-form" style="max-width:400px;margin:0 auto 2rem;" action="<?php echo home_url('/'); ?>" method="get">
                <input type="search" name="s" placeholder="ابحث في المدونة..." value="<?php echo get_search_query(); ?>">
                <button type="submit">🔍</button>
            </form>

            <a href="<?php echo home_url('/'); ?>" class="btn-primary" style="display:inline-block;">🏠 العودة للمدونة</a>
        </div>
    </div>
</div>

<?php get_footer(); ?>
