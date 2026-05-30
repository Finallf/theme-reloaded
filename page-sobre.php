<?php
/**
 * Template: Page — About
 *
 * Template specific to the page with the "sobre" slug.
 * WP picks this file automatically via the template hierarchy
 * (page-{slug}.php) — no selection is needed in the editor.
 *
 * Differences vs the generic page.php:
 *   - Hardcoded hero at the top: avatar (author Gravatar) + name + tagline
 *     (user biography) + social network icons (reused helper).
 *     Ensures visual consistency + auto-sync with panel configuration.
 *   - Inline Schema.org Person JSON-LD — feeds Google's E-E-A-T
 *     (Experience, Expertise, Authoritativeness, Trustworthiness).
 *     "sameAs" picks URLs from the social networks active in the panel.
 *   - Featured image and comments honored the same way as page.php.
 *
 * The main content (long bio, projects, extra photos) continues via the
 * editor — the hero is only the standardized "institutional top".
 *
 * @package ReloadeD
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();

	$author_id   = get_the_author_meta( 'ID' );
	$author_name = get_the_author_meta( 'display_name', $author_id );
	// Defensive fallback: falls back to nicename/login if display_name is empty
	// (Schema.org Person requires a populated name — without it, Rich Results
	// shows "Unnamed item").
	if ( empty( $author_name ) ) {
		$author_name = get_the_author_meta( 'user_nicename', $author_id );
	}
	if ( empty( $author_name ) ) {
		$author_name = get_the_author_meta( 'user_login', $author_id );
	}
	$author_bio = get_the_author_meta( 'description', $author_id );
	$avatar_url = get_avatar_url( $author_id, array( 'size' => 240 ) );

	// Collects URLs of the active social networks for Schema.org sameAs.
	$social_keys = array( 'discord', 'telegram', 'whatsapp', 'youtube', 'instagram', 'steam', 'twitter', 'facebook' );
	$same_as     = array();
	foreach ( $social_keys as $key ) {
		$url = rd_get_option( 'social_' . $key );
		if ( ! empty( $url ) ) {
			$same_as[] = esc_url( $url );
		}
	}

	// Schema.org Person JSON-LD.
	$schema = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'Person',
		'name'        => $author_name,
		'image'       => $avatar_url,
		'url'         => get_permalink(),
		'description' => wp_strip_all_tags( $author_bio ),
	);
	if ( ! empty( $same_as ) ) {
		$schema['sameAs'] = $same_as;
	}
	?>

	<script type="application/ld+json"<?php echo rd_csp_nonce_attr(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce already escaped ?>>
	<?php echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?>
	</script>

	<main id="primary" class="site-main rd-page">
		<div class="container">
			<div class="content-area">

				<article id="page-<?php the_ID(); ?>" <?php post_class( 'rd-page-article rd-page-about' ); ?>>

					<header class="rd-about-hero">
						<div class="rd-about-avatar">
							<img src="<?php echo esc_url( $avatar_url ); ?>"
								alt="<?php echo esc_attr( $author_name ); ?>"
								width="120"
								height="120"
								loading="eager"
								fetchpriority="high">
						</div>
						<div class="rd-about-info">
							<div class="rd-about-info-header">
								<h1 class="rd-about-name"><?php echo esc_html( $author_name ); ?></h1>
								<a href="<?php echo esc_url( get_author_posts_url( $author_id ) ); ?>" class="rd-about-cta-btn">
									<?php esc_html_e( 'View all posts', 'reloaded' ); ?>
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
										<path d="M5 12h14M13 5l7 7-7 7"></path>
									</svg>
								</a>
							</div>
							<?php if ( ! empty( $author_bio ) ) : ?>
								<p class="rd-about-tagline"><?php echo esc_html( $author_bio ); ?></p>
							<?php endif; ?>
							<?php if ( function_exists( 'rd_render_social_icons' ) ) : ?>
								<div class="rd-about-socials">
									<?php rd_render_social_icons(); ?>
								</div>
							<?php endif; ?>
						</div>
					</header>

					<?php
					// Respects the "Hide Featured Image" meta box option.
					$hide_thumbnail = get_post_meta( get_the_ID(), '_rd_hide_thumbnail', true );

					if ( has_post_thumbnail() && $hide_thumbnail !== 'yes' ) :
						?>
						<div class="rd-page-thumbnail">
							<?php the_post_thumbnail( 'large', array( 'loading' => 'lazy' ) ); ?>
						</div>
					<?php endif; ?>

					<div class="rd-page-content">
						<?php
						the_content();

						wp_link_pages(
							array(
								'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'reloaded' ),
								'after'  => '</div>',
							)
						);
						?>
					</div>

				</article>

				<?php
				// Comments (only if the admin enabled them for this page).
				if ( comments_open() || get_comments_number() ) :
					comments_template();
				endif;
				?>

			</div>
			<?php get_sidebar(); ?>
		</div>
	</main>

	<?php
endwhile;

get_footer();
