<?php get_header(); ?>

<!-- Page Hero -->
<section class="pe-page-hero">
    <!-- Animated Blobs -->
    <div class="blob" style="width:400px;height:400px;background:#6ee7b7;top:-100px;right:-100px;"></div>
    <div class="blob animate-blob animation-delay-2000" style="width:350px;height:350px;background:#5eead4;bottom:-80px;left:-80px;"></div>

    <div class="pe-container" style="position:relative;z-index:1;">
        <span class="pe-section-tag" style="font-size:0.75rem;">المدونة والدروس</span>
        <h1 style="font-size:clamp(2rem,5vw,3rem);font-weight:900;color:#0f172a;margin-bottom:0.75rem;">
            اكتشف أسرار <span class="text-gradient">التميز الرياضي</span>
        </h1>
        <p style="font-size:1.05rem;color:#64748b;font-weight:700;max-width:560px;margin:0 auto;">
            مقالات تعليمية، نصائح رياضية، وآخر تحديثات منصة PE Smart لنبقيك دائماً في الصدارة.
        </p>
    </div>
</section>

<!-- Blog Posts -->
<section class="pe-blog-section">
    <div class="pe-container">
        <?php if (have_posts()): ?>
        <div class="pe-blog-grid pe-blog-grid-3">
            <?php while (have_posts()): the_post(); ?>
            
            <article class="pe-post-card reveal">
                <!-- Thumbnail -->
                <div class="pe-card-thumb">
                    <?php if (has_post_thumbnail()): ?>
                        <?php the_post_thumbnail('medium_large'); ?>
                    <?php else: ?>
                        <span class="pe-thumb-placeholder">📰</span>
                    <?php endif; ?>

                    <?php
                    $cats = get_the_category();
                    if ($cats):
                    ?>
                    <span class="pe-card-category"><?php echo esc_html($cats[0]->name); ?></span>
                    <?php endif; ?>
                </div>

                <!-- Body -->
                <div class="pe-card-body">
                    <div class="pe-card-meta">
                        <span>✍️ <?php the_author(); ?></span>
                        <span><?php echo get_the_date('j M Y'); ?></span>
                    </div>

                    <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>

                    <p class="pe-card-excerpt">
                        <?php echo wp_trim_words(get_the_excerpt(), 20, '...'); ?>
                    </p>

                    <a href="<?php the_permalink(); ?>" class="pe-read-more">
                        اقرأ المزيد
                        <span class="pe-read-more-arrow">←</span>
                    </a>
                </div>
            </article>

            <?php endwhile; ?>
        </div>

        <!-- Pagination -->
        <?php pe_smart_pagination(); ?>

        <?php else: ?>
        <div style="text-align:center;padding:5rem 2rem;background:var(--slate-50);border-radius:var(--radius-xl);border:1px solid var(--slate-100);">
            <div style="font-size:4rem;margin-bottom:1rem;">📝</div>
            <p style="color:var(--slate-500);font-weight:700;font-size:1.1rem;">لا توجد مقالات منشورة حالياً.</p>
            <p style="color:var(--slate-400);font-size:0.9rem;margin-top:0.5rem;">سيتم نشر محتوى قريباً!</p>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php get_footer(); ?>
