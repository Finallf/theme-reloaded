<?php defined( 'ABSPATH' ) || exit; ?>
<aside id="secondary" class="widget-area">
	<?php
	// WordPress widgets (Appearance → Widgets) — only renders when present.
	// Discord, Support and ad banners are now WP widgets (RD_Discord_Widget,
	// RD_Support_Widget, RD_Ad_Widget), placed and ordered freely here.
	if ( is_active_sidebar( 'sidebar-1' ) ) {
		dynamic_sidebar( 'sidebar-1' );
	}

	// Sticky banner at the bottom (comes from mod-ads.php).
	if ( function_exists( 'rd_render_ad_sidebar_sticky' ) ) {
		rd_render_ad_sidebar_sticky();
	}
	?>
</aside>