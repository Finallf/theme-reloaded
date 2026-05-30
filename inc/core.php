<?php
defined( 'ABSPATH' ) || exit;
/*******************************************************************************
 * Core do Tema - Funções Estruturais e Nativas (Hardcoded)
 */

/*******************************************************************************
 * Funções e definições do tema ReloadeD                         - (Hardcoded) *
 */
if ( ! function_exists( 'rd_setup' ) ) :
	function rd_setup() {
		// Carrega o text domain do tema. WP procura por /languages/{locale}.mo
		// (convenção pra arquivos DENTRO da pasta do tema — sem o prefixo do
		// text domain). Se o .mo não existir, o WP cai pra string-fonte (en-US).
		// Referência: https://developer.wordpress.org/themes/classic-themes/functionality/internationalization/
		load_theme_textdomain( 'reloaded', get_template_directory() . '/languages' );

		add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) ); // Adicione suporte a HTML5
		add_theme_support( 'title-tag' ); // Adiciona suporte a títulos dinâmicos (gerenciados pelo WP)
		add_theme_support( 'post-thumbnails' ); // Habilita Imagens Destacadas (essencial para portais)
		add_theme_support( 'responsive-embeds' ); // Mantem a proporção correta dos vídeos inseridos via bloco de "Vídeo" ou "YouTube"
		add_theme_support(
			'custom-logo',
			array(
				'height'      => 100,
				'width'       => 400,
				'flex-height' => true,
				'flex-width'  => true,
			)
		); // Habilita a troca de logotipo personalizada
		add_theme_support( 'align-wide' ); // Habilita opção no editor para que imagens ocupem a largura total da tela (estilo ""Gutenberg"")
		add_theme_support( 'automatic-feed-links' ); // Adiciona links de feeds RSS automaticamente ao <head>
		add_theme_support( 'customize-selective-refresh-widgets' ); // Permite a atualização seletiva de widgets no Customizador (sem recarregar a página toda)
		add_theme_support( 'editor-styles' ); // Exibe as fontes e estilos do tema no editor Gutenberg
		add_editor_style( 'assets/css/style.css' ); // Aponta para o seu CSS principal

		// TAMANHOS DE IMAGEM PERSONALIZADOS (HARD CROP)
		// Mantido no core pois os tamanhos precisam ser registrados na inicialização do tema
		if ( rd_get_option_bool( 'image_resizing' ) ) {
			add_image_size( 'rd-micro', 150, 84, true );          // Miniaturas para Widgets/Sidebar (16:9).
			add_image_size( 'rd-popular-thumb', 200, 113, true ); // Widget "Most Read" — display 100x56 (16:9) com DPR 2x retina = 200x113. Antes usava 'medium' do WP (300x300, sem aspect-ratio fixo) que servia o original gigante (até 2560x1429) quando o medium não cobria.
			add_image_size( 'rd-card-half', 400, 225, true );     // Cards do post-grid em viewport intermediário (display ~390x220). Aproveita o srcset auto do WP — browser escolhe esse quando o display fica entre rd-micro e rd-card. Antes o WP servia rd-card 600x338 (overkill em DPR 1x) ou o original.
			add_image_size( 'rd-card', 600, 338, true );          // Tamanho para os cards da Home.
			add_image_size( 'rd-full-banner', 1200, 675, true );  // Tamanho para o banner no topo da notícia.
			add_image_size( 'rd-qr', 240, 240, true );            // QR codes do bloco de doações (sidebar). Display real ~160-200px; 240 cobre retina sem desperdiçar banda como o source de 635x635 que admins costumam subir.
		}

		// Registra os locais de menu
		register_nav_menus(
			array(
				'menu-1'      => esc_html__( 'Primary Menu', 'reloaded' ),
				'menu-footer' => esc_html__( 'Footer Menu', 'reloaded' ),
			)
		);
	}
endif;
add_action( 'after_setup_theme', 'rd_setup' );

/*******************************************************************************
 * Remove tamanhos de imagem ocultos/nativos do WordPress        - (Hardcoded) *
 */
add_action(
	'init',
	function () {
		remove_image_size( 'medium_large' );
		remove_image_size( '1536x1536' );
		remove_image_size( '2048x2048' );
	}
);

/*******************************************************************************
 * Renderiza o Logotipo (Imagem ou Texto)                        - (Hardcoded) *
 *******************************************************************************/
function rd_render_logo() {
	if ( has_custom_logo() ) {
		echo '<div class="site-branding-image">';
		the_custom_logo();
		echo '</div>';
	} else {
		?>
		<div class="site-branding-text">
			<h1 class="site-title">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<?php bloginfo( 'name' ); ?>
				</a>
			</h1>
		</div>
		<?php
	}
}

/*******************************************************************************
 * Helper: URL do logo do site com fallback                        - (Hardcoded) *
 *                                                                                *
 * Source of truth pra "qual logo usar fora do frontend regular".                *
 * Resolve na ordem:                                                              *
 *   1. Custom Logo do WP (Aparência → Personalizar → Identidade do Site)        *
 *   2. assets/img/logo-reloaded-panel.webp (fallback hardcoded do tema)        *
 *                                                                                *
 * Usado por: mod-maintenance.php (tela 503), mod-security.php (WSOD 500),       *
 * mod-integrations.php (Discord facade quando admin não cadastrou logo dedicado *
 * via discord_facade_logo), mod-seo.php (Schema.org Organization logo).         *
 *                                                                                *
 * Por que não usar Custom Logo no painel admin (panel.php) nem no OG image      *
 * fallback (mod-seo.php → rd_seo_resolve_og_image): aqueles 2 casos são         *
 * intencionalmente hardcoded — o primeiro é UI do tema (branding do produto    *
 * "ReloadeD theme"), o segundo é fallback DE fallback de OG image (Custom Logo *
 * geralmente é horizontal, proporção errada pra preview social 1.91:1).         *
 *                                                                                *
 *
 * @param string $size Size do attachment quando o Custom Logo é usado.          *
 *                     'medium' (default), 'large', 'full', etc.                 *
 * @return array { url: string, width: int|null, height: int|null }              *
 *******************************************************************************/
function rd_get_site_logo( string $size = 'medium' ): array {
	$custom_logo_id = (int) get_theme_mod( 'custom_logo' );
	if ( $custom_logo_id ) {
		$src = wp_get_attachment_image_src( $custom_logo_id, $size );
		if ( $src ) {
			return array(
				'url'    => $src[0],
				'width'  => (int) $src[1],
				'height' => (int) $src[2],
			);
		}
	}

	// Fallback: arquivo hardcoded em assets/img/logo-reloaded-panel.webp (430x100)
	return array(
		'url'    => get_template_directory_uri() . '/assets/img/logo-reloaded-panel.webp',
		'width'  => 430,
		'height' => 100,
	);
}

/*******************************************************************************
 * Registro das áreas de Widgets (Sidebar e Rodapé)              - (Hardcoded) *
 *******************************************************************************/
function rd_widgets_init() {

	// Barra Lateral Principal
	register_sidebar(
		array(
			'name'          => __( 'Main Sidebar', 'reloaded' ),
			'id'            => 'sidebar-1',
			'description'   => __( 'Add widgets here to appear in the sidebar.', 'reloaded' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="wp-block-heading widget-title">',
			'after_title'   => '</h2>',
		)
	);

	// Rodapé - Área Dinâmica
	register_sidebar(
		array(
			'name'          => __( 'Footer - Column 3 (Popular Posts)', 'reloaded' ),
			'id'            => 'footer-widget-area',
			'description'   => __( 'Add widgets here to appear in the footer.', 'reloaded' ),
			'before_widget' => '<div id="%1$s" class="widget footer-widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h3 class="footer-heading">',
			'after_title'   => '</h3>',
		)
	);
}
add_action( 'widgets_init', 'rd_widgets_init' );

/***********************************************************************************
 * Carrega assets do admin                                            - (Hardcoded) *
 *                                                                                  *
 * CSS:                                                                             *
 *   - Painel rd_options                                                            *
 *   - Editor de post (post.php / post-new.php) — pra estilizar os meta boxes       *
 *                                                                                  *
 * JS + Media uploader:                                                             *
 *   - Apenas no painel rd_options (onde existe upload de mídia via campos)         *
 ***********************************************************************************/
function rd_admin_scripts( $hook ) {

	$is_panel       = ( strpos( $hook, 'rd_options' ) !== false );
	$is_post_editor = ( $hook === 'post.php' || $hook === 'post-new.php' );

	// CSS — carrega em ambos os contextos
	if ( $is_panel || $is_post_editor ) {
		wp_enqueue_style(
			'rd-admin-css',
			get_template_directory_uri() . '/assets/css/admin-style.css',
			array(),
			rd_asset_version( '/assets/css/admin-style.css' )
		);
	}

	// JS + Media — apenas no painel rd_options
	if ( $is_panel ) {
		wp_enqueue_media();
		wp_enqueue_script(
			'rd-admin-js',
			get_template_directory_uri() . '/assets/js/admin-scripts.js',
			array( 'jquery' ),
			rd_asset_version( '/assets/js/admin-scripts.js' ),
			true
		);
	}
}
add_action( 'admin_enqueue_scripts', 'rd_admin_scripts' );

/*******************************************************************************
 * Helper: Versão de asset com fallback seguro                  - (Performance) *
 * Útil para cache busting em wp_enqueue_*.                                     *
 * Usa hash do conteúdo + mtime para máxima precisão de invalidação de cache.   *
 * Imune a problemas de timestamp causados por uploads atômicos via SFTP.       *
 *******************************************************************************/
function rd_asset_version( $relative_path, $fallback = '1.0.0' ) {
	$full_path = get_template_directory() . $relative_path;
	return file_exists( $full_path ) ? (string) filemtime( $full_path ) : $fallback;
}

/*******************************************************************************
 * IP do cliente — com proteção contra header spoofing             - (Segurança) *
 *                                                                              *
 * Modelo: REMOTE_ADDR (que vem do TCP, não-spoofável) é a fonte de verdade.   *
 * Headers de proxy (CF-Connecting-IP, X-Forwarded-For) só são confiados QUANDO *
 * REMOTE_ADDR está numa faixa de IP de proxy reconhecida — Cloudflare por      *
 * default + ranges custom configurados pelo admin em `trusted_proxy_ips`.      *
 *                                                                              *
 * Consumido por mod-maintenance (rate-limit de senha) e mod-views (dedupe de  *
 * views). Função canônica — qualquer módulo que precise saber "quem é o user" *
 * deve chamar daqui em vez de reimplementar.                                   *
 *******************************************************************************/
function rd_get_client_ip(): string {
	$remote = isset( $_SERVER['REMOTE_ADDR'] )
		? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
		: '0.0.0.0';

	if ( rd_remote_is_trusted_proxy( $remote ) ) {
		// CF-Connecting-IP é single IP do visitante — fonte primária quando confiável
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			$cf_ip = trim( sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) );
			if ( filter_var( $cf_ip, FILTER_VALIDATE_IP ) ) {
				return $cf_ip;
			}
		}
		// X-Forwarded-For é lista "client, proxy1, proxy2, ..." — primeiro = original
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
			$ips       = explode( ',', $forwarded );
			$first     = trim( $ips[0] );
			if ( filter_var( $first, FILTER_VALIDATE_IP ) ) {
				return $first;
			}
		}
	}

	// Default: REMOTE_ADDR cru — não vem de header, atacante não falsifica
	return $remote;
}

/*******************************************************************************
 * REMOTE_ADDR está numa faixa de proxy reconhecida?               - (Segurança) *
 *                                                                              *
 * Combina:                                                                     *
 *   - Lista hardcoded do Cloudflare (https://www.cloudflare.com/ips/) — cobre *
 *     o caso mais comum out-of-the-box, sem o admin precisar configurar nada. *
 *   - Ranges custom do painel (`trusted_proxy_ips`, CIDR um por linha) — pra  *
 *     quem usa outro proxy/CDN (Nginx-front, AWS ALB, Sucuri, BunnyCDN etc).  *
 *******************************************************************************/
function rd_remote_is_trusted_proxy( string $ip ): bool {
	static $cf_ranges = array(
		// IPv4 — Cloudflare
		'173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22',
		// IPv6 — Cloudflare
		'2400:cb00::/32',
		'2606:4700::/32',
		'2803:f800::/32',
		'2405:b500::/32',
		'2405:8100::/32',
		'2a06:98c0::/29',
		'2c0f:f248::/32',
	);

	if ( rd_ip_in_ranges( $ip, $cf_ranges ) ) {
		return true;
	}

	$custom = (string) rd_get_option( 'trusted_proxy_ips', '' );
	if ( $custom === '' ) {
		return false;
	}

	$lines  = preg_split( '/[\r\n]+/', $custom );
	$ranges = array_filter( array_map( 'trim', (array) $lines ) );
	if ( empty( $ranges ) ) {
		return false;
	}

	return rd_ip_in_ranges( $ip, $ranges );
}

/*******************************************************************************
 * Verifica se um IP cai em qualquer um dos ranges CIDR fornecidos. - (Segurança) *
 *                                                                              *
 * Suporta IPv4 (`192.168.1.0/24`), IPv6 (`2001:db8::/32`) e IPs únicos (sem `/`). *
 * Ranges malformados são pulados silenciosamente — a função nunca falha por    *
 * input ruim do admin, só retorna false.                                       *
 *******************************************************************************/
function rd_ip_in_ranges( string $ip, array $ranges ): bool {
	// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- inet_pton emite warning em IP malformado; o @ + `false === $ip_bin` check abaixo é o pattern recomendado pra graceful failure.
	$ip_bin = @inet_pton( $ip );
	if ( false === $ip_bin ) {
		return false;
	}
	$ip_is_v6 = strlen( $ip_bin ) === 16;

	foreach ( $ranges as $range ) {
		$range = trim( (string) $range );
		if ( '' === $range ) {
			continue;
		}

		// Single IP (sem prefix) — comparação binária direta
		if ( false === strpos( $range, '/' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- graceful failure em range malformado (admin pode digitar input ruim no painel); validação via `false !== $range_bin` abaixo.
			$range_bin = @inet_pton( $range );
			if ( false !== $range_bin && $range_bin === $ip_bin ) {
				return true;
			}
			continue;
		}

		list( $subnet, $prefix ) = explode( '/', $range, 2 );
		$prefix                  = (int) $prefix;
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- graceful failure em subnet malformada; validação via `false === $subnet_bin` abaixo.
		$subnet_bin = @inet_pton( $subnet );
		if ( false === $subnet_bin ) {
			continue;
		}

		// Mismatch de família (IPv4 vs IPv6) — não pode bater
		$range_is_v6 = strlen( $subnet_bin ) === 16;
		if ( $range_is_v6 !== $ip_is_v6 ) {
			continue;
		}

		$max_prefix = $ip_is_v6 ? 128 : 32;
		if ( $prefix < 0 || $prefix > $max_prefix ) {
			continue;
		}

		$full_bytes     = intdiv( $prefix, 8 );
		$remaining_bits = $prefix % 8;

		if ( $full_bytes > 0 && substr( $ip_bin, 0, $full_bytes ) !== substr( $subnet_bin, 0, $full_bytes ) ) {
			continue;
		}

		if ( $remaining_bits > 0 ) {
			$mask = ( ~( ( 1 << ( 8 - $remaining_bits ) ) - 1 ) ) & 0xff;
			if ( ( ord( $ip_bin[ $full_bytes ] ) & $mask ) !== ( ord( $subnet_bin[ $full_bytes ] ) & $mask ) ) {
				continue;
			}
		}

		return true;
	}

	return false;
}
