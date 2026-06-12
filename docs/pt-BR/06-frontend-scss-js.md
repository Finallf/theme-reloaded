# 06 — Frontend SCSS + JS

## 🎨 Arquitetura SCSS

O CSS do tema é totalmente **escrito em SCSS** com sistema modular `@use`. O entry point do frontend é `sass/style.scss`, que importa todos os parciais → `assets/css/style.css`. O painel admin tem um source próprio standalone, `sass/admin-style.scss` → `assets/css/admin-style.css` (não usa `@use`, é um arquivo único; convertido de CSS-à-mão pra SCSS pra ganhar minificação + comentários stripados no build, igual ao frontend). A **Fase 2** do refactor reorganizou o arquivo inteiro: paleta nativa do wp-admin centralizada em ~22 variáveis `$rd-*` + nesting BEM (`&__elem`/`&--mod`) em todos os seletores. Continua standalone — **não** compartilha os tokens do frontend (`base/_tokens.scss`), porque a paleta do admin é a do wp-admin (azul `#2271b1`, cinzas `#1d2327`/`#50575e`, etc.), não a da marca.

### Estrutura de pastas

```
sass/
├── style.scss              # Entry point do frontend (só @use)
├── admin-style.scss        # Source do painel admin (standalone) → admin-style.css
├── base/
│   ├── _variables.scss     # @font-face + SCSS vars + CSS vars (dark/light)
│   ├── _globals.scss       # Reset, body, h_, .container, .post-tag, scrollbar
│   └── _tokens.scss        # Design tokens (cores secundárias, espaçamentos)
├── layout/
│   ├── _header.scss        # Top bar, top branding, menu bar fixed, theme switch
│   ├── _footer.scss        # Footer columns + bottom bar
│   ├── _grid.scss          # Home grid (.post-grid, .grid-item)
│   ├── _sidebar.scss       # Sidebar widgets, Discord block, search widget
│   ├── _single.scss        # Single post (article header, .post-tags, byline)
│   └── _media.scss         # Modo hambúrguer (≤1024px) — slide panel, etc.
└── components/
    ├── _backtotop.scss     # Botão flutuante "voltar ao topo"
    ├── _breadcrumbs.scss   # Trilha de navegação contextual (Home › Cat › Post)
    ├── _carousel.scss      # Carrossel de destaques (scroll-snap, peek, overlay, dots)
    ├── _buttons.scss       # Botões compartilhados
    ├── _comments.scss      # Lista de comentários + form
    ├── _facades.scss       # Facade YouTube/Discord (estado clickable)
    ├── _home.scss          # Vitrine configurável da home (override Grid 3-col + spacing das seções; reusa wrappers do _search)
    ├── _latest-video.scss  # Widget "Último Vídeo" da sidebar/footer (reusa o facade)
    ├── _lgpd.scss          # Banner de cookies (expansível, 3 categorias de consent)
    ├── _markdown.scss      # Estilização de conteúdo Markdown (h_, code, blockquote, table, alerts GitHub-style)
    ├── _overline.scss      # Chapéu/sobretítulo do post (dois contextos: single + card)
    ├── _page-404.scss      # Card 404 + Recommended Levels
    ├── _page-archive.scss  # Header de archive
    ├── _page-author.scss   # Header de autor
    ├── _page-static.scss   # page.php styling
    ├── _pagination.scss    # Pagination (numbered + prev/next)
    ├── _prism.scss         # Override do tema do Prism.js
    ├── _search.scss        # Página de busca (header + chips + 4 layouts)
    └── _social-widget.scss # Widget Redes Sociais "Siga-nos" (grade 2 col de chips)
```

### Ordem de import no entry

```scss
// sass/style.scss
@use 'base/variables' as *;     // Carrega como global (vars disponíveis em todos)
@use 'base/globals';
@use 'base/tokens';

@use 'layout/footer';
@use 'layout/grid';
@use 'layout/header';
@use 'layout/media';            // Carrega DEPOIS de header — sobrescreve em media queries
@use 'layout/single';
@use 'layout/sidebar';

@use 'components/buttons';
@use 'components/comments';
@use 'components/prism';
@use 'components/backtotop';
@use 'components/breadcrumbs';
@use 'components/facades';
@use 'components/lgpd';
@use 'components/markdown';
@use 'components/overline';
@use 'components/page-404';
@use 'components/page-static';
@use 'components/page-author';
@use 'components/page-archive';
@use 'components/pagination';
@use 'components/popular-widget';   // Widget "Most Read" da sidebar
@use 'components/latest-video';     // Widget "Último Vídeo" da sidebar/footer
@use 'components/search';
@use 'components/related-posts';    // Depois de search — herda .rd-wrapper-grid
@use 'components/toc';              // Sticky Table of Contents (FAB)
@use 'components/search-suggestions'; // Autocomplete do campo de busca
@use 'components/print';            // @media print — por último pra garantir override
```

> **Regra de ouro de ordem**: `layout/media.scss` precisa vir DEPOIS de `layout/header.scss` porque sobrescreve regras desktop do header em mobile/tablet. Da mesma forma, `components/print.scss` vem **por último** — todas as regras `@media print` devem vencer no cascade pra garantir que o CSS de impressão sobrescreva qualquer regra de tela definida antes.

### Sistema de variáveis (Dark/Light)

Tudo em `sass/base/_variables.scss`. 3 camadas:

#### 1. `@font-face` — fontes locais

**Inventário atual (14 arquivos, ~366 KB total):**
- **Inter** (4 variants): 400 Regular, 500 Medium, 600 SemiBold, 700 Bold
- **Poppins** (6 variants): 400 Regular, 400 italic, 500 Medium, 600 SemiBold, 700 Bold, 900 Black
- **JetBrains Mono** (4 variants): 400 Regular, 400 italic, 600 SemiBold, 700 Bold

Todos `font-display: swap`.

**Removidos durante a Wave 8 cleanup:**
- ❌ **Poppins 800 ExtraBold** (2026-05-22) — único uso era `h2.wp-block-heading` da sidebar, trocado por Poppins 500 (consistência visual com h2 dos grids da home)
- ❌ **Inter Italic** (2026-05-23) — todos os usos eram decorativos secundários (blockquote, image captions, footer hosting-info, comment meta, LGPD tag). Browser sintetiza italic via `font-synthesis: style weight` rule global em `_globals.scss` (no `body`) — qualidade indistinguível pra texto curto

**Net savings:** ~105 KB de inventário + 2 downloads HTTP a menos por página que tinha elementos italic.

#### 2. SCSS variables (mapping)

```scss
$font-primary: 'Inter', sans-serif;
$font-heading: 'Poppins', sans-serif;

// $font-mono: para CÓDIGO real (markdown blocks, Prism, <code>)
// JetBrains Mono é a fonte branded de código — vale o download em páginas de post.
$font-mono:    'JetBrains Mono', ui-monospace, 'Cascadia Mono', 'Source Code Pro', Menlo, Consolas, monospace;

// $font-system-mono: para ELEMENTOS DE UI que precisam de monospace mas NÃO são código
// (botão PIX copy na sidebar, etc.). Evita preload de JetBrains Mono nessas situações.
// Crítico pra performance: sem isso, browser baixa JetBrains Mono em TODA página com
// sidebar+doações ativas (donations widget está em sidebar = above-the-fold).
$font-system-mono: ui-monospace, 'Cascadia Mono', 'Source Code Pro', Menlo, Consolas, monospace;

$brand-blue-dark:  #031CFF;
$brand-blue-light: #00A8FF;
$brand-gradient:   linear-gradient(135deg, $brand-blue-dark 0%, $brand-blue-light 100%);

// Aliases pra CSS vars (pra usar em SCSS sem repetir var(...))
$dark-bg:         var(--rd-bg);
$dark-text:       var(--rd-text);
$text-light:      var(--rd-text-light);
$dark-text-muted: var(--rd-text-muted);
// ... etc
```

#### Preload de fontes críticas

`inc/mod-performance.php` injeta `<link rel="preload">` pros **2 arquivos** do above-the-fold (controlado pelo toggle `preload_critical_fonts` no painel, default ON):

```
- inter-variable.woff2   (TODOS os pesos do corpo, eixo 400-700 — latin, 47 KB)
- poppins-variable.woff2 (TODOS os pesos de headings, eixo 100-900 — latin, 17,6 KB)
```

> **Migração pra fontes variáveis (2026-06-12):** um woff2 variável guarda os contornos UMA vez + deltas de interpolação — o browser calcula qualquer peso do eixo em tempo real. Inter e JetBrains Mono vieram dos slices latin do Google Fonts; a **Poppins variável é build próprio do Finallf** (Google não a distribui; subset validado em `tools/fonts/font.html`). Inventário: 14 arquivos/366 KB → **5 arquivos/132 KB (−64%)**. Itálicas (Poppins/JetBrains) continuam estáticas — itálica real é desenho separado, e a JetBrains itálica variável pesava mais que a estática 400 (única usada).

JetBrains Mono (variável + itálica estática) carrega normalmente via `@font-face` só em posts com Prism.

#### 3. CSS Custom Properties (a "fonte da verdade" pro tema)

```scss
:root {
    color-scheme: dark;

    // Modo Dark (Padrão)
    --rd-bg:         #151515;
    --rd-surface:    #212121;
    --rd-deep:       #111111;
    --rd-deeper:     #0d1117;

    --rd-text:        #e0e0e0;
    --rd-text-light:  #cccccc;
    --rd-text-label:  #aaaaaa;
    --rd-text-muted:  #999999;
    --rd-text-dimmed: #909090;

    --rd-border:        #222222;
    --rd-border-light:  #333333;
    --rd-border-subtle: #30363d;

    --rd-glass-bg:     rgba(255, 255, 255, 0.04);
    --rd-glass-border: rgba(255, 255, 255, 0.08);
    --rd-glass-shadow: rgba(0, 0, 0, 0.4);
    --rd-media-shadow: rgba(0, 0, 0, 0.4);

    --rd-blue-dark:  #031CFF;
    --rd-blue-light: #00A8FF;

    // Padding do "card" de conteúdo (posts single + páginas estáticas) — fonte
    // única, com escada responsiva; o FAB do TOC lê --rd-content-pad-inline.
    --rd-content-pad-block:  50px;  // 40px ≤1024px · 30px ≤768px
    --rd-content-pad-inline: 60px;  // 40px ≤1024px · 25px ≤768px · 0.5rem <600px (só posts)
}

[data-theme="light"] {
    color-scheme: light;

    --rd-bg:      #f8f9fa;
    --rd-surface: #ffffff;
    --rd-deep:    #f1f3f5;
    --rd-deeper:  #e9ecef;

    --rd-text:        #1a1a1a;
    --rd-text-light:  #333333;
    --rd-text-label:  #495057;
    --rd-text-muted:  #6c757d;
    --rd-text-dimmed: #7a818b;

    --rd-border:        #dee2e6;
    --rd-border-light:  #e9ecef;
    --rd-border-subtle: #ced4da;

    --rd-glass-bg:     rgba(0, 0, 0, 0.03);
    --rd-glass-border: rgba(0, 0, 0, 0.1);
    --rd-glass-shadow: rgba(0, 0, 0, 0.08);
    --rd-media-shadow: rgba(0, 0, 0, 0.15);
}
```

### Sistema do Menu (sempre dark!)

**Decisão de design**: o menu (barra + painel hambúrguer + dropdowns) **NÃO acompanha o switcher dark/light**. Sempre dark, em ambos os modos.

Motivo: identidade visual estável + contraste garantido + padrão da indústria (GitHub, Twitter, Reddit).

Implementação:
- `.menu-bar-fixed`, `.menu-panel`, `.sub-menu` usam cores fixas hardcoded (`$color-menu-bg = #212121`, `rgba(255,255,255,...)`)
- Inputs do menu usam `color-scheme: normal` pra quebrar a herança de `:root { color-scheme: dark }` (necessário pra que widgets nativos como `<input type="search">` X de cancelar renderem corretamente)

### Breakpoints

| Breakpoint | Usado pra |
|------------|-----------|
| `≥1025px` | Desktop — layout completo inline (menu + busca expansível) |
| `769-1024px` | Tablets — modo hambúrguer com top bar **e busca expansível visíveis** (sidebar também empilha em ≤1024px — threshold único de "layout compacto") |
| `≤768px` | Mobile real — modo hambúrguer + top bar oculta + busca expansível oculta (busca vive no painel) |
| `≤600px` | Ajustes finos pra phones (raros) |

A maior parte das responsividade vive em `sass/layout/_media.scss`.

### Padrões unificados de card (sombra + hover)

Após auditoria geral de cards (todos os 12 componentes com `box-shadow` ou estética de card), o tema segue convenção única:

| Cenário | Estilo |
|---------|--------|
| **Cards/headers (estado normal)** | `0 8px 20px var(--rd-glass-shadow)` (adapta dark/light) |
| **Cards interativos (hover)** | `transform: translateY(-4px); border-color: $brand-blue-light; box-shadow: 0 8px 25px rgba($brand-blue-light, 0.2);` |
| **Transition** | `all 250ms ease` (cards interativos) |

**Cards interativos cobertos pela convenção:**
- `.grid-item` (cards principais home/index)
- `.rd-search-card.layout-grid` / `.layout-vertical` / `.layout-compact` (cards da busca)
- `.rd-404-recommended-item` (cards de "Níveis Recomendados" na 404)

**Headers/containers não-interativos** (mesma sombra base, sem hover):
- `.rd-search-header`, `.rd-404-card`, `.rd-author-header`, `.rd-archive-header`, `.rd-facade`

> **Histórico:** versões anteriores tinham `rgba(0, 0, 0, 0.5)` hardcoded em 5 lugares (não adaptavam dark/light), `.grid-item` usava `translateY(-8px)` e `0 10px 30px` (mais dramático que os outros), `.rd-card-link-flex` compact usava `-2px` outlier. Tudo unificado pra o padrão acima.

### Classe utilitária `.rd-resizing` (anti-flash)

Pra evitar flashes/teleportes de elementos quando a janela é redimensionada cruzando breakpoints (ex: `.menu-panel` muda de `display: contents` pra `display: block + position: fixed`), o `_globals.scss` define:

```scss
.rd-resizing,
.rd-resizing *,
.rd-resizing *::before,
.rd-resizing *::after {
    transition: none !important;
}
```

`navigation.js` adiciona `.rd-resizing` no `<body>` no início do evento `resize` e remove após debounce de 150ms — durante a janela ativa, todas as transitions ficam desligadas globalmente, evitando que transitions disparem visualmente durante mudanças de `display`. Padrão da indústria pra esse cenário.

### Transitions de tema escopadas (`html.rd-theme-switching`)

O espelho do `.rd-resizing`: o `_globals.scss` tinha um `* { transition: background-color, border-color, color, box-shadow }` **incondicional** — todo elemento da página carregava 4 transitions em tempo integral (Lighthouse flagava 200+ "animações não compostas" e cada hover pagava taxa de style-recalc), sendo que o efeito só é desejado na troca dark/light. Agora a regra é `html.rd-theme-switching *`: o toggle de tema em `navigation.js` adiciona a classe no `<html>` imediatamente antes de virar o `data-theme` e remove ~400ms depois. Transitions de hover declaradas por componente (cards, links, botões) não são afetadas.

### Fallbacks de fonte com métricas casadas

`_variables.scss` define `Inter-fallback`/`Poppins-fallback` (`src: local('Arial')` + `size-adjust`/`ascent`/`descent`/`line-gap-override`) e os stacks `$font-primary`/`$font-heading` os incluem após a fonte real. Com `font-display: swap`, o primeiro paint usa Arial **redimensionado pra ocupar as mesmas caixas** da fonte final — a troca não re-quebra linha nenhuma (matou o CLS residual do banner LGPD). Detalhes no [doc de módulos](04-modulos-php.md), seção Critical fonts.

### Print stylesheet (`sass/components/_print.scss`)

Otimiza o output em papel ou "Salvar como PDF". Adicionado em 2026-05-29 — antes disso o tema não tinha **nenhuma** regra `@media print`, então um Ctrl+P imprimia header + sidebar + ads + footer junto.

#### Filosofia

- **Tudo dentro de um bloco `@media print { ... }`** — zero impacto fora do contexto de impressão.
- **Importado por último** em `style.scss` pra ganhar a guerra de especificidade contra qualquer componente de tela definido antes.
- **Sem dependências de variáveis dark/light** — paleta forçada pra fundo branco + texto preto (economia de tinta e legibilidade em papel).

#### O que esconde (`display: none !important`)

Tudo que é UI/navegação e não-conteúdo: `.site-header`, `.site-footer`, `.rd-top-bar`, `.menu-bar-fixed`, `.main-navigation`, `.navigation`, `.menu-toggle`, `.menu-close`, `.header-search-container`, `.theme-switch-wrapper`, `.theme-toggle-btn`, `.site-branding-toggle`, `aside`, `.footer-widget`, `.rd-breadcrumbs`, `.back-to-top`, `.rd-cookie-banner`, `.rd-lgpd-reopen`, `.rd-toc` + variantes (`.rd-toc__fab`, `.rd-toc__panel`, `.rd-toc-anchor`), `.rd-sugg`, `.rd-search-suggestions`, `.rd-related-posts`, `.rd-popular-posts`, `.rd-social-icons`, `.rd-social-link`, `.rd-ticker-*`, `.rd-ad-container` + closers, `.rd-support-block`, `.rd-copy-btn`, `.comments-area`, `.comment-list`, `#respond`, `.pagination`, `.reading-time`.

#### O que mantém

- Título do post (h1)
- Conteúdo (`.single-post-content`, `.entry-overline`, parágrafos, listas, blockquotes)
- Imagens (forçadas pra `max-width: 100%` + `page-break-inside: avoid`)
- Code blocks (com borda de 1px + monospace 10pt pra distinguir do corpo)
- Tabelas

#### Truques aplicados

- **Reset global** `* { background: transparent !important; color: #000 !important; box-shadow: none !important; }` — neutraliza qualquer sombra/gradiente de tela que iria poluir a impressão.
- **URL após links externos** via `a[href^="http"]:after { content: " (" attr(href) ")"; }` — pro leitor offline conseguir digitar a URL depois. Skip pra âncoras (`#`) e `mailto:` que não fariam sentido.
- **Tipografia print-friendly:** Georgia 12pt no corpo, Courier 10pt em code. Serif lê melhor em papel; monospace mantém alinhamento de código.
- **Page-break rules:**
  - `page-break-after: avoid` em `h1`-`h6` — título não fica sozinho no fim da página
  - `page-break-inside: avoid` em `p`, `blockquote`, `ul`, `ol`, `pre`, `table`, `img`, `picture`
  - `orphans: 3; widows: 3;` — mínimo de 3 linhas de parágrafo em cada quebra
- **Layout full-width:** `.site-main`, `.content-area`, `.single-post-content`, `.post-grid`, `.grid-item` recebem `width: 100% !important; float: none !important; margin: 0; padding: 0;` — sem o sidebar escondido, o conteúdo ocupa a folha inteira.

#### Como testar sem imprimir

Chrome DevTools → F12 → menu `⋮` (canto sup. direito) → **More tools** → **Rendering** → rolar até **Emulate CSS media type** → escolher **print**. A página renderiza ao vivo com o CSS de impressão. Ctrl+R re-aplica. Pra ver o preview de quebras de página/cabeçalho/rodapé do browser, usar Ctrl+P direto.

---

## ⚙️ Frontend JavaScript

Tudo em **vanilla JS** (sem jQuery no frontend). Os arquivos:

### `assets/js/navigation.js` (~410 linhas)

Arquivo principal do frontend — o único global incondicional. Vários `DOMContentLoaded` listeners independentes pra recursos diferentes.

> **Dieta da v1.7.x (PageSpeed wave):** dois blocos que só trabalhavam em páginas específicas foram extraídos pra arquivos próprios com enqueue condicional — o controle de layout da busca (~7 KB → `search-layout.js`, só `is_search()`) e o submit AJAX de comentários (~4,7 KB → `comments.js`, só `is_singular() + comments_open()`). O arquivo caiu de ~29 KB pra ~18 KB.

#### 1. **Voltar ao Topo**
- Botão `#back-to-top` que aparece após scroll de 300px
- Smooth scroll ao clicar

#### 2. **Menu Hambúrguer + Busca Integrada**
- `.menu-toggle` (hambúrguer, lado esquerdo da barra) só ABRE o `.menu-panel` (slide-in da esquerda, desliza por cima do botão)
- `.menu-close` (X flutuante fora da borda direita do painel aberto, alinhado à busca interna; revelado via `.toggled +` no CSS) fecha o painel — sem morphing de ícone no hambúrguer
- Funções `window.rdOpenMenuPanel(focusSearch)` e `window.rdCloseMenuPanel()` expostas pra outros handlers (o trigger do 404 usa `rdOpenMenuPanel(true)` pra abrir + focar a busca interna)
- Resize listener com debounce: fecha o painel se usuário redimensionar pra >1024px (evita ficar "preso aberto"). **Também adiciona `.rd-resizing` no `<body>` no início do resize e remove após debounce de 150ms** — neutraliza transitions durante o resize ativo, evita o flash do `.menu-panel` quando muda de `display: contents` (desktop) pra `display: block + position: fixed` (mobile/tablet)

#### 3. **Facades (YouTube/Discord)**
- Click handler em `.rd-facade` — substitui o div estático pelo iframe real
- Pra Discord, persiste estado em `sessionStorage` pra não recolher na navegação
- **YouTube timestamp:** lê `data-t` injetado pelo PHP (`rd_youtube_facade` em `mod-performance.php`) e propaga como `&start=N` na URL do iframe. Vídeo abre no tempo exato definido na URL original (`?t=30s`, `?t=1m30s`, etc.)

#### 4. **Copy to Clipboard** (genérico)
Helper reutilizável em qualquer lugar do tema, com 2 formas de uso:

**Programático** (de qualquer JS):
```js
window.rdCopyToClipboard('texto a copiar', {
    feedbackEl:   '.meu-span',     // selector ou Element
    feedbackText: 'Copiado!',       // (opcional) sobrescreve reloaded_i18n.copied
    revertAfter:  2000,             // ms até voltar ao texto original
    onSuccess: function() { /* ... */ },
    onError:   function(err) { /* ... */ }
});
```

**Declarativo** (zero JS no template — só atributos HTML):
```html
<button data-rd-copy="valor a copiar"
        data-rd-copy-feedback=".meu-span"   <!-- opcional: selector ou 'self' -->
        data-rd-copy-text="Copiado!"         <!-- opcional: sobrescreve i18n -->
        data-rd-copy-revert="2000">          <!-- opcional: ms até reverter -->
    <span class="rd-copy-text">valor visível</span>
</button>
```

Por convenção, se `data-rd-copy-feedback` não for setado, o handler procura um `.rd-copy-text` dentro do botão como fallback.

A função usa `reloaded_i18n.copied` e `reloaded_i18n.copy_error` (via `wp_localize_script`) pras strings padrão.

**Consumidor atual:** botão "Copiar chave PIX" no bloco "Apoie o Projeto" (`inc/mod-donations.php`).

#### 5. **Prism Line Numbers**
- Adiciona `class="line-numbers"` em todos os `<pre>` automaticamente

#### 6. **Banner LGPD (consent granular)**
- Banner expansível com 3 botões: `#rd-lgpd-reject` / `#rd-lgpd-customize` / `#rd-lgpd-accept`
- Click em "Customize" → toggle `data-state="expanded"` no `#rd-lgpd-banner` + troca label do botão pra "Salvar preferências"
- Click em Reject/Accept/Save → grava cookie JSON `rd_lgpd_consent` (365 dias) + apaga cookie legado `rd_lgpd_accepted` + animação `.rd-lgpd-closing` + `location.reload()` após 350ms pra scripts gated re-avaliarem
- Link `#rd-lgpd-reopen` no footer → remove classe `.rd-lgpd-hidden` do banner e expande direto (sem reload, sem perder valores dos toggles que vêm pré-marcados pelo PHP)

#### 7. **Search Chips Toggle** → movido pra `assets/js/search-layout.js` (ver abaixo)

#### 8. **Ad Close (mobile anchor)**
- Click em `.rd-ad-close` → fade out + `display: none`

#### 9. **404 Search Trigger**
- Click em `#rd-404-search-trigger` → foca busca expansível (desktop) OU abre painel + foca busca interna (mobile/tablet)

#### 10. **Dark/Light Toggle**
- Click em `#rd-theme-toggle` → toggle `data-theme` no `<html>` + persiste em `localStorage['rd-theme']`

### `assets/js/carousel.js` (~130 linhas)

Camada de controle do Carrossel de Destaques (markup em `mod-carousel.php`; enqueue **condicional** — só quando o carrossel renderiza). O slide/swipe é CSS scroll-snap nativo; o JS adiciona:

- **Autoplay** com rewind (sem clonagem de DOM) — `scrollTo({ behavior: 'smooth' })`
- **Matriz de pausas:** hover/foco, `pointerdown` no track, aba oculta (`visibilitychange`), fora da viewport (`IntersectionObserver` threshold 0.25), e `prefers-reduced-motion` → autoplay nunca liga
- Setas/dots/teclado; slide ativo derivado da **posição de scroll** (fonte única de verdade — swipe, setas e autoplay convergem)
- Gate `.is-ready` revela os controles (sem JS = fileira swipeável pura)

### `assets/js/search-layout.js` (~200 linhas)

Controle de layout da busca (chips + redistribuição AJAX) — enqueue **condicional em `is_search()`** (`mod-search.php`, que também localiza o `rd_search_data` nesse handle). Vivia dentro do `navigation.js`, entregando ~7 KB de código morto pra toda página que não fosse busca.

- Click nos chips `.rd-chip` → toggle `.active` + atualiza `localStorage['rd_search_prefs']`
- Se `paged > 1` → full reload pra page 1
- Se `paged == 1` → AJAX `rd_search_redistribute` + swap de innerHTML
- Loading state `.rd-loading` durante AJAX
- Keyboard nav nos chips (setas/Home/End, padrão WAI-ARIA toolbar)

### `assets/js/comments.js` (~120 linhas)

Submit AJAX do form de comentários — enqueue **condicional em `is_singular() && comments_open()`** (`mod-performance.php`). Também extraído do `navigation.js`.

- Captura o submit do `#commentform` (só se a action ainda é a nativa `wp-comments-post.php` — não interfere com Disqus/Discourse)
- `fetch` com `redirect: 'manual'`: WP responde 302 no sucesso → `opaqueredirect` = comentário criado → feedback + soft reload; 200 com HTML de `wp_die` = erro → extrai a mensagem e mantém o form editável
- Strings i18n via objeto global `reloaded_i18n` (localizado no handle `rd-navigation`, que é global e imprime antes de qualquer script `defer` executar)

### `assets/js/search-suggestions.js` (~280 linhas)

Autocomplete da busca — **não é mais enfileirado**: um loader inline (~0,5 KB, `wp_footer`, nonce CSP) injeta o script + dados no **primeiro `focusin`** de um campo de busca (detalhes no [doc de módulos](04-modulos-php.md), seção mod-search-suggestions). Por isso o init checa `document.readyState` em vez de só esperar `DOMContentLoaded`.

### `assets/js/views-tracker.js` (~25 linhas)

Tracking de views, isolado pra carregar SÓ em singles.

```js
window.addEventListener('DOMContentLoaded', function () {
    if (typeof rd_views_data === 'undefined') return;
    setTimeout(function () {
        fetch(rd_views_data.ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=rd_track_view'
                + '&post_id=' + encodeURIComponent(rd_views_data.post_id)
                + '&nonce=' + encodeURIComponent(rd_views_data.nonce),
            keepalive: true
        });
    }, 1500);
});
```

Dados (`post_id`, `nonce`, `ajaxurl`) injetados via `wp_localize_script` no `mod-views.php`.

### `assets/js/admin-panel.js`

Bundle único do painel admin — consolida 7 módulos que antes eram arquivos separados por aba: uploads de mídia (WP Media Library), gráficos K4/auto-render (Chart.js), toggles inline do Dashboard, self-update do tema, import/export/restore de backup e regeneração WebP/AVIF (chunks com orçamento de tempo + resumo de falhas + botão "Remove unused format"). Enfileirado uma vez (`rd-admin-panel`, prioridade 5) em qualquer aba do painel; cada módulo interno tem escopo próprio e se auto-protege (sai cedo se o DOM/objeto localizado dele não existe), então o código fica inerte nas abas que não atende. Os módulos por aba (`mod-stats`/`mod-dashboard`/`mod-backup`/`mod-image-formats`) só injetam seus dados via `wp_localize_script` no handle `rd-admin-panel`.

> O módulo de upload de mídia usa jQuery (porque o admin do WP carrega jQuery por padrão); o resto é vanilla.

### `assets/js/admin-category-color.js`

Bootstrap do `wp-color-picker` (lib Iris) no campo "Color" da edição de categoria. Carregado apenas em `edit-tags.php` e `term.php` quando `taxonomy=category` (gating em `mod-category-colors.php → rd_category_color_admin_enqueue`). 5 linhas, jQuery one-liner.

---

## 🎬 Anti-FOUC do Dark/Light

Pra evitar flash de tema errado, o tema injeta um `<script>` inline no `<head>` ANTES de qualquer CSS:

```html
<head>
    <script>
    (function() {
        const adminDefault = '<?php echo esc_js( rd_get_option( 'default_theme_mode', 'system' ) ); ?>';
        const savedTheme = localStorage.getItem('rd-theme');
        let theme;

        if (savedTheme) {
            theme = savedTheme;
        } else if (adminDefault === 'dark' || adminDefault === 'light') {
            theme = adminDefault;
        } else {
            // 'system' mode — segue prefers-color-scheme
            let systemPrefersLight = false;
            if (window.matchMedia) {
                systemPrefersLight = window.matchMedia('(prefers-color-scheme: light)').matches;
            }
            theme = systemPrefersLight ? 'light' : 'dark';
        }

        document.documentElement.setAttribute('data-theme', theme);
    })();
    </script>
    <?php wp_head(); ?>
</head>
```

**Cascata de decisão:**
1. Escolha explícita do user (localStorage `rd-theme`)
2. Admin escolheu `dark` ou `light` como default → respeita
3. Admin escolheu `system` → segue `prefers-color-scheme` do SO
4. Fallback: `dark` (também usado se navegador não suporta `matchMedia`)

> O atributo vai no `<html>` (`document.documentElement`) porque `<body>` ainda não existe nesse momento. Seletores CSS `[data-theme="light"]` funcionam igual em qualquer elemento.
