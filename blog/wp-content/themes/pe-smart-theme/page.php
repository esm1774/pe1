<?php get_header(); ?>

<?php while (have_posts()): the_post(); ?>

<nav class="pe-breadcrumb">
    <a href="<?php echo home_url('/'); ?>">الرئيسية</a>
    <span class="sep">/</span>
    <span style="color:var(--slate-600);"><?php the_title(); ?></span>
</nav>

<div class="pe-container" style="padding-top:1rem;padding-bottom:5rem;max-width:860px;">
    <article>
        <h1 style="font-size:clamp(1.8rem,4vw,2.75rem);font-weight:900;color:var(--slate-900);margin-bottom:2rem;line-height:1.3;">
            <?php the_title(); ?>
        </h1>

        <?php if (has_post_thumbnail()): ?>
        <div class="pe-single-featured-img" style="margin-bottom:2.5rem;">
            <?php the_post_thumbnail('large'); ?>
        </div>
        <?php endif; ?>

        <div class="pe-entry-content">
            <?php the_content(); ?>
        </div>
    </article>
</div>

<?php endwhile; ?>

<?php get_footer(); ?>
