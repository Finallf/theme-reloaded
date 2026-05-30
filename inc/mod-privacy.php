<?php
defined( 'ABSPATH' ) || exit;
/*******************************************************************************
 * Módulo Privacidade - Consentimento e LGPD                                    *
 *                                                                              *
 * Sistema de consent granular por categoria (LGPD/GDPR-compliant):             *
 *                                                                              *
 *   - Necessários (sempre on, obrigatórios pro site funcionar)                 *
 *   - Estatísticas (Google Analytics, Clarity, Plausible/Umami)                *
 *   - Marketing (Facebook Pixel, TikTok Pixel)                                 *
 *                                                                              *
 * Storage: cookie único `rd_lgpd_consent` em JSON, validade 365 dias.          *
 * Estado consultado via `rd_consent_given('analytics'|'marketing'|'necessary')` *
 * — todos os scripts de tracking checam essa função antes de injetar.          *
 *                                                                              *
 * Quando `enable_lgpd` está OFF, sistema fica adormecido: nenhum banner,       *
 * nenhum link no footer, e `rd_consent_given()` retorna true sempre (admin     *
 * assumiu compliance por fora — sites internos, dev, etc).                     *
 *******************************************************************************/

/*******************************************************************************
 * Constantes do módulo                                                         *
 *******************************************************************************/
const RD_LGPD_COOKIE_NAME    = 'rd_lgpd_consent';
const RD_LGPD_LEGACY_COOKIE  = 'rd_lgpd_accepted';   // antigo, pra migração
const RD_LGPD_COOKIE_VERSION = 1;                    // bump invalida cookies antigos
const RD_LGPD_CATEGORIES     = array( 'necessary', 'analytics', 'marketing' );

/*******************************************************************************
 * Lê o estado do consent do cookie (com migração do cookie legado)             *
 *                                                                              *
 * Retorna sempre 4 chaves:                                                     *
 *   - necessary (bool — sempre true)                                           *
 *   - analytics (bool)                                                         *
 *   - marketing (bool)                                                         *
 *   - choice_made (bool — true se user já interagiu com o banner)              *
 *                                                                              *
 * Cache estático na request — leitura do cookie só uma vez.                    *
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

	// 1. Cookie novo (formato JSON v1+)
	if ( isset( $_COOKIE[ RD_LGPD_COOKIE_NAME ] ) ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON content; sanitize_text_field destruiria estrutura. Validação acontece via json_decode + checks `is_array`/`isset(['version'])` abaixo. wp_unslash é o equivalente WP-idiomático de stripslashes.
		$raw  = wp_unslash( $_COOKIE[ RD_LGPD_COOKIE_NAME ] );
		$data = json_decode( $raw, true );
		if ( is_array( $data ) && isset( $data['version'] ) && (int) $data['version'] === RD_LGPD_COOKIE_VERSION ) {
			$cached = array(
				'necessary'   => true, // sempre on, independe do cookie
				'analytics'   => ! empty( $data['analytics'] ),
				'marketing'   => ! empty( $data['marketing'] ),
				'choice_made' => true,
			);
			return $cached;
		}
	}

	// 2. Cookie legado (`rd_lgpd_accepted=true`) — migração in-memory
	// O cookie legado significava "aceitou tudo" no banner antigo (1 botão só).
	// Tratamos como tudo aceito pra não re-prompt usuário existente. A limpeza
	// do cookie legado é feita pelo JS (não dá pra setcookie aqui — headers
	// podem já ter sido enviados).
	if ( isset( $_COOKIE[ RD_LGPD_LEGACY_COOKIE ] ) ) {
		$cached = array(
			'necessary'   => true,
			'analytics'   => true,
			'marketing'   => true,
			'choice_made' => true,
		);
		return $cached;
	}

	// 3. Sem cookie — escolha pendente
	$cached = $default;
	return $cached;
}

/*******************************************************************************
 * Helper público: verifica se o user consentiu com uma categoria              *
 *                                                                              *
 * Todos os scripts de tracking devem chamar essa função antes de injetar:     *
 *   if ( ! rd_consent_given('analytics') ) return;                            *
 *                                                                              *
 * Quando banner desligado no painel, retorna true sempre (sistema dormante).  *
 *******************************************************************************/
function rd_consent_given( string $category ): bool {
	// Banner desligado pelo admin → sistema adormecido, tudo passa
	if ( ! rd_get_option_bool( 'enable_lgpd' ) ) {
		return true;
	}

	// Necessários sempre on
	if ( $category === 'necessary' ) {
		return true;
	}

	// Categoria desconhecida → false defensivo
	if ( ! in_array( $category, RD_LGPD_CATEGORIES, true ) ) {
		return false;
	}

	$consent = rd_lgpd_get_consent();
	return ! empty( $consent[ $category ] );
}

/*******************************************************************************
 * Renderiza o banner de consent LGPD                          - (Privacidade) *
 *                                                                              *
 * Markup tem 2 modos visuais controlados via CSS/JS:                          *
 *   - Compacto (default): texto + 3 botões                                    *
 *   - Expandido: + 3 toggles de categoria (mostrado ao clicar "Personalizar") *
 *                                                                              *
 * Estado é serializado em data-attributes pra o JS consumir. CSS faz a       *
 * transição via max-height + opacity.                                          *
 *******************************************************************************/
function rd_render_lgpd_banner() {
	if ( ! rd_get_option_bool( 'enable_lgpd' ) ) {
		return;
	}

	// Sempre renderiza o banner no DOM (com estado inicial baseado no cookie).
	// Quando o user já escolheu, o banner começa escondido via classe — mas
	// continua disponível pro link "Cookie Preferences" do footer revelar
	// sem precisar de reload + sem perder os valores atuais dos toggles.
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
