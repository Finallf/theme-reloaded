/**
 * admin-category-color.js
 * Initializes the wp-color-picker (Iris) on the category-edit color field.
 *
 * Loaded by the rd_category_color_admin_enqueue() function in mod-category-colors.php,
 * only on the Categories screens (edit-tags.php?taxonomy=category and term.php).
 */
jQuery(document).ready(function ($) {
    $('.rd-color-picker').wpColorPicker();
});
