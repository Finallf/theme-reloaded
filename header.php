<!DOCTYPE html>
<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="profile" href="https://gmpg.org/xfn/11">

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
						<span class="rd-ticker-label">Últimas:</span>
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

							<button class="menu-toggle" aria-controls="primary-menu" aria-expanded="false" aria-label="<?php esc_attr_e( 'Abrir menu de navegação', 'reloaded' ); ?>">
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
									placeholder="<?php esc_attr_e( 'Pesquisar...', 'reloaded' ); ?>" 
									value="<?php echo esc_attr( get_search_query() ); ?>" 
									name="s"
									aria-label="<?php esc_attr_e( 'Pesquisar no site', 'reloaded' ); ?>"
								/>
								<button type="submit" class="search-submit" aria-label="<?php esc_attr_e( 'Buscar', 'reloaded' ); ?>">
									<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="25" height="25" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="search-icon" aria-hidden="true">
										<circle cx="11" cy="11" r="8"></circle>
										<line x1="22" y1="22" x2="16.65" y2="16.65"></line>
									</svg>
								</button>
							</form>
						</div>
					</div>
				</div>
			</header>