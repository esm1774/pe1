<?php get_header(); ?>

<?php while (have_posts()): the_post(); ?>

<!-- Breadcrumb -->
<nav class="pe-breadcrumb">
    <a href="<?php echo home_url('/'); ?>">الرئيسية</a>
    <span class="sep">/</span>
    <?php
    $cats = get_the_category();
    if ($cats): ?>
        <a href="<?php echo get_category_link($cats[0]->term_id); ?>"><?php echo esc_html($cats[0]->name); ?></a>
        <span class="sep">/</span>
    <?php endif; ?>
    <span style="color:var(--slate-600);"><?php the_title(); ?></span>
</nav>

<!-- Single Article -->
<div class="pe-container">
    <div class="pe-single-wrapper">
        <header class="pe-single-header">
            <!-- Category Badge -->
            <?php if ($cats): ?>
            <span class="pe-single-category"><?php echo esc_html($cats[0]->name); ?></span>
            <?php endif; ?>

            <!-- Title -->
            <h1 class="pe-single-title"><?php the_title(); ?></h1>

            <!-- Meta -->
            <div class="pe-single-meta">
                <span>✍️ <?php the_author(); ?></span>
                <span>📅 <?php echo get_the_date('j F Y'); ?></span>
                <span>⏱️ <?php echo pe_reading_time(); ?></span>
                <?php
                $comments_num = get_comments_number();
                if ($comments_num > 0):
                ?>
                <span>💬 <?php echo $comments_num; ?> تعليق</span>
                <?php endif; ?>
            </div>
        </header>

        <!-- Featured Image -->
        <?php if (has_post_thumbnail()): ?>
        <div class="pe-single-featured-img">
            <?php the_post_thumbnail('large'); ?>
        </div>
        <?php endif; ?>

        <!-- Content -->
        <div class="pe-entry-content">
            <?php the_content(); ?>
        </div>

        <!-- Tags -->
        <?php $tags = get_the_tags(); if ($tags): ?>
        <div style="margin-top:2.5rem;padding-top:1.5rem;border-top:1px solid var(--slate-100);display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;">
            <span style="font-weight:900;color:var(--slate-500);font-size:0.875rem;">🏷️ الوسوم:</span>
            <?php foreach ($tags as $tag): ?>
            <a href="<?php echo get_tag_link($tag->term_id); ?>"
               style="background:var(--slate-100);color:var(--slate-600);padding:0.3rem 0.75rem;border-radius:9999px;font-size:0.8rem;font-weight:700;transition:all 0.2s;"
               onmouseover="this.style.background='#d1fae5';this.style.color='#059669'"
               onmouseout="this.style.background='#f1f5f9';this.style.color='#475569'">
                <?php echo esc_html($tag->name); ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Author Box -->
        <div style="margin-top:3rem;background:var(--slate-50);border:1px solid var(--slate-100);border-radius:var(--radius-xl);padding:2rem;display:flex;gap:1.5rem;align-items:flex-start;">
            <div style="flex-shrink:0;">
                <?php echo get_avatar(get_the_author_meta('ID'), 64, '', '', ['style' => 'border-radius:50%;']); ?>
            </div>
            <div>
                <p style="font-size:0.7rem;font-weight:900;color:var(--emerald-600);text-transform:uppercase;letter-spacing:0.1em;margin-bottom:0.25rem;">كاتب المقال</p>
                <h4 style="font-weight:900;color:var(--slate-800);font-size:1.1rem;margin-bottom:0.5rem;"><?php the_author(); ?></h4>
                <p style="font-size:0.875rem;color:var(--slate-500);font-weight:700;"><?php echo esc_html(get_the_author_meta('description')); ?></p>
            </div>
        </div>

        <!-- Post Navigation -->
        <nav style="margin-top:2.5rem;display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <?php
            $prev = get_previous_post();
            $next = get_next_post();
            ?>
            <?php if ($prev): ?>
            <a href="<?php echo get_permalink($prev); ?>"
               style="background:var(--slate-50);border:1px solid var(--slate-100);border-radius:var(--radius-lg);padding:1.25rem;display:block;transition:all 0.2s;"
               onmouseover="this.style.borderColor='#34d399'" onmouseout="this.style.borderColor='#f1f5f9'">
                <span style="font-size:0.7rem;font-weight:900;color:var(--emerald-600);text-transform:uppercase;display:block;margin-bottom:0.4rem;">→ المقال السابق</span>
                <span style="font-weight:900;color:var(--slate-800);font-size:0.9rem;"><?php echo esc_html($prev->post_title); ?></span>
            </a>
            <?php else: ?><div></div><?php endif; ?>

            <?php if ($next): ?>
            <a href="<?php echo get_permalink($next); ?>"
               style="background:var(--slate-50);border:1px solid var(--slate-100);border-radius:var(--radius-lg);padding:1.25rem;display:block;text-align:left;transition:all 0.2s;"
               onmouseover="this.style.borderColor='#34d399'" onmouseout="this.style.borderColor='#f1f5f9'">
                <span style="font-size:0.7rem;font-weight:900;color:var(--emerald-600);text-transform:uppercase;display:block;margin-bottom:0.4rem;">المقال التالي ←</span>
                <span style="font-weight:900;color:var(--slate-800);font-size:0.9rem;"><?php echo esc_html($next->post_title); ?></span>
            </a>
            <?php else: ?><div></div><?php endif; ?>
        </nav>

        <!-- Comments -->
        <?php if (comments_open() || get_comments_number()): ?>
        <div style="margin-top:3rem;">
            <?php comments_template(); ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endwhile; ?>

<?php get_footer(); ?>
