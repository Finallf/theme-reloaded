<?php
defined( 'ABSPATH' ) || exit;
/*******************************************************************************
 * General Module - Interface Features and Default Behavior
 */

/***********************************************************************************
 * Featured Image Display Control (Single)               - (Interface) - (General) *
 */

// 1. Create the box (Meta Box) only if AT LEAST one of the gate options is
// active in the panel. Today: enable_thumb_control OR enable_primary_category.
// If both are off, the box disappears entirely (doesn't sit empty).
add_action( 'add_meta_boxes', 'rd_add_post_options_meta_box' );
function rd_add_post_options_meta_box() {
	if ( ! rd_get_option_bool( 'enable_thumb_control' ) &&
		! rd_get_option_bool( 'enable_primary_category' ) &&
		! rd_get_option_bool( 'enable_post_kicker' ) ) {
		return;
	}
	add_meta_box(
		'rd_post_options',
		__( 'Post Options (ReloadeD)', 'reloaded' ),
		'rd_post_options_callback',
		'post',
		'side',
		'default'
	);
}

/**
 * 2. Draws the HTML of the controls inside the box.
 *
 * @param WP_Post $post The post currently being edited.
 */
function rd_post_options_callback( $post ) {
	wp_nonce_field( 'rd_save_post_options', 'rd_post_options_nonce' );

	// --- Hide Featured Image (if the global option is active) ---
	if ( rd_get_option_bool( 'enable_thumb_control' ) ) :
		$is_hidden = get_post_meta( $post->ID, '_rd_hide_thumbnail', true );
		?>
		<p>
			<label>
				<input type="checkbox" name="rd_hide_thumbnail" value="yes" <?php checked( $is_hidden, 'yes' ); ?> />
				<?php esc_html_e( 'Hide Featured Image at the top of the post', 'reloaded' ); ?>
			</label>
		</p>
		<p class="description"><?php esc_html_e( 'Check to prevent the thumbnail from duplicating with videos inserted at the beginning of the text.', 'reloaded' ); ?></p>
		<hr class="rd-post-options-divider">
	<?php endif; ?>

	<?php // --- Primary Category (if the global option is active) --- ?>
	<?php if ( rd_get_option_bool( 'enable_primary_category' ) ) : ?>
	<p>
		<label for="rd_primary_category" class="rd-post-options-label">
			<strong><?php esc_html_e( 'Primary Category', 'reloaded' ); ?></strong>
		</label>
		<?php
		$primary_id = (int) get_post_meta( $post->ID, '_rd_primary_category', true );
		$post_cats  = get_the_category( $post->ID );

		if ( empty( $post_cats ) ) :
			?>
			<span class="description"><?php esc_html_e( 'Save the post with categories assigned to choose a primary one.', 'reloaded' ); ?></span>
			<?php
		else :
			?>
			<select id="rd_primary_category" name="rd_primary_category" class="rd-post-options-select">
				<option value="0"><?php esc_html_e( 'Auto (first category)', 'reloaded' ); ?></option>
				<?php foreach ( $post_cats as $cat ) : ?>
					<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( $primary_id, $cat->term_id ); ?>>
						<?php echo esc_html( $cat->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php
		endif;
		?>
	</p>
	<p class="description">
		<?php esc_html_e( 'When the post belongs to multiple categories, only the primary one will highlight in the menu.', 'reloaded' ); ?>
	</p>
	<?php endif; // enable_primary_category ?>

	<?php // --- Post Overline (if the global option is active) --- ?>
	<?php
	if ( rd_get_option_bool( 'enable_post_kicker' ) ) :
		$overline = get_post_meta( $post->ID, '_rd_post_kicker', true );
		?>
		<hr class="rd-post-options-divider">
		<p>
			<label for="rd_post_kicker" class="rd-post-options-label">
				<strong><?php esc_html_e( 'Overline', 'reloaded' ); ?></strong>
			</label>
			<input type="text" id="rd_post_kicker" name="rd_post_kicker" value="<?php echo esc_attr( $overline ); ?>" class="widefat" maxlength="60" placeholder="<?php esc_attr_e( 'e.g. INTERVIEW, EXCLUSIVE, ANALYSIS', 'reloaded' ); ?>" />
		</p>
		<p class="description">
			<?php esc_html_e( 'Optional small label rendered above the title on single posts and on grid cards. Useful for journalism-style overlines. Leave empty to hide.', 'reloaded' ); ?>
		</p>
	<?php endif; // enable_post_kicker ?>
	<?php
}

// 3. Saves the individual preferences in the database
add_action( 'save_post', 'rd_save_post_options' );
function rd_save_post_options( $post_id ) {
	if ( ! isset( $_POST['rd_post_options_nonce'] ) ||
		! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rd_post_options_nonce'] ) ), 'rd_save_post_options' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Hide Featured Image
	if ( isset( $_POST['rd_hide_thumbnail'] ) ) {
		update_post_meta( $post_id, '_rd_hide_thumbnail', 'yes' );
	} else {
		delete_post_meta( $post_id, '_rd_hide_thumbnail' );
	}

	// Primary Category — only saves if the global option is active AND the ID is
	// of a category actually assigned to the post
	if ( rd_get_option_bool( 'enable_primary_category' ) && isset( $_POST['rd_primary_category'] ) ) {
		$chosen_id = absint( $_POST['rd_primary_category'] );
		if ( $chosen_id === 0 ) {
			// "Auto" → clears the meta to use the first-category fallback
			delete_post_meta( $post_id, '_rd_primary_category' );
		} else {
			$post_cat_ids = wp_get_post_categories( $post_id );
			if ( in_array( $chosen_id, $post_cat_ids, true ) ) {
				update_post_meta( $post_id, '_rd_primary_category', $chosen_id );
			} else {
				// Chosen category no longer belongs to the post — clear it
				delete_post_meta( $post_id, '_rd_primary_category' );
			}
		}
	}

	// Post Overline — only saves if the global option is active
	if ( rd_get_option_bool( 'enable_post_kicker' ) && isset( $_POST['rd_post_kicker'] ) ) {
		$overline = sanitize_text_field( wp_unslash( $_POST['rd_post_kicker'] ) );
		if ( ! empty( $overline ) ) {
			update_post_meta( $post_id, '_rd_post_kicker', $overline );
		} else {
			delete_post_meta( $post_id, '_rd_post_kicker' );
		}
	}
}

/***********************************************************************************
 * Helper: returns the HTML of a post's "Overline" as a string.        - (General) *
 *                                                                                 *
 * The "get" version to be consumed where markup is built by concatenation         *
 * (e.g. `rd_render_post_card()` in `inc/post-card.php`). Same logic as            *
 * `rd_render_post_overline()` which just wraps this function with `echo`.         *
 *                                                                                 *
 * Returns an empty string when: global feature off, invalid post_id, or overline  *
 * not filled — the caller can concatenate safely without an extra check.          *
 ***********************************************************************************/
function rd_get_post_overline_html( int $post_id = 0, string $context = 'single' ): string {
	if ( ! rd_get_option_bool( 'enable_post_kicker' ) ) {
		return '';
	}

	if ( ! $post_id ) {
		$post_id = (int) get_the_ID();
	}
	if ( ! $post_id ) {
		return '';
	}

	$overline = trim( (string) get_post_meta( $post_id, '_rd_post_kicker', true ) );
	if ( empty( $overline ) ) {
		return '';
	}

	$context_safe = preg_replace( '/[^a-z0-9_-]/i', '', $context );
	if ( empty( $context_safe ) ) {
		$context_safe = 'single';
	}

	return sprintf(
		'<span class="entry-overline entry-overline-%s">%s</span>',
		esc_attr( $context_safe ),
		esc_html( $overline )
	);
}

/***********************************************************************************
 * Helper: prints the "Overline" (kicker) above a post's title.        - (General) *
 *                                                                                 *
 * Echo wrapper over `rd_get_post_overline_html()`. Use directly in templates      *
 * (single.php, index.php) where the markup is structured with PHP echo tags.      *
 ***********************************************************************************/
function rd_render_post_overline( int $post_id = 0, string $context = 'single' ): void {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns already-escaped HTML (esc_attr + esc_html applied internally)
	echo rd_get_post_overline_html( $post_id, $context );
}

/***********************************************************************************
 * Dynamic image quality - GENERAL OPTIONS                             - (General) *
 ***********************************************************************************/
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- $quality is part of the `jpeg_quality`/`webp_quality`/etc filter signatures; we ignore the received value and return the one configured in the panel.
function rd_custom_image_quality( $quality ) {
	$opt = get_option( 'rd_settings' );

	// Strict comparison with '' instead of !empty() — prevents legitimate
	// numeric values (which could coincide with PHP falsy) from being
	// discarded. Defensive range clamping (1-100) in case some invalid value
	// reaches here bypassing the panel input's min/max.
	if ( isset( $opt['jpeg_quality'] ) && $opt['jpeg_quality'] !== '' ) {
		return max( 1, min( 100, intval( $opt['jpeg_quality'] ) ) );
	}
	return 90;
}
add_filter( 'jpeg_quality', 'rd_custom_image_quality' );
add_filter( 'webp_quality', 'rd_custom_image_quality' );
add_filter( 'avif_quality', 'rd_custom_image_quality' );
add_filter( 'wp_editor_set_quality', 'rd_custom_image_quality' );

/*******************************************************************************
 * Global comments kill switch                                     - (General) *
 *                                                                             *
 * When `enable_comments_globally` is OFF:                                     *
 *   - `comments_open` returns false → form and list don't render              *
 *   - `pings_open` returns false → pingbacks/trackbacks disabled              *
 *   - Applies to ALL existing posts instantly (no need to                     *
 *     bulk-edit the DB or run SQL)                                            *
 *                                                                             *
 * When ON: native WP behavior is respected (per-post + WP Settings).          *
 *                                                                             *
 * Plugins that override `comments_template` (WP-Discourse, Disqus, etc)       *
 * keep working regardless of this toggle — they operate on a hook             *
 * downstream that isn't affected by `comments_open`.                          *
 *******************************************************************************/
function rd_filter_comments_open( $open ) {
	if ( ! rd_get_option_bool( 'enable_comments_globally' ) ) {
		return false;
	}
	return $open;
}
add_filter( 'comments_open', 'rd_filter_comments_open' );
add_filter( 'pings_open', 'rd_filter_comments_open' ); // same logic for pingbacks/trackbacks

/*******************************************************************************
 * Hides the list of existing comments when the kill switch is OFF             *
 *                                                                             *
 * The `comments_open` filter alone blocks NEW comments but the list of        *
 * existing ones keeps rendering. This complementary filter returns an empty   *
 * array to `wp_list_comments()` — nobody shows on the front (the whole list   *
 * disappears). The DB stays untouched: flipping the toggle restores it all.   *
 *                                                                             *
 * Also zeroes the displayed `comment_count` ("X comments" icon in the meta),  *
 * via the `get_comments_number` filter, to avoid visual inconsistency.        *
 *******************************************************************************/
function rd_hide_comments_when_disabled( $comments ) {
	if ( ! rd_get_option_bool( 'enable_comments_globally' ) ) {
		return array();
	}
	return $comments;
}
add_filter( 'comments_array', 'rd_hide_comments_when_disabled' );

function rd_zero_comments_count_when_disabled( $count ) {
	if ( ! rd_get_option_bool( 'enable_comments_globally' ) ) {
		return 0;
	}
	return $count;
}
add_filter( 'get_comments_number', 'rd_zero_comments_count_when_disabled' );

/*******************************************************************************
 * Zeroes frontend comment queries when the kill switch is OFF                 *
 *                                                                             *
 * The `comments_array` and `get_comments_number` filters cover rendering      *
 * of the `comments.php` template but do NOT affect direct comment queries     *
 * made elsewhere — mainly the "Recent Comments" widget                        *
 * (legacy WP_Widget_Recent_Comments + core/latest-comments block) and any     *
 * frontend code that uses `get_comments()` or `WP_Comment_Query`.             *
 *                                                                             *
 * We inject `post__in = [0]` (a non-existent post ID) → the query always      *
 * returns an empty array. Admin is preserved (`! is_admin()`) so moderation   *
 * keeps working. Toggle flipped back = queries return to normal instantly.    *
 *******************************************************************************/
function rd_disable_frontend_comment_queries( $query ) {
	if ( is_admin() ) {
		return;
	}
	if ( rd_get_option_bool( 'enable_comments_globally' ) ) {
		return;
	}

	$query->query_vars['post__in'] = array( 0 );
}
add_action( 'pre_get_comments', 'rd_disable_frontend_comment_queries' );

/*******************************************************************************
 * Hides entire "Recent Comments" widgets when the kill switch is off          *
 *                                                                             *
 * Filtering the query (above) already zeroes the widget content, but it still *
 * renders the title and the "No comments to show" message — orphan-widget     *
 * look. Here we skip the full render of both flavors:                         *
 *                                                                             *
 *   - `WP_Widget_Recent_Comments` (legacy widget, in old sidebars)            *
 *   - `core/latest-comments` (block widget, Gutenberg widgets editor)         *
 *                                                                             *
 * Toggle flipped back → widgets reappear instantly, without losing config.    *
 *******************************************************************************/
function rd_hide_legacy_recent_comments_widget( $instance, $widget ) {
	if ( ! rd_get_option_bool( 'enable_comments_globally' ) && $widget instanceof WP_Widget_Recent_Comments ) {
		return false; // skip render
	}
	return $instance;
}
add_filter( 'widget_display_callback', 'rd_hide_legacy_recent_comments_widget', 10, 2 );

function rd_hide_block_latest_comments( $pre_render, $parsed_block ) {
	if ( ! rd_get_option_bool( 'enable_comments_globally' )
		&& isset( $parsed_block['blockName'] )
		&& $parsed_block['blockName'] === 'core/latest-comments' ) {
		return ''; // empty string skips the block render
	}
	return $pre_render;
}
add_filter( 'pre_render_block', 'rd_hide_block_latest_comments', 10, 2 );

/*******************************************************************************
 * Fixes Accessibility warnings in the Comment Form                - (General) *
 *******************************************************************************/
function rd_fix_comment_form_labels( $fields ) {

	if ( ! rd_get_option_bool( 'comment_a11y' ) ) {
		return $fields;
	}

	$commenter = wp_get_current_commenter();
	$req       = get_option( 'require_name_email' );
	$html_req  = ( $req ? " required='required'" : '' );

	// Rewrites the NAME field (added autocomplete="name")
	$fields['author'] = '<p class="comment-form-author">' .
						'<label for="author_name">' . esc_html__( 'Name', 'reloaded' ) . ( $req ? ' <span class="required">*</span>' : '' ) . '</label> ' .
						'<input id="author_name" name="author" type="text" value="' . esc_attr( $commenter['comment_author'] ) . '" size="30" maxlength="245" autocomplete="name"' . $html_req . ' />' .
						'</p>';

	// Rewrites the EMAIL field (added autocomplete="email")
	$fields['email'] = '<p class="comment-form-email">' .
						'<label for="author_email">' . esc_html__( 'Email', 'reloaded' ) . ( $req ? ' <span class="required">*</span>' : '' ) . '</label> ' .
						'<input id="author_email" name="email" type="email" value="' . esc_attr( $commenter['comment_author_email'] ) . '" size="30" maxlength="100" aria-describedby="email-notes" autocomplete="email"' . $html_req . ' />' .
						'</p>';

	// Rewrites the WEBSITE field (added autocomplete="url")
	$fields['url'] = '<p class="comment-form-url">' .
						'<label for="author_url">' . esc_html__( 'Website', 'reloaded' ) . '</label> ' .
						'<input id="author_url" name="url" type="url" value="' . esc_attr( $commenter['comment_author_url'] ) . '" size="30" maxlength="200" autocomplete="url" />' .
						'</p>';

	// Rewrites the COOKIES field
	$consent           = empty( $commenter['comment_author_email'] ) ? '' : ' checked="checked"';
	$fields['cookies'] = '<p class="comment-form-cookies-consent">' .
						'<input id="wp-comment-cookies-consent-id" name="wp-comment-cookies-consent" type="checkbox" value="yes"' . $consent . ' />' .
						'<label for="wp-comment-cookies-consent-id">' . esc_html__( 'Save my name, email, and website in this browser for the next time I comment.', 'reloaded' ) . '</label>' .
						'</p>';

	return $fields;
}
add_filter( 'comment_form_default_fields', 'rd_fix_comment_form_labels' );

/*******************************************************************************
 * Markdown in comments (rendering)                                - (General) *
 *                                                                             *
 * Replaces the default `comment_text` pipeline (wpautop + texturize) with     *
 * Parsedown — the same lib we already load for posts. Comments gain           *
 * support for:                                                                *
 *                                                                             *
 *   - `**bold**` / `_italic_`                                                 *
 *   - `> quote`                                                               *
 *   - `[text](url)` links                                                     *
 *   - lists, headers, inline code + blocks with syntax highlight              *
 *   - tables (GFM)                                                            *
 *                                                                             *
 * Parsedown in safe mode = strips dangerous HTML (scripts, iframes etc),      *
 * only pure Markdown is converted. Regular users write normal text,           *
 * power users format with Markdown — both work.                               *
 *                                                                             *
 * The additional `setMarkupEscaped(true)` escapes any literal HTML the        *
 * user tries to paste — double protection.                                    *
 *******************************************************************************/
function rd_comment_markdown( $content ) {
	if ( ! class_exists( 'Parsedown' ) ) {
		require_once get_template_directory() . '/lib/Parsedown.php';
	}

	$parsedown = new Parsedown();
	$parsedown->setSafeMode( true );
	$parsedown->setMarkupEscaped( true );

	return $parsedown->text( $content );
}
// IMPORTANT: Parsedown runs at priority 5 — BEFORE WP's default filters
// (make_clickable=9, wptexturize=10, convert_chars=10, force_balance_tags=25,
// wpautop=30) which mangle the Markdown input:
// - convert_chars turns `>` into `&gt;` (breaks blockquotes)
// - wptexturize turns `-` into `&#8211;` (breaks lists, swaps for an en-dash)
// - make_clickable converts raw URLs before Parsedown sees the [text](url) syntax
// Running first, the output is ALREADY HTML with `<a>`, `<ul>`, `<blockquote>` etc.,
// and the following filters operate only on text nodes (harmless typography touches).
add_filter( 'comment_text', 'rd_comment_markdown', 5 );

// Remove filters that ruin structured HTML after Parsedown:
// - wpautop turns line breaks into <p>, duplicating Parsedown's tags
// - force_balance_tags can break our valid HTML
remove_filter( 'comment_text', 'wpautop', 30 );
remove_filter( 'comment_text', 'force_balance_tags', 25 );

/*******************************************************************************
 * "Markdown supported" hint next to the comment form label        - (General) *
 *                                                                             *
 * Hook on `comment_form_field_comment` that injects a <span> with short       *
 * examples right AFTER the textarea's </label> — it becomes a sibling of the  *
 * label in the DOM, and the CSS positions one left and one right on one line. *
 *                                                                             *
 * We use <span> (not <p>) because the field is already a <p class="comment-   *
 * form-comment">, and <p> nested in <p> is invalid HTML (the parser closed    *
 * the parent). Appears for ALL visitors (logged in or not).                   *
 *******************************************************************************/
function rd_comment_form_markdown_hint( $field ) {
	// The id is referenced by aria-describedby on the <textarea> (a11y: screen
	// reader announces the markdown hint when the field gains focus).
	$hint  = '<span id="rd-comment-md-hint" class="rd-comment-markdown-hint">';
	$hint .= esc_html__( 'Markdown:', 'reloaded' );
	$hint .= ' <code>**bold**</code> <code>_italic_</code> <code>[link](url)</code> <code>> quote</code> <code>`code`</code>';
	$hint .= '</span>';

	// Add aria-describedby on the <textarea> linking it to the hint.
	$field = preg_replace(
		'/<textarea\b([^>]*)>/i',
		'<textarea$1 aria-describedby="rd-comment-md-hint">',
		$field,
		1
	);

	// Inject the hint right after the field's first </label>. Ensures a
	// semantic position (label + hint together, describing the same input).
	$needle = '</label>';
	$pos    = strpos( $field, $needle );
	if ( false !== $pos ) {
		return substr_replace( $field, $needle . $hint, $pos, strlen( $needle ) );
	}

	// Defensive fallback: if the markup changes and has no </label>, keep
	// the old behavior of appending after the field.
	return $field . $hint;
}
add_filter( 'comment_form_field_comment', 'rd_comment_form_markdown_hint' );

/*******************************************************************************
 * Sidebar position (left/right)                                   - (General) *
 *                                                                             *
 * Adds the `rd-sidebar-left` class to <body> when the admin chooses to        *
 * position the sidebar on the left. The CSS does the flip via `flex-direction:*
 * row-reverse` on the .site-main container, without touching markup.          *
 *                                                                             *
 * Mobile (≤1024px) ignores the flag — sidebar always goes below the content   *
 * (managed by the _grid.scss media query).                                    *
 *******************************************************************************/
function rd_add_sidebar_position_body_class( array $classes ): array {
	if ( rd_get_option( 'sidebar_position', 'right' ) === 'left' ) {
		$classes[] = 'rd-sidebar-left';
	}
	return $classes;
}
add_filter( 'body_class', 'rd_add_sidebar_position_body_class' );

/*******************************************************************************
 * Word count in automatic excerpts                                - (General) *
 *                                                                             *
 * WordPress default is 55. The admin can adjust it via the panel (General →   *
 * Excerpt Length). Posts with a manual excerpt (editor's "Excerpt" field)     *
 * don't go through `wp_trim_words` and so aren't affected by this setting.    *
 *******************************************************************************/
function rd_custom_excerpt_length( $length ) {
	$opt = get_option( 'rd_settings' );

	if ( isset( $opt['excerpt_length'] ) && (int) $opt['excerpt_length'] > 0 ) {
		return (int) $opt['excerpt_length'];
	}
	return $length; // keeps WP's default (55)
}
add_filter( 'excerpt_length', 'rd_custom_excerpt_length', 999 );

/*******************************************************************************
 * Customizes the "Read More" text                                 - (General) *
 *******************************************************************************/
function rd_custom_excerpt_more( $more ) {
	$opt = get_option( 'rd_settings' );

	// Strict comparison with '' instead of !empty() — `empty('0')` returns
	// true, which would discard a literal "0" text as the button label.
	// Edge case, but keeps the pattern consistent across the whole theme.
	if ( isset( $opt['excerpt_text'] ) && $opt['excerpt_text'] !== '' ) {
		return '... <br><span class="more-link">' . esc_html( $opt['excerpt_text'] ) . '</span>';
	}

	return $more;
}
add_filter( 'excerpt_more', 'rd_custom_excerpt_more' );

/*******************************************************************************
 * Changes the "on" text in the Latest Comments block              - (General) *
 *******************************************************************************/
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $domain is part of the `gettext` filter signature, even when we don't consult the text domain.
function rd_change_latest_comments_text( $translated_text, $text, $domain ) {
	if ( $text === '%1$s on %2$s' || $text === '%s on %s' ) {

		$opt = get_option( 'rd_settings' );
		$sep = isset( $opt['comments_separator'] ) ? $opt['comments_separator'] : '';

		if ( $sep === '' ) {
			return $translated_text;
		}

		return '%1$s ' . wp_kses_post( $sep ) . ' %2$s';
	}

	return $translated_text;
}
add_filter( 'gettext', 'rd_change_latest_comments_text', 20, 3 );
