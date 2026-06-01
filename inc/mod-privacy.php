<?php
defined( 'ABSPATH' ) || exit;
/*******************************************************************************
 * Privacy Module - Consent and LGPD                                            *
 *                                                                              *
 * Granular per-category consent system (LGPD/GDPR-compliant):                  *
 *                                                                              *
 *   - Necessary (always on, required for the site to work)                     *
 *   - Statistics (Google Analytics, Clarity, Plausible/Umami)                  *
 *   - Marketing (Facebook Pixel, TikTok Pixel)                                 *
 *                                                                              *
 * Storage: a single `rd_lgpd_consent` cookie in JSON, 365-day validity.        *
 * State queried via `rd_consent_given('analytics'|'marketing'|'necessary')`    *
 * — every tracking script checks this function before injecting.               *
 *                                                                              *
 * When `enable_lgpd` is OFF, the system stays dormant: no banner,              *
 * no footer link, and `rd_consent_given()` always returns true (admin          *
 * assumed compliance elsewhere — internal sites, dev, etc).                    *
 *******************************************************************************/

/*******************************************************************************
 * Module constants                                                             *
 *******************************************************************************/
const RD_LGPD_COOKIE_NAME    = 'rd_lgpd_consent';
const RD_LGPD_LEGACY_COOKIE  = 'rd_lgpd_accepted';   // legacy, for migration
const RD_LGPD_COOKIE_VERSION = 1;                    // bump invalidates old cookies
const RD_LGPD_CATEGORIES     = array( 'necessary', 'analytics', 'marketing' );

/*******************************************************************************
 * Reads the consent state from the cookie (with legacy cookie migration)       *
 *                                                                              *
 * Always returns 4 keys:                                                       *
 *   - necessary (bool — always true)                                           *
 *   - analytics (bool)                                                         *
 *   - marketing (bool)                                                         *
 *   - choice_made (bool — true if the user already interacted with the banner) *
 *                                                                              *
 * Static cache per request — the cookie is read only once.                     *
 *******************************************************************************/
function rd_lgpd_get_consent(): array {
	static $cached = null;
	if ( $cached !== null ) {
		return $cached;
	}

	$default = array(
		'necessary'   => true,
		'analytics'   => false,
		'marketing'   => false,
		'choice_made' => false,
	);

	// 1. New cookie (JSON format v1+)
	if ( isset( $_COOKIE[ RD_LGPD_COOKIE_NAME ] ) ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON content; sanitize_text_field would destroy the structure. Validation happens via json_decode + `is_array`/`isset(['version'])` checks below. wp_unslash is the WP-idiomatic equivalent of stripslashes.
		$raw  = wp_unslash( $_COOKIE[ RD_LGPD_COOKIE_NAME ] );
		$data = json_decode( $raw, true );
		if ( is_array( $data ) && isset( $data['version'] ) && (int) $data['version'] === RD_LGPD_COOKIE_VERSION ) {
			$cached = array(
				'necessary'   => true, // always on, regardless of the cookie
				'analytics'   => ! empty( $data['analytics'] ),
				'marketing'   => ! empty( $data['marketing'] ),
				'choice_made' => true,
			);
			return $cached;
		}
	}

	// 2. Legacy cookie (`rd_lgpd_accepted=true`) — in-memory migration
	// The legacy cookie meant "accepted everything" in the old banner (single button).
	// We treat it as all-accepted to avoid re-prompting an existing user. Clearing
	// the legacy cookie is done by the JS (can't setcookie here — headers
	// may have already been sent).
	if ( isset( $_COOKIE[ RD_LGPD_LEGACY_COOKIE ] ) ) {
		$cached = array(
			'necessary'   => true,
			'analytics'   => true,
			'marketing'   => true,
			'choice_made' => true,
		);
		return $cached;
	}

	// 3. No cookie — choice pending
	$cached = $default;
	return $cached;
}

/*******************************************************************************
 * Public helper: checks whether the user consented to a category               *
 *                                                                              *
 * Every tracking script must call this function before injecting:              *
 *   if ( ! rd_consent_given('analytics') ) return;                             *
 *                                                                              *
 * When the banner is off in the panel, always returns true (dormant system).   *
 *******************************************************************************/
function rd_consent_given( string $category ): bool {
	// Banner turned off by the admin → dormant system, everything passes
	if ( ! rd_get_option_bool( 'enable_lgpd' ) ) {
		return true;
	}

	// Necessary always on
	if ( $category === 'necessary' ) {
		return true;
	}

	// Unknown category → defensive false
	if ( ! in_array( $category, RD_LGPD_CATEGORIES, true ) ) {
		return false;
	}

	$consent = rd_lgpd_get_consent();
	return ! empty( $consent[ $category ] );
}

/*******************************************************************************
 * Renders the LGPD consent banner                                - (Privacy)   *
 *                                                                              *
 * Markup has 2 visual modes controlled via CSS/JS:                             *
 *   - Compact (default): text + 3 buttons                                      *
 *   - Expanded: + 3 category toggles (shown when clicking "Customize")         *
 *                                                                              *
 * State is serialized in data-attributes for the JS to consume. CSS handles    *
 * the transition via max-height + opacity.                                     *
 *******************************************************************************/
function rd_render_lgpd_banner() {
	if ( ! rd_get_option_bool( 'enable_lgpd' ) ) {
		return;
	}

	// Always render the banner in the DOM (with initial state based on the cookie).
	// When the user already chose, the banner starts hidden via a class — but
	// stays available for the footer "Cookie Preferences" link to reveal
	// without a reload + without losing the current toggle values.
	$consent      = rd_lgpd_get_consent();
	$hidden_class = $consent['choice_made'] ? ' rd-lgpd-hidden' : '';
	$analytics_on = ! empty( $consent['analytics'] );
	$marketing_on = ! empty( $consent['marketing'] );

	// Privacy Policy link
	$privacy_url  = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';
	$link_label   = __( 'Privacy Policy', 'reloaded' );
	$privacy_link = $privacy_url
		? '<a href="' . esc_url( $privacy_url ) . '">' . esc_html( $link_label ) . '</a>'
		: esc_html( $link_label );

	$custom_text = rd_get_option( 'lgpd_text' );
	$template    = ! empty( $custom_text )
		? $custom_text
		/* translators: %s: link to the Privacy Policy page (rendered as anchor or plain label depending on whether the Privacy Policy URL is configured in WP Settings) */
		: __( 'We use cookies and similar technologies to improve your experience. Read more in our %s.', 'reloaded' );
	$banner_text = sprintf( $template, $privacy_link );
	?>

	<div id="rd-lgpd-banner" class="rd-cookie-banner<?php echo esc_attr( $hidden_class ); ?>" data-state="compact">
		<div class="rd-lgpd-text">
			<?php echo wp_kses_post( $banner_text ); ?>
		</div>

		<div class="rd-lgpd-options" inert>
			<label class="rd-lgpd-option rd-lgpd-option-locked">
				<input type="checkbox" name="rd-lgpd-necessary" checked disabled>
				<span class="rd-lgpd-option-label">
					<strong><?php esc_html_e( 'Necessary', 'reloaded' ); ?></strong>
					<em class="rd-lgpd-required-tag">(<?php esc_html_e( 'always on', 'reloaded' ); ?>)</em>
				</span>
				<small><?php esc_html_e( 'Essential cookies for the site to work (login, theme, your preferences).', 'reloaded' ); ?></small>
			</label>

			<label class="rd-lgpd-option">
				<input type="checkbox" name="rd-lgpd-analytics" id="rd-lgpd-analytics"<?php checked( $analytics_on ); ?>>
				<span class="rd-lgpd-option-label">
					<strong><?php esc_html_e( 'Statistics', 'reloaded' ); ?></strong>
				</span>
				<small><?php esc_html_e( 'Help us understand how you use the site (Google Analytics, Clarity, Plausible).', 'reloaded' ); ?></small>
			</label>

			<label class="rd-lgpd-option">
				<input type="checkbox" name="rd-lgpd-marketing" id="rd-lgpd-marketing"<?php checked( $marketing_on ); ?>>
				<span class="rd-lgpd-option-label">
					<strong><?php esc_html_e( 'Marketing', 'reloaded' ); ?></strong>
				</span>
				<small><?php esc_html_e( 'Used to personalize ads on social media (Facebook Pixel, TikTok Pixel).', 'reloaded' ); ?></small>
			</label>
		</div>

		<div class="rd-lgpd-actions">
			<button type="button" id="rd-lgpd-reject" class="rd-lgpd-btn rd-lgpd-btn-secondary"><?php esc_html_e( 'Reject all', 'reloaded' ); ?></button>
			<button type="button" id="rd-lgpd-customize" class="rd-lgpd-btn rd-lgpd-btn-secondary" data-label-compact="<?php esc_attr_e( 'Customize', 'reloaded' ); ?>" data-label-expanded="<?php esc_attr_e( 'Save preferences', 'reloaded' ); ?>"><?php esc_html_e( 'Customize', 'reloaded' ); ?></button>
			<button type="button" id="rd-lgpd-accept" class="rd-lgpd-btn rd-lgpd-btn-primary"><?php esc_html_e( 'Accept all', 'reloaded' ); ?></button>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'rd_render_lgpd_banner' );
