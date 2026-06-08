<?php defined( 'ABSPATH' ) || exit; ?>
<aside id="secondary" class="widget-area">
	<?php
	// Top-of-sidebar ad banner (comes from mod-ads.php).
	if ( function_exists( 'rd_render_ad_sidebar_top' ) ) {
		rd_render_ad_sidebar_top();
	}

	// WordPress widgets (Appearance → Widgets) — only renders when present.
	// The "Support the Project" block is now RD_Support_Widget, placed and
	// ordered here via Appearance → Widgets (no longer hardcoded).
	if ( is_active_sidebar( 'sidebar-1' ) ) {
		dynamic_sidebar( 'sidebar-1' );
	}

	// Sticky banner at the bottom (comes from mod-ads.php).
	if ( function_exists( 'rd_render_ad_sidebar_sticky' ) ) {
		rd_render_ad_sidebar_sticky();
	}
	?>
</aside>