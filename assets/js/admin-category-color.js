/**
 * admin-category-color.js
 * Inicializa o wp-color-picker (Iris) no campo de cor da edição de categoria.
 *
 * Carregado pela função rd_category_color_admin_enqueue() em mod-category-colors.php,
 * apenas nas telas de Categorias (edit-tags.php?taxonomy=category e term.php).
 */
jQuery(document).ready(function ($) {
    $('.rd-color-picker').wpColorPicker();
});
