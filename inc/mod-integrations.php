<?php
defined( 'ABSPATH' ) || exit;
/*******************************************************************************
 * Module: Integrations - Analytics, Discord, etc.
 */

/*******************************************************************************
 * Scripts no Head - (Analytics e Ads)                         - (Integrações) *
 */
add_action(
	'wp_head',
	function () {
		$ga_id = rd_get_option( 'ga_id' );

		// Gated por consent de "analytics" — só carrega se o user consentiu
		if ( ! empty( $ga_id ) && preg_match( '/^(G-|UA-)[A-Z0-9-]+$/i', $ga_id ) && rd_consent_given( 'analytics' ) ) {
			$nonce_attr = rd_csp_nonce_attr();
			?>
		<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- script oficial do gtag/Google Analytics injetado inline no wp_head — wp_enqueue_script não é apropriado aqui (precisa rodar exatamente nessa posição do <head>, antes de outros tracking scripts, com data layer setup correto). ?>
		<script<?php echo $nonce_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce attribute already escaped by rd_csp_nonce_attr() ?> async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $ga_id ); ?>"></script>
		<script<?php echo $nonce_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce attribute already escaped by rd_csp_nonce_attr() ?>>
			window.dataLayer = window.dataLayer || [];
			function gtag(){dataLayer.push(arguments);}
			gtag('js', new Date());
			gtag('config', '<?php echo esc_attr( $ga_id ); ?>');
		</script>
			<?php
		}

		$ad_global = rd_get_option( 'ad_global' );
		if ( ! empty( $ad_global ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- BY DESIGN: global ad/tracking script (AdSense Auto Ads, custom head snippets). Admin with manage_options trusted under WP model. CSP nonce injetado automaticamente em <script> tags via rd_csp_inject_nonce().
			echo rd_csp_inject_nonce( $ad_global ) . "\n";
		}
	}
);

/*******************************************************************************
 * Meta tags de verificação de propriedade do site            - (Integrações) *
 *                                                                             *
 * Cada motor de busca exige uma <meta> específica pra confirmar que você é   *
 * dono do domínio antes de liberar acesso ao painel de Webmaster Tools.      *
 *                                                                             *
 *   - Google: <meta name="google-site-verification" content="...">           *
 *   - Bing:   <meta name="msvalidate.01" content="...">                      *
 *                                                                             *
 * As tags são impressas apenas se o admin tiver preenchido o valor no painel.*
 * Sem opção on/off — vazio = não renderiza, é o controle natural.            *
 *                                                                             *
 * Sanitização: extra leve via esc_attr (códigos são alfanuméricos + alguns   *
 * separadores). Não precisa wp_kses porque a string vai pra atributo HTML.   *
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
		// etc.). Já sanitizado no save via wp_kses com whitelist estrito de
		// <meta> + atributos específicos (panel.php → rd_options_sanitize).
		// Echo direto preservando line breaks pra leitura humana no view-source.
		$custom_meta = trim( (string) rd_get_option( 'custom_verification_meta' ) );
		if ( $custom_meta !== '' ) {
			echo $custom_meta . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized at save via wp_kses with strict <meta>-only allowlist.
		}
	},
	1
); // priority 1 — meta tags de verificação ficam no topo do <head> por convenção

/*******************************************************************************
 * Scripts de Analytics e Tracking de Terceiros               - (Integrações) *
 *                                                                             *
 * Bloco único no wp_head que injeta os scripts oficiais de cada ferramenta   *
 * quando o ID respectivo está preenchido no painel:                          *
 *                                                                             *
 *   - Microsoft Clarity (heatmaps + session recordings gratuitos)            *
 *   - Facebook Pixel (retargeting + conversões Meta)                         *
 *   - TikTok Pixel (retargeting + conversões TikTok Ads)                     *
 *   - Plausible Analytics (cloud, privacy-friendly)                          *
 *   - Umami Analytics (self-hosted, privacy-friendly)                        *
 *                                                                             *
 * Cada script é OFICIAL da plataforma — copiado direto da documentação. ID   *
 * passa por validação regex pra evitar XSS via campo do painel (mesmo sendo  *
 * input só pra admin com manage_options).                                    *
 */
add_action(
	'wp_head',
	function () {

		$nonce_attr = rd_csp_nonce_attr();

		// --- Microsoft Clarity --- (Estatísticas)
		$clarity_id = trim( (string) rd_get_option( 'clarity_id' ) );
		if ( $clarity_id !== '' && preg_match( '/^[a-z0-9]+$/i', $clarity_id ) && rd_consent_given( 'analytics' ) ) :
			?>
		<script type="text/javascript"<?php echo $nonce_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce already escaped ?>>
			(function(c,l,a,r,i,t,y){
				c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
				t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
				y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
			})(window, document, "clarity", "script", "<?php echo esc_js( $clarity_id ); ?>");
		</script>
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

		// --- Plausible Analytics (cloud) --- (Estatísticas)
		$plausible_domain = trim( (string) rd_get_option( 'plausible_domain' ) );
		if ( $plausible_domain !== '' && rd_consent_given( 'analytics' ) ) :
			?>
		<script defer<?php echo $nonce_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- nonce already escaped ?> data-domain="<?php echo esc_attr( $plausible_domain ); ?>" src="https://plausible.io/js/script.js"></script>
			<?php
	endif;

		// --- Umami Analytics (self-hosted) --- (Estatísticas)
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
 * Renderiza o Widget do Discord (Sidebar)                     - (Integrações) *
 *******************************************************************************/
function rd_render_discord_widget() {
	// 1. Verificação Mestre: O widget está ativado no painel?
	$show_discord = rd_get_option_bool( 'discord_widget' );

	// Se estiver desligado, a função morre aqui e não renderiza nada
	if ( ! $show_discord ) {
		return;
	}

	// 2. Define qual ID usar (Painel ou Padrão ReloadeD)
	$id_discord = rd_get_option( 'discord_id' ) ? rd_get_option( 'discord_id' ) : '408089552759029788';

	// 3. Verifica o modo de performance (Fachada ou Iframe)
	$use_facade = rd_get_option_bool( 'facade_discord' );

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
					// Resolução em 3 níveis:
					// 1. discord_facade_logo do painel (admin escolheu imagem dedicada)
					// 2. Custom Logo do WP (rd_get_site_logo)
					// 3. logo-reloaded-panel.webp (fallback hardcoded — dentro do rd_get_site_logo)
					$facade_logo_url = rd_get_option( 'discord_facade_logo' );
					if ( empty( $facade_logo_url ) ) {
						$logo_data       = rd_get_site_logo( 'medium' );
						$facade_logo_url = $logo_data['url'];
					}
					$facade_img_html = sprintf(
						'<img src="%1$s" alt="%2$s" width="430" height="100">',
						esc_url( $facade_logo_url ),
						esc_attr( get_bloginfo( 'name' ) )
					);
					// Envolve em <picture> se houver AVIF/WebP no filesystem.
					// Function check defensivo — mod-image-formats pode estar desabilitado
					// em algum cenário futuro (toggle, código separado). Fallback inerte.
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
}