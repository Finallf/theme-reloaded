# 02 — Painel de Controle

O painel admin do ReloadeD fica em **`Aparência → ReloadeD`** (ou diretamente no item **"ReloadeD"** que aparece como menu top-level na sidebar do admin, logo abaixo do Dashboard).

Ele é dividido em **13 abas** (12 fixas + Statistics condicional), agrupando opções por **contexto de uso** — não por componente técnico. Toda a lógica vive em `inc/panel.php` (registro via Settings API) + módulos `inc/mod-*.php` (callbacks customizados e consumo das opções). Estrutura definida na **Wave 11** (Maio 2026).

> **Reorganização Wave 11:** O painel foi completamente reorganizado em Maio/2026. Slug interno `interface` foi renomeado pra `doacoes`; aba "Privacidade" foi unificada em "Segurança & Privacidade".

---

## 📚 Visão geral das abas

| # | Aba (label público) | Slug interno | Tipo |
|---|---|---|---|
| 1 | **Dashboard** | `dashboard` | Read-only — status do site, métricas e atalhos |
| 2 | **General** | `geral` | Configurações de UX, conteúdo e busca |
| 3 | **Images & Media** | `media` | Upload, qualidade, formatos next-gen, regenerar biblioteca |
| 4 | **Performance** | `performance` | Otimização de carregamento + bloat reducers |
| 5 | **Security & Privacy** | `seguranca` | LGPD, hardening, login protection, CSP |
| 6 | **Integrations** | `integracoes` | Analytics, marketing pixels, Discord widget |
| 7 | **SEO** | `seo` | Site verification, Open Graph, sitemap, robots.txt |
| 8 | **Social Networks** | `redes` | URLs das redes sociais (footer + top bar) |
| 9 | **Donations** | `doacoes` | GitHub Sponsors, PayPal, PIX |
| 10 | **Ads** | `ads` | Script global + banners por posição |
| 11 | **Statistics** | `estatisticas` | Tracking de views + dashboard de stats (top posts, growth chart) |
| 12 | **Maintenance** | `manutencao` | Modo manutenção + senha de dev |
| 13 | **Backup & Restore** | `backup` | Export/import de configurações em JSON |

Cada aba tem um **ícone Dashicons** no header de cada section, dando contexto visual instantâneo (engrenagem pra settings, escudo pra security, gráfico pra stats, etc.).

---

## 🏠 Aba 1 — Dashboard (`?tab=dashboard`)

Aba **read-only** que serve de landing quando admin abre o painel. Sem opções editáveis — só visualização de estado e atalhos. Renderizada por `rd_dashboard_render()` em `inc/mod-dashboard.php`.

**4 seções:**

### Site Status
Grid de **15 cards** em 5 colunas (3 linhas × 5 cards em desktop; 3 colunas em tablet 768-1023px; 1 coluna em mobile). Cada card mostra badge de estado + ação inline (toggle ou deep link, conforme apropriado):

| Card | Estados possíveis | Ação inline |
|---|---|---|
| CSP | `OFF` / `REPORT-ONLY` (info) / `ENFORCE` (danger) | ⚙ deep link `?tab=seguranca#sec_seg_csp` |
| Login Protection | `OFF` / `ON` (mostra slug em `<code>`) | ⚙ deep link `?tab=seguranca#sec_seg_login` |
| Maintenance Mode | `OFF` / `⚠️ ON` (success com emoji prefix) | switch (confirm dialog ao ligar) |
| Statistics Tracking | `OFF` / `ON` | switch |
| Critical CSS Inline | `OFF` / `ON` | switch |
| Next-gen Images | `OFF` / `ON` (mostra formato AVIF/WEBP/BOTH) | ⚙ deep link `?tab=media#sec_media_upload` |
| Top Bar | `OFF` / `ON` | switch |
| Comments | `OFF` / `ON` | switch |
| Markdown | `OFF` / `ON` | switch |
| Discord Widget | `OFF` / `ON` | switch |
| YouTube Facade | `OFF` / `ON` | switch |
| Breadcrumbs | `OFF` / `ON` | switch |
| LGPD Banner | `OFF` / `ON` | switch |
| Sitemap | `OFF` / `ON` | switch |
| Open Graph | `OFF` / `ON` | ⚙ deep link `?tab=seo#sec_seo_og` |

**Toggle switches inline:** componente `.rd-pswitch` (track + thumb animado). Clicar flipa via AJAX (`wp_ajax_rd_dashboard_toggle`) sem reload. Defesa em profundidade: whitelist server-side de 11 keys permitidas + nonce + `current_user_can('manage_options')` + validação estrita de valor. Maintenance Mode mostra confirm dialog ao **ligar** (operação que bloqueia visitantes); desligar é sempre seguro, sem confirm.

**Tooltips de nome:** passar o mouse no **nome** de cada card mostra uma explicação curta da feature, reusando o balão `[data-tooltip]` dos switches/engrenagens (fundo preto translúcido, centralizado sobre o card). As explicações vêm de `rd_dashboard_get_status_tooltips()` e são injetadas via o arg `tooltip` do `rd_panel_card_open()`.

**Layout estável dos controles:** o switch/gear fica sempre na **esquerda** do card e o badge à **direita** via `justify-content: space-between` no `.rd-dashboard-status-line`. Sem isso, a posição do controle "jumpava" 1px lateralmente quando o badge mudava de "ON" pra "OFF" (texto ~5px mais largo). Agora o ajuste cai no gap central, controle fica plantado.

**Deep link buttons:** ícone de engrenagem com tooltip estilizado (fundo escuro com setinha, visual igual ao Chart.js). Click abre a aba relevante e o browser scrolla nativo até a section via hash anchor — sem JS necessário.

### Quick Metrics
3 cards big-number com janelas **rolling** (não calendar):

- **Views Last 24h** — últimas 24h a partir de agora. Reusa `rd_stats_total_views('day')` com **bypass do transient cache** pra dados frescos a cada page load.
- **Posts Last 30 Days** — `now − 30 dias` rolling. Query direta no banco.
- **Pending Comments** — snapshot do estado atual via `wp_count_comments()->moderated`. Quando > 0, mostra link "Review queue".

### Activity Trend
Bar chart de **views por dia nos últimos 7 dias**. Função `rd_dashboard_get_views_7d()` parseia logs (`_rd_post_views_log`) sem cache. Quando não há dados (tracking nunca ativo ou recém-instalado), o chart mostra todas as barras zeradas — preview do layout futuro.

### Quick Actions
4-5 botões pra atalhar pras outras abas mais usadas:

- General Settings → `?tab=geral`
- Security & CSP → `?tab=seguranca`
- Images & Media → `?tab=media`
- Backup → `?tab=backup`
- Statistics → `?tab=estatisticas`

### Footer Info
Linha discreta com versões: tema, WordPress, PHP.

---

## 🗂️ Aba 2 — General (`?tab=geral`)

Recursos básicos de UI/UX + conteúdo + comentários + busca + feature flags. **5 sections** divididas por contexto.

### Section: Visual Shell (`sec_geral_shell`)
Layout visual do site (não conteúdo).

| Opção | Default | O que faz |
|---|---|---|
| `enable_top_bar` | ✅ | Top bar pequena com data + ticker de últimas + redes sociais |
| `enable_breadcrumbs` | ✅ | Breadcrumb trail abaixo do header (exceto na home). Schema.org BreadcrumbList sempre gerado |
| `sidebar_position` | `right` | Posição da sidebar em desktop (`right`/`left`). Mobile sempre stack |
| `back_to_top` | ✅ | Botão flutuante no canto inferior direito após scroll de 300px |
| `enable_theme_switch` | ✅ | Botão de troca dark/light mode no header |
| `default_theme_mode` | `system` | Modo inicial pra primeiros visitantes (`system`/`dark`/`light`) |
| `footer_subline` | _vazio_ | Linha extra abaixo do copyright (HTML básico permitido) |

### Section: Content (`sec_geral_content`)
Editor e renderização de posts.

| Opção | Default | O que faz |
|---|---|---|
| `enable_thumb_control` | ✅ | Toggle no editor pra esconder featured image em posts específicos |
| `enable_primary_category` | ✅ | Dropdown no editor pra destacar 1 categoria quando post pertence a várias |
| `enable_post_kicker` | ✅ | Campo "Overline" no editor (label jornalístico tipo INTERVIEW, EXCLUSIVE) |
| `excerpt_length` | `55` | Número de palavras nos excerpts automáticos (range 10-200) |
| `excerpt_text` | _vazio_ | Texto custom do botão "Read More" (vazio = default) |
| `markdown_enabled` | ✅ | Markdown nativo nos posts (Parsedown vendored no tema) |
| `prism_js` | ✅ | Syntax highlight pra blocos `<code>` (só carregado em posts) |
| `enable_related_posts` | ✅ | Bloco de 3 posts relacionados no fim do single (mesma categoria primária + overlap de tags, cache 1h por post). Grid 3-col desktop / 2-col tablet (3º card oculto pra evitar gap) / 1-col mobile |
| `enable_author_bio` | ✅ | Box com avatar + nome + bio + redes sociais do autor entre o footer do artigo e o bloco de related posts. Auto-hide se bio + socials estiverem todos vazios |
| `enable_reading_time` | ✅ | Indicador "X min read" alinhado à direita do `.entry-meta` do single. Cálculo: word_count / 200 WPM, cacheado em post meta (`_rd_reading_time`) recalculado em todo save_post |
| `enable_table_of_contents` | ✅ | FAB sticky no canto superior direito do post (renderizado quando há ≥3 headings h2/h3). Click expande painel collapsible com lista nested + smooth-scroll. Auto-IDs nos headings via DOMDocument no filter `the_content`. JS detecta sticky-state via IntersectionObserver no sentinel → ativa box-shadow ("decolagem" visual) |

### Section: Comments (`sec_geral_comments`)
Controle de comentários do site.

| Opção | Default | O que faz |
|---|---|---|
| `enable_comments_globally` | ✅ | Master switch — quando OFF, NENHUM comment renderiza no site (form, lista, count, widget) |
| `comment_a11y` | ✅ | Labels + autocomplete attributes pra acessibilidade do form |
| `comments_separator` | _vazio_ | Texto entre autor e post no link. Vazio = default WP; `&nbsp;` = esconde |

### Section: Search Page (`sec_geral_search`)
Layouts da página de resultados de busca.

| Opção | Default | O que faz |
|---|---|---|
| `search_layout_grid` | ✅ | Habilita layout Grid (cards lado a lado) |
| `search_layout_vertical` | ❌ | Habilita layout Vertical (imagem grande + excerpt) |
| `search_layout_compact` | ✅ | Habilita layout Compact (thumbnail + título inline). Também é safety net |
| `search_layout_google` | ❌ | Habilita layout minimalista estilo Google |
| `enable_search_suggestions` | ✅ | Dropdown de autocomplete (até 5 posts) abaixo dos search inputs (`.search-field` no header + `.menu-search-field` no hamburger). Trigger a partir de 3 chars, debounce 250ms, cache transient 15min server-side (auto-flush em save/delete post). JS posiciona via `position: fixed` + `getBoundingClientRect()` pra escapar do `overflow: hidden` do form |

---

## 🖼️ Aba 3 — Images & Media (`?tab=media`)

Aba criada na Wave 11 consolidando 4 controles dispersos. **2 sections** (1 standard + 1 custom-rendered).

### Section: Upload & Quality (`sec_media_upload`)

| Opção | Default | O que faz |
|---|---|---|
| `image_resizing` | ✅ | Hard crop em uploads (banners/cards sempre alinhados) |
| `jpeg_quality` | `80` | Qualidade de re-encode no upload (JPEG/WebP/AVIF). WP default = 82; tema = 80 |
| `enable_next_gen_images` | ✅ | Gera WebP/AVIF de cada upload + wrappa `<img>` em `<picture>` com `<source>` |
| `image_format_mode` | `avif` | Formato ativo: `avif` (default — ~50% menor), `webp` (~30% menor), ou `both` |

### Section: Regenerate Library (`sec_media_regenerate`) — *custom renderer*

Renderizada por `rd_img_render_panel_section()` em `inc/mod-image-formats.php`. Layout 30/70 (Wave 11 Fase G):

- **Card 30% (Server Capabilities):** detecção automática de Imagick/WebP/AVIF no servidor com badges `available`/`unavailable`. Status banner amber quando módulo está dormente (sem WebP nem AVIF).
- **Card 70% (Regenerate Action):** descrição expandida + botão "Start regeneration" + contador de attachments + progress bar AJAX em chunks de 10.

---

## ⚡ Aba 4 — Performance (`?tab=performance`)

Focada em **speed do frontend** e **bloat reducers**. **2 sections.**

### Section: Loading Optimization (`sec_perf_loading`)

| Opção | Default | O que faz |
|---|---|---|
| `preload_critical_fonts` | ✅ | Preload de Inter Regular + Poppins Bold no `<head>` (reduz FOIT/FOUT, melhora LCP) |
| `inline_critical_css` | ❌ | Inline do critical CSS + async load do stylesheet completo (requer `npm run critical:all` antes de ligar) |
| `facade_youtube` | ✅ | YouTube embeds viram thumbnail estática até clique. Reduz peso inicial |
| `facade_discord` | ✅ | Widget Discord vira placeholder estático até clique. Economiza conexão de terceiros |

### Section: Bloat Reducers (`sec_perf_bloat`)

| Opção | Default | O que faz |
|---|---|---|
| `disable_emojis` | ✅ | Remove emoji script do WP (browsers modernos renderizam nativos) |
| `disable_gutenberg_css` | ✅ | Remove CSS global do Gutenberg + block library (ideal pra quem usa Markdown) |
| `optimize_heartbeat` | ❌ | Reduz Heartbeat API (60s → 120s no admin; desativa no frontend) |
| `post_revisions_limit` | `5` | Limite de revisões por post (range 0-50; 0 = desativa) |

---

## 🛡️ Aba 5 — Security & Privacy (`?tab=seguranca`)

Hardening + LGPD + Login Protection + CSP. **5 sections.** Slug legacy `?tab=privacidade` redireciona pra cá.

### Section: LGPD & Cookies (`sec_seg_lgpd`)

| Opção | Default | O que faz |
|---|---|---|
| `enable_lgpd` | ✅ | Banner de consent no footer pra compliance |
| `lgpd_text` | _vazio_ | Texto custom do banner. Use `%s` onde quer o link da Política de Privacidade |

### Section: Hardening (`sec_seg_hardening`)

| Opção | Default | O que faz |
|---|---|---|
| `disable_xmlrpc` | ✅ | Desativa endpoint `/xmlrpc.php` (alvo de brute-force e DDoS amplification) |
| `hide_wp_ver` | ✅ | Remove meta `<meta name="generator">` com versão WP |
| `enable_security_headers` | ✅ | Envia X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy |
| `trusted_proxy_ips` | _vazio_ | Ranges CIDR de proxies/CDNs confiáveis (Cloudflare já incluído por default) |

### Section: Login Protection (`sec_seg_login`)

| Opção | Default | O que faz |
|---|---|---|
| `login_secret_slug` | _vazio_ | Slug customizado pra esconder `/wp-login.php` (vazio = feature dormente). Login só via `/{slug}`; URL antiga retorna 404 |
| `login_rate_limit_max` | `5` | Tentativas máximas por IP antes de bloqueio (15min) |
| `login_rate_limit_window` | `15` | Janela de lockout em minutos |
| `login_hide_user_enumeration` | ✅ | Substitui erros que revelam "user não existe" por mensagem genérica |

### Section: Content Security Policy (`sec_seg_csp`)

| Opção | Default | O que faz |
|---|---|---|
| `enable_csp_report_only` | ❌ | Envia header `Content-Security-Policy-Report-Only` com policy calculada das integrações ativas. Browser reporta violações pro endpoint `/wp-json/rd/v1/csp-report` (sem bloquear) |
| `csp_enforce_mode` | ❌ | ⚠️ Promove de Report-Only pra Enforce real. Ligar SÓ após 30+ dias de monitoramento sem violações inesperadas |
| `csp_custom_scripts` | _vazio_ | Origens custom pra `script-src` + `connect-src`. 1 por linha. Só HTTPS |
| `csp_custom_frames` | _vazio_ | Origens custom pra `frame-src`. 1 por linha |
| `csp_custom_styles` | _vazio_ | Origens custom pra `style-src` + `font-src`. 1 por linha |

Detalhes completos do sistema CSP em [10 — Content Security Policy](10-content-security-policy.md).

### Section: CSP Violation Reports (`sec_seg_csp_reports`) — *custom renderer*

Renderizada por `rd_csp_render_reports_panel()` em `inc/mod-csp.php`. Quando há reports:

- **Doughnut chart** (Wave 11 Fase G) mostrando distribuição de violações por directive
- **Tabela com 4 colunas:** When | Directive | Blocked URI | Document
- **Botão "Clear reports"** (nonce-protected) zera o FIFO

Quando não há reports, mostra empty state.

---

## 🔌 Aba 6 — Integrations (`?tab=integracoes`)

Tracking, marketing e widget de comunidade. **3 sections.**

### Section: Analytics (`sec_int_analytics`)

| Opção | Default | O que faz |
|---|---|---|
| `ga_id` | _vazio_ | Google Analytics 4 ID (formato `G-XXXXXXX`) |
| `clarity_id` | _vazio_ | Microsoft Clarity Project ID (heatmaps + session recordings gratuitos) |
| `plausible_domain` | _vazio_ | Domain registrado em plausible.io (privacy-friendly, cloud) |
| `umami_website_id` | _vazio_ | Website ID (UUID) do Umami self-hosted |
| `umami_script_url` | _vazio_ | URL do script.js do seu Umami |

### Section: Marketing & Conversion Pixels (`sec_int_marketing`)

| Opção | Default | O que faz |
|---|---|---|
| `facebook_pixel_id` | _vazio_ | Meta Pixel ID (retargeting + conversões) |
| `tiktok_pixel_id` | _vazio_ | TikTok Pixel ID |

### Section: Discord Widget (`sec_int_discord`)

| Opção | Default | O que faz |
|---|---|---|
| `discord_widget` | ✅ | Habilita widget do Discord na sidebar |
| `discord_id` | _vazio_ | Server ID numérico (snowflake 17-19 dígitos). Server precisa ter Widget ON em Server Settings |
| `discord_facade_logo` | _vazio_ | Logo custom pro facade Discord (~430x100px). Fallback: Custom Logo do WP |

---

## 🔍 Aba 7 — SEO (`?tab=seo`)

Site verification + Open Graph + sitemap + robots.txt. **4 sections.**

### Section: Site Verification (`sec_seo_verify`)

| Opção | Default | O que faz |
|---|---|---|
| `google_site_verification` | _vazio_ | Verification code do Google Search Console (só o `content` value, não a meta tag completa) |
| `bing_site_verification` | _vazio_ | Verification code do Bing Webmaster Tools |
| `custom_verification_meta` | _vazio_ | Textarea livre — cola `<meta>` tags completas de outros serviços (Pinterest `p:domain_verify`, Yandex `yandex-verification`, Naver, Facebook Domain, Norton SafeWeb, Apple News, etc.). Uma por linha. Sanitização strict via `wp_kses` aceita só `<meta>` com atributos `name`/`content`/`property`/`http-equiv`/`charset` — qualquer outra coisa é stripped no save |

### Section: Open Graph & Sharing (`sec_seo_og`)

| Opção | Default | O que faz |
|---|---|---|
| `enable_open_graph` | ✅ | Gera meta tags Open Graph (Facebook, Discord, WhatsApp, etc.) |
| `og_fallback_image` | _vazio_ | Imagem default pra compartilhamento quando post não tem featured image |

### Section: Sitemap (`sec_seo_sitemap`)

| Opção | Default | O que faz |
|---|---|---|
| `enable_sitemap` | ✅ | Gera `/wp-sitemap.xml` (WP 5.5+ nativo) |
| `sitemap_include_authors` | ❌ | Inclui `/wp-sitemap-users-1.xml`. Off pra solo blogs (evita duplicate content) |
| `sitemap_include_cpt` | ✅ | Inclui custom post types públicos no sitemap |

### Section: robots.txt (`sec_seo_robots`)

| Opção | Default | O que faz |
|---|---|---|
| `custom_robots_txt` | _vazio_ | **Substitui** o default WP completamente quando preenchido. O hint do field mostra os defaults do WP (`Disallow: /wp-admin/`, `Allow: /admin-ajax.php`, `Sitemap: ...`) num bloco `<pre>` pra copy-paste fácil quando admin quer customizar mantendo as regras base |

---

## 🔗 Aba 8 — Social Networks (`?tab=redes`)

URLs das redes sociais pra renderizar ícones no top bar + footer. **1 section.**

### Section: Your Social Network Links (`sec_redes`)

| Opção | Placeholder |
|---|---|
| `social_discord` | `https://discord.gg/...` |
| `social_telegram` | `https://t.me/...` |
| `social_youtube` | `https://youtube.com/@...` |
| `social_instagram` | `https://instagram.com/...` |
| `social_steam` | `https://steamcommunity.com/groups/...` |
| `social_twitter` | `https://x.com/...` |
| `social_facebook` | `https://facebook.com/...` |
| `social_whatsapp` | `https://wa.me/5511999999999` |

Todos default _vazio_. Quando vazio, ícone não renderiza.

---

## 💰 Aba 9 — Donations (`?tab=doacoes`)

Sistema de doações com 2 públicos. **2 sections.** Slug legacy `?tab=interface` redireciona pra cá.

### Section: International (`sec_doacoes_intl`)

| Opção | Placeholder |
|---|---|
| `github_sponsors` | `https://github.com/sponsors/seu-usuario` |
| `paypal_url` | `https://www.paypal.com/donate?hosted_button_id=XXXX` |
| `paypal_qrcode` | (media uploader) |

### Section: Brazil (PIX) (`sec_doacoes_br`)

| Opção | Placeholder |
|---|---|
| `pix_url` | `https://nubank.com.br/pagar/xxx` |
| `pix_qrcode` | (media uploader) |
| `pix_chave` | `email@domain.com` ou CPF/CNPJ |

---

## 📢 Aba 10 — Ads (`?tab=ads`)

Monetização. **2 sections.**

### Section: Global Script (`sec_ads_global`)

| Opção | O que faz |
|---|---|
| `ad_global` | Tag global do `<head>` (ex: AdSense Auto Ads). Aceita JS/HTML raw |

### Section: Banners by Position (`sec_ads_zones`)

| Opção | Posição | Tamanho típico |
|---|---|---|
| `ad_topo_desktop` | Header desktop | 728×90 |
| `ad_topo_mobile` | Header mobile (anchor fixa) | 320×100 |
| `ad_sidebar_top` | Sidebar topo | 300×250 |
| `ad_sidebar_sticky` | Sidebar sticky | 300×600 |

> **Nota CSP:** Os 5 campos `ad_*` aceitam `<script>` raw. Nonce é **injetado automaticamente** via `rd_csp_inject_nonce()` antes do echo (Wave 8.5) — não precisa adicionar nonce manualmente.

---

## 📊 Aba 11 — Statistics (`?tab=estatisticas`)

Dashboard read-only com top posts, total de views e gráfico de crescimento mensal. A coleta real de dados é controlada pelo toggle `enable_views_tracking` na section abaixo (também espelhado como switch inline no card "Statistics Tracking" do Dashboard tab).

### Section: Tracking Settings (`sec_stats_tracking`)

| Opção | Default | O que faz |
|---|---|---|
| `enable_views_tracking` | ✅ | Liga o tracker de views (IP único conta 1x por 30min; bots ignorados) |
| `views_number_format` | `full` | Formato no frontend: `full` (1.234) ou `compact` (1.2k / 1.2M) |
| `stats_top_limit` | `5` | Itens nos rankings (5/10/15/20/25/30) |

### Section: Dashboard (`sec_stats_dashboard`) — *custom renderer*

Renderizada por `rd_stats_render_dashboard()` em `inc/mod-stats.php`. Mostra:

- **K2 — Total Views:** big-number + breakdown (today/week/month/year com trend pills)
- **K1 — Top Posts by Views:** ranking com sub-tabs de janela temporal (All-time/Year/Month/Week), pódio dourado/prata/bronze no top 3
- **K3 — Top Posts by Comments:** ranking similar
- **K4 — Monthly Growth:** Chart.js de 12 meses
- **Botão "Refresh now":** força recompute de todos os transients (nonce-protected)

---

## 🔧 Aba 12 — Maintenance (`?tab=manutencao`)

Controle de acesso. **1 section.** Backup foi promovido pra aba própria na Wave 11.

### Section: Access Control (`sec_manut_access`)

| Opção | Default | O que faz |
|---|---|---|
| `maintenance_mode` | ❌ | Bloqueia visitantes regulares com tela "We'll be right back" (retorna HTTP 503 pra Google) |
| `maintenance_pass` | _vazio_ | Senha pra devs (bcrypt hash). Acesso: `/?rd-dev-login` + form |
| `maintenance_text` | _vazio_ | Texto custom da tela de manutenção (HTML básico permitido) |

---

## 💾 Aba 13 — Backup & Restore (`?tab=backup`)

Aba dedicada (Wave 11) — saiu de sub-section da Manutenção. **1 section custom-rendered.**

### Section: Backup & Restore (`sec_backup`) — *custom renderer*

Renderizada por `rd_backup_render_panel()` em `inc/mod-backup.php`. UI em grid de 2 colunas:

- **Card Export:** fieldset com 3 checkboxes (Settings / Category colors / Ad banners) + botão "Download backup JSON". URL atualiza dinamicamente via JS conforme seções marcadas.
- **Card Import:** input file pra JSON + preview do diff (will_update / will_add / will_keep) + botão "Apply".
- **Card Restore** *(aparece só se há snapshot)*: variant warning amber pra sinalizar ação reversível mas perigosa. Auto-snapshot salvo antes de cada import; restore é one-shot undo.

---

## 🎨 Configurações fora do painel ReloadeD

Algumas opções do tema vivem em telas nativas do WordPress, não no painel ReloadeD. Deixei elas separadas porque a UX nativa do WP já cobre bem o caso e duplicar seria redundante.

### Cor da categoria

Tela: **Posts → Categorias → (qualquer categoria) → Editar → campo "Color"**

Cada categoria pode ter uma cor de fundo própria pro chip que aparece nos cards do grid e no kicker do single post. A cor do texto (preto ou branco) é calculada automaticamente via luminância YIQ pra garantir contraste legível — sem trabalho extra pro admin.

Categorias sem cor configurada caem no cinza padrão (`#555555`). Lógica em `inc/mod-category-colors.php`.

### Posts por página

Tela: **Configurações → Leitura → "Páginas do blog mostram no máximo"**

Padrão WP funciona perfeitamente, sem necessidade de override no tema.

---

## 💾 Onde as opções vivem no banco

Tudo em uma única option do WP: `rd_settings` (array associativo com **98 keys** após Wave 11).

Defaults são aplicados via `rd_set_default_options()` (em `inc/panel.php`) que roda no hook `after_switch_theme` — só a primeira ativação populates as opções.

**Migração automática de keys ausentes** (`rd_migrate_missing_options()`): roda em `admin_init`, compara defaults atuais com `rd_settings` salvo, adiciona keys ausentes via `array_diff_key`. Garante que tema atualizado com options novas não deixa features dormentes.

Pra ler uma opção em código:

```php
// String / valor literal
$ga_id = rd_get_option('ga_id');

// Boolean (aceita 1, '1', true)
if ( rd_get_option_bool('enable_thumb_control') ) {
    // ...
}
```

Helpers definidos em `inc/panel.php`.

> **Nota Wave 11:** A reorganização do painel **não renomeou nenhuma option key**. Todas as 98 keys permanecem idênticas no banco — só mudou em qual aba/section elas aparecem visualmente. Zero migração de dados.

---

## 🧩 Sistema de componentes do painel

Pra dar consistência visual ao painel admin, existe um conjunto de **componentes reusáveis** com namespace `rd-p*` (Wave 11 Fase C). Use-os em **qualquer UI nova** que adicionar ao painel. Implementação em `inc/panel-helpers.php` (PHP) + `assets/css/admin-style.css` seção 1 (CSS).

### Componentes disponíveis

| Helper PHP | Classe CSS | Quando usar |
|---|---|---|
| `rd_panel_dash_open()` / `rd_panel_dash_close()` | `.rd-pdash` | Wrapper do dashboard de uma section custom-rendered |
| `rd_panel_dash_header($args)` | `.rd-pdash__header` | Header cinza com info à esquerda + ação à direita |
| `rd_panel_card_open($args)` / `rd_panel_card_close()` | `.rd-pcard` | Card branco — container principal de conteúdo |
| `rd_panel_badge($variant, $text)` | `.rd-pbadge--{variant}` | Chip de status inline (info/success/warning/danger/neutral) |
| `rd_panel_status($variant, $text)` | `.rd-pstatus--{variant}` | Banner colorido com left-border pra feedback de ação |
| `rd_panel_empty($message)` | `.rd-pempty` | Empty state "nada pra mostrar" |
| `rd_panel_section_header($args)` | `.rd-psection-header` | Header com ícone Dashicons + título + descrição. Emite `<h2>` |
| `rd_panel_register_section($id, $title, $icon, $page)` | — | Wrapper de `add_settings_section()` que renderiza header com ícone em vez do `<h2>` cru do Settings API |

**Card big-number** (Wave 11 Fase F): elementos opcionais dentro do `.rd-pcard__body` pra métricas destacadas:
- `.rd-pcard__big-number` — número grande (2.6rem, azul WP, tabular-nums)
- `.rd-pcard__big-hint` — hint pequeno abaixo

**Grid** (sem helper PHP — use direto): `.rd-pgrid` com modifiers `--two-cols`, `--three-cols`, `--sidebar-main`. Item span full-width: `.rd-pgrid__item--full`.

**Charts auto-render** (Wave 11 Fase G — módulo charts no bundle `assets/js/admin-panel.js`): qualquer `<canvas data-rd-chart-type="doughnut|bar" data-labels="..." data-values="...">` no DOM é detectado e inicializado automaticamente. Chart.js precisa estar enfileirado na aba — gate em `mod-stats.php → rd_stats_admin_enqueue()`.

### Variantes de cor (badge + status)

- **info** — azul WP (`#2271b1`) — informacional, Report-Only
- **success** — verde (`#007a39`) — ON, OK, Enabled
- **warning** — amber (`#d29922`) — Atenção, Pending
- **danger** — vermelho (`#d63638`) — Enforce, Erro
- **neutral** — cinza (`#757575`) — Inativo (só no badge)

### Exemplo de uso

```php
function meu_callback_de_section_custom() {
    $is_enabled = rd_get_option_bool( 'minha_feature' );

    rd_panel_dash_open();

    rd_panel_dash_header( array(
        'info'   => 'Status: ' . rd_panel_badge(
            $is_enabled ? 'success' : 'neutral',
            $is_enabled ? 'ON' : 'OFF'
        ),
        'action' => '<a href="' . esc_url( $action_url ) . '" class="button">'
            . esc_html__( 'Refresh', 'reloaded' ) . '</a>',
    ) );

    rd_panel_card_open( array(
        'title' => __( 'My Card', 'reloaded' ),
        'desc'  => __( 'Optional descriptive text.', 'reloaded' ),
        'hint'  => __( 'Optional <code>code snippet</code> hint at bottom.', 'reloaded' ),
    ) );

    if ( empty( $data ) ) {
        rd_panel_empty( __( 'Nothing to show yet — enable the toggle above.', 'reloaded' ) );
    } else {
        // ...renderiza conteúdo do card
    }

    rd_panel_card_close();
    rd_panel_dash_close();
}
```

### Regra de ouro

Código novo usa **sempre** os componentes `rd-p*`. Não criar classes ad-hoc com prefixo específico (`.rd-foo-card`, `.rd-bar-header`) — isso era o problema que a Wave 11 veio resolver. Quando o componente genérico não cobrir um caso específico (ex: ranking com pódio do Stats Dashboard), aí sim criar classe própria, mas como **complemento** dos componentes — não como sistema paralelo.
