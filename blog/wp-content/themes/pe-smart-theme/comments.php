<?php
/**
 * The template for displaying comments
 */

if ( post_password_required() ) {
	return;
}
?>

<div id="comments" class="pe-comments-area">

	<?php if ( have_comments() ) : ?>
		<h3 class="pe-comments-title">
			<span class="pe-comments-icon">💬</span>
			<?php
			$comments_number = get_comments_number();
			if ( '1' === $comments_number ) {
				printf( _x( 'تعليق واحد على "%s"', 'comments title', 'pe-smart' ), get_the_title() );
			} else {
				printf(
					_nx(
						'%1$s تعليق على "%2$s"',
						'%1$s تعليقات على "%2$s"',
						$comments_number,
						'comments title',
						'pe-smart'
					),
					number_format_i18n( $comments_number ),
					get_the_title()
				);
			}
			?>
		</h3>

		<ul class="pe-comment-list">
			<?php
			wp_list_comments( array(
				'style'       => 'ul',
				'short_ping'  => true,
				'avatar_size' => 64,
				'callback'    => 'pe_smart_comment'
			) );
			?>
		</ul>

		<?php if ( get_comment_pages_count() > 1 && get_option( 'page_comments' ) ) : ?>
			<nav class="pe-comment-navigation" role="navigation">
				<div class="nav-previous"><?php previous_comments_link( __( '&larr; تعليقات أقدم', 'pe-smart' ) ); ?></div>
				<div class="nav-next"><?php next_comments_link( __( 'تعليقات أحدث &rarr;', 'pe-smart' ) ); ?></div>
			</nav>
		<?php endif; ?>

	<?php endif; // have_comments() ?>

	<?php if ( ! comments_open() && get_comments_number() && post_type_supports( get_post_type(), 'comments' ) ) : ?>
		<p class="no-comments"><?php _e( 'التعليقات مغلقة حالياً.', 'pe-smart' ); ?></p>
	<?php endif; ?>

	<?php
	comment_form( array(
		'title_reply'        => __( 'أضف رداً جديداً', 'pe-smart' ),
		'title_reply_to'     => __( 'رد على %s', 'pe-smart' ),
		'cancel_reply_link'  => __( 'إلغاء الرد', 'pe-smart' ),
		'label_submit'       => __( 'إرسال التعليق', 'pe-smart' ),
		'class_submit'       => 'submit pe-btn-primary',
		'submit_button'      => '<button name="%1$s" type="submit" id="%2$s" class="%3$s">%4$s</button>',
        'submit_field'       => '<p class="form-submit">%1$s %2$s</p>',
        'comment_field'      => '<p class="comment-form-comment"><label for="comment">' . _x( 'التعليق', 'noun', 'pe-smart' ) . '</label><textarea id="comment" name="comment" cols="45" rows="8" aria-required="true" placeholder="اكتب تعليقك هنا..."></textarea></p>',
	) );
	?>

</div>
