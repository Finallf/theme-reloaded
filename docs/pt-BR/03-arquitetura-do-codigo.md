# 03 — Arquitetura do Código

## 📂 Estrutura de pastas

```
reloaded.com.br/
├── 404.php                   # Template de erro 404 (com Recommended Levels)
├── archive.php               # Listagem de categoria/tag/data
├── author.php                # Página do autor (multi-redator ready, com Schema Person)
├── comments.php              # Template do bloco de comentários
├── footer.php                # Rodapé (4 colunas: Brand fixa + 3 widget, + bottom bar)
├── functions.php             # Entry point — só requires + bootstrap
├── header.php                # Header (top bar + branding + menu + scripts)
├── index.php                 # Fallback principal (home)
├── page.php                  # Páginas estáticas (fallback genérico)
├── page-templates/           # Page templates NOMEADOS (atribuíveis via Atributos → Modelo, slug-agnostic)
│   ├── template-about.php    # "About Page (author hero)" — hero do autor + Schema Person
│   ├── template-contact.php  # "Contact Page" — sem thumb/comments
│   └── template-legal.php    # "Legal Page (Privacy / Terms)" — sem thumb/comments, "Last updated"
├── search.php                # Resultados de busca (4 layouts)
├── sidebar.php               # Sidebar: anúncios hardcoded + dynamic_sidebar (widgets: Apoie, Discord, etc.)
├── single.php                # Post individual
├── style.css                 # Header WP + CSS compilado
├── README.md
├── CHANGELOG.md
├── CONTRIBUTING.md
│
├── assets/
│   ├── css/                  # CSS compilado (gerado via SCSS)
│   │   ├── style.css         # Frontend
│   │   └── admin-style.css   # Painel admin
│   ├── fonts/                # .woff2 locais (Inter, Poppins, JetBrains Mono)
│   ├── img/                  # Logos, ícones, banners
│   ├── js/
│   │   ├── navigation.js     # Frontend (menu, modais, busca AJAX, ad close, 404 trigger, theme toggle)
│   │   ├── views-tracker.js  # Tracking de views (carregado só em singles)
│   │   ├── admin-panel.js    # Bundle do painel admin (todas as abas; cada módulo se auto-protege)
│   │   └── admin-category-color.js # Color picker na tela de Categorias
│   └── vendor/
│       └── prism/            # Prism.js (syntax highlighting)
│
├── inc/                      # Lógica PHP modular (código próprio)
│   ├── core.php              # Setup do tema, supports, menus, sidebars, helpers
│   ├── panel.php             # Painel admin (registros + render)
│   ├── post-card.php         # Renderer reutilizável de cards de post
│   └── mod-*.php             # Cada feature em um módulo isolado
│
├── lib/                      # Libs 3ª parte runtime (vendored)
│   ├── Parsedown.php         # Markdown parser (Erusev, MIT, 1.7.4)
│   ├── prism.js              # Syntax highlighting (PrismJS, MIT, 1.30.0)
│   └── chartjs.min.js        # Gráficos no admin (Chart.js, MIT, 4.5.1)
│
├── languages/                # Internacionalização
│   ├── reloaded.pot          # Template (gerado via `wp i18n make-pot`)
│   ├── pt_BR.po              # Tradução em PT-BR (editável no Poedit)
│   └── pt_BR.mo              # Compilado (gerado pelo Poedit)
│
├── sass/                     # SCSS modular (fonte do CSS)
│   ├── style.scss            # Entry point do frontend — só @use → assets/css/style.css
│   ├── admin-style.scss      # Fonte do painel admin → assets/css/admin-style.css
│   ├── base/                 # Variáveis, globals, design tokens
│   ├── layout/               # Estruturas (header, footer, grid, sidebar, single, media queries)
│   └── components/           # Pedaços reutilizáveis (search, page-*, comments, lgpd, etc.)
│
├── docs/                     # Esta documentação
│
└── .github/
    └── workflows/            # CI/CD (smoke-test + master release)
```

## 🧠 Convenções

### Nomenclatura

| Tipo | Padrão | Exemplo |
|------|--------|---------|
| Arquivos PHP de feature | `inc/mod-{feature}.php` | `inc/mod-views.php` |
| Funções PHP públicas | `rd_*` | `rd_get_post_views()` |
| Hooks/filtros | `rd_*` (camelCase ou snake_case ok) | `rd_filter_menu_primary_category` |
| Post meta | `_rd_*` (underscore prefix esconde da UI Custom Fields) | `_rd_primary_category` |
| Constantes | `RD_*` | `RD_VIEWS_META_KEY` |
| CSS classes | `rd-*` (ou `.rd-{component}`) | `.rd-search-card`, `.rd-facade` |
| Classes utilitárias | `wp-*` (compatibilidade WP) | `.wp-block-embed` |
| AJAX action | `rd_{action}` | `rd_track_view`, `rd_search_redistribute` |
| Text domain (i18n) | `'reloaded'` (sempre) | `__('Search', 'reloaded')` |

### SCSS — uso de `@use` (não `@import`)

O sistema é totalmente modular usando o sistema de módulos moderno do Sass:

```scss
// sass/style.scss (entry)
@use 'base/variables' as *;     // Carrega como global
@use 'base/globals';
@use 'layout/header';
// ...
```

Variáveis declaradas em `base/_variables.scss` ficam disponíveis em todos os arquivos que dão `@use 'variables' as *`.

### CSS Custom Properties (CSS Variables)

Todo o sistema dark/light é feito via CSS vars no `:root` e `[data-theme="light"]`:

```scss
:root {
  color-scheme: dark;
  --rd-bg: #151515;
  --rd-text-light: #cccccc;
  --rd-glass-shadow: rgba(0, 0, 0, 0.4);
  // ...
}

[data-theme="light"] {
  color-scheme: light;
  --rd-bg: #f8f9fa;
  --rd-text-light: #333333;
  --rd-glass-shadow: rgba(0, 0, 0, 0.08);
  // ...
}
```

E SCSS variables fazem o "ponte":

```scss
$dark-text:      var(--rd-text);
$text-light:     var(--rd-text-light);
$glass-shadow:   var(--rd-glass-shadow);
```

### Hierarquia de cores de texto

| Variável | Quando usar |
|----------|-------------|
| `var(--rd-text)` | Reservado pra **destaque máximo** (use explicitamente) |
| `var(--rd-text-light)` | **Default** (body + h1-h6 globais usam isso) |
| `var(--rd-text-muted)` | Metadados, infos auxiliares |
| `var(--rd-text-dimmed)` | Textos bem secundários (placeholders, hints) |

> Estabelecido na refatoração `refactor(theme): adopt --rd-text-light as default body color`.

### Box-shadow padronizado

- **Cards/blocos comuns**: `box-shadow: 0 8px 20px var(--rd-glass-shadow)` (adapta dark/light, plano no light)
- **Hover azul**: `box-shadow: 0 8px 25px rgba(0, 168, 255, 0.2)` (cor da marca, mesma intensidade em ambos)
- **Sombras especiais hardcoded**: opacidade fixa `0.5` (`rgba(0, 0, 0, 0.5)`)

### Separação de Concerns (SoC)

O tema segue rigidamente:

- **Zero JS inline em templates PHP** → tudo vai pra `assets/js/navigation.js`
- **Zero CSS inline (`style="..."`)** → tudo vira class no SCSS
- **Zero `onclick=`** → tudo via `addEventListener` no JS
- **Strings dinâmicas** pra JS via `wp_localize_script` (ver `mod-views.php` como exemplo)

> Estabelecido em `refactor(soc): move inline scripts and styles to dedicated files`.

## 🔧 Bootstrap (entry point)

`functions.php` faz só uma coisa: requires na ordem correta.

```php
<?php
defined('ABSPATH') || exit;

// Núcleo + painel
require_once get_template_directory() . '/inc/core.php';
require_once get_template_directory() . '/inc/panel.php';

// Módulos de feature
require_once get_template_directory() . '/inc/mod-general.php';
require_once get_template_directory() . '/inc/mod-privacy.php';
require_once get_template_directory() . '/inc/mod-integrations.php';
require_once get_template_directory() . '/inc/mod-performance.php';
require_once get_template_directory() . '/inc/mod-social.php';
require_once get_template_directory() . '/inc/mod-seo.php';
require_once get_template_directory() . '/inc/mod-breadcrumbs.php';
require_once get_template_directory() . '/inc/mod-category-colors.php';
require_once get_template_directory() . '/inc/mod-archive-header.php';
require_once get_template_directory() . '/inc/mod-donations.php';
require_once get_template_directory() . '/inc/mod-ads.php';
require_once get_template_directory() . '/inc/mod-maintenance.php';
require_once get_template_directory() . '/inc/mod-views.php';
require_once get_template_directory() . '/inc/mod-security.php';
require_once get_template_directory() . '/inc/post-card.php';
require_once get_template_directory() . '/inc/mod-search.php';
require_once get_template_directory() . '/inc/mod-menu.php';
```

> Cada módulo é independente. Se quiser **desativar** um, comente a linha — o tema continua funcionando, só perde aquele recurso específico.

## 🌐 Helpers globais expostos

Funções que você pode usar em templates ou módulos próprios:

| Função | Onde vive | O que faz |
|--------|-----------|-----------|
| `rd_get_option($key, $default)` | `panel.php` | Lê opção do `rd_settings`, com default seguro |
| `rd_get_option_bool($key)` | `panel.php` | Idem mas força resultado booleano |
| `rd_render_logo()` | `core.php` | Renderiza logo (custom logo do WP ou texto fallback) |
| `rd_asset_version($relative)` | `core.php` | Versão dinâmica pra cache busting (mtime do arquivo) |
| `rd_get_client_ip()` | `core.php` | IP real do cliente. Valida `REMOTE_ADDR` contra ranges de proxy reconhecidos antes de confiar em `CF-Connecting-IP`/`X-Forwarded-For` (evita header spoofing). Usado em rate-limits e dedup |
| `rd_remote_is_trusted_proxy($ip)` | `core.php` | True se `$ip` está em range de proxy reconhecido (Cloudflare hardcoded + custom CIDR do painel `trusted_proxy_ips`) |
| `rd_ip_in_ranges($ip, $ranges)` | `core.php` | Match CIDR genérico IPv4/IPv6 via `inet_pton`. Ranges malformados são pulados em silêncio |
| `rd_get_post_views($post_id, $window)` | `mod-views.php` | Conta de views all-time ou janela (day/week/month/year) |
| `rd_get_popular_posts($limit, $args)` | `mod-views.php` | `WP_Query` ordenada por views |
| `rd_format_views_number($n)` | `mod-views.php` | Formata número de views respeitando config `views_number_format` (full/compact). Compact usa floor (truncate) — `1999` vira `1.9k`, nunca `2k` |
| `rd_get_primary_category_id($post_id)` | `mod-menu.php` | Categoria principal (com fallback pra primeira) |
| `rd_render_post_card($type)` | `post-card.php` | Renderiza card no layout escolhido (grid/vertical/compact/google) |
| `rd_render_distribution($distribution)` | `post-card.php` | Renderiza wrappers com posts distribuídos pelos layouts |
| `rd_search_distribute_posts($posts, $layouts)` | `mod-search.php` | Algoritmo de distribuição de resultados de busca |
| `rd_render_social_icons($user_id = null)` | `mod-social.php` | Renderiza 8 ícones sociais. Sem arg = URLs do portal (footer/template-about). Com `$user_id` = URLs pessoais do user (author archive) |
| `rd_render_news_ticker()` | `mod-social.php` | Ticker rolante da top bar (últimos N posts) |
| `rd_render_date()` | `mod-social.php` | Data atual formatada na top bar |
| `rd_youtube_parse_timestamp($t)` | `mod-performance.php` | Parseia timestamps do YouTube nos 4 formatos (`30`, `30s`, `1m30s`, `1h2m30s`) → inteiro de segundos. Usado pelo facade pra preservar `?t=` no embed |
| `rd_img_get_capabilities()` | `mod-image-formats.php` | Auto-detecta Imagick + GD + suporte a WebP/AVIF no servidor (cache static) |
| `rd_img_can_generate($format)` | `mod-image-formats.php` | Boolean — servidor consegue gerar `'webp'` ou `'avif'`? |
| `rd_img_convert($src, $fmt, $quality)` | `mod-image-formats.php` | Converte 1 arquivo (JPEG/PNG) pra WebP/AVIF. Prefere Imagick, fallback GD |
| `rd_stats_top_posts_by_views($limit, $window)` | `mod-stats.php` | Top N posts por views numa janela. Cache transient 1h. Usado pelo dashboard Statistics + Widget "Most Read" |

## 🎬 Lifecycle: o que carrega quando

| Hook | Ações executadas |
|------|------------------|
| `after_switch_theme` | `rd_set_default_options()` — popula `rd_settings` na primeira ativação |
| `after_setup_theme` | `rd_setup()` — supports (HTML5, post-thumbnails, custom-logo, etc.), tamanhos de imagem, registro de menus |
| `init` | `remove_image_size()` — tira tamanhos nativos não usados |
| `widgets_init` | `rd_widgets_init()` — registra Sidebar Principal + Footer Widget Area |
| `wp_enqueue_scripts` | Enqueue de Prism, navigation.js, views-tracker.js (singles) |
| `admin_enqueue_scripts` | Enqueue do `admin-panel.js` (bundle único) + `admin-style.css` (apenas no painel ReloadeD) |
| `admin_menu` | `rd_add_admin_menu()` — registra "ReloadeD" no menu top-level |
| `add_meta_boxes` | `rd_add_post_options_meta_box` — meta box "Post Options (ReloadeD)" |
| `wp_head` | Scripts do GA + Open Graph tags + ad global |
| `wp_footer` | `rd_render_ad_mobile_anchor` — banner anchor fixo em mobile |

## 🔗 Próximos passos

- **[04 — Módulos PHP](04-modulos-php.md)**: o que cada `inc/mod-*.php` faz
- **[05 — Templates](05-templates.md)**: cada `.php` da raiz
