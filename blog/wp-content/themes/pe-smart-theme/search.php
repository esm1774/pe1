<?php get_header(); ?>

<div class="pe-container" style="padding-top:8rem;padding-bottom:5rem;">
    <section class="pe-page-hero" style="padding-top:3rem;padding-bottom:2rem;border-radius:var(--radius-xl);margin-bottom:2rem;">
        <h1>نتائج البحث عن: <span class="text-gradient"><?php echo get_search_query(); ?></span></h1>
    </section>

    <?php if (have_posts()): ?>
    <div class="pe-blog-grid pe-blog-grid-3" style="margin-top:2rem;">
        <?php while (have_posts()): the_post(); ?>
        <article class="pe-post-card reveal">
            <div class="pe-card-thumb">
                <?php if (has_post_thumbnail()): ?>
                    <?php the_post_thumbnail('medium_large'); ?>
                <?php else: ?>
                    <span class="pe-thumb-placeholder">📰</span>
                <?php endif; ?>
            </div>
            <div class="pe-card-body">
                <div class="pe-card-meta"><span><?php echo get_the_date('j M Y'); ?></span></div>
                <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                <p class="pe-card-excerpt"><?php echo wp_trim_words(get_the_excerpt(), 20, '...'); ?></p>
                <a href="<?php the_permalink(); ?>" class="pe-read-more">اقرأ المزيد <span class="pe-read-more-arrow">←</span></a>
            </div>
        </article>
        <?php endwhile; ?>
    </div>
    <?php pe_smart_pagination(); ?>
    <?php else: ?>
    <div style="text-align:center;padding:4rem;background:var(--slate-50);border-radius:var(--radius-xl);">
        <div style="font-size:3rem;margin-bottom:1rem;">🤷</div>
        <p style="color:var(--slate-500);font-weight:700;font-size:1.1rem;">لا توجد نتائج لـ "<?php echo get_search_query(); ?>"</p>
        <a href="<?php echo home_url('/'); ?>" class="btn-primary" style="display:inline-block;margin-top:1.5rem;">← العودة للمدونة</a>
    </div>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
