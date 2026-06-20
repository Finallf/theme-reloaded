<?php
defined( 'ABSPATH' ) || exit;
/*******************************************************************************
 * Module: Integrations - Analytics, Discord, etc.
 */

/*******************************************************************************
 * Scripts in the Head - (Analytics and Ads)                  - (Integrations) *
 */
add_action(
	'wp_head',
	function () {
		$ga_id = rd_get_option( 'ga_id' );

		// Gated by "analytics" consent — only loads if the user consented
		if ( ! empty( $ga_id ) && preg_match( '/^(G-|UA-)[A-Z0-9-]+$/i', $ga_id ) && rd_consent_given( 'analytics' ) ) {
			$nonce_attr = rd_csp_nonce_attr();
			?>
		<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- official gtag/Google Analytics script injected inline in wp_head — wp_enqueue_script isn't appropriate here (must run at exactly this position in the <head>, before other tracking scripts, with correct data layer setup). ?>
		<script<?php echo $nonce_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce attribute already escaped by rd_csp_nonce_attr() ?> async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $ga_id ); ?>"></script>
		<script<?php echo $nonce_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce attribute already escaped by rd_csp_nonce_attr() ?>>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?php echo esc_attr( $ga_id ); ?>');</script>
			<?php
		}

		$ad_global = rd_get_option( 'ad_global' );
		if ( ! empty( $ad_global ) && rd_get_option_bool( 'enable_ads', true ) ) {
			$ad_global_out = rd_csp_inject_nonce( $ad_global );
			// Register this loader with the dedupe BEFORE the body ad slots
			// render: wp_head runs first, so when the global head snippet
			// already carries adsbygoogle.js, every per-slot copy of the
			// loader is stripped and the page ends up with exactly one
			// (see rd_ads_dedupe_loader in mod-ads.php).
			if ( function_exists( 'rd_ads_dedupe_loader' ) ) {
				$ad_global_out = rd_ads_dedupe_loader( $ad_global_out );
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- BY DESIGN: global ad/tracking script (AdSense Auto Ads, custom head snippets). Admin with manage_options trusted under WP model. CSP nonce injected automatically into <script> tags via rd_csp_inject_nonce().
			echo $ad_global_out . "\n";
		}
	}
);

/*******************************************************************************
 * Site ownership verification meta tags                       - (Integrations)*
 *                                                                             *
 * Each search engine requires a specific <meta> to confirm you own            *
 * the domain before granting access to the Webmaster Tools panel.             *
 *                                                                             *
 *   - Google: <meta name="google-site-verification" content="...">            *
 *   - Bing:   <meta name="msvalidate.01" content="...">                       *
 *                                                                             *
 * The tags are printed only if the admin filled in the value in the panel.    *
 * No on/off option — empty = doesn't render, that's the natural control.      *
 *                                                                             *
 * Sanitization: extra-light via esc_attr (codes are alphanumeric + a few      *
 * separators). No wp_kses needed because the string goes into an HTML attr.   *
 */
add_action(
	'wp_head',
	function () {
		$google = trim( (string) rd_get_option( 'google_site_verification' ) );
		if ( $google !== '' ) {
			echo '<meta name="google-site-verification" content="' . esc_attr( $google ) . '" />' . "\n";
		}

		$bing = trim( (string) rd_get_option( 'bing_site_verification' ) );
		if ( $bing !== '' ) {
			echo '<meta name="msvalidate.01" content="' . esc_attr( $bing ) . '" />' . "\n";
		}

		// Custom verification meta tags (Pinterest, Yandex, Naver, Facebook Domain,
		// etc.). Already sanitized on save via wp_kses with a strict whitelist of
		// <meta> + specific attributes (panel.php → rd_options_sanitize).
		// Direct echo preserving line breaks for human reading in view-source.
		$custom_meta = trim( (string) rd_get_option( 'custom_verification_meta' ) );
		if ( $custom_meta !== '' ) {
			echo $custom_meta . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized at save via wp_kses with strict <meta>-only allowlist.
		}
	},
	1
); // priority 1 — verification meta tags go at the top of the <head> by convention

/*******************************************************************************
 * Third-party Analytics and Tracking scripts                  - (Integrations)*
 *                                                                             *
 * Single block in wp_head that injects the official script of each tool       *
 * when the respective ID is filled in the panel:                              *
 *                                                                             *
 *   - Microsoft Clarity (free heatmaps + session recordings)                  *
 *   - Facebook Pixel (retargeting + Meta conversions)                         *
 *   - TikTok Pixel (retargeting + TikTok Ads conversions)                     *
 *   - Plausible Analytics (cloud, privacy-friendly)                           *
 *   - Umami Analytics (self-hosted, privacy-friendly)                         *
 *                                                                             *
 * Each script is the platform's OFFICIAL one — copied straight from the docs. *
 * The ID goes through regex validation to prevent XSS via the panel field     *
 * (even though it's input only for an admin with manage_options).             *
 */
add_action(
	'wp_head',
	function () {

		$nonce_attr = rd_csp_nonce_attr();

		// --- Microsoft Clarity --- (Statistics)
		$clarity_id = trim( (string) rd_get_option( 'clarity_id' ) );
		if ( $clarity_id !== '' && preg_match( '/^[a-z0-9]+$/i', $clarity_id ) && rd_consent_given( 'analytics' ) ) :
			?>
		<script type="text/javascript"<?php echo $nonce_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce already escaped ?>>(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,"clarity","script","<?php echo esc_js( $clarity_id ); ?>");</script>
			<?php
	endif;

		// --- Facebook Pixel --- (Marketing)
		$fb_pixel = trim( (string) rd_get_option( 'facebook_pixel_id' ) );
		if ( $fb_pixel !== '' && preg_match( '/^\d+$/', $fb_pixel ) && rd_consent_given( 'marketing' ) ) :
			?>
		<script<?php echo $nonce_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce already escaped ?>>
			!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window, document,'script','https://connect.facebook.net/en_US/fbevents.js');
			fbq('init', '<?php echo esc_js( $fb_pixel ); ?>');
			fbq('track', 'PageView');
		</script>
		<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?php echo esc_attr( $fb_pixel ); ?>&ev=PageView&noscript=1" alt=""/></noscript>
			<?php
	endif;

		// --- TikTok Pixel --- (Marketing)
		$tt_pixel = trim( (string) rd_get_option( 'tiktok_pixel_id' ) );
		if ( $tt_pixel !== '' && preg_match( '/^[A-Z0-9]+$/i', $tt_pixel ) && rd_consent_given( 'marketing' ) ) :
			?>
		<script<?php echo $nonce_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce already escaped ?>>
			!function (w, d, t) {w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e};ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};
			ttq.load('<?php echo esc_js( $tt_pixel ); ?>');
			ttq.page();
			}(window, document, 'ttq');
		</script>
			<?php
	endif;

		// --- Plausible Analytics (cloud) --- (Statistics)
		$plausible_domain = trim( (string) rd_get_option( 'plausible_domain' ) );
		if ( $plausible_domain !== '' && rd_consent_given( 'analytics' ) ) :
			?>
		<script defer<?php echo $nonce_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce already escaped ?> data-domain="<?php echo esc_attr( $plausible_domain ); ?>" src="https://plausible.io/js/script.js"></script>
			<?php
	endif;

		// --- Umami Analytics (self-hosted) --- (Statistics)
		$umami_id  = trim( (string) rd_get_option( 'umami_website_id' ) );
		$umami_url = trim( (string) rd_get_option( 'umami_script_url' ) );
		if ( $umami_id !== '' && $umami_url !== '' && filter_var( $umami_url, FILTER_VALIDATE_URL ) && rd_consent_given( 'analytics' ) ) :
			?>
		<script async defer<?php echo $nonce_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce already escaped ?> data-website-id="<?php echo esc_attr( $umami_id ); ?>" src="<?php echo esc_url( $umami_url ); ?>"></script>
			<?php
	endif;
	},
	5
);

/*******************************************************************************
 * Renders the Discord Widget (Sidebar)                        - (Integrations)*
 *                                                                             *
 * Builds and RETURNS the Discord block HTML from a data array. Config now     *
 * lives in RD_Discord_Widget (Appearance → Widgets), not the theme panel.     *
 * Keys: discord_id (string), facade (bool-ish), facade_logo (URL).            *
 *******************************************************************************/
function rd_get_discord_widget_html( array $data ): string {
	// Server ID — falls back to the ReloadeD default when not provided.
	$id_discord = ! empty( $data['discord_id'] ) ? $data['discord_id'] : '408089552759029788';

	// Facade (lazy-load) defaults ON when the instance hasn't set it yet.
	$use_facade = ! isset( $data['facade'] ) ? true : ! empty( $data['facade'] );

	ob_start();
	if ( $use_facade ) :
		?>
		<div class="rd-facade discord-style" data-type="discord" data-id="<?php echo esc_attr( $id_discord ); ?>">
			<div class="discord-placeholder">
				<div class="logo-ext">
					<?php
					$svg_path = get_template_directory() . '/assets/img/discord.svg';
					if ( file_exists( $svg_path ) ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped,WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- hardcoded SVG asset shipped with the theme (path under get_template_directory())
						echo file_get_contents( $svg_path ); }
					?>
				</div>
				<div class="logo-int">
					<?php
					// 3-level resolution:
					// 1. facade_logo from the widget (admin chose a dedicated image)
					// 2. WP's Custom Logo (rd_get_site_logo)
					// 3. reloaded-logo-200-55.webp (hardcoded fallback — inside rd_get_site_logo)
					$facade_logo_url = $data['facade_logo'] ?? '';
					if ( empty( $facade_logo_url ) ) {
						$logo_data       = rd_get_site_logo( 'medium' );
						$facade_logo_url = $logo_data['url'];
					}
					$facade_img_html = sprintf(
						'<img src="%1$s" alt="%2$s" width="430" height="100">',
						esc_url( $facade_logo_url ),
						esc_attr( get_bloginfo( 'name' ) )
					);
					// Wrap in <picture> if there's AVIF/WebP on the filesystem.
					// Defensive function check — mod-image-formats may be disabled
					// in some future scenario (toggle, separate code). Inert fallback.
					if ( function_exists( 'rd_img_wrap_url_in_picture' ) ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper builds <picture> from already-escaped components ($facade_img_html composed with esc_url + esc_attr above; helper appends <source> tags with esc_url internally).
						echo rd_img_wrap_url_in_picture( $facade_logo_url, $facade_img_html );
					} else {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $facade_img_html composed with esc_url + esc_attr via sprintf above.
						echo $facade_img_html;
					}
					?>
				</div>
			</div>
		</div>
	<?php else : ?>
		<iframe src="https://ptb.discord.com/widget?id=<?php echo esc_attr( $id_discord ); ?>&theme=dark" width="100%" height="500" allowtransparency="true" frameborder="0" loading="lazy"></iframe>
		<?php
	endif;
	return (string) ob_get_clean();
}