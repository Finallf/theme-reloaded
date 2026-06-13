# 04 — Módulos PHP

Cada arquivo `inc/mod-*.php` encapsula uma feature isolada. Pode ser ligado/desligado independentemente, e a maioria tem uma "opção mestra" no painel pra desativar sem precisar comentar require.

---

## 🛡️ `inc/core.php` — Setup do tema

Não é "módulo de feature", mas a base de tudo. Hooks no `after_setup_theme`:

- Carrega text domain (`load_theme_textdomain('reloaded')`)
- `add_theme_support()` pra HTML5, title-tag, post-thumbnails, custom-logo, align-wide, automatic-feed-links, customize-selective-refresh-widgets, editor-styles, responsive-embeds
- Registra tamanhos de imagem custom — todos com Hard Crop. Cada size existe pra um caso de uso específico no tema:
  - `rd-micro` 150×84 — miniaturas genéricas pra widgets/sidebar (16:9)
  - `rd-popular-thumb` 200×113 — Widget "Most Read" (display 100×56 com DPR 2x retina, 16:9)
  - `rd-card-half` 400×225 — cards do post-grid em viewport intermediário (display ~390×220, 16:9). Aproveita o srcset auto do WP — browser escolhe esse entre `rd-micro` e `rd-card`
  - `rd-card` 600×338 — cards da home/archives/search (16:9)
  - `rd-card-wide` 800×450 — degrau intermediário entre `rd-card` e `rd-full-banner`: slides do carrossel (~630px CSS / mobile DPR 2) e cards hero (~700px) caíam no vão e forçavam o candidato 1200w (PageSpeed: "image larger than needed")
  - `rd-full-banner` 1200×675 — banner top do post single (16:9) + carrossel da home
  - `rd-qr` 240×240 — QR codes de doação na sidebar (1:1, cobre retina sem desperdiçar banda)
- Remove os tamanhos retina nativos não usados (`1536x1536`, `2048x2048`). O `medium_large` (768px) é **mantido de propósito**: é option-based (`medium_large_size_w`), invisível ao `remove_image_size()` — e pra uploads 16:9 o 768×432 dele entra na escada do srcset e serve bem os slots de card ~600-700px (ver comentário no `core.php`)
- Registra 1 menu (`menu-1` Primary). _(O `menu-footer` foi removido em 2026-06-04 — o menu do footer agora é por widget.)_
- Registra 2 sidebars (`sidebar-1` Main, `footer-widget-area`)

Helpers expostos:

- `rd_render_logo()` — custom logo do WP ou fallback de texto com `bloginfo('name')`
- `rd_get_site_logo($size = 'medium')` — **source of truth** pro logo do site em contextos fora do frontend regular (tela manutenção, WSOD, Discord facade, Schema.org). Resolução em 2 níveis: (1) Custom Logo do WP em `Aparência → Personalizar → Identidade do Site`; (2) fallback hardcoded em `assets/img/logo-reloaded-panel.webp` (430×100). Retorna array `{ url, width, height }`. Consumido por `mod-maintenance`, `mod-security`, `mod-integrations` (Discord facade quando `discord_facade_logo` não cadastrado), `mod-seo` (Schema.org Organization logo). **Não usado** em `panel.php` (UI do tema, intencionalmente hardcoded) nem no fallback de OG image em `mod-seo` (Custom Logo geralmente é horizontal, proporção errada pra preview social 1.91:1 — admin deve cadastrar `og_fallback_image` dedicado)
- `rd_asset_version($relative)` — `filemtime()` do arquivo pra cache busting
- `rd_get_client_ip()` — IP real do cliente com proteção contra header spoofing. Valida `REMOTE_ADDR` (vem do TCP, não-spoofável) contra ranges de proxy reconhecidos antes de confiar em `CF-Connecting-IP`/`X-Forwarded-For`. Consumido por `mod-maintenance` (rate-limit da senha de dev), `mod-views` (dedup de views por IP) e `mod-csp` (rate-limit do endpoint de reports)
- `rd_remote_is_trusted_proxy($ip)` — true se `$ip` está numa faixa de proxy reconhecida. Combina lista hardcoded do Cloudflare (15 ranges IPv4 + 7 IPv6 — https://www.cloudflare.com/ips/) com ranges custom do painel (opção `trusted_proxy_ips` na aba Segurança, CIDR um por linha)
- `rd_ip_in_ranges($ip, $ranges)` — match CIDR genérico IPv4/IPv6 via `inet_pton`. Aceita ranges com prefix (`192.168.1.0/24`, `2001:db8::/32`) e IPs únicos (sem `/`). Ranges malformados são pulados em silêncio — função nunca falha por input ruim do admin

---

## 🎛️ `inc/panel.php` — Painel admin

Tudo do painel admin vive aqui:

- `rd_get_default_options()` — **source of truth dos defaults** (DRY entre primeira ativação e migração de keys ausentes)
- `rd_set_default_options()` — popula `rd_settings` na primeira ativação (`after_switch_theme`). Reusa `rd_get_default_options()`
- `rd_migrate_missing_options()` — **migração automática de keys ausentes** (`admin_init`). Compara defaults com `rd_settings` salvo via `array_diff_key`, faz merge e salva quando detecta keys novas. Fast path quando nada falta. **Por quê:** sem isso, opções novas adicionadas DEPOIS da primeira ativação ficariam silenciosamente desligadas (`rd_get_option_bool` retornaria `false` da assinatura padrão, não do default do tema). Resolve o problema de "atualizei o tema com nova feature mas ela não aparece como ON no painel"
- `rd_get_option()`, `rd_get_option_bool()` — readers seguros com default
- `rd_add_admin_menu()` — adiciona o menu top-level "ReloadeD"
- `rd_options_render()` — UI HTML com 10 abas (loop `<a>` + `<div class="rd-tab-content">`)
- `rd_register_settings()` — registra todas as seções e fields via Settings API
- `rd_master_field_cb()` — callback genérico que renderiza checkbox/text/number/textarea/select/media baseado em `$args['type']`

Pra adicionar uma nova opção no painel:

1. Default em `rd_get_default_options()` (uma única função pra editar)
2. Field em `rd_register_settings()` com `add_settings_field(...)`
3. Lê em qualquer lugar com `rd_get_option('chave')` ou `rd_get_option_bool('chave')`
4. **Próximo page load do admin** já popula a key no `rd_settings` automaticamente via migração — sem reinstalar tema

---

## 📝 `inc/mod-general.php` — Recursos gerais

| Função / Hook | O que faz |
|---------------|-----------|
| Meta box "Post Options (ReloadeD)" | Hospeda **Hide Featured Image** + **Primary Category** + **Overline** (cada um gateado pela respectiva opção no painel). O meta box só aparece se ao menos uma das 3 features estiver ativa |
| `rd_save_post_options()` | Save handler com nonce check; salva `_rd_hide_thumbnail`, `_rd_primary_category` e `_rd_post_kicker` |
| `rd_render_post_overline($post_id, $context)` | Helper que imprime o chapéu do post (`entry-overline-{single,card}`) acima do título. Chamado de `single.php` e `index.php`. Sai cedo se a feature global está off ou se o post não tem overline preenchido |
| `rd_add_sidebar_position_body_class()` | Filtra `body_class` adicionando `rd-sidebar-left` quando `sidebar_position = 'left'`. SCSS faz `flex-direction: row-reverse` no container do `.site-main` em desktop |
| `rd_custom_image_quality()` | Filtra `jpeg_quality`, `webp_quality`, `avif_quality`, `wp_editor_set_quality` |
| `rd_custom_excerpt_length()` | Filtra `excerpt_length` (prioridade 999) com valor configurável no painel; default 55 (padrão WP) |
| `rd_fix_comment_form_labels()` | A11y nos comments — labels visíveis + autocomplete |
| `rd_custom_excerpt_more()` | Customiza texto do "Continue Reading" |
| `rd_change_latest_comments_text()` | Modifica o "em" do widget Latest Comments via filtro `gettext` |

---

## 🍪 `inc/mod-privacy.php` — LGPD com consent granular

Sistema completo de consent por categoria (LGPD/GDPR-compliant). Substitui o banner antigo "1 botão = tudo" por banner expansível com 3 categorias.

### Categorias

- **necessary** — sempre on (cookies do tema, banner, login WP). Não pode ser rejeitado
- **analytics** — gate pra GA, Microsoft Clarity, Plausible, Umami
- **marketing** — gate pra Facebook Pixel, TikTok Pixel

### Storage

Cookie único `rd_lgpd_consent` em JSON, 365 dias, versionado:

```json
{
    "necessary": true,
    "analytics": false,
    "marketing": false,
    "date": "2026-05-16",
    "version": 1
}
```

Cookie legado `rd_lgpd_accepted=true` migra automaticamente como "tudo aceito" pra não re-prompt usuários existentes.

### Helpers públicos

- `rd_lgpd_get_consent(): array` — lê + faz cache static do consent na request
- `rd_consent_given(string $category): bool` — gate principal. Retorna `true` quando `enable_lgpd = OFF` (sistema dormente)

### Banner

Renderizado sempre que `enable_lgpd = ON`, com classe `rd-lgpd-hidden` quando user já fez escolha (DOM presente pra reabertura instantânea pelo link do footer). 2 modos visuais via `data-state="compact|expanded"`. CSS faz transição via `max-height + opacity`.

3 botões: `Reject all` / `Customize` / `Accept all`. Reject visualmente equivalente a Accept (requisito LGPD). Necessary toggle locked on.

### Soft reload

Após salvar consent, JS dispara `location.reload()` em 350ms (depois da animação de fade-out) pra PHP re-avaliar gates dos scripts gated.

---

## 🔌 `inc/mod-integrations.php` — Tracking, Analytics, Verificações

Múltiplos hooks no `wp_head` pra injetar scripts e metas de plataformas externas. Cada um conditional na sua opção do painel + gated pelo consent quando aplicável.

### Verificação de propriedade (sem consent)

Priority 1 no wp_head — convenção de ficar no topo:
- **Google Search Console** (`google_site_verification`) → `<meta name="google-site-verification">`
- **Bing Webmaster** (`bing_site_verification`) → `<meta name="msvalidate.01">`

Não são tracking, só metadata pro crawler — não passam por `rd_consent_given()`.

### Analytics (gated por `analytics`)

Priority 5 no wp_head:
- **Google Analytics** (`ga_id`): `gtag.js` quando ID válido (regex `^(G-|UA-)[A-Z0-9-]+$`)
- **Microsoft Clarity** (`clarity_id`): heatmaps + session recordings
- **Plausible** (`plausible_domain`): script cloud privacy-friendly
- **Umami** (`umami_website_id` + `umami_script_url`): script self-hosted, precisa dos 2 campos + URL válida via `filter_var FILTER_VALIDATE_URL`

### Marketing (gated por `marketing`)

- **Facebook Pixel** (`facebook_pixel_id`): script Meta + `<noscript><img>` fallback
- **TikTok Pixel** (`tiktok_pixel_id`): script TikTok Ads

### Discord Widget

- `rd_get_discord_widget_html( array $data )` — **retorna** o HTML do bloco a partir do array do widget (`discord_id`, `facade`, `facade_logo`); não lê mais opções do painel
- Facade: div estática com SVG do Discord + logo do site, sem carregar iframe até o user clicar
- Iframe direto: `<iframe loading="lazy" src="https://ptb.discord.com/widget?id=...&theme=dark">`
- Config + posicionamento no **`RD_Discord_Widget`** (`inc/class-rd-discord-widget.php`). O CSP libera o `frame-src` via `is_active_widget('rd_discord')`

### Ad Global

- `ad_global`: insere HTML/JS livre no `<head>` se opção preenchida (não gated — admin assume responsabilidade)

---

## ⚡ `inc/mod-performance.php` — Otimizações

### Limpeza WP

- **Disable emojis** (`disable_emojis`): remove o script de emojis do WP
- **Hide WP version** (`hide_wp_ver`): filtra `the_generator` pra retornar string vazia
- **Disable Gutenberg CSS** (`disable_gutenberg_css`): dequeue de `global-styles`, `wp-block-library`, `wp-block-library-theme`, `classic-theme-styles`

### Facades

- **YouTube Facade** (`facade_youtube`): hook em `embed_oembed_html` substitui iframes YT por `<div class="rd-facade">` com thumb. Swap pra iframe real ao clicar feito em `navigation.js`
  - **Timestamp preservation**: helper `rd_youtube_parse_timestamp($t)` parseia o parâmetro de tempo da URL original (4 formatos aceitos pelo YouTube: `30` puro, `30s` com sufixo, `1m30s`, `1h2m30s`) e devolve inteiro em segundos. Tenta `?t=` primeiro (formato user-facing das URLs de share), cai pra `?start=N` (embed). Injeta `data-t="N"` no `<div class="rd-facade">` quando > 0. O click handler em `navigation.js` lê o atributo e propaga como `&start=N` na URL do iframe — vídeo abre no tempo correto. Sem timestamp na URL = atributo não renderiza = comportamento idêntico ao anterior
  - **Helpers reusáveis**: a extração de ID e a montagem do markup foram fatoradas em 3 funções — `rd_youtube_extract_id($text)` (acha o 1º ID de vídeo num texto, seja uma URL única ou um `post_content` inteiro), `rd_youtube_cover_html($id)` (thumbnail + botão play) e `rd_youtube_facade_markup($id, $t)` (wrapper `.rd-facade` completo). O filtro oEmbed e o widget "Latest Video" (sidebar/footer) compartilham os mesmos helpers — saída do facade inalterada

### Database / Server

- **Post Revisions Limit** (`post_revisions_limit`): filter `wp_revisions_to_keep` clamping 0-50. Default 5. `0` = nenhuma revisão guardada
- **Optimize Heartbeat** (`optimize_heartbeat`): quando ON, deregistra `heartbeat` no frontend + bumpa interval admin não-editor pra 120s (WP max). Editor mantém 15s pra preservar autosave

### Network hints

- **DNS Prefetch + Preconnect**: filter `wp_resource_hints` injeta hints condicionais por feature:
  - `preconnect` pra `www.youtube.com` + `youtube-nocookie.com` (quando facade YT ON) — DNS + TLS handshake antecipado
  - `dns-prefetch` pra `img.youtube.com`, `i.ytimg.com` (CDN thumbs), `ptb.discord.com` (quando widget ON), `googletagmanager.com` (quando GA configurado), `secure.gravatar.com` (sempre)

### Critical fonts

- **Preload Critical Fonts** (`preload_critical_fonts`, default ON): injeta `<link rel="preload" as="font" type="font/woff2" crossorigin>` pra **2 arquivos** no `wp_head` priority 1 — **Inter variável** (47 KB, todos os pesos do corpo 400-700) e **Poppins variável** (17,6 KB, todos os pesos de heading 100-900). A migração pra fontes variáveis (2026-06-12) eliminou o jogo de adivinhar quais pesos preloadar: a lista antiga tinha 5 estáticas (~173 KB) e ainda deixava pesos de fora. Fora do preload de propósito: italics (decorativas/sintetizadas) e jetbrains-mono (code blocks, only posts). Com os fallbacks de métrica casada, fonte que chega tarde não desloca nada — preload aqui é antecipação de FOUT, não correção de CLS
- **Fontes variáveis (inventário 2026-06-12):** `inter-variable.woff2` (Google latin slice v20, eixo 400-700), `poppins-variable.woff2` (**build próprio do Finallf** — Google não distribui Poppins variável; eixo 100-900, subset latin validado em `tools/fonts/font.html`), `jetbrains-mono-variable.woff2` (Google latin v24, eixo 100-800). Itálicas de Poppins/JetBrains continuam estáticas: itálica real é desenho separado, e no caso da JetBrains a itálica variável (32 KB) pesava MAIS que a estática 400 (25 KB) que é a única usada. Total: 14 arquivos/366 KB → **5 arquivos/132 KB (−64%)**
- **Fallbacks com métricas casadas** (`_variables.scss`): faces sintéticas `Inter-fallback`/`Poppins-fallback` (`src: local('Arial')` + `size-adjust`/`ascent`/`descent`/`line-gap-override`, valores da calibração fontaine) entram nos stacks logo após a fonte real. Com `font-display: swap`, o texto pinta no Arial **redimensionado pra ocupar as mesmas caixas** da fonte final — o swap fica geometricamente invisível (curou o CLS residual do corpo/headings na carga de fonte). Browser sem `size-adjust` ignora os descritores — comportamento idêntico ao anterior. *(O banner LGPD, que era o maior offender de CLS isolado, foi resolvido por outra via: migrou pra `system-ui` em `_lgpd.scss`, removendo a dependência de webfont por completo — ver doc 06.)*

### LCP image preload

- **`rd_preload_lcp_image()`** (`wp_head` prio 2): o tema sabe server-side qual é o elemento LCP — slide 1 do carrossel na home, banner destacado nos singles — e anuncia no `<head>` via `<link rel="preload" as="image" imagesrcset imagesizes fetchpriority="high">`. Ataca o "atraso no carregamento de recursos" do breakdown de LCP (880ms mobile / 1.040ms desktop medidos antes). **Regras de correção** (ambas evitam download duplicado): (1) `imagesrcset`/`imagesizes` espelham EXATAMENTE o que o `<img>`/`<source>` renderizado carrega — mesmas APIs de srcset do WP + mesmo mapeamento next-gen + a mesma string de sizes (`rd_carousel_img_sizes()` é a fonte única compartilhada com o renderer do carrossel); (2) `type="image/avif"` faz browsers sem suporte pularem o preload inteiro.

### Lazy loading

- **`wp_lazy_loading_enabled`** forçado true pra iframes (filter defensivo caso plugin desligue)
- **`rd_strip_thumbnail_auto_sizes()`** (filter `post_thumbnail_html`) — remove o `auto,` que o WP 6.7+/7.0 injeta no `sizes` de todo thumbnail lazy. O keyword `auto` manda o Chrome resolver o candidato pela largura renderizada, mas nas seções 3-col do desktop ele super-resolve e pega o 600w pra slots de ~304px (≈66 KiB), sobrescrevendo o hint hand-tuned `(min-width:769px) 320px` (que mapeia certinho pro 400w). Mobile fica intacto (seções 1-col → `100vw` e `auto` convergem). Os hero/LCP cards são eager, nunca recebem `auto`. Complemento: `add_filter('wp_img_tag_add_auto_sizes', '__return_false')` desliga o auto-sizes em imagens in-content — e isso **preserva** o `sizes="auto"` proposital da capa do facade YouTube (markup cru, não passa por esse filtro).

---

## 🚀 `inc/mod-critical-css.php` — Critical CSS inline + async stylesheet (G8)

Elimina render-blocking do `style.css` principal injetando o CSS above-the-fold inline no `<head>` e carregando o stylesheet completo de forma assíncrona. Ganho medido na sandbox: LCP Mobile **2.9s → 2.3s** (cruzou Core Web Vitals "Good" <2.5s), Performance **95 → 98**. Toggle `inline_critical_css` na aba Performance, **default OFF** (opt-in após gerar os arquivos critical-{template}.css).

### Fluxo de runtime

1. **`wp_head` priority 0** — antes de qualquer outra coisa no head, lê `assets/css/critical-{template}.css` e injeta numa tag `<style id="rd-critical-css">`. Detecção de template via `rd_critical_css_template_key()`:
   - `is_singular('post')` → `single`
   - `is_page()` → `page`
   - `is_search()` → `search`
   - `is_archive() || is_category() || is_tag() || is_author() || is_date() || is_tax()` → `archive`
   - fallback → `home`
2. **Filter `style_loader_tag`** — converte o `<link rel="stylesheet">` do handle `rd-main-style` em:
   ```html
   <link rel="preload" href="..." as="style" onload="this.onload=null;this.rel='stylesheet'">
   <noscript><link rel="stylesheet" href="..."></noscript>
   ```
   Preload assíncrono pra browsers JS-on, fallback síncrono pra browsers JS-off (acessibilidade).

### Graceful degradation

Se `assets/css/critical-{template}.css` não existir pro template atual:
- `rd_critical_css_get_content()` tenta fallback pra `critical-home.css`
- Se nem isso existir, retorna string vazia → módulo não injeta nada → stylesheet carrega síncrono normalmente (sem FOUC, sem quebra)

Esse comportamento permite gerar críticos pro home e adiar outros templates sem travar o feature.

### Tooling de geração

Critical CSS é gerado offline via Node + Puppeteer no `tools/critical/extract.js`. Comandos:

```powershell
npm run critical:home        # só home
npm run critical:single      # só single post
npm run critical:page        # páginas estáticas
npm run critical:archive     # archives (category, tag, author, date)
npm run critical:search      # search results
npm run critical:all         # todos os 5 em sequência (~5min)
```

Output em `assets/css/critical-{template}.css` — versionados no repo. Re-gerar quando mexer em SCSS above-the-fold (header, hero, primeiro card, sidebar widgets) ou estrutura HTML de templates raiz.

### Filosofia preservada

Node + Puppeteer são **devDependencies** locais (em `node_modules/` gitignored). **Produção continua servindo apenas PHP/CSS/JS estático.** Os arquivos `critical-{template}.css` no `assets/css/` são artefatos pré-gerados — o servidor não precisa de Node em runtime.

---

## 🖼️ `inc/mod-image-formats.php` — WebP/AVIF (Next-gen image delivery)

Gera automaticamente versões **WebP** e/ou **AVIF** de cada imagem JPEG/PNG/WebP no upload (todos os tamanhos do WP + tamanhos custom do tema) e entrega via `<picture><source>...<img></picture>` com fallback transparente pro original. Uploads em WebP também ganham gêmeo AVIF (WebP→WebP é no-op).

### Dependências de servidor (auto-detectadas em runtime)

O módulo **degrada graciosamente** — sem dependência forte. Detecta capability via runtime:

| Componente | Pra que serve | Versão mínima |
|------------|--------------|--------------|
| **Imagick** (PHP ext) + **ImageMagick** com WebP/AVIF | Conversão preferencial (qualidade superior) | ImageMagick 6.9+ com `--with-webp` e `libheif` (AVIF). ⚠️ No IM6 o delegate heic/AVIF **ignora** `setImageCompressionQuality` (per-image) e só lê `setCompressionQuality` (global) — descoberto empiricamente em 2026-06-11 (q80 e q45 geravam bytes idênticos); o módulo seta os DOIS, inofensivo no IM7 |
| **GD** com WebP | Fallback de WebP se Imagick indisponível; leitura de uploads WebP | PHP 7.1+ (WebP nativo) |
| **GD** com AVIF | Fallback de AVIF; **engine forçada** pra AVIF de fontes com alpha potencial (PNG, WebP — bug do IM6+libheif com transparência) | PHP 8.1+ (AVIF nativo) |

**Diagnóstico:** o painel mostra o que está disponível no servidor em **Performance → Next-gen Image Formats → Server capabilities**. Se nem Imagick nem GD têm WebP/AVIF, o módulo fica dormente — uploads não são convertidos e o filter `<picture>` passa transparente. Não quebra nada.

**Verificação rápida via terminal:**

```bash
php -r "
echo 'Imagick: ' . (extension_loaded('imagick') ? 'YES' : 'NO') . PHP_EOL;
if (extension_loaded('imagick')) {
    \$formats = (new Imagick())->queryFormats();
    echo 'WebP: ' . (in_array('WEBP', \$formats) ? 'YES' : 'NO') . PHP_EOL;
    echo 'AVIF: ' . (in_array('AVIF', \$formats) ? 'YES' : 'NO') . PHP_EOL;
}
echo 'GD WebP: ' . (function_exists('imagewebp') ? 'YES' : 'NO') . PHP_EOL;
echo 'GD AVIF: ' . (function_exists('imageavif') ? 'YES' : 'NO') . PHP_EOL;
"
```

### Configuração no painel

Aba **Performance → Next-gen Image Formats**:

- **Enable WebP/AVIF Delivery** (`enable_next_gen_images`, default ON) — kill switch geral
- **Format Mode** (`image_format_mode`, default `avif`) — qual formato gerar:
  - `avif` (default) — Só AVIF (~80% browser support, ~50% menor que JPEG, melhor compressão)
  - `webp` — Só WebP (~95% browser support, ~30% menor que JPEG)
  - `both` — AVIF + WebP (~2x espaço extra em disco, máxima compatibilidade do fallback chain)

### Funcionamento

| Componente | Hook | O que faz |
|-----------|------|-----------|
| `rd_img_get_capabilities()` | static cache | Detecta Imagick/GD + WebP/AVIF em cada |
| `rd_img_can_generate($format)` | helper | Boolean — pode gerar WebP ou AVIF? |
| `rd_img_convert($source, $format, $quality = null)` | helper de conversão | Converte 1 arquivo (fonte jpg/jpeg/png/webp). Prefere Imagick, fallback GD — exceto AVIF de PNG/WebP (alpha potencial → direto GD). Quality default por formato via `rd_img_get_quality_for()`. AVIF usa `heic:speed: 6` + seta `setCompressionQuality` global (workaround IM6). Alimenta `rd_img_conversion_stats()` |
| `rd_img_get_quality()` | helper | Lê `jpeg_quality` do painel com clamp 1-100. Default 80 |
| `rd_img_get_quality_for($format)` | helper | **Qualidade por formato:** JPEG/WebP usam o valor do painel direto; **AVIF usa valor−30 com piso 40** (escala 0-100 não é portável entre codecs — AVIF em q de JPEG gera arquivo inchado; −30 calibrado contra o default histórico do libheif que o site sempre serviu sem reclamação). Painel 80 → AVIF 50 |
| `rd_img_conversion_stats($op)` | static counters | Contadores converted/failed por request — alimentam o resumo "Last regeneration" do painel (option `rd_img_last_regen`) e o relatório de falhas no fim do regen |
| `rd_img_generate_on_upload($metadata, $id)` | `wp_generate_attachment_metadata` filter | Itera por TODOS os sizes do attachment (full + thumbnail + medium + medium_large + large + custom sizes do tema) e gera WebP/AVIF de cada um. Fontes WebP geram só o gêmeo AVIF |
| `rd_img_get_variant_url($url, $format)` | helper | Resolve a URL da variante next-gen de UMA URL original (checa extensão, escopo do uploads e existência no disco). Single source of truth dos dois geradores de `<source>` |
| `rd_img_get_nextgen_srcsets($srcset, $fallback)` | helper | **Peça-chave da entrega responsiva:** espelha o `srcset` completo do `<img>` trocando cada candidato pra `.avif`/`.webp` quando o arquivo existe. Antes o `<source>` tinha UMA URL fixa — browser com suporte a AVIF ignorava o srcset inteiro e baixava o tamanho errado (flagrado pelo PageSpeed: AVIF 1200px pra slot de 630px) |
| `rd_img_wrap_in_picture($html, ...)` | `wp_get_attachment_image` filter | Envolve `<img>` em `<picture>` com `<source type="image/avif">` antes de `<source type="image/webp">` antes do `<img>` original — cada `<source>` com srcset espelhado + `sizes` copiado do `<img>`. Browser pega o primeiro `<source>` compatível E o candidato do tamanho certo |
| `rd_img_wrap_content_images($content)` | `the_content` filter (prio 20) | Mesma cobertura pra `<img>` direto no conteúdo (bloco `core/image`, Markdown, HTML cru) via DOMDocument — também com srcset espelhado |
| `rd_img_force_correct_src($attr, $att, $size)` | `wp_get_attachment_image_attributes` filter | **Defensivo.** Em alguns cenários (metadata desatualizada após `add_image_size` novo, comportamento `sizes="auto"` do WP 6.7+) o `<img src>` aponta pro original em vez do size requested. Esse filter detecta o caso (verifica se o arquivo do size existe no filesystem) e força o `src` + `width`/`height` corretos. Fail-safe: se o arquivo realmente não existe, deixa o WP servir o original |
| `rd_img_cleanup_on_delete($id)` | `delete_attachment` action | Apaga WebP/AVIF órfãos quando o attachment é deletado |
| `rd_img_ajax_regenerate()` | `wp_ajax_rd_img_regenerate` | AJAX em chunks com **orçamento de tempo** (metade do `max_execution_time`, cap 20s, piso 5s; `RD_IMG_REGEN_CHUNK = 10` é só o teto da query) — para cedo quando o budget estoura e o JS retoma do ponto exato. Acumula stats de conversão em `rd_img_last_regen` |
| `rd_img_ajax_cleanup_formats()` | `wp_ajax_rd_img_cleanup_formats` | Botão **Remove unused format**: apaga do disco os arquivos do formato NÃO coberto pelo Format Mode atual (trocou `both`→`avif`? os `.webp` órfãos saem). Single-shot, retorna arquivos removidos + KB liberados. Guard de same-path protege os WebP que são originais/sizes nativos |

### Estratégia AVIF antes do WebP no `<picture>` (com srcset espelhado)

```html
<picture>
    <source srcset="foto-400x225.avif 400w, foto-600x338.avif 600w, foto-1200x675.avif 1200w"
            sizes="(min-width: 1025px) 461px, 100vw" type="image/avif">
    <source srcset="foto-400x225.webp 400w, foto-600x338.webp 600w, ..." sizes="..." type="image/webp">
    <img src="foto-600x338.jpg" srcset="foto-400x225.jpg 400w, ..." sizes="..." loading="lazy">
</picture>
```

Browser pega o **primeiro `<source>` compatível** — então:
- Chrome 85+/Firefox 86+/Safari 16+ → pega AVIF (~50% menor) **no tamanho certo pro slot**
- Outros browsers modernos → pegam WebP (~30% menor)
- Browsers antigos → caem no `<img>` original

> ⚠️ **Histórico:** até a v1.7.0 cada `<source>` carregava UMA URL fixa (o tamanho requested), sem candidatos nem `sizes`. Resultado: todo browser moderno ignorava o srcset responsivo do `<img>` e baixava o tamanho fixo — o PageSpeed flagrou 275 KiB de desperdício na home. O espelhamento do srcset (candidatos sem variante next-gen no disco são dropados individualmente) restaurou a seleção por tamanho.

### Regeneração de imagens existentes

O botão **Start regeneration** no painel não regenera apenas WebP/AVIF — desde 2026-05-20 ele chama `wp_generate_attachment_metadata()` (função do WP core) que faz:

1. **Apaga sizes obsoletos** dos attachments
2. **Regenera TODOS os sizes** registrados via `add_image_size` no `core.php` (`rd-micro`, `rd-popular-thumb`, `rd-card-half`, `rd-card`, `rd-card-wide`, `rd-full-banner`, `rd-qr` + thumbnail/medium/medium_large/large nativos do WP)
3. **Atualiza metadata** do attachment no DB
4. Dispara filter `wp_generate_attachment_metadata` no final → aciona `rd_img_generate_on_upload` que gera WebP/AVIF de cada size recém-regenerado

**Resolve em uma volta:** sizes faltantes (após adicionar `add_image_size` novo) + next-gen formats (WebP/AVIF). Antes a função AJAX chamava só `rd_img_generate_on_upload` e gerava apenas next-gen sobre os sizes EXISTENTES — agora também re-cropa os sizes do WP.

**Use o botão após:**
- Ativar o módulo num site com Media Library já populada
- Trocar o Format Mode (`both` ↔ `webp` ↔ `avif`)
- Adicionar/mudar um `add_image_size` no `core.php`
- Trocar a **Image Quality** no painel (o knob agora funciona de verdade pro AVIF — ver workaround IM6 acima)

**UX:**
1. Painel → Performance → Next-gen Image Formats → **Start regeneration**
2. Confirma o processamento (mostra total de attachments JPEG/PNG/WebP)
3. AJAX em chunks com orçamento de tempo (até 10 attachments por request, parando antes se o budget de tempo estourar), com progress bar percentual
4. Cada chunk é uma request HTTP — se travar, basta clicar de novo (offset é recalculado)
5. Pra Media Library grande pode levar minutos (re-cropar é mais pesado que só converter formato)
6. Ao final: resumo com **X convertidos / Y falhas**; o painel guarda a última execução na linha "Last regeneration" (option `rd_img_last_regen`, badge de aviso quando há falha)

> 🚨 **Ritual obrigatório com CDN/proxy na frente (Cloudflare!):** o regen sobrescreve arquivos **mantendo os nomes** — e o edge tem cache de longa duração pra estáticos. Depois de regenerar, **purgar o cache do Cloudflare**, senão produção continua servindo as versões antigas indefinidamente (aconteceu em 2026-06-11: AVIFs re-encodados no origin, edge servindo os velhos com `Cf-Cache-Status: HIT`).

### Cobertura

✅ Tudo que passa por `wp_get_attachment_image` (`rd_img_wrap_in_picture`):
- Featured images do post
- Post thumbnails nos cards (post-card.php usa essa função)
- Galleries do Gutenberg
- Custom logo

✅ Imagens inline do conteúdo (`rd_img_wrap_content_images`, filter `the_content` prio 20 — depois do Markdown em 6 e do wpautop em 10): bloco Gutenberg `core/image`, Markdown processado, `<img>` cru no editor. Parsing via DOMDocument (não-regex), skip de `<img>` já dentro de `<picture>`.

### Original sempre preservado

Os arquivos JPEG/PNG originais **nunca são deletados nem alterados**. WebP/AVIF são acréscimos. Se algum dia desativar o módulo, o site continua funcionando normal com os originais — só perde o ganho de compressão.

---

## 🌐 `inc/mod-cdn-images.php` — CDN URL rewrite (dormente por padrão)

Ponto de extensão pra reescrever URLs de imagem do uploads pra um CDN externo (Cloudflare Images, Bunny, KeyCDN, BunnyCDN Image Optimization, etc.) **sem editar core do tema**. Adicionado em 2026-05-29 como "future-proofing barato" — o módulo nasce desligado e só atua quando alguém hookar o filter.

### Filosofia

- **Dormente:** sem callbacks registrados no filter, todas as URLs passam intactas. Zero overhead, zero mudança de comportamento.
- **Ativação por código, não por painel:** decisão consciente — quem ativa CDN sabe configurar PHP. Não vale o custo de painel UI + opção de banco + sanitização pra algo que muda 1× e fica.
- **Single source of truth:** todo o delivery de imagem do tema passa pelo filter `reloaded_image_url`, então o ativador hooka **uma vez** e cobre featured images, post cards, galleries, og:image, srcset responsivo — tudo.

### Cobertura

O filter `reloaded_image_url($url, $context)` é aplicado em 3 pontos do WordPress que cobrem 100% do delivery de imagens do tema:

| Filter WP nativo | Quando dispara | Contexto passado |
| --- | --- | --- |
| `wp_get_attachment_url` | URL canônica do attachment (sem size) | `'attachment_url'` |
| `wp_get_attachment_image_src` | `<img src>` com size resolvido (post-thumb, featured, galleries) | `'image_src'` |
| `wp_calculate_image_srcset` | Cada URL do srcset responsivo (`240w, 480w, 720w...`) | `'srcset'` |

Tudo isso é chamado em cascata por funções de mais alto nível (`the_post_thumbnail`, `wp_get_attachment_image`, `get_the_post_thumbnail_url`, og:image em `mod-seo.php`, etc.), então **não precisa instrumentar mais nada**.

### O que NÃO é coberto (intencional)

- **URLs externas** (não-uploads): Gravatar, oEmbed, ícones de redes sociais hardcoded em SVG, ads de terceiros. O helper interno `rd_cdn_maybe_rewrite()` verifica `strpos($url, wp_upload_dir()['baseurl']) === 0` antes de invocar o filter — URLs fora do uploads passam silenciosas (caso contrário o CDN serviria 404).
- **URLs hardcoded em templates**: se algum `header.php` ou template tiver `<img src="https://reloaded.com.br/wp-content/uploads/...">` literal, refatorar pra usar `wp_get_attachment_image()` ou `get_template_directory_uri()` — o filter não enxerga string literal em template.
- **Inline content do post**: imagens do bloco Gutenberg `core/image` no `the_content` já são cobertas indiretamente, porque `<img src>` produzido pelo block usa `wp_get_attachment_image_src` internamente.

### Interação com `mod-image-formats.php`

A ordem de execução é: `mod-image-formats` decide WebP/AVIF e troca o `<img>` por `<picture><source>...<img></picture>` com a URL apontando pro arquivo `.avif`/`.webp` no filesystem. Quando `wp_get_attachment_image_src` retorna a URL final, **ela já está apontando pra extensão next-gen** (se aplicável). O `reloaded_image_url` recebe essa URL pronta e a reescreve pro CDN. Resultado: o CDN serve `.avif`/`.webp` automaticamente — não precisa configurar nada no CDN, ele só precisa servir o arquivo cuja URL você está pedindo.

> ⚠️ **CDNs com image transforms próprias** (Cloudflare Images, Bunny Optimizer) podem **dobrar o trabalho**: eles fazem WebP/AVIF on-the-fly a partir do JPEG original. Se for usar um desses, vale considerar **desligar `mod-image-formats`** (toggle no painel → Performance → Imagens & Mídia) pra evitar disco-cheio com versões locais que ninguém vai servir. Mas pra CDNs simples (KeyCDN, BunnyCDN básico — só servem arquivo) deixa os dois ligados.

### Como ativar

Adicione 1 callback ao filter em qualquer um destes lugares:
- **mu-plugin** (preferido — `wp-content/mu-plugins/cdn-rewrite.php`, sobrevive a troca de tema)
- **Snippet no `functions.php` do child theme**
- **Snippet plugin** (Code Snippets, WPCodeBox)

#### Receita 1 — Bunny CDN (Pull Zone simples)

Mais comum. Você cria uma Pull Zone no Bunny apontando pra `https://reloaded.com.br`, recebe um hostname tipo `reloaded.b-cdn.net`, e troca o prefixo:

```php
<?php // wp-content/mu-plugins/cdn-rewrite.php
defined( 'ABSPATH' ) || exit;

add_filter( 'reloaded_image_url', function( $url, $context ) {
    return str_replace(
        'https://reloaded.com.br/wp-content/uploads',
        'https://reloaded.b-cdn.net/wp-content/uploads',
        $url
    );
}, 10, 2 );
```

Se quiser **excluir o admin** (dev backend serve direto do origin pra evitar cache stale durante upload):

```php
add_filter( 'reloaded_image_url', function( $url, $context ) {
    if ( is_admin() ) {
        return $url;
    }
    return str_replace(
        'https://reloaded.com.br/wp-content/uploads',
        'https://reloaded.b-cdn.net/wp-content/uploads',
        $url
    );
}, 10, 2 );
```

#### Receita 2 — BunnyCDN com Image Optimization

Se ativou Optimizer no Bunny (resize/quality on-the-fly via querystring), o CDN re-otimiza baseado em parâmetros. Pode passar quality global pra evitar atropelar suas configs:

```php
add_filter( 'reloaded_image_url', function( $url, $context ) {
    $cdn = str_replace(
        'https://reloaded.com.br/wp-content/uploads',
        'https://reloaded.b-cdn.net/wp-content/uploads',
        $url
    );
    // Não aplica querystring em srcset (cada source já tem seu width próprio)
    if ( 'srcset' === $context ) {
        return $cdn;
    }
    return $cdn . ( strpos( $cdn, '?' ) === false ? '?' : '&' ) . 'quality=85';
}, 10, 2 );
```

#### Receita 3 — Cloudflare Images (CDN + transform on demand)

Cloudflare Images usa um path peculiar: `https://imagedelivery.net/<account-hash>/<image-id>/<variant>`. Como o `<image-id>` é gerado por upload na API deles (não bate com path do uploads do WP), o caminho mais simples é usar **Cloudflare Image Resizing** (não Images), que mantém URL original com prefixo:

```php
add_filter( 'reloaded_image_url', function( $url, $context ) {
    // Cloudflare Image Resizing: /cdn-cgi/image/format=auto,quality=85/<url>
    return 'https://reloaded.com.br/cdn-cgi/image/format=auto,quality=85/' . $url;
}, 10, 2 );
```

> Cloudflare Images "real" (upload via API com IDs próprios) requer migração de Media Library inteira pra IDs do Cloudflare — fora do escopo deste filter. Use Image Resizing pra esse caso.

#### Receita 4 — KeyCDN / outros CDNs simples (Pull Zone)

Idêntico à Receita 1, só troca o hostname. Maioria dos CDNs Pull-Zone funciona assim.

### Verificar que está funcionando

Depois de hookar, abrir qualquer post com imagem, **View Source** (Ctrl+U), procurar por `<img src=` e `srcset=`. URLs devem apontar pro hostname do CDN. Conferir também:

- `<meta property="og:image">` no `<head>` — deve estar reescrito (provavelmente é o teste mais visível: redes sociais vão buscar a imagem desse URL)
- `<link rel="preload" as="image">` se a página tiver LCP image hint
- Featured images dos post cards na home

Se algo escapar, é porque está usando uma função WP que não passa pelos 3 filters da tabela acima — abrir issue / refatorar pra usar `wp_get_attachment_image_src`.

### Desativar / desinstalar

Remover o callback do filter (apagar o mu-plugin ou comentar o snippet). URLs voltam a apontar pro origin imediatamente — não há cache no lado do tema.

### Sintergias previstas

- Quando **Wave 14 (HTTP/3 + Brotli)** chegar, o CDN cobre os dois (Bunny/CF já servem HTTP/3 e Brotli por default). Imagem é o maior peso por byte numa página típica → maior ganho.
- Quando **ativar Redis Object Cache** (Till Kruss), o filter custa zero (sem queries — `wp_upload_dir()` é static-cached pelo WP em runtime).

---

## 📱 `inc/mod-social.php` — Redes sociais

### Helpers

- `rd_render_social_icons( $user_id = null )` — itera pelas 8 redes (`discord`, `telegram`, `whatsapp`, `youtube`, `instagram`, `steam`, `twitter`, `facebook`) e renderiza um `<a>` com SVG embarcado pra cada rede com URL preenchida. **Dois modos:**
  - **Sem argumento (default):** usa as URLs globais do portal (`rd_get_option('social_X')`). Comportamento clássico, usado em top bar, footer bottom bar, template-about hero.
  - **Com `$user_id`:** usa redes pessoais do user específico via `get_the_author_meta('social_X', $user_id)`. Usado no `author.php` pra mostrar redes próprias do redator.
- `rd_render_news_ticker()` — pega últimas N posts pra alimentar o ticker rolante da top bar
- `rd_render_date()` — renderiza a data atual formatada na top bar (esquerda)

### Filter: redes sociais por user

`rd_add_user_social_fields()` engancha em `user_contactmethods` (filter nativo do WP) e adiciona os mesmos 8 campos de social URL em **Usuários → Perfil → Informações de contato**. Cada redator tem suas próprias URLs salvas em `user_meta` (acessíveis via `get_the_author_meta('social_X', $user_id)`). Pra multi-redator: cada um preenche seu próprio Twitter/Discord/etc no perfil e isso aparece automaticamente em `/author/{slug}/`, separado das redes do portal global.

---

## 📡 `inc/mod-indexnow.php` — IndexNow (push indexing)

Notificação push pros buscadores participantes (Bing/Yandex/Seznam/Naver) no momento em que conteúdo muda — indexação em minutos em vez de esperar o crawler (Google não participa; o sitemap segue essencial). 3 peças:

- **Chave:** auto-gerada (32-hex) no save via `pre_update_option_rd_settings` quando o toggle liga com o campo vazio (`rd_indexnow_generate_key_on_save`). Aceita chave externa colada (validação `[a-z0-9-]{8,128}`)
- **Prova de propriedade:** `/{key}.txt` servido **virtualmente** (`rd_indexnow_serve_key_file` no `init`) — sem arquivo físico, com `X-Robots-Tag: noindex`
- **Ping:** `transition_post_status` (post/page; publica, atualiza publicado, ou despublica — engines reveem e acham o 404/410). POST JSON pro `api.indexnow.org` com `blocking=false` + timeout 3s (fire-and-forget — o editor nunca espera). Slug `__trashed` é limpo antes do ping de despublicação
- **Breadcrumb:** último ping (timestamp + URL) salvo em `rd_indexnow_last_ping` (autoload off) e exibido na desc do campo da chave no painel

---

## 🔍 `inc/mod-seo.php` — SEO técnico (OG, Twitter, Canonical, Description, Schema)

Um módulo, cinco frentes complementares. Tudo emitido no `<wp_head>` com prioridades distintas pra ordem previsível no source.

### Open Graph (singular)

Filtra `wp_head` pra adicionar meta tags OG quando `enable_open_graph` está ativo, somente em `is_single() || is_page()`:
- `og:type`, `og:title`, `og:description`, `og:url`, `og:image`
- `og:image:width` / `og:image:height` só quando as dimensões são realmente conhecidas (evita mentir pro crawler que ajusta crop)
- Imagem: featured image do post → fallback `og_fallback_image` do painel → fallback do logo do tema
- Validação de imagens: rejeita SVG (não suportado por Facebook/X/WhatsApp/Discord) e qualquer imagem abaixo de 200x200 (mínimo do Facebook)

### Twitter Cards (singular)

Junto com o bloco OG, emite cards completos `summary_large_image`:
- `twitter:card`, `twitter:title`, `twitter:description`, `twitter:image`
- `twitter:site` — extraído automaticamente da URL configurada em **Redes Sociais → Twitter (X)** via regex (`x.com/usuario` ou `twitter.com/usuario`)
- `twitter:creator` — lê o user meta `twitter_handle` do autor do post (UI no perfil ainda não criada — feature futura). Fallback pro `twitter:site` global, que é correto pra blog single-author

### Canonical URLs

Substitui o `rel_canonical` nativo do WP (que só cobre singulares) por uma implementação que cobre **todas** as superfícies indexáveis:
- Home/Blog → URL raiz do site
- Singular → permalink (delega pro `wp_get_canonical_url()` nativo)
- Category/Tag/Tax → URL do termo
- Author → URL do archive do autor
- Year/Month/Day archives → URL canônica do nível correspondente
- Search → URL com `?s=`
- Paginação (`/page/N/`) preservada em todos os archives

Pula 404 e feeds. Sem opção no painel — canonical é baseline sem trade-off.

### Meta Description

Emite `<meta name="description">` em todas as superfícies indexáveis, com fonte por contexto:
- Singular → excerpt do post (helper `rd_seo_resolve_post_description()` compartilhado com OG)
- Home/Blog → tagline do site (Configurações Gerais → Descrição)
- Category/Tag/Tax → descrição do termo, fallback "Posts in NomeDoTermo."
- Author → bio do autor, fallback "Posts by Nome."
- Date archives → "Archive of posts from {ano/mês/dia}" localizado via `date_i18n`

Pula 404, busca e feeds. Trunca em 160 caracteres pra ficar dentro do snippet do Google.

### Schema.org JSON-LD

Emite dois schemas por contexto:
- **Article** em `is_single()` — headline, description, image, datePublished, dateModified, author (Person com `@id`, name, url, image), publisher (Organization + logo), mainEntityOfPage
- **WebSite** na front page — name, URL, description e `potentialAction.SearchAction` (habilita o sitelinks search box do Google nos SERPs)

#### Person `@id` matching (Article ↔ author archive)

O `author` dentro do Article é um `Person` com `@id => get_author_posts_url($author_id) . '#person'`. Esse mesmo `@id` é declarado no Person standalone emitido pelo `author.php`. **Google amarra os dois schemas como o MESMO Person** — alimenta E-E-A-T (Experience, Expertise, Authoritativeness, Trustworthiness) ligando "esse Article foi escrito pelo autor desse perfil". Também inclui `image` (avatar Gravatar 240px) e `url`. Name tem fallback chain `display_name → user_nicename → user_login` pra evitar "Item sem nome" no Rich Results se algum user foi seedado sem display_name.

Helper `rd_seo_print_jsonld()` faz o `wp_json_encode` com flags `UNESCAPED_SLASHES | UNESCAPED_UNICODE` pra source legível. Logo do publisher resolve via `rd_seo_resolve_site_logo()` (Custom Logo do Customizer → fallback logo do tema).

Validado contra o **Google Rich Results Test**. O `BreadcrumbList` fica no módulo separado (`mod-breadcrumbs.php`). O `Person` standalone (`author.php` e `template-about.php`) é emitido nos templates correspondentes — Rich Results não destaca Person porque não tem rich snippet visual, mas Google lê e usa internamente pro Knowledge Graph.

### Custom robots.txt

Filter `robots_txt` permite substituir totalmente o output gerado por `do_robots()`:
- Vazio (`custom_robots_txt`) → mantém default WP (User-agent, Disallow /wp-admin/, Sitemap)
- Preenchido → substitui completamente

Sanitização defensiva: remove BOM UTF-8, normaliza CRLF→LF, garante trailing newline (parsers strict reclamam quando falta).

### Sitemap nativo controlável

3 toggles via filters do WP 5.5+:
- `wp_sitemaps_enabled` ← `enable_sitemap` — kill switch global
- `wp_sitemaps_add_provider` ← `sitemap_include_authors` (default OFF) — remove sub-sitemap `users` quando desabilitado
- `wp_sitemaps_post_types` ← `sitemap_include_cpt` — filtra apenas `post` + `page` quando desabilitado (descarta CPTs)

---

## 🍞 `inc/mod-breadcrumbs.php` — Trilha de navegação contextual

Calcula uma única vez o trail (`array<['name', 'url']>`) e alimenta dois consumidores:

- `rd_render_breadcrumbs()` — markup HTML semântico (`<nav aria-label> > <ol>`) com `aria-current="page"` no item ativo. Chamado de `header.php` logo após o `</header>`. Gated pelo toggle `enable_breadcrumbs` (aba Geral, default ON).
- `rd_add_breadcrumb_jsonld()` — `BreadcrumbList` Schema.org no `<wp_head>`. **Sempre ativo** independente do toggle visual (SEO baseline). Pulado em search/404.

### Trilhas por contexto

| Contexto | Trail |
|----------|-------|
| Front page | (vazio — não renderiza) |
| Single post | Home › Categoria primária › Título |
| Page | Home › Título |
| Category | Home › (ancestrais) › Categoria |
| Tag | Home › `Tag: nome` |
| Taxonomia custom | Home › Termo |
| Author | Home › `Author: nome` |
| Year/Month/Day | Home › Ano › Mês › Dia |
| Search | Home › `Search results for: termo` |
| 404 | Home › `Page not found` |

### Categoria primária em singles

Reusa o meta `_rd_primary_category` (feature do Geral → Primary Category). Fallback pra primeira categoria atribuída quando não há explícita.

Estilos visuais em `sass/components/_breadcrumbs.scss` (separador `/`, truncamento em mobile).

---

## 🎨 `inc/mod-category-colors.php` — Cor por categoria configurável

Substitui o mapping hardcoded `.tag-{slug}` que existia em SCSS por um sistema data-driven baseado em `term_meta`. Cada categoria tem cor própria, calculada uma vez e injetada no `<head>`.

### Admin UI

Color picker (`wp-color-picker`/Iris) na tela de **Posts → Categorias → Editar → Color**:
- `category_add_form_fields` → adiciona o campo no form de criar nova categoria
- `category_edit_form_fields` → adiciona o campo no form de editar existente
- `created_category` + `edited_category` → salvam o term meta `rd_category_color`
- `admin_enqueue_scripts` → carrega o `wp-color-picker` apenas em `edit-tags.php` e `term.php` quando `taxonomy=category`

Sanitização via `sanitize_hex_color()`. Se o admin escolher a cor default (`#555555`) ou um hex inválido, o meta é removido e o chip cai no fallback do SCSS.

### Frontend

`rd_category_color_render_styles()` rodando em `wp_head` (prioridade 20):
1. `get_terms` em todas as categorias (mesmo as sem posts, `hide_empty=false`)
2. Itera, lê `rd_category_color` de cada uma
3. Pra cada categoria com cor, calcula a cor do texto via YIQ e emite uma regra CSS
4. Tudo concatenado num único `<style id="rd-category-colors">` no `<head>`

Exemplo do output:
```html
<style id="rd-category-colors">
.post-tag.tag-games{background-color:#8a2be2;color:#ffffff;}
.post-tag.tag-noticias{background-color:#00d1ff;color:#000000;}
</style>
```

### Helper de contraste

`rd_get_contrast_text_color($hex)` — retorna `#000000` ou `#ffffff` baseado na fórmula YIQ:
- Pondera os canais R/G/B pela percepção humana de luminância (`R*299 + G*587 + B*114`)
- Threshold 128 = padrão web pra decisão binária "preto vs branco"
- Aceita shorthand `#abc`, valida hex; chuta branco em entrada inválida

Sem `!important`, sem hack. Especificidade do seletor (`.post-tag.tag-{slug}` = 0,2,0) é suficiente pra ganhar do fallback básico (`.post-tag` = 0,1,0). A regra legada `color: #000` no grid foi removida pra não atropelar o cálculo de contraste.

### Kicker no single

`rd_render_single_category_kicker()` — chamada em `single.php` dentro de `<header class="entry-header">`. Renderiza um chip da categoria primária à direita do título (flex layout em desktop, coluna no mobile ≤600px). Reusa o meta `_rd_primary_category` pra escolha; fallback pra primeira categoria.

Markup compartilhado com o chip do grid (`<a class="post-tag tag-{slug}">`), então herda automaticamente a cor configurada no admin sem regra CSS adicional.

---

## 📂 `inc/mod-archive-header.php` — Cabeçalho rico do template archive

Encapsula o markup do header dos archives (categoria, tag, taxonomia, data) em um único helper `rd_render_archive_header()` chamado direto de `archive.php`. Três features sobre o header básico que existia inline:

### 1. Ícone contextual SVG

Helper `rd_archive_header_get_context()` identifica o tipo de archive via condicionais nativos do WP (`is_category`, `is_tag`, `is_tax`, `is_year`, `is_month`, `is_day`, `is_author`). Cada contexto tem um ícone feather-style embarcado em `rd_archive_header_get_icon()`:

| Contexto | Ícone |
|----------|-------|
| `category` | folder |
| `tag` / `tax` | tag |
| `date` (ano/mês/dia) | calendar |
| `author` | user |
| `generic` (fallback) | linhas horizontais |

### 2. Contador de posts

`$wp_query->found_posts` formatado via `_n('%s post', '%s posts', ...)` pra pluralização correta. Renderizado como `<X> post(s)` abaixo da descrição.

### 3. Borda esquerda colorida

Helper `rd_archive_header_get_accent_color()` lê a cor do termo via `rd_category_color` (term meta do `mod-category-colors.php`). Só categorias têm cor — outras superfícies caem no fallback brand-blue.

A cor é injetada inline via CSS variable: `style="--rd-archive-accent: #abc"`. O SCSS usa `var(--rd-archive-accent, $brand-blue-light)` em `::after` (borda esquerda 4px) e na cor do ícone, criando harmonia visual entre os dois.

### Render

`rd_render_archive_header()` monta o HTML completo: wrapper com classe contextual `.rd-archive-header-{category|tag|date|author|generic}`, ícone à esquerda, bloco de texto à direita (título + descrição + contador). Estilos visuais em `sass/components/_page-archive.scss`.

O title e a description seguem usando os helpers nativos `the_archive_title()` e `the_archive_description()` — preserva i18n e o `<span>` interno do título que recebe o gradient brand.

---

## 💝 `inc/mod-donations.php` — Apoie o Projeto

- `rd_get_support_block_html( array $data ): string` — monta e **retorna** o HTML do bloco "Apoie o Projeto" a partir de um array de dados (não lê mais opções do painel):
  - Título configurável (`<h3>`; vazio = "Support the Project")
  - GitHub Sponsors (link)
  - PayPal (link + QR code clicável)
  - PIX (URL + QR code + chave "copia e cola" com botão JS)
  - Retorna `''` quando nenhum método é preenchido (o caller pula o wrapper do widget)
- `rd_render_qr_img()` — serve a versão `rd-qr` (240×240) do QR quando o anexo é resolvível.

> Configuração e posicionamento ficam no **`RD_Support_Widget`** (`inc/class-rd-support-widget.php`, Aparência → Widgets). O bloco deixou de ser hardcoded no `sidebar.php` e os campos saíram do painel.

> JS pra "Copy PIX key" usa o helper genérico `data-rd-copy` (definido em `assets/js/navigation.js`). Veja seção [Copy to Clipboard](06-frontend-scss-js.md#copy-to-clipboard) pra detalhes.

---

## 📢 `inc/mod-ads.php` — Anúncios

5 slots renderizadores chamados pelos templates:

| Função | Onde é chamada | Slot |
|--------|----------------|------|
| `rd_render_ad_topo()` | `header.php` (top branding) | Banner desktop 728×90 |
| `rd_render_ad_mobile_anchor()` | Hook `wp_footer` | Anchor fixo mobile |
| `rd_render_ad_sidebar_sticky()` | `sidebar.php` (sticky) | 300×250 ou 300×600 |

Cada um lê do `rd_settings` o HTML/JS literal do anúncio e renderiza só se preenchido. O banner "sidebar topo" virou o widget `RD_Ad_Widget` (`inc/class-rd-ad-widget.php`) — código no form do widget, nonce do CSP via `rd_csp_inject_nonce()`.

**Dedupe do loader (`rd_ads_dedupe_loader`)** — cada bloco AdSense colado no painel/widget traz a própria tag `<script src=".../adsbygoogle.js?client=...">`; sem tratamento, a página carregava 5 cópias do loader (flagrado pelo PageSpeed). Todo código de anúncio passa por esse helper na renderização: mantém a **primeira** ocorrência de cada URL de loader e remove as repetidas (dedupe por URL exata — setups multi-client mantêm um loader por client ID). O `ad_global` do `<head>` (mod-integrations.php) também passa pelo helper ANTES dos slots do body, então o loader global "vacina" os slots → exatamente 1 tag por página. Genérico: qualquer loader externo duplicado (gpt.js do GAM etc.) recebe o mesmo tratamento.

**Lazy ads (`ads_lazy_load`, default OFF)** — toggle "Delay ads until interaction" em Monetization → Global Script. Quando ON, o `rd_ads_dedupe_loader` muda de comportamento: em vez de manter a primeira tag, **remove TODOS os loaders** e enfileira as URLs (`rd_ads_loader_queue`); um bootstrap inline no footer (`rd_ads_print_lazy_loader`, `wp_footer` prio 99, nonce CSP) injeta os loaders oficiais no **primeiro gesto do visitante** (scroll/touch/tecla/mouse/click — modificadora pura tipo Ctrl/Shift/Alt não conta, é atalho de browser) ou após o `ads_lazy_timeout` (default 5s; `0` = só interação). Os `<ins>` + `push()` ficam inline com espaço reservado — zero CLS quando o ad chega. Efeito colateral desejado: o Lighthouse não interage no lab, então os ~440 KB de JS de ads ficam fora da medição; visitante que sai sem interagir nunca baixa nada. É o loader oficial sem modificação — só o momento muda.

**Park/unpark de unidades escondidas** — o `push()` do AdSense preenche unidades pendentes em ordem de DOM **sem checar visibilidade**: uma unidade fixa 728x90 processada com o container `display:none` (breakpoint mobile) é "auto-dimensionada" pelo Google pra proporção da página (`data-ad-auto-size` reescreve o width/height inline — sem opt-out público; o `data-full-width-responsive="false"` só bloqueia a *expansão* full-width, não essa mutação). Caso real: carregar com janela <769px e alargar deixava o slot do topo como caixa esticada/errada. O bootstrap lazy resolve na raiz: antes do loader rodar, **estaciona** fora do DOM toda `<ins>` invisível (comment placeholder marca o lugar, o push órfão é removido da fila) e **devolve + pusha** quando o container fica visível (listener em `matchMedia('(max-width: 768px)')` — cobre resize cruzando o breakpoint E rotação de celular/tablet). Cinto de segurança adicional no CSS: `.rd-ad-desktop` com clamp `max-width:728px / max-height:90px / overflow:hidden`.

**ads.txt virtual** — `rd_ads_serve_ads_txt()` (hook `init`) serve `/ads.txt` direto da opção `ads_txt_content` (textarea em Monetization → Global Script). Mesmo padrão do arquivo de chave do IndexNow: sem arquivo físico, sem SFTP, sobrevive a migrações e entra no backup de settings. Sanitizado como plain-text (`sanitize_textarea_field` — a chave não casa com o prefixo `ad_` de HTML raw). Vazio = dormante; arquivo físico na raiz tem precedência.

JS pra fechar o anchor mobile (X) vive em `navigation.js` (`.rd-ad-close`).

---

## 🛠️ `inc/mod-maintenance.php` — Modo manutenção

Quando `maintenance_mode = 1`:

1. `template_redirect` intercepta requests
2. Se usuário NÃO é admin logado → mostra `wp_die()` com tela 503
3. Tela usa o `maintenance_text` customizado ou texto padrão com `%s` substituído por `get_bloginfo('name')`
4. Logo da tela vem de `assets/img/logo-reloaded-panel.webp`

**Escapatória pra dev:**
- URL `/?rd_maint_login` mostra um form de senha
- Senha é comparada com `password_verify()` contra o hash salvo em `maintenance_pass_hash`
- Length cap de 200 chars na senha submetida antes do verify (evita DoS marginal de payloads gigantes)
- Sucesso → token criptográfico (`bin2hex(random_bytes(32))`) salvo em transient + cookie `rd_maint_token` (httponly + secure + SameSite=Lax) que libera acesso por 24h
- Failed: rate limit de 5 tentativas / 15 min por IP via transients (`rd_maint_rate_<md5(ip)>`). IP vem de `rd_get_client_ip()` em `core.php` — valida `REMOTE_ADDR` contra ranges de proxy reconhecidos antes de confiar em `CF-Connecting-IP`/`X-Forwarded-For`, **impedindo o bypass clássico** de "muda header → contador zera"

---

## 👁️ `inc/mod-views.php` — Sistema de views

| Constante | Valor | Significado |
|-----------|-------|-------------|
| `RD_VIEWS_META_KEY` | `_rd_post_views` | Contador all-time |
| `RD_VIEWS_META_KEY_LOG` | `_rd_post_views_log` | Array de timestamps (pra janelas) |
| `RD_VIEWS_DEDUP_WINDOW` | `1800` (30 min) | Janela de deduplicação por IP |
| `RD_VIEWS_LOG_RETENTION` | `31536000` (1 ano) | Quanto tempo manter timestamps no log |

**Fluxo:**

1. Em singles, `views-tracker.js` é enfileirado via `wp_enqueue_script` com `wp_localize_script` injetando `post_id`, `nonce`, `ajaxurl`
2. JS dispara AJAX `rd_track_view` 1.5s após DOMContentLoaded (escapa de cache de página)
3. Backend (`rd_views_ajax_track`) valida nonce, status, current_user_can(edit_posts), filtro de bots por User-Agent, dedup por IP
4. Se válido, `rd_views_increment()` faz `update_post_meta` direto no contador + log

**API pública:**

- `rd_get_post_views($post_id, $window)` — `'all'`, `'day'`, `'week'`, `'month'`, `'year'`
- `rd_get_popular_posts($limit, $args)` — `WP_Query` ordenado por `meta_value_num`
- `rd_format_views_number($n)` — formatador respeitando config `views_number_format` do painel. **Dois modos:**
  - **`'full'` (default):** `number_format_i18n($n)` — número exato (`1.234`, `12.345.678`)
  - **`'compact'`:** estilo redes sociais (YouTube/Reddit/GitHub):
    - `< 1k` → exato (`999`)
    - `< 10k` → 1 decimal **truncado** (`1.9k`, nunca `2k` pra 1999 — usa `floor`, não `round`)
    - `< 1M` → sem decimal (`10k`/`999k`)
    - `< 10M` → 1 decimal (`1.2M`/`9.9M`)
    - `< 1B` → sem decimal (`10M`/`999M`)
    - `≥ 1B` → `NB` (defensivo)
  - **Aplicado SÓ no frontend** (cards/single via `rd_get_formatted_views()`). Admin (coluna posts, painel Statistics em `mod-stats.php`) usa `number_format_i18n` direto — sempre exato.

**Admin extras:**
- Coluna "Views" sortável na lista de posts
- `is_protected_meta` filtro pra esconder os meta keys da UI Custom Fields

---

## 🏠 `inc/mod-dashboard.php` — Aba "Dashboard" (Visão Geral)

Aba read-only adicionada na Wave 11 Fase F. Vira a **primeira aba** e o **default landing** quando admin abre o painel ReloadeD. Mostra um overview do estado do site sem precisar abrir cada aba individualmente.

### Estrutura

A função `rd_dashboard_render()` (callback de `add_settings_section('sec_dashboard', ...)`) renderiza 4 seções via componentes `rd-p*`:

1. **Site Status** — 6 cards com badge mostrando estado de features-chave:
   - Content Security Policy → `OFF` (neutral) / `REPORT-ONLY` (info) / `ENFORCE` (danger)
   - Login Protection → `OFF` / `ON` (mostra slug em `<code>` se configurado)
   - Maintenance Mode → `OFF` / `ON` (warning amber)
   - Statistics Tracking → `OFF` / `ON`
   - Critical CSS Inline → `OFF` / `ON`
   - Next-gen Images → `OFF` / `ON` (mostra formato ativo: AVIF/WEBP/BOTH)

2. **Quick Metrics** — 3 cards big-number com janelas temporais **rolling** (não calendar) pra consistência semântica:
   - Views Last 24h — reusa `rd_stats_total_views('day')`. **Bypass do cache** via `delete_transient(RD_STATS_CACHE_PREFIX . 'total_day')` antes da chamada — sem isso, o transient com TTL 1h do mod-stats deixaria o número até 1h stale no Dashboard. Custo: 1 query SQL extra por render (insignificante). Outros transients de stats continuam cacheados.
   - Posts Last 30 Days — query `get_posts` com `date_query` rolling: `now − 30 dias`
   - Pending Comments — `wp_count_comments()->moderated`; snapshot do estado atual; quando > 0 mostra link "Review queue"

3. **Activity Trend** (Wave 11 Fase G) — bar chart de views por dia, últimos 7 dias. Função `rd_dashboard_get_views_7d()` parseia logs (`_rd_post_views_log`) sem cache (mesma filosofia do Views Last 24h — Dashboard sempre fresh). Render via canvas com `data-rd-chart-type="bar"`, auto-inicializado pelo módulo charts do bundle `admin-panel.js`. Sempre renderiza — sem dados, barras ficam zeradas (preview do layout futuro). **Quando o CSP report-only tem violações** (2026-06-05), o doughnut "Violações por Diretiva" (`rd-dashboard-csp-chart`, reusa `rd_csp_get_violations_by_directive()`) aparece ao lado via `.rd-pgrid--sidebar-main` (estreito-esquerda + Activity Trend largo-direita); a descrição do Activity Trend foi pra dentro do card pra os dois headers ficarem title-only e alinharem.

### Toggle switches inline (adições pós-Fase H)

3 cards do Site Status com **deep link buttons** (CSP, Login Protection, Next-gen Images) — ícone de engrenagem ao lado do badge com tooltip estilizado que abre a aba relevante e scrolla até a section via hash anchor.

8 cards com **toggle switch inline** (Maintenance Mode, Statistics Tracking, Critical CSS Inline, Top Bar, Comments, Markdown, YouTube Facade, Breadcrumbs) — admin flipa ON/OFF sem sair do Dashboard. (Discord saiu — virou widget WP.)

**Tooltips de nome (Wave 12)** — passar o mouse no **nome** de qualquer card do Site Status mostra uma explicação curta do que a feature faz, reusando o mesmo balão `[data-tooltip]` dos switches/engrenagens (fundo preto translúcido). O balão fica centralizado sobre o card (âncora via `position: relative` no `.rd-pcard`) e o texto em caixa normal. As explicações vivem em `rd_dashboard_get_status_tooltips()` (keyed pelo option name de cada card) e são injetadas pelo novo arg `tooltip` do `rd_panel_card_open()`.

**AJAX endpoint `wp_ajax_rd_dashboard_toggle`** (callback `rd_dashboard_ajax_toggle()`) — defesa em profundidade:

- **Whitelist constant** `RD_DASHBOARD_TOGGLE_WHITELIST` restringe quais option keys podem ser flipadas (9 atualmente — todas binárias 0/1)
- **Nonce** verificado via `check_ajax_referer( 'rd_dashboard_toggle', '_wpnonce' )`
- **Capability** `current_user_can( 'manage_options' )` — mesma do painel inteiro
- **Validação de value** — só aceita `'0'` ou `'1'`
- Sem `wp_ajax_nopriv` — intent claro de admin-only

Retorna JSON `{ ok: true|false, key, value | error }`. Maintenance Mode é o único com confirm dialog (turning ON bloqueia visitantes); desligar é sempre seguro. Badge ON do Maintenance ganha prefix `⚠️` via `<span class="rd-pbadge__emoji">` (kept green for visual consistency com outros toggles).

**JS handler** no módulo dashboard-toggle do bundle `assets/js/admin-panel.js` — detecta clique em `.rd-pswitch[data-rd-toggle]`, dispara fetch, atualiza visual sem reload (aria-checked + badge variant + texto ON/OFF). Network/server errors trigger shake animation no switch (`is-error` class).

**Componente CSS `.rd-pswitch`** — toggle deslizante moderno (track verde/cinza + thumb branca animada). `role="switch"` + `aria-checked` pra acessibilidade. Estados `.is-loading` (cursor wait, opacity reduzida) e `.is-error` (track vermelha + shake animation).

**Enqueue dedicado** `rd_dashboard_admin_enqueue()` carrega o JS apenas em `?tab=dashboard` (gate independente do Chart.js gate em `mod-stats.php`).

3. **Quick Actions** — 4-5 botões pra atalhar pras outras abas (General, Security & CSP, Images & Media, Backup, Statistics se ativa)

4. **Footer Info** — linha discreta com versão do tema (`wp_get_theme()->get('Version')`), versão WP (`get_bloginfo('version')`) e versão PHP (`PHP_VERSION`)

### Decisões de design

- **Sem JS** — toda atualização vem do page reload. Dados são instantâneos (transients do mod-stats + 1 query rápida pra posts/mês + count nativo do WP pra comments).
- **Sem botão "Refresh"** — desnecessário; reload da página já refresca.
- **Sem botão "Clear cache"** — boundaries claros entre tema e infra. Redis Object Cache plugin e Nginx Helper plugin têm UI própria pra purge.
- **Cards em grid 3 cols** em desktop (`.rd-pgrid--three-cols`), 1 col em mobile.
- **Statistics tab no `quick_actions`** sempre aparece (a aba é incondicional desde a remoção do feature flag `enable_stats`).

### Helpers internos

- `rd_dashboard_get_status_data()` — coleta os itens de status (lê os options via `rd_get_option_bool`)
- `rd_dashboard_get_status_tooltips()` — mapa `option name → explicação curta` mostrada no tooltip do nome do card (mantido separado do status data pra não inchar aquele array)
- `rd_dashboard_get_metrics_data()` — coleta as 3 métricas
- `rd_dashboard_get_quick_actions()` — monta a lista de atalhos (URL/icon/label)

### CSS específico

Em `assets/css/admin-style.css`, section "DASHBOARD" (após sistema rd-p* e antes do PAINEL DE OPÇÕES legacy):

- `.rd-dashboard-status-line` — linha "[BADGE] [detail]" dentro dos cards de Status (line-height generoso pra acomodar badges + code inline)
- `.rd-dashboard-actions` — flex row dos botões em Quick Actions
- `.rd-dashboard-footer-info` — texto centralizado discreto do footer

Adicionado também ao sistema de componentes (porque é reusável fora do Dashboard):
- `.rd-pcard__big-number` — número grande dentro de qualquer card (`font-size: 2.6rem`, `tabular-nums`)
- `.rd-pcard__big-hint` — hint pequeno abaixo do big-number

### Por que aba separada e não section dentro de "Geral"

Hierarquia clara: Dashboard = read-only (só lê estado); Geral = editáveis (toggles). Misturar criava confusão visual e quebraria a regra mental "abra Geral pra configurar, abra Dashboard pra checar".

---

## 📊 `inc/mod-stats.php` — Dashboard de Estatísticas (aba Statistics)

Agrega dados coletados pelo `mod-views.php` num dashboard read-only no painel admin. Não substitui Google Analytics — foca em **conteúdo** (que post performa, que gera discussão, tendência mensal). Filosofia "zero plugins externos" mantida: tudo nativo, dados ficam no DB do site.

| Constante | Valor | Significado |
|-----------|-------|-------------|
| `RD_STATS_CACHE_TTL` | `3600` (1 hora) | TTL dos transients agregados |
| `RD_STATS_CACHE_PREFIX` | `rd_stats_` | Prefixo das keys de cache pra batch delete |

**Gate:** sem gate de visibilidade — a aba Statistics e o dashboard sempre estão disponíveis. A coleta real de dados é controlada por `enable_views_tracking` (mod-views) — quando OFF, novas views não são contadas mas o histórico permanece visível aqui.

### Helpers de query (todos com cache transient)

- `rd_stats_total_views($window)` — `'all'` (SQL `SUM` direto), `'day'`/`'week'`/`'month'`/`'year'` (parsing PHP dos logs)
- `rd_stats_total_views_previous($window)` — total da janela ANTERIOR pra comparações tipo "vs semana passada"
- `rd_stats_top_posts_by_views($limit, $window)` — array de posts ordenados por views na janela
- `rd_stats_top_posts_by_comments($limit, $sort)` — `$sort = 'comments'` (SQL ORDER BY) ou `'ratio'` (PHP `usort` por engagement); inclui ratio em todos os casos
- `rd_stats_views_by_month($months_back)` — bucket por mês `['YYYY-MM' => count]` pros últimos N meses (pro gráfico K4)
- `rd_stats_calculate_trend($current, $previous)` — `{ pct, direction, label }` com `'up' | 'down' | 'flat' | 'new'`
- `rd_stats_get_post_primary_category($post_id)` — reusa a lógica do `mod-category-colors` (`_rd_primary_category` meta + fallback)

### Refresh cache

- `rd_stats_refresh_cache()` — SQL `DELETE` em todos os transients com prefixo `rd_stats_`
- `rd_stats_handle_refresh_request()` — hook `admin_init`, intercepta o link "Refresh now" do dashboard com nonce + capability check, redireciona limpando querystring

### Enqueue admin (gated)

- `rd_stats_admin_enqueue($hook)` — só roda em `toplevel_page_rd_options` + `?tab=estatisticas` (ou Dashboard tab + Segurança tab pra Chart.js de outros gráficos). Carrega só a lib `chartjs.min.js` (v4.5.1, ~204KB); o JS de init dos gráficos vive no bundle `admin-panel.js` (módulos stats/charts), que tem guard de `typeof window.Chart`. **Zero impacto em outras telas do admin e no frontend.**

### Render

- `rd_stats_render_dashboard()` — callback da section `sec_stats_dashboard` do Settings API. Renderiza os 4 widgets (K2/K1/K3/K4) usando os helpers acima e os data-attributes que o módulo stats do `admin-panel.js` consome no DOMContentLoaded pra desenhar o Chart.js.

---

## 🔥 `inc/class-rd-popular-posts-widget.php` — Widget "Most Read" (Popular Posts)

> **Convenção de nome:** este é o único arquivo de `inc/` que tem classe própria (`RD_Popular_Posts_Widget`). Por isso segue a regra WPCS `WordPress.Files.FileName` (`class-{kebab-case}.php`) em vez do padrão `mod-*.php`. Renomeado em 2026-05-23 durante a Wave 10 housekeeping. Todos os outros arquivos `mod-*.php` são function-only e mantêm a convenção `mod-*`.

**Primeiro widget WP nativo do tema.** Classe `RD_Popular_Posts_Widget extends WP_Widget` registrada em `widgets_init`. Aparece em **Aparência → Widgets** sob o nome "ReloadeD: Popular Posts" — o admin arrasta pra qualquer sidebar registrada (no momento, só "Main Sidebar").

**Diferente dos blocos hardcoded no `sidebar.php`** (apenas os anúncios agora) que são chamadas de função fixas — esse é controlado 100% pela UI nativa de widgets do WP. Admin decide se/onde colocar e configura sem editar código. (Discord e Apoie o Projeto também já são widgets `WP_Widget`.)

### Configuração no admin (`form()`)

3 campos no widget config:

- **Title** (text) — default `"Most Read"` (traduzido via .po em pt-BR)
- **Time window** (select) — `all` (default) / `year` / `month` / `week`
- **Number of posts** (select 3-15) — default 5

### Output frontend (`widget()`)

Lista vertical (`<ul class="rd-popular-posts">`) com item per-post (`<li class="rd-popular-item">`) contendo:

- **Thumbnail 16/9** (100x56) via `get_the_post_thumbnail()` com `loading=lazy`. Fallback `linear-gradient` sutil pra posts sem featured image (evita imagem quebrada)
- **Título** clamped a 2 linhas com ellipsis
- **Views floating** no canto inferior direito (SVG ícone 👁️ + número, mesmo padrão dos `.entry-meta` / `.rd-card-meta` nos cards do tema)
- **Empty state** ("No popular posts yet.") quando a janela escolhida não retorna resultados

### Hover

- Título vira `$brand-blue-light`
- Thumb dá zoom suave (`transform: scale(1.05)`) — mesma sensação dos cards principais

### Dependências

- `rd_stats_top_posts_by_views($limit, $window)` em `mod-stats.php` — consulta com cache transient 1h
- `rd_format_views_number()` em `mod-views.php` — respeita config global full/compact

### Sanitização (`update()`)

- `title` → `sanitize_text_field`
- `limit` → clamp `max(3, min(15, absint(...)))`
- `window` → whitelist `['all', 'year', 'month', 'week']` ou fallback `'all'`

### CSS

Estilos próprios em `sass/components/_popular-widget.scss`. Pra herdar o **card glass shell** da sidebar (background, border, padding, shadow, h2 com linha azul), o `_sidebar.scss` foi refatorado pra que o seletor do shell inclua `.widget_block, .widget_rd_popular_posts`. Pra inscrever futuros widgets clássicos do tema nesse shell, basta adicionar o classname ao seletor.

---

## 🎬 `inc/class-rd-latest-video-widget.php` — Widget "Latest Video" (Último Vídeo)

**Segundo widget WP nativo do tema** (mesma convenção `class-{kebab-case}.php` do popular). Classe `RD_Latest_Video_Widget extends WP_Widget`, registrada em `widgets_init`. Aparece em **Aparência → Widgets** como "ReloadeD: Latest Video" e pode ser arrastado pra **qualquer área registrada** — Main Sidebar (`sidebar-1`) **ou** Footer (`footer-widget-area`). Esse é o ganho do modelo `WP_Widget`: posicionamento livre, sem toggle dedicado no painel.

Mostra o **vídeo mais recente** de uma categoria escolhida: varre os últimos posts da categoria e pega o **primeiro embed do YouTube** encontrado no conteúdo.

### Configuração no admin (`form()`)

- **Title** (text) — default `"Latest Video"`
- **Category** (select via `wp_dropdown_categories`) — categoria de onde puxar os vídeos. Per-instance: cada widget pode apontar pra uma categoria diferente

### Output frontend (`widget()`)

1. Lê categoria + título. Sem categoria escolhida → não renderiza nada.
2. `get_latest_video($cat_id)` busca os últimos `SCAN_LIMIT` (10) posts publicados da categoria (data desc) e roda `rd_youtube_extract_id()` no `post_content` de cada um — o **primeiro com YouTube válido** vence (pula posts sem vídeo). Nenhum vídeo na categoria → widget não renderiza.
3. Renderiza dentro de `<div class="rd-lvideo">`:
   - **Facade ON** (`facade_youtube`) → reusa `rd_youtube_facade_markup()` (mesmo `.rd-facade` click-to-load; `navigation.js` troca pelo iframe no clique)
   - **Facade OFF** → `<a class="rd-lvideo__poster">` com o mesmo thumbnail (`rd_youtube_cover_html()`) linkando pro **post** — a sidebar/footer nunca carrega um iframe pesado
   - **+ título curto** (`.rd-lvideo__title`) linkando pro post

### Cache

Resultado (ou marcador `'none'`) guardado no transient `rd_latest_video_{cat_id}` (TTL 1h). Invalidado em `save_post` + `before_delete_post` (`rd_latest_video_flush_cache()`): varre as categorias do post e limpa o transient de cada uma. A varredura roda no máximo 1×/hora por categoria, e um vídeo novo aparece assim que publicado.

### Dependências (todas em `mod-performance.php`)

- `rd_youtube_extract_id($text)` — acha o 1º ID de vídeo num texto (URL ou `post_content`)
- `rd_youtube_facade_markup($id, $t)` — wrapper `.rd-facade` (facade ON)
- `rd_youtube_cover_html($id)` — thumbnail + botão play (facade OFF)

### CSS

`sass/components/_latest-video.scss`. O caminho facade-ON reusa `.rd-facade` (já estilizado em `_facades.scss`); o `.rd-lvideo__poster` (facade OFF) espelha o visual mas como `<a>` — a classe `.rd-facade` é **evitada** ali pra não brigar com o handler de clique do `navigation.js`. Inscrito no card glass shell da sidebar via `.widget_rd_latest_video` no `_sidebar.scss`.

---

## 📣 `inc/class-rd-social-widget.php` — Widget "Redes Sociais" (Siga-nos)

Classe `RD_Social_Widget extends WP_Widget` ("ReloadeD: Social Networks"). Grade **2 colunas** de chips `[ícone] Nome da rede` (8 redes = 4 linhas de 2), cada chip linkando pra rede em nova aba.

**Divisão de responsabilidades (convenção do tema):** as URLs são **dado global do site** → ficam no painel (Integrations → Social Networks, compartilhadas com a fileira de ícones do footer/top-bar). O widget só decide **exibição**: título (default "Follow Us") + 1 checkbox por rede. Sem contagem de seguidores — número ao vivo exigiria credencial de API por rede, e texto manual envelhece.

- Rede renderiza só se **marcada E com URL no painel**; sem URL, o checkbox aparece desabilitado no form com aviso. Nenhuma qualificada → widget não renderiza nada
- **Catálogo/ícones**: `rd_social_get_networks()` + `rd_social_get_icons()` em `mod-social.php` — extraídos do `rd_render_social_icons()` (fonte única de SVGs pro footer, perfis de autor e widget)
- CSS: `sass/components/_social-widget.scss`; card glass shell via `.widget_rd_social` no `_sidebar.scss`

---

## 🔐 `inc/mod-security.php` — Headers HTTP defensivos + Hardening

### Headers HTTP

Quando `enable_security_headers = 1` (aba **Performance**) e estamos no frontend (não admin):

- `X-Frame-Options: SAMEORIGIN` — anti-clickjacking
- `X-Content-Type-Options: nosniff` — anti MIME sniffing
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: ...` — limita features sensíveis (camera, microfone, geolocalização)

CSP (Content Security Policy) vive em módulo separado — ver `inc/mod-csp.php` abaixo.

### Disable XML-RPC

Quando `disable_xmlrpc = 1` (aba **Segurança**):

- **Interceptor no `init`**: requests pra `/xmlrpc.php` (GET ou POST) devolvem **403** com `Content-Type: text/plain` + corpo `XML-RPC disabled.` — fecha a mensagem hardcoded que o WP imprime mesmo antes dos filtros rodarem
- **`xmlrpc_enabled` → `__return_false`**: redundância de segurança caso alguma rota chegue no servidor XML-RPC sem passar pelo arquivo
- **Header `X-Pingback` removido** de todas as respostas via filtro `wp_headers`
- **`<link rel="EditURI">` (RSD)** removido do `<head>` via `remove_action('wp_head', 'rsd_link')` — fecha o vetor de descoberta do endpoint

Default ON em novas instalações. Mantenha ligado a menos que use ativamente um app mobile remoto ou pingbacks/trackbacks.

### WSOD branded (erro 500)

Customiza a tela "There has been a critical error on this website" que o WP 5.2+ mostra quando o PHP fatala (out of memory, exception não tratada, etc.):

- **`wp_php_error_args`** — sobrescreve o título da aba do navegador com `Critical Error - {nome do site}`
- **`wp_php_error_message`** — retorna HTML completo (com `<style>` inline + card markup) que sobrescreve o template grayscale padrão. Resultado: card dark themed igual à manutenção (logo, gradient stripe, fade-in animation, brand colors)

A técnica aproveita que o `_default_wp_die_handler` renderiza nosso HTML dentro do `<div class="wp-die-message">` — os styles inline (`body#error-page`, `.rd-wsod-card`) sobrescrevem o cinza default sem precisar do `wp_head`.

Por que **não dá pra usar enqueue normal**: em fatal o tema pode estar quebrado. Inline funciona em qualquer cenário.

Por que **duplicado** ao invés de reusar o helper de manutenção: em código que roda durante crash, evitar dependência cross-module reduz risco de cascata de erros.

---

## 🔒 `inc/mod-login-protection.php` — Hide Login URL + Rate Limit + Anti Enumeration + Anti-Leak

Quatro camadas defensivas contra brute-force e tentativas automáticas no `/wp-login.php`. Implementado como item da Wave 8.5 em 2026-05-23.

### Camada 1 — Hide Login URL (opt-in)

Field `login_secret_slug` na aba Segurança → "Login Protection". Quando preenchido (3-50 chars, alfanumérico + hífen, lista de reservados rejeitada):

- `/wp-login.php` retorna **404 brandeado** (template `404.php` do tema) pra visitantes anônimos
- Login só é acessível via `/{slug}` (ex: `/painel-secreto`)
- **Logged-in users mantêm acesso** ao `/wp-login.php` pra logout e outras ações admin
- Filters `login_url`, `site_url`, `network_site_url`, `logout_url`, `lostpassword_url` reescrevem todas as URLs de login geradas pelo WP pra usar o slug — emails de notificação, reset de senha, etc.

**Detecção do request** via hook `wp_loaded` priority 1 (último action antes do template, garantia de que tema + plugins + init já carregaram):
- Path matches slug → `rd_login_serve_via_slug()` faz `require_once ABSPATH . 'wp-login.php'` + `exit`
- `SCRIPT_NAME` é `/wp-login.php` (setado pelo Nginx, não-spoofável) + não tem `RD_LOGIN_VIA_SLUG` definido + user não-logado → 404 + exit

**Variable scope gotcha em `require_once` de wp-login.php**: wp-login.php foi escrito assumindo script global scope. Quando required de dentro de uma função, suas variáveis (`$user_login`, `$error`, `$action`, etc.) ficam locais à função, e WP 7.0 reporta `Undefined variable` que vazam HTML dentro dos campos do form. Por isso `rd_login_serve_via_slug()` declara essas variáveis como `global` e pré-inicializa `$user_login` e `$error` a strings vazias antes do require.

### Camada 2 — Rate Limit

Fields `login_rate_limit_max` (default 5) e `login_rate_limit_window` minutos (default 15).

- **Hook `wp_login_failed`** → incrementa transient `rd_login_attempts_{md5(IP)}` com TTL da janela
- **Hook `wp_login`** (sucesso) → deleta o transient (counter zerado)
- **Filter `authenticate` priority 30** → retorna `WP_Error('rd_rate_limited')` quando IP atinge o limite, **antes** do password check (atacante não desperdiça CPU servidor)

Reusa `rd_get_client_ip()` em `inc/core.php` (Wave 9 A) — header-spoofing-proof via whitelist Trusted Proxy do painel.

### Camada 3 — Anti-leak via /wp-admin/ (ativa quando slug está set)

Sem essa camada, qualquer anônimo hit em `/wp-admin/` causa WP a redirecionar pra login URL (que com nosso filter vira `/{slug}`). O `Location: /{slug}?...` no header da response **vaza o slug** pra qualquer bot que faça `curl -I /wp-admin/`.

**Fix:** hook em `init` priority 1 (`rd_login_block_wpadmin_for_anonymous`) intercepta requests anônimos pra `/wp-admin/*` e redireciona pra `home_url('/')` em vez de pro login. Bot só vê `Location: /` — slug preservado.

**Exceções (endpoints com actions públicas legítimas, deixamos WP gerir):**
- `admin-ajax.php` — detectado via `wp_doing_ajax()`. Actions `wp_ajax_nopriv_*` (ex: nosso `rd_track_view`, `rd_search_redistribute`) continuam funcionais
- `admin-post.php` — detectado via `basename($_SERVER['SCRIPT_NAME'])`. Actions `admin_post_nopriv_*` continuam funcionais

Resultado:
- Bot bate em `/wp-admin/` → redirect pra `/` (home) — sem leak
- Bot bate em `/wp-admin/admin-ajax.php?action=rd_track_view` → WP processa normalmente
- Você logado bate em `/wp-admin/options-general.php` → acesso direto, sem redirect

### Camada 4 — Anti User-Enumeration (default ON)

Toggle `login_hide_user_enumeration` (default 1).

WordPress default mostra mensagens distintas pra "user não existe" vs "senha errada", permitindo atacante enumerar usernames válidos antes de brute-forcing. OWASP recomenda mensagem genérica há décadas.

**Filter `wp_login_errors` priority 100** intercepta o `WP_Error` agregado:
- Se contém qualquer dos códigos `incorrect_password`, `invalid_username`, `invalid_email`, `invalidcombo` → substitui por `WP_Error('rd_invalid_credentials', 'Error: Invalid username or password.')`
- Outros códigos (lost password sent, etc.) passam inalterados

### Recovery hatch

Se admin se trancar fora ao salvar slug e esquecer:

```bash
# Via WP-CLI
wp option patch update rd_settings login_secret_slug ""
```

> Em último caso (sem acesso WP-CLI), `rd_settings` é uma option serializada — recovery direto via SQL/phpMyAdmin requer cuidado pra manter o serialization válido.

### Filosofia

- Defesa em profundidade real (3 camadas independentes — bypassar uma não anula as outras)
- Zero dependências externas (nada de plugins de segurança "WordfenceLite"-style)
- Logged-in users mantêm acesso ao `/wp-login.php` (importante pra logout/admin URL)

---

## 🛡️ `inc/mod-csp.php` — Content Security Policy (Report-Only)

Adiciona o header `Content-Security-Policy-Report-Only` no frontend com policy calculada dinamicamente. NÃO bloqueia nada — coleta relatórios de violações pra auditoria. Útil pra mapear o que o site carrega (scripts, styles, frames, imagens, fontes) antes de promover pra `Content-Security-Policy` (enforce).

| Constante | Valor | Significado |
|-----------|-------|-------------|
| `RD_CSP_REPORTS_OPTION` | `rd_csp_reports` | Option onde os reports são armazenados |
| `RD_CSP_REPORTS_MAX` | `100` | FIFO — descarta reports antigos quando atinge o limite |

**Gate:** toggle `enable_csp_report_only` em **Painel → Segurança** (default OFF). Quando ON, header é enviado no frontend (não no admin); endpoint REST permanece sempre registrado mas inerte quando feature OFF.

### Policy builder

`rd_csp_build_policy()` monta a string CSP dinamicamente:

- **Baseline restrita**: `default-src 'self'`, `object-src 'none'`, `base-uri 'self'`, `form-action 'self'`
- **`script-src`**: `'self' 'nonce-XXX' 'strict-dynamic' 'unsafe-inline'`. Em browsers CSP3 (modernos), nonce + strict-dynamic anulam o `'unsafe-inline'` automaticamente — só scripts com nonce executam. `'unsafe-inline'` fica como graceful degradation pra browsers pré-CSP3 (Safari < 15.4)
- **`style-src`**: `'self' 'nonce-XXX' 'unsafe-inline'`. Mesma lógica do script-src, sem strict-dynamic (CSP3 spec — strict-dynamic só aplica a scripts)
- **`img-src`**: `'self' data: https:` — **permissivo intencional**. Posts copiados de markdown externo (GitHub camo, shields.io, imgur, ...) trazem imagens de origens variadas. Como `https:` engloba todas, dispensa whitelist (que viraria fardo de manutenção). Vector de ataque via imagens é muito limitado em CSP — risco real fica em `script-src`/`frame-src` que continuam restritos
- **Origens condicionais** adicionadas conforme integrações ativas (em `script-src`/`connect-src`/`frame-src` — `img-src` já cobre tudo via `https:`):
  - GA configurado → `googletagmanager.com`, `google-analytics.com`, `*.analytics.google.com`
  - Clarity ID → `*.clarity.ms`
  - FB Pixel → `connect.facebook.net`, `www.facebook.com`
  - TikTok Pixel → `analytics.tiktok.com`
  - Plausible domain → `plausible.io`
  - Umami URL → extrai origem do `umami_script_url`
  - Discord widget ON → `ptb.discord.com`, `discord.com` (frame-src)
  - YouTube facade ON → `youtube.com`, `youtube-nocookie.com` (frame-src)
  - Ads configurados (`rd_csp_ads_active()`: ad_global/zonas/widget) → AdSense: `pagead2.googlesyndication.com`, `googleads.g.doubleclick.net`, `tpc.googlesyndication.com` (script-src fallback pré-CSP3), iframes de anúncio + safeframes + `www.google.com` + `*.adtrafficquality.google` (frame-src), SODAR `*.adtrafficquality.google` + pagead2 (connect-src)
  - GA com Google Signals/Ads → `www.google.com` + `stats.g.doubleclick.net` (connect-src, beacons g/collect)
- **Custom Origins (3 textareas no painel)** — admin adiciona origens novas sem editar PHP:
  - `csp_custom_scripts` → script-src + connect-src
  - `csp_custom_frames` → frame-src
  - `csp_custom_styles` → style-src + font-src
  - Parser `rd_csp_parse_custom_origins()` valida formato (só HTTPS, sem keywords com aspas, sem wildcard puro)
- **`report-uri`**: endpoint REST próprio `/wp-json/rd/v1/csp-report`
- **Filtro de ruído nos reports** — descarta violação irrelevante **antes de gravar** (`rd_csp_report_is_noise()`):
  - Camada A (código): `source-file` com esquema de extensão (`chrome-extension://`, `moz-extension://`, `safari-web-extension://`, `webkit-masked-url://`)
  - Camada B (painel): host do `blocked-uri` na denylist `csp_report_denylist` (parser `rd_csp_parse_report_denylist()`). Ex.: `use.typekit.net` injetado por extensão sem `source-file`

### Nonce + strict-dynamic (Wave 8.5)

`rd_csp_nonce()` gera 16 bytes aleatórios (base64url, ~22 chars) por request, cacheado em `static`. Helpers expostos:

- `rd_csp_nonce()` — retorna o nonce do request atual ou `''` se CSP OFF
- `rd_csp_nonce_attr()` — retorna ` nonce="..."` pronto pra concatenar em `<script>`/`<style>` (ou `''`)
- `rd_csp_inject_nonce($html)` — injeta nonce em todas as tags `<script>` do HTML; idempotente (não duplica). Usado nos campos `ad_*` do painel (AdSense, FB Ads, etc.) pra nonce'ar snippets externos automaticamente

**Propagação automática em recursos WP-managed:**
- Filter `script_loader_tag` (priority 99) → adiciona nonce em todos os `<script src="...">` enqueued
- Filter `style_loader_tag` (priority 99) → adiciona nonce em `<link rel="stylesheet">` enqueued
- Filter `wp_inline_script_attributes` + `wp_script_attributes` → propaga pros inline adicionados via `wp_add_inline_script()`
- Filter `wp_inline_style_attributes` + `wp_style_attributes` → equivalente pra inline `<style>` modernos (WP 6.4+)
- **Output buffer late-stage no `wp_head`** → cobre o caso legacy do `WP_Styles::print_inline_style()` que usa `printf()` direto sem filter (atinge `wp_add_inline_style($handle, $data)` do WP core e plugins). Aplica nonce via regex em qualquer `<style>` sem nonce no HTML final do request. Idempotente.

**Inline scripts/styles do tema** (header anti-FOUC, integrações de tracking, JSON-LD, critical CSS, category colors) instrumentados manualmente com `rd_csp_nonce_attr()`.

### Endpoint REST

`POST /wp-json/rd/v1/csp-report` — recebe relatórios do browser, valida estrutura padrão CSP-2 (`{ "csp-report": {...} }`), aplica whitelist defensiva de campos e armazena em option `rd_csp_reports` (FIFO de 100 entries, autoload=no).

### Helpers públicos

- `rd_csp_get_reports()` — retorna reports armazenados (mais novo primeiro)
- `rd_csp_clear_reports()` — zera reports
- `rd_csp_handle_clear_request()` — hook `admin_init` que intercepta o link "Clear reports" do painel (nonce + capability check, redirect)
- `rd_csp_render_reports_panel()` — callback de section que renderiza tabela de reports na aba Segurança

### Promovendo pra enforce (futuro)

Quando policy estiver madura (30+ dias de monitoramento sem violações inesperadas — **timer resetou em 2026-05-23** com a implementação do nonce/strict-dynamic):

1. Ligar o toggle **⚠️ Enforce Mode** em Painel → Segurança (instantâneo, sem deploy)
2. ~~Considerar remover `'unsafe-inline'` migrando pra nonce/hash~~ ✅ feito em Wave 8.5 (2026-05-23) — nonce + strict-dynamic ativos, `'unsafe-inline'` mantido como fallback pra browsers pré-CSP3
3. Adicionar `Reporting-Endpoints` header + `report-to` directive pra usar Reporting API moderna (CSP 3)

---

## 🎠 `inc/mod-carousel.php` — Carrossel de Destaques

Carrossel full-width entre o header e a main (home página 1, via `rd_render_carousel()` no `index.php`). Layout "peek": slides de ~84% com snap central — primeiro/último colam nas bordas (snap `start`/`end`, sem vão na carga).

- **Fontes** (`carousel_source`): `sticky` (posts fixados ★, mais recentes primeiro, fallback pros últimos) / `category` / `latest`. Só posts **com imagem destacada** (`meta_key _thumbnail_id`)
- **Anti-duplicação:** `rd_carousel_exclude_from_home()` (`pre_get_posts`) exclui os IDs do carrossel da main query da home; com source sticky também desliga o sticky-prepend do WP (o carrossel **é** a vitrine dos fixados)
- **Performance:** slide 1 = candidato LCP (`eager` + `fetchpriority=high`), demais `lazy`; reusa o crop `rd-full-banner` (sem regenerate); `sizes` explícito espelhando o CSS (`88vw` mobile / `84vw` desktop, cap 1210px — o default do WP fazia o browser sempre baixar 1200w pra slots de ~630px); `carousel.js` só enfileira quando o carrossel renderiza
- **Slide:** chip de categoria (`.post-tag.tag-{slug}`, cores do admin) + chapéu (`rd_get_post_overline_html()` contexto `carousel`) + título clampado em 2 linhas, sobre gradiente fixo
- **A11y:** `aria-roledescription` carousel/slide, "x de N", setas/dots com labels, navegação por teclado
- Card "Carousel" no Dashboard (toggle inline + engrenagem + nº de slides)
- Sem JS: fileira swipeável nativa (controles ocultos via gate `.is-ready`)

---

## 🏠 `inc/mod-home.php` — Layout configurável da home

Controla o layout da home (`index.php`). A home é uma vitrine fixa: **2 cards grandes (hero)** sempre no topo + até três seções opcionais que reúsam os layouts de card da busca (Grid/Vertical/Compact, via `rd_render_post_card()` de `post-card.php`). Google não é usado aqui.

### Configuração

3 selects no painel (aba General → Home Page): `home_layout_grid` (0/3/6/9), `home_layout_vertical` (0/1/2/3), `home_layout_compact` (0/2/4/6). As quantidades são múltiplos da contagem de colunas de cada layout (3/1/2), pra preencher linhas completas.

- `RD_HOME_HERO_COUNT` (= 2) e `RD_HOME_SECTION_CHOICES` — constantes com a ordem fixa (grid → vertical → compact) e as quantidades válidas.
- `rd_home_get_active_sections()` — `[ layout => quantidade ]` só das seções ligadas (quantidade válida ≠ 0), na ordem fixa. Vazio = fallback clássico.
- `rd_home_is_active()` / `rd_home_total_posts()` — atalho booleano e total de posts (`2 + soma das quantidades`).
- `rd_home_modify_query()` — hook `pre_get_posts` que ajusta `posts_per_page` da home quando o layout está ativo. Guard estrito (`is_main_query` + `is_home` + front-end), mutuamente exclusivo com o hook da busca (`is_search`).
- `rd_render_home_hero_card()` — markup do card grande (extraído do `index.php` histórico). Compartilhado pelo fallback clássico e pelo layout novo (DRY). Os 2 primeiros posts (`current_post < 2`) recebem `loading=eager` + `fetchpriority=high` (candidatos a LCP); o resto fica `lazy`.

### Distribuição na home (vs. busca)

Diferente da busca, a home **não** usa `rd_render_distribution()` nem AJAX: como não há escolha do visitante, tudo é decidido server-side e renderizado de uma vez. O `index.php` consome a loop principal em fatias sequenciais — 2 pros hero, depois cada seção ativa pega sua quantidade, na ordem fixa, pulando as desligadas (as de baixo "sobem"). Como o `posts_per_page` é exatamente `2 + soma`, nada sobra nem repete. Wrapper vazio é evitado com `if (! have_posts()) break;` antes de abrir cada seção.

### Independência total da busca

Namespaces separados (`home_layout_*` vs `search_layout_*`, `rd_home_*` vs `rd_search_*`), hooks `pre_get_posts` mutuamente exclusivos, e CSS escopado (`.rd-home-sections`). A busca não é tocada em nenhum arquivo — o D1 é puramente aditivo.

## 🔍 `inc/mod-search.php` — Sistema de busca multi-layout

Maior módulo do tema. Resolve o problema de "como mostrar resultados em 4 formatos diferentes ao mesmo tempo".

### Algoritmo de distribuição

`rd_search_distribute_posts($posts, $active_layouts)`:

1. Posts vazios → distribuição vazia
2. Visitor tem só 1 layout ativo → ele pega TUDO
3. Visitor desativou tudo → Compact pega tudo (rede de segurança)
4. Poucos resultados (< CAP) com múltiplos ativos → vai pro Compact
5. Distribuição normal: Grid → Vertical → Compact → Google, com CAP por layout
6. Overflow → vai pro **último layout ativo** (respeita preferência do visitante)

### AJAX redistribution

Quando o visitante clica nos chips (`Grid | Vertical | Compact | Google`):

1. JS atualiza preferências no localStorage (`rd_search_prefs`)
2. Se `paged > 1` → full reload pra page 1 (evita pagination dessincronizada)
3. Se `paged == 1` → AJAX `rd_search_redistribute` busca novo HTML
4. Backend roda a query, distribui com novos layouts ativos, retorna HTML
5. JS troca o `innerHTML` do `.rd-search-results-containers`

### Hooks

- `pre_get_posts` filter: ajusta query da busca (no_found_rows, paged, etc.)
- AJAX action `rd_search_redistribute` (logged + nopriv)
- `wp_localize_script` injeta `rd_search_data` (ajaxurl, nonce, query, etc.)

---

## 🏷️ `inc/mod-menu.php` — Categoria principal

Resolve o problema do "múltiplos itens do menu destacados".

### Helper

`rd_get_primary_category_id($post_id)` — cascata:
1. Meta `_rd_primary_category` (escolha do autor)
2. Primeira categoria do post (fallback)

### Filtro

`wp_nav_menu_objects` filter:

1. Em singles, identifica o item de menu apontando pra categoria principal
2. Sobe a árvore de menu (via `menu_item_parent`) coletando ancestrais
3. Pra cada item NÃO no "keep set", remove classes `current-*` / `current_*`

Resultado: só a categoria principal e seus ancestrais ficam destacados.

---

## 🔗 `inc/mod-related-posts.php` — Posts relacionados no fim do single

Bloco de 3 cards de posts relacionados, renderizado entre o `</article>` e os comments do single (depois do author-bio quando ambos ativos).

**Gate:** `enable_related_posts` (default ON) em General → Content.

### Algoritmo (2 stages)

1. **Stage 1:** Resolve categoria primária via meta `_rd_primary_category` (fallback: primeira categoria). `WP_Query` pega até 10 candidatos da mesma categoria, ordem `date DESC`.
2. **Stage 2:** Pra cada candidato, conta tag overlap com o post atual via `array_intersect`. `usort` estável ordena por overlap DESC — data DESC preservada quando empate.
3. Retorna top 3 IDs.

### Cache

Transient `rd_related_{post_id}` TTL 1h. Invalidado em `save_post` (só do próprio post — TTL natural cobre eventual consistency em related de terceiros).

### Render

`rd_render_related_posts($post_id)` chama `rd_render_post_card('grid')` do `post-card.php` — visual idêntico ao search/home. Wrapper combina classes `.rd-wrapper-grid` (herda estilo dos cards) + `.rd-related-posts__grid` (override do grid-template-columns pra 3/2/1 cols).

Mobile breakpoint: 3º card oculto em tablet (768-1023px) pra evitar 1 card sozinho em row 2.

---

## 👤 `inc/mod-author-bio.php` — Author bio box no fim do single

Caixa com avatar + nome + bio + ícones sociais + link "View all posts by X" no fim do single, antes do bloco de related posts.

**Gate:** `enable_author_bio` (default ON) em General → Content.

### Graceful degradation

Se autor tem bio VAZIA **e** ZERO redes sociais cadastradas → box não renderiza (anti-ruído — autor já aparece no `.entry-meta` do single).

### Visual

Reusa classes `.rd-author-*` de `_page-author.scss` (visual idêntico ao header da `/author/{slug}/` archive). Modifier `.rd-author-header--single` ajusta margins + estiliza `<a>` dentro do nome (no archive é `<h1>` puro).

Helper `rd_render_social_icons($user_id)` puxa metas sociais por user (multi-author ready — cada redator preenche o próprio perfil em `Users → Profile`).

---

## 🕐 `inc/mod-reading-time.php` — Tempo de leitura estimado

Indicador "X min read" no `.entry-meta` do single, alinhado à direita via `margin-left: auto` no flex container.

**Gate:** `enable_reading_time` (default ON) em General → Content.

### Algoritmo

`wp_strip_all_tags(strip_shortcodes($content))` → texto puro. `preg_match_all('/\S+/u', $text)` conta palavras (UTF-8 safe — `str_word_count()` falha com acentos). `ceil($word_count / 200)` minutos, mínimo 1.

### Cache

Post meta `_rd_reading_time` recalculado em todo `save_post`. Lê instantâneo no render (zero overhead). Pra posts pré-instalação do módulo, calcula on-the-fly + grava cache na primeira leitura.

### i18n

`_n('%d min read', '%d min read', $minutes, 'reloaded')` — plural-aware (mesmo singular/plural em EN, mas suporta linguagens com plural diferente).

---

## 📑 `inc/mod-table-of-contents.php` — TOC sticky com FAB collapsible

FAB flutuante no canto superior direito de cada single (quando há ≥3 headings h2/h3). Click expande painel collapsible com lista nested + smooth-scroll pra cada seção.

**Gate:** `enable_table_of_contents` (default ON) em General → Content.

### Arquitetura

**Parsing (2 chamadas eager):**
1. Template `single.php` chama `rd_render_table_of_contents()` ANTES de `the_content()` — precisa do HTML processado pra parsear. Helper `rd_toc_get_cached_data($post_id)` faz `apply_filters('the_content', $raw)` (com remove/re-add do próprio filter pra evitar recursão), parseia via DOMDocument, retorna `[entries, modified_content]`. Resultado cacheado em static por `post_id`.
2. Filter em `the_content` priority 25: lê do cache estático e retorna `modified_content` (com IDs adicionados nas headings).

**Auto-IDs nas headings:** sluggify via `sanitize_title()` + collision counter (`slug-2`, `slug-3`...). IDs manuais existentes são preservados.

**Estrutura HTML:**
```
<article class="single-post-content">      ← position: relative
    <div class="rd-toc-anchor">             ← position: absolute, height:100% do article
        <div class="rd-toc__sentinel"></div> ← invisível, marca natural top do FAB
        <div class="rd-toc">                ← position: sticky top:20px
            <button class="rd-toc__fab">    ← FAB clicável
            <nav class="rd-toc__panel">     ← collapsible
        </div>
    </div>
    <header class="entry-header">...</header>
    ...
</article>
```

### CSS variable compartilhada

`--rd-content-pad-inline` (padding horizontal do conteúdo — 60px desktop, 40px ≤1024px, 25px ≤768px; e 0.5rem no mobile <600px só nos posts) é definida em `_variables.scss` e compartilhada por `.single-post-content` e `.rd-page-article`. O `right` do `.rd-toc-anchor` lê esse token, então mudanças de padding são auto-seguidas pelo TOC sem alterar `_toc.scss`. Os posts redeclaram o token no breakpoint <600px (mantendo o mobile justo) e, como o anchor está aninhado dentro, o FAB acompanha automaticamente.

### Sticky detection via IntersectionObserver

`rd_toc__sentinel` invisível posicionado no top do anchor (= natural position do FAB). IntersectionObserver com `rootMargin: '-20px 0 0 0'` (matching sticky `top: 20px`) detecta quando sentinel passa do top da viewport → adiciona `.is-stuck` no `.rd-toc` → CSS ativa `box-shadow` ("decolagem visual").

### JS (`assets/js/toc.js`)

- Toggle FAB → painel open/close
- Click em link → smooth-scroll (via CSS `scroll-behavior: smooth` no `html`) + close
- Click outside / ESC → close
- IntersectionObserver pra `.is-stuck`

### Mobile

Painel em viewport <600px: `position: fixed; top: 70px; right: 20px; width: calc(100vw - 40px)` — quase fullscreen, abre a partir do FAB sticky (não do canto inferior).

---

## 🔍 `inc/mod-search-suggestions.php` — Autocomplete na busca

Dropdown de até 5 posts relevantes que aparece ao usuário digitar nos search inputs.

**Gate:** `enable_search_suggestions` (default ON) em General → Search Page.

### Cobertura

2 inputs do tema:
- `.search-field` (busca expansível do header desktop)
- `.menu-search-field` (busca dentro do painel hambúrguer)

### AJAX endpoint

`wp_ajax_rd_search_suggestions` + `wp_ajax_nopriv_*` — disponível pra anônimos + logados.

Sanitiza query (min 3 / max 50 chars), `WP_Query` com `s={query}`, `posts_per_page=5`, `orderby=relevance`. Coleta thumb_url via `rd-micro` size (150×84 hard-crop, display 71×40 com DPR 2x retina).

### Cache

Transient `rd_sugg_{md5(lowercase(query))}` TTL 15 min. Auto-flush em `save_post` + `deleted_post` via batch DELETE por prefixo (queries esparsas em save_post — performance OK).

### Carregamento lazy por interação (v1.7.x)

O script **não é mais enfileirado** via `wp_enqueue_script`: ele só trabalha quando alguém foca um campo de busca, então não faz sentido entregar ~10 KB pra todo visitante no load. Em vez disso, `rd_search_suggestions_print_loader()` (hook `wp_footer`) imprime um snippet inline (~0,5 KB, nonce CSP) que escuta `focusin` nos campos de busca e, no **primeiro foco**, injeta o `<script>` real + o objeto de dados `window.rdSearchSugg` (que antes vinha de `wp_localize_script`). O injetado é confiado pelo CSP via `strict-dynamic`. Por isso o `search-suggestions.js` inicializa checando `document.readyState` — quando ele executa, o `DOMContentLoaded` quase sempre já disparou.

### JS (`assets/js/search-suggestions.js`)

- Debounce 250ms entre keystrokes
- `AbortController` cancela request anterior quando user continua digitando
- Dropdown injetado direto no `<body>` (escapa do `overflow: hidden` do `.search-form`)
- Posicionamento via `position: fixed` + coords calculadas a partir do `input.getBoundingClientRect()`
- Lógica de `left` diferencia 2 inputs: desktop ancora pela direita do form (compensa botão submit 40px); hamburger ancora pela esquerda do input com offset -13px
- Keyboard nav: ↑↓ percorre items + `scrollIntoView`, Enter abre, Esc fecha
- Click fora fecha; window scroll/resize esconde dropdowns (position:fixed não acompanha scroll)

### Strings i18n

`rdSearchSugg.i18n`: `noResults`, `seeAll`, `loading` — inlined no loader lazy (JSON via `wp_json_encode`), não mais via `wp_localize_script`. URL de busca completa via footer "See all results for X →".

---

## 📦 `inc/post-card.php` — Renderer reutilizável

Helper de renderização compartilhado entre `search.php`, `archive.php`, `author.php`, `404.php`.

### Funções

- `rd_post_card_text($text)` — escapa texto e aplica highlight do search query (se `is_search()`)
- `rd_get_formatted_views($post_id)` — bloco "👁️ N" (ícone SVG + número formatado)
- `rd_render_distribution($distribution)` — renderiza wrappers + cards de cada layout
- `rd_render_post_card($type)` — renderiza um card individual no layout escolhido

### Layouts suportados

| Layout | Estrutura |
|--------|-----------|
| `grid` | `<a>` envolvendo thumb + body (todo card clicável) |
| `vertical` | `<a>` envolvendo thumb + body (clicável; thumb à esquerda, body à direita) |
| `compact` | `<a>` envolvendo thumb pequena + título + data |
| `google` | Apenas título com link (URL acima + excerpt abaixo, sem thumb) |

---

## 🔄 `inc/mod-self-update.php` — Auto-update via GitHub Releases (custom)

Módulo próprio (~250 linhas) que conecta o tema às releases do GitHub e oferece o botão "Update Now" no admin, igual qualquer tema do wp.org — mas distribuído pelo nosso próprio canal. **Zero deps externas** — reusa `lib/Parsedown.php` pra renderizar release notes no modal "View Details".

Decisão histórica: cogitamos usar [`plugin-update-checker`](https://github.com/YahnisElsts/plugin-update-checker) (PUC, lib madura do Yahnis), mas descartado por 3 razões:

1. **Duplicação de Parsedown** — PUC vem com cópia própria do Parsedown em `vendor/Parsedown.php`, duplicando o que já temos em `lib/`.
2. **Bloat** — ~421 KB instalado, dos quais ~340 KB são código que nunca usaríamos (Bitbucket/GitLab APIs, Plugin updater, DebugBar, license keys, authentication, multi-idioma).
3. **Filosofia** — "zero plugins externos" deveria escalar pra "zero libs all-in-one" também.

Nosso custom resolve o happy path (1 repo público + 1 asset ZIP + tema) em código próprio mantido sob nosso controle.

### Canal de atualização (Stable/Beta)

Switch **"Beta channel"** no card "Theme Updates" do Dashboard (option `update_beta_channel`, default OFF = stable; usa a infra genérica `rd_dashboard_toggle` + whitelist):

- **Stable (OFF):** `/releases/latest` — o GitHub exclui prereleases por design (comportamento original)
- **Beta (ON):** `/releases?per_page=15` → pega a **primeira** entrada (a "ponta", prerelease ou estável, a mais nova). Quando uma estável sai depois das betas, ela é a ponta → instalações beta são **promovidas pra estável automaticamente**
- **Cache ciente do canal:** o payload do transient guarda `channel`; mismatch (trocou o canal) → refetch imediato. O render do card também confere e trata cache do outro canal como "never checked"
- **UX:** ao flipar o switch, o JS encadeia um "Check for updates" automático + sincroniza o badge **BETA** ao lado da "Latest version"
- **Sem downgrade:** beta→stable não rebaixa; a instalação fica na beta até uma estável MAIS NOVA sair (`version_compare` trata sufixos `-beta.N` corretamente)

### Fluxo end-to-end

1. Push commits convencionais (`feat:`, `fix:`, `refactor:`) pra `master`
2. Workflow `.github/workflows/master.yml` roda → `semantic-release` calcula nova versão semver e cria tag `v0.5.0`
3. Build step gera `reloaded.zip` com estrutura interna `reloaded/...` na raiz
4. `softprops/action-gh-release` anexa o ZIP ao GitHub Release como asset binário
5. WP do usuário, ao rodar `pre_set_site_transient_update_themes`, chama `rd_self_update_fetch_release()` que consulta `api.github.com/repos/Finallf/theme-reloaded/releases/latest` (cache 24h)
6. Encontra `v0.5.0` > `Version: 0.4.x` local → injeta entrada no transient `update_themes` do WP
7. Admin vê "Update available" no card do tema em Appearance → Themes
8. User clica "Update Now" → WP baixa `reloaded.zip` (asset binário) → descompacta em `wp-content/themes/reloaded/`

### Hooks WP usados (4)

| Hook | Função registrada | O que faz |
|---|---|---|
| `pre_set_site_transient_update_themes` | `rd_self_update_inject` | Injeta nossa release no array de updates pendentes do WP, quando latest > local |
| `themes_api` | `rd_self_update_themes_api` | Alimenta o modal "View version details" com nome/autor/changelog (markdown renderizado via Parsedown) |
| `upgrader_source_selection` | `rd_self_update_fix_source` | Defense in depth — garante que a pasta extraída vire `reloaded/` mesmo se algum dia o asset vier com nome diferente |
| `wp_ajax_rd_check_update` | `rd_self_update_handle_ajax` | Endpoint do botão "Check for updates" no card Dashboard |

### Cache: 1 transient TTL 24h

- Key: `rd_self_update_release`
- TTL normal: 24h
- TTL em erro de rede: 1h (curto, evita martelar GitHub durante outage)
- Invalidação manual: botão "Check for updates" no Dashboard ou expiração natural

### Por que asset binário e não tarball

GitHub gera tarball automático em `/releases/tag/v0.5.0/tarball` com pasta-raiz tipo `Finallf-theme-reloaded-abc123/` — WP esperaria `reloaded/style.css` no topo do ZIP mas veria `Finallf-theme-reloaded-abc123/style.css`, quebrando a descompactação.

Solução: `rd_self_update_fetch_release()` itera `data.assets` procurando o primeiro `.zip` — pega `reloaded.zip` (gerado pelo CI com estrutura correta) em vez do tarball. Defense in depth adicional via `rd_self_update_fix_source` renomeia a pasta se algum dia o asset chegar com nome inesperado.

### Pré-requisitos no repositório

- ✅ Header `Update URI: https://github.com/Finallf/theme-reloaded` no `style.css` — sinaliza pro WP core 5.8+ "esse tema tem updater externo, não procure no wp.org pelo slug `reloaded`". Previne colisão futura.
- ✅ Workflow CI gerando asset `reloaded.zip` anexado ao release.
- ✅ ZIP com estrutura interna `reloaded/...`.
- ✅ Branch `beta` marca releases como `prerelease: true` no `.releaserc` → API `/releases/latest` ignora por construção → usuários só veem stable releases de `master`.

### UI no Dashboard

Card "Theme Updates" entre Activity Trend e Quick Actions (`rd_dashboard_render_updates_card()` em `inc/mod-dashboard.php`):

- **Current version** — lida do `style.css`
- **Latest version** — lida do cache (sem disparar fetch novo)
- **Last check** — `human_time_diff()` do `checked_at`
- **Status badge** — "Up to date" (success) / "Update available" (warning) / "Never checked" (neutral)
- **Botão "Check for updates"** — canto superior direito do card, dispara AJAX que invalida cache + re-fetcha imediato + atualiza inline (sem reload)
- **CTA condicional** — quando há update, mostra "Go to Themes → Update Now" + "View release on GitHub"

JS handler no módulo self-update do bundle `assets/js/admin-panel.js`. i18n localizado via `wp_localize_script` (`rdSelfUpdate.i18n`).

### Filosofia

- **Sem call-home pra serviço próprio.** GitHub é a infra. Zero custo, zero ponto único de falha sob nosso controle.
- **Sem telemetria.** Só consulta API pública anônima (60req/h por IP — 1× a cada 24h é folgadíssimo).
- **APIs WP estáveis.** Os 4 filters usados existem desde WP 2.8+, blindagem natural contra mudanças de versão.

---

## 📦 Libs 3ª parte runtime (`lib/`)

Separação intencional entre **runtime** (vão pro ZIP do usuário, ficam em `lib/`) e **dev** (PHPCS/WPCS via Composer, ficam em `vendor/`, gitignored, nunca vão pro ZIP).

| Subpasta | Lib | Versão | Onde é usada |
|---|---|---|---|
| `lib/Parsedown.php` | [Parsedown](https://github.com/erusev/parsedown) (Emanuil Rusev) | 1.7.4 | Markdown nos posts (`mod-performance.php`) + sanitização Markdown em comentários (`mod-general.php`) |
| `lib/prism.js` | [Prism](https://prismjs.com) | 1.30.0 | Syntax highlighting de code blocks em singles/pages (`mod-performance.php`) |
| `lib/chartjs.min.js` | [Chart.js](https://www.chartjs.org) | 4.5.1 | Gráficos no admin: Stats Dashboard, CSP doughnut, Activity Trend (`mod-stats.php`) |

### Por que `lib/` e não `vendor/`?

Decisão arquitetural (2026-05-29): `vendor/` é convenção universal Composer — toda ferramenta (IDE, scanner, GitHub language detection) assume que é deps gerenciadas via `composer install`. Misturar runtime do tema lá:
- Confunde quem cai no projeto pela 1ª vez.
- Quebra o SFTP-ignore intuitivo (admin sobe runtime mas não quer subir PHPCS/WPCS).
- Forçava whitelist seletiva no `.gitignore` com pitfalls (trailing slash bloqueando descida).

Separar em `lib/` (convenção WP comum — Sage/Roots usam `app/`, maioria dos custom themes usa `lib/`) resolve os 3 problemas: `.gitignore` volta a ser limpo (`vendor/` ignorado inteiro), SFTP-ignore vira óbvio, IDE para de marcar `vendor/squizlabs/` como "código do projeto".

### Regras de manipulação

- **Não editar diretamente.** Atualizações vêm do upstream.
- **Para atualizar uma lib**: baixar versão nova → substituir conteúdo da subpasta → atualizar a coluna "Versão" desta tabela → bumpar a string de versão no `wp_enqueue_script()` correspondente (cache-buster do WP).
- **`.gitignore`**: `vendor/` ignorado inteiro (Composer dev). `lib/` versionada normalmente.
- **Workflow CI**: rsync no `master.yml` exclui `vendor/` explicitamente (defense in depth caso alguém rode `composer install` no runner). `lib/` vai pro ZIP normalmente.
- **SFTP**: admin pode ignorar `vendor/` no cliente SFTP sem medo. `lib/` precisa ser sincronizada como qualquer outra pasta de runtime.

### Licenças (compatíveis com GPL-2.0+ do tema)

- Parsedown — MIT
- Prism.js — MIT
- Chart.js — MIT
- plugin-update-checker — MIT
