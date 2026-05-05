<?php
defined('ABSPATH') || exit;
// Arquivos estruturais e de interface do usuário (Painel)
require_once get_template_directory() . '/inc/core.php';    // Carrega o núcleo do tema
require_once get_template_directory() . '/inc/panel.php';

// Módulos do Tema (Baseados no Painel)
require_once get_template_directory() . '/inc/mod-general.php';
require_once get_template_directory() . '/inc/mod-privacy.php';
require_once get_template_directory() . '/inc/mod-integrations.php';
require_once get_template_directory() . '/inc/mod-performance.php';
require_once get_template_directory() . '/inc/mod-social.php';
require_once get_template_directory() . '/inc/mod-seo.php';
require_once get_template_directory() . '/inc/mod-donations.php';
require_once get_template_directory() . '/inc/mod-ads.php';
require_once get_template_directory() . '/inc/mod-maintenance.php';
require_once get_template_directory() . '/inc/mod-views.php';