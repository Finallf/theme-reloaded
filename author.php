<?php
/**
 * Template: Author Archive (Posts by author)
 *
 * Lists every post authored by the queried user. The user's profile
 * (avatar, display name, bio, post count, registration date) appears
 * in the header, followed by their posts rendered with the shared
 * vertical post card.
 *
 * Note: this is the ARCHIVE of posts by an author, not a profile page.
 * A richer "About the author" template (single-author-page) is planned
 * separately in the future-agenda backlog.
 *
 * @package ReloadeD
 */

defined( 'ABSPATH' ) || exit;

get_header();

$author_id  = (int) get_queried_object_id();
$author     = get_userdata( $author_id );
$post_count = (int) count_user_posts( $author_id, 'post', true );
$bio        = $author ? get_the_author_meta( 'description', $author_id ) : '';
$registered = $author ? $author->user_registered : '';

// Schema.org Person — alimenta E-E-A-T do Google (Experience, Expertise,
// Authoritativeness, Trustworthiness). O @id é referenciado pelo mod-seo.php
// quando renderiza Article schema em singles desse autor — Google amarra
// os dois schemas como o mesmo Person.
if ( $author ) {
	$social_keys = array( 'discord', 'telegram', 'whatsapp', 'youtube', 'instagram', 'steam', 'twitter', 'facebook' );
	$same_as     = array();
	foreach ( $social_keys as $key ) {
		$url = get_the_author_meta( 'social_' . $key, $author_id );
		if ( ! empty( $url ) ) {
			$same_as[] = esc_url( $url );
		}
	}

	// Fortificação defensiva: cai pro user_nicename → user_login se display_name
	// estiver vazio (cenário raro mas possível em users seedados/importados).
	// Schema.org Person exige `name` populado — sem isso, o Google lista como
	// "Item sem nome" no Rich Results.
	$person_name = $author->display_name;
	if ( empty( $person_name ) ) {
		$person_name = ! empty( $author->user_nicename ) ? $author->user_nicename : $author->user_login;
	}

	$person_schema = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'Person',
		'@id'         => get_author_posts_url( $author_id ) . '#person',
		'name'        => $person_name,
		'image'       => get_avatar_url( $author_id, array( 'size' => 240 ) ),
		'url'         => get_author_posts_url( $author_id ),
		'description' => wp_strip_all_tags( $bio ),
	);
	if ( ! empty( $same_as ) ) {
		$person_schema['sameAs'] = $same_as;
	}
	?>
	<script type="application/ld+json"<?php echo rd_csp_nonce_attr(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce already escaped ?>>
	<?php echo wp_json_encode( $person_schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?>
	</script>
	<?php
}
?>

<main id="primary" class="site-main rd-page rd-author-page">
	<div class="container">
		<div class="content-area">

			<?php if ( $author ) : ?>
				<header class="rd-author-header">

					<div class="rd-author-avatar">
						<?php
						echo get_avatar(
							$author_id,
							120,
							'',
							$author->display_name,
							array( 'class' => 'rd-author-avatar-img' )
						);
						?>
					</div>

					<div class="rd-author-info">
						<div class="rd-author-info-header">
							<h1 class="rd-author-name"><?php echo esc_html( $author->display_name ); ?></h1>
							<?php
							// Ícones sociais do autor (não do portal). Renderiza só se o user
							// preencheu alguma URL em Usuários → Perfil → Informações de contato.
							if ( function_exists( 'rd_render_social_icons' ) ) :
								?>
								<div class="rd-author-socials">
									<?php rd_render_social_icons( $author_id ); ?>
								</div>
							<?php endif; ?>
						</div>

						<?php if ( ! empty( $bio ) ) : ?>
							<p class="rd-author-bio"><?php echo esc_html( $bio ); ?></p>
						<?php endif; ?>

						<div class="rd-author-meta">
							<span class="rd-author-meta-item">
								<?php
								printf(
									// translators: %s: number of published articles
									esc_html( _n( '%s published article', '%s published articles', $post_count, 'reloaded' ) ),
									esc_html( number_format_i18n( $post_count ) )
								);
								?>
							</span>

							<?php if ( ! empty( $registered ) ) : ?>
								<span class="rd-author-meta-item">
									<?php
									printf(
										// translators: %s: registration date
										esc_html__( 'Member since %s', 'reloaded' ),
										esc_html( date_i18n( 'F \d\e Y', strtotime( $registered ) ) )
									);
									?>
								</span>
							<?php endif; ?>
						</div>
					</div>

				</header>
			<?php endif; ?>

			<?php if ( have_posts() ) : ?>

				<div class="rd-search-results-containers">
					<div class="rd-wrapper-vertical">
						<?php
						while ( have_posts() ) :
							the_post();
							?>
							<?php rd_render_post_card( 'vertical' ); ?>
						<?php endwhile; ?>
					</div>
				</div>

				<?php
				the_posts_pagination(
					array(
						'prev_text' => __( '&larr; Previous', 'reloaded' ),
						'next_text' => __( 'Next &rarr;', 'reloaded' ),
					)
				);
				?>

			<?php else : ?>

				<p class="rd-no-posts"><?php esc_html_e( 'This author has not published any articles yet.', 'reloaded' ); ?></p>

			<?php endif; ?>

		</div>
		<?php get_sidebar(); ?>
	</div>
</main>

<?php get_footer(); ?>
