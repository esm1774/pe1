<?php get_header(); ?>

<!-- Archive Hero -->
<section class="pe-page-hero">
    <div class="pe-container" style="position:relative;z-index:1;">
        <span class="pe-section-tag">
            <?php
            if (is_category()) echo 'تصنيف: ' . single_cat_title('', false);
            elseif (is_tag()) echo 'وسم: ' . single_tag_title('', false);
            elseif (is_author()) echo 'مقالات الكاتب: ' . get_the_author();
            elseif (is_date()) echo 'أرشيف: ' . get_the_date('F Y');
            else echo 'الأرشيف';
            ?>
        </span>
        <h1>
            <?php
            if (is_category() || is_tag() || is_author()) the_archive_title();
            else echo get_the_date('F Y');
            ?>
        </h1>
        <?php if (category_description()): ?>
        <p><?php echo category_description(); ?></p>
        <?php endif; ?>
    </div>
</section>

<!-- Posts Grid -->
<section class="pe-blog-section">
    <div class="pe-container">
        <?php if (have_posts()): ?>
        <div class="pe-blog-grid pe-blog-grid-3">
            <?php while (have_posts()): the_post(); ?>
            <article class="pe-post-card reveal">
                <div class="pe-card-thumb">
                    <?php if (has_post_thumbnail()): ?>
                        <?php the_post_thumbnail('medium_large'); ?>
                    <?php else: ?>
                        <span class="pe-thumb-placeholder">📰</span>
                    <?php endif; ?>
                    <?php $cats = get_the_category(); if ($cats): ?>
                    <span class="pe-card-category"><?php echo esc_html($cats[0]->name); ?></span>
                    <?php endif; ?>
                </div>
                <div class="pe-card-body">
                    <div class="pe-card-meta">
                        <span>✍️ <?php the_author(); ?></span>
                        <span><?php echo get_the_date('j M Y'); ?></span>
                    </div>
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
            <p style="color:var(--slate-500);font-weight:700;font-size:1.1rem;">لا توجد مقالات في هذا القسم.</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php get_footer(); ?>
