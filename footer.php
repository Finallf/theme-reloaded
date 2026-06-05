<?php defined( 'ABSPATH' ) || exit; ?>
			<footer id="colophon" class="site-footer">
				<div class="container">

					<div class="footer-grid">

						<div class="footer-column about-rd">
							<?php if ( has_custom_logo() ) : ?>
								<div class="footer-logo"><?php the_custom_logo(); ?></div>
							<?php else : ?>
								<h2 class="footer-title"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h2>
							<?php endif; ?>
							<?php
							// Tagline from the WP Site Description (Settings > General), with the
							// original sentence as a fallback when the description is empty.
							$footer_tagline = get_bloginfo( 'description' );
							if ( '' === trim( (string) $footer_tagline ) ) {
								$footer_tagline = __( 'News, technology, games and the best of the open-source world. Where information is reloaded daily.', 'reloaded' );
							}
							?>
							<p><?php echo esc_html( $footer_tagline ); ?></p>
						</div>

						<?php
						// Footer columns 2-4 — dynamic widget areas. The empty-state hint shows
						// only to admins, so visitors never see setup instructions.
						foreach ( array( 'footer-widget-area', 'footer-widget-area-2', 'footer-widget-area-3' ) as $rd_footer_col ) :
							?>
							<div class="footer-column">
								<?php if ( is_active_sidebar( $rd_footer_col ) ) : ?>
									<?php dynamic_sidebar( $rd_footer_col ); ?>
								<?php elseif ( current_user_can( 'edit_theme_options' ) ) : ?>
									<p class="footer-widget-empty"><?php esc_html_e( 'Add a widget in Appearance > Widgets.', 'reloaded' ); ?></p>
								<?php endif; ?>
							</div>
							<?php
						endforeach;
						?>

					</div>

					<div class="footer-bottom">
						<div class="footer-bottom-info">
							<p>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?>. <?php esc_html_e( 'All rights reserved.', 'reloaded' ); ?>
								<?php
								// Link to reopen the LGPD banner — only rendered if the system is active.
								// When enable_lgpd is OFF, the system is dormant: no banner, no link.
								if ( rd_get_option_bool( 'enable_lgpd' ) ) :
									?>
									&middot; <button type="button" id="rd-lgpd-reopen" class="rd-lgpd-reopen"><?php esc_html_e( 'Cookie Preferences', 'reloaded' ); ?></button>
								<?php endif; ?>
							</p>
							<?php
							// Opt-in subline via the panel (General > Footer Subline).
							// If the admin leaves it empty, the line simply does not render.
							$footer_subline = rd_get_option( 'footer_subline' );
							if ( ! empty( $footer_subline ) ) :
								?>
								<p class="hosting-info"><?php echo wp_kses_post( $footer_subline ); ?></p>
							<?php endif; ?>
						</div>
						<?php
						if ( function_exists( 'rd_render_social_icons' ) ) {
							rd_render_social_icons(); }
						?>
					</div>
				</div>
			</footer>

		</div>

		<?php if ( rd_get_option_bool( 'back_to_top' ) ) : ?>
			<button id="back-to-top" class="back-to-top" aria-label="<?php esc_attr_e( 'Back to top', 'reloaded' ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
					<polyline points="18 15 12 9 6 15"></polyline>
				</svg>
			</button>
		<?php endif; ?>

		<?php wp_footer(); ?>
	</body>
</html>
