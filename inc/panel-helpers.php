<?php
/**
 * Panel UI Component Helpers — Wave 11 Fase C.
 *
 * @package ReloadeD
 */

defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Panel UI Component Helpers — Wave 11 Phase C                         *
 *                                                                              *
 * Component system for the admin panel UI. Replaces ad-hoc markup from the    *
 * 3 custom-rendered sections (Stats, CSP Reports, Backup) with DRY helpers.   *
 *                                                                              *
 * CSS namespace: `.rd-p*` (rd-pdash, rd-pcard, rd-pbadge, etc.)               *
 * Visual definitions: `assets/css/admin-style.css` (components "rd-p"         *
 * grouped in their own block at the top of the file).                          *
 *                                                                              *
 * Typical usage (inside a custom section callback):                            *
 *                                                                              *
 *   function my_section_callback() {                                           *
 *       rd_panel_dash_open();                                                  *
 *       rd_panel_dash_header( array(                                           *
 *           'info'   => __( 'Status: ', 'reloaded' ) .                         *
 *                       rd_panel_badge( 'success', 'ON' ),                     *
 *           'action' => '<a href="..." class="button">Action</a>',             *
 *       ) );                                                                   *
 *       rd_panel_card_open( array( 'title' => __( 'My Card', 'reloaded' ) ) ); *
 *       echo '<p>Content here...</p>';                                         *
 *       rd_panel_card_close();                                                 *
 *       rd_panel_dash_close();                                                 *
 *   }                                                                          *
 *                                                                              *
 * Status (2026-05-23):                                                         *
 * - Components created and available for use in new code.                     *
 * - The 3 current custom-rendered sections (Stats, CSP Reports, Backup)       *
 *   keep using their legacy classes (`.rd-stats-*`, `.rd-csp-*`,              *
 *   `.rd-backup-*`). Refactor to the new components comes in Phase E.         *
 *******************************************************************************/

/**
 * Opens the "dashboard" wrapper — typical container of a custom-rendered section.
 *
 * More than a div, it is the semantic marker for "this section uses the new
 * panel design system". Combines well with rd_panel_dash_header() +
 * rd_panel_card_open().
 *
 * @param array $args Optional arguments.
 *
 *     @type string $class Optional extra CSS class (concatenated with `rd-pdash`).
 */
function rd_panel_dash_open( array $args = array() ): void {
	$extra_class = isset( $args['class'] ) ? ' ' . sanitize_html_class( $args['class'] ) : '';
	echo '<div class="rd-pdash' . esc_attr( $extra_class ) . '">';
}

/**
 * Closes the wrapper opened by rd_panel_dash_open().
 */
function rd_panel_dash_close(): void {
	echo '</div>';
}

/**
 * Renders the grey dashboard header — info on the left + action on the right.
 *
 * Pattern used today in Stats Dashboard and CSP Reports: light grey background,
 * descriptive state text on the left, action button on the right (optional).
 *
 * @param array $args Header arguments.
 *
 *     @type string $info   HTML for the left side (textual info). PRE-ESCAPED by the caller.
 *     @type string $action HTML for the right side (button/link), optional. PRE-ESCAPED by the caller.
 */
function rd_panel_dash_header( array $args ): void {
	$info   = $args['info'] ?? '';
	$action = $args['action'] ?? '';

	echo '<div class="rd-pdash__header">';
	if ( '' !== $info ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller is responsible for escaping; internal helpers (rd_panel_badge) already return escaped HTML.
		echo '<span class="rd-pdash__header-info">' . $info . '</span>';
	}
	if ( '' !== $action ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller responsible (usually an <a class="button"> with URLs already passed through esc_url).
		echo '<span class="rd-pdash__header-action">' . $action . '</span>';
	}
	echo '</div>';
}

/**
 * Opens a white card — primary content container inside the dashboard.
 *
 * Accepts optional title/desc/hint. When none of these is passed, the card
 * stays "clean" — useful for wrapping tables (CSP Reports) or lists (Top
 * Posts of Stats).
 *
 * Visual modifiers via `variant`:
 *   - `default`     — regular white card (default)
 *   - `placeholder` — light grey background + dashed border (pending feature)
 *   - `warning`     — amber left-border (reversible but dangerous action, e.g. Restore)
 *   - `danger`      — red left-border (destructive action)
 *
 * @param array $args Card arguments.
 *
 *     @type string $title   Uppercase card title, optional.
 *     @type string $tooltip Short hover explanation on the title, optional (plain text).
 *     @type string $desc    Description below the title, optional (accepts HTML).
 *     @type string $hint    Discreet italic hint in the footer, optional (accepts HTML).
 *     @type string $variant One of: default, placeholder, warning, danger.
 *     @type string $class   Optional extra CSS class.
 */
function rd_panel_card_open( array $args = array() ): void {
	$title   = $args['title'] ?? '';
	$tooltip = $args['tooltip'] ?? '';
	$desc    = $args['desc'] ?? '';
	$hint    = $args['hint'] ?? '';
	$variant = $args['variant'] ?? 'default';
	$class   = isset( $args['class'] ) ? ' ' . sanitize_html_class( $args['class'] ) : '';

	$allowed_variants = array( 'default', 'placeholder', 'warning', 'danger' );
	if ( ! in_array( $variant, $allowed_variants, true ) ) {
		$variant = 'default';
	}
	$variant_class = 'default' === $variant ? '' : ' rd-pcard--' . $variant;

	echo '<div class="rd-pcard' . esc_attr( $variant_class . $class ) . '">';

	if ( '' !== $title ) {
		echo '<h3 class="rd-pcard__title"';
		if ( '' !== $tooltip ) {
			echo ' data-tooltip="' . esc_attr( $tooltip ) . '"';
		}
		echo '>' . esc_html( $title ) . '</h3>';
	}
	if ( '' !== $desc ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller passes controlled HTML (text + <code>/<strong>); using wp_kses_post would be overhead for internal panel usage.
		echo '<p class="rd-pcard__desc">' . $desc . '</p>';
	}
	echo '<div class="rd-pcard__body">';

	// If a hint was passed, the body is closed and the hint injected after the
	// close — but since the caller writes content between open/close, the hint
	// must be inserted on close. Stashed in $GLOBALS for rd_panel_card_close().
	if ( '' !== $hint ) {
		$GLOBALS['_rd_panel_card_hint'] = $hint;
	}
}

/**
 * Closes a card opened by rd_panel_card_open().
 *
 * If rd_panel_card_open() received a `hint`, it is rendered here (after the
 * card content, before the closing tag). Uses $GLOBALS to carry the hint
 * between the two calls — ugly but pragmatic to avoid buffers or closures.
 */
function rd_panel_card_close(): void {
	echo '</div>'; // .rd-pcard__body

	if ( ! empty( $GLOBALS['_rd_panel_card_hint'] ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hint is controlled HTML (caller already handled escaping).
		echo '<p class="rd-pcard__hint">' . $GLOBALS['_rd_panel_card_hint'] . '</p>';
		unset( $GLOBALS['_rd_panel_card_hint'] );
	}

	echo '</div>'; // .rd-pcard
}

/**
 * Returns HTML for a status badge (inline coloured chip).
 *
 * Unlike the other helpers, this one RETURNS a string instead of echoing —
 * so it can be concatenated inside text (e.g. "Status: [BADGE]").
 *
 * Variants:
 *   - `info`    — WP blue (#2271b1) — informational / Report-Only
 *   - `success` — green (#007a39)   — ON / OK / Enabled
 *   - `warning` — amber (#d29922)   — Attention / Pending
 *   - `danger`  — red (#d63638)     — Enforce / Error / Disabled
 *   - `neutral` — grey (#757575)    — Inactive / Default
 *
 * @param string $variant One of: info, success, warning, danger, neutral.
 * @param string $text    Badge text (will be uppercased via CSS).
 * @return string Escaped HTML ready to echo.
 */
function rd_panel_badge( string $variant, string $text ): string {
	$allowed = array( 'info', 'success', 'warning', 'danger', 'neutral' );
	if ( ! in_array( $variant, $allowed, true ) ) {
		$variant = 'neutral';
	}
	return '<span class="rd-pbadge rd-pbadge--' . esc_attr( $variant ) . '">'
		. esc_html( $text )
		. '</span>';
}

/**
 * Renders a status banner (coloured stripe with left-border).
 *
 * Larger than the badge — used for feedback messages after actions
 * (import success, upload error, info about an operation in progress).
 *
 * Variants match the badge: info, success, warning, danger.
 *
 * @param string $variant One of: info, success, warning, danger.
 * @param string $text    Message (accepts HTML — caller responsible for escaping).
 */
function rd_panel_status( string $variant, string $text ): void {
	$allowed = array( 'info', 'success', 'warning', 'danger' );
	if ( ! in_array( $variant, $allowed, true ) ) {
		$variant = 'info';
	}
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller is responsible for escaping $text (usually text + <code>/<strong>).
	echo '<div class="rd-pstatus rd-pstatus--' . esc_attr( $variant ) . '">' . $text . '</div>';
}

/**
 * Renders a standardized empty state (centered italic text).
 *
 * Used when a custom section has nothing to show — feature OFF, no data yet,
 * etc. Replaces `.rd-csp-empty`, `.rd-backup-empty`, etc.
 *
 * @param string $message Text to show (passed through esc_html()).
 */
function rd_panel_empty( string $message ): void {
	echo '<p class="rd-pempty">' . esc_html( $message ) . '</p>';
}

/**
 * Renders a section header — used inside add_settings_section() callbacks.
 *
 * Today most sections use `__return_false` as the callback (no header).
 * This helper provides a consistent header: optional dashicons icon + title +
 * optional description. Lets "regular" sections (Settings API) get the same
 * look as the custom-rendered ones.
 *
 * @param array $args Header arguments.
 *
 *     @type string $icon  Dashicon slug (e.g. 'admin-appearance') or '' for no icon.
 *     @type string $title Section title.
 *     @type string $desc  Description (HTML allowed — caller handles escaping).
 */
function rd_panel_section_header( array $args ): void {
	$icon  = $args['icon'] ?? '';
	$title = $args['title'] ?? '';
	$desc  = $args['desc'] ?? '';
	$id    = $args['id'] ?? ''; // optional — becomes the div id for hash anchors.

	if ( '' === $title && '' === $desc ) {
		return;
	}

	$id_attr = '' !== $id ? ' id="' . esc_attr( $id ) . '"' : '';
	echo '<div class="rd-psection-header"' . $id_attr . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $id_attr already escaped above via esc_attr().

	if ( '' !== $icon ) {
		echo '<span class="rd-psection-header__icon dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span>';
	}
	if ( '' !== $title ) {
		// h2 (not h3) to align HTML hierarchy with what Settings API auto-generates
		// in the `<h2>{title}</h2>` of default sections.
		echo '<h2 class="rd-psection-header__title">' . esc_html( $title ) . '</h2>';
	}
	if ( '' !== $desc ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller passes controlled HTML.
		echo '<p class="rd-psection-header__desc">' . $desc . '</p>';
	}

	echo '</div>';
}

/**
 * Wrapper around `add_settings_section()` that registers a regular WP section
 * but with a custom header (dashicons icon + title) instead of the raw `<h2>`
 * automatically generated by the Settings API.
 *
 * Empty-title trick: passing `''` in `add_settings_section()` makes WP NOT
 * render the automatic `<h2>` — the callback emits the full header via
 * `rd_panel_section_header()` (covers consistent icon + title).
 *
 * Replaces the `add_settings_section( $id, $title, '__return_false', $page )`
 * pattern — each call turns into 1 line instead of 6, with the icon as a bonus.
 *
 * @param string $id    Section ID (e.g. 'sec_geral_shell').
 * @param string $title Translatable title.
 * @param string $icon  Dashicon slug (without the `dashicons-` prefix).
 *                      E.g. 'admin-appearance', 'shield-alt'. '' for no icon.
 * @param string $page  Page slug (e.g. 'rd_options_general') — same as in add_settings_section.
 */
function rd_panel_register_section( string $id, string $title, string $icon, string $page ): void {
	add_settings_section(
		$id,
		'', // Empty title = WP does not render the automatic <h2>; callback emits the header.
		static function () use ( $id, $title, $icon ) {
			rd_panel_section_header(
				array(
					'id'    => $id, // div id for hash anchors (Dashboard deep links).
					'icon'  => $icon,
					'title' => $title,
				)
			);
		},
		$page
	);
}
