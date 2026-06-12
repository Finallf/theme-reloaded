# 07 — Recursos Especiais

Documentação aprofundada das features mais "robustas" do tema.

---

## 📝 Markdown

Habilitado via opção `markdown_enabled` no painel (default: ✅).

### Como funciona

- A biblioteca **Parsedown** (de Erusev) está vendored em `lib/Parsedown.php`
- `inc/mod-performance.php` (ou `mod-general.php`, dependendo de onde foi colocado) hooka no filtro `the_content`
- Quando o post é renderizado, o conteúdo é processado por Parsedown ANTES dos filtros padrão do WP

### Sintaxe suportada

Tudo o que Parsedown suporta:
- Headings (`# H1`, `## H2`, etc.)
- Bold (`**texto**`), italic (`_texto_`)
- Listas ordenadas e não ordenadas (com aninhamento)
- Code blocks (` ``` `) com syntax highlighting via Prism
- Inline code (`` `código` ``)
- Links (`[texto](url)`)
- Imagens (`![alt](url)`)
- Tables (com alinhamento via `|---|`)
- Blockquotes (`> texto`)
- Horizontal rules (`---`)
- HTML embarcado (passes through)

### Extensões customizadas (style)

O tema estiliza estes patterns adicionais:

#### Alerts estilo GitHub
```markdown
> [!NOTE] Texto da nota
> [!TIP] Dica útil
> [!WARNING] Aviso importante
> [!IMPORTANT] Crítico
> [!CAUTION] Cuidado!
```

Renderizam com cor + ícone Octicon SVG matching o estilo do GitHub. Ver `sass/components/_markdown.scss` (~linha 240+).

#### Tabelas com estilo GitHub
- Borda escura no dark mode
- Borda clara no light mode
- Zebra (linhas alternadas)
- Headers com fundo levemente destacado

---

## 🎨 Prism.js — Syntax Highlighting

Vendored em `lib/prism.js`. Habilitado via opção `prism_js` (default: ✅).

### Carregamento condicional

Carrega **apenas em singles** (não na home, archive, etc.) pra economizar requests.

### Plugins ativos

- **Line Numbers** — adicionado automaticamente via JS em todos os `<pre>`
- **Toolbar** — barra com nome da linguagem + botão "Copy"
- **Copy to Clipboard Button** — botão funcional de copiar código

### Linguagens incluídas

Bundle padrão do Prism — cobre as ~30 mais comuns (JavaScript, PHP, Python, CSS, HTML, JSON, Bash, SQL, etc.).

### Customização visual

`sass/components/_prism.scss` overrida:
- Fonte → JetBrains Mono (via `$font-mono`)
- Background → `var(--rd-deeper)` (dark fixo, mas com override pra light em `_variables.scss`)
- Cores das tokens (keyword, string, comment, etc.)
- Border radius 5px (regra de ouro do tema)

---

## 🎬 Facades de iframe (YouTube/Discord)

Habilitado para YouTube via toggle no painel (Performance): `facade_youtube` (default ✅). O facade do Discord agora é um checkbox no próprio widget **"ReloadeD: Discord"** (default ligado).

### Por que?

iframes são **pesados** pro carregamento inicial:
- YouTube embed: ~300KB+ de assets carregados imediatamente
- Discord widget: ~100KB+

Com facades, substituímos o iframe por uma **div estática leve** (imagem + ícone) que só vira iframe real **quando o usuário clica**.

### YouTube

Hook em `embed_oembed_html` substitui iframes do YT por:

```html
<div class="rd-facade" data-type="youtube" data-id="VIDEO_ID" style="position:relative; cursor:pointer;">
    <img src="https://img.youtube.com/vi/VIDEO_ID/sddefault.jpg" alt="Video cover" loading="lazy">
    <div class="play-button"><svg>...</svg></div>
</div>
```

JS em `navigation.js`:
```js
facade.addEventListener('click', function () {
    const iframeUrl = `https://www.youtube.com/embed/${id}?autoplay=1&rel=0`;
    this.innerHTML = `<iframe src="${iframeUrl}" ...></iframe>`;
});
```

> O autoplay funciona porque o click do usuário é uma "user gesture" (políticas modernas de browser).

### Discord

Diferente do YT, o bloco Discord é renderizado pelo `rd_get_discord_widget_html()` (via `RD_Discord_Widget`) em vez de oembed. Mesma lógica:
- Com facade: div com SVG do Discord + logo do site (clicável)
- Sem facade: `<iframe src="https://ptb.discord.com/widget?id=...&theme=dark">` direto

### Persistência (Discord)

Pra Discord, o estado "expandido" persiste em `sessionStorage`:
```js
const isDiscordOpen = sessionStorage.getItem('rd_discord_open');
if (type === 'discord' && isDiscordOpen === 'true') {
    // Já abre direto na próxima página da mesma sessão
}
```

Razão: usuário que já abriu o widget uma vez quer continuar vendo a comunidade durante a navegação.

---

## 🖼️ Next-gen Image Formats (WebP/AVIF)

Habilitado via `enable_next_gen_images` no painel (default: ✅). Detalhes técnicos em [04 — Módulos PHP — mod-image-formats](04-modulos-php.md#-incmod-image-formatsphp--webpavif-next-gen-image-delivery).

### Como funciona

1. **Upload** — você manda um JPEG/PNG/WebP via Media Library. WP gera todos os tamanhos (full, thumbnail, medium, medium_large, large + `rd-micro`, `rd-popular-thumb`, `rd-card-half`, `rd-card`, `rd-card-wide`, `rd-full-banner`, `rd-qr`)
2. **Hook automático** (`wp_generate_attachment_metadata`) — pra cada tamanho gerado, o módulo cria WebP e/ou AVIF ao lado (preferência: Imagick; fallback: GD — forçado pra AVIF de fontes com alpha). Quality do painel; AVIF em escala derivada (valor−20, piso 45)
3. **Render no template** — qualquer chamada a `wp_get_attachment_image()` (featured image, post-card, gallery) e qualquer `<img>` do conteúdo tem o `<img>` envolvido em `<picture>` com `<source>` pra cada formato existente, **com srcset responsivo espelhado + `sizes`**
4. **Browser decide** — pega o primeiro `<source>` que entender (AVIF se Chrome 85+/Firefox 86+/Safari 16+, WebP se outro browser moderno, fallback `<img>` pra browsers antigos) e o candidato do tamanho certo pro slot

### Dependências de servidor (auto-detectadas)

| Componente | Função |
|------------|--------|
| **Imagick + ImageMagick com WEBP/AVIF** | Preferencial (qualidade superior) |
| **GD com WebP** (PHP 7.1+) | Fallback de WebP |
| **GD com AVIF** (PHP 8.1+) | Fallback de AVIF |

Se nenhum dos dois tem WebP/AVIF, o módulo fica dormente — nada quebra, originais continuam funcionando. O painel mostra status auto-detectado em **Performance → Next-gen Image Formats**.

### Format Mode (`image_format_mode`)

Select com 3 opções (nesta ordem):

| Modo | Browser support | Tamanho relativo a JPEG | Espaço em disco |
|------|----------------|-------------------------|-----------------|
| `avif` (default) | ~80% (Chrome 85+/FF 86+/Safari 16+) | -50% | 2x (original + AVIF) |
| `webp` | ~95% (Chrome/Firefox/Safari/Edge) | -30% | 2x (original + WebP) |
| `both` | Universal (chain de fallbacks) | AVIF -50% / WebP -30% | **3x** (original + WebP + AVIF) |

### Regeneração de imagens existentes

Pra Media Library já populada antes de ativar o módulo:

1. Painel → Performance → Next-gen Image Formats → **Start regeneration**
2. Confirma o total de imagens
3. Loop AJAX em chunks de 10 attachments, com progress bar percentual
4. Reusa a mesma lógica do hook on-upload — zero duplicação de código

Pra Media Library grande, pode levar minutos. Se travar (timeout, network), basta clicar de novo — vai retomar de onde parou (offset baseado em ID).

### Cobertura

✅ **Featured images, post thumbnails, galleries, custom logo** — tudo que passa por `wp_get_attachment_image()`

⏳ **NÃO cobre** imagens inline no conteúdo do post (bloco Gutenberg `core/image`). Essas vêm direto como `<img>` no HTML do `the_content()`. Pra cobrir, Fase 2 com filter `the_content` + parsing seguro via DOMDocument.

### Originais sempre preservados

Os JPEG/PNG **nunca são deletados nem alterados**. WebP/AVIF são acréscimos. Se você desativar o módulo um dia, o site continua funcionando — só perde o ganho de compressão.

### Qualidade unificada com o painel

A opção **"Qualidade das Imagens (%)"** em **Recursos Gerais** (`jpeg_quality`) é a fonte única de verdade pra qualidade de TODOS os formatos do tema:

- **JPEG** — `mod-general.php` engata em 4 filtros do WP core (`jpeg_quality`, `webp_quality`, `avif_quality`, `wp_editor_set_quality`) — aplicado quando o WP gera os sizes a partir do upload
- **WebP/AVIF do nosso módulo** — `rd_img_get_quality()` lê a mesma key e passa pro Imagick/GD na conversão

Mudou o painel pra 85%? Todos os formatos passam pra 85% nas próximas regenerações. Mudou pra 70%? Idem. Coerência total entre formatos.

Pra aplicar a nova qualidade em imagens já existentes, clique **Start regeneration** no painel — re-cropa os sizes (com nova qualidade nos JPEGs) + regenera WebP/AVIF (com a mesma qualidade).

---

## 🛠️ Modo Manutenção

Habilitado via `maintenance_mode` no painel (default: ❌).

### Comportamento

Quando ativo:
- **Visitantes não logados** veem tela 503 com mensagem customizada
- **Admin logado** continua acessando normalmente o site E o admin
- **Devs com senha** podem desbloquear via URL secreta

### URL secreta de dev

`https://seusite.com/?rd_maint_login`

Mostra um form de senha. Se a senha bater com o hash salvo (`maintenance_pass`):
- Cria cookie `rd_maint_dev_pass` válido por 24h
- Próximas requests do mesmo browser/cookie passam direto

### Rate limiting

Pra evitar brute force, há limit por IP via WP transients:
- Após N falhas, IP fica bloqueado por X minutos
- Mensagem "Too many attempts. Please wait a few minutes."

### Customização visual

A tela de manutenção é renderizada via `wp_die()` com HTML inline (CSS embutido). Estilo:
- Logo do site (vem de `assets/img/logo-reloaded-panel.webp`)
- Pulse animation no texto
- Mensagem do admin (com `%s` substituído por `get_bloginfo('name')`)

---

## 👁️ Sistema de Views por Post

Habilitado via `enable_views_tracking` no painel (default: ✅).

### Arquitetura

- **Storage**: 2 post metas
  - `_rd_post_views` (int) — contador all-time
  - `_rd_post_views_log` (array de timestamps) — pra queries por janela
- **Endpoint**: AJAX `rd_track_view` (ambos `wp_ajax_*` e `wp_ajax_nopriv_*`)
- **Frontend**: `assets/js/views-tracker.js` (carregado só em singles)

### Anti-spam

| Filtro | Lógica |
|--------|--------|
| **Admins** | `current_user_can('edit_posts')` retorna early |
| **Bots** | Match contra lista de UA conhecidos (Googlebot, Bingbot, ClaudeBot, etc.) |
| **Dedup por IP** | Transient `rd_view_{md5(post_id_ip)}` por 30 min |
| **Status do post** | Só conta `publish` |
| **Nonce** | Validação obrigatória (`wp_verify_nonce`) |

### Detecção de IP

A função `rd_get_client_ip()` em `inc/core.php` é compartilhada entre views, manutenção e CSP. Modelo de confiança:

1. **`REMOTE_ADDR` é a fonte de verdade** — vem do TCP, atacante não falsifica (exigiria IP spoofing real, que não funciona em conexão TCP estabelecida)
2. **`CF-Connecting-IP` e `X-Forwarded-For` só são confiados** quando `REMOTE_ADDR` está numa faixa de proxy reconhecida:
   - **Cloudflare hardcoded** (15 ranges IPv4 + 7 IPv6, da [lista oficial](https://www.cloudflare.com/ips/))
   - **Ranges custom** do painel (**Segurança → Trusted Proxy IPs**, CIDR um por linha — pra Nginx-front, AWS ALB, Sucuri, BunnyCDN etc.)
3. **Fallback final:** `REMOTE_ADDR` cru

> **Por que isso importa:** sem essa validação, um atacante podia mandar `CF-Connecting-IP: 1.1.1.1`, depois `1.1.1.2`, depois `1.1.1.3`... — cada "IP novo" zerava o contador de tentativas, viabilizando brute-force da senha de dev e inflação artificial de views. A função `rd_get_client_ip()` ignora esses headers a menos que o request realmente venha de um proxy conhecido.

### API pública

```php
// Total de views de um post
$total = rd_get_post_views( $post_id );

// Janela específica (day, week, month, year)
$views_semana = rd_get_post_views( $post_id, 'week' );

// Posts mais populares (all-time)
$query = rd_get_popular_posts( 5 );
while ( $query->have_posts() ) {
    $query->the_post();
    // ...
}

// Formatação respeitando config do painel (full vs compact)
echo rd_format_views_number( 1234 );   // "1.234" (modo full) ou "1.2k" (compact)
echo rd_format_views_number( 1234567 ); // "1.234.567" ou "1.2M"
```

### Formato de exibição (full vs compact)

Config `views_number_format` no painel (Recursos Gerais → Views Display Format):

- **Full (default):** `number_format_i18n()` — número exato (`1.234`)
- **Compact:** estilo redes sociais (`1.2k`, `10k`, `1.2M`). Algoritmo usa `floor` (truncate) — `1.999` vira `1.9k`, nunca `2k` enganoso. Decimal só pra primeira faixa de cada magnitude (`1.2k`/`9.9k` mas `10k`/`99k`/`999k`)

Aplicado **só no frontend** (cards/single via `rd_get_formatted_views()`). Admin (coluna posts + painel Statistics) sempre usa formato exato.

### Coluna no admin

Coluna "👁️ Views" na lista de posts (sortável). Permite ordenar posts por popularidade.

### Limpeza automática

`rd_views_increment()` limpa entries do log mais antigas que `RD_VIEWS_LOG_RETENTION` (1 ano) toda vez que um view é registrado. Evita o array crescer infinitamente no banco.

---

## 🔍 Sistema de Busca Multi-Layout

Documentado em detalhes em [04 — Módulos PHP — mod-search](04-modulos-php.md#-incmod-searchphp--sistema-de-busca-multi-layout).

### TL;DR

- 4 layouts disponíveis: **Grid, Vertical, Compact, Google**
- Admin escolhe quais ficam ativos por default no painel
- Visitor pode toggle individual via chips no header da busca
- Resultados são distribuídos via algoritmo entre os layouts ativos
- Toggle muda os resultados via AJAX (sem reload em page 1)
- Compact age como **rede de segurança** se admin/visitor desligar tudo

### Arquivos envolvidos

- `inc/mod-search.php` — algoritmo + AJAX handler + helpers
- `inc/post-card.php` — renderer de cada layout
- `search.php` — template usando `rd_search_distribute_posts()` + `rd_render_distribution()`
- `assets/js/navigation.js` — toggle dos chips + AJAX
- `sass/components/_search.scss` — visual dos chips + 4 layouts

---

## 🏷️ Categoria Principal

Habilitado via `enable_primary_category` no painel (default: ✅).

### Problema que resolve

Quando um post pertence a 2+ categorias, o WordPress destaca **todos** os itens do menu correspondentes. Isso confunde o usuário (qual é a categoria "real" deste post?).

### Solução

- Meta box no editor de post: dropdown "Categoria Principal" mostrando as categorias atribuídas
- Opção "Auto (primeira categoria)" como fallback automático
- Filtro `wp_nav_menu_objects` remove `current-*` classes de todos os menu items menos o da primária e seus ancestrais no menu

### Implementação

- Meta box: `inc/mod-general.php` (no callback `rd_post_options_callback`)
- Filtro: `inc/mod-menu.php` (`rd_filter_menu_primary_category`)
- Storage: post meta `_rd_primary_category` (int — term_id)

### Helper público

```php
$primary_id = rd_get_primary_category_id( get_the_ID() );
// Pode usar pra breadcrumbs, related posts, etc.
```

---

## 🎠 Carrossel de Destaques

Vitrine editorial full-width entre o header e o conteúdo da home (página 1). Liga em **General → Featured Carousel** (ou pelo card do Dashboard). Curadoria pelo recurso nativo do WP: marca os posts com **"Fixar no topo do blog"** (★) e eles entram no carrossel (mais recentes primeiro; sem fixados, caem os últimos posts). Largura limitada ao container do portal (1440px). Detalhes técnicos em [04 — Módulos PHP](04-modulos-php.md#-incmod-carouselphp--carrossel-de-destaques).

---

## 💝 Sistema de Doações

Configurado no widget **"ReloadeD: Support the Project"** (`RD_Support_Widget`, Aparência → Widgets) — todas as opções ficam no próprio widget, não mais no painel. O bloco "Apoie o Projeto" aparece **onde você colocar o widget** (Main Sidebar ou Footer), na ordem que definir.

### Itens suportados (campos do widget)

| Item | Campo |
|------|-------|
| **Título (heading)** | `title` — texto do `<h3>`; vazio = "Support the Project" (padrão) |
| **GitHub Sponsors** | URL completa em `github_sponsors` |
| **PayPal** | URL em `paypal_url` + QR code (seletor de mídia ou URL) em `paypal_qrcode` |
| **PIX (link)** | URL em `pix_url` (link copia-cola se o banco fornecer) |
| **PIX (QR)** | Imagem em `pix_qrcode` (seletor de mídia ou URL) |
| **PIX (chave texto)** | Texto em `pix_chave` — renderiza com botão "Copiar chave" funcional via JS |

Cada campo vazio remove o item do bloco. Se **nenhum** método de doação for preenchido, o widget se oculta por completo (nem o wrapper aparece).

### Botão "Copiar chave PIX"

Implementado declarativamente via helper genérico `data-rd-copy`:

```html
<button class="rd-copy-btn" data-rd-copy="{chave-pix}" ...>
    <span class="rd-copy-text">{chave-pix}</span>
</button>
```

O handler em `navigation.js` faz o resto: copia pra clipboard, troca o texto do `.rd-copy-text` por "Copiado!" (via `reloaded_i18n.copied`), adiciona classe `.copied` no botão, e reverte tudo depois de 2 segundos.

---

## 🔥 Widget "Most Read" (Popular Posts)

**Primeiro widget WP nativo do tema** — registrado em `mod-popular-widget.php` como `RD_Popular_Posts_Widget extends WP_Widget`. Aparece em **Aparência → Widgets** sob o nome "ReloadeD: Popular Posts" e pode ser arrastado pra qualquer sidebar registrada.

### Distinção vs blocos hardcoded do `sidebar.php`

A sidebar do tema renderiza:

```php
1. dynamic_sidebar('sidebar-1')    ← WIDGETS WP NATIVOS (Most Read, Latest Video, Support, Discord, Ad/Banner, etc.)
2. rd_render_ad_sidebar_sticky()   ← bloco hardcoded (fixo no rodapé da sidebar — único restante)
```

Apoie o Projeto (`RD_Support_Widget`), Discord (`RD_Discord_Widget`), os anúncios de sidebar (`RD_Ad_Widget`) e as Redes Sociais (`RD_Social_Widget`, grade "Siga-nos" 2 colunas — URLs vêm do painel, o widget só marca quais exibir) são widgets, posicionados livremente. Só o **banner sticky** segue hardcoded (posição `sticky` especial no rodapé da sidebar). Vantagem do modelo widget: o usuário ordena/remove pela UI nativa, sem editar código.

### Configuração

| Campo | Tipo | Default | Range |
|-------|------|---------|-------|
| **Title** | text | `Most Read` | livre |
| **Time window** | select | `all` | `all` / `year` / `month` / `week` |
| **Number of posts** | select | `5` | `3` a `15` |

### Output

Lista vertical com thumb 16/9 (100x56) à esquerda, título 2 linhas com ellipsis no centro, ícone 👁️ + número flutuando no canto inferior direito (mesmo padrão das `.entry-meta` dos cards). Hover: título vira azul + thumb dá zoom suave.

Empty state ("No popular posts yet.") quando a janela escolhida não tem resultados. Posts sem featured image ganham gradient sutil de fallback (evita imagem quebrada).

### Dependências

- `rd_stats_top_posts_by_views($limit, $window)` em `mod-stats.php` — cache transient 1h
- `rd_format_views_number()` em `mod-views.php` — respeita config full/compact

### CSS

Estilos próprios em `_popular-widget.scss`. O **card glass shell** (fundo, borda, padding, shadow, h2 com linha azul) vem do `_sidebar.scss` via seletor combinado `.widget_block, .widget_rd_popular_posts` — pra inscrever futuros widgets clássicos do tema nesse shell, basta adicionar o classname ao seletor.

---

## 👤 Sistema multi-redator

Quando o portal sair do modo solo-author e tiver múltiplos redatores, **zero trabalho extra é necessário**. A infra é automática:

### URL automática por redator

Cada user com role que publica posts (Author, Editor, Administrator) ganha `/author/{username}/` automaticamente — renderizado pelo `author.php`. Lista os posts dele com paginação.

### Ícones sociais por user

`mod-social.php` registra um filter `user_contactmethods` que adiciona 8 campos de URL social em **Usuários → Perfil → Informações de contato** (`social_discord`, `social_telegram`, `social_whatsapp`, `social_youtube`, `social_instagram`, `social_steam`, `social_twitter`, `social_facebook`). Cada redator preenche os seus.

O helper `rd_render_social_icons( $user_id )` puxa os meta do user específico em vez das URLs globais do portal. No `author.php` os ícones aparecem ao lado do `<h1>` do nome — visualmente igual ao padrão do template-about.

### Schema.org Person + E-E-A-T

`author.php` injeta JSON-LD Person inline:

```json
{
  "@context": "https://schema.org",
  "@type": "Person",
  "@id": "https://site.com/author/joao/#person",
  "name": "Display Name",
  "image": "url-gravatar-240px",
  "url": "https://site.com/author/joao/",
  "description": "Bio do user",
  "sameAs": ["url-twitter", "url-github", "..."]
}
```

`mod-seo.php` injeta o **mesmo `@id`** no campo `author` (Person) dentro do `Article` schema de cada single post. Google amarra os dois schemas como o MESMO Person — alimenta **E-E-A-T** (Experience, Expertise, Authoritativeness, Trustworthiness) ligando "esse Article foi escrito pelo autor desse perfil".

### Defensiva — `name` nunca vazio

`display_name` pode estar vazio em users seedados/importados. Fallback chain: `display_name → user_nicename → user_login`. Evita "Item sem nome" no Google Rich Results.

### Diferença entre `template-about.php` e `author.php`

| Aspecto | `template-about.php` | `author.php` |
|---|---|---|
| **O que é** | Página institucional do site | Archive de posts do autor |
| **Quantos por site** | 1 (institucional única) | N (1 por user que publica) |
| **Hero** | Hardcoded com avatar + nome + bio + sociais do PORTAL | Header similar mas com sociais PESSOAIS do user |
| **Conteúdo principal** | Editor (bio longa, projetos, fotos) | Lista de posts do autor (automática) |
| **Schema.org** | `Person` (avatar+bio do autor da página) | `Person` (avatar+bio do user queried) + posts |
| **Quando usar** | "Sobre o ReloadeD" institucional | "Posts publicados por João" |

Veja [Copy to Clipboard (genérico)](06-frontend-scss-js.md#copy-to-clipboard) pra usar a função em outros lugares do tema.
