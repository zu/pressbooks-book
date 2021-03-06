<?php
/**
 * @author  Pressbooks <code@pressbooks.com>
 * @license GPLv2 (or any later version)
 */

use Pressbooks\Container;
use PressbooksMix\Assets;

// Turn off admin bar
add_filter( 'show_admin_bar', function () { // @codingStandardsIgnoreLine
	return false;
} );

/**
 * Set up array of metadata keys for display in web book footer.
 */
global $metakeys;
$metakeys = [
	'pb_author' => __( 'Author', 'pressbooks-book' ),
	'pb_contributing_authors' => __( 'Contributing Author', 'pressbooks-book' ),
	'pb_publisher'  => __( 'Publisher', 'pressbooks-book' ),
	'pb_print_isbn'  => __( 'Print ISBN', 'pressbooks-book' ),
	'pb_keywords_tags'  => __( 'Keywords/Tags', 'pressbooks-book' ),
	'pb_publication_date'  => __( 'Publication Date', 'pressbooks-book' ),
	'pb_hashtag'  => __( 'Hashtag', 'pressbooks-book' ),
	'pb_ebook_isbn'  => __( 'Ebook ISBN', 'pressbooks-book' ),
];

/* ------------------------------------------------------------------------ *
 * Scripts and styles for Book Info Page (cover page)
 * ------------------------------------------------------------------------ */

function pressbooks_book_info_page() {
	$assets = new Assets( 'pressbooks-book', 'theme' );
	$assets->setSrcDirectory( 'assets' )->setDistDirectory( 'dist' );

	if ( is_front_page() ) {
		wp_enqueue_style( 'pressbooks-book-info', $assets->getPath( 'styles/book-info.css' ), [], null, 'all' );
		wp_enqueue_style( 'book-info-fonts', 'https://fonts.googleapis.com/css?family=Droid+Serif:400,700|Oswald:300,400,700' );

		// Book info page Table of Content columns
		wp_enqueue_script( 'columnizer',  $assets->getPath( 'scripts/columnizer.js' ), [ 'jquery' ], null );

		// Sharer.js
		wp_enqueue_script( 'sharer', $assets->getPath( 'scripts/sharer.js' ) );
	}
}
add_action( 'wp_enqueue_scripts', 'pressbooks_book_info_page' );

/* ------------------------------------------------------------------------ *
 * Asyncronous loading to improve speed of page load
 * ------------------------------------------------------------------------ */

function pressbooks_async_scripts( $tag, $handle, $src ) {
	$async = [
		'pressbooks/a11y',
		'pressbooks/keyboard-nav',
		'pressbooks/navbar',
		'pressbooks/toc',
		'columnizer',
		'sharer',
		'jquery-migrate',
	];

	if ( in_array( $handle, $async, true ) ) {
		return "<script async type='text/javascript' src='{$src}'></script>" . "\n";
	}

	return $tag;
}

add_filter( 'script_loader_tag', 'pressbooks_async_scripts', 10, 3 );

/* ------------------------------------------------------------------------ *
 * Register and enqueue scripts and stylesheets.
 * ------------------------------------------------------------------------ */
function pb_enqueue_scripts() {
	$assets = new Assets( 'pressbooks-book', 'theme' );
	$assets
		->setSrcDirectory( 'assets' )
		->setDistDirectory( 'dist' );

	wp_enqueue_style( 'pressbooks/structure', $assets->getPath( 'styles/structure.css' ), false, null, 'screen, print' );
	wp_enqueue_style( 'pressbooks/webfonts', 'https://fonts.googleapis.com/css?family=Oswald|Open+Sans+Condensed:300,300italic&subset=latin,cyrillic,greek,cyrillic-ext,greek-ext', false, null );

	$deps = [ 'pressbooks/structure', 'pressbooks/webfonts' ];

	if ( pb_is_custom_theme() ) { // Custom CSS (deprecated)
		wp_enqueue_style( 'pressbooks/custom-css', pb_get_custom_stylesheet_url(), $deps, get_option( 'pressbooks_last_custom_css' ), 'screen' );
	} else {
		$styles = Container::get( 'Styles' );
		if ( $styles->isCurrentThemeCompatible( 1 ) || $styles->isCurrentThemeCompatible( 2 ) ) {
			$sass = Container::get( 'Sass' );
			// Custom Styles
			if ( get_stylesheet() === 'pressbooks-book' && ! get_option( 'pressbooks_webbook_structure_version' ) ) {
				$styles->updateWebBookStyleSheet();
				update_option( 'pressbooks_webbook_structure_version', 1 );
			}
			$fullpath = $sass->pathToUserGeneratedCss() . '/style.css';
			if ( ! is_file( $fullpath ) ) {
				$styles->updateWebBookStyleSheet();
			}
			if ( $styles->isCurrentThemeCompatible( 1 ) && get_stylesheet() !== 'pressbooks-book' ) {
				wp_enqueue_style( 'pressbooks/book', get_template_directory_uri() . '/style.css', $deps, null, 'screen, print' );
			}
			wp_enqueue_style( 'pressbooks/theme', $sass->urlToUserGeneratedCss() . '/style.css', $deps, @filemtime( $fullpath ), 'screen, print' ); // @codingStandardsIgnoreLine
		} else {
			// Classic mode (does not use Sass)
			wp_enqueue_style( 'pressbooks/theme', get_stylesheet_directory_uri() . '/style.css', $deps, null, 'screen, print' );
		}
	}

	wp_enqueue_script( 'pressbooks/keyboard-nav', $assets->getPath( 'scripts/keyboard-nav.js' ), [ 'jquery' ], null, true );

	if ( is_single() ) {
		wp_enqueue_script( 'pressbooks/toc', $assets->getPath( 'scripts/toc.js' ), [ 'jquery' ], null, false );
	}

	wp_enqueue_script( 'pressbooks/a11y', $assets->getPath( 'scripts/a11y.js' ), [ 'jquery' ], null );
	wp_enqueue_style( 'pressbooks/a11y', $assets->getPath( 'styles/a11y.css' ), [ 'dashicons' ], null, 'screen' );
}
add_action( 'wp_enqueue_scripts', 'pb_enqueue_scripts' );

/**
 * Update web book stylesheet.
 */
function pressbooks_update_webbook_stylesheet() {
	if ( false === Container::get( 'Styles' )->isCurrentThemeCompatible( 1 ) && false === Container::get( 'Styles' )->isCurrentThemeCompatible( 2 ) ) {
		return;
	}

	if ( Container::get( 'Styles' )->isCurrentThemeCompatible( 1 ) ) {
		$inputs = [
			get_stylesheet_directory() . '/_fonts-web.scss',
			get_stylesheet_directory() . '/_mixins.scss',
			get_stylesheet_directory() . '/style.scss',
		];
	} elseif ( Container::get( 'Styles' )->isCurrentThemeCompatible( 2 ) ) {
		$inputs = [
			get_stylesheet_directory() . '/assets/styles/web/_fonts.scss',
			get_stylesheet_directory() . '/assets/styles/web/style.scss',
		];
		foreach ( glob( get_stylesheet_directory() . '/assets/styles/components/*.scss' ) as $import ) {
			$inputs[] = realpath( $import );
		}
	} else {
		$inputs = [];
	}

	$output = Container::get( 'Sass' )->pathToUserGeneratedCss() . '/style.css';

	$recompile = false;

	foreach ( $inputs as $input ) {
		if ( filemtime( $input ) > filemtime( $output ) ) {
			$recompile = true;
			break;
		}
	}

	if ( true === $recompile ) {
		if ( WP_DEBUG ) {
			error_log( 'Updating web book stylesheet.' );
		}
		Container::get( 'Styles' )->updateWebBookStyleSheet();
	} else {
		if ( WP_DEBUG ) {
			error_log( 'No web book stylesheet update needed.' );
		}
	}
}

if ( defined( 'WP_ENV' ) && 'development' === WP_ENV ) {
	add_action( 'template_redirect', 'pressbooks_update_webbook_stylesheet' );
}

/* ------------------------------------------------------------------------ *
 * Replaces the excerpt "more" text by a link
 * ------------------------------------------------------------------------ */

function new_pressbooks_excerpt_more( $more ) {
	   global $post;
	return '<a class="more-tag" href="' . get_permalink( $post->ID ) . '"> Read more &raquo;</a>';
}
add_filter( 'excerpt_more', 'new_pressbooks_excerpt_more' );

/**
 * Render Previous and next buttons
 *
 * @param bool $echo
 */
function pb_get_links( $echo = true ) {
	global $first_chapter, $prev_chapter, $next_chapter;
	$first_chapter = pb_get_first();
	$prev_chapter = pb_get_prev();
	$next_chapter = pb_get_next();
	if ( $echo ) :
	?><nav class="navigation posts-navigation" role="navigation">
	<div class="nav-links">
	<?php if ( $prev_chapter !== '/' ) : ?>
	<div class="nav-previous"><a href="<?php echo $prev_chapter; ?>"><?php _e( 'Previous', 'pressbooks-book' ); ?></a></div>
	<?php endif; ?>
	<?php if ( $next_chapter !== '/' ) : ?>
	<div class="nav-next"><a href="<?php echo $next_chapter ?>"><?php _e( 'Next', 'pressbooks-book' ); ?></a></div>
	<?php endif; ?>
	</div>
	</nav><?php
  endif;
}


/**
 * Prevent access by unregistered user if the book in question is private.
 */
function pb_private() {
	get_template_part( 'private' );
}


if ( ! function_exists( 'pressbooks_comment' ) ) {

	/**
	 * Template for comments and pingbacks.
	 *
	 * To override this walker in a child theme without modifying the comments template
	 * simply create your own pressbooks_comment(), and that function will be used instead.
	 *
	 * Used as a callback by wp_list_comments() for displaying the comments.
	 *
	 * @param \WP_Comment $comment
	 * @param array $args
	 * @param int $depth
	 */
	function pressbooks_comment( $comment, $args, $depth ) {
		$GLOBALS['comment'] = $comment;
		switch ( $comment->comment_type ) {
			case '' :
		?>
		<li <?php comment_class(); ?> id="li-comment-<?php comment_ID(); ?>">
		<div id="comment-<?php comment_ID(); ?>">
		<div class="comment-author vcard">
			<?php echo get_avatar( $comment, 40 ); ?>
			<?php printf( __( '%s on', 'pressbooks-book' ), sprintf( '<cite class="fn">%s</cite>', get_comment_author_link() ) ); ?> <?php printf( __( '%1$s at %2$s', 'pressbooks-book' ), get_comment_date(),  get_comment_time() ); ?> <span class="says">says:</span><?php edit_comment_link( __( '(Edit)', 'pressbooks-book' ), ' ' ); ?>
		</div><!-- .comment-author .vcard -->
		<?php if ( empty( $comment->comment_approved ) ) : ?>
			<em><?php _e( 'Your comment is awaiting moderation.', 'pressbooks-book' ); ?></em>
			<br />
		<?php endif; ?>

		<div class="comment-body"><?php comment_text(); ?></div>

		<div class="reply">
			<?php comment_reply_link( array_merge( $args, [ 'depth' => $depth, 'max_depth' => $args['max_depth'] ] ) ); ?>
		</div><!-- .reply -->
	</div><!-- #comment-##  -->

	<?php
			break;
			case 'pingback'  :
			case 'trackback' :
		?>
		<li class="post pingback">
		<p><?php _e( 'Pingback:', 'pressbooks-book' ); ?> <?php comment_author_link(); ?><?php edit_comment_link( __( '(Edit)', 'pressbooks-book' ), ' ' ); ?></p>
	<?php
			break;
		}
	}
};

/* ------------------------------------------------------------------------ *
 * Copyright License
 * ------------------------------------------------------------------------ */

/**
 * @param bool $show_custom_copyright (optional, default is true)
 *
 * @return string
 */
function pressbooks_copyright_license( $show_custom_copyright = true ) {
	$metadata = \Pressbooks\Book::getBookInformation();

	if ( empty( $metadata['pb_book_license'] ) ) {
		$all_rights_reserved = true;
	} elseif ( $metadata['pb_book_license'] === 'all-rights-reserved' ) {
		$all_rights_reserved = true;
	} else {
		$all_rights_reserved = false;
	}
	if ( ! empty( $metadata['pb_custom_copyright'] ) && $show_custom_copyright ) {
		$has_custom_copyright = true;
	} else {
		$has_custom_copyright = false;
	}

	// Custom Copyright must override All Rights Reserved
	$html = '';
	if ( ! $has_custom_copyright || ( $has_custom_copyright && ! $all_rights_reserved ) ) {
		$html .= _do_license( $metadata );
	}
	if ( $has_custom_copyright ) {
		$html .= '<div class="license-attribution">' . $metadata['pb_custom_copyright'] . '</div>';
	}

	return $html;
}

/**
 * @param array $metadata
 *
 * @return string
 */
function _do_license( $metadata ) {

	global $post;
	$id = $post->ID;
	$title = ( is_front_page() ) ? get_bloginfo( 'name' ) : $post->post_title;

	try {
		$licensing = new \Pressbooks\Licensing();
		return $licensing->doLicense( $metadata, $id, $title );
	} catch ( \Exception $e ) {
		error_log( $e->getMessage() );
	}
	return '';
}

/* ------------------------------------------------------------------------ *
 * Hooks, Actions and Filters
 * ------------------------------------------------------------------------ */

function pressbooks_theme_pdf_hacks( $hacks ) {

	$options = get_option( 'pressbooks_theme_options_pdf' );

	$hacks['pdf_footnotes_style'] = $options['pdf_footnotes_style'];

	return $hacks;
}
add_filter( 'pb_pdf_hacks', 'pressbooks_theme_pdf_hacks' );


function pressbooks_theme_ebook_hacks( $hacks ) {

	// --------------------------------------------------------------------
	// Global Options

	$options = get_option( 'pressbooks_theme_options_global' );

	// Display chapter numbers?
	if ( $options['chapter_numbers'] ) {
		$hacks['chapter_numbers'] = true;
	}

	// --------------------------------------------------------------------
	// Ebook Options

	$options = get_option( 'pressbooks_theme_options_ebook' );

	// Compress images
	if ( $options['ebook_compress_images'] ) {
		$hacks['ebook_compress_images'] = true;
	}

	return $hacks;
}
add_filter( 'pb_epub_hacks', 'pressbooks_theme_ebook_hacks' );

function pressbooks_theme_add_metadata() {
	if ( is_front_page() ) {
		echo pb_get_seo_meta_elements();
		echo pb_get_microdata_elements();
	} else {
		echo pb_get_microdata_elements();
	}
}

add_action( 'wp_head', 'pressbooks_theme_add_metadata' );

function pressbooks_cover_promo() {
	?>
	<?php if ( ! defined( 'PB_HIDE_COVER_PROMO' ) || PB_HIDE_COVER_PROMO === false ) : ?>
	<a href="https://pressbooks.com" class="pressbooks-brand"><img src="<?php echo get_template_directory_uri(); ?>/dist/images/pressbooks-branding-2x.png" alt="pressbooks-branding" width="186" height="123" /> <span><?php _e( 'Make your own books on Pressbooks', 'pressbooks-book' ); ?></span></a>
	<?php else : ?>
	<div class="spacer"></div>
	<?php endif;
}

add_action( 'pb_cover_promo', 'pressbooks_cover_promo' );

/**
 * Restrict search.
 *
 * @param \WP_Query $query
 * @return \WP_Query
 */
function pb_filter_search( $query ) {
	if ( $query->is_search && ! is_admin() ) {
		$query->set( 'post_type', [ 'front-matter', 'back-matter', 'chapter', 'part' ] );
	}

	return $query;
}
add_filter( 'pre_get_posts', 'pb_filter_search' );

/**
 * Check if the book is private or public.
 */

function pb_is_public() {
	global $blog_id;
	$blog_public = absint( get_option( 'blog_public' ) );
	if ( $blog_public === 1 || $blog_public === 0 && current_user_can_for_blog( $blog_id, 'read' ) ) {
		return true;
	}
	return false;
}

function pb_social_media_enabled() {
	$options = get_option( 'pressbooks_theme_options_web' );
	if ( ! isset( $options['social_media'] ) ) {
		return true;
	} elseif ( isset( $options['social_media'] ) && absint( $options['social_media'] === 1 ) ) {
		return true;
	}
	return false;
}

function pressbooks_book_setup() {
	load_theme_textdomain( 'pressbooks-book', get_template_directory() . '/languages' );
	remove_action( 'wp_head', 'wp_generator' );
}

add_action( 'after_setup_theme', 'pressbooks_book_setup' );
