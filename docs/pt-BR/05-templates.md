# 05 — Templates

Cada arquivo `.php` na raiz do tema é um **template** que o WordPress usa pra renderizar um tipo específico de página. A hierarquia segue o [Template Hierarchy do WordPress](https://developer.wordpress.org/themes/basics/template-hierarchy/).

---

## 🏠 `index.php` — Fallback principal

Usado quando nenhum template mais específico encontra. É o que renderiza a **home** quando configurada como "Posts mais recentes".

Estrutura típica:
```php
<?php get_header(); ?>
<main class="site-main">
    <div class="container">
        <div class="content-area">
            <?php if ( have_posts() ) : ?>
                <div class="post-grid">
                    <?php while ( have_posts() ) : the_post(); ?>
                        <!-- card de post -->
                    <?php endwhile; ?>
                </div>
                <?php the_posts_pagination(); ?>
            <?php else : ?>
                <p><?php esc_html_e('Nothing found.', 'reloaded'); ?></p>
            <?php endif; ?>
        </div>
        <?php get_sidebar(); ?>
    </div>
</main>
<?php get_footer(); ?>
```

> Os cards usam o estilo `.grid-item` definido em `sass/layout/_grid.scss`.

---

## 📰 `single.php` — Post individual

Renderiza um post `post_type=post`. Estrutura:

1. **Header do artigo** — **overline** (opcional) + linha com título à esquerda e **kicker** (chip da categoria primária) à direita + meta (data, autor, views)
2. **Imagem destacada** (a menos que `_rd_hide_thumbnail = yes`)
3. **Conteúdo** (`the_content()`) — passa pelo filtro Markdown se ativo
4. **Footer do artigo** — tags (`.post-tags`)
5. **Comentários** (`comments_template()`)

> **Overline (chapéu):** texto livre por post (ENTREVISTA, ANÁLISE, etc.) renderizado por `rd_render_post_overline()` em `mod-general.php`. Aparece dentro do `.entry-title-block` à esquerda, junto do `<h1>`. Aparece também nos cards do grid (`index.php`) em versão menor.

> **Kicker (categoria):** chip da categoria primária renderizado por `rd_render_single_category_kicker()` em `mod-category-colors.php`. Fica fixo no topo direito do `.entry-title-row` (mesmo quando o overline empurra o título pra baixo, porque overline+título estão num bloco separado). No mobile, vira `position: absolute` no canto superior direito pra liberar largura total do título.

Hooks específicos:
- `the_content` filter aplica Parsedown se Markdown ativo
- `views-tracker.js` é enfileirado aqui (via `is_singular('post')` check em `mod-views.php`)

A meta `_rd_hide_thumbnail` controla se a featured image renderiza ou não — usado quando o post começa com vídeo embed (evita duplicação visual).

---

## 📄 `page.php` — Páginas estáticas (genérico)

Fallback genérico pra qualquer `post_type=page` que não tenha template específico de slug. Renderiza:

1. Header com título
2. Imagem destacada respeitando meta `_rd_hide_thumbnail`
3. `the_content()`
4. Sidebar (`get_sidebar()`)
5. Comentários se habilitados

Estilizado por `sass/components/_page-static.scss`.

### Page templates nomeados (`page-templates/`)

> **Refator 2026-06-04:** os templates institucionais eram `page-{slug}.php` (casados ao slug PT pela Template Hierarchy). Isso acopla o **código** ao **slug** — anti-padrão pra tema distribuído. Migrados pra **named page templates** (header `Template Name:`) na pasta **`page-templates/`**, atribuíveis a qualquer página em **Página → Atributos → Modelo**, independente do slug/idioma (padrão dos temas profissionais). Slugs ficam em PT (conteúdo/SEO do site BR); código em EN. Template atribuído tem prioridade sobre `page-{slug}.php` na hierarquia → migração sem downtime.

Os 3 templates shipados:

#### `template-legal.php` — "Legal Page (Privacy / Terms)"

Genérico pra **qualquer página legal** (Política de Privacidade, Termos de Uso, etc.):

- ❌ Imagem destacada (irrelevante em texto legal) · ❌ Comentários (não vira fórum)
- ➕ Header com **"Last updated: {date}"** via `get_the_modified_date()` — boa prática LGPD. Aparece como `<p class="rd-page-updated">` em itálico discreto abaixo do `<h1>`
- Tipografia densa já vem do `.rd-page-content` em `_page-static.scss` (font-size 1.05rem, line-height 1.75)
- Classe `rd-page-legal` no `<article>` (era `rd-page-privacy`; generalizada pra servir Privacy + Terms)
- **Sidebar mantida** (linha de leitura confortável em 1440px)

#### `template-contact.php` — "Contact Page"

- ❌ Imagem destacada (foco nos canais) · ❌ Comentários (CTAs precisam de destaque)
- Conteúdo (email, redes, Discord, etc) vai pelo editor — **sem bloco hardcoded auto-injetado**
- Classe `rd-page-contact` · Sidebar mantida
- Formulário nativo (sem plugin) fica como item futuro do backlog se quiser

#### `template-about.php` — "About Page (author hero)"

Hero hardcoded no topo + conteúdo via editor:

- **Hero institucional**: avatar (Gravatar do autor) + display_name + biographical info (Usuários → Perfil) + ícones sociais via `rd_render_social_icons()` (redes do **portal**)
- **Botão "View all posts →"** ao lado do `<h1>` linkando `get_author_posts_url($author_id)` (= `/author/{slug}/`, renderizado pelo `author.php`)
- **Schema.org Person JSON-LD** inline — `sameAs` das URLs do portal, `name` com fallback `display_name → user_nicename → user_login`. Alimenta E-E-A-T
- Imagem destacada e comentários opt-in igual `page.php`
- Classe `rd-page-about`

> **Importante — multi-redator:** o template About é UMA página institucional do site (só 1 por site). Pra cada redator ter sua "página de autor" com posts dele, o WP usa `author.php` automaticamente em `/author/{username}/` — não precisa criar template novo por redator.

---

## 📂 `archive.php` — Listagem de categoria/tag/data

Usado pra:
- Páginas de categoria (`/category/games/`)
- Páginas de tag (`/tag/ragnarok/`)
- Arquivos de data (`/2026/05/`)
- Custom taxonomies

Estrutura:
1. Header rico via `rd_render_archive_header()` (módulo `mod-archive-header.php`) — ícone contextual SVG, título com gradient na parte dinâmica, descrição opcional, contador de posts e borda esquerda com a cor da categoria (quando aplicável)
2. Lista de posts no layout **vertical** (via `rd_render_post_card('vertical')`)
3. Pagination

Estilizado por `sass/components/_page-archive.scss`.

---

## ✍️ `author.php` — Página do autor (multi-redator ready)

Renderiza `/author/{username}/`. Template **automático do WP** — cada user com role que publica posts ganha sua própria URL sem precisar de configuração. Estrutura:

1. **Header (`.rd-author-header`)**:
   - Avatar (Gravatar 120x120) à esquerda
   - **Linha do título (`.rd-author-info-header` flex):** `<h1>` com nome à esquerda + ícones sociais do user à direita (mesmo padrão visual do template-about)
   - Bio (`get_the_author_meta('description')`)
   - Meta: número de posts publicados (`count_user_posts`) + "Member since {data}"
2. Lista de posts do autor no layout **vertical** (reusa `rd_render_post_card('vertical')` do search)
3. Pagination ou empty state ("This author has not published any articles yet.")

### Schema.org Person JSON-LD

Inline no template, com:
- `@id => get_author_posts_url($author_id) . '#person'` — matching com o Person dentro do `Article` schema (em `mod-seo.php`). Google amarra Article + perfil como o MESMO Person → E-E-A-T
- `name` — fallback chain `display_name → user_nicename → user_login` (evita "Item sem nome" em users seedados)
- `image` (avatar Gravatar 240px), `url`, `description` (bio)
- `sameAs` — array com URLs das redes pessoais do user (não do portal — ver abaixo)

### Ícones sociais por user

Renderizados via `rd_render_social_icons( $author_id )` — passa o `$user_id` opcional pro helper, que busca em `get_the_author_meta('social_X', $user_id)` em vez das URLs globais. Cada redator preenche **suas próprias** URLs em **Usuários → Perfil → Informações de contato** (campos adicionados pelo filter `rd_add_user_social_fields` em `mod-social.php`). 8 redes: discord, telegram, whatsapp, youtube, instagram, steam, twitter, facebook.

### Pra novos redatores

Zero trabalho extra. Quando um user `joao` registrar com role que publica + publicar primeiro post:
- `/author/joao/` funciona automaticamente
- Ícones sociais aparecem se ele preencher os campos no perfil
- Schema Person é gerado com seus dados
- Article schema dos posts dele aponta pro `@id` deste perfil

Estilizado por `sass/components/_page-author.scss`.

---

## 🔍 `search.php` — Resultados de busca

Renderiza `/?s=...`. Estrutura:

1. Header `.rd-search-header` com:
   - H1 "Pesquisou por: `<span class="rd-search-term">termo</span>`"
   - Chips de filtro (`Grid | Vertical | Compact | Google`) — se mais de 1 layout ativo
2. Container `.rd-search-results-containers`:
   - Posts distribuídos pelos layouts ativos via `rd_search_distribute_posts()`
   - Renderizados via `rd_render_distribution()` (de `inc/post-card.php`)
3. Pagination
4. Bloco "No results" se a busca veio vazia

JS:
- Toggle dos chips via AJAX (`rd_search_redistribute` action)
- Persistência da preferência em `localStorage['rd_search_prefs']`

Veja [04 — Módulos PHP — mod-search](04-modulos-php.md#-incmod-searchphp--sistema-de-busca-multi-layout) pra detalhes do algoritmo.

---

## 🚫 `404.php` — Página não encontrada

Renderiza quando WordPress não acha o conteúdo. Tema temático "LEVEL NOT FOUND" 🎮:

1. Card central `.rd-404-card`:
   - Big "404" code
   - Título "LEVEL NOT FOUND"
   - Mensagem amigável
   - Botões: "Voltar ao início" (primário) + "Buscar conteúdo" (secundário)
2. Seção "Níveis Recomendados":
   - 3 posts populares via `rd_get_popular_posts(3)`
   - Fallback pros 3 posts mais recentes se views não disponíveis ainda
   - Layout grid customizado `.rd-404-recommended-grid`

JS:
- Botão "Buscar conteúdo" foca a busca expansível do header (≥1441px) ou abre o painel hambúrguer + foca a busca interna (≤1440px)

Estilizado por `sass/components/_page-404.scss`.

---

## 🛡️ `403.php` — Acesso negado (template passivo)

Template no mesmo estilo visual do 404, mas pra erros 403 (Forbidden). **Passivo:** WordPress não roteia automaticamente pra 403 no fluxo normal — o arquivo fica disponível pra ser servido por código futuro:

```php
status_header( 403 );
include get_template_directory() . '/403.php';
exit;
```

Cenários previstos: bloqueio manual de IPs, área restrita por permissão, plugins de segurança que queiram tela visual em vez do `wp_die` cinza padrão.

Tom RPG/fantasy harmonizando com o 404:
- Big "403" code
- Título "PORTÃO SELADO"
- Mensagem do "guardião"
- Botões: "Voltar ao portal" (primário) + "Apresentar credenciais" (secundário, vai pra `wp_login_url()`)
- Seção "🗝️ Áreas Liberadas" com posts populares (mesma lógica do 404)

Reusa `sass/components/_page-404.scss` — todas as classes `.rd-404-*` cobrem os dois templates.

---

## 🛠️ Páginas de erro adicionais (500, 503)

Não são templates `.php` — são wp_die contexts com HTML inline:

- **503 Service Unavailable** — modo manutenção (`mod-maintenance.php`). Card dark themed, logo, mensagem customizável, opcional formulário de login pra dev burlar. Tema visual padronizado com 404 (fade-in animation, glass card, gradient stripe)
- **500 WSOD** — tela "critical error" do WP 5.2+ brandeada via `wp_php_error_args` + `wp_php_error_message` em `mod-security.php`. Mesma estética da manutenção (logo, brand colors, dark bg) sobrescrevendo o template grayscale padrão do `_default_wp_die_handler`

Resultado: set completo 403/404/500/503 com identidade visual consistente.

---

## 📜 `header.php` — Topo de toda página

Estrutura:

```html
<!DOCTYPE html>
<html>
<head>
    <!-- Script anti-FOUC inline pro dark/light -->
    <script>(function(){...})();</script>
    <?php wp_head(); ?>
</head>
<body>
    <!-- Top bar (data + ticker + sociais) — opcional -->
    <div class="rd-top-bar">...</div>

    <header class="site-header">
        <div class="top-branding">
            <div class="container">
                <div class="site-branding"><?php rd_render_logo(); ?></div>
                <div class="header-banner"><?php rd_render_ad_topo(); ?></div>
            </div>
        </div>

        <div class="menu-bar-fixed">
            <div class="container">
                <nav class="main-navigation">
                    <div class="site-branding-toggle"><?php rd_render_logo(); ?></div>
                    <div class="menu-panel" id="primary-menu-panel">
                        <form class="menu-search-form">...</form>
                        <?php wp_nav_menu(...); ?>
                    </div>
                </nav>

                <div class="header-search-container">...</div>
                <button class="menu-search-toggle">...</button>
                <div class="theme-switch-wrapper">...</div>
                <button class="menu-toggle">...</button>
            </div>
        </div>
    </header>
```

**Estratégia responsiva:**
- ≥1441px: menu inline horizontal + busca expansível visível
- ≤1440px: menu vira hambúrguer (slide panel) + busca-icon abre painel

Veja [06 — Frontend SCSS + JS](06-frontend-scss-js.md) pra detalhes de breakpoints.

---

## 🦶 `footer.php` — Rodapé

**4 colunas** (grid `auto-fit`, empilha no mobile; separadores verticais sutis no desktop ≥1200px):

1. **Brand** (fixa) — logo (ou nome) + tagline via `get_bloginfo('description')` com frase de fallback se vazia
2-4. **3 áreas de widget** — `footer-widget-area`, `footer-widget-area-2`, `footer-widget-area-3` ("Footer Column 2/3/4"), registradas em loop no `core.php`. Montadas via widgets em Aparência → Widgets (ex.: **Institucional** via "Menu de Navegação", **Mais Lidos**, **Último Vídeo**). Empty-state ("Add a widget…") só visível pra admin.

> O menu de navegação no footer agora é por widget (não mais o `menu-footer` hardcoded). CSS: `.footer-widget .menu` vira lista de links limpa (espelha `.footer-links`), excluído do estilo genérico de post-list.

Bottom bar:
- Copyright "© ano. Todos os direitos reservados."
- "Hospedado em infraestrutura própria."
- Botão "Voltar ao topo" (controlado por JS em navigation.js)

Banner cookie LGPD renderizado aqui (se ativo + cookie ainda não aceito).

`wp_footer()` no fim — onde plugins/scripts injetam coisas.

---

## 📑 `sidebar.php` — Sidebar lateral

Usada por single.php, page.php, archive.php, search.php, author.php.

Conteúdo:

1. **Sidebar dinâmica `sidebar-1`** — widgets WP nativos (Most Read, Latest Video, **Apoie o Projeto**, **Discord**, **Ad / Banner**, etc.)
2. **Banner sidebar sticky** (`rd_render_ad_sidebar_sticky()`) — `position: sticky` (único bloco hardcoded restante)

---

## 💬 `comments.php` — Bloco de comentários

Carregado por `comments_template()` em `single.php`.

- Lista paginada de comentários com avatares
- Form de novo comentário com a11y melhorado (`mod-general.php` filtra `comment_form_default_fields`)
- Nested comments (resposta a respostas)
- "Comments are closed" se admin desativou

---

## 🎯 Hierarquia em prática

Quando o WP recebe uma URL, ele segue a Template Hierarchy. Exemplos:

| URL | Template usado |
|-----|----------------|
| `/` (com posts mais recentes) | `index.php` |
| `/sample-page/` | `page.php` |
| `/2026/05/14/post-title/` | `single.php` |
| `/category/games/` | `archive.php` |
| `/tag/ragnarok/` | `archive.php` |
| `/author/finallf/` | `author.php` |
| `/?s=ragnarok` | `search.php` |
| `/url-inexistente` | `404.php` |

> Você pode criar templates ainda mais específicos como `single-post.php`, `category-games.php`, `archive-noticias.php`, `tag-ragnarok.php` — o tema atual não usa, mas são possibilidades de extensão.
