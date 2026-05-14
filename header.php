<!DOCTYPE html>
<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="profile" href="https://gmpg.org/xfn/11">

		<script>
			/**
			 * Script Anti-Flash (ReloadeD)
			 * Roda no <head> ANTES do CSS, evitando qualquer flash de tema errado.
			 * Cascata de decisão:
			 *   1. Escolha explícita do user (localStorage 'rd-theme')
			 *   2. Admin escolheu 'dark' ou 'light' como default → respeita
			 *   3. Admin escolheu 'system' → segue prefers-color-scheme
			 *   4. Fallback: dark (também usado quando navegador não suporta matchMedia)
			 *
			 * Atributo no <html> (document.documentElement) porque <body> ainda não
			 * existe nesse momento de execução. Seletores CSS [data-theme="light"]
			 * funcionam igual em qualquer elemento.
			 */
			(function() {
				const adminDefault = '<?php echo esc_js( rd_get_option( 'default_theme_mode', 'system' ) ); ?>';
				const savedTheme = localStorage.getItem('rd-theme');
				let theme;

				if (savedTheme) {
					theme = savedTheme;
				} else if (adminDefault === 'dark' || adminDefault === 'light') {
					theme = adminDefault;
				} else {
					// system mode — segue preferência do SO
					let systemPrefersLight = false;
					if (window.matchMedia) {
						systemPrefersLight = window.matchMedia('(prefers-color-scheme: light)').matches;
					}
					theme = systemPrefersLight ? 'light' : 'dark';
				}

				document.documentElement.setAttribute('data-theme', theme);
			})();
		</script>

		<?php wp_head(); ?>
	</head>

	<body <?php body_class(); ?>>

		<?php if ( rd_get_option_bool('enable_top_bar') ) : ?>
			<div class="rd-top-bar">
				<div class="rd-container">

					<div class="rd-top-left">
						<?php rd_render_date(); ?>
					</div>

					<div class="rd-top-center">
						<span class="rd-ticker-label"><?php esc_html_e( 'Latest:', 'reloaded' ); ?></span>
						<?php rd_render_news_ticker(); ?>
					</div>

					<div class="rd-top-right">
						<?php rd_render_social_icons(); ?>
					</div>

				</div>
			</div>
		<?php endif; ?>

		<?php wp_body_open(); ?>

		<div id="page" class="site">

			<header id="masthead" class="site-header">
				<div class="top-branding">
					<div class="container">
						<div class="site-branding">
							<?php if ( function_exists('rd_render_logo') ) { rd_render_logo(); } ?>
						</div>
						<div class="header-banner">
							<?php if ( function_exists('rd_render_ad_topo') ) { rd_render_ad_topo(); } ?>
						</div>
					</div>
				</div>

				<div class="menu-bar-fixed">
					<div class="container">
						<nav id="site-navigation" class="main-navigation">

							<div class="site-branding-toggle">
								<?php if ( function_exists('rd_render_logo') ) { rd_render_logo(); } ?>
							</div>

							<button class="menu-toggle" aria-controls="primary-menu" aria-expanded="false" aria-label="<?php esc_attr_e( 'Open navigation menu', 'reloaded' ); ?>">
								<span class="hamburger-icon" aria-hidden="true">&#9776;</span>
							</button>
							<?php
								wp_nav_menu( array(
									'theme_location' => 'menu-1',
									'menu_id'        => 'primary-menu',
									'container'      => false,
								) );
							?>
						</nav>

						<div class="header-search-container">
							<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
								<input
									type="search"
									class="search-field"
									placeholder="<?php esc_attr_e( 'Search...', 'reloaded' ); ?>"
									value="<?php echo esc_attr( get_search_query() ); ?>"
									name="s"
									aria-label="<?php esc_attr_e( 'Search the site', 'reloaded' ); ?>"
								/>
								<button type="submit" class="search-submit" aria-label="<?php esc_attr_e( 'Search', 'reloaded' ); ?>">
									<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="25" height="25" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="search-icon" aria-hidden="true">
										<circle cx="11" cy="11" r="8"></circle>
										<line x1="22" y1="22" x2="16.65" y2="16.65"></line>
									</svg>
								</button>
							</form>
						</div>

						<?php if ( rd_get_option_bool('enable_theme_switch') ) : ?>
							<div class="theme-switch-wrapper">
								<button id="rd-theme-toggle" class="theme-toggle-btn" aria-label="<?php esc_attr_e( 'Toggle dark/light mode', 'reloaded' ); ?>">
									<svg class="sun-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
									<svg class="moon-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
								</button>
							</div>
						<?php endif; ?>

					</div>
				</div>
			</header>
