			<footer id="colophon" class="site-footer">
				<div class="container">

					<div class="footer-grid">

						<div class="footer-column about-rd">
							<?php if ( has_custom_logo() ) : ?>
								<div class="footer-logo"><?php the_custom_logo(); ?></div>
							<?php else : ?>
								<h2 class="footer-title">ReloadeD</h2>
							<?php endif; ?>
							<p><?php esc_html_e( 'News, technology, games and the best of the open-source world. Where information is reloaded daily.', 'reloaded' ); ?></p>
						</div>

						<div class="footer-column">
                            <h3 class="footer-heading"><?php esc_html_e( 'Navigation', 'reloaded' ); ?></h3>
							<?php
							wp_nav_menu(
								array(
									'theme_location' => 'menu-footer',
									'container'      => false,
									'menu_class'     => 'footer-links',
									'fallback_cb'    => false,
								)
							);
							?>
						</div>

						<div class="footer-column">
							<?php if ( is_active_sidebar( 'footer-widget-area' ) ) : ?>
								<?php dynamic_sidebar( 'footer-widget-area' ); ?>
							<?php else : ?>
                                <h3 class="footer-heading"><?php esc_html_e( 'Featured', 'reloaded' ); ?></h3>
                                <p style="color:#888; font-size: 0.9rem;"><?php esc_html_e( 'Add a widget in Appearance > Widgets.', 'reloaded' ); ?></p>
							<?php endif; ?>
						</div>

						<div class="footer-column">
                            <h3 class="footer-heading"><?php esc_html_e( 'Support the Project', 'reloaded' ); ?></h3>
							<ul class="support-links">
                                <li><a href="#" target="_blank"><?php esc_html_e( 'Community Discord', 'reloaded' ); ?></a></li>
                                <li><a href="#" target="_blank"><?php esc_html_e( 'GitHub Sponsors', 'reloaded' ); ?></a></li>
                                <li><a href="#" target="_blank"><?php esc_html_e( 'Support via PIX', 'reloaded' ); ?></a></li>
							</ul>
						</div>
					</div>

					<div class="footer-bottom">
                        <p>&copy; <?php echo esc_html( wp_date('Y') ); ?> <?php bloginfo( 'name' ); ?>. <?php esc_html_e( 'All rights reserved.', 'reloaded' ); ?></p>
                        <p class="hosting-info"><?php esc_html_e( 'Hosted on private infrastructure.', 'reloaded' ); ?></p>
					</div>
				</div>
			</footer>

		</div>

		<?php if ( rd_get_option_bool('back_to_top') ) : ?>
            <button id="back-to-top" class="back-to-top" aria-label="<?php esc_attr_e( 'Back to top', 'reloaded' ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
					<polyline points="18 15 12 9 6 15"></polyline>
				</svg>
			</button>
		<?php endif; ?>

		<?php wp_footer(); ?>
	</body>
</html>
