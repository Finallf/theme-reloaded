<?php
/**
 * Panel UI Component Helpers — Wave 11 Fase C.
 *
 * @package ReloadeD
 */

defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Panel UI Component Helpers — Wave 11 Fase C                          *
 *                                                                              *
 * Sistema de componentes pra UI do painel admin. Substitui markup ad-hoc das  *
 * 3 sections custom-rendered (Stats, CSP Reports, Backup) por helpers DRY.    *
 *                                                                              *
 * Namespace CSS: `.rd-p*` (rd-pdash, rd-pcard, rd-pbadge, etc.)               *
 * Definições visuais: `assets/css/admin-style.css` (componentes "rd-p"        *
 * separados em bloco próprio no topo do arquivo).                              *
 *                                                                              *
 * Uso típico (em callback de section custom):                                  *
 *                                                                              *
 *   function meu_callback_de_section() {                                       *
 *       rd_panel_dash_open();                                                  *
 *       rd_panel_dash_header( array(                                           *
 *           'info'   => __( 'Status: ', 'reloaded' ) .                         *
 *                       rd_panel_badge( 'success', 'ON' ),                     *
 *           'action' => '<a href="..." class="button">Action</a>',             *
 *       ) );                                                                   *
 *       rd_panel_card_open( array( 'title' => __( 'My Card', 'reloaded' ) ) ); *
 *       echo '<p>Conteúdo aqui...</p>';                                        *
 *       rd_panel_card_close();                                                 *
 *       rd_panel_dash_close();                                                 *
 *   }                                                                          *
 *                                                                              *
 * Status (2026-05-23):                                                         *
 * - Componentes criados e disponíveis pra uso em código novo.                 *
 * - As 3 sections custom-rendered atuais (Stats, CSP Reports, Backup)         *
 *   continuam usando suas classes legacy (`.rd-stats-*`, `.rd-csp-*`,         *
 *   `.rd-backup-*`). Refator pra usar os componentes novos vem na Fase E.    *
 *******************************************************************************/

/**
 * Abre o wrapper de um "dashboard" — container típico de section custom-rendered.
 *
 * Mais que um div, é o marker semântico de "esta section usa o design system
 * novo do painel". Combina bem com rd_panel_dash_header() + rd_panel_card_open().
 *
 * @param array $args Argumentos opcionais.
 *
 *     @type string $class CSS class extra opcional (concatenada com `rd-pdash`).
 */
function rd_panel_dash_open( array $args = array() ): void {
	$extra_class = isset( $args['class'] ) ? ' ' . sanitize_html_class( $args['class'] ) : '';
	echo '<div class="rd-pdash' . esc_attr( $extra_class ) . '">';
}

/**
 * Fecha o wrapper aberto por rd_panel_dash_open().
 */
function rd_panel_dash_close(): void {
	echo '</div>';
}

/**
 * Renderiza o header cinza do dashboard — info à esquerda + ação à direita.
 *
 * Pattern usado hoje em Stats Dashboard e CSP Reports: fundo cinza claro,
 * texto descritivo do estado à esquerda, botão de ação à direita (opcional).
 *
 * @param array $args Argumentos do header.
 *
 *     @type string $info   HTML do lado esquerdo (info textual). PRÉ-ESCAPADO pelo caller.
 *     @type string $action HTML do lado direito (botão/link), opcional. PRÉ-ESCAPADO pelo caller.
 */
function rd_panel_dash_header( array $args ): void {
	$info   = $args['info'] ?? '';
	$action = $args['action'] ?? '';

	echo '<div class="rd-pdash__header">';
	if ( '' !== $info ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller é responsável por escapar; helpers internos (rd_panel_badge) já retornam HTML escapado.
		echo '<span class="rd-pdash__header-info">' . $info . '</span>';
	}
	if ( '' !== $action ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller responsável (geralmente um <a class="button"> com URLs já passados por esc_url).
		echo '<span class="rd-pdash__header-action">' . $action . '</span>';
	}
	echo '</div>';
}

/**
 * Abre um card branco — container principal de conteúdo dentro do dashboard.
 *
 * Aceita title/desc/hint opcionais. Quando nenhum desses é passado, o card
 * fica "limpo" — útil pra wrapping de tabelas (CSP Reports) ou listas (Top
 * Posts do Stats).
 *
 * Modifiers visuais via `variant`:
 *   - `default`     — card branco normal (padrão)
 *   - `placeholder` — fundo cinza claro + border tracejada (feature pendente)
 *   - `warning`     — left-border amber (ação reversível mas perigosa, ex: Restore)
 *   - `danger`      — left-border vermelha (ação destrutiva)
 *
 * @param array $args Argumentos do card.
 *
 *     @type string $title   Título uppercase do card, opcional.
 *     @type string $desc    Descrição abaixo do título, opcional (aceita HTML).
 *     @type string $hint    Hint italic discreto no rodapé, opcional (aceita HTML).
 *     @type string $variant Um de: default, placeholder, warning, danger.
 *     @type string $class   CSS class extra opcional.
 */
function rd_panel_card_open( array $args = array() ): void {
	$title   = $args['title'] ?? '';
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
		echo '<h3 class="rd-pcard__title">' . esc_html( $title ) . '</h3>';
	}
	if ( '' !== $desc ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller passa HTML controlado (texto + <code>/<strong>); usar wp_kses_post seria overhead pra uso interno do painel.
		echo '<p class="rd-pcard__desc">' . $desc . '</p>';
	}
	echo '<div class="rd-pcard__body">';

	// Se passou hint, fecha o body e injeta o hint depois do close — mas como
	// o caller vai escrever conteúdo entre open/close, o hint precisa entrar
	// no close. Stash no static pra rd_panel_card_close() pegar.
	if ( '' !== $hint ) {
		$GLOBALS['_rd_panel_card_hint'] = $hint;
	}
}

/**
 * Fecha um card aberto por rd_panel_card_open().
 *
 * Se rd_panel_card_open() recebeu um `hint`, ele é renderizado aqui (depois
 * do conteúdo do card, antes do fechamento). Usa $GLOBALS pra carregar o hint
 * entre as duas chamadas — feio mas pragmático pra evitar buffers ou closures.
 */
function rd_panel_card_close(): void {
	echo '</div>'; // .rd-pcard__body

	if ( ! empty( $GLOBALS['_rd_panel_card_hint'] ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hint é HTML controlado (caller já cuidou do escape).
		echo '<p class="rd-pcard__hint">' . $GLOBALS['_rd_panel_card_hint'] . '</p>';
		unset( $GLOBALS['_rd_panel_card_hint'] );
	}

	echo '</div>'; // .rd-pcard
}

/**
 * Retorna HTML de um badge de status (chip colorido inline).
 *
 * Diferente dos outros helpers, esse RETORNA string em vez de echo —
 * pra que possa ser concatenado em textos (ex: "Status: [BADGE]").
 *
 * Variantes:
 *   - `info`    — azul WP (#2271b1) — informacional / Report-Only
 *   - `success` — verde (#007a39)  — ON / OK / Enabled
 *   - `warning` — amber (#d29922)  — Atenção / Pending
 *   - `danger`  — vermelho (#d63638) — Enforce / Erro / Disabled
 *   - `neutral` — cinza (#757575)  — Inativo / Padrão
 *
 * @param string $variant Um de: info, success, warning, danger, neutral.
 * @param string $text    Texto do badge (será uppercased via CSS).
 * @return string HTML escapado pronto pra echo.
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
 * Renderiza um banner de status (faixa colorida com left-border).
 *
 * Maior que o badge — usado pra mensagens de feedback após ações
 * (sucesso de import, erro de upload, info sobre operação em andamento).
 *
 * Variantes seguem as do badge: info, success, warning, danger.
 *
 * @param string $variant Um de: info, success, warning, danger.
 * @param string $text    Mensagem (aceita HTML — caller responsável pelo escape).
 */
function rd_panel_status( string $variant, string $text ): void {
	$allowed = array( 'info', 'success', 'warning', 'danger' );
	if ( ! in_array( $variant, $allowed, true ) ) {
		$variant = 'info';
	}
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller é responsável pelo escape de $text (geralmente texto + <code>/<strong>).
	echo '<div class="rd-pstatus rd-pstatus--' . esc_attr( $variant ) . '">' . $text . '</div>';
}

/**
 * Renderiza um empty state padronizado (texto italic centralizado).
 *
 * Usado quando uma section custom não tem nada pra mostrar — feature OFF,
 * sem dados ainda, etc. Substitui `.rd-csp-empty`, `.rd-backup-empty`, etc.
 *
 * @param string $message Texto a mostrar (será passado por esc_html()).
 */
function rd_panel_empty( string $message ): void {
	echo '<p class="rd-pempty">' . esc_html( $message ) . '</p>';
}

/**
 * Renderiza um header de section — usado em callbacks de add_settings_section().
 *
 * Hoje a maioria das sections usa `__return_false` como callback (sem header).
 * Esse helper dá um header consistente: ícone dashicons opcional + título
 * + descrição opcional. Permite que sections "comuns" (Settings API) ganhem
 * o mesmo look das custom-rendered.
 *
 * @param array $args Argumentos do header.
 *
 *     @type string $icon  Slug do dashicon (ex: 'admin-appearance') ou '' pra sem ícone.
 *     @type string $title Título da section.
 *     @type string $desc  Descrição (HTML permitido — caller cuida do escape).
 */
function rd_panel_section_header( array $args ): void {
	$icon  = $args['icon'] ?? '';
	$title = $args['title'] ?? '';
	$desc  = $args['desc'] ?? '';
	$id    = $args['id'] ?? ''; // opcional — vira id na div pra hash anchors.

	if ( '' === $title && '' === $desc ) {
		return;
	}

	$id_attr = '' !== $id ? ' id="' . esc_attr( $id ) . '"' : '';
	echo '<div class="rd-psection-header"' . $id_attr . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $id_attr already escaped above via esc_attr().

	if ( '' !== $icon ) {
		echo '<span class="rd-psection-header__icon dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span>';
	}
	if ( '' !== $title ) {
		// h2 (não h3) pra alinhar hierarquia HTML com o que Settings API gera
		// no `<h2>{title}</h2>` automático das sections padrão.
		echo '<h2 class="rd-psection-header__title">' . esc_html( $title ) . '</h2>';
	}
	if ( '' !== $desc ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller passa HTML controlado.
		echo '<p class="rd-psection-header__desc">' . $desc . '</p>';
	}

	echo '</div>';
}

/**
 * Wrapper de `add_settings_section()` que registra uma section padrão do WP
 * mas com header custom (ícone dashicons + título) em vez do `<h2>` cru
 * automático do Settings API.
 *
 * Truque do title vazio: passar `''` em `add_settings_section()` faz o WP
 * NÃO renderizar o `<h2>` automático — o callback emite o header completo
 * via `rd_panel_section_header()` (cobre ícone + título consistente).
 *
 * Substitui o padrão `add_settings_section( $id, $title, '__return_false', $page )`
 * — cada chamada vira 1 linha em vez de 6, ganhando ícone de bônus.
 *
 * @param string $id    ID da section (ex: 'sec_geral_shell').
 * @param string $title Título traduzível.
 * @param string $icon  Slug do dashicon (sem prefixo `dashicons-`).
 *                      Ex: 'admin-appearance', 'shield-alt'. '' pra sem ícone.
 * @param string $page  Page slug (ex: 'rd_options_general') — mesma do add_settings_section.
 */
function rd_panel_register_section( string $id, string $title, string $icon, string $page ): void {
	add_settings_section(
		$id,
		'', // Title vazio = WP não renderiza <h2> automático; callback emite header.
		static function () use ( $id, $title, $icon ) {
			rd_panel_section_header(
				array(
					'id'    => $id, // id na div pra hash anchors (deep links do Dashboard).
					'icon'  => $icon,
					'title' => $title,
				)
			);
		},
		$page
	);
}
