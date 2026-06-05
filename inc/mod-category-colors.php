<?php
defined( 'ABSPATH' ) || exit;
/*******************************************************************************
 * Module: Category Colors — Per-category color configurable via admin         *
 *                                                                             *
 * Replaces the hardcoded `.tag-{slug}` mapping in SCSS with a data-driven     *
 * system based on term meta. Each category can have its own background        *
 * color; the text color is computed automatically (black/white) based         *
 * on the background luminance to ensure readable contrast.                    *
 *                                                                             *
 * Flow:                                                                       *
 *   1. Admin picks a color via wp-color-picker on the category edit screen    *
 *   2. Value sanitized and saved in `term_meta` with the `rd_category_color` key
 *   3. Frontend injects a <style> in <wp_head> with one CSS rule per          *
 *      category that has a color set                                          *
 *   4. SCSS keeps only the fallback `.post-tag { background-color: #555 }`    *
 *******************************************************************************/

/*******************************************************************************
 * Default color when a category has no explicit color configured              *
 *******************************************************************************/
const RD_CATEGORY_COLOR_DEFAULT = '#555555';

/*******************************************************************************
 * Computes the ideal text color (#000 or #fff) for contrast over a background *
 *                                                                             *
 * Uses the real WCAG formula (relative luminance + contrast ratio). Computes  *
 * the background's contrast against white AND against black, returns the      *
 * color with the higher ratio — always guarantees the best possible option.   *
 *                                                                             *
 * The simple YIQ method (threshold 128) that was here before failed on        *
 * intermediate colors like grass-green (#00a859) and vivid-red (#e52d27):     *
 * both > 128 YIQ but white text on them gives a ratio < 4.5:1 (WCAG AA fail). *
 *                                                                             *
 *
 * @param string $hex Background color in `#rrggbb` or `#rgb` format           *
 * @return string `#000000` if black contrasts better, `#ffffff` if white      *
 *******************************************************************************/
function rd_get_contrast_text_color( string $hex ): string {
	$hex = ltrim( trim( $hex ), '#' );

	// Expand shorthand `#abc` to `#aabbcc`
	if ( strlen( $hex ) === 3 ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}

	if ( strlen( $hex ) !== 6 || ! ctype_xdigit( $hex ) ) {
		return '#ffffff'; // invalid hex — guess white (assumes a dark background)
	}

	$r = hexdec( substr( $hex, 0, 2 ) );
	$g = hexdec( substr( $hex, 2, 2 ) );
	$b = hexdec( substr( $hex, 4, 2 ) );

	// WCAG relative luminance (https://www.w3.org/WAI/GL/wiki/Relative_luminance)
	$rs           = $r / 255;
	$gs           = $g / 255;
	$bs           = $b / 255;
	$rl           = $rs <= 0.03928 ? $rs / 12.92 : pow( ( $rs + 0.055 ) / 1.055, 2.4 );
	$gl           = $gs <= 0.03928 ? $gs / 12.92 : pow( ( $gs + 0.055 ) / 1.055, 2.4 );
	$bl           = $bs <= 0.03928 ? $bs / 12.92 : pow( ( $bs + 0.055 ) / 1.055, 2.4 );
	$luminance_bg = 0.2126 * $rl + 0.7152 * $gl + 0.0722 * $bl;

	// Pre-computed luminance: white=1.0, black=0.0
	// Contrast = (L_light + 0.05) / (L_dark + 0.05)
	$contrast_white = ( 1.0 + 0.05 ) / ( $luminance_bg + 0.05 );
	$contrast_black = ( $luminance_bg + 0.05 ) / ( 0.0 + 0.05 );

	return $contrast_black >= $contrast_white ? '#000000' : '#ffffff';
}

/*******************************************************************************
 * Adds the "Color" field to the new-category creation form                    *
 *******************************************************************************/
function rd_category_color_add_form_field() {
	?>
	<div class="form-field term-color-wrap">
		<label for="rd_category_color"><?php esc_html_e( 'Color', 'reloaded' ); ?></label>
		<input type="text" name="rd_category_color" id="rd_category_color" value="" class="rd-color-picker" data-default-color="<?php echo esc_attr( RD_CATEGORY_COLOR_DEFAULT ); ?>" />
		<p><?php esc_html_e( 'Background color for this category badge: shown on post cards in grids and next to the title at the top of single posts. The text color is automatically adjusted (black or white) for readable contrast. Leave the default to fall back to the theme gray.', 'reloaded' ); ?></p>
	</div>
	<?php
}
add_action( 'category_add_form_fields', 'rd_category_color_add_form_field' );

/*******************************************************************************
 * Adds the "Color" field to the existing-category edit form                   *
 *******************************************************************************/
function rd_category_color_edit_form_field( WP_Term $term ) {
	$color = get_term_meta( $term->term_id, 'rd_category_color', true );
	if ( empty( $color ) ) {
		$color = RD_CATEGORY_COLOR_DEFAULT;
	}
	?>
	<tr class="form-field term-color-wrap">
		<th scope="row"><label for="rd_category_color"><?php esc_html_e( 'Color', 'reloaded' ); ?></label></th>
		<td>
			<input type="text" name="rd_category_color" id="rd_category_color" value="<?php echo esc_attr( $color ); ?>" class="rd-color-picker" data-default-color="<?php echo esc_attr( RD_CATEGORY_COLOR_DEFAULT ); ?>" />
			<p class="description"><?php esc_html_e( 'Background color for this category badge: shown on post cards in grids and next to the title at the top of single posts. The text color is automatically adjusted (black or white) for readable contrast.', 'reloaded' ); ?></p>
		</td>
	</tr>
	<?php
}
add_action( 'category_edit_form_fields', 'rd_category_color_edit_form_field' );

/*******************************************************************************
 * Saves the term meta when the admin creates or edits the category            *
 *******************************************************************************/
function rd_category_color_save( int $term_id ): void {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WP verifies the nonce upstream in wp_insert_term/wp_update_term before firing the created_category/edited_category hooks. Explicit capability check below as defense in depth.
	if ( ! isset( $_POST['rd_category_color'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- same reason as above (WP's upstream nonce).
	$raw   = sanitize_text_field( wp_unslash( $_POST['rd_category_color'] ) );
	$color = sanitize_hex_color( $raw );

	if ( $color && $color !== RD_CATEGORY_COLOR_DEFAULT ) {
		update_term_meta( $term_id, 'rd_category_color', $color );
	} else {
		// Default or invalid → clear it to fall back to the SCSS fallback
		delete_term_meta( $term_id, 'rd_category_color' );
	}
}
add_action( 'created_category', 'rd_category_color_save' );
add_action( 'edited_category', 'rd_category_color_save' );

/*******************************************************************************
 * Enqueues the wp-color-picker on the category edit screens                   *
 *******************************************************************************/
function rd_category_color_admin_enqueue( string $hook ): void {
	if ( $hook !== 'edit-tags.php' && $hook !== 'term.php' ) {
		return;
	}

	// The hook isn't enough — check the taxonomy via query var (term.php) or GET (edit-tags.php)
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only gate in admin_enqueue_scripts: decides whether to load the color picker based on the current taxonomy, doesn't process a form.
	$taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '';
	if ( $taxonomy !== 'category' ) {
		return;
	}

	wp_enqueue_style( 'wp-color-picker' );
	wp_enqueue_script(
		'rd-category-color',
		get_template_directory_uri() . '/assets/js/admin-category-color.js',
		array( 'wp-color-picker', 'jquery' ),
		rd_asset_version( '/assets/js/admin-category-color.js' ),
		true
	);
}
add_action( 'admin_enqueue_scripts', 'rd_category_color_admin_enqueue' );

/*******************************************************************************
 * Injects the <style> with dynamic rules into <wp_head>                       *
 *                                                                             *
 * Iterates over all categories that have `rd_category_color` in term meta and *
 * generates one CSS rule for each. Those without meta follow the SCSS         *
 * fallback (.post-tag { background-color: #555 }).                            *
 *******************************************************************************/
function rd_category_color_render_styles(): void {
	$cats = get_terms(
		array(
			'taxonomy'   => 'category',
			'hide_empty' => false,
			'fields'     => 'id=>slug',
		)
	);

	if ( empty( $cats ) || is_wp_error( $cats ) ) {
		return;
	}

	$css = '';
	foreach ( $cats as $term_id => $slug ) {
		$bg = get_term_meta( (int) $term_id, 'rd_category_color', true );
		if ( empty( $bg ) ) {
			continue;
		}

		$fg   = rd_get_contrast_text_color( $bg );
		$css .= '.post-tag.tag-' . esc_attr( $slug ) . '{background-color:' . esc_attr( $bg ) . ';color:' . esc_attr( $fg ) . ';}';
	}

	if ( empty( $css ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $css is built by concatenating CSS rules where every dynamic value (slug, bg, fg) is esc_attr()-encoded above (line ~183). Nonce attribute already escaped by rd_csp_nonce_attr().
	echo '<style id="rd-category-colors"' . rd_csp_nonce_attr() . '>' . $css . '</style>' . "\n";
}
add_action( 'wp_head', 'rd_category_color_render_styles', 20 );

/*******************************************************************************
 * Helper: renders the "kicker" (primary category chip) in the single header   *
 *                                                                             *
 * Called from single.php right above the title. Reuses the                    *
 * `_rd_primary_category` meta (a mod-general feature) to choose which to show *
 * when the post has multiple categories.                                      *
 *******************************************************************************/
function rd_render_single_category_kicker(): void {
	if ( ! is_singular( 'post' ) ) {
		return;
	}

	$post_id = get_the_ID();
	$cats    = get_the_category( $post_id );
	if ( empty( $cats ) ) {
		return;
	}

	// Primary category when set; fallback to the first
	$primary_id = (int) get_post_meta( $post_id, '_rd_primary_category', true );
	$chosen     = null;

	if ( $primary_id ) {
		foreach ( $cats as $c ) {
			if ( (int) $c->term_id === $primary_id ) {
				$chosen = $c;
				break;
			}
		}
	}

	if ( ! $chosen ) {
		$chosen = $cats[0];
	}

	printf(
		'<a href="%s" class="post-tag tag-%s entry-kicker">%s</a>',
		esc_url( get_category_link( $chosen->term_id ) ),
		esc_attr( $chosen->slug ),
		esc_html( $chosen->name )
	);
}
