<?php
defined( 'ABSPATH' ) || exit;
// Structural and UI files (Panel).
require_once get_template_directory() . '/inc/core.php';    // Loads the theme core.
require_once get_template_directory() . '/inc/panel-helpers.php'; // UI component system (Wave 11 Phase C) — loaded BEFORE panel.php so it is available inside callbacks.
require_once get_template_directory() . '/inc/panel.php';

// Theme modules (Panel-based).
require_once get_template_directory() . '/inc/mod-dashboard.php'; // Read-only Dashboard tab (Wave 11 Phase F).
require_once get_template_directory() . '/inc/mod-general.php';
require_once get_template_directory() . '/inc/mod-privacy.php';
require_once get_template_directory() . '/inc/mod-integrations.php';
require_once get_template_directory() . '/inc/mod-performance.php';
require_once get_template_directory() . '/inc/mod-image-formats.php';
require_once get_template_directory() . '/inc/mod-cdn-images.php'; // `reloaded_image_url` filter — dormant extension point for Cloudflare/Bunny.
require_once get_template_directory() . '/inc/mod-critical-css.php';
require_once get_template_directory() . '/inc/mod-login-protection.php';
require_once get_template_directory() . '/inc/mod-social.php';
require_once get_template_directory() . '/inc/mod-seo.php';
require_once get_template_directory() . '/inc/mod-breadcrumbs.php';
require_once get_template_directory() . '/inc/mod-category-colors.php';
require_once get_template_directory() . '/inc/mod-archive-header.php';
require_once get_template_directory() . '/inc/mod-donations.php';
require_once get_template_directory() . '/inc/mod-ads.php';
require_once get_template_directory() . '/inc/mod-maintenance.php';
require_once get_template_directory() . '/inc/mod-views.php';
require_once get_template_directory() . '/inc/mod-stats.php';
require_once get_template_directory() . '/inc/class-rd-popular-posts-widget.php';
require_once get_template_directory() . '/inc/class-rd-latest-video-widget.php';
require_once get_template_directory() . '/inc/class-rd-support-widget.php';
require_once get_template_directory() . '/inc/class-rd-discord-widget.php';
require_once get_template_directory() . '/inc/class-rd-ad-widget.php';
require_once get_template_directory() . '/inc/mod-security.php';
require_once get_template_directory() . '/inc/mod-csp.php';
require_once get_template_directory() . '/inc/mod-backup.php';
require_once get_template_directory() . '/inc/post-card.php';
require_once get_template_directory() . '/inc/mod-home.php'; // Configurable home layout (hero + Grid/Vertical/Compact sections).
require_once get_template_directory() . '/inc/mod-carousel.php'; // Featured carousel between header and main (home only).
require_once get_template_directory() . '/inc/mod-author-bio.php';
require_once get_template_directory() . '/inc/mod-reading-time.php';
require_once get_template_directory() . '/inc/mod-related-posts.php';
require_once get_template_directory() . '/inc/mod-table-of-contents.php';
require_once get_template_directory() . '/inc/mod-search-suggestions.php';
require_once get_template_directory() . '/inc/mod-search.php';
require_once get_template_directory() . '/inc/mod-menu.php';
require_once get_template_directory() . '/inc/mod-self-update.php'; // Auto-update via GitHub Releases (custom, no external deps).
