<?php
defined( 'ABSPATH' ) || exit;
// Arquivos estruturais e de interface do usuário (Painel)
require_once get_template_directory() . '/inc/core.php';    // Carrega o núcleo do tema
require_once get_template_directory() . '/inc/panel-helpers.php'; // Sistema de componentes UI (Wave 11 Fase C) — carregado ANTES do panel.php pra estar disponível nos callbacks.
require_once get_template_directory() . '/inc/panel.php';

// Módulos do Tema (Baseados no Painel)
require_once get_template_directory() . '/inc/mod-dashboard.php'; // Aba Dashboard read-only (Wave 11 Fase F).
require_once get_template_directory() . '/inc/mod-general.php';
require_once get_template_directory() . '/inc/mod-privacy.php';
require_once get_template_directory() . '/inc/mod-integrations.php';
require_once get_template_directory() . '/inc/mod-performance.php';
require_once get_template_directory() . '/inc/mod-image-formats.php';
require_once get_template_directory() . '/inc/mod-cdn-images.php'; // Filter `reloaded_image_url` — ponto de extensão dormente pra Cloudflare/Bunny.
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
require_once get_template_directory() . '/inc/mod-security.php';
require_once get_template_directory() . '/inc/mod-csp.php';
require_once get_template_directory() . '/inc/mod-backup.php';
require_once get_template_directory() . '/inc/post-card.php';
require_once get_template_directory() . '/inc/mod-author-bio.php';
require_once get_template_directory() . '/inc/mod-reading-time.php';
require_once get_template_directory() . '/inc/mod-related-posts.php';
require_once get_template_directory() . '/inc/mod-table-of-contents.php';
require_once get_template_directory() . '/inc/mod-search-suggestions.php';
require_once get_template_directory() . '/inc/mod-search.php';
require_once get_template_directory() . '/inc/mod-menu.php';
require_once get_template_directory() . '/inc/mod-self-update.php'; // Auto-update via GitHub Releases (custom, sem deps externas).
